<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

// ---- Função de geração do XML Excel (SpreadsheetML) ----
function xlsBegin(): string {
    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
    '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
              xmlns:x="urn:schemas-microsoft-com:office:excel">
    <Styles>
        <Style ss:ID="header">
            <Font ss:Bold="1" ss:Color="#FFFFFF"/>
            <Interior ss:Color="#1f2231" ss:Pattern="Solid"/>
        </Style>
        <Style ss:ID="header_orange">
            <Font ss:Bold="1" ss:Color="#FFFFFF"/>
            <Interior ss:Color="#f97316" ss:Pattern="Solid"/>
        </Style>
        <Style ss:ID="accent">
            <Font ss:Bold="1" ss:Color="#f97316"/>
        </Style>
        <Style ss:ID="currency">
            <NumberFormat ss:Format="R$\ #,##0.00"/>
        </Style>
        <Style ss:ID="number">
            <NumberFormat ss:Format="0.00"/>
        </Style>
        <Style ss:ID="date">
            <NumberFormat ss:Format="DD/MM/YYYY"/>
        </Style>
        <Style ss:ID="bold">
            <Font ss:Bold="1"/>
        </Style>
    </Styles>' . "\n";
}

function xlsEnd(): string { return '</Workbook>'; }

function xlsSheetStart(string $name): string {
    return "<Worksheet ss:Name=\"$name\"><Table>\n";
}
function xlsSheetEnd(): string { return "</Table></Worksheet>\n"; }

function xlsRow(array $cells, string $style = ''): string {
    $s   = $style ? " ss:StyleID=\"$style\"" : '';
    $out = "<Row$s>";
    foreach ($cells as $c) {
        if ($c === null || $c === '') {
            $out .= '<Cell><Data ss:Type="String"></Data></Cell>';
        } elseif (is_float($c) || (is_string($c) && preg_match('/^-?\d+\.\d+$/', $c))) {
            $out .= '<Cell ss:StyleID="currency"><Data ss:Type="Number">' . $c . '</Data></Cell>';
        } elseif (is_int($c)) {
            $out .= '<Cell><Data ss:Type="Number">' . $c . '</Data></Cell>';
        } else {
            $escaped = htmlspecialchars((string)$c, ENT_XML1, 'UTF-8');
            $out .= '<Cell><Data ss:Type="String">' . $escaped . '</Data></Cell>';
        }
    }
    $out .= "</Row>\n";
    return $out;
}

/** Célula numérica com decimal mas sem formatação de moeda (ex: médias de quantidade) */
function xlsNumCell(float $v): string {
    return '<Cell ss:StyleID="number"><Data ss:Type="Number">' . $v . '</Data></Cell>';
}

function xlsHeaderRow(array $cells, string $style = 'header'): string {
    $out = "<Row ss:StyleID=\"$style\">";
    foreach ($cells as $c) {
        $escaped = htmlspecialchars((string)$c, ENT_XML1, 'UTF-8');
        $out .= '<Cell><Data ss:Type="String">' . $escaped . '</Data></Cell>';
    }
    $out .= "</Row>\n";
    return $out;
}

function xlsLabel(string $label, $value, string $styleVal = ''): string {
    $sv  = $styleVal ? " ss:StyleID=\"$styleVal\"" : '';
    $el  = htmlspecialchars($label, ENT_XML1, 'UTF-8');
    $out = '<Row>';
    $out .= '<Cell ss:StyleID="bold"><Data ss:Type="String">' . $el . '</Data></Cell>';
    if (is_float($value) || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value))) {
        $out .= '<Cell ss:StyleID="currency"' . $sv . '><Data ss:Type="Number">' . $value . '</Data></Cell>';
    } elseif (is_int($value)) {
        $out .= '<Cell' . $sv . '><Data ss:Type="Number">' . $value . '</Data></Cell>';
    } else {
        $ev = htmlspecialchars((string)$value, ENT_XML1, 'UTF-8');
        $out .= '<Cell' . $sv . '><Data ss:Type="String">' . $ev . '</Data></Cell>';
    }
    $out .= "</Row>\n";
    return $out;
}

function xlsBlankRow(): string { return "<Row><Cell><Data ss:Type=\"String\"></Data></Cell></Row>\n"; }

// ---- Busca dados ----
$lojas = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();

// Estoque atual por loja+produto (para aba Previsão)
$estoque_map = [];
foreach ($db->query("SELECT loja_id, nome, estoque, estoque_minimo FROM produtos")->fetchAll() as $e) {
    $estoque_map[$e['loja_id']][$e['nome']] = (int)$e['estoque'];
}

$produtos = $db->query("
    SELECT p.*, l.nome AS loja_nome, l.estado
    FROM produtos p JOIN lojas l ON l.id = p.loja_id
    ORDER BY l.id, p.categoria, p.nome
")->fetchAll();

$vendas = $db->query("
    SELECT v.*, l.nome AS loja_nome, l.estado,
           c.nome AS cliente_nome
    FROM vendas v
    JOIN lojas l   ON l.id = v.loja_id
    JOIN clientes c ON c.id = v.cliente_id
    ORDER BY v.data_venda DESC
")->fetchAll();

// Clientes totais e por loja
$clientes_total = (int)$db->query("SELECT COUNT(*) FROM clientes")->fetchColumn();
$clientes_por_loja = $db->query("
    SELECT l.nome AS loja_nome, l.estado, COUNT(c.id) AS total_clientes
    FROM lojas l
    LEFT JOIN clientes c ON c.loja_id = l.id
    GROUP BY l.id ORDER BY l.id
")->fetchAll();

// Vendas por mês
$vendas_por_mes = $db->query("
    SELECT DATE_FORMAT(data_venda,'%m/%Y') AS mes,
           COUNT(id)   AS n_vendas,
           SUM(qtd)    AS qtd_total,
           SUM(total)  AS receita
    FROM vendas
    GROUP BY DATE_FORMAT(data_venda,'%Y-%m')
    ORDER BY DATE_FORMAT(data_venda,'%Y-%m')
")->fetchAll();

// Receita por estado
$receita_por_estado = $db->query("
    SELECT l.estado,
           COUNT(DISTINCT v.cliente_id) AS clientes,
           COUNT(v.id)                  AS n_vendas,
           SUM(v.qtd)                   AS qtd_produtos,
           SUM(v.total)                 AS receita
    FROM vendas v JOIN lojas l ON l.id = v.loja_id
    GROUP BY l.estado ORDER BY receita DESC
")->fetchAll();

// Receita total
$receita_total = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM vendas")->fetchColumn();
$qtd_vendas    = (int)$db->query("SELECT COUNT(*) FROM vendas")->fetchColumn();
$qtd_produtos_vendidos = (int)$db->query("SELECT COALESCE(SUM(qtd),0) FROM vendas")->fetchColumn();

// Receita por filial
$receita_por_filial = $db->query("
    SELECT l.nome, l.tipo, l.estado,
           COUNT(DISTINCT v.cliente_id)  AS clientes,
           COUNT(v.id)                   AS n_vendas,
           COALESCE(SUM(v.qtd),0)        AS qtd_produtos,
           COALESCE(SUM(v.total),0.0)    AS receita,
           COALESCE(AVG(v.total),0.0)    AS ticket_medio
    FROM lojas l
    LEFT JOIN vendas v ON v.loja_id = l.id
    GROUP BY l.id ORDER BY receita DESC
")->fetchAll();

// Receita por produto
$receita_por_produto = $db->query("
    SELECT v.produto, v.categoria,
           SUM(v.qtd)    AS qtd_total,
           MAX(v.preco_unit) AS preco_unit,
           SUM(v.total)  AS receita_total
    FROM vendas v
    GROUP BY v.produto, v.categoria
    ORDER BY receita_total DESC
")->fetchAll();

// Resumo por loja (para aba Resumo)
$resumo_loja = $db->query("
    SELECT l.nome, l.tipo,
        COALESCE(COUNT(v.id),0)    AS n_vendas,
        COALESCE(SUM(v.qtd),0)     AS qtd_total,
        COALESCE(SUM(v.total),0.0) AS receita,
        COALESCE(AVG(v.total),0.0) AS ticket_medio
    FROM lojas l
    LEFT JOIN vendas v ON v.loja_id = l.id
    GROUP BY l.id ORDER BY receita DESC
")->fetchAll();

// Previsão 6 meses 
$previsao = $db->query("
    SELECT v.loja_id, l.nome AS loja_nome, v.produto, v.categoria,
        SUM(v.qtd)                AS total_vendido,
        ROUND(SUM(v.qtd)/3.0, 2) AS media_mensal,
        MAX(v.preco_unit)         AS preco_unit
    FROM vendas v JOIN lojas l ON l.id = v.loja_id
    GROUP BY v.loja_id, v.produto, v.categoria
    ORDER BY l.id, media_mensal DESC
")->fetchAll();

// ---- Gera o arquivo ----
$filename = 'MotoStock_' . date('Y-m-d') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo xlsBegin();

// ====================================================
// ABA 1: LOJAS (Matriz + Filiais)
// ====================================================
echo xlsSheetStart('Lojas');
echo '<Row><Cell ss:StyleID="header_orange"><Data ss:Type="String">MotoStock — Lojas Cadastradas</Data></Cell></Row>' . "\n";
echo xlsBlankRow();
echo xlsHeaderRow(['#', 'Nome', 'Tipo', 'Cidade', 'Estado', 'Endereço']);
foreach ($lojas as $i => $l) {
    echo xlsRow([
        $i + 1,
        $l['nome'],
        $l['tipo'],
        $l['cidade'],
        $l['estado'],
        $l['endereco'] ?? '',
    ]);
}
echo xlsSheetEnd();

// ====================================================
// ABA 2: PRODUTOS
// ====================================================
echo xlsSheetStart('Produtos');
echo xlsHeaderRow(['#', 'Produto', 'Categoria', 'Loja', 'Estado', 'Preço Unit. (R$)', 'Estoque', 'Est. Mínimo', 'Valor em Estoque']);
$i = 1;
foreach ($produtos as $p) {
    echo xlsRow([
        $i++,
        $p['nome'],
        $p['categoria'],
        $p['loja_nome'],
        $p['estado'],
        (float)$p['preco'],
        (int)$p['estoque'],
        (int)$p['estoque_minimo'],
        round($p['preco'] * $p['estoque'], 2),
    ]);
}
echo xlsSheetEnd();

// ====================================================
// ABA 3: PREVISÃO 6 MESES
// ====================================================
echo xlsSheetStart('Previsão 6 Meses');
$MESES = ['Abr/25','Mai/25','Jun/25','Jul/25','Ago/25','Set/25'];

echo xlsHeaderRow(array_merge(
    ['Loja', 'Produto', 'Categoria', 'Hist. 3 meses', 'Méd/Mês'],
    $MESES,
    ['Total 6m', 'Estoque Atual', 'Repor', 'Receita Prevista (R$)']
));

foreach ($previsao as $p) {
    $media      = (float)$p['media_mensal'];
    $total_hist = (int)$p['total_vendido'];
    $total6m    = (int)round($media * 6);
    $est_atual  = $estoque_map[$p['loja_id']][$p['produto']] ?? 0;
    $repor      = max(0, $total6m - $est_atual);

    $out = '<Row>';

    // Colunas de texto
    foreach ([$p['loja_nome'], $p['produto'], $p['categoria']] as $c) {
        $escaped = htmlspecialchars((string)$c, ENT_XML1, 'UTF-8');
        $out .= '<Cell><Data ss:Type="String">' . $escaped . '</Data></Cell>';
    }

    // Histórico Jan–Mar 
    $out .= '<Cell><Data ss:Type="Number">' . $total_hist . '</Data></Cell>';

    // Média mensal (com 2 casas decimais)
    $out .= xlsNumCell($media);

    // Colunas mensais — demanda INDIVIDUAL de cada mês (incremental)
    for ($m = 1; $m <= 6; $m++) {
        $acum_atual  = (int)round($media * $m);
        $acum_antes  = (int)round($media * ($m - 1));
        $demanda_mes = $acum_atual - $acum_antes;
        $out .= '<Cell><Data ss:Type="Number">' . $demanda_mes . '</Data></Cell>';
    }

    // Total 6m, Estoque Atual, Repor, Receita Prevista
    $out .= '<Cell><Data ss:Type="Number">' . $total6m . '</Data></Cell>';
    $out .= '<Cell><Data ss:Type="Number">' . $est_atual . '</Data></Cell>';
    $out .= '<Cell><Data ss:Type="Number">' . $repor . '</Data></Cell>';
    $out .= '<Cell ss:StyleID="currency"><Data ss:Type="Number">' . round($total6m * $p['preco_unit'], 2) . '</Data></Cell>';
    $out .= "</Row>\n";
    echo $out;
}
echo xlsSheetEnd();

// ====================================================
// ABA 4: DASHBOARD (todos os KPIs do trabalho)
// ====================================================
echo xlsSheetStart('Dashboard');

// Título
echo '<Row><Cell ss:StyleID="header_orange"><Data ss:Type="String">MotoStock — Dashboard Financeiro</Data></Cell></Row>' . "\n";
echo xlsBlankRow();

// --- Bloco 1: KPIs Gerais ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">INDICADORES GERAIS</Data></Cell></Row>' . "\n";
echo xlsLabel('Número de Clientes',          $clientes_total);
echo xlsLabel('Quantidade de Vendas (total)', $qtd_vendas);
echo xlsLabel('Quantidade de Produtos Vendidos', $qtd_produtos_vendidos);
echo xlsLabel('Receita Total (R$)',           $receita_total);
echo xlsBlankRow();

// --- Bloco 2: Vendas por Mês ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">VENDAS POR MÊS</Data></Cell></Row>' . "\n";
echo xlsHeaderRow(['Mês', 'Nº Vendas', 'Qtd Produtos Vendidos', 'Receita (R$)']);
foreach ($vendas_por_mes as $m) {
    echo xlsRow([$m['mes'], (int)$m['n_vendas'], (int)$m['qtd_total'], (float)$m['receita']]);
}
echo xlsBlankRow();

// --- Bloco 3: Receita por Estado ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">RECEITA POR ESTADO</Data></Cell></Row>' . "\n";
echo xlsHeaderRow(['Estado', 'Clientes', 'Nº Vendas', 'Qtd Produtos', 'Receita (R$)']);
foreach ($receita_por_estado as $e) {
    echo xlsRow([$e['estado'], (int)$e['clientes'], (int)$e['n_vendas'], (int)$e['qtd_produtos'], (float)$e['receita']]);
}
echo xlsBlankRow();

// --- Bloco 4: Receita por Filial ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">RECEITA TOTAL POR FILIAL</Data></Cell></Row>' . "\n";
echo xlsHeaderRow(['Loja', 'Tipo', 'Estado', 'Clientes', 'Nº Vendas', 'Qtd Produtos', 'Receita (R$)', 'Ticket Médio (R$)']);
foreach ($receita_por_filial as $f) {
    echo xlsRow([
        $f['nome'], $f['tipo'], $f['estado'],
        (int)$f['clientes'], (int)$f['n_vendas'], (int)$f['qtd_produtos'],
        (float)$f['receita'], (float)$f['ticket_medio'],
    ]);
}
echo xlsBlankRow();

// --- Bloco 5: Receita por Produto ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">RECEITA TOTAL POR PRODUTO</Data></Cell></Row>' . "\n";
echo xlsHeaderRow(['Produto', 'Categoria', 'Qtd Vendida', 'Preço Unit. (R$)', 'Receita Total (R$)']);
foreach ($receita_por_produto as $p) {
    echo xlsRow([
        $p['produto'], $p['categoria'],
        (int)$p['qtd_total'], (float)$p['preco_unit'], (float)$p['receita_total'],
    ]);
}
echo xlsBlankRow();

// --- Bloco 6: Clientes por Loja ---
echo '<Row><Cell ss:StyleID="header"><Data ss:Type="String">CLIENTES POR LOJA</Data></Cell></Row>' . "\n";
echo xlsHeaderRow(['Loja', 'Estado', 'Total de Clientes']);
foreach ($clientes_por_loja as $cl) {
    echo xlsRow([$cl['loja_nome'], $cl['estado'], (int)$cl['total_clientes']]);
}

echo xlsSheetEnd();

// ====================================================
// ABA 5: RESUMO POR LOJA
// ====================================================
echo xlsSheetStart('Resumo por Loja');
echo xlsHeaderRow(['Loja', 'Tipo', 'Nº Vendas', 'Qtd Total', 'Receita (R$)', 'Ticket Médio (R$)']);
foreach ($resumo_loja as $r) {
    echo xlsRow([
        $r['nome'], $r['tipo'],
        (int)$r['n_vendas'], (int)$r['qtd_total'],
        (float)$r['receita'], (float)$r['ticket_medio'],
    ]);
}
echo xlsSheetEnd();

// ====================================================
// ABA 6: VENDAS
// ====================================================
echo xlsSheetStart('Vendas');
echo xlsHeaderRow(['ID', 'Data', 'Loja', 'Estado', 'Cliente', 'Produto', 'Categoria', 'Qtd', 'Preço Unit. (R$)', 'Total (R$)']);
foreach ($vendas as $v) {
    echo xlsRow([
        $v['codigo'],
        date('d/m/Y', strtotime($v['data_venda'])),
        $v['loja_nome'],
        $v['estado'],
        $v['cliente_nome'],
        $v['produto'],
        $v['categoria'],
        (int)$v['qtd'],
        (float)$v['preco_unit'],
        (float)$v['total'],
    ]);
}
echo xlsSheetEnd();

// ====================================================
// ABA 7: CLIENTES
// ====================================================
$todos_clientes = $db->query("
    SELECT c.id, c.nome, c.email, l.nome AS loja_nome, l.estado
    FROM clientes c JOIN lojas l ON l.id = c.loja_id
    ORDER BY l.id, c.nome
")->fetchAll();

echo xlsSheetStart('Clientes');
echo xlsHeaderRow(['#', 'Nome', 'E-mail', 'Loja', 'Estado']);
$i = 1;
foreach ($todos_clientes as $c) {
    echo xlsRow([$i++, $c['nome'], $c['email'], $c['loja_nome'], $c['estado']]);
}
echo xlsSheetEnd();

// ====================================================
// ABA 8: TRANSFERÊNCIAS
// ====================================================
$transferencias = $db->query("
    SELECT t.*, lo.nome AS origem_nome, ld.nome AS destino_nome
    FROM transferencias t
    JOIN lojas lo ON lo.id = t.loja_origem
    JOIN lojas ld ON ld.id = t.loja_destino
    ORDER BY t.data_transferencia DESC
")->fetchAll();

echo xlsSheetStart('Transferências');
echo xlsHeaderRow(['#', 'Data/Hora', 'Produto', 'Categoria', 'Origem', 'Destino', 'Qtd']);
$i = 1;
foreach ($transferencias as $t) {
    echo xlsRow([
        $i++,
        date('d/m/Y H:i', strtotime($t['data_transferencia'])),
        $t['produto_nome'],
        $t['categoria'],
        $t['origem_nome'],
        $t['destino_nome'],
        (int)$t['quantidade'],
    ]);
}
echo xlsSheetEnd();

echo xlsEnd();
exit;