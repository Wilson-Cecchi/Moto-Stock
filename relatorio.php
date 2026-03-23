<?php
// relatorio.php — Relatório imprimível (PDF via Ctrl+P / window.print) motos
require_once __DIR__ . '/config.php';
requireGerente();
$page  = 'relatorio';
$title = 'Relatório';
$db    = getDB();

$loja_filtro = getLojaFiltro();
$loja_id     = $loja_filtro ?: (int)($_GET['loja'] ?? 0);

$lojas = $db->query($loja_filtro ? "SELECT * FROM lojas WHERE id = $loja_filtro" : "SELECT * FROM lojas ORDER BY id")->fetchAll();

$w_v = $loja_id ? "WHERE loja_id = $loja_id"      : '';
$w_p = $loja_id ? "WHERE loja_id = $loja_id"      : '';
$w_vj= $loja_id ? "AND v.loja_id = $loja_id"      : '';

// KPIs
$kpi = $db->query("
    SELECT COUNT(*) AS n_vendas, SUM(total) AS receita, SUM(qtd) AS itens, ROUND(AVG(total),2) AS ticket
    FROM vendas $w_v
")->fetch();

// Estoque — alertas
$alertas = $db->query("
    SELECT p.nome, p.categoria, p.estoque, p.estoque_minimo, l.nome AS loja_nome
    FROM produtos p JOIN lojas l ON l.id = p.loja_id
    WHERE p.estoque <= p.estoque_minimo
    " . ($loja_id ? "AND p.loja_id = $loja_id" : '') . "
    ORDER BY p.estoque ASC LIMIT 20
")->fetchAll();

// Receita por loja
$por_loja = $db->query("
    SELECT l.nome, COALESCE(SUM(v.total),0) AS receita, COALESCE(COUNT(v.id),0) AS n_vendas
    FROM lojas l LEFT JOIN vendas v ON v.loja_id = l.id $w_vj
    " . ($loja_id ? "WHERE l.id = $loja_id" : '') . "
    GROUP BY l.id ORDER BY receita DESC
")->fetchAll();

// Top 10 produtos
$top10 = $db->query("
    SELECT produto, categoria, SUM(qtd) AS qtd_total, SUM(total) AS receita
    FROM vendas $w_v GROUP BY produto, categoria
    ORDER BY qtd_total DESC LIMIT 10
")->fetchAll();

// Previsão 6 meses
$prev = $db->query("
    SELECT v.produto, v.categoria, l.nome AS loja_nome,
           ROUND(SUM(v.qtd)/3.0,1) AS media_mensal,
           MAX(v.preco_unit) AS preco_unit
    FROM vendas v JOIN lojas l ON l.id = v.loja_id
    " . ($loja_id ? "WHERE v.loja_id = $loja_id" : '') . "
    GROUP BY v.produto, v.categoria, v.loja_id
    ORDER BY media_mensal DESC LIMIT 15
")->fetchAll();

// Metas
$metas = $db->query("
    SELECT m.mes, m.meta_valor, l.nome AS loja_nome,
           COALESCE(SUM(v.total),0) AS realizado
    FROM metas m JOIN lojas l ON l.id = m.loja_id
    LEFT JOIN vendas v ON v.loja_id = m.loja_id AND MONTH(v.data_venda)=m.mes AND YEAR(v.data_venda)=m.ano
    WHERE m.ano = 2025
    " . ($loja_id ? "AND m.loja_id = $loja_id" : '') . "
    GROUP BY m.id ORDER BY l.id, m.mes
")->fetchAll();

$MESES_PT = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

include __DIR__ . '/includes/header.php';
?>

<!-- Barra de ação (não aparece no PDF) -->
<div class="no-print" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap">
    <?php if (!$loja_filtro): ?>
    <form method="get" style="display:flex;gap:8px;align-items:center">
        <label style="font-size:.8rem;color:var(--text2)">Filtrar por loja:</label>
        <select name="loja" onchange="this.form.submit()"
            style="background:var(--surface3);border:1px solid var(--border2);color:var(--text);padding:6px 10px;border-radius:5px;font-size:.82rem">
            <option value="0">Todas as lojas</option>
            <?php foreach ($lojas as $l): ?>
            <option value="<?= $l['id'] ?>" <?= $loja_id == $l['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($l['nome']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
    <button onclick="window.print()"
        style="padding:8px 22px;background:var(--accent);color:#000;border:none;border-radius:6px;
               font-weight:700;cursor:pointer;font-family:'Rajdhani',sans-serif;font-size:.95rem;letter-spacing:.05em">
        ⎙ Imprimir / Salvar PDF
    </button>
    <span style="font-size:.75rem;color:var(--text3)">
        Use <b>Salvar como PDF</b> na janela de impressão do navegador.
    </span>
</div>

<!-- ======================================================
     CONTEÚDO IMPRIMÍVEL
     ====================================================== -->
<div class="print-doc">

    <!-- Cabeçalho do documento -->
    <div style="border-bottom:2px solid #f97316;padding-bottom:14px;margin-bottom:24px;display:flex;justify-content:space-between;align-items:flex-end">
        <div>
            <div style="font-family:'Rajdhani',sans-serif;font-size:1.8rem;font-weight:700;color:#f97316;line-height:1">⚙ MotoStock</div>
            <div style="font-size:.8rem;color:#666;margin-top:2px">Relatório de Gestão — Jan–Mar 2026</div>
            <?php if ($loja_id): ?>
            <div style="font-size:.8rem;color:#f97316;margin-top:2px">
                <?= htmlspecialchars($lojas[0]['nome'] ?? '') ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="font-size:.75rem;color:#888;text-align:right">
            Gerado em <?= date('d/m/Y H:i') ?><br>
            MotoStock Distribuidora
        </div>
    </div>

    <!-- KPIs -->
    <h2 class="rpt-section">1. Resumo Geral</h2>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px">
        <?php
        $kpis_print = [
            ['Receita Total', brl($kpi['receita'] ?? 0), 'Jan–Mar 2025'],
            ['Vendas',        number_format($kpi['n_vendas'] ?? 0), 'transações'],
            ['Itens Vendidos',number_format($kpi['itens'] ?? 0),    'unidades'],
            ['Ticket Médio',  brl($kpi['ticket'] ?? 0),            'por venda'],
        ];
        foreach ($kpis_print as [$lb, $vl, $sb]): ?>
        <div class="rpt-kpi">
            <div class="rpt-kpi-label"><?= $lb ?></div>
            <div class="rpt-kpi-value"><?= $vl ?></div>
            <div class="rpt-kpi-sub"><?= $sb ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Receita por Loja -->
    <h2 class="rpt-section">2. Receita por Loja</h2>
    <table class="rpt-table">
        <thead><tr><th>Loja</th><th class="num">Vendas</th><th class="num">Receita</th></tr></thead>
        <tbody>
        <?php foreach ($por_loja as $l): ?>
        <tr>
            <td><?= htmlspecialchars($l['nome']) ?></td>
            <td class="num"><?= number_format($l['n_vendas']) ?></td>
            <td class="num"><?= brl($l['receita']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Metas -->
    <?php if (!empty($metas)): ?>
    <h2 class="rpt-section">3. Metas de Vendas</h2>
    <table class="rpt-table">
        <thead><tr><th>Loja</th><th>Mês</th><th class="num">Meta</th><th class="num">Realizado</th><th class="num">%</th></tr></thead>
        <tbody>
        <?php foreach ($metas as $m):
            $pct = $m['meta_valor'] > 0 ? round($m['realizado'] / $m['meta_valor'] * 100) : 0;
        ?>
        <tr>
            <td><?= htmlspecialchars($m['loja_nome']) ?></td>
            <td><?= $MESES_PT[(int)$m['mes']] ?>/2025</td>
            <td class="num"><?= brl($m['meta_valor']) ?></td>
            <td class="num"><?= brl($m['realizado']) ?></td>
            <td class="num" style="color:<?= $pct>=100?'#22c55e':($pct>=70?'#eab308':'#ef4444') ?>;font-weight:600">
                <?= $pct ?>%
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Top Produtos -->
    <h2 class="rpt-section">4. Top 10 Produtos Mais Vendidos</h2>
    <table class="rpt-table">
        <thead><tr><th>#</th><th>Produto</th><th>Categoria</th><th class="num">Qtd</th><th class="num">Receita</th></tr></thead>
        <tbody>
        <?php foreach ($top10 as $i => $p): ?>
        <tr>
            <td style="font-weight:700;color:#f97316"><?= $i+1 ?></td>
            <td><?= htmlspecialchars($p['produto']) ?></td>
            <td><?= htmlspecialchars($p['categoria']) ?></td>
            <td class="num"><?= $p['qtd_total'] ?></td>
            <td class="num"><?= brl($p['receita']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Alertas de Estoque -->
    <?php if (!empty($alertas)): ?>
    <h2 class="rpt-section" style="color:#ef4444">5. ⚠ Alertas de Estoque Mínimo</h2>
    <table class="rpt-table">
        <thead><tr><th>Produto</th><th>Categoria</th><th>Loja</th><th class="num">Estoque</th><th class="num">Mínimo</th></tr></thead>
        <tbody>
        <?php foreach ($alertas as $a): ?>
        <tr>
            <td><?= htmlspecialchars($a['nome']) ?></td>
            <td><?= htmlspecialchars($a['categoria']) ?></td>
            <td><?= htmlspecialchars($a['loja_nome']) ?></td>
            <td class="num" style="color:<?= $a['estoque'] <= 0 ? '#ef4444' : '#eab308' ?>;font-weight:600">
                <?= $a['estoque'] ?>
            </td>
            <td class="num"><?= $a['estoque_minimo'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Previsão -->
    <h2 class="rpt-section">6. Previsão de Demanda — Próximos 6 Meses</h2>
    <table class="rpt-table">
        <thead><tr><th>Produto</th><th>Cat.</th><th>Loja</th><th class="num">Méd./mês</th><th class="num">Prev. 6m (un)</th><th class="num">Prev. 6m (R$)</th></tr></thead>
        <tbody>
        <?php foreach ($prev as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['produto']) ?></td>
            <td><?= htmlspecialchars($p['categoria']) ?></td>
            <td><?= htmlspecialchars($p['loja_nome']) ?></td>
            <td class="num"><?= $p['media_mensal'] ?></td>
            <td class="num"><?= round($p['media_mensal'] * 6) ?></td>
            <td class="num"><?= brl($p['media_mensal'] * 6 * $p['preco_unit']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Rodapé -->
    <div style="margin-top:32px;padding-top:12px;border-top:1px solid #ccc;font-size:.72rem;color:#888;display:flex;justify-content:space-between">
        <span>MotoStock — Sistema de Gestão e Previsão de Estoque</span>
        <span>Equipe: Bruna · Fernando · Wilson Klein Cecchi</span>
    </div>

</div><!-- .print-doc -->

<style>
/* Estilos específicos do relatório */
.print-doc {
    font-family: 'Inter', sans-serif;
    font-size: .85rem;
    color: var(--text);
}
.rpt-section {
    font-family: 'Rajdhani', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--accent);
    margin: 24px 0 10px;
    padding-bottom: 4px;
    border-bottom: 1px solid var(--border);
}
.rpt-kpi {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 16px;
}
.rpt-kpi-label { font-size:.7rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; }
.rpt-kpi-value { font-size:1.3rem; font-weight:700; font-family:'Rajdhani',sans-serif; margin:4px 0 2px; }
.rpt-kpi-sub   { font-size:.7rem; color:var(--text3); }
.rpt-table { width:100%; border-collapse:collapse; margin-bottom:8px; }
.rpt-table th { background:var(--surface3); color:var(--text2); font-size:.75rem; font-weight:600; padding:8px 10px; text-align:left; border:1px solid var(--border); font-family:'Rajdhani',sans-serif; letter-spacing:.04em; }
.rpt-table td { padding:7px 10px; border:1px solid var(--border); font-size:.82rem; }
.rpt-table tr:nth-child(even) td { background: var(--surface); }
.num { text-align:right; font-family:'Fira Code',monospace; }

/* Impressão */
@media print {
    body { background: #fff !important; color: #000 !important; font-size: 11pt; }
    .print-doc { color: #000; }
    .rpt-section { color: #c2410c; border-color: #ccc; }
    .rpt-kpi { background: #f9f9f9; border-color: #ddd; }
    .rpt-kpi-label, .rpt-kpi-sub { color: #666; }
    .rpt-table th { background: #f0f0f0; color: #333; border-color: #ccc; }
    .rpt-table td { border-color: #ddd; color: #000; }
    .rpt-table tr:nth-child(even) td { background: #fafafa; }
    @page { margin: 1.5cm; }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
