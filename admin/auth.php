<?php
// admin/auth.php — Proteção da área administrativa
// config.php (incluso antes) já iniciou a sessão

function requireLogin(): void {
    if (empty($_SESSION['usuario']) || $_SESSION['usuario']['nivel_acesso'] !== 'admin') {
        header('Location: /motostock/login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/motostock/admin/'));
        exit;
    }
}

function isLogged(): bool {
    return !empty($_SESSION['usuario']) && $_SESSION['usuario']['nivel_acesso'] === 'admin';
}