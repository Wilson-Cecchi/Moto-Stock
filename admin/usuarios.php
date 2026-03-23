<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db  = getDB();
$msg = '';
$err = '';

$lojas = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();

// Trocar senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'trocar_senha') {
        $id    = (int)$_POST['id'];
        $senha = $_POST['senha'] ?? '';
        $conf  = $_POST['confirma'] ?? '';
        if (strlen($senha) < 6) {
            $err = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif ($senha !== $conf) {
            $err = 'As senhas não coincidem.';
        } else {
            $hash = password_hash($senha, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?")->execute([$hash, $id]);
            $msg = 'Senha atualizada com sucesso!';
        }
    }

    elseif ($acao === 'criar_funcionario') {
        $nome    = trim($_POST['nome']    ?? '');
        $usuario = trim($_POST['usuario'] ?? '');
        $senha   = $_POST['senha']        ?? '';
        $loja_id = (int)($_POST['loja_id'] ?? 0);

        if (!$nome || !$usuario || strlen($senha) < 6 || !$loja_id) {
            $err = 'Preencha todos os campos. Senha mínima: 6 caracteres.';
        } else {
            $existe = $db->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $existe->execute([$usuario]);
            if ($existe->fetch()) {
                $err = 'Esse nome de usuário já existe.';
            } else {
                $hash = password_hash($senha, PASSWORD_BCRYPT);
                $db->prepare("INSERT INTO usuarios (nome, usuario, senha, nivel_acesso, loja_id) VALUES (?,?,?,'funcionario',?)")
                   ->execute([$nome, $usuario, $hash, $loja_id]);
                $msg = "Funcionário \"$usuario\" criado com sucesso!";
            }
        }
    }

    elseif ($acao === 'deletar') {
        $id = (int)$_POST['id'];
        // Não permite deletar o próprio admin
        $alvo = $db->prepare("SELECT nivel_acesso FROM usuarios WHERE id = ?");
        $alvo->execute([$id]);
        $alvo = $alvo->fetch();
        if ($alvo && $alvo['nivel_acesso'] === 'admin') {
            $err = 'Não é possível deletar uma conta admin.';
        } else {
            $db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
            $msg = 'Usuário removido.';
        }
    }
}

$usuarios = $db->query("
    SELECT u.id, u.nome, u.usuario, u.nivel_acesso, u.ativo, l.nome AS loja_nome
    FROM usuarios u
    LEFT JOIN lojas l ON l.id = u.loja_id
    ORDER BY u.nivel_acesso DESC, u.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Usuários</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .modal-bg {
            display: none;
            position: fixed; inset: 0;
            background: #000a;
            z-index: 200;
            align-items: center;
            justify-content: center;
        }
        .modal-bg.open { display: flex; }
        .modal {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px 28px;
            width: 340px;
        }
        .modal h3 { font-family:'Rajdhani',sans-serif; font-size:1.2rem; margin-bottom:18px; color:var(--accent); }
        .field { margin-bottom: 14px; }
        .field label { display:block; font-size:.75rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; margin-bottom:5px; }
        .field input, .field select {
            width:100%; background:var(--surface3); border:1px solid var(--border2);
            color:var(--text); padding:9px 12px; border-radius:6px; font-size:.88rem;
            outline:none; font-family:'Inter',sans-serif;
        }
        .field input:focus, .field select:focus { border-color: var(--accent); }
        .save-btn, .btn-save {
            padding:10px 24px; background:var(--accent); color:#000;
            border:none; border-radius:6px; font-size:.95rem; font-weight:700;
            cursor:pointer; font-family:'Rajdhani',sans-serif; letter-spacing:.05em;
            text-transform:uppercase; margin-top:4px;
        }
        .save-btn:hover, .btn-save:hover { opacity:.85; }
        .btn-cancel { display:block; text-align:center; margin-top:12px; font-size:.8rem; color:var(--text3); cursor:pointer; }
        .btn-cancel:hover { color:var(--text); }
        .btn-edit {
            padding:5px 14px; background:var(--surface3); border:1px solid var(--border2);
            color:var(--text2); border-radius:5px; font-size:.78rem; cursor:pointer;
        }
        .btn-edit:hover { border-color:var(--accent); color:var(--accent); }
        .msg-ok  { background:var(--ok-bg); border:1px solid var(--ok); color:var(--ok); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .msg-err { background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .badge-admin   { background:#7c3a0d; color:#f97316; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:700; }
        .badge-gerente { background:#1e3a5f; color:#60a5fa; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:700; }
        .badge-func    { background:#1a2e1a; color:#4ade80; padding:2px 8px; border-radius:4px; font-size:.72rem; font-weight:700; }
    </style>
</head>
<body>
<?php $admin_page = 'usuarios'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Gerenciar Usuários</h1></div>
        <div class="topbar-meta">MotoStock · <?= date('d/m/Y') ?></div>
    </header>
    <div class="content">

        <?php if ($msg): ?><div class="msg-ok">✓ <?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="msg-err">✗ <?= $err ?></div><?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Usuários do Sistema</span>
                <span class="panel-sub">clique em "Trocar Senha" para alterar</span>
            </div>
            <div class="panel-body" style="padding:0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Usuário</th>
                            <th>Nível</th>
                            <th>Loja</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nome']) ?></td>
                            <td class="mono"><?= htmlspecialchars($u['usuario']) ?></td>
                            <td>
                                <span class="<?= $u['nivel_acesso'] === 'admin' ? 'badge-admin' : ($u['nivel_acesso'] === 'gerente' ? 'badge-gerente' : 'badge-func') ?>">
                                    <?= $u['nivel_acesso'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['loja_nome'] ?? '— todas —') ?></td>
                            <td style="white-space:nowrap">
                                <button class="btn-edit"
                                    onclick="abrirModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nome']) ?>')">
                                    Trocar Senha
                                </button>
                                <?php if ($u['nivel_acesso'] !== 'admin'): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('Deletar <?= htmlspecialchars($u['usuario']) ?>?')">
                                    <input type="hidden" name="acao" value="deletar">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn-edit" style="color:var(--danger);border-color:var(--danger);margin-left:4px">Deletar</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CRIAR FUNCIONÁRIO -->
        <div class="panel" style="max-width:520px; margin-top:24px">
            <div class="panel-header">
                <span class="panel-title">Criar Funcionário</span>
            </div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="acao" value="criar_funcionario">
                    <div class="field">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" required>
                    </div>
                    <div class="field">
                        <label>Usuário (login)</label>
                        <input type="text" name="usuario" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label>Senha</label>
                        <input type="password" name="senha" required minlength="6" autocomplete="new-password">
                    </div>
                    <div class="field">
                        <label>Loja</label>
                        <select name="loja_id" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($lojas as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="save-btn">+ Criar Funcionário</button>
                </form>
            </div>
        </div>

    </div>
</main>

<!-- Modal trocar senha -->
<div class="modal-bg" id="modal">
    <div class="modal">
        <h3>🔑 Trocar Senha</h3>
        <p id="modal-nome" style="font-size:.82rem;color:var(--text2);margin-bottom:18px"></p>
        <form method="post">
            <input type="hidden" name="acao" value="trocar_senha">
            <input type="hidden" name="id" id="modal-id">
            <div class="field">
                <label>Nova Senha</label>
                <input type="password" name="senha" id="modal-senha" autocomplete="new-password" minlength="6">
            </div>
            <div class="field">
                <label>Confirmar Senha</label>
                <input type="password" name="confirma" autocomplete="new-password" minlength="6">
            </div>
            <button type="submit" class="btn-save">Salvar</button>
        </form>
        <span class="btn-cancel" onclick="fecharModal()">Cancelar</span>
    </div>
</div>

<script>
function abrirModal(id, nome) {
    document.getElementById('modal-id').value   = id;
    document.getElementById('modal-nome').textContent = nome;
    document.getElementById('modal-senha').value = '';
    document.getElementById('modal').classList.add('open');
    document.getElementById('modal-senha').focus();
}
function fecharModal() {
    document.getElementById('modal').classList.remove('open');
}
document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
</script>

</body>
</html>