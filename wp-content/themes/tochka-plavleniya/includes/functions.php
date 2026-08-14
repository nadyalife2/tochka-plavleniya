<?php
/**
 * functions.php — Вспомогательные функции темы «Точка Плавления»
 */

if (!function_exists('e')) {
    function e($str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('render_tag')) {
    function render_tag($tag_key, $tag_name, $active_tag = '') {
        $is_active = ($active_tag === $tag_key || ($active_tag === '' && $tag_key === 'all')) ? 'active' : '';
        $href = $tag_key === 'all' ? '/' : "/?tag={$tag_key}";
        echo "<a href='{$href}' class='pill {$is_active}' data-filter='{$tag_key}'>{$tag_name}</a>";
    }
}

if (!function_exists('paginate')) {
    /**
     * Вычислить массив данных для пагинации (по 6 статей на страницу)
     */
    function paginate(array $items, int $per_page = 6, int $page = 1): array {
        $total = count($items);
        $total_pages = (int) ceil($total / $per_page);
        $page = max(1, min($page, max(1, $total_pages)));
        $offset = ($page - 1) * $per_page;
        return [
            'items'       => array_slice($items, $offset, $per_page),
            'total'       => $total,
            'total_pages' => $total_pages,
            'current'     => $page,
        ];
    }
}

if (!function_exists('render_pagination')) {
    /**
     * Отрисовка пагинации с сохранением всех GET-параметров (tag, s)
     */
    function render_pagination(array $paginated, string $base_url = '/'): string {
        if ($paginated['total_pages'] <= 1) return '';
        
        $queryParams = $_GET;
        $html = '<nav class="pagination">';
        
        if ($paginated['current'] > 1) {
            $prev = $paginated['current'] - 1;
            $queryParams['page'] = $prev;
            $link = $base_url . '?' . http_build_query($queryParams);
            $html .= "<a href=\"" . e($link) . "\" class=\"page-btn nav-btn\">← Назад</a>";
        }
        
        for ($i = 1; $i <= $paginated['total_pages']; $i++) {
            $active = ($i === $paginated['current']) ? ' active' : '';
            $queryParams['page'] = $i;
            $link = $base_url . '?' . http_build_query($queryParams);
            $html .= "<a href=\"" . e($link) . "\" class=\"page-btn{$active}\">{$i}</a>";
        }
        
        if ($paginated['current'] < $paginated['total_pages']) {
            $next = $paginated['current'] + 1;
            $queryParams['page'] = $next;
            $link = $base_url . '?' . http_build_query($queryParams);
            $html .= "<a href=\"" . e($link) . "\" class=\"page-btn nav-btn\">Вперед →</a>";
        }
        
        $html .= '</nav>';
        return $html;
    }
}

if (!function_exists('get_avatar_char')) {
    function get_avatar_char(string $name): string {
        if (empty($name)) return '👤';
        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 1);
        }
        if (preg_match('/./u', $name, $match)) {
            return $match[0];
        }
        return substr($name, 0, 1);
    }
}

if (!function_exists('get_card_icon')) {
    function get_card_icon(string $icon): string {
        $icons = [
            'thermometer' => '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 2v14M12 16a4 4 0 1 1-4 4h8a4 4 0 0 1-4-4z"/></svg>',
            'tools'       => '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>',
            'chip'        => '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="7" y="7" width="10" height="10" rx="2"/><path d="M9 2v2M15 2v2M9 20v2M15 20v2M2 9h2M2 15h2M20 9h2M20 15h2"/></svg>',
            'drop'        => '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 2C12 2 5 9.5 5 14a7 7 0 0 0 14 0c0-4.5-7-12-7-12z"/></svg>',
            'board'       => '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="7,9 11,9 11,13 15,13"/></svg>',
        ];
        return $icons[$icon] ?? $icons['chip'];
    }
}

if (!function_exists('render_geo_schema')) {
    /**
     * Генерация автоматической GEO / Schema.org микроразметки для ИИ (AI-Ready JSON-LD)
     * Совместимо как с автономным PHP, так и с шаблонами WordPress (Single/Page).
     */
    function render_geo_schema($standalone_article = null) {
        $schemas = [];
        
        $site_name = function_exists('get_bloginfo') ? get_bloginfo('name') : 'Точка Плавления';
        $site_url  = function_exists('home_url') ? home_url() : 'https://tochka-plavleniya.ru';
        
        $schemas[] = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => $site_name,
            "url" => $site_url,
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => $site_url . "/index.php?q={search_term_string}",
                "query-input" => "required name=search_term_string"
            ]
        ];

        $schemas[] = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => "Точка Плавления",
            "url" => $site_url,
            "logo" => $site_url . "/assets/images/logo.png",
            "knowsAbout" => [
                "Пайка электроники",
                "SMD и BGA монтаж",
                "Флюсы и припои",
                "Проектирование печатных плат PCB",
                "Микроконтроллеры ESP32 / STM32"
            ]
        ];

        $is_wp_single = function_exists('is_single') && is_single();
        
        if ($is_wp_single || !empty($standalone_article)) {
            if ($is_wp_single) {
                global $post;
                $title       = get_the_title();
                $excerpt     = get_the_excerpt();
                $permalink   = get_permalink();
                $date_pub    = get_the_date('c');
                $date_mod    = get_the_modified_date('c');
                $author_name = get_the_author();
                $thumb_url   = has_post_thumbnail() ? get_the_post_thumbnail_url($post->ID, 'full') : $site_url . '/assets/images/article-default.jpg';
                $tag_name    = get_the_category() ? get_the_category()[0]->name : 'Электроника';
                
                $proficiency = get_post_meta($post->ID, 'proficiency_level', true) ?: 'Beginner';
                $tech_tools  = get_post_meta($post->ID, 'required_tools', true);
            } else {
                $title       = $standalone_article['title'] ?? 'Статья ТЧП';
                $excerpt     = $standalone_article['excerpt'] ?? '';
                $permalink   = $site_url . '/article.php?slug=' . urlencode($standalone_article['slug'] ?? '');
                $date_pub    = date('c');
                $date_mod    = date('c');
                $author_name = 'Лаборатория ТЧП';
                $thumb_url   = $site_url . '/assets/images/article-default.jpg';
                $tag_name    = $standalone_article['tag'] ?? 'Инженерия';
                $proficiency = 'Beginner';
                $tech_tools  = null;
            }

            $article_schema = [
                "@context" => "https://schema.org",
                "@type" => "TechArticle",
                "headline" => $title,
                "description" => $excerpt,
                "url" => $permalink,
                "datePublished" => $date_pub,
                "dateModified" => $date_mod,
                "inLanguage" => "ru",
                "proficiencyLevel" => $proficiency,
                "image" => $thumb_url,
                "author" => [
                    "@type" => "Person",
                    "name" => $author_name
                ],
                "publisher" => [
                    "@type" => "Organization",
                    "name" => "Точка Плавления",
                    "url" => $site_url
                ],
                "about" => [
                    ["@type" => "Thing", "name" => "Технология пайки"],
                    ["@type" => "Thing", "name" => $tag_name]
                ]
            ];

            if (!empty($tech_tools)) {
                $article_schema["dependencies"] = is_array($tech_tools) ? implode(', ', $tech_tools) : $tech_tools;
            }

            $schemas[] = $article_schema;
        }

        foreach ($schemas as $schema) {
            echo '<script type="application/ld+json">' . "\n";
            echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
            echo '</script>' . "\n";
        }
    }
}
