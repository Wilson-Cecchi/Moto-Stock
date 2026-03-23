<?php
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'previsao';
$title = 'Previsão — Próximos 6 Meses';
$db    = getDB();

$loja_filtro = getLojaFiltro();
$loja_id = $loja_filtro ?: (int)($_GET['loja'] ?? 0);
$lojas   = $db->query($loja_filtro ? "SELECT * FROM lojas WHERE id = $loja_filtro" : "SELECT * FROM lojas ORDER BY id")->fetchAll();

// Média mensal de vendas por produto/loja (base: 3 meses de dados)
$sql_params = [];
$sql_where  = $loja_id ? 'WHERE v.loja_id = ?' : '';
if ($loja_id) $sql_params[] = $loja_id;

$media_vendas = $db->prepare("
    SELECT
        v.loja_id,
        l.nome AS loja_nome,
        v.produto,
        v.categoria,
        ROUND(SUM(v.qtd) / 3.0, 2) AS media_mensal,
        SUM(v.qtd)                  AS total_3m,
        MAX(v.preco_unit)           AS preco_unit
    FROM vendas v
    JOIN lojas l ON l.id = v.loja_id
    $sql_where
    GROUP BY v.loja_id, v.produto, v.categoria
    ORDER BY l.id, media_mensal DESC
");
$media_vendas->execute($sql_params);
$dados = $media_vendas->fetchAll();

// Estoque atual dos produtos
$estoque_map = [];
$estq = $db->query("
    SELECT p.loja_id, p.nome, p.estoque, p.estoque_minimo, p.preco
    FROM produtos p
")->fetchAll();
foreach ($estq as $e) {
    $estoque_map[$e['loja_id']][$e['nome']] = $e;
}

// Meses futuros para previsão (a partir de Abril 2025)
$meses_prev = [];
$base = new DateTime('2025-04-01');
for ($i = 0; $i < 6; $i++) {
    $meses_prev[] = $base->format('M/y');
    $base->modify('+1 month');
}
$MESES_LABEL = ['Abr/25','Mai/25','Jun/25','Jul/25','Ago/25','Set/25'];

// Agrupamento por loja para exibição
$por_loja = [];
foreach ($dados as $d) {
    $por_loja[$d['loja_id']]['nome'] = $d['loja_nome'];
    $por_loja[$d['loja_id']]['produtos'][] = $d;
}

// Totais para gráfico
$grafico_labels  = [];
$grafico_valores = [];
foreach ($por_loja as $lid => $loja_data) {
    $grafico_labels[] = $loja_data['nome'];
    $total_prev = 0;
    foreach ($loja_data['produtos'] as $p) {
        $total_prev += $p['media_mensal'] * 6 * $p['preco_unit'];
    }
    $grafico_valores[] = round($total_prev, 2);
}

include __DIR__ . '/includes/header.php';
?>

<!-- FILTRO LOJA -->
<form method="get" class="filters">
    <label>Loja</label>
    <select name="loja" onchange="this.form.submit()">
        <option value="0">Todas as lojas</option>
        <?php foreach ($lojas as $l): ?>
        <option value="<?= $l['id'] ?>" <?= $loja_id == $l['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($l['nome']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <div style="color:var(--text3);font-size:.75rem;font-family:'Fira Code',monospace;padding:4px 0">
        Base de cálculo: média mensal dos 3 meses registrados (Jan–Mar 2025)
    </div>
</form>

<!-- GRÁFICO VISÃO GERAL --> motos
<div class="section-grid" style="margin-bottom:24px">
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Receita Prevista por Loja (6 meses)</span>
            <span class="panel-sub">Abr–Set 2025</span>
        </div>
        <div class="panel-body">
            <div class="chart-wrap" style="height:220px">
                <canvas id="chartPrevisao"></canvas>
            </div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Metodologia</span>
            <span class="panel-sub">como a previsão é calculada</span>
        </div>
        <div class="panel-body" style="font-size:.84rem;line-height:1.8;color:var(--text2)">
            <p style="margin-bottom:12px">
                A previsão usa a <strong style="color:var(--text)">média móvel simples</strong> baseada nos
                3 meses de histórico disponíveis (Janeiro a Março de 2025).
            </p>

            <p style="margin-bottom:6px"><strong style="color:var(--text)">Passo 1 — Média mensal</strong></p>
            <p style="font-family:'Fira Code',monospace;font-size:.78rem;background:var(--surface3);padding:8px 12px;border-radius:6px;margin-bottom:12px;color:var(--accent)">
                média_mensal = total_vendido_3_meses ÷ 3
            </p>

            <p style="margin-bottom:6px"><strong style="color:var(--text)">Passo 2 — Demanda prevista</strong></p>
            <p style="font-family:'Fira Code',monospace;font-size:.78rem;background:var(--surface3);padding:8px 12px;border-radius:6px;margin-bottom:12px;color:var(--accent)">
                demanda_prevista = média_mensal × número_de_meses
            </p>

            <p style="margin-bottom:6px"><strong style="color:var(--text)">Passo 3 — Reposição sugerida</strong></p>
            <p style="font-family:'Fira Code',monospace;font-size:.78rem;background:var(--surface3);padding:8px 12px;border-radius:6px;margin-bottom:12px;color:var(--accent)">
                reposição = demanda_prevista − estoque_atual
            </p>

            <p style="color:var(--text3);font-size:.78rem;border-top:1px solid var(--border);padding-top:10px;margin-top:4px">
                ⚠ Valores negativos na coluna Repor indicam que o estoque atual já cobre a previsão.
            </p>
        </div>
    </div>
</div>

<!-- TABELA POR LOJA -->
<?php foreach ($por_loja as $lid => $loja_data): ?>
<div class="panel" style="margin-bottom:20px">
    <div class="panel-header">
        <span class="panel-title"><?= htmlspecialchars($loja_data['nome']) ?></span>
        <span class="panel-sub"><?= count($loja_data['produtos']) ?> produto(s) com histórico</span>
    </div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Cat.</th>
                    <th class="num">Média/mês</th>
                    <th class="num">Estoque Atual</th>
                    <?php foreach ($MESES_LABEL as $m): ?>
                    <th class="num" style="color:var(--accent)"><?= $m ?></th>
                    <?php endforeach; ?>
                    <th class="num">Total 6m</th>
                    <th class="num">Repor</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($loja_data['produtos'] as $prod):
                $est_atual = $estoque_map[$lid][$prod['produto']]['estoque'] ?? '–';
                $total_6m  = round($prod['media_mensal'] * 6);
                $repor      = is_numeric($est_atual) ? max(0, $total_6m - $est_atual) : $total_6m;
            ?>
            <tr>
                <td style="font-weight:500"><?= htmlspecialchars($prod['produto']) ?></td>
                <td><span class="badge badge-cat"><?= $prod['categoria'] ?></span></td>
                <td class="num mono" style="color:var(--text2)"><?= $prod['media_mensal'] ?></td>
                <td class="num mono"><?= $est_atual ?></td>
                <?php for ($m = 1; $m <= 6; $m++): ?>
                <td class="num mono" style="color:var(--text2)"><?= round($prod['media_mensal'] * $m) ?></td>
                <?php endfor; ?>
                <td class="num mono" style="font-weight:700"><?= $total_6m ?></td>
                <td class="num">
                    <?php if ($repor > 0): ?>
                        <span class="badge badge-warn">+<?= $repor ?></span>
                    <?php else: ?>
                        <span class="badge badge-ok">OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($por_loja)): ?>
<div class="panel"><div class="panel-body empty">Nenhum dado de vendas encontrado para esta loja.</div></div>
<?php endif; ?>

<script>
new Chart(document.getElementById('chartPrevisao'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($grafico_labels) ?>,
        datasets: [{
            label: 'Receita Prevista 6 meses (R$)',
            data: <?= json_encode($grafico_valores) ?>,
            backgroundColor: ['#f9731640','#fb923c40','#3b82f640','#a78bfa40','#22c55e40'],
            borderColor:     ['#f97316',  '#fb923c',  '#3b82f6',  '#a78bfa',  '#22c55e'],
            borderWidth: 2,
            borderRadius: 5,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { color:'#94a3b8', font:{family:'Fira Code',size:11} } } },
        scales: {
            x: { ticks: { color:'#94a3b8' }, grid: { color:'#1f2231' } },
            y: { ticks: { color:'#94a3b8', callback: v => 'R$' + (v/1000).toFixed(0) + 'k' }, grid: { color:'#1f2231' } }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>