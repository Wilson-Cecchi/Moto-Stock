<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin();
$db = getDB();

$msg   = '';
$erro  = '';
$edit  = null;

// --- AÇÕES ---
$acao = $_POST['acao'] ?? $_GET['acao'] ?? '';

// DELETAR
if ($acao === 'deletar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);
    $msg = 'Produto removido com sucesso.';
}

// SALVAR (novo ou edição)
if ($acao === 'salvar') {
    $id         = (int)($_POST['id'] ?? 0);
    $loja_id    = (int)$_POST['loja_id'];
    $nome       = trim($_POST['nome']);
    $categoria  = trim($_POST['categoria']);
    $preco      = (float)str_replace(',', '.', $_POST['preco']);
    $estoque    = (int)$_POST['estoque'];
    $est_min    = (int)$_POST['estoque_minimo'];

    if (!$nome || !$categoria || $preco <= 0) {
        $erro = 'Preencha todos os campos corretamente.';
    } else {
        if ($id > 0) {
            $db->prepare("UPDATE produtos SET loja_id=?, nome=?, categoria=?, preco=?, estoque=?, estoque_minimo=? WHERE id=?")
               ->execute([$loja_id, $nome, $categoria, $preco, $estoque, $est_min, $id]);
            $msg = 'Produto atualizado com sucesso.';
        } else {
            $db->prepare("INSERT INTO produtos (loja_id, nome, categoria, preco, estoque, estoque_minimo) VALUES (?,?,?,?,?,?)")
               ->execute([$loja_id, $nome, $categoria, $preco, $estoque, $est_min]);
            $msg = 'Produto adicionado com sucesso.';
        }
    }
}

// EDITAR — carrega dados
if ($acao === 'editar' && isset($_GET['id'])) {
    $edit = $db->prepare("SELECT * FROM produtos WHERE id = ?");
    $edit->execute([(int)$_GET['id']]);
    $edit = $edit->fetch();
}

// Listagem com filtro de loja
$loja_id_f = (int)($_GET['loja'] ?? 0);
$sql_w     = $loja_id_f ? 'WHERE p.loja_id = ' . $loja_id_f : '';
$produtos  = $db->query("SELECT p.*, l.nome AS loja_nome FROM produtos p JOIN lojas l ON l.id=p.loja_id $sql_w ORDER BY l.id, p.categoria, p.nome")->fetchAll();
$lojas     = $db->query("SELECT * FROM lojas ORDER BY id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Produtos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .form-grid.full { grid-template-columns:1fr; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field label { font-size:.73rem; color:var(--text3); text-transform:uppercase; letter-spacing:.08em; font-family:'Fira Code',monospace; }
        .field input, .field select {
            background: var(--surface3); border: 1px solid var(--border2);
            color: var(--text); padding: 9px 12px; border-radius: 6px;
            font-size: .88rem; outline: none; font-family:'Inter',sans-serif;
        }
        .field input:focus, .field select:focus { border-color: var(--accent); }
        .form-actions { display:flex; gap:10px; margin-top:6px; }
        .msg-ok  { background:#052e16; border:1px solid #166534; color:var(--ok); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.84rem; }
        .msg-err { background:var(--danger-bg); border:1px solid #7f1d1d; color:var(--danger); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.84rem; }
        .act-link { font-size:.8rem; font-family:'Fira Code',monospace; text-decoration:none; padding:4px 10px; border-radius:4px; }
        .act-edit   { color:#60a5fa; background:#0f1f3d; border:1px solid #1e3a5f; }
        .act-delete { color:var(--danger); background:var(--danger-bg); border:1px solid #7f1d1d; }
    </style>
</head>
<body>
<?php $admin_page = 'produtos'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>
<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1><?= $edit ? 'Editar Produto' : 'Gerenciar Produtos' ?></h1></div>
        <div class="topbar-meta">MotoStock · Admin</div>
    </header>
    <div class="content">

        <?php if ($msg):  ?><div class="msg-ok"><?= $msg ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="msg-err"><?= $erro ?></div><?php endif; ?>

        <!-- FORMULÁRIO -->
        <div class="panel" style="margin-bottom:24px">
            <div class="panel-header">
                <span class="panel-title"><?= $edit ? 'Editar: ' . htmlspecialchars($edit['nome']) : 'Adicionar Novo Produto' ?></span>
            </div>
            <div class="panel-body">
                <form method="post" action="/motostock/admin/produtos.php">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
                    <div class="form-grid" style="margin-bottom:14px">
                        <div class="field">
                            <label>Loja</label>
                            <select name="loja_id">
                                <?php foreach ($lojas as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= ($edit['loja_id'] ?? 0) == $l['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label>Categoria</label>
                            <select name="categoria">
                                <?php foreach ($CATEGORIAS as $c): ?>
                                <option value="<?= $c ?>" <?= ($edit['categoria'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid full" style="margin-bottom:14px">
                        <div class="field">
                            <label>Nome do Produto</label>
                            <input type="text" name="nome" value="<?= htmlspecialchars($edit['nome'] ?? '') ?>" placeholder="Ex: Capacete Fechado Pro">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="field">
                            <label>Preço Unitário (R$)</label>
                            <input type="text" name="preco" value="<?= $edit['preco'] ?? '' ?>" placeholder="0.00">
                        </div>
                        <div class="field">
                            <label>Estoque Atual</label>
                            <input type="number" name="estoque" value="<?= $edit['estoque'] ?? 0 ?>" min="0">
                        </div>
                        <div class="field">
                            <label>Estoque Mínimo</label>
                            <input type="number" name="estoque_minimo" value="<?= $edit['estoque_minimo'] ?? 5 ?>" min="1">
                        </div>
                    </div>
                    <div class="form-actions" style="margin-top:16px">
                        <button class="btn" type="submit"><?= $edit ? 'Salvar Alterações' : 'Adicionar Produto' ?></button>
                        <?php if ($edit): ?>
                        <a href="/motostock/admin/produtos.php" class="btn btn-ghost">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- FILTRO -->
        <form method="get" class="filters" style="margin-bottom:16px">
            <label>Filtrar por loja</label>
            <select name="loja" onchange="this.form.submit()">
                <option value="0">Todas</option>
                <?php foreach ($lojas as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $loja_id_f == $l['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['nome']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- TABELA -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Produtos Cadastrados</span>
                <span class="panel-sub"><?= count($produtos) ?> produto(s)</span>
            </div>
            <div class="panel-body" style="padding:0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Loja</th>
                            <th class="num">Preço</th>
                            <th class="num">Estoque</th>
                            <th class="num">Mínimo</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($produtos as $p): ?>
                    <tr>
                        <td style="font-weight:500"><?= htmlspecialchars($p['nome']) ?></td>
                        <td><span class="badge badge-cat"><?= $p['categoria'] ?></span></td>
                        <td style="font-size:.8rem;color:var(--text2)"><?= htmlspecialchars($p['loja_nome']) ?></td>
                        <td class="num mono"><?= brl($p['preco']) ?></td>
                        <td class="num mono" style="color:<?= $p['estoque'] <= $p['estoque_minimo'] ? 'var(--danger)' : 'var(--ok)' ?>"><?= $p['estoque'] ?></td>
                        <td class="num mono"><?= $p['estoque_minimo'] ?></td>
                        <td style="display:flex;gap:6px;align-items:center">
                            <a href="?acao=editar&id=<?= $p['id'] ?>" class="act-link act-edit">Editar</a>
                            <a href="?acao=deletar&id=<?= $p['id'] ?>"
                               onclick="return confirm('Remover <?= htmlspecialchars(addslashes($p['nome'])) ?>?')"
                               class="act-link act-delete">Remover</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>
</body>
</html>