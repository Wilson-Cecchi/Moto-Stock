<?php
// ============================================================ motos
// setup_usuarios.php — Criação inicial de usuários
// ⚠ Acesse UMA vez via browser, depois DELETE este arquivo!
// URL: http://localhost/motostock/setup_usuarios.php
// ============================================================
require_once __DIR__ . '/config.php';

$db = getDB();

// Verifica se tabela existe
$tabela = $db->query("SHOW TABLES LIKE 'usuarios'")->fetchColumn();
if (!$tabela) {
    die('<div style="font-family:monospace;color:#ef4444;padding:2rem;">
        Tabela <b>usuarios</b> não encontrada.<br>
        Execute <b>setup_v2.sql</b> no phpMyAdmin primeiro.
    </div>');
}

// Usuários a criar (usuario => [nome, senha, nivel, loja_id])
$lista = [
    'admin'    => ['Administrador Geral',    'umasenhaboa', 'admin',    null],
    'gerente1' => ['Gerente — Matriz',       'loja1234',    'gerente',     1],
    'gerente2' => ['Gerente — Filial 1',     'loja1234',    'gerente',     2],
    'gerente3' => ['Gerente — Filial 2',     'loja1234',    'gerente',     3],
    'gerente4' => ['Gerente — Filial 3',     'loja1234',    'gerente',     4],
    'gerente5' => ['Gerente — Filial 4',     'loja1234',    'gerente',     5],
];

$stmt = $db->prepare("
    INSERT IGNORE INTO usuarios (nome, usuario, senha, nivel_acesso, loja_id)
    VALUES (?, ?, ?, ?, ?)
");

$ok = [];
foreach ($lista as $usr => [$nome, $senha, $nivel, $loja]) {
    $hash = password_hash($senha, PASSWORD_BCRYPT);
    $stmt->execute([$nome, $usr, $hash, $nivel, $loja]);
    $ok[] = "$usr ($nivel)" . ($loja ? " → loja $loja" : '');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>MotoStock — Setup Usuários</title>
    <style>
        body { font-family: monospace; background: #0b0d12; color: #e2e8f0; padding: 2rem; }
        h2  { color: #f97316; margin-bottom: 1rem; }
        ul  { line-height: 2; }
        .warn { color: #ef4444; margin-top: 1.5rem; font-size: 1.1rem; }
        table { border-collapse: collapse; margin-top: 1rem; }
        td, th { border: 1px solid #262a3a; padding: 8px 16px; }
        th { background: #181b24; color: #f97316; }
    </style>
</head>
<body>
    <h2>✅ Usuários criados com sucesso</h2>
    <table>
        <tr><th>Usuário</th><th>Senha</th><th>Nível</th></tr>
        <tr><td>admin</td><td>umasenhaboa</td><td>Admin</td></tr>
        <tr><td>gerente1 … gerente5</td><td>loja1234</td><td>Gerente</td></tr>
    </table>
    <p class="warn">⚠ DELETE este arquivo agora: <code>/motostock/setup_usuarios.php</code></p>
</body>
</html>
