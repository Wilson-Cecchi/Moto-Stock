<?php
// includes/header.php
$page = $page ?? 'dashboard';

// Badge de alertas de estoque (filtrado por loja se gerente)
$_u_loja = getLojaFiltro();
$_badge_sql = $_u_loja ? "WHERE estoque <= estoque_minimo AND loja_id = $_u_loja"
                       : "WHERE estoque <= estoque_minimo";
$_badge_count = (int) getDB()->query("SELECT COUNT(*) FROM produtos $_badge_sql")->fetchColumn();
$_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — <?= htmlspecialchars($title ?? 'Dashboard') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .nav-badge {
            margin-left: auto;
            background: var(--danger);
            color: #fff;
            font-size: .65rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 99px;
            min-width: 18px;
            text-align: center;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: var(--surface3);
            border-radius: 6px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
        }
        .user-chip-name { font-size:.78rem; font-weight:500; color:var(--text2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:130px; }
        .user-chip-role { font-size:.65rem; color:var(--text3); text-transform:uppercase; letter-spacing:.06em; }
        .user-avatar { width:28px; height:28px; background:var(--accent-dim); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; color:var(--accent); flex-shrink:0; }
        .meta-bar-wrap { margin-bottom: 20px; }
        .meta-bar-header { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:5px; }
        .meta-bar-loja { font-size:.85rem; font-weight:500; }
        .meta-bar-vals { font-size:.75rem; color:var(--text2); font-family:'Fira Code',monospace; }
        .meta-bar-track { height:8px; background:var(--surface3); border-radius:4px; overflow:hidden; }
        .meta-bar-fill  { height:100%; border-radius:4px; transition:width .4s ease; }
        .meta-pct { font-size:.7rem; color:var(--text3); margin-top:3px; text-align:right; }
        @media print {
            .sidebar, .topbar, .no-print { display:none !important; }
            .main { margin-left:0 !important; }
            body  { background:#fff !important; color:#000 !important; }
        }
    </style>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('active');
            document.getElementById('hamburgerBtn').classList.toggle('open');
        }
        function closeSidebar() {
            document.querySelector('.sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
            document.getElementById('hamburgerBtn').classList.remove('open');
        }
    </script>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon">⚙</span>
        <div>
            <div class="brand-name">MotoStock</div>
            <div class="brand-sub">Sistema de Gestão</div>
        </div>
    </div>
    <nav class="nav">
        <a href="/motostock/index.php" class="nav-item <?= $page==='dashboard'?'active':'' ?>">
            <span class="nav-icon">▣</span> Dashboard
        </a>
        <a href="/motostock/estoque.php" class="nav-item <?= $page==='estoque'?'active':'' ?>">
            <span class="nav-icon">▤</span> Estoque
            <?php if ($_badge_count > 0): ?>
            <span class="nav-badge"><?= $_badge_count ?></span>
            <?php endif; ?>
        </a>
        <a href="/motostock/previsao.php" class="nav-item <?= $page==='previsao'?'active':'' ?>">
            <span class="nav-icon">◈</span> Previsão 6 Meses
        </a>
        <a href="/motostock/vendas.php" class="nav-item <?= $page==='vendas'?'active':'' ?>">
            <span class="nav-icon">◎</span> Vendas
        </a>
        <?php if (($_user['nivel_acesso'] ?? '') === 'gerente' || ($_user['nivel_acesso'] ?? '') === 'admin'): ?>
        <a href="/motostock/gerente/solicitacoes.php" class="nav-item <?= $page==='solicitacoes'?'active':'' ?>">
            <span class="nav-icon">◷</span> Solicitações
        </a>
        <?php endif; ?>
        <a href="/motostock/relatorio.php" class="nav-item <?= $page==='relatorio'?'active':'' ?>">
            <span class="nav-icon">⎙</span> Relatório PDF
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip">
            <div class="user-avatar"><?= strtoupper(substr($_user['nome'] ?? 'U', 0, 1)) ?></div>
            <div>
                <div class="user-chip-name"><?= htmlspecialchars($_user['nome'] ?? '') ?></div>
                <div class="user-chip-role"><?= ($_user['nivel_acesso'] ?? '') === 'admin' ? 'Admin' : 'Gerente' ?></div>
            </div>
        </div>
        <?php if (($_user['nivel_acesso'] ?? '') === 'admin'): ?>
        <a href="/motostock/admin/" class="nav-item" style="margin-bottom:6px">
            <span class="nav-icon">⚙</span> Admin
        </a>
        <?php endif; ?>
        <a href="/motostock/logout.php" class="nav-item" style="color:var(--danger)">
            <span class="nav-icon">→</span> Sair
        </a>
        <div class="sf-label" style="margin-top:10px">Dados: Jan–Mar 2026</div>
        <div class="sf-label">5 lojas · 100 produtos</div>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<main class="main">
    <header class="topbar">
        <button class="hamburger no-print" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-title">
            <h1><?= htmlspecialchars($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="topbar-meta">
            <?php if (($_user['nivel_acesso'] ?? '') === 'gerente'): ?>
            <span style="color:var(--accent);margin-right:8px">●</span>
            <?php endif; ?>
            <?= date('d/m/Y') ?> · MotoStock Distribuidora
        </div>
    </header>
    <div class="content">