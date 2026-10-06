<?php
/**
 * Theme Setup & Functions for ТОЧКА ПЛАВЛЕНИЯ (site2)
 *
 * @package Site2
 * @version 2.1.0
 */

defined('ABSPATH') || exit;

// 1. Load helper functions if available
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
}

// 2. Theme Setup
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(800, 450, true);
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets'
    ]);
    add_theme_support('responsive-embeds');

    register_nav_menus([
        'primary' => __('Основное меню', 'site2'),
        'footer'  => __('Меню в подвале', 'site2'),
    ]);
});

// 3. Fail-safe: Prevent root URL from ever showing 404 in WordPress
add_action('template_redirect', function () {
    if (is_404() && ($_SERVER['REQUEST_URI'] === '/' || empty(trim($_SERVER['REQUEST_URI'], '/')))) {
        status_header(200);
        require __DIR__ . '/index.php';
        exit;
    }
});

// 4. Disable WordPress comments (Zero-PII / 152-ФЗ compliance)
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);
