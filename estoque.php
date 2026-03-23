<?php
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'estoque';
$title = 'Estoque por Loja';
$db    = getDB();

$loja_filtro = getLojaFiltro(); // 0 = admin, >0 = gerente

$loja_id  = $loja_filtro ?: (int)($_GET['loja']    ?? 0);
$cat      = $_GET['cat']           ?? '';
$alerta   = (int)($_GET['alerta']  ?? 0);

// Filtros
$where  = [];
$params = [];
if ($loja_id) { $where[] = 'p.loja_id = ?'; $params[] = $loja_id; }
if ($cat)     { $where[] = 'p.categoria = ?'; $params[] = $cat; }
if ($alerta)  { $where[] = 'p.estoque <= p.estoque_minimo'; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$produtos = $db->prepare("
    SELECT p.*, l.nome AS loja_nome, l.tipo AS loja_tipo
    FROM produtos p
    JOIN lojas l ON l.id = p.loja_id
    $sql_where
    ORDER BY l.id, p.categoria, p.nome
");
$produtos->execute($params);
$produtos = $produtos->fetchAll();

$lojas_sql = $loja_filtro ? "SELECT * FROM lojas WHERE id = $loja_filtro" : "SELECT * FROM lojas ORDER BY id";
$lojas = $db->query($lojas_sql)->fetchAll();
$categorias = $CATEGORIAS;

// Alertas totais
$alertas_por_loja = $db->query("
    SELECT l.nome, COUNT(p.id) AS qtd
    FROM produtos p JOIN lojas l ON l.id = p.loja_id
    WHERE p.estoque <= p.estoque_minimo
    GROUP BY l.id
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Gráfico: estoque por categoria
$est_cat_params = [];
$est_cat_where  = $loja_id ? 'WHERE p.loja_id = ?' : '';
if ($loja_id) $est_cat_params[] = $loja_id;
$est_cat = $db->prepare("
    SELECT p.categoria,
        SUM(p.estoque) AS total_unidades,
        SUM(p.preco * p.estoque) AS valor_total,
        SUM(CASE WHEN p.estoque <= p.estoque_minimo THEN 1 ELSE 0 END) AS alertas
    FROM produtos p $est_cat_where
    GROUP BY p.categoria ORDER BY valor_total DESC
");
$est_cat->execute($est_cat_params);
$est_cat = $est_cat->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- FILTROS -->
<form method="get" class="filters">
    <label>Loja</label>
    <select name="loja">
        <option value="0">Todas as lojas</option>
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

    <label>
        <input type="checkbox" name="alerta" value="1" <?= $alerta ? 'checked' : '' ?> onchange="this.form.submit()">
        Somente alertas
    </label>

    <button class="btn" type="submit">Filtrar</button>
    <a href="/motostock/estoque.php" class="btn btn-ghost">Limpar</a>
</form>

<!-- RESUMO DE ALERTAS POR LOJA -->
<?php if (!$loja_id && array_sum($alertas_por_loja) > 0): ?>
<div class="section-grid" style="margin-bottom:20px">
    <?php foreach ($alertas_por_loja as $lnome => $qtd): ?>
    <div class="alert-strip" style="flex:1">
        <span class="alert-icon">⚠</span>
        <div><strong><?= htmlspecialchars($lnome) ?>:</strong> <?= $qtd ?> produto(s) com estoque crítico</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- GRÁFICO ESTOQUE POR CATEGORIA -->
<div class="section-grid" style="margin-bottom:20px">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Unidades em Estoque por Categoria</span>
            <span class="panel-sub">total de unidades</span>
        </div>
        <div class="panel-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chartEstCat"></canvas>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Valor em Estoque por Categoria</span>
            <span class="panel-sub">R$ imobilizado</span>
        </div>
        <div class="panel-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chartEstValor"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- TABELA -->
<div class="panel">
    <div class="panel-header">
        <span class="panel-title">Produtos em Estoque</span>
        <span class="panel-sub"><?= count($produtos) ?> produto(s)</span>
    </div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Loja</th>
                    <th class="num">Preço Unit.</th>
                    <th>Estoque Atual</th>
                    <th class="num">Mínimo</th>
                    <th class="num">Val. em Estoque</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($produtos)): ?>
                <tr><td colspan="8" class="empty">Nenhum produto encontrado para os filtros selecionados.</td></tr>
            <?php endif; ?>
            <?php foreach ($produtos as $p):
                $color = stockColor($p['estoque'], $p['estoque_minimo']);
                $pct = $p['estoque_minimo'] > 0 ? min(100, round($p['estoque'] / ($p['estoque_minimo'] * 2) * 100)) : 100;
                $valor_estoque = $p['preco'] * $p['estoque'];
            ?>
            <tr>
                <td style="font-weight:500"><?= htmlspecialchars($p['nome']) ?></td>
                <td><span class="badge badge-cat"><?= $p['categoria'] ?></span></td>
                <td>
                    <span class="badge <?= $p['loja_tipo']==='Matriz' ? 'badge-matriz' : 'badge-filial' ?>">
                        <?= htmlspecialchars($p['loja_nome']) ?>
                    </span>
                </td>
                <td class="num mono"><?= brl($p['preco']) ?></td>
                <td>
                    <div class="stock-bar">
                        <div class="stock-track">
                            <div class="stock-fill fill-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span class="stock-num" style="color:var(--<?= $color === 'ok' ? 'ok' : ($color === 'danger' ? 'danger' : ($color === 'caution' ? 'caution' : 'warn')) ?>)"><?= $p['estoque'] ?></span>
                    </div>
                </td>
                <td class="num mono"><?= $p['estoque_minimo'] ?></td>
                <td class="num mono"><?= brl($valor_estoque) ?></td>
                <td>
                    <?php if ($p['estoque'] <= 0): ?>
                        <span class="badge badge-danger">⚠ Zerado</span>
                    <?php elseif ($p['estoque'] <= $p['estoque_minimo']): ?>
                        <span class="badge badge-danger">⚠ Crítico</span>
                    <?php elseif ($p['estoque'] <= $p['estoque_minimo'] * 1.5): ?>
                        <span class="badge badge-caution">↓ Baixo</span>
                    <?php else: ?>
                        <span class="badge badge-ok">✓ OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
const catLabels = <?= json_encode(array_column($est_cat, 'categoria')) ?>;
const catUnid   = <?= json_encode(array_map(fn($r) => (int)$r['total_unidades'], $est_cat)) ?>;
const catValor  = <?= json_encode(array_map(fn($r) => (float)$r['valor_total'], $est_cat)) ?>;
const colors    = ['#f97316','#fb923c','#3b82f6','#a78bfa','#22c55e'];
const borders   = ['#f97316','#fb923c','#3b82f6','#a78bfa','#22c55e'];

const chartOpts = {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { color:'#94a3b8', font:{family:'Fira Code',size:11} } } },
    scales: {
        x: { ticks:{color:'#94a3b8'}, grid:{color:'#1f2231'} },
        y: { ticks:{color:'#94a3b8'}, grid:{color:'#1f2231'} }
    }
};

new Chart(document.getElementById('chartEstCat'), {
    type: 'bar',
    data: {
        labels: catLabels,
        datasets: [{ label: 'Unidades', data: catUnid,
            backgroundColor: colors.map(c => c + '40'),
            borderColor: borders, borderWidth: 2, borderRadius: 5 }]
    },
    options: chartOpts
});

new Chart(document.getElementById('chartEstValor'), {
    type: 'doughnut',
    data: {
        labels: catLabels,
        datasets: [{ data: catValor,
            backgroundColor: colors, borderColor: '#111318', borderWidth: 3 }]
    },
    options: { responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ labels:{ color:'#94a3b8', font:{family:'Fira Code',size:11} } } } }
});
</script>