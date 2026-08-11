<?php
/**
 * set-cookie.php — Установка cookie согласия
 */
if (isset($_GET['consent']) && $_GET['consent'] == '1') {
    setcookie('cookie_consent', 'accepted', [
        'expires'  => time() + 365 * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'message' => 'Cookie consent recorded']);
    exit;
}
