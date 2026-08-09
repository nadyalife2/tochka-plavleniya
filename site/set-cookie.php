<?php
/**
 * set-cookie.php — AJAX endpoint для установки cookie-согласия
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setcookie('cookie_consent', '1', [
        'expires'  => time() + 365 * 24 * 3600,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
} else {
    http_response_code(405);
}
