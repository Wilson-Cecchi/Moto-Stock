<?php
// login.php — Página de login unificada (admin e gerentes)
require_once __DIR__ . '/config.php';

// Se já está logado, redireciona
if (!empty($_SESSION['usuario'])) {
    header('Location: /motostock/');
    exit;
}

$erro = '';
$next = $_GET['next'] ?? '/motostock/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha   = $_POST['senha'] ?? '';
    $next    = $_POST['next']   ?? '/motostock/';

    if ($usuario && $senha) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE usuario = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['usuario'] = [
                'id'           => $user['id'],
                'nome'         => $user['nome'],
                'usuario'      => $user['usuario'],
                'nivel_acesso' => $user['nivel_acesso'],
                'loja_id'      => $user['loja_id'],
            ];
            // Admin vai para o painel admin, gerente vai para o dashboard
            $destino = ($next !== '/motostock/')
                ? $next
                : ($user['nivel_acesso'] === 'admin'       ? '/motostock/admin/'
                  : ($user['nivel_acesso'] === 'funcionario' ? '/motostock/funcionario.php'
                  : '/motostock/'));
            header('Location: ' . $destino);
            exit;
        }
    }
    $erro = 'Usuário ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Entrar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; flex-direction:column; }
        .login-box {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px 36px;
            width: 360px;
        }
        .login-logo { font-family:'Rajdhani',sans-serif; font-size:2rem; font-weight:700; color:var(--accent); margin-bottom:4px; }
        .login-sub  { font-size:.8rem; color:var(--text3); margin-bottom:28px; }
        .field { margin-bottom: 16px; }
        .field label { display:block; font-size:.75rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; margin-bottom:6px; }
        .field input {
            width:100%; background:var(--surface3); border:1px solid var(--border2);
            color:var(--text); padding:10px 14px; border-radius:6px; font-size:.9rem;
            outline:none; font-family:'Inter',sans-serif;
        }
        .field input:focus { border-color:var(--accent); }
        .login-btn {
            width:100%; padding:11px; background:var(--accent); color:#000;
            border:none; border-radius:6px; font-size:1rem; font-weight:700;
            cursor:pointer; font-family:'Rajdhani',sans-serif; letter-spacing:.06em;
            text-transform:uppercase; margin-top:8px;
        }
        .login-btn:hover { opacity:.85; }
        .erro { background:var(--danger-bg); border:1px solid #7f1d1d; color:var(--danger);
                padding:10px 14px; border-radius:6px; font-size:.84rem; margin-bottom:16px; }
        .hint { font-size:.72rem; color:var(--text3); text-align:center; margin-top:16px; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="login-logo">⚙ MotoStock</div>
        <div class="login-sub">Sistema de Gestão — Entrar</div>

        <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
            <div class="field">
                <label>Usuário</label>
                <input type="text" name="usuario" autofocus autocomplete="username">
            </div>
            <div class="field">
                <label>Senha</label>
                <input type="password" name="senha" autocomplete="current-password">
            </div>
            <button class="login-btn" type="submit">Entrar</button>
        </form>
        <div class="hint">Admin e gerentes usam este mesmo login.</div>
    </div>
</body>
</html>
