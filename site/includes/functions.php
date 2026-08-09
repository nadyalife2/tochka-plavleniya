<?php
/**
 * functions.php — Вспомогательные функции
 * Точка Плавления
 */

/**
 * Найти статью по slug
 */
function get_article_by_slug(array $articles, string $slug): ?array {
    foreach ($articles as $a) {
        if ($a['slug'] === $slug) return $a;
    }
    return null;
}

/**
 * Получить статьи по tag_key
 */
function get_articles_by_tag(array $articles, string $tag_key): array {
    if ($tag_key === 'all') return $articles;
    return array_values(array_filter($articles, fn($a) => $a['tag_key'] === $tag_key));
}

/**
 * Получить featured статьи
 */
function get_featured_articles(array $articles): array {
    return array_values(array_filter($articles, fn($a) => $a['featured'] === true));
}

/**
 * Пагинация
 */
function paginate(array $items, int $per_page = 9, int $page = 1): array {
    $total = count($items);
    $total_pages = (int) ceil($total / $per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;
    return [
        'items'       => array_slice($items, $offset, $per_page),
        'total'       => $total,
        'total_pages' => $total_pages,
        'current'     => $page,
    ];
}

/**
 * Форматировать дату по-русски
 */
function format_date_ru(string $date): string {
    $months = [
        '01' => 'января', '02' => 'февраля', '03' => 'марта',
        '04' => 'апреля', '05' => 'мая', '06' => 'июня',
        '07' => 'июля', '08' => 'августа', '09' => 'сентября',
        '10' => 'октября', '11' => 'ноября', '12' => 'декабря',
    ];
    $parts = explode('-', $date);
    return $parts[2] . ' ' . ($months[$parts[1]] ?? '') . ' ' . $parts[0];
}

/**
 * Безопасный вывод строки
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * SVG-иконки для карточек
 */
function get_card_icon(string $icon): string {
    $icons = [
        'thermometer' => '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 2v14M12 16a4 4 0 1 1-4 4h8a4 4 0 0 1-4-4z"/></svg>',
        'tools'       => '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>',
        'chip'        => '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>',
        'drop'        => '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M12 2C12 2 5 9.5 5 14a7 7 0 0 0 14 0c0-4.5-7-12-7-12z"/></svg>',
        'board'       => '<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="7,9 11,9 11,13 15,13"/><circle cx="7" cy="9" r="1" fill="currentColor"/><circle cx="17" cy="15" r="1" fill="currentColor"/></svg>',
    ];
    return $icons[$icon] ?? $icons['chip'];
}

/**
 * Получить уникальные tag_key из массива статей
 */
function get_all_tags(array $articles): array {
    $tags = [];
    foreach ($articles as $a) {
        $tags[$a['tag_key']] = $a['tag'];
    }
    return $tags;
}

/**
 * Пагинация HTML
 */
function render_pagination(array $paginated, string $base_url): string {
    if ($paginated['total_pages'] <= 1) return '';
    $html = '<nav class="pagination">';
    for ($i = 1; $i <= $paginated['total_pages']; $i++) {
        $active = $i === $paginated['current'] ? ' active' : '';
        $html .= "<a href=\"{$base_url}&page={$i}\" class=\"page-btn{$active}\">{$i}</a>";
    }
    $html .= '</nav>';
    return $html;
}
