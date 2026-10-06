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
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_editor_style('assets/css/article.css');

    register_nav_menus([
        'primary' => __('Основное меню', 'site2'),
        'footer'  => __('Меню в подвале', 'site2'),
    ]);
});

// 3. Register Block Styles & Block Patterns (Gutenberg)
add_action('init', function () {
    // 3.1. Block Styles for Images
    if (function_exists('register_block_style')) {
        register_block_style('core/image', [
            'name'  => 'blueprint',
            'label' => __('Инженерный чертёж', 'site2'),
        ]);
        register_block_style('core/image', [
            'name'  => 'polaroid',
            'label' => __('Лабораторный полароид', 'site2'),
        ]);
        register_block_style('core/image', [
            'name'  => 'washi',
            'label' => __('Со скотчем (Washi)', 'site2'),
        ]);
    }

    // 3.2. Block Patterns Category
    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('tochka-plavleniya', [
            'label' => __('ТОЧКА ПЛАВЛЕНИЯ', 'site2'),
        ]);
    }

    // 3.3. Block Patterns
    if (function_exists('register_block_pattern')) {
        // Pattern 1: TL;DR Sticky Note
        register_block_pattern('tochka-plavleniya/tldr-sticker', [
            'title'       => __('Врезка: Главный принцип (TL;DR)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Крафтовый жёлтый стикер с ключевой мыслью регламента', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout tldr"} -->
<div class="wp-block-group callout tldr"><p style="font-family:monospace;font-size:0.75rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--color-ink);margin-bottom:0.4rem">📌 ГЛАВНЫЙ ИНЖЕНЕРНЫЙ ПРИНЦИП // 10 СЕКУНД</p><p style="font-weight:600;line-height:1.6;margin:0">Ключевая мысль: Нагрев должен быть равномерным с двух сторон. Нижний подогрев 140–160°C обязателен для предотвращения коробления текстолита.</p></div>
<!-- /wp:group -->',
        ]);

        // Pattern 2: Safety First Callout
        register_block_pattern('tochka-plavleniya/safety-warning', [
            'title'       => __('Предупреждение: Техника безопасности (Safety First)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Оранжевый блок предостережения от термического удара и повреждения компонентов', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout-safety"} -->
<div class="wp-block-group callout-safety"><p style="margin:0">⚠️ <strong>ВНИМАНИЕ / ТЕРМОУДАР:</strong> Скорость подъема температуры не должна превышать <strong>2–3 °C в секунду</strong>. Превышение скорости приводит к растрескиванию кремниевого кристалла и расслоению BGA-подложки.</p></div>
<!-- /wp:group -->',
        ]);

        // Pattern 3: Technical BOM Table
        register_block_pattern('tochka-plavleniya/bom-table', [
            'title'       => __('Таблица: Спецификация и оснастка (BOM-table)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Инженерная спецификация инструментов, сплавов и допусков', 'site2'),
            'content'     => '<!-- wp:table {"className":"bom-table"} -->
<figure class="wp-block-table bom-table"><table class="has-fixed-layout"><thead><tr><th>Параметр / Инструмент</th><th>Технический регламент</th><th>Допуск / Спецификация</th></tr></thead><tbody><tr><td>Термофен (сопло 10 мм)</td><td>330–350 °C на индикаторе</td><td>±5 °C на выходе</td></tr><tr><td>Нижний подогрев (ИК/плита)</td><td>140–160 °C на плате</td><td>Контроль термопарой K-типа</td></tr><tr><td>Флюс для пайки</td><td>ROL0 / RMA безотмывочный</td><td>Вязкость 35–45 Па·с</td></tr><tr><td>Время расплава (TAL)</td><td>45–60 секунд</td><td>Не более 75 секунд</td></tr></tbody></table></figure>
<!-- /wp:table -->',
        ]);

        // Pattern 4: Step-by-Step Procedure
        register_block_pattern('tochka-plavleniya/step-procedure', [
            'title'       => __('Пошаговый регламент операции (Шаги 1–4)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Структурированный пошаговый процесс монтажа с бейджами шагов', 'site2'),
            'content'     => '<!-- wp:group {"className":"space-y-4 my-6"} -->
<div class="wp-block-group space-y-4 my-6"><!-- wp:group {"className":"p-5 rounded-lg border border-paper-border bg-paper space-y-2"} -->
<div class="wp-block-group p-5 rounded-lg border border-paper-border bg-paper space-y-2"><div style="display:flex;align-items:center;gap:0.5rem"><span class="pill-tag-yellow" style="font-family:monospace;font-size:0.75rem">ШАГ 01</span><h4 style="margin:0;font-size:1.1rem;font-weight:700">Подготовка и преднагрев платы</h4></div><p style="margin:0.25rem 0 0 0;font-size:0.95rem;line-height:1.6">Установите плату на фиксатор термостола. Разогрейте плату снизу до 140–150°C для равномерного распределения тепла.</p></div>
<!-- /wp:group --><!-- wp:group {"className":"p-5 rounded-lg border border-paper-border bg-paper space-y-2"} -->
<div class="wp-block-group p-5 rounded-lg border border-paper-border bg-paper space-y-2"><div style="display:flex;align-items:center;gap:0.5rem"><span class="pill-tag-orange" style="font-family:monospace;font-size:0.75rem">ШАГ 02</span><h4 style="margin:0;font-size:1.1rem;font-weight:700">Нанесение флюса и оплавление</h4></div><p style="margin:0.25rem 0 0 0;font-size:0.95rem;line-height:1.6">Нанесите тонкий равномерный слой вязкого гелевого флюса под чип. Направьте поток верхнего фена круговыми движениями на высоте 15–20 мм.</p></div>
<!-- /wp:group --><!-- wp:group {"className":"p-5 rounded-lg border border-paper-border bg-paper space-y-2"} -->
<div class="wp-block-group p-5 rounded-lg border border-paper-border bg-paper space-y-2"><div style="display:flex;align-items:center;gap:0.5rem"><span class="pill-tag-mint" style="font-family:monospace;font-size:0.75rem">ШАГ 03</span><h4 style="margin:0;font-size:1.1rem;font-weight:700">Контролируемое охлаждение и отмывка</h4></div><p style="margin:0.25rem 0 0 0;font-size:0.95rem;line-height:1.6">Не перемещайте плату до затвердевания припоя (ниже 180°C). После остывания промойте контакты изопропиловым спиртом высокой очистки.</p></div>
<!-- /wp:group --></div>
<!-- /wp:group -->',
        ]);
    }
});

// 4. Fail-safe: Prevent root URL from ever showing 404 in WordPress
add_action('template_redirect', function () {
    if (is_404() && ($_SERVER['REQUEST_URI'] === '/' || empty(trim($_SERVER['REQUEST_URI'], '/')))) {
        status_header(200);
        require __DIR__ . '/index.php';
        exit;
    }
});

// 5. Disable WordPress comments (Zero-PII / 152-ФЗ compliance)
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);
