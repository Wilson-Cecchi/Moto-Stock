<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
requireLogin(); // admin
// Gerentes também acessam via include direto — veja nota abaixo
$db  = getDB();
$u   = getCurrentUser();
$msg = '';
$err = '';

// Gerente só vê solicitações da própria loja
$loja_filtro = getLojaFiltro();

// ---- AÇÃO ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'], $_POST['solic_id'])) {
    $sid  = (int)$_POST['solic_id'];
    $acao = $_POST['acao'];
    $obs  = trim($_POST['obs'] ?? '');

    $solic = $db->prepare("SELECT * FROM solicitacoes WHERE id = ?");
    $solic->execute([$sid]);
    $solic = $solic->fetch();

    if (!$solic) { $err = 'Solicitação não encontrada.'; goto fim; }

    // Rejeitar
    if ($acao === 'rejeitar') {
        $db->prepare("UPDATE solicitacoes SET status='rejeitado', obs_resposta=?, aprovado_por_origem=? WHERE id=?")
           ->execute([$obs, $u['id'], $sid]);
        $msg = 'Solicitação rejeitada.';
        goto fim;
    }

    // Aprovar reposição normal
    if ($acao === 'aprovar' && $solic['tipo'] === 'reposicao') {
        $db->beginTransaction();
        try {
            // Verifica se produto existe na loja
            $prod = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
            $prod->execute([$solic['loja_solicitante'], $solic['produto_nome']]);
            $prod = $prod->fetch();

            if ($prod) {
                $db->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")
                   ->execute([$solic['quantidade'], $prod['id']]);
            } else {
                // Cria produto na loja se não existir
                $db->prepare("INSERT INTO produtos (loja_id, nome, categoria, preco, estoque, estoque_minimo) VALUES (?,?,?,0,?,5)")
                   ->execute([$solic['loja_solicitante'], $solic['produto_nome'], $solic['categoria'], $solic['quantidade']]);
            }
            $db->prepare("UPDATE solicitacoes SET status='aprovado', obs_resposta=?, aprovado_por_origem=? WHERE id=?")
               ->execute([$obs, $u['id'], $sid]);
            $db->commit();
            $msg = 'Reposição aprovada e estoque atualizado!';
        } catch (Exception $e) {
            $db->rollBack();
            $err = 'Erro ao aprovar reposição.';
        }
        goto fim;
    }

    // Aprovar transferência — lado origem (loja solicitante)
    if ($acao === 'aprovar_origem' && $solic['tipo'] === 'transferencia' && $solic['status'] === 'pendente') {
        if (isAdmin()) {
            // Admin aprova pelos dois lados de uma vez
            $db->beginTransaction();
            try {
                // Tira da cedente
                $prod_ced = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
                $prod_ced->execute([$solic['loja_cedente'], $solic['produto_nome']]);
                $prod_ced = $prod_ced->fetch();

                if (!$prod_ced || $prod_ced['estoque'] < $solic['quantidade']) {
                    $db->rollBack();
                    $err = 'Estoque insuficiente na loja cedente.';
                    goto fim;
                }
                $db->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")
                   ->execute([$solic['quantidade'], $prod_ced['id']]);

                // Coloca na solicitante
                $prod_sol = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
                $prod_sol->execute([$solic['loja_solicitante'], $solic['produto_nome']]);
                $prod_sol = $prod_sol->fetch();
                if ($prod_sol) {
                    $db->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")
                       ->execute([$solic['quantidade'], $prod_sol['id']]);
                } else {
                    $db->prepare("INSERT INTO produtos (loja_id, nome, categoria, preco, estoque, estoque_minimo) VALUES (?,?,?,?,?,5)")
                       ->execute([$solic['loja_solicitante'], $solic['produto_nome'], $solic['categoria'], $prod_ced['preco'], $solic['quantidade']]);
                }

                // Registra no histórico de transferências
                $db->prepare("INSERT INTO transferencias (produto_nome, categoria, quantidade, loja_origem, loja_destino) VALUES (?,?,?,?,?)")
                   ->execute([$solic['produto_nome'], $solic['categoria'], $solic['quantidade'], $solic['loja_cedente'], $solic['loja_solicitante']]);

                $db->prepare("UPDATE solicitacoes SET status='aprovado', obs_resposta=?, aprovado_por_origem=?, aprovado_por_cedente=? WHERE id=?")
                   ->execute([$obs, $u['id'], $u['id'], $sid]);
                $db->commit();
                $msg = 'Transferência aprovada pelo admin — estoque atualizado!';
            } catch (Exception $e) {
                $db->rollBack();
                $err = 'Erro ao aprovar transferência.';
            }
        } else {
            // Gerente da loja solicitante aprova o lado origem
            $db->prepare("UPDATE solicitacoes SET status='aprovado_origem', obs_resposta=?, aprovado_por_origem=? WHERE id=?")
               ->execute([$obs, $u['id'], $sid]);
            $msg = 'Aprovação da origem registrada. Aguardando gerente da loja cedente.';
        }
        goto fim;
    }

    // Aprovar transferência — lado cedente
    if ($acao === 'aprovar_cedente' && $solic['tipo'] === 'transferencia' && $solic['status'] === 'aprovado_origem') {
        $db->beginTransaction();
        try {
            $prod_ced = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
            $prod_ced->execute([$solic['loja_cedente'], $solic['produto_nome']]);
            $prod_ced = $prod_ced->fetch();

            if (!$prod_ced || $prod_ced['estoque'] < $solic['quantidade']) {
                $db->rollBack();
                $err = 'Estoque insuficiente na loja cedente.';
                goto fim;
            }
            $db->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ?")
               ->execute([$solic['quantidade'], $prod_ced['id']]);

            $prod_sol = $db->prepare("SELECT * FROM produtos WHERE loja_id = ? AND nome = ?");
            $prod_sol->execute([$solic['loja_solicitante'], $solic['produto_nome']]);
            $prod_sol = $prod_sol->fetch();
            if ($prod_sol) {
                $db->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ?")
                   ->execute([$solic['quantidade'], $prod_sol['id']]);
            } else {
                $db->prepare("INSERT INTO produtos (loja_id, nome, categoria, preco, estoque, estoque_minimo) VALUES (?,?,?,?,?,5)")
                   ->execute([$solic['loja_solicitante'], $solic['produto_nome'], $solic['categoria'], $prod_ced['preco'], $solic['quantidade']]);
            }

            $db->prepare("INSERT INTO transferencias (produto_nome, categoria, quantidade, loja_origem, loja_destino) VALUES (?,?,?,?,?)")
               ->execute([$solic['produto_nome'], $solic['categoria'], $solic['quantidade'], $solic['loja_cedente'], $solic['loja_solicitante']]);

            $db->prepare("UPDATE solicitacoes SET status='aprovado', obs_resposta=?, aprovado_por_cedente=? WHERE id=?")
               ->execute([$obs, $u['id'], $sid]);
            $db->commit();
            $msg = 'Transferência concluída — estoque atualizado!';
        } catch (Exception $e) {
            $db->rollBack();
            $err = 'Erro ao concluir transferência.';
        }
        goto fim;
    }

    fim:
}

// ---- LISTAGEM ----
$where = $loja_filtro
    ? "WHERE (s.loja_solicitante = $loja_filtro OR s.loja_cedente = $loja_filtro)"
    : '';

$solics = $db->query("
    SELECT s.*,
           ls.nome AS loja_sol_nome,
           lc.nome AS loja_ced_nome,
           u.nome  AS func_nome
    FROM solicitacoes s
    JOIN lojas ls   ON ls.id = s.loja_solicitante
    LEFT JOIN lojas lc ON lc.id = s.loja_cedente
    JOIN usuarios u ON u.id  = s.funcionario_id
    $where
    ORDER BY
        FIELD(s.status,'pendente','aprovado_origem','aprovado','rejeitado'),
        s.criado_em DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoStock — Solicitações</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Fira+Code:wght@400;500&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/motostock/assets/style.css">
    <link rel="stylesheet" href="/motostock/assets/admin_extra.css">
    <style>
        .msg-ok  { background:var(--ok-bg); border:1px solid var(--ok); color:var(--ok); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .msg-err { background:var(--danger-bg); border:1px solid var(--danger); color:var(--danger); padding:10px 16px; border-radius:6px; margin-bottom:16px; font-size:.85rem; }
        .status-pill { display:inline-block; padding:2px 10px; border-radius:99px; font-size:.72rem; font-weight:700; }
        .status-pendente        { background:#1c1502; color:var(--warn); }
        .status-aprovado_origem { background:#0c1a2e; color:#60a5fa; }
        .status-aprovado        { background:#052e16; color:var(--ok); }
        .status-rejeitado       { background:var(--danger-bg); color:var(--danger); }
        .btn-sm { padding:4px 12px; border-radius:5px; border:none; cursor:pointer; font-size:.78rem; font-weight:600; font-family:'Rajdhani',sans-serif; letter-spacing:.03em; }
        .btn-ok  { background:var(--ok); color:#000; }
        .btn-rej { background:var(--danger); color:#fff; }
        .btn-info { background:var(--surface3); color:var(--text2); border:1px solid var(--border2); }
        .modal-bg { display:none; position:fixed; inset:0; background:#000a; z-index:200; align-items:center; justify-content:center; }
        .modal-bg.open { display:flex; }
        .modal { background:var(--surface2); border:1px solid var(--border); border-radius:12px; padding:28px; width:400px; }
        .modal h3 { font-family:'Rajdhani',sans-serif; color:var(--accent); margin-bottom:14px; }
        .modal textarea { width:100%; background:var(--surface3); border:1px solid var(--border2); color:var(--text); padding:9px 12px; border-radius:6px; font-size:.85rem; outline:none; resize:vertical; min-height:70px; margin-bottom:12px; }
        .modal-btns { display:flex; gap:8px; }
        .cancel-link { font-size:.8rem; color:var(--text3); cursor:pointer; margin-top:10px; display:block; text-align:center; }
        .cancel-link:hover { color:var(--text); }
    </style>
</head>
<body>
<?php $admin_page = 'solicitacoes'; include __DIR__ . '/../includes/admin_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-title"><h1>Solicitações</h1></div>
        <div class="topbar-meta">MotoStock · <?= date('d/m/Y') ?></div>
    </header>
    <div class="content">

        <?php if ($msg): ?><div class="msg-ok">✓ <?= $msg ?></div><?php endif; ?>
        <?php if ($err): ?><div class="msg-err">✗ <?= $err ?></div><?php endif; ?>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Solicitações dos Funcionários</span>
                <span class="panel-sub"><?= count($solics) ?> registro(s)</span>
            </div>
            <div class="panel-body" style="padding:0;overflow-x:auto">
                <?php if (empty($solics)): ?>
                <p style="padding:20px;color:var(--text3)">Nenhuma solicitação no momento.</p>
                <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Funcionário</th>
                            <th>Tipo</th>
                            <th>Produto</th>
                            <th class="num">Qtd</th>
                            <th>Loja Solicitante</th>
                            <th>Loja Cedente</th>
                            <th>Status</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($solics as $s):
                        $labels = [
                            'pendente'        => 'Pendente',
                            'aprovado_origem' => 'Ag. cedente',
                            'aprovado'        => 'Aprovado',
                            'rejeitado'       => 'Rejeitado',
                        ];
                        // Determina quais botões mostrar
                        $pode_aprovar_orig   = $s['status'] === 'pendente';
                        $pode_aprovar_ced    = $s['status'] === 'aprovado_origem' && $s['tipo'] === 'transferencia'
                                              && (isAdmin() || ($loja_filtro && $loja_filtro == $s['loja_cedente']));
                        $pode_rejeitar       = in_array($s['status'], ['pendente','aprovado_origem']);
                    ?>
                    <tr>
                        <td class="mono" style="color:var(--text3)"><?= date('d/m/Y', strtotime($s['criado_em'])) ?></td>
                        <td><?= htmlspecialchars($s['func_nome']) ?></td>
                        <td><?= $s['tipo'] === 'reposicao' ? '📦 Reposição' : '⇄ Transferência' ?></td>
                        <td style="font-weight:500"><?= htmlspecialchars($s['produto_nome']) ?></td>
                        <td class="num mono"><?= $s['quantidade'] ?></td>
                        <td><?= htmlspecialchars($s['loja_sol_nome']) ?></td>
                        <td><?= $s['loja_ced_nome'] ? htmlspecialchars($s['loja_ced_nome']) : '—' ?></td>
                        <td><span class="status-pill status-<?= $s['status'] ?>"><?= $labels[$s['status']] ?></span></td>
                        <td style="white-space:nowrap">
                            <?php if ($pode_aprovar_orig && !$pode_aprovar_ced): ?>
                                <?php $label_btn = $s['tipo'] === 'transferencia' && !isAdmin() ? 'Aprovar origem' : 'Aprovar'; ?>
                                <button class="btn-sm btn-ok" onclick="abrirModal(<?= $s['id'] ?>,'<?= $s['tipo'] === 'transferencia' && !isAdmin() ? 'aprovar_origem' : 'aprovar' ?>')">
                                    <?= $label_btn ?>
                                </button>
                            <?php endif; ?>
                            <?php if ($pode_aprovar_ced): ?>
                                <button class="btn-sm btn-ok" onclick="abrirModal(<?= $s['id'] ?>,'aprovar_cedente')">
                                    Aprovar cedente
                                </button>
                            <?php endif; ?>
                            <?php if ($pode_rejeitar): ?>
                                <button class="btn-sm btn-rej" style="margin-left:4px" onclick="abrirModal(<?= $s['id'] ?>,'rejeitar')">
                                    Rejeitar
                                </button>
                            <?php endif; ?>
                            <?php if (!$pode_aprovar_orig && !$pode_aprovar_ced && !$pode_rejeitar): ?>
                                <span style="color:var(--text3);font-size:.78rem">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Modal de confirmação -->
<div class="modal-bg" id="modal">
    <div class="modal">
        <h3 id="modal-titulo">Confirmar Ação</h3>
        <form method="post">
            <input type="hidden" name="solic_id" id="modal-id">
            <input type="hidden" name="acao"     id="modal-acao">
            <textarea name="obs" placeholder="Observação (opcional)..."></textarea>
            <div class="modal-btns">
                <button type="submit" class="btn-sm btn-ok" style="padding:8px 20px">Confirmar</button>
            </div>
        </form>
        <span class="cancel-link" onclick="fecharModal()">Cancelar</span>
    </div>
</div>

<script>
function abrirModal(id, acao) {
    document.getElementById('modal-id').value   = id;
    document.getElementById('modal-acao').value = acao;
    const titulos = {
        aprovar:          'Aprovar Solicitação',
        aprovar_origem:   'Aprovar — Lado Origem',
        aprovar_cedente:  'Aprovar — Lado Cedente',
        rejeitar:         'Rejeitar Solicitação',
    };
    document.getElementById('modal-titulo').textContent = titulos[acao] || 'Confirmar';
    document.getElementById('modal').classList.add('open');
}
function fecharModal() { document.getElementById('modal').classList.remove('open'); }
document.getElementById('modal').addEventListener('click', e => { if (e.target === document.getElementById('modal')) fecharModal(); });
</script>
</body>
</html>
