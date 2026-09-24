<?php
/**
 * set-cookie.php — Установка cookie согласия
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$consent = $_POST['consent'] ?? '';

$allowed = [
    'accepted',
    'declined',
    'necessary_only'
];

if (!in_array($consent, $allowed, true)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid consent value'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

setcookie('tp_cookie_consent', $consent, [
    'expires'  => time() + 365 * 86400,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => false,
    'samesite' => 'Lax'
]);

echo json_encode([
    'status'  => 'ok',
    'message' => 'Cookie consent recorded',
    'consent' => $consent
], JSON_UNESCAPED_UNICODE);
