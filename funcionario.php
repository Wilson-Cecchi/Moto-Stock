<?php
require_once __DIR__ . '/config.php';
requireAuth();

// Só funcionários (e admin/gerente para teste) acessam aqui
$u = getCurrentUser();
$loja_id = (int)($u['loja_id'] ?? 0);
if (!$loja_id) {
    header('Location: /motostock/'); exit;
}

$db    = getDB();
$msg   = '';
$erro  = '';
$tab   = $_GET['tab'] ?? 'venda'; // venda | solicitacao

// Dados da loja
$loja  = $db->query("SELECT * FROM lojas WHERE id = $loja_id")->fetch();

// ---- REGISTRAR VENDA ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'venda') {
    $prod_nome = trim($_POST['produto_nome'] ?? '');
    $qtd       = (int)($_POST['qtd'] ?? 0);

    $prod = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
    $prod->execute([$loja_id, $prod_nome]);
    $prod = $prod->fetch();

    if (!$prod) {
        $erro = 'Produto não encontrado.';
    } elseif ($qtd <= 0) {
        $erro = 'Quantidade inválida.';
    } elseif ($prod['estoque'] < $qtd) {
        $erro = "Estoque insuficiente. Disponível: {$prod['estoque']} unidades.";
    } else {
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")
               ->execute([$qtd, $prod['id']]);

            $codigo = 'V' . str_pad($db->query("SELECT COUNT(*)+1 FROM vendas")->fetchColumn(), 4, '0', STR_PAD_LEFT);
            $total  = $qtd * $prod['preco'];
            $db->prepare("INSERT INTO vendas (codigo, data_venda, loja_id, produto, categoria, qtd, preco_unit, total)
                          VALUES (?,CURDATE(),?,?,?,?,?,?)")
               ->execute([$codigo, $loja_id, $prod['nome'], $prod['categoria'], $qtd, $prod['preco'], $total]);

            $db->commit();
            $msg = "Venda registrada! {$qtd}x {$prod['nome']} — " . brl($total);
            $tab = 'venda';
        } catch (Exception $e) {
            $db->rollBack();
            $erro = 'Erro ao registrar venda.';
        }
    }
}

// ---- ABRIR SOLICITAÇÃO ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'solicitar') {
    $tipo       = $_POST['tipo']        ?? 'reposicao';
    $prod_nome  = trim($_POST['produto_nome'] ?? '');
    $categoria  = trim($_POST['categoria']    ?? '');
    $qtd        = (int)($_POST['qtd']         ?? 0);
    $motivo     = trim($_POST['motivo']       ?? '');
    $loja_ced   = $tipo === 'transferencia' ? (int)($_POST['loja_cedente'] ?? 0) : null;

    if (!$prod_nome || $qtd <= 0) {
        $erro = 'Preencha produto e quantidade.';
    } elseif ($tipo === 'transferencia' && (!$loja_ced || $loja_ced === $loja_id)) {
        $erro = 'Selecione uma loja cedente válida.';
    } else {
        // Para reposição, buscar categoria do produto se não informada
        if (!$categoria) {
            $p = $db->prepare("SELECT categoria FROM produtos WHERE loja_id = ? AND nome = ?");
            $p->execute([$loja_id, $prod_nome]);
            $categoria = $p->fetchColumn() ?: 'Outros';
        }
        $db->prepare("INSERT INTO solicitacoes (tipo, funcionario_id, loja_solicitante, produto_nome, categoria, quantidade, loja_cedente, motivo)
                      VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$tipo, $u['id'], $loja_id, $prod_nome, $categoria, $qtd, $loja_ced, $motivo]);
        $msg = 'Solicitação enviada com sucesso!';
        $tab = 'solicitacao';
    }
}

// Produtos da loja
$produtos = $db->query("SELECT * FROM produtos WHERE loja_id = $loja_id ORDER BY categoria, nome")->fetchAll();

// Outras lojas (para transferência)
$outras_lojas = $db->query("SELECT * FROM lojas WHERE id != $loja_id ORDER BY nome")->fetchAll();

// Solicitações do funcionário
$solicitacoes = $db->prepare("
    SELECT s.*, lo.nome AS loja_cedente_nome
    FROM solicitacoes s
    LEFT JOIN lojas lo ON lo.id = s.loja_cedente
    WHERE s.funcionario_id = ?
    ORDER BY s.criado_em DESC
    LIMIT 20
");
$solicitacoes->execute([$u['id']]);
$solicitacoes = $solicitacoes->fetchAll();

$page  = 'funcionario';
$title = 'Painel do Funcionário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Funcionário</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .tab-bar { display:flex; gap:8px; margin-bottom:20px; }
        .tab-btn {
            padding:8px 20px; border-radius:6px; border:1px solid var(--border2);
            background:var(--surface2); color:var(--text2); cursor:pointer;
            font-family:'Rajdhani',sans-serif; font-size:.95rem; font-weight:600;
            letter-spacing:.04em; text-decoration:none;
        }
        .tab-btn.active { background:var(--accent-dim); color:var(--accent); border-color:var(--accent); }
        .tab-btn:hover:not(.active) { border-color:var(--border); color:var(--text); }
        .field { margin-bottom:14px; }
        .field label { display:block; font-size:.73rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; margin-bottom:5px; }
        .field input, .field select, .field textarea {
            width:100%; background:var(--surface3); border:1px solid var(--border2);
            color:var(--text); padding:9px 12px; border-radius:6px; font-size:.88rem;
            outline:none; font-family:'Inter',sans-serif;
        }
        .field input:focus, .field select:focus, .field textarea:focus { border-color:var(--accent); }
        .field textarea { resize:vertical; min-height:70px; }
        .btn-primary {
            padding:10px 24px; background:var(--accent); color:#000; border:none;
            border-radius:6px; font-weight:700; cursor:pointer;
            font-family:'Rajdhani',sans-serif; font-size:.95rem; letter-spacing:.05em; text-transform:uppercase;
        }
        .btn-primary:hover { opacity:.85; }
        .msg-ok  { background:var(--ok-bg); border:1px solid var(--ok); color:var(--ok); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .msg-err { background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .status-pill {
            display:inline-block; padding:2px 10px; border-radius:99px; font-size:.72rem; font-weight:700; font-family:'Fira Code',monospace;
        }
        .status-pendente    { background:#1c1502; color:var(--warn); }
        .status-aprovado_origem { background:#052e16; color:var(--ok); }
        .status-aprovado    { background:#052e16; color:var(--ok); }
        .status-rejeitado   { background:var(--danger-bg); color:var(--danger); }
        .estoque-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px; margin-bottom:24px; }
        .estoque-card {
            background:var(--surface2); border:1px solid var(--border);
            border-radius:8px; padding:14px 16px;
        }
        .estoque-card-nome { font-weight:500; font-size:.88rem; margin-bottom:4px; }
        .estoque-card-cat  { font-size:.72rem; color:var(--text3); margin-bottom:8px; }
        .estoque-card-row  { display:flex; justify-content:space-between; align-items:center; }
        .estoque-card-preco { font-family:'Fira Code',monospace; font-size:.8rem; color:var(--text2); }
        .estoque-card-qtd   { font-family:'Fira Code',monospace; font-size:.88rem; font-weight:700; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="brand">
        <span class="brand-icon">⚙</span>
        <div>
            <div class="brand-name">MotoStock</div>
            <div class="brand-sub"><?= htmlspecialchars($loja['nome'] ?? '') ?></div>
        </div>
    </div>
    <nav class="nav">
        <a href="?tab=venda"       class="nav-item <?= $tab==='venda'       ?'active':'' ?>"><span class="nav-icon">◎</span> Registrar Venda</a>
        <a href="?tab=solicitacao" class="nav-item <?= $tab==='solicitacao' ?'active':'' ?>"><span class="nav-icon">◈</span> Solicitações</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-chip" style="margin-bottom:10px">
            <div class="user-avatar"><?= strtoupper(substr($u['nome'] ?? 'F', 0, 1)) ?></div>
            <div>
                <div class="user-chip-name"><?= htmlspecialchars($u['nome'] ?? '') ?></div>
                <div class="user-chip-role">Funcionário</div>
            </div>
        </div>
        <a href="/motostock/logout.php" class="nav-item" style="color:var(--danger)">
            <span class="nav-icon">→</span> Sair
        </a>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1><?= $tab === 'venda' ? 'Registrar Venda' : 'Solicitações' ?></h1></div>
        <div class="topbar-meta"><?= date('d/m/Y') ?> · <?= htmlspecialchars($loja['nome'] ?? '') ?></div>
    </header>
    <div class="content">

        <?php if ($msg):  ?><div class="msg-ok">✓ <?= $msg ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="msg-err">✗ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <?php if ($tab === 'venda'): ?>
        <!-- ======= ESTOQUE DA LOJA ======= -->
        <div class="panel" style="margin-bottom:20px">
            <div class="panel-header">
                <span class="panel-title">Estoque — <?= htmlspecialchars($loja['nome'] ?? '') ?></span>
                <span class="panel-sub"><?= count($produtos) ?> produtos</span>
            </div>
            <div class="panel-body">
                <div class="estoque-grid">
                <?php foreach ($produtos as $p):
                    $cor = $p['estoque'] <= 0 ? 'var(--danger)' : ($p['estoque'] <= $p['estoque_minimo'] ? 'var(--warn)' : 'var(--ok)');
                ?>
                <div class="estoque-card">
                    <div class="estoque-card-nome"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="estoque-card-cat"><?= $p['categoria'] ?></div>
                    <div class="estoque-card-row">
                        <span class="estoque-card-preco"><?= brl($p['preco']) ?></span>
                        <span class="estoque-card-qtd" style="color:<?= $cor ?>"><?= $p['estoque'] ?> un.</span>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ======= FORMULÁRIO DE VENDA ======= -->
        <div class="panel" style="max-width:520px">
            <div class="panel-header"><span class="panel-title">Nova Venda</span></div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="acao" value="venda">
                    <div class="field">
                        <label>Produto</label>
                        <select name="produto_nome" required onchange="atualizarPreco(this)">
                            <option value="">Selecione...</option>
                            <?php foreach ($produtos as $p): ?>
                            <option value="<?= htmlspecialchars($p['nome']) ?>"
                                    data-preco="<?= $p['preco'] ?>"
                                    data-estoque="<?= $p['estoque'] ?>"
                                    <?= $p['estoque'] <= 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($p['nome']) ?> (<?= $p['estoque'] ?> un.)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Quantidade</label>
                        <input type="number" name="qtd" id="qtdVenda" min="1" value="1" required>
                    </div>
                    <div style="font-size:.8rem;color:var(--text3);margin-bottom:14px;font-family:'Fira Code',monospace" id="totalPreview"></div>
                    <button type="submit" class="btn-primary">Registrar Venda</button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <!-- ======= ABRIR SOLICITAÇÃO ======= -->
        <div class="panel" style="max-width:580px; margin-bottom:24px">
            <div class="panel-header"><span class="panel-title">Nova Solicitação</span></div>
            <div class="panel-body">
                <form method="post">
                    <input type="hidden" name="acao" value="solicitar">
                    <div class="field">
                        <label>Tipo</label>
                        <select name="tipo" id="tipoSolic" onchange="toggleCedente(this.value)">
                            <option value="reposicao">Reposição de Estoque</option>
                            <option value="transferencia">Transferência de Outra Loja</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Categoria</label>
                        <select name="categoria" id="categoriaSolic" onchange="filtrarProdutos(this.value)" required>
                            <option value="">— selecione —</option>
                            <?php
                            $cats_unicas = array_unique(array_column($produtos, 'categoria'));
                            sort($cats_unicas);
                            foreach ($cats_unicas as $cat): ?>
                            <option><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Produto</label>
                        <select name="produto_nome" id="produtoSolic" required>
                            <option value="">— selecione a categoria primeiro —</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Quantidade</label>
                        <input type="number" name="qtd" min="1" value="1" required>
                    </div>
                    <div class="field" id="cedenteFld" style="display:none">
                        <label>Loja Cedente</label>
                        <select name="loja_cedente">
                            <option value="">Selecione...</option>
                            <?php foreach ($outras_lojas as $l): ?>
                            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Motivo / Observação</label>
                        <textarea name="motivo" placeholder="Explique o motivo da solicitação..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Enviar Solicitação</button>
                </form>
            </div>
        </div>

        <!-- ======= HISTÓRICO DE SOLICITAÇÕES ======= -->
        <?php if (!empty($solicitacoes)): ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Minhas Solicitações</span></div>
            <div class="panel-body" style="padding:0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Produto</th>
                            <th class="num">Qtd</th>
                            <th>Loja Cedente</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($solicitacoes as $s): ?>
                    <tr>
                        <td class="mono" style="color:var(--text3)"><?= date('d/m/Y', strtotime($s['criado_em'])) ?></td>
                        <td><?= $s['tipo'] === 'reposicao' ? 'Reposição' : 'Transferência' ?></td>
                        <td><?= htmlspecialchars($s['produto_nome']) ?></td>
                        <td class="num mono"><?= $s['quantidade'] ?></td>
                        <td><?= $s['loja_cedente_nome'] ? htmlspecialchars($s['loja_cedente_nome']) : '—' ?></td>
                        <td>
                            <?php
                            $labels = [
                                'pendente'         => 'Pendente',
                                'aprovado_origem'  => 'Ag. loja cedente',
                                'aprovado'         => 'Aprovado',
                                'rejeitado'        => 'Rejeitado',
                            ];
                            ?>
                            <span class="status-pill status-<?= $s['status'] ?>">
                                <?= $labels[$s['status']] ?? $s['status'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div>
</main>

<script>
// Dados de produtos por categoria (gerados pelo PHP)
const produtosPorCategoria = <?php
    $map = [];
    foreach ($produtos as $p) {
        $map[$p['categoria']][] = $p['nome'];
    }
    echo json_encode($map);
?>;

function filtrarProdutos(categoria) {
    const sel = document.getElementById('produtoSolic');
    sel.innerHTML = '<option value="">— selecione o produto —</option>';
    if (!categoria || !produtosPorCategoria[categoria]) return;
    produtosPorCategoria[categoria].forEach(nome => {
        const opt = document.createElement('option');
        opt.value = nome;
        opt.textContent = nome;
        sel.appendChild(opt);
    });
}

function atualizarPreco(sel) {
    const opt    = sel.options[sel.selectedIndex];
    const preco  = parseFloat(opt.dataset.preco) || 0;
    const estoque= parseInt(opt.dataset.estoque) || 0;
    const qtd    = parseInt(document.getElementById('qtdVenda').value) || 1;
    document.getElementById('qtdVenda').max = estoque;
    atualizarTotal(preco, qtd);
}
document.getElementById('qtdVenda')?.addEventListener('input', function() {
    const sel   = document.querySelector('[name="produto_nome"]');
    const opt   = sel?.options[sel.selectedIndex];
    const preco = parseFloat(opt?.dataset.preco) || 0;
    atualizarTotal(preco, parseInt(this.value) || 0);
});
function atualizarTotal(preco, qtd) {
    const total = preco * qtd;
    document.getElementById('totalPreview').textContent =
        preco ? `Total: R$ ${total.toFixed(2).replace('.',',')}` : '';
}
function toggleCedente(tipo) {
    document.getElementById('cedenteFld').style.display = tipo === 'transferencia' ? '' : 'none';
}
</script>
</body>
</html>