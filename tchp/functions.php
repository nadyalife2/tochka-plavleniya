<?php
/**
 * Root Theme Functions & Setup for ТЧП (Точка Плавления)
 *
 * @package TCHP
 * @version 2.1.0
 */

defined('ABSPATH') || exit;

// 1. Load legacy helper functions if available
if (file_exists(get_template_directory() . '/includes/functions.php')) {
    require_once get_template_directory() . '/includes/functions.php';
}

// 2. Theme Setup
add_action('after_setup_theme', function () {
    // Let WordPress manage the document title
    add_theme_support('title-tag');

    // Enable Featured Images
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(800, 450, true);

    // HTML5 semantic markup support
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

    // Responsive embed blocks
    add_theme_support('responsive-embeds');

    // Navigation Menus
    register_nav_menus([
        'primary' => __('Основное меню', 'tchp'),
        'footer'  => __('Меню в подвале', 'tchp'),
    ]);
});

// 3. Enqueue Styles & Scripts (Standard WordPress Queue)
add_action('wp_enqueue_scripts', function () {
    $theme_ver = wp_get_theme()->get('Version') ?: '2.1.0';

    // 3.1. Design Tokens (Single Source of Truth)
    if (file_exists(get_template_directory() . '/assets/css/tokens.css')) {
        wp_enqueue_style(
            'tchp-tokens',
            get_template_directory_uri() . '/assets/css/tokens.css',
            [],
            $theme_ver
        );
    }

    // 3.2. Self-hosted fonts (IBM Plex Sans, JetBrains Mono, Newsreader, Caveat)
    if (file_exists(get_template_directory() . '/assets/css/fonts.css')) {
        wp_enqueue_style(
            'tchp-fonts',
            get_template_directory_uri() . '/assets/css/fonts.css',
            ['tchp-tokens'],
            $theme_ver
        );
    }

    // 3.3. Base UI Components & Resets
    if (file_exists(get_template_directory() . '/assets/css/base.css')) {
        wp_enqueue_style(
            'tchp-base',
            get_template_directory_uri() . '/assets/css/base.css',
            ['tchp-tokens', 'tchp-fonts'],
            $theme_ver
        );
    }

    // 3.4. Compiled Tailwind CSS production bundle
    if (file_exists(get_template_directory() . '/assets/css/build.css')) {
        wp_enqueue_style(
            'tchp-build',
            get_template_directory_uri() . '/assets/css/build.css',
            ['tchp-base'],
            $theme_ver
        );
    }

    // 3.5. Workbench & Interactive Styles
    if (file_exists(get_template_directory() . '/assets/css/workbench.css')) {
        wp_enqueue_style(
            'tchp-workbench',
            get_template_directory_uri() . '/assets/css/workbench.css',
            ['tchp-build'],
            $theme_ver
        );
    }

    if (file_exists(get_template_directory() . '/assets/css/interactive.css')) {
        wp_enqueue_style(
            'tchp-interactive',
            get_template_directory_uri() . '/assets/css/interactive.css',
            ['tchp-build'],
            $theme_ver
        );
    }

    // 3.6. Common / Legacy Styles if present
    if (file_exists(get_template_directory() . '/assets/css/common.css')) {
        wp_enqueue_style(
            'tchp-common',
            get_template_directory_uri() . '/assets/css/common.css',
            ['tchp-build'],
            $theme_ver
        );
    }

    // 3.7. Google Fonts (Space Grotesk + Material Symbols Outlined)
    wp_enqueue_style(
        'tchp-google-fonts',
        'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap',
        [],
        null
    );

    // 3.8. Primary Theme Stylesheet (style.css)
    wp_enqueue_style(
        'tchp-theme',
        get_stylesheet_uri(),
        ['tchp-build'],
        $theme_ver
    );

    // 3.9. Scripts
    if (file_exists(get_template_directory() . '/assets/js/ui-helpers.js')) {
        wp_enqueue_script(
            'tchp-ui-helpers',
            get_template_directory_uri() . '/assets/js/ui-helpers.js',
            [],
            $theme_ver,
            true
        );
    }

    if (file_exists(get_template_directory() . '/assets/js/main.js')) {
        wp_enqueue_script(
            'tchp-main',
            get_template_directory_uri() . '/assets/js/main.js',
            ['tchp-ui-helpers'],
            $theme_ver,
            true
        );
    }

    // Workbench scripts
    if (is_page('interactive') || is_page_template('page-interactive.php') || is_front_page()) {
        $deps = ['tchp-main'];
        if (file_exists(get_template_directory() . '/assets/js/flux-calc.js')) {
            wp_enqueue_script('tchp-flux-calc', get_template_directory_uri() . '/assets/js/flux-calc.js', ['tchp-main'], $theme_ver, true);
            $deps[] = 'tchp-flux-calc';
        }
        if (file_exists(get_template_directory() . '/assets/js/thermal-slider.js')) {
            wp_enqueue_script('tchp-thermal-slider', get_template_directory_uri() . '/assets/js/thermal-slider.js', ['tchp-main'], $theme_ver, true);
            $deps[] = 'tchp-thermal-slider';
        }
        if (file_exists(get_template_directory() . '/assets/js/solder-quiz.js')) {
            wp_enqueue_script('tchp-solder-quiz', get_template_directory_uri() . '/assets/js/solder-quiz.js', ['tchp-main'], $theme_ver, true);
            $deps[] = 'tchp-solder-quiz';
        }
        if (file_exists(get_template_directory() . '/assets/js/solder-table.js')) {
            wp_enqueue_script('tchp-solder-table', get_template_directory_uri() . '/assets/js/solder-table.js', ['tchp-main'], $theme_ver, true);
            $deps[] = 'tchp-solder-table';
        }
        if (file_exists(get_template_directory() . '/assets/js/workbench.js')) {
            wp_enqueue_script('tchp-workbench', get_template_directory_uri() . '/assets/js/workbench.js', $deps, $theme_ver, true);
        }
    }

    // Single post reading UX
    if (is_singular() && file_exists(get_template_directory() . '/assets/js/article-ux.js')) {
        wp_enqueue_script(
            'tchp-article-ux',
            get_template_directory_uri() . '/assets/js/article-ux.js',
            ['tchp-main'],
            $theme_ver,
            true
        );
    }
});

// 4. 152-ФЗ / Zero-PII: Complete Disabling of Comments & Personal Data Collection
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);

add_action('admin_init', function () {
    // Remove comment support from all post types
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
    // Block direct access to edit-comments.php
    global $pagenow;
    if ('edit-comments.php' === $pagenow) {
        wp_safe_redirect(admin_url());
        exit;
    }
});

add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

add_action('init', function () {
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
});

// Block guest user enumeration via REST API (/wp-json/wp/v2/users)
add_filter('rest_endpoints', function ($endpoints) {
    if (!is_user_logged_in()) {
        if (isset($endpoints['/wp/v2/users'])) {
            unset($endpoints['/wp/v2/users']);
        }
        if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }
    }
    return $endpoints;
});

// Disable IP address collection
add_filter('pre_comment_user_ip', '__return_empty_string');

// 5. Custom Meta Fields for Technical Articles (REST API & Editorial)
add_action('init', function () {
    $fields = [
        'tchp_reading_time' => [
            'type'         => 'integer',
            'description'  => 'Время чтения статьи в минутах',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_difficulty' => [
            'type'         => 'string',
            'description'  => 'Уровень сложности (Базовый, Средний, PRO)',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_solder_alloy' => [
            'type'         => 'string',
            'description'  => 'Основной сплав припоя (ПОС-61, SAC305 и др.)',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_standard' => [
            'type'         => 'string',
            'description'  => 'Стандарт (IPC/JEDEC J-STD-020D, ГОСТ и др.)',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_temp_range' => [
            'type'         => 'string',
            'description'  => 'Температурный диапазон пайки (°C)',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_tools_needed' => [
            'type'         => 'string',
            'description'  => 'Список инструментов и расходников',
            'single'       => true,
            'show_in_rest' => true,
        ],
        'tchp_faq_items' => [
            'type'         => 'string',
            'description'  => 'FAQ вопросы и ответы в формате JSON для Schema.org',
            'single'       => true,
            'show_in_rest' => true,
        ],
    ];

    foreach ($fields as $meta_key => $args) {
        register_post_meta('post', $meta_key, $args);
    }
});

// 6. Schema.org (TechArticle + FAQPage) and OpenGraph in wp_head
add_action('wp_head', function () {
    if (!is_single()) {
        return;
    }

    global $post;
    if (!$post) return;

    $permalink = get_permalink($post);
    $author_name = get_the_author_meta('display_name', $post->post_author) ?: 'Инженер Лаборатории ТЧП';
    $categories = get_the_category($post->ID);
    $cat_name = !empty($categories) ? $categories[0]->name : 'Пайка и Монтаж';

    // OpenGraph Tags
    echo '<meta property="og:type" content="article">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr(get_the_title($post)) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr(wp_strip_all_tags(get_the_excerpt($post))) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url($permalink) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '">' . "\n";
    if (has_post_thumbnail($post)) {
        $thumb_url = get_the_post_thumbnail_url($post, 'large');
        if ($thumb_url) {
            echo '<meta property="og:image" content="' . esc_url($thumb_url) . '">' . "\n";
        }
    }

    // JSON-LD Graph
    $graph = [
        [
            '@type' => 'TechArticle',
            '@id' => $permalink . '#article',
            'headline' => get_the_title($post),
            'description' => wp_strip_all_tags(get_the_excerpt($post)),
            'inLanguage' => 'ru',
            'datePublished' => get_the_date('Y-m-d', $post),
            'dateModified' => get_the_modified_date('Y-m-d', $post),
            'author' => [
                '@type' => 'Person',
                'name' => $author_name,
                'jobTitle' => 'Ведущий инженер-технолог пайки и монтажа РЭА',
                'worksFor' => [
                    '@type' => 'Organization',
                    'name' => 'Точка Плавления',
                    'url' => home_url('/')
                ]
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'url' => home_url('/')
            ],
            'mainEntityOfPage' => $permalink
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Главная',
                    'item' => home_url('/')
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $cat_name,
                    'item' => !empty($categories) ? get_category_link($categories[0]->term_id) : home_url('/')
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => get_the_title($post),
                    'item' => $permalink
                ]
            ]
        ]
    ];

    // FAQ Schema
    $faq_json = get_post_meta($post->ID, 'tchp_faq_items', true);
    if (!empty($faq_json)) {
        $faq_data = json_decode($faq_json, true);
        if (is_array($faq_data) && !empty($faq_data)) {
            $faq_entities = [];
            foreach ($faq_data as $q => $a) {
                $question = is_string($q) ? $q : ($a['q'] ?? '');
                $answer = is_string($a) ? $a : ($a['a'] ?? '');
                if (!empty($question) && !empty($answer)) {
                    $faq_entities[] = [
                        '@type' => 'Question',
                        'name' => $question,
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $answer
                        ]
                    ];
                }
            }
            if (!empty($faq_entities)) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    'mainEntity' => $faq_entities
                ];
            }
        }
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => $graph
    ];

    echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n</script>\n";
}, 10);

// 7. Affiliate Link Compliance: add rel="sponsored nofollow" and ad labeling (347-ФЗ)
add_filter('the_content', function ($content) {
    if (empty($content)) return $content;

    // Automatically ensure outbound affiliate / shop links have rel="sponsored nofollow"
    $patterns = ['aliexpress.com', 'ya.cc', 'market.yandex.ru', 'ozon.ru', 'megamarket.ru'];
    foreach ($patterns as $domain) {
        $content = preg_replace_callback(
            '/<a\s+([^>]*href=["\'][^"\']*' . preg_quote($domain, '/') . '[^"\']*["\'][^>]*)>/i',
            function ($matches) {
                $tag = $matches[0];
                if (strpos($tag, 'rel=') === false) {
                    return str_replace('<a ', '<a rel="sponsored nofollow" ', $tag);
                } else {
                    return preg_replace('/rel=["\']([^"\']*)["\']/i', 'rel="$1 sponsored nofollow"', $tag);
                }
            },
            $content
        );
    }
    return $content;
});

// 8. Fallback Articles Data Loader (for demo / fresh install preview)
if (!function_exists('tchp_get_fallback_articles')) {
    function tchp_get_fallback_articles() {
        static $data = null;
        if ($data === null) {
            $file = get_template_directory() . '/includes/articles-data.php';
            if (file_exists($file)) {
                require $file;
                $data = $articles ?? [];
            } else {
                $data = [];
            }
        }
        return $data;
    }
}

// 9. Excerpt Length & Suffix
add_filter('excerpt_length', function ($length) {
    return 28;
}, 999);

add_filter('excerpt_more', function ($more) {
    return '...';
});
