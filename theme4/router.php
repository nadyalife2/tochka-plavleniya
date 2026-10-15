<?php
/**
 * router.php — Маршрутизатор для встроенного PHP-сервера (php -S)
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 0. GEO и LLM файлы спецификации (robots.txt, llms.txt, llms-full.txt)
if ($uri === '/llms.txt' || $uri === '/llms-full.txt' || $uri === '/robots.txt') {
    $target = __DIR__ . $uri;
    if (file_exists($target)) {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: all');
        readfile($target);
        exit;
    }
}

// 1. Если запрашивается реальный статический файл (css, js, svg, png, jpg, webp, woff2 и т.д.)
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false; // Отдаем файл встроенным веб-сервером напрямую
}

// 2. Прямые PHP-скрипты
if ($uri === '/' || $uri === '/index.php') {
    require __DIR__ . '/index.php';
    exit;
}

if ($uri === '/category.php') {
    require __DIR__ . '/category.php';
    exit;
}

if ($uri === '/interactive.php') {
    require __DIR__ . '/interactive.php';
    exit;
}

if ($uri === '/article.php' || $uri === '/single.php') {
    require __DIR__ . '/article.php';
    exit;
}

if ($uri === '/cookies.php' || $uri === '/privacy.php' || $uri === '/terms.php' || $uri === '/set-cookie.php' || $uri === '/404.php') {
    require __DIR__ . $uri;
    exit;
}

// 3. Красивые URL рубрик: /category/{slug}/
if (preg_match('#^/category/([a-zA-Z0-9_\-%]+)/?$#', $uri, $matches)) {
    $_GET['slug'] = urldecode($matches[1]);
    require __DIR__ . '/category.php';
    exit;
}

// 4. Красивые URL статей и уроков: /{slug}/
$slug = trim($uri, '/');
if (!empty($slug)) {
    $rubric_slugs = ['start', 'materialy', 'praktika', 'oshibki', 'oborudovanie', 'archive', 'all'];
    if (in_array($slug, $rubric_slugs, true)) {
        $_GET['slug'] = ($slug === 'archive' ? 'all' : $slug);
        require __DIR__ . '/category.php';
        exit;
    }

    // Иначе это статья или микроурок
    $_GET['slug'] = $slug;
    require __DIR__ . '/article.php';
    exit;
}

// 5. Fallback на главную
require __DIR__ . '/index.php';
