<?php
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'vendas';
$title = 'Histórico de Vendas';
$db    = getDB();

$loja_filtro = getLojaFiltro();

// Filtros motos
$loja_id    = $loja_filtro ?: (int)($_GET['loja']       ?? 0);
$cat        = $_GET['cat']              ?? '';
$mes        = (int)($_GET['mes']        ?? 0);
$data_ini   = $_GET['data_ini']         ?? '';
$data_fim   = $_GET['data_fim']         ?? '';
$pg         = max(1, (int)($_GET['pg'] ?? 1));
$per_pg     = 25;

$lojas = $db->query($loja_filtro ? "SELECT * FROM lojas WHERE id = $loja_filtro" : "SELECT * FROM lojas ORDER BY id")->fetchAll();

$where  = [];
$params = [];
if ($loja_id)  { $where[] = 'v.loja_id = ?';              $params[] = $loja_id; }
if ($cat)      { $where[] = 'v.categoria = ?';             $params[] = $cat; }
if ($mes)      { $where[] = 'MONTH(v.data_venda) = ?';     $params[] = $mes; }
if ($data_ini) { $where[] = 'v.data_venda >= ?';           $params[] = $data_ini; }
if ($data_fim) { $where[] = 'v.data_venda <= ?';           $params[] = $data_fim; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total para paginação
$total_stmt = $db->prepare("SELECT COUNT(*) FROM vendas v $sql_where");
$total_stmt->execute($params);
$total_rows = (int)$total_stmt->fetchColumn();
$total_pgs  = (int)ceil($total_rows / $per_pg);

// Vendas paginadas
$offset  = ($pg - 1) * $per_pg;
$p2 = $params;
$vendas_stmt = $db->prepare("
    SELECT v.*, l.nome AS loja_nome, l.tipo AS loja_tipo
    FROM vendas v
    JOIN lojas l ON l.id = v.loja_id
    $sql_where
    ORDER BY v.data_venda DESC
    LIMIT $per_pg OFFSET $offset
");
$vendas_stmt->execute($params);
$vendas = $vendas_stmt->fetchAll();

// KPIs filtrados
$kpi_stmt = $db->prepare("SELECT COUNT(*) AS n, SUM(total) AS receita, SUM(qtd) AS qtd FROM vendas v $sql_where");
$kpi_stmt->execute($params);
$kfilt = $kpi_stmt->fetch();

// Gráfico: vendas por dia
$dia_stmt = $db->prepare("
    SELECT DATE(data_venda) AS dia, SUM(total) AS receita
    FROM vendas v $sql_where
    GROUP BY dia ORDER BY dia
");
$dia_stmt->execute($params);
$por_dia = $dia_stmt->fetchAll();

// Ticket médio por loja (sempre geral, sem filtro)
$ticket_loja = $db->query("
    SELECT l.nome, ROUND(AVG(v.total),2) AS ticket
    FROM vendas v JOIN lojas l ON l.id = v.loja_id
    GROUP BY l.id ORDER BY ticket DESC
")->fetchAll();

$MESES_PT = [1=>'Janeiro',2=>'Fevereiro',3=>'Março'];
$categorias = $CATEGORIAS;

include __DIR__ . '/includes/header.php';
?>

<!-- FILTROS -->
<form method="get" class="filters">
    <label>Loja</label>
    <select name="loja">
        <option value="0">Todas</option>
        <?php foreach ($lojas as $l): ?>
        <option value="<?= $l['id'] ?>" <?= $loja_id == $l['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($l['nome']) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <label>Categoria</label>
    <select name="cat">
        <option value="">Todas</option>
        <?php foreach ($categorias as $c): ?>
        <option value="<?= $c ?>" <?= $cat === $c ? 'selected' : '' ?>><?= $c ?></option>
        <?php endforeach; ?>
    </select>

    <label>Mês</label>
    <select name="mes">
        <option value="0">Todos</option>
        <?php foreach ($MESES_PT as $m => $mn): ?>
        <option value="<?= $m ?>" <?= $mes == $m ? 'selected' : '' ?>><?= $mn ?></option>
        <?php endforeach; ?>
    </select>

    <label>De</label>
    <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>">

    <label>Até</label>
    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">

    <button class="btn" type="submit">Filtrar</button>
    <a href="/motostock/vendas.php" class="btn btn-ghost">Limpar</a>
</form>

<!-- TICKET MÉDIO POR LOJA -->
<div class="panel" style="margin-bottom:20px">
    <div class="panel-header">
        <span class="panel-title">Ticket Médio por Loja</span>
        <span class="panel-sub">valor médio por transação</span>
    </div>
    <div class="panel-body">
        <div class="loja-rank">
        <?php
        $max_ticket = max(array_column($ticket_loja, 'ticket')) ?: 1;
        foreach ($ticket_loja as $t): ?>
            <div class="loja-rank-item">
                <div class="loja-rank-header">
                    <span class="loja-rank-name"><?= htmlspecialchars($t['nome']) ?></span>
                    <span class="loja-rank-val"><?= brl($t['ticket']) ?></span>
                </div>
                <div class="loja-rank-track">
                    <div class="loja-rank-fill" style="width:<?= round($t['ticket'] / $max_ticket * 100) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- KPIs FILTRADOS -->
<div class="kpi-grid" style="margin-bottom:20px">
    <div class="kpi-card accent">
        <div class="kpi-label">Receita Filtrada</div>
        <div class="kpi-value"><?= brl($kfilt['receita'] ?? 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Transações</div>
        <div class="kpi-value"><?= number_format($kfilt['n']) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Itens</div>
        <div class="kpi-value"><?= number_format($kfilt['qtd'] ?? 0) ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Ticket Médio</div>
        <div class="kpi-value"><?= brl($kfilt['n'] > 0 ? $kfilt['receita'] / $kfilt['n'] : 0) ?></div>
    </div>
</div>

<!-- GRÁFICO TIMELINE -->
<?php if (!empty($por_dia)): ?>
<div class="panel" style="margin-bottom:20px">
    <div class="panel-header">
        <span class="panel-title">Receita por Dia</span>
        <span class="panel-sub"><?= count($por_dia) ?> dias com vendas</span>
    </div>
    <div class="panel-body">
        <div class="chart-wrap" style="height:180px">
            <canvas id="chartDia"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TABELA DE VENDAS -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Registros de Vendas</span>
        <span class="panel-sub"><?= $total_rows ?> registro(s) · pág. <?= $pg ?> de <?= max(1,$total_pgs) ?></span>
    </div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Loja</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th class="num">Qtd</th>
                    <th class="num">Preço Unit.</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($vendas)): ?>
                <tr><td colspan="8" class="empty">Nenhuma venda encontrada.</td></tr>
            <?php endif; ?>
            <?php foreach ($vendas as $v): ?>
            <tr>
                <td class="mono" style="color:var(--text3)"><?= $v['codigo'] ?></td>
                <td class="mono"><?= date('d/m/Y', strtotime($v['data_venda'])) ?></td>
                <td>
                    <span class="badge <?= $v['loja_tipo']==='Matriz' ? 'badge-matriz' : 'badge-filial' ?>">
                        <?= htmlspecialchars($v['loja_nome']) ?>
                    </span>
                </td>
                <td style="font-weight:500"><?= htmlspecialchars($v['produto']) ?></td>
                <td><span class="badge badge-cat"><?= $v['categoria'] ?></span></td>
                <td class="num mono"><?= $v['qtd'] ?></td>
                <td class="num mono"><?= brl($v['preco_unit']) ?></td>
                <td class="num mono" style="color:var(--accent)"><?= brl($v['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PAGINAÇÃO -->
<?php if ($total_pgs > 1): ?>
<div class="pagination">
    <?php if ($pg > 1): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pg-1])) ?>" class="page-btn">← Ant</a>
    <?php endif; ?>
    <?php for ($i = max(1,$pg-2); $i <= min($total_pgs,$pg+2); $i++): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $i])) ?>"
       class="page-btn <?= $i === $pg ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($pg < $total_pgs): ?>
    <a href="?<?= http_build_query(array_merge($_GET, ['pg' => $pg+1])) ?>" class="page-btn">Prox →</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
<?php if (!empty($por_dia)): ?>
new Chart(document.getElementById('chartDia'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['dia'])), $por_dia)) ?>,
        datasets: [{
            label: 'Receita (R$)',
            data: <?= json_encode(array_column($por_dia, 'receita')) ?>,
            borderColor: '#f97316',
            backgroundColor: '#f9731615',
            fill: true,
            tension: 0.4,
            pointRadius: 2,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { color:'#94a3b8', maxTicksLimit: 15 }, grid: { color:'#1f2231' } },
            y: { ticks: { color:'#94a3b8', callback: v => 'R$'+(v/1000).toFixed(0)+'k' }, grid: { color:'#1f2231' } }
        }
    }
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>