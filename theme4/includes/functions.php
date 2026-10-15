<?php
/**
 * functions.php — Вспомогательные функции темы «Точка Плавления»
 */

if (!function_exists('e')) {
    function e($str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url): string {
        return htmlspecialchars((string)$url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text): string {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text): string {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
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
        
        // Preserve active query params (tag, s)
        $queryParams = $_GET;
        
        $html = '<nav class="pagination">';
        
        // Prev button
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
        
        // Next button
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

if (!function_exists('get_avatar_char')) {
    function get_avatar_char(string $name): string {
        if (empty($name)) return 'Т';
        if (function_exists('mb_substr')) {
            return mb_substr($name, 0, 1);
        }
        if (preg_match('/./u', $name, $match)) {
            return $match[0];
        }
        return substr($name, 0, 1);
    }
}

if (!function_exists('safe_strtoupper')) {
    function safe_strtoupper($str): string {
        return function_exists('mb_strtoupper') ? mb_strtoupper((string)$str) : strtoupper((string)$str);
    }
}

if (!function_exists('get_reading_time')) {
    function get_reading_time(string $text, int $wpm = 180): int {
        $clean_text = strip_tags($text);
        $word_count = count(preg_split('/\s+/u', trim($clean_text), -1, PREG_SPLIT_NO_EMPTY));
        return max(3, (int) ceil($word_count / $wpm));
    }
}

if (!function_exists('render_article_schema')) {
    function render_article_schema(array $article, array $faq = []): string {
        $slug = $article['slug'] ?? 'post';
        $post_id = $article['id'] ?? 0;
        if (function_exists('tchp_get_article_url')) {
            $raw_url = tchp_get_article_url($slug, $post_id);
            if (str_starts_with($raw_url, 'http://') || str_starts_with($raw_url, 'https://')) {
                $url = $raw_url;
            } else {
                $url = "https://tochka-plavleniya.ru" . (str_starts_with($raw_url, '/') ? $raw_url : '/' . $raw_url);
            }
        } else {
            $url = "https://tochka-plavleniya.ru/" . urlencode($slug) . "/";
        }
        $date_pub = !empty($article['date']) ? $article['date'] : '2026-08-20';
        $date_mod = !empty($article['date_modified']) ? $article['date_modified'] : $date_pub;
        $graph = [
            [
                "@type" => "TechArticle",
                "@id" => $url . "#article",
                "headline" => $article['title'] ?? '',
                "description" => $article['excerpt'] ?? '',
                "inLanguage" => "ru",
                "datePublished" => $date_pub,
                "dateModified" => $date_mod,
                "author" => [
                    "@type" => "Person",
                    "name" => $article['author'] ?? 'Инженер Лаборатории ТЧП',
                    "jobTitle" => "Ведущий инженер-технолог пайки и монтажа РЭА",
                    "worksFor" => [
                        "@type" => "Organization",
                        "name" => "Точка Плавления // ТЧП",
                        "url" => "https://tochka-plavleniya.ru/"
                    ]
                ],
                "publisher" => [
                    "@type" => "Organization",
                    "name" => "Точка Плавления",
                    "url" => "https://tochka-plavleniya.ru/"
                ],
                "mainEntityOfPage" => $url,
                "proficiencyLevel" => $article['difficulty'] ?? "Expert / PRO"
            ],
            [
                "@type" => "BreadcrumbList",
                "itemListElement" => [
                    [
                        "@type" => "ListItem",
                        "position" => 1,
                        "name" => "Главная",
                        "item" => "https://tochka-plavleniya.ru/"
                    ],
                    [
                        "@type" => "ListItem",
                        "position" => 2,
                        "name" => "Статьи",
                        "item" => "https://tochka-plavleniya.ru/#articles"
                    ],
                    [
                        "@type" => "ListItem",
                        "position" => 3,
                        "name" => $article['title'] ?? '',
                        "item" => $url
                    ]
                ]
            ]
        ];

        if (!empty($faq)) {
            $faqItems = [];
            foreach ($faq as $q => $a) {
                $faqItems[] = [
                    "@type" => "Question",
                    "name" => $q,
                    "acceptedAnswer" => [
                        "@type" => "Answer",
                        "text" => $a
                    ]
                ];
            }
            $graph[] = [
                "@type" => "FAQPage",
                "mainEntity" => $faqItems
            ];
        }

        $json = json_encode([
            "@context" => "https://schema.org",
            "@graph" => $graph
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);


        return "<script type=\"application/ld+json\">\n" . $json . "\n</script>";
    }
}

/**
 * Фильтр для автоматического добавления rel="sponsored nofollow noopener" к партнёрским ссылкам (347-ФЗ)
 */
if (!function_exists('tchp_filter_affiliate_links')) {
    function tchp_filter_affiliate_links(string $content): string {
        if (empty($content)) return $content;

        $patterns = ['aliexpress.com', 'ya.cc', 'market.yandex.ru', 'ozon.ru', 'megamarket.ru'];
        foreach ($patterns as $domain) {
            $content = preg_replace_callback(
                '/<a\s+([^>]*href=["\'][^"\']*' . preg_quote($domain, '/') . '[^"\']*["\'][^>]*)>/i',
                function ($matches) {
                    $tag = $matches[0];
                    if (strpos($tag, 'rel=') === false) {
                        return str_replace('<a ', '<a rel="sponsored nofollow noopener" ', $tag);
                    } else {
                        // If rel already exists, ensure sponsored is present
                        if (strpos($tag, 'sponsored') === false) {
                            return preg_replace('/rel=["\']([^"\']*)["\']/i', 'rel="$1 sponsored"', $tag);
                        }
                        return $tag;
                    }
                },
                $content
            );
        }
        return $content;
    }
}

/**
 * Строгая семантическая цветовая классификация тегов ТЧП:
 * - Orange: Тепловые режимы, припои, нагрев, предупреждения о дефектах
 * - Yellow: Химия, флюсы, канифоль, заметки верстака
 * - Blue: Стандарты (ГОСТ, IPC), SMD-монтаж, геометрия жал, приборы
 */
if (!function_exists('get_semantic_tag_pill')) {
    function get_semantic_tag_pill(string $tag_key): string {
        return match(strtolower($tag_key)) {
            'materials'          => 'pill-orange', // Металлы, сплавы, нагрев
            'basics'             => 'pill-yellow', // База, флюсы, канифоль
            'smd', 'tools'       => 'pill-blue',   // SMD компоненты, приборы, точность
            'defects', 'oshibki' => 'pill-orange', // Ошибки, брак, предупреждения
            default              => 'pill-blue',
        };
    }
}

/**
 * Schema.org микроразметка для страницы рубрики (CollectionPage + ItemList + BreadcrumbList)
 */
if (!function_exists('render_rubric_schema')) {
    function render_rubric_schema(array $rubric, array $articles): string {
        $rslug = $rubric['slug'] ?? 'materialy';
        if (function_exists('get_category_by_slug') && ($cat = get_category_by_slug($rslug))) {
            $canonical_url = get_category_link($cat->term_id);
        } else {
            $canonical_url = "https://tochka-plavleniya.ru/category/" . urlencode($rslug) . "/";
        }
        $itemList = [];
        $pos = 1;
        foreach ($articles as $art) {
            $aslug = $art['slug'] ?? '';
            $aid = $art['id'] ?? 0;
            if (function_exists('tchp_get_article_url')) {
                $raw_art_url = tchp_get_article_url($aslug, $aid);
                $itemUrl = (str_starts_with($raw_art_url, 'http://') || str_starts_with($raw_art_url, 'https://'))
                    ? $raw_art_url
                    : ("https://tochka-plavleniya.ru" . (str_starts_with($raw_art_url, '/') ? $raw_art_url : '/' . $raw_art_url));
            } else {
                $itemUrl = "https://tochka-plavleniya.ru/" . urlencode($aslug) . "/";
            }
            $itemList[] = [
                "@type" => "ListItem",
                "position" => $pos++,
                "url" => $itemUrl,
                "name" => $art['title'] ?? ''
            ];
        }

        $schema = [
            "@context" => "https://schema.org",
            "@graph" => [
                [
                    "@type" => "CollectionPage",
                    "@id" => $canonical_url . "#webpage",
                    "url" => $canonical_url,
                    "name" => ($rubric['title'] ?? 'Рубрика') . " — Журнал ТОЧКА ПЛАВЛЕНИЯ",
                    "description" => $rubric['lead'] ?? '',
                    "inLanguage" => "ru",
                    "isPartOf" => [
                        "@type" => "WebSite",
                        "@id" => "https://tochka-plavleniya.ru/#website",
                        "url" => "https://tochka-plavleniya.ru/",
                        "name" => "ТОЧКА ПЛАВЛЕНИЯ // ТЧП"
                    ],
                    "mainEntity" => [
                        "@type" => "ItemList",
                        "itemListElement" => $itemList
                    ]
                ],
                [
                    "@type" => "BreadcrumbList",
                    "itemListElement" => [
                        [
                            "@type" => "ListItem",
                            "position" => 1,
                            "name" => "Главная",
                            "item" => "https://tochka-plavleniya.ru/"
                        ],
                        [
                            "@type" => "ListItem",
                            "position" => 2,
                            "name" => "Рубрики журнала",
                            "item" => "https://tochka-plavleniya.ru/#rubrics"
                        ],
                        [
                            "@type" => "ListItem",
                            "position" => 3,
                            "name" => $rubric['title'] ?? 'Рубрика',
                            "item" => $canonical_url
                        ]
                    ]
                ]
            ]
        ];

        return "<script type=\"application/ld+json\">\n" . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n</script>";
    }
}

if (!function_exists('tchp_get_article_url')) {
    /**
     * Возвращает корректный URL статьи как для WordPress, так и для автономного PHP-сервера
     */
    function tchp_get_article_url(string $slug, int $id = 0): string {
        $site_root = function_exists('home_url') ? home_url('/') : '/';
        if (function_exists('get_permalink') && $id > 0 && function_exists('get_post') && get_post($id)) {
            return get_permalink($id);
        }
        if (function_exists('home_url') && function_exists('get_posts')) {
            return home_url('/' . urlencode($slug) . '/');
        }
        return $site_root . 'article.php?slug=' . urlencode($slug);
    }
}

if (!function_exists('tp_icon')) {
    /**
     * Возвращает локальную Lucide SVG-иконку из assets/icons/
     */
    function tp_icon(string $name, string $class = ''): string {
        static $cache = [];
        $clean_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $name);
        
        if (!isset($cache[$clean_name])) {
            $path = __DIR__ . '/../assets/icons/' . $clean_name . '.svg';
            if (file_exists($path)) {
                $cache[$clean_name] = file_get_contents($path);
            } else {
                $cache[$clean_name] = '';
            }
        }
        
        if (empty($cache[$clean_name])) {
            return '';
        }
        
        $svg = $cache[$clean_name];
        $classes = trim('tp-icon ' . $class);
        if (!empty($classes)) {
            $svg = preg_replace('/^<svg\b/', '<svg class="' . esc_attr($classes) . '"', $svg, 1);
        }
        return $svg;
    }
}


