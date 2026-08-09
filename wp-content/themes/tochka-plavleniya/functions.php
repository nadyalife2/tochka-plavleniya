<?php
/**
 * Tochka Plavleniya Theme Functions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function tochka_plavleniya_setup() {
    // Add default posts and comments RSS feed links to head.
    add_theme_support('automatic-feed-links');

    // Let WordPress manage the document title.
    add_theme_support('title-tag');

    // Enable support for Post Thumbnails on posts and pages.
    add_theme_support('post-thumbnails');

    // Register navigation menus.
    register_nav_menus(array(
        'primary' => __('Главное меню (Шапка)', 'tochka-plavleniya'),
        'footer'  => __('Футер меню', 'tochka-plavleniya'),
    ));

    // Switch default core markup for search form, comment form, etc. to HTML5.
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
}
add_action('after_setup_theme', 'tochka_plavleniya_setup');

/**
 * Enqueue scripts and styles.
 */
function tochka_plavleniya_scripts() {
    // Google Fonts: Hanken Grotesk & IBM Plex Mono
    wp_enqueue_style('tochka-fonts', 'https://fonts.googleapis.com/css2?family=Hanken+Grotesk:ital,wght@0,400..900;1,400..900&family=IBM+Plex+Mono:wght@400;500;600;700&display=swap', array(), null);

    // Theme main stylesheet
    wp_enqueue_style('tochka-style', get_stylesheet_uri(), array('tochka-fonts'), '1.0.0');
}
add_action('wp_enqueue_scripts', 'tochka_plavleniya_scripts');

/**
 * Estimated reading time helper.
 */
function tochka_reading_time($post_id = null) {
    $content = get_post_field('post_content', $post_id);
    $word_count = str_word_count(strip_tags($content));
    $reading_time = ceil($word_count / 200);
    return max(1, $reading_time) . ' мин';
}
