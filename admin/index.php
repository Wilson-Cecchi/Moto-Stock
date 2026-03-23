<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$page  = 'admin';
$title = 'Administração';
$db    = getDB();

$total_produtos = $db->query("SELECT COUNT(*) FROM produtos")->fetchColumn();
$total_vendas   = $db->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
$alertas        = $db->query("SELECT COUNT(*) FROM produtos WHERE estoque <= estoque_minimo")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Administração</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .admin-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:28px; }
        .admin-card {
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 28px 24px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: border-color .15s, background .15s;
        }
        .admin-card:hover { border-color: var(--accent); background: var(--surface3); }
        .admin-card-icon { font-size: 2rem; line-height: 1; }
        .admin-card-title { font-family:'Rajdhani',sans-serif; font-size:1.15rem; font-weight:700; letter-spacing:.04em; }
        .admin-card-desc { font-size:.82rem; color:var(--text3); line-height:1.5; }
    </style>
</head>
<body>
<?php $admin_page = ''; include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Painel Administrativo</h1></div>
        <div class="topbar-meta">MotoStock · <?= date('d/m/Y') ?></div>
    </header>
    <div class="content">
        <div class="kpi-grid" style="margin-bottom:28px">
            <div class="kpi-card"><div class="kpi-label">Produtos</div><div class="kpi-value"><?= $total_produtos ?></div></div>
            <div class="kpi-card"><div class="kpi-label">Vendas Registradas</div><div class="kpi-value"><?= $total_vendas ?></div></div>
            <div class="kpi-card <?= $alertas > 0 ? 'danger' : 'ok' ?>">
                <div class="kpi-label">Alertas de Estoque</div>
                <div class="kpi-value"><?= $alertas ?></div>
            </div>
        </div>

        <div class="admin-grid">
            <a href="/motostock/admin/produtos.php" class="admin-card">
                <div class="admin-card-icon">📦</div>
                <div class="admin-card-title">Gerenciar Produtos</div>
                <div class="admin-card-desc">Adicionar, editar ou remover produtos de qualquer loja. Ajuste preços e estoque.</div>
            </a>
            <a href="/motostock/admin/venda.php" class="admin-card">
                <div class="admin-card-icon">🛒</div>
                <div class="admin-card-title">Registrar Venda</div>
                <div class="admin-card-desc">Lançar uma nova venda no sistema. O estoque é atualizado automaticamente.</div>
            </a>
            <a href="/motostock/admin/exportar.php" class="admin-card">
                <div class="admin-card-icon">📊</div>
                <div class="admin-card-title">Exportar para Excel</div>
                <div class="admin-card-desc">Gera um arquivo .xlsx com produtos, vendas, resumo por loja e previsão de 6 meses.</div>
            </a>
            <a href="/motostock/admin/metas.php" class="admin-card">
                <div class="admin-card-icon">🎯</div>
                <div class="admin-card-title">Metas de Vendas</div>
                <div class="admin-card-desc">Definir e acompanhar metas mensais por loja. Barras de progresso no dashboard.</div>
            </a>
            <a href="/motostock/admin/solicitacoes.php" class="admin-card">
                <div class="admin-card-icon">◷</div>
                <div class="admin-card-title">Solicitações</div>
                <div class="admin-card-desc">Aprovar ou rejeitar reposições e transferências solicitadas pelos funcionários.</div>
            </a>
            <a href="/motostock/admin/usuarios.php" class="admin-card">
                <div class="admin-card-icon">◈</div>
                <div class="admin-card-title">Usuários</div>
                <div class="admin-card-desc">Trocar senha e criar ou remover funcionários do sistema.</div>
            </a>
        </div>
    </div>
</main>
</body>
</html>