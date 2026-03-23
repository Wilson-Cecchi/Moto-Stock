<?php
// admin/metas.php — Gerenciar metas mensais de vendas
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin(); // somente admin

$db    = getDB();
$ok    = '';
$erro  = '';

$lojas = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();
$meses_nomes = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$ano_atual   = 2025;

// --- Salvar ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['metas'])) {
    $stmt = $db->prepare("
        INSERT INTO metas (loja_id, mes, ano, meta_valor)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE meta_valor = VALUES(meta_valor)
    ");
    foreach ($_POST['metas'] as $key => $valor) {
        [$loja_id, $mes] = explode('_', $key);
        $stmt->execute([(int)$loja_id, (int)$mes, $ano_atual, (float)str_replace(',', '.', $valor)]);
    }
    $ok = 'Metas salvas com sucesso!';
}

// --- Ler metas atuais ---
$raw = $db->query("SELECT loja_id, mes, meta_valor FROM metas WHERE ano = $ano_atual")->fetchAll();
$metas = [];
foreach ($raw as $r) {
    $metas[$r['loja_id']][$r['mes']] = $r['meta_valor'];
}

// --- Realizado por loja/mês ---
$real_raw = $db->query("
    SELECT loja_id, MONTH(data_venda) AS mes, SUM(total) AS realizado
    FROM vendas WHERE YEAR(data_venda) = $ano_atual
    GROUP BY loja_id, mes
")->fetchAll();
$real = [];
foreach ($real_raw as $r) {
    $real[$r['loja_id']][$r['mes']] = $r['realizado'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Metas de Vendas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .metas-table { width:100%; border-collapse:collapse; font-size:.84rem; }
        .metas-table th, .metas-table td { border:1px solid var(--border); padding:9px 12px; }
        .metas-table th { background:var(--surface3); color:var(--text2); font-weight:600; font-family:'Rajdhani',sans-serif; letter-spacing:.04em; text-align:left; }
        .metas-table td:first-child { color:var(--text); font-weight:500; }
        .metas-table input[type=number] {
            background: var(--surface3); border:1px solid var(--border2);
            color:var(--text); padding:6px 8px; border-radius:5px;
            font-family:'Fira Code',monospace; font-size:.8rem;
            width: 110px; outline:none;
        }
        .metas-table input[type=number]:focus { border-color:var(--accent); }
        .real-val { font-family:'Fira Code',monospace; font-size:.78rem; color:var(--text2); }
        .pct-ok   { color: var(--ok); }
        .pct-warn { color: var(--warn); }
        .pct-bad  { color: var(--danger); }
        .save-btn {
            padding:10px 28px; background:var(--accent); color:#000;
            border:none; border-radius:6px; font-size:1rem; font-weight:700;
            cursor:pointer; font-family:'Rajdhani',sans-serif; letter-spacing:.06em;
            text-transform:uppercase; margin-top:20px;
        }
        .save-btn:hover { opacity:.85; }
        .msg-ok   { background:var(--ok-bg); border:1px solid var(--ok); color:var(--ok); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .msg-err  { background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
    </style>
</head>
<body>
<?php $admin_page = 'metas'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Metas de Vendas — <?= $ano_atual ?></h1></div>
        <div class="topbar-meta">MotoStock · <?= date('d/m/Y') ?></div>
    </header>
    <div class="content">

        <?php if ($ok):  ?><div class="msg-ok">✓ <?= $ok ?></div><?php endif; ?>
        <?php if ($erro):?><div class="msg-err">✗ <?= $erro ?></div><?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Metas Mensais por Loja</span>
                <span class="panel-sub">valores em R$ — edite e salve</span>
            </div>
            <div class="panel-body" style="padding:0;overflow-x:auto">
                <form method="post">
                    <table class="metas-table">
                        <thead>
                            <tr>
                                <th>Loja</th>
                                <?php foreach ($meses_nomes as $i => $m): ?>
                                <th><?= $m ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lojas as $loja): ?>
                            <!-- Meta row -->
                            <tr>
                                <td><?= htmlspecialchars($loja['nome']) ?></td>
                                <?php for ($mes = 1; $mes <= 12; $mes++):
                                    $val = $metas[$loja['id']][$mes] ?? 0;
                                ?>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                        name="metas[<?= $loja['id'] ?>_<?= $mes ?>]"
                                        value="<?= number_format($val, 2, '.', '') ?>">
                                </td>
                                <?php endfor; ?>
                            </tr>
                            <!-- Realizado row -->
                            <tr style="background:var(--surface)">
                                <td style="color:var(--text3);font-size:.75rem;padding-left:20px">↳ realizado</td>
                                <?php for ($mes = 1; $mes <= 12; $mes++):
                                    $r   = $real[$loja['id']][$mes] ?? 0;
                                    $mt  = $metas[$loja['id']][$mes] ?? 0;
                                    $pct = $mt > 0 ? round($r / $mt * 100) : null;
                                    $cls = $pct === null ? '' : ($pct >= 100 ? 'pct-ok' : ($pct >= 70 ? 'pct-warn' : 'pct-bad'));
                                ?>
                                <td class="real-val <?= $cls ?>">
                                    <?= $r > 0 ? 'R$ ' . number_format($r, 0, ',', '.') : '—' ?>
                                    <?php if ($pct !== null && $r > 0): ?>
                                    <br><small><?= $pct ?>%</small>
                                    <?php endif; ?>
                                </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="padding:16px">
                        <button type="submit" class="save-btn">💾 Salvar Metas</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</main>
</body>
</html>
