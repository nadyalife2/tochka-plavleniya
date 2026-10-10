<?php
/**
 * header-nav.php — Единый источник навигации ТЧП
 *
 * Переменная $current_page устанавливается в каждом шаблоне:
 *   'index' | 'start' | 'materialy' | 'praktika' | 'oshibki' | 'interactive' | 'category'
 */
$current_page = $current_page ?? '';
$site_root = function_exists('home_url') ? home_url('/') : '/';

if (!function_exists('tchp_get_category_url')) {
    function tchp_get_category_url(array $slug_candidates, string $fallback_slug, string $category_name = '') {
        $site_root = function_exists('home_url') ? home_url('/') : '/';
        if (function_exists('get_category_by_slug')) {
            foreach ($slug_candidates as $s) {
                $cat = get_category_by_slug($s);
                if ($cat && !is_wp_error($cat)) {
                    return get_category_link($cat->term_id);
                }
            }
        }
        if (!empty($category_name) && function_exists('get_term_by')) {
            $cat = get_term_by('name', $category_name, 'category');
            if ($cat && !is_wp_error($cat)) {
                return get_category_link($cat->term_id);
            }
        }
        // In WordPress environment, canonical category rewrite is /category/{slug}/
        if (function_exists('home_url')) {
            return home_url('/category/' . $fallback_slug . '/');
        }
        return $site_root . 'category.php?slug=' . urlencode($fallback_slug);
    }
}

$start_link = tchp_get_category_url(
    ['start', 'старт-и-база', '%d1%81%d1%82%d0%b0%d1%80%d1%82-%d0%b8-%d0%b1%d0%b0%d0%b7%d0%b0', 'start-i-baza'],
    'старт-и-база',
    'Старт и база'
);

$praktika_link = tchp_get_category_url(
    ['praktika', 'практика-и-монтаж', '%d0%bf%d1%80%d0%b0%d0%ba%d1%82%d0%b8%d0%ba%d0%b0-%d0%b8-%d0%bc%d0%be%d0%bd%d1%82%d0%b0%d0%b6', 'praktika-i-montazh'],
    'praktika',
    'Практика и монтаж'
);

$nav_items = [
    ['href' => $site_root . '#articles',       'label' => 'Статьи',       'page' => 'articles'],
    ['href' => $site_root . 'interactive.php', 'label' => 'Калькуляторы', 'page' => 'interactive'],
    ['href' => $praktika_link,                 'label' => 'Практика',     'page' => 'praktika'],
    ['href' => $start_link,                    'label' => 'С чего начать', 'page' => 'start'],
];

if (!function_exists('is_tchp_nav_active')) {
    function is_tchp_nav_active($item_page, $current_page) {
        if ($current_page === $item_page) return true;
        if ($item_page === 'start' && in_array($current_page, ['start', 'старт-и-база', '%d1%81%d1%82%d0%b0%d1%80%d1%82-%d0%b8-%d0%b1%d0%b0%d0%b7%d0%b0', 'start-i-baza'], true)) return true;
        if ($item_page === 'praktika' && in_array($current_page, ['praktika', 'практика-и-монтаж'], true)) return true;
        if (($current_page === 'category' || empty($current_page)) && isset($_GET['slug'])) {
            $get_slug = $_GET['slug'];
            if ($item_page === 'start' && in_array($get_slug, ['start', 'старт-и-база', '%d1%81%d1%82%d0%b0%d1%80%d1%82-%d0%b8-%d0%b1%d0%b0%d0%b7%d0%b0', 'start-i-baza'], true)) return true;
            if ($item_page === 'praktika' && in_array($get_slug, ['praktika', 'практика-и-монтаж'], true)) return true;
        }
        if (function_exists('is_category')) {
            if ($item_page === 'start' && (is_category('start') || is_category('старт-и-база') || is_category('Старт и база') || is_category(3))) return true;
            if ($item_page === 'praktika' && (is_category('praktika') || is_category('Практика и монтаж') || is_category(10))) return true;
        }
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
