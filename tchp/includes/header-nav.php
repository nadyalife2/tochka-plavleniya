<?php
/**
 * header-nav.php — Единый источник навигации ТЧП
 *
 * Переменная $current_page устанавливается в каждом шаблоне:
 *   'index' | 'start' | 'materialy' | 'praktika' | 'oshibki' | 'interactive' | 'category'
 */
$current_page = $current_page ?? '';

$nav_items = [
    ['href' => '/',                                  'label' => 'Главная',      'page' => 'index'],
    ['href' => '/category.php?slug=start',           'label' => 'Начать паять', 'page' => 'start'],
    ['href' => '/category.php?slug=materialy',       'label' => 'Материалы',    'page' => 'materialy'],
    ['href' => '/category.php?slug=praktika',        'label' => 'Практика',     'page' => 'praktika'],
    ['href' => '/category.php?slug=oshibki',         'label' => 'Проблемы',     'page' => 'oshibki'],
    ['href' => '/interactive.php',                   'label' => 'Калькуляторы', 'page' => 'interactive'],
];

if (!function_exists('is_tchp_nav_active')) {
    function is_tchp_nav_active($item_page, $current_page) {
        if ($current_page === $item_page) return true;
        if (($current_page === 'category' || empty($current_page)) && isset($_GET['slug']) && $_GET['slug'] === $item_page) return true;
        return false;
    }
}
?>
<nav class="nav flex items-center gap-3.5 sm:gap-5 overflow-x-auto py-1 text-[13px] sm:text-[13.5px] font-mono whitespace-nowrap scrollbar-none" aria-label="Основная навигация">
    <?php foreach ($nav_items as $item): 
        $active = is_tchp_nav_active($item['page'], $current_page);
    ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="nav-link <?= $active ? 'active text-accent font-bold border-b-2 border-accent' : 'text-ink-muted hover:text-ink transition-colors' ?>"
           <?php if ($active): ?>aria-current="page"<?php endif; ?>>
            <?= htmlspecialchars($item['label']) ?>
        </a>
    <?php endforeach; ?>
</nav>
