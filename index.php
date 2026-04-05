<?php
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'dashboard';
$title = 'Dashboard';
$db    = getDB();
$loja_filtro = getLojaFiltro(); // 0 = admin (todas), >0 = gerente (só a sua loja)

// --- Filtro de período ---
$anos_disp = [2024, 2025, 2026];
$ano_sel   = isset($_GET['ano'])  && in_array((int)$_GET['ano'],  $anos_disp) ? (int)$_GET['ano']  : 2025;
$mes_ini   = isset($_GET['mes_ini']) ? max(1, min(12, (int)$_GET['mes_ini'])) : 1;
$mes_fim   = isset($_GET['mes_fim']) ? max(1, min(12, (int)$_GET['mes_fim'])) : 12;
if ($mes_ini > $mes_fim) $mes_fim = $mes_ini;

$w_periodo = "YEAR(data_venda) = $ano_sel AND MONTH(data_venda) BETWEEN $mes_ini AND $mes_fim";

$w_venda   = $loja_filtro
    ? "WHERE loja_id = $loja_filtro AND $w_periodo"
    : "WHERE $w_periodo";
$w_produto = $loja_filtro ? "WHERE loja_id = $loja_filtro"     : '';
$w_join    = $loja_filtro ? "WHERE l.id    = $loja_filtro"     : '';
$w_v_join  = $loja_filtro
    ? "AND v.loja_id = $loja_filtro AND $w_periodo"
    : "AND $w_periodo";

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

// --- Total de clientes ---
$total_clientes = $db->query(
    $loja_filtro
        ? "SELECT COUNT(*) FROM clientes WHERE loja_id = $loja_filtro"
        : "SELECT COUNT(*) FROM clientes"
)->fetchColumn();

// --- Receita por estado ---
$por_estado = $db->query("
    SELECT l.estado,
           COALESCE(SUM(v.total), 0) AS receita,
           COALESCE(COUNT(v.id), 0)  AS n_vendas
    FROM lojas l
    LEFT JOIN vendas v ON v.loja_id = l.id $w_v_join
    $w_join
    GROUP BY l.estado
    ORDER BY receita DESC
")->fetchAll();
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
    SELECT MONTH(data_venda) AS mes, SUM(total) AS receita, SUM(qtd) AS qtd, COUNT(*) AS n
    FROM vendas $w_venda GROUP BY mes ORDER BY mes
")->fetchAll();

// --- Receita por categoria ---
$por_cat = $db->query("
    SELECT categoria, SUM(total) AS receita, SUM(qtd) AS qtd
    FROM vendas $w_venda GROUP BY categoria ORDER BY receita DESC
")->fetchAll();

// --- Top 10 produtos mais vendidos ---
$top5 = $db->query("
    SELECT produto, categoria, SUM(qtd) AS qtd_total, SUM(total) AS receita
    FROM vendas $w_venda GROUP BY produto, categoria
    ORDER BY qtd_total DESC LIMIT 10
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

$MESES_PT = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

include __DIR__ . '/includes/header.php';
?>

<!-- FILTRO DE PERÍODO -->
<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:20px;
    background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 18px;">
    <span style="font-family:'Rajdhani',sans-serif;font-weight:700;font-size:.9rem;color:var(--text2);margin-right:4px">⏱ Período:</span>

    <select name="ano" onchange="this.form.submit()"
        style="background:var(--surface3);border:1px solid var(--border2);color:var(--text);padding:6px 10px;border-radius:6px;font-size:.82rem;cursor:pointer">
        <?php foreach($anos_disp as $a): ?>
        <option value="<?= $a ?>" <?= $a == $ano_sel ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
    </select>

    <label style="font-size:.8rem;color:var(--text3)">De</label>
    <select name="mes_ini" onchange="this.form.submit()"
        style="background:var(--surface3);border:1px solid var(--border2);color:var(--text);padding:6px 10px;border-radius:6px;font-size:.82rem;cursor:pointer">
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $m == $mes_ini ? 'selected' : '' ?>><?= $MESES_PT[$m] ?></option>
        <?php endfor; ?>
    </select>

    <label style="font-size:.8rem;color:var(--text3)">até</label>
    <select name="mes_fim" onchange="this.form.submit()"
        style="background:var(--surface3);border:1px solid var(--border2);color:var(--text);padding:6px 10px;border-radius:6px;font-size:.82rem;cursor:pointer">
        <?php for($m=1;$m<=12;$m++): ?>
        <option value="<?= $m ?>" <?= $m == $mes_fim ? 'selected' : '' ?>><?= $MESES_PT[$m] ?></option>
        <?php endfor; ?>
    </select>

    <?php if ($loja_filtro == 0): ?>
    <select name="loja_dash" onchange="this.form.submit()"
        style="background:var(--surface3);border:1px solid var(--border2);color:var(--text);padding:6px 10px;border-radius:6px;font-size:.82rem;cursor:pointer">
        <option value="">Todas as lojas</option>
        <?php
        $lojas_all = $db->query("SELECT id, nome FROM lojas ORDER BY id")->fetchAll();
        foreach($lojas_all as $la): ?>
        <option value="<?= $la['id'] ?>"><?= htmlspecialchars($la['nome']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <span style="font-size:.75rem;color:var(--text3);margin-left:auto">
        Exibindo: <?= $MESES_PT[$mes_ini] ?> – <?= $MESES_PT[$mes_fim] ?> <?= $ano_sel ?>
    </span>
</form>

<!-- KPI CARDS -->
<div class="kpi-grid">
    <div class="kpi-card accent">
        <div class="kpi-label">Receita Total</div>
        <div class="kpi-value"><?= brl($kpi['receita_total']) ?></div>
        <div class="kpi-sub"><?= $MESES_PT[$mes_ini] ?> — <?= $MESES_PT[$mes_fim] ?> <?= $ano_sel ?></div>
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
    <div class="kpi-card">
        <div class="kpi-label">Número de Clientes</div>
        <div class="kpi-value"><?= number_format($total_clientes) ?></div>
        <div class="kpi-sub">clientes cadastrados</div>
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
            <span class="panel-sub"><?= $MESES_PT[$mes_ini] ?>–<?= $MESES_PT[$mes_fim] ?> <?= $ano_sel ?></span>
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

<!-- QTDE VENDIDA POR MÊS -->
<div class="panel" style="margin-bottom:24px">
    <div class="panel-header">
        <span class="panel-title">Quantidade de Produtos Vendidos por Mês</span>
        <span class="panel-sub"><?= $MESES_PT[$mes_ini] ?>–<?= $MESES_PT[$mes_fim] ?> <?= $ano_sel ?></span>
    </div>
    <div class="panel-body">
        <div class="chart-wrap" style="height:200px">
            <canvas id="chartQtdMes"></canvas>
        </div>
    </div>
</div>

<!-- RECEITA POR ESTADO -->
<div class="panel" style="margin-bottom:24px">
    <div class="panel-header">
        <span class="panel-title">Receita por Estado</span>
        <span class="panel-sub">todas as filiais</span>
    </div>
    <div class="panel-body">
        <div class="chart-wrap" style="height:220px">
            <canvas id="chartEstado"></canvas>
        </div>
        <table class="data-table" style="margin-top:16px">
            <thead>
                <tr>
                    <th>Estado</th>
                    <th class="num">Receita</th>
                    <th class="num">Vendas</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($por_estado as $e): ?>
            <tr>
                <td style="font-weight:600;font-family:'Rajdhani',sans-serif;font-size:1rem"><?= htmlspecialchars($e['estado']) ?></td>
                <td class="num mono" style="color:var(--accent)"><?= brl($e['receita']) ?></td>
                <td class="num mono"><?= number_format($e['n_vendas']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- RECEITA TOTAL POR PRODUTO -->
<div class="panel" style="margin-bottom:24px">
    <div class="panel-header">
        <span class="panel-title">Receita Total por Produto</span>
        <span class="panel-sub">top 10 por receita</span>
    </div>
    <div class="panel-body">
        <div class="chart-wrap" style="height:280px">
            <canvas id="chartProduto"></canvas>
        </div>
    </div>
</div>

<!-- LOJAS + TOP 10 -->
<div class="section-grid thirds">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Top 10 Produtos</span>
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
            yAxisID: 'y',
        }]
    },
    options: { ...chartDefaults, scales: {
        x: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } },
        y: { ticks: { color:'#94a3b8', callback: v => 'R$' + (v/1000).toFixed(0) + 'k' }, grid: { color:'#1f2231' } }
    }}
});

// Quantidade de Produtos Vendidos por Mês
new Chart(document.getElementById('chartQtdMes'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $MESES_PT[(int)$r['mes']], $por_mes)) ?>,
        datasets: [
            {
                label: 'Qtd Itens Vendidos',
                data: <?= json_encode(array_column($por_mes, 'qtd')) ?>,
                backgroundColor: '#3b82f630',
                borderColor: '#3b82f6',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3b82f6',
                pointRadius: 5,
            },
            {
                label: 'Nº de Transações',
                data: <?= json_encode(array_column($por_mes, 'n')) ?>,
                backgroundColor: '#a78bfa30',
                borderColor: '#a78bfa',
                borderWidth: 2,
                fill: false,
                tension: 0.4,
                pointBackgroundColor: '#a78bfa',
                pointRadius: 5,
            }
        ]
    },
    options: { ...chartDefaults, scales: {
        x: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } },
        y: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } }
    }}
});

// Receita por Categoria
new Chart(document.getElementById('chartCat'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($por_cat, 'categoria')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($por_cat, 'receita')) ?>,
            backgroundColor: ['#f97316','#fb923c','#3b82f6','#a78bfa','#22c55e','#eab308','#ef4444','#06b6d4','#ec4899','#84cc16'],
            borderColor: '#111318',
            borderWidth: 3,
        }]
    },
    options: { ...chartDefaults }
});

// Receita por Estado
new Chart(document.getElementById('chartEstado'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($por_estado, 'estado')) ?>,
        datasets: [{
            label: 'Receita por Estado (R$)',
            data: <?= json_encode(array_column($por_estado, 'receita')) ?>,
            backgroundColor: ['#3b82f650','#a78bfa50','#22c55e50','#f9731650','#fb923c50'],
            borderColor:     ['#3b82f6',  '#a78bfa',  '#22c55e',  '#f97316',  '#fb923c'],
            borderWidth: 2,
            borderRadius: 5,
        }]
    },
    options: { ...chartDefaults, scales: {
        x: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } },
        y: { ticks: { color:'#94a3b8', callback: v => 'R$' + (v/1000).toFixed(0) + 'k' }, grid: { color:'#1f2231' } }
    }}
});

// Receita Total por Produto (top 10)
new Chart(document.getElementById('chartProduto'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($top5, 'produto')) ?>,
        datasets: [{
            label: 'Receita (R$)',
            data: <?= json_encode(array_column($top5, 'receita')) ?>,
            backgroundColor: [
                '#f9731660','#3b82f660','#a78bfa60','#22c55e60','#eab30860',
                '#ef444460','#06b6d460','#ec489960','#84cc1660','#fb923c60'
            ],
            borderColor: [
                '#f97316','#3b82f6','#a78bfa','#22c55e','#eab308',
                '#ef4444','#06b6d4','#ec4899','#84cc16','#fb923c'
            ],
            borderWidth: 2,
            borderRadius: 5,
        }]
    },
    options: { ...chartDefaults,
        indexAxis: 'y',
        scales: {
            x: { ticks: { color:'#94a3b8', callback: v => 'R$' + (v/1000).toFixed(0) + 'k' }, grid: { color:'#1f2231' } },
            y: { ticks: { color:'#94a3b8', font: { size: 11 } }, grid: { color:'#1f2231' } }
        }
    }
});
</script>

<?php if (!empty($metas_por_loja)): ?>
<!-- METAS DE VENDAS -->
<div class="panel" style="margin-bottom:24px">
    <div class="panel-header">
        <span class="panel-title">Meta de Vendas — <?= $MESES_PT[$mes_ini] ?>–<?= $MESES_PT[$mes_fim] ?> <?= $ano_sel ?></span>
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