<?php
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'dashboard';
$title = 'Dashboard';
$db    = getDB();
$loja_filtro = getLojaFiltro(); // 0 = admin (todas), >0 = gerente (só a sua loja)
$w_venda   = $loja_filtro ? "WHERE loja_id = $loja_filtro"     : '';
$w_produto = $loja_filtro ? "WHERE loja_id = $loja_filtro"     : '';
$w_join    = $loja_filtro ? "WHERE l.id    = $loja_filtro"     : '';
$w_v_join  = $loja_filtro ? "AND v.loja_id = $loja_filtro"     : '';

// --- KPIs gerais ---
$kpi = $db->query("
    SELECT
        COUNT(*)            AS total_vendas,
        SUM(total)          AS receita_total,
        SUM(qtd)            AS itens_vendidos,
        ROUND(AVG(total),2) AS ticket_medio
    FROM vendas $w_venda
")->fetch();

$total_produtos = $db->query("SELECT COUNT(*) FROM produtos $w_produto")->fetchColumn();
$alertas = $db->query("
    SELECT COUNT(*) FROM produtos WHERE estoque <= estoque_minimo
    " . ($loja_filtro ? "AND loja_id = $loja_filtro" : '')
)->fetchColumn();

// --- Receita por loja ---
$por_loja = $db->query("
    SELECT l.id, l.nome, l.tipo,
        COALESCE(SUM(v.total),0)   AS receita,
        COALESCE(COUNT(v.id),0)    AS n_vendas
    FROM lojas l
    LEFT JOIN vendas v ON v.loja_id = l.id $w_v_join
    $w_join
    GROUP BY l.id ORDER BY receita DESC
")->fetchAll();

$max_receita = max(array_column($por_loja, 'receita')) ?: 1;

// --- Receita por mês ---
$por_mes = $db->query("
    SELECT MONTH(data_venda) AS mes, SUM(total) AS receita, COUNT(*) AS n
    FROM vendas $w_venda GROUP BY mes ORDER BY mes
")->fetchAll();

// --- Receita por categoria ---
$por_cat = $db->query("
    SELECT categoria, SUM(total) AS receita, SUM(qtd) AS qtd
    FROM vendas $w_venda GROUP BY categoria ORDER BY receita DESC
")->fetchAll();

// --- Top 5 produtos mais vendidos ---
$top5 = $db->query("
    SELECT produto, categoria, SUM(qtd) AS qtd_total, SUM(total) AS receita
    FROM vendas $w_venda GROUP BY produto, categoria
    ORDER BY qtd_total DESC LIMIT 5
")->fetchAll();

// --- Metas do período --- motos
$metas_stmt = $db->query("
    SELECT m.loja_id, l.nome AS loja_nome, m.mes, m.ano, m.meta_valor,
           COALESCE(SUM(v.total), 0) AS realizado
    FROM metas m
    JOIN lojas l ON l.id = m.loja_id
    LEFT JOIN vendas v ON v.loja_id = m.loja_id
        AND MONTH(v.data_venda) = m.mes AND YEAR(v.data_venda) = m.ano
    WHERE m.ano = 2025
    " . ($loja_filtro ? "AND m.loja_id = $loja_filtro" : '') . "
    GROUP BY m.loja_id, m.mes, m.ano, m.meta_valor
    ORDER BY m.loja_id, m.mes
");
$metas_raw = $metas_stmt->fetchAll();

// Agrupa por loja: soma meta e realizado no período
$metas_por_loja = [];
foreach ($metas_raw as $m) {
    $lid = $m['loja_id'];
    if (!isset($metas_por_loja[$lid])) {
        $metas_por_loja[$lid] = ['nome' => $m['loja_nome'], 'meta' => 0, 'realizado' => 0];
    }
    $metas_por_loja[$lid]['meta']      += $m['meta_valor'];
    $metas_por_loja[$lid]['realizado'] += $m['realizado'];
}

$MESES_PT = ['','Jan','Fev','Mar'];

include __DIR__ . '/includes/header.php';
?>

<!-- KPI CARDS -->
<div class="kpi-grid">
    <div class="kpi-card accent">
        <div class="kpi-label">Receita Total</div>
        <div class="kpi-value"><?= brl($kpi['receita_total']) ?></div>
        <div class="kpi-sub">Jan — Mar 2025</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Vendas Realizadas</div>
        <div class="kpi-value"><?= number_format($kpi['total_vendas']) ?></div>
        <div class="kpi-sub">3 lojas ativas</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Itens Vendidos</div>
        <div class="kpi-value"><?= number_format($kpi['itens_vendidos']) ?></div>
        <div class="kpi-sub">unidades totais</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ticket Médio</div>
        <div class="kpi-value"><?= brl($kpi['ticket_medio']) ?></div>
        <div class="kpi-sub">por transação</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Produtos Cadastrados</div>
        <div class="kpi-value"><?= $total_produtos ?></div>
        <div class="kpi-sub">em 5 lojas</div>
    </div>
    <div class="kpi-card <?= $alertas > 0 ? 'danger' : 'ok' ?>">
        <div class="kpi-label">Alertas de Estoque</div>
        <div class="kpi-value"><?= $alertas ?></div>
        <div class="kpi-sub"><?= $alertas > 0 ? 'produtos abaixo do mínimo' : 'estoque regularizado' ?></div>
    </div>
</div>

<?php if ($alertas > 0): ?>
<div class="alert-strip">
    <span class="alert-icon">⚠</span>
    <div><strong><?= $alertas ?> produto(s)</strong> com estoque abaixo do nível mínimo.
    <a href="/motostock/estoque.php?alerta=1" style="color:var(--accent);margin-left:8px;">Ver detalhes →</a></div>
</div>
<?php endif; ?>

<!-- CHARTS ROW -->
<div class="section-grid">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Receita Mensal</span>
            <span class="panel-sub">Jan–Mar 2026</span>
        </div>
        <div class="panel-body">
            <div class="chart-wrap">
                <canvas id="chartMes"></canvas>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Receita por Categoria</span>
            <span class="panel-sub">todas as lojas</span>
        </div>
        <div class="panel-body">
            <div class="chart-wrap">
                <canvas id="chartCat"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- LOJAS + TOP 5 -->
<div class="section-grid thirds">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Top 5 Produtos</span>
            <span class="panel-sub">por quantidade vendida</span>
        </div>
        <div class="panel-body" style="padding:0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produto</th>
                        <th>Cat.</th>
                        <th class="num">Qtd</th>
                        <th class="num">Receita</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($top5 as $i => $p): ?>
                    <tr>
                        <td style="color:var(--accent);font-weight:700;font-family:'Rajdhani',sans-serif"><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($p['produto']) ?></td>
                        <td><span class="badge badge-cat"><?= $p['categoria'] ?></span></td>
                        <td class="num mono"><?= $p['qtd_total'] ?></td>
                        <td class="num mono"><?= brl($p['receita']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Ranking de Lojas</span>
            <span class="panel-sub">por receita</span>
        </div>
        <div class="panel-body">
            <div class="loja-rank">
            <?php foreach ($por_loja as $l): ?>
                <div class="loja-rank-item">
                    <div class="loja-rank-header">
                        <span class="loja-rank-name"><?= htmlspecialchars($l['nome']) ?></span>
                        <span class="loja-rank-val"><?= brl($l['receita']) ?></span>
                    </div>
                    <div class="loja-rank-track">
                        <div class="loja-rank-fill" style="width:<?= $max_receita > 0 ? round($l['receita'] / $max_receita * 100) : 0 ?>%"></div>
                    </div>
                    <div style="font-size:.72rem;color:var(--text3);margin-top:3px;font-family:'Fira Code',monospace">
                        <?= $l['n_vendas'] ?> vendas
                        <?= $l['receita'] == 0 ? ' · sem dados ainda' : '' ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
const chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: { color: '#94a3b8', font: { family: 'Fira Code', size: 11 } }
        }
    }
};

// Receita Mensal
new Chart(document.getElementById('chartMes'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $MESES_PT[(int)$r['mes']], $por_mes)) ?>,
        datasets: [{
            label: 'Receita (R$)',
            data: <?= json_encode(array_column($por_mes, 'receita')) ?>,
            backgroundColor: '#f9731650',
            borderColor: '#f97316',
            borderWidth: 2,
            borderRadius: 5,
        }]
    },
    options: { ...chartDefaults, scales: {
        x: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } },
        y: { ticks: { color:'#94a3b8', callback: v => 'R$' + (v/1000).toFixed(0) + 'k' }, grid: { color:'#1f2231' } }
    }}
});

// Receita por Categoria
new Chart(document.getElementById('chartCat'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($por_cat, 'categoria')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($por_cat, 'receita')) ?>,
            backgroundColor: ['#f97316','#fb923c','#3b82f6','#a78bfa','#22c55e'],
            borderColor: '#111318',
            borderWidth: 3,
        }]
    },
    options: { ...chartDefaults }
});
</script>

<?php if (!empty($metas_por_loja)): ?>
<!-- METAS DE VENDAS -->
<div class="panel" style="margin-bottom:24px">
    <div class="panel-header">
        <span class="panel-title">Meta de Vendas — Jan–Mar 2026</span>
        <?php if (isAdmin()): ?>
        <a href="/motostock/admin/metas.php" class="no-print" style="font-size:.75rem;color:var(--accent);text-decoration:none">Editar metas →</a>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <?php foreach ($metas_por_loja as $m):
            $pct = $m['meta'] > 0 ? min(100, round($m['realizado'] / $m['meta'] * 100)) : 0;
            $cor = $pct >= 100 ? 'var(--ok)' : ($pct >= 70 ? 'var(--warn)' : 'var(--danger)');
        ?>
        <div class="meta-bar-wrap">
            <div class="meta-bar-header">
                <span class="meta-bar-loja"><?= htmlspecialchars($m['nome']) ?></span>
                <span class="meta-bar-vals"><?= brl($m['realizado']) ?> / <?= brl($m['meta']) ?></span>
            </div>
            <div class="meta-bar-track">
                <div class="meta-bar-fill" style="width:<?= $pct ?>%;background:<?= $cor ?>"></div>
            </div>
            <div class="meta-pct"><?= $pct ?>%<?= $pct >= 100 ? ' ✓ Meta atingida' : '' ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
