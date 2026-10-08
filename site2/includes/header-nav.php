<?php
/**
 * header-nav.php — Единый источник навигации ТЧП
 *
 * Переменная $current_page устанавливается в каждом шаблоне:
 *   'index' | 'start' | 'materialy' | 'praktika' | 'oshibki' | 'interactive' | 'category'
 */
$current_page = $current_page ?? '';
$site_root = function_exists('home_url') ? home_url('/') : '/';

$praktika_link = function_exists('get_category_by_slug') && ($cat = get_category_by_slug('praktika')) 
    ? get_category_link($cat->term_id) 
    : ($site_root . 'category.php?slug=praktika');

$start_link = function_exists('get_category_by_slug') && ($cat = get_category_by_slug('start')) 
    ? get_category_link($cat->term_id) 
    : ($site_root . 'category.php?slug=start');

$nav_items = [
    ['href' => $site_root . '#articles',  'label' => 'Статьи',       'page' => 'articles'],
    ['href' => $site_root . 'interactive.php', 'label' => 'Калькуляторы', 'page' => 'interactive'],
    ['href' => $praktika_link,            'label' => 'Практика',     'page' => 'praktika'],
    ['href' => $start_link,               'label' => 'С чего начать', 'page' => 'start'],
];

if (!function_exists('is_tchp_nav_active')) {
    function is_tchp_nav_active($item_page, $current_page) {
        if ($current_page === $item_page) return true;
        if (($current_page === 'category' || empty($current_page)) && isset($_GET['slug']) && $_GET['slug'] === $item_page) return true;
        if (function_exists('is_category') && is_category($item_page)) return true;
        return false;
    }
}
?>
<nav class="nav flex items-center gap-1 sm:gap-2 overflow-x-auto text-sm font-mono whitespace-nowrap scrollbar-none py-0.5" aria-label="Основная навигация">
    <?php foreach ($nav_items as $item): 
        $active = is_tchp_nav_active($item['page'], $current_page);
    ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="nav-link inline-flex items-center min-h-[44px] px-2.5 sm:px-3 rounded transition-colors text-xs sm:text-sm <?= $active ? 'active text-accent font-bold border-b-2 border-accent bg-accent/5' : 'text-ink-muted hover:text-ink hover:bg-paper-border/30' ?>"
           <?php if ($active): ?>aria-current="page"<?php endif; ?>>
            <?= htmlspecialchars($item['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
