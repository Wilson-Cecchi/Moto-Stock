<?php
// ============================================================
// config.php — Configuração do Banco de Dados
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // usuário padrão do XAMPP
define('DB_PASS', '');          // senha padrão do XAMPP (vazia)
define('DB_NAME', 'motostock');

// Sessão iniciada uma única vez aqui
if (session_status() === PHP_SESSION_NONE) {
    date_default_timezone_set('America/Sao_Paulo');
    session_start();
}

// --- Auth helpers ---

function requireAuth(): void {
    if (empty($_SESSION['usuario'])) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: /motostock/login.php?next=$next");
        exit;
    }
}

function getCurrentUser(): array {
    return $_SESSION['usuario'] ?? [];
}

function isAdmin(): bool {
    return (getCurrentUser()['nivel_acesso'] ?? '') === 'admin';
}

function isGerente(): bool {
    return (getCurrentUser()['nivel_acesso'] ?? '') === 'gerente';
}

function isFuncionario(): bool {
    return (getCurrentUser()['nivel_acesso'] ?? '') === 'funcionario';
}

/** Retorna loja_id obrigatório para gerentes e funcionários; 0 = admin (sem filtro). */
function getLojaFiltro(): int {
    $u = getCurrentUser();
    $nivel = $u['nivel_acesso'] ?? '';
    if ($nivel === 'gerente' || $nivel === 'funcionario') {
        return (int)($u['loja_id'] ?? 0);
    }
    return 0;
}

/** Bloqueia funcionários de acessar páginas de gerente/admin. */
function requireGerente(): void {
    requireAuth();
    $nivel = getCurrentUser()['nivel_acesso'] ?? '';
    if ($nivel === 'funcionario') {
        header('Location: /motostock/funcionario.php');
        exit;
    }
}

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;color:#ef4444;padding:2rem;">
                <b>Erro de conexão com o banco:</b><br>' . $e->getMessage() . '<br><br>
                Verifique se o XAMPP está rodando e execute o arquivo <b>setup.sql</b> no phpMyAdmin.
            </div>');
        }
    }
    return $pdo;
}

// Formatação de moeda BR
function brl(float $v): string {
    return 'R$ ' . number_format($v, 2, ',', '.');
}

// Cor de alerta de estoque
function stockColor(int $estoque, int $minimo): string {
    if ($estoque <= 0) return 'danger';
    if ($estoque <= $minimo) return 'warning';
    if ($estoque <= $minimo * 1.5) return 'caution';
    return 'ok';
}

$CATEGORIAS = ['Proteção','Vestuário','Manutenção','Acessório','Segurança'];
$MESES_PT = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
