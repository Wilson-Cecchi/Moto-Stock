<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

$msg  = '';
$erro = '';

$lojas = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();

// Produtos da loja selecionada (via AJAX ou POST)
$loja_sel = (int)($_POST['loja_id'] ?? $_GET['loja_id'] ?? 1);
$produtos_loja = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? ORDER BY categoria, nome");
$produtos_loja->execute([$loja_sel]);
$produtos_loja = $produtos_loja->fetchAll();

// REGISTRAR VENDA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar'])) {
    $loja_id   = (int)$_POST['loja_id'];
    $prod_nome = trim($_POST['produto_nome']);
    $categoria = trim($_POST['categoria']);
    $qtd       = (int)$_POST['qtd'];
    $preco     = (float)str_replace(',', '.', $_POST['preco_unit']);
    $data      = $_POST['data_venda'];

    if (!$prod_nome || $qtd <= 0 || $preco <= 0 || !$data) {
        $erro = 'Preencha todos os campos corretamente.';
    } else {
        // Verifica estoque
        $prod_db = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
        $prod_db->execute([$loja_id, $prod_nome]);
        $prod_db = $prod_db->fetch();

        if ($prod_db && $prod_db['estoque'] < $qtd) {
            $erro = "Estoque insuficiente. Disponível: {$prod_db['estoque']} unidades.";
        } else {
            // Gera próximo código
            $ultimo = $db->query("SELECT codigo FROM vendas ORDER BY id DESC LIMIT 1")->fetchColumn();
            $num    = $ultimo ? (int)substr($ultimo, 1) + 1 : 1;
            $codigo = 'V' . str_pad($num, 4, '0', STR_PAD_LEFT);
            $total  = round($qtd * $preco, 2);

            $db->prepare("INSERT INTO vendas (codigo, data_venda, loja_id, produto, categoria, qtd, preco_unit, total) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$codigo, $data, $loja_id, $prod_nome, $categoria, $qtd, $preco, $total]);

            // Atualiza estoque
            if ($prod_db) {
                $db->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")
                   ->execute([$qtd, $prod_db['id']]);
            }

            $msg = "Venda $codigo registrada com sucesso! Total: " . brl($total);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Nova Venda</title>
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
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
        .msg-ok  { background:#052e16; border:1px solid #166534; color:var(--ok); padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:.9rem; }
        .msg-err { background:var(--danger-bg); border:1px solid #7f1d1d; color:var(--danger); padding:12px 16px; border-radius:6px; margin-bottom:16px; font-size:.9rem; }
        .total-preview { background:var(--surface3); border:1px solid var(--border2); border-radius:8px; padding:16px 20px; margin-bottom:20px; }
        .total-label { font-size:.73rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; font-family:'Fira Code',monospace; }
        .total-value { font-family:'Rajdhani',sans-serif; font-size:2rem; font-weight:700; color:var(--accent); }
        .estoque-info { font-size:.78rem; color:var(--text3); font-family:'Fira Code',monospace; margin-top:4px; }
    </style>
</head>
<body>
<?php $admin_page = 'venda'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Registrar Nova Venda</h1></div>
        <div class="topbar-meta">MotoStock · Admin</div>
    </header>
    <div class="content">

        <?php if ($msg):  ?><div class="msg-ok">✓ <?= $msg ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="msg-err">⚠ <?= $erro ?></div><?php endif; ?>

        <div class="panel" style="max-width:680px">
            <div class="panel-header">
                <span class="panel-title">Dados da Venda</span>
                <span class="panel-sub">o estoque é atualizado automaticamente</span>
            </div>
            <div class="panel-body">
                <form method="post" id="formVenda">
                    <!-- Loja -->
                    <div class="field">
                        <label>Loja</label>
                        <select name="loja_id" id="lojaSelect" onchange="atualizarProdutos(this.value)">
                            <?php foreach ($lojas as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $loja_sel == $l['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Produto -->
                    <div class="field">
                        <label>Produto</label>
                        <select name="produto_nome" id="prodSelect" onchange="preencherProduto(this)">
                            <option value="">Selecione...</option>
                            <?php foreach ($produtos_loja as $p): ?>
                            <option value="<?= htmlspecialchars($p['nome']) ?>"
                                    data-cat="<?= $p['categoria'] ?>"
                                    data-preco="<?= $p['preco'] ?>"
                                    data-estoque="<?= $p['estoque'] ?>">
                                <?= htmlspecialchars($p['nome']) ?> — <?= brl($p['preco']) ?> (est: <?= $p['estoque'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="estoque-info" id="estoqueInfo"></span>
                    </div>

                    <input type="hidden" name="categoria" id="categoriaHidden">

                    <div class="form-grid">
                        <div class="field">
                            <label>Preço Unitário (R$)</label>
                            <input type="text" name="preco_unit" id="precoInput" placeholder="0.00">
                        </div>
                        <div class="field">
                            <label>Quantidade</label>
                            <input type="number" name="qtd" id="qtdInput" min="1" value="1" oninput="calcTotal()">
                        </div>
                    </div>

                    <div class="field">
                        <label>Data da Venda</label>
                        <input type="date" name="data_venda" value="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Preview total -->
                    <div class="total-preview">
                        <div class="total-label">Total da Venda</div>
                        <div class="total-value" id="totalPreview">R$ 0,00</div>
                    </div>

                    <button class="btn" type="submit" name="registrar" value="1">Registrar Venda</button>
                </form>
            </div>
        </div>

    </div>
</main>

<script>
const produtosData = <?= json_encode(array_combine(
    array_column($produtos_loja, 'nome'),
    $produtos_loja
)) ?>;

function preencherProduto(sel) {
    const opt = sel.options[sel.selectedIndex];
    const cat   = opt.dataset.cat    || '';
    const preco = opt.dataset.preco  || '';
    const est   = opt.dataset.estoque || '';

    document.getElementById('categoriaHidden').value = cat;
    document.getElementById('precoInput').value      = preco;
    document.getElementById('estoqueInfo').textContent = est ? `Estoque disponível: ${est} unidades` : '';
    calcTotal();
}

function calcTotal() {
    const preco = parseFloat(document.getElementById('precoInput').value) || 0;
    const qtd   = parseInt(document.getElementById('qtdInput').value)    || 0;
    const total = preco * qtd;
    document.getElementById('totalPreview').textContent =
        'R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

document.getElementById('precoInput').addEventListener('input', calcTotal);

function atualizarProdutos(lojaId) {
    window.location.href = '/motostock/admin/venda.php?loja_id=' + lojaId;
}
</script>
</body>
</html>