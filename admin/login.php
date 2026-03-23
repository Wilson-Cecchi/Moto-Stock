<?php
// admin/login.php — redireciona para o login unificado
require_once __DIR__ . '/../config.php';
header('Location: /motostock/login.php?next=/motostock/admin/');
exit;
