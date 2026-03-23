<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

$msg  = '';
$erro = '';

// Lê mensagem de sucesso via PRG
if (isset($_SESSION['transferencia_msg']) && isset($_GET['ok'])) {
    $msg = $_SESSION['transferencia_msg'];
    unset($_SESSION['transferencia_msg']);
}

$lojas = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();

// Loja origem selecionada
$loja_origem = (int)($_POST['loja_origem'] ?? $_GET['loja_origem'] ?? 1);

// Produtos da loja origem com estoque > 0
$produtos_origem = $db->prepare("
    SELECT * FROM produtos WHERE loja_id = ? AND estoque > 0 ORDER BY categoria, nome
");
$produtos_origem->execute([$loja_origem]);
$produtos_origem = $produtos_origem->fetchAll();

// EXECUTAR TRANSFERÊNCIA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transferir'])) {
    $loja_orig  = (int)$_POST['loja_origem'];
    $loja_dest  = (int)$_POST['loja_destino'];
    $prod_nome  = trim($_POST['produto_nome']);
    $categoria  = trim($_POST['categoria']);
    $qtd        = (int)$_POST['qtd'];

    if ($loja_orig === $loja_dest) {
        $erro = 'Loja de origem e destino não podem ser iguais.';
    } elseif ($qtd <= 0) {
        $erro = 'Quantidade deve ser maior que zero.';
    } elseif (!$prod_nome) {
        $erro = 'Selecione um produto.';
    } else {
        $prod_orig = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
        $prod_orig->execute([$loja_orig, $prod_nome]);
        $prod_orig = $prod_orig->fetch();

        if (!$prod_orig) {
            $erro = 'Produto não encontrado na loja de origem.';
        } elseif ($prod_orig['estoque'] < $qtd) {
            $erro = "Estoque insuficiente. Disponível na origem: {$prod_orig['estoque']} unidades.";
        } else {
            $prod_dest = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
            $prod_dest->execute([$loja_dest, $prod_nome]);
            $prod_dest = $prod_dest->fetch();

            $db->beginTransaction();
            try {
                // Diminui na origem
                $db->prepare("UPDATE produtos SET estoque = estoque - ? WHERE loja_id = ? AND nome = ?")
                   ->execute([$qtd, $loja_orig, $prod_nome]);

                if ($prod_dest) {
                    // Aumenta na destino
                    $db->prepare("UPDATE produtos SET estoque = estoque + ? WHERE loja_id = ? AND nome = ?")
                       ->execute([$qtd, $loja_dest, $prod_nome]);
                } else {
                    // Cria produto na destino
                    $db->prepare("INSERT INTO produtos (loja_id, nome, categoria, preco, estoque, estoque_minimo) VALUES (?,?,?,?,?,?)")
                       ->execute([$loja_dest, $prod_nome, $categoria, $prod_orig['preco'], $qtd, $prod_orig['estoque_minimo']]);
                }

                // Grava no histórico persistente
                $db->prepare("INSERT INTO transferencias (produto_nome, categoria, quantidade, loja_origem, loja_destino) VALUES (?,?,?,?,?)")
                   ->execute([$prod_nome, $categoria, $qtd, $loja_orig, $loja_dest]);

                $db->commit();

                $nomes = [];
                foreach ($lojas as $l) $nomes[$l['id']] = $l['nome'];
                $_SESSION['transferencia_msg'] = "{$qtd} unidade(s) de <strong>{$prod_nome}</strong> transferida(s) de <strong>{$nomes[$loja_orig]}</strong> para <strong>{$nomes[$loja_dest]}</strong>.";
                header('Location: /motostock/admin/transferencia.php?ok=1');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                $erro = 'Erro ao executar transferência. Tente novamente.';
            }
        }
    }
}

// Histórico persistente do banco
$historico = $db->query("
    SELECT t.*, lo.nome AS origem_nome, ld.nome AS destino_nome
    FROM transferencias t
    JOIN lojas lo ON lo.id = t.loja_origem
    JOIN lojas ld ON ld.id = t.loja_destino
    ORDER BY t.data_transferencia DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Transferência de Estoque</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .field { display:flex; flex-direction:column; gap:5px; margin-bottom:14px; }
        .field label { font-size:.73rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; font-family:'Fira Code',monospace; }
        .field input, .field select {
            background: var(--surface3); border: 1px solid var(--border2);
            color: var(--text); padding: 10px 12px; border-radius: 6px;
            font-size: .9rem; outline: none; font-family:'Inter',sans-serif; width:100%;
        }
        .field input:focus, .field select:focus { border-color: var(--accent); }
        .msg-ok  { background:#052e16; border:1px solid #166534; color:var(--ok); padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:.9rem; }
        .msg-err { background:var(--danger-bg); border:1px solid #7f1d1d; color:var(--danger); padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:.9rem; }
        .arrow { display:flex; align-items:center; justify-content:center; font-size:1.5rem; color:var(--accent); padding:0 8px; margin-top:28px; }
        .transfer-grid { display:grid; grid-template-columns:1fr auto 1fr; gap:16px; align-items:start; }
        .estoque-badge { display:inline-block; font-family:'Fira Code',monospace; font-size:.75rem; background:var(--surface3); border:1px solid var(--border2); padding:3px 10px; border-radius:4px; margin-top:4px; color:var(--text2); }
    </style>
</head>
<body>
<?php $admin_page = 'transfer'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Transferência de Estoque</h1></div>
        <div class="topbar-meta">MotoStock · Admin</div>
    </header>
    <div class="content">

        <?php if ($msg):  ?><div class="msg-ok">✓ <?= $msg ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="msg-err">⚠ <?= $erro ?></div><?php endif; ?>

        <div class="panel" style="max-width:780px; margin-bottom:24px">
            <div class="panel-header">
                <span class="panel-title">Mover Produto Entre Lojas</span>
                <span class="panel-sub">o estoque é ajustado automaticamente</span>
            </div>
            <div class="panel-body">
                <form method="post">
                    <div class="transfer-grid" style="margin-bottom:16px">
                        <div>
                            <div class="field">
                                <label>Loja Origem</label>
                                <select name="loja_origem" onchange="this.form.submit()">
                                    <?php foreach ($lojas as $l): ?>
                                    <option value="<?= $l['id'] ?>" <?= $loja_origem == $l['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['nome']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Produto</label>
                                <select name="produto_nome" id="prodSelect" onchange="preencherInfo(this)">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($produtos_origem as $p): ?>
                                    <option value="<?= htmlspecialchars($p['nome']) ?>"
                                            data-cat="<?= $p['categoria'] ?>"
                                            data-estoque="<?= $p['estoque'] ?>">
                                        <?= htmlspecialchars($p['nome']) ?> (<?= $p['estoque'] ?> un.)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="estoque-badge" id="estoqueInfo">selecione um produto</span>
                            </div>
                            <input type="hidden" name="categoria" id="categoriaHidden">
                        </div>

                        <div class="arrow">⇄</div>

                        <div>
                            <div class="field">
                                <label>Loja Destino</label>
                                <select name="loja_destino">
                                    <?php foreach ($lojas as $l): ?>
                                    <?php if ($l['id'] != $loja_origem): ?>
                                    <option value="<?= $l['id'] ?>">
                                        <?= htmlspecialchars($l['nome']) ?>
                                    </option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label>Quantidade a Transferir</label>
                                <input type="number" name="qtd" id="qtdInput" min="1" value="1">
                            </div>
                        </div>
                    </div>

                    <button class="btn" type="submit" name="transferir" value="1">Confirmar Transferência</button>
                </form>
            </div>
        </div>

        <!-- HISTÓRICO PERSISTENTE -->
        <?php if (!empty($historico)): ?>
        <div class="panel" style="max-width:780px">
            <div class="panel-header">
                <span class="panel-title">Histórico de Transferências</span>
                <span class="panel-sub"><?= count($historico) ?> registro(s) recentes</span>
            </div>
            <div class="panel-body" style="padding:0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Produto</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th class="num">Qtd</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($historico as $t): ?>
                    <tr>
                        <td class="mono" style="color:var(--text3)"><?= date('d/m/Y H:i', strtotime($t['data_transferencia'])) ?></td>
                        <td style="font-weight:500"><?= htmlspecialchars($t['produto_nome']) ?></td>
                        <td><span class="badge badge-filial"><?= htmlspecialchars($t['origem_nome']) ?></span></td>
                        <td><span class="badge badge-matriz"><?= htmlspecialchars($t['destino_nome']) ?></span></td>
                        <td class="num mono" style="color:var(--accent)"><?= $t['quantidade'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>
<script>
function preencherInfo(sel) {
    const opt     = sel.options[sel.selectedIndex];
    const estoque = opt.dataset.estoque || 0;
    const cat     = opt.dataset.cat     || '';
    document.getElementById('estoqueInfo').textContent  = estoque ? `Disponível: ${estoque} unidades` : 'selecione um produto';
    document.getElementById('categoriaHidden').value    = cat;
    document.getElementById('qtdInput').max             = estoque;
}
</script>
</body>
</html>