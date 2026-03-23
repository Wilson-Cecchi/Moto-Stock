<?php
// includes/admin_sidebar.php
$admin_page = $admin_page ?? '';
$_u = getCurrentUser();
?>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon">⚙</span>
        <div>
            <div class="brand-name">MotoStock</div>
            <div class="brand-sub">Admin</div>
        </div>
    </div>
    <nav class="nav">
        <a href="/motostock/admin/"                   class="nav-item <?= $admin_page===''             ?'active':'' ?>"><span class="nav-icon">▣</span> Painel Admin</a>
        <a href="/motostock/admin/produtos.php"       class="nav-item <?= $admin_page==='produtos'     ?'active':'' ?>"><span class="nav-icon">▤</span> Produtos</a>
        <a href="/motostock/admin/venda.php"          class="nav-item <?= $admin_page==='venda'        ?'active':'' ?>"><span class="nav-icon">◎</span> Nova Venda</a>
        <a href="/motostock/admin/transferencia.php"  class="nav-item <?= $admin_page==='transfer'     ?'active':'' ?>"><span class="nav-icon">⇄</span> Transferência</a>
        <a href="/motostock/admin/metas.php"          class="nav-item <?= $admin_page==='metas'        ?'active':'' ?>"><span class="nav-icon">◉</span> Metas</a>
        <a href="/motostock/admin/solicitacoes.php"   class="nav-item <?= $admin_page==='solicitacoes' ?'active':'' ?>"><span class="nav-icon">◷</span> Solicitações</a>
        <a href="/motostock/admin/usuarios.php"       class="nav-item <?= $admin_page==='usuarios'     ?'active':'' ?>"><span class="nav-icon">◈</span> Usuários</a>
        <a href="/motostock/admin/exportar.php"       class="nav-item <?= $admin_page==='exportar'     ?'active':'' ?>"><span class="nav-icon">↓</span> Exportar Excel</a>    
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip" style="margin-bottom:10px">
            <div class="user-avatar"><?= strtoupper(substr($_u['nome'] ?? 'A', 0, 1)) ?></div>
            <div>
                <div class="user-chip-name"><?= htmlspecialchars($_u['nome'] ?? '') ?></div>
                <div class="user-chip-role">Admin</div>
            </div>
        </div>
        <a href="/motostock/" style="color:var(--text3);font-size:.75rem;text-decoration:none;display:block;margin-bottom:6px;padding:4px 0">← Ver sistema</a>
        <a href="/motostock/logout.php" style="color:var(--danger);font-size:.75rem;text-decoration:none;padding:4px 0;display:block">→ Sair</a>
    </div>
</aside>
