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
                "author" => array_filter([
                    "@type" => "Person",
                    "name" => $article['author'] ?? 'Редакция «Точка Плавления»',
                    "jobTitle" => !empty($article['author_job_title']) ? $article['author_job_title'] : null,
                    "worksFor" => [
                        "@type" => "Organization",
                        "name" => "Точка Плавления // ТЧП",
                        "url" => "https://tochka-plavleniya.ru/"
                    ]
                ]),
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

        if ($slug === 'temperaturnye-profili') {
            $graph[] = [
                "@type" => "HowTo",
                "@id" => $url . "#howto",
                "name" => "Настройка 4 фаз термопрофиля пайки BGA и SMD компонентов",
                "description" => "Пошаговый инженерный регламент пайки без коробления текстолита и деламинации.",
                "step" => [
                    ["@type" => "HowToStep", "name" => "Preheat (Преднагрев)", "text" => "Плавный подъем температуры со скоростью 1–3 °C/с до 150 °C для предотвращения термоудара."],
                    ["@type" => "HowToStep", "name" => "Soak (Активация флюса)", "text" => "Выдержка 60–120 секунд при 150–200 °C для выпаривания растворителей флюса и выравнивания градиента."],
                    ["@type" => "HowToStep", "name" => "Reflow (Плавление припоя)", "text" => "Пиковый нагрев до 235–245 °C с выдержкой Time Above Liquidus (TAL) 30–60 секунд."],
                    ["@type" => "HowToStep", "name" => "Cooling (Контролируемое охлаждение)", "text" => "Снижение температуры со скоростью 2–4 °C/с для формирования мелкозернистой структуры галтели."]
                ]
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
        $site_root_url = function_exists('home_url') ? home_url('/') : 'https://tochka-plavleniya.ru/';
        if ($rslug === 'all') {
            $canonical_url = $site_root_url . 'category/all/';
        } elseif (function_exists('get_category_by_slug') && ($cat = get_category_by_slug($rslug))) {
            $canonical_url = get_category_link($cat->term_id);
        } else {
            $canonical_url = $site_root_url . 'category/' . urlencode($rslug) . '/';
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
                            "name" => ($rslug === 'all' ? "Архив" : "Рубрики журнала"),
                            "item" => ($rslug === 'all' ? "https://tochka-plavleniya.ru/archive/" : "https://tochka-plavleniya.ru/#rubrics")
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

// ─────────────────────────────────────────────────────────────────────────────
// ЕДИНЫЙ ИСТОЧНИК ПРАВДЫ: РЕЕСТР МАТЕРИАЛОВ ТЧП
// ─────────────────────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/data/materials-registry.php';

if (!function_exists('tchp_get_materials_registry')) {
    function tchp_get_materials_registry(): array {
        global $MATERIALS_REGISTRY;
        return $MATERIALS_REGISTRY ?? [];
    }
}

if (!function_exists('tchp_get_express_workbench_matrix')) {
    /**
     * Формирует единую матрицу уставок экспресс-калькулятора (index.php)
     * строго на базе $MATERIALS_REGISTRY без дублирования в клиентском JS.
     */
    function tchp_get_express_workbench_matrix(): array {
        global $MATERIALS_REGISTRY;
        $solders = $MATERIALS_REGISTRY['solders'] ?? [];
        $matrix = [];

        // Специальные калиброванные пресеты для ключевых сплавов
        $known_presets = [
            'pos61' => [
                'smd'    => ['range' => '260 – 295', 'liq' => '183–190 °C', 'delta' => '+75...105 °C', 'max' => 295],
                'pth'    => ['range' => '280 – 320', 'liq' => '183–190 °C', 'delta' => '+95...130 °C', 'max' => 320],
                'copper' => ['range' => '300 – 340', 'liq' => '183–190 °C', 'delta' => '+115...150 °C', 'max' => 340],
            ],
            'sn63pb37' => [
                'smd'    => ['range' => '260 – 290', 'liq' => '183 °C', 'delta' => '+75...105 °C', 'max' => 290],
                'pth'    => ['range' => '275 – 315', 'liq' => '183 °C', 'delta' => '+90...130 °C', 'max' => 315],
                'copper' => ['range' => '300 – 340', 'liq' => '183 °C', 'delta' => '+115...155 °C', 'max' => 340],
            ],
            'pos40' => [
                'smd'    => ['range' => '280 – 310', 'liq' => '183–238 °C', 'delta' => '+42...72 °C от ликвидуса', 'max' => 310],
                'pth'    => ['range' => '300 – 330', 'liq' => '183–238 °C', 'delta' => '+62...92 °C от ликвидуса', 'max' => 330],
                'copper' => ['range' => '320 – 350', 'liq' => '183–238 °C', 'delta' => '+82...112 °C (технолог. предел 350 °C)', 'max' => 350],
            ],
            'sac305' => [
                'smd'    => ['range' => '310 – 335', 'liq' => '217–220 °C', 'delta' => '+90...115 °C', 'max' => 335],
                'pth'    => ['range' => '320 – 350', 'liq' => '217–220 °C', 'delta' => '+100...130 °C', 'max' => 350],
                'copper' => ['range' => '340 – 370', 'liq' => '217–220 °C', 'delta' => '+120...150 °C', 'max' => 370],
            ],
            'sn42bi58' => [
                'smd'    => ['range' => '200 – 225', 'liq' => '138 °C', 'delta' => '+62...87 °C', 'max' => 225],
                'pth'    => ['range' => '210 – 235', 'liq' => '138 °C', 'delta' => '+72...97 °C', 'max' => 235],
                'copper' => ['range' => '220 – 245', 'liq' => '138 °C', 'delta' => '+82...107 °C', 'max' => 245],
            ],
            'roze' => [
                'smd'    => ['range' => '140 – 170', 'liq' => '94 °C', 'delta' => '+46...76 °C', 'max' => 170],
                'pth'    => ['range' => '150 – 180', 'liq' => '94 °C', 'delta' => '+56...86 °C', 'max' => 180],
                'copper' => ['range' => '160 – 190', 'liq' => '94 °C', 'delta' => '+66...96 °C', 'max' => 190],
            ],
            'wood' => [
                'smd'    => ['range' => '110 – 135', 'liq' => '72 °C', 'delta' => '+40...65 °C', 'max' => 135],
                'pth'    => ['range' => '120 – 145', 'liq' => '72 °C', 'delta' => '+50...75 °C', 'max' => 145],
                'copper' => ['range' => '130 – 155', 'liq' => '72 °C', 'delta' => '+60...85 °C', 'max' => 155],
            ],
        ];

        foreach ($solders as $id => $s) {
            if (isset($known_presets[$id])) {
                $matrix[$id] = $known_presets[$id];
                continue;
            }
            $t_min = $s['t_melt_min'] ?? 183;
            $t_max = $s['t_melt_max'] ?? 190;
            $liq_str = ($t_min === $t_max) ? "{$t_max} °C" : "{$t_min}–{$t_max} °C";

            $matrix[$id] = [
                'smd'    => ['range' => ($t_max + 75) . ' – ' . ($t_max + 105), 'liq' => $liq_str, 'delta' => '+75...105 °C', 'max' => $t_max + 105],
                'pth'    => ['range' => ($t_max + 95) . ' – ' . ($t_max + 130), 'liq' => $liq_str, 'delta' => '+95...130 °C', 'max' => $t_max + 130],
                'copper' => ['range' => ($t_max + 115) . ' – ' . ($t_max + 150), 'liq' => $liq_str, 'delta' => '+115...150 °C', 'max' => $t_max + 150],
            ];
        }

        return $matrix;
    }
}

if (!function_exists('tchp_process_article_anchors')) {
    /**
     * Обработка якорей и TOC в HTML статьи с обеспечением 100% документной уникальности всех ID.
     * 
     * 1. Резервирует системные ID шаблона (main-content, reading-progress, sec-tools, etc.).
     * 2. Обнаруживает и устраняет дубликаты ID среди любых тегов (<div id="step-one">, <section id="step-one">),
     *    присваивая суффиксы -2, -3.
     * 3. Обрабатывает заголовки <h2>, формирует $article_toc,
     *    устраняет коллизии с уже занятыми ID.
     * 
     * @param string $content_html Исходный HTML контента статьи
     * @param array  &$article_toc Массив TOC: [['id' => '...', 'title' => '...'], ...]
     * @param array  &$detected_duplicates Массив обнаруженных дубликатов non-H2 ID
     * @return string Итоговый HTML со 100% уникальными ID
     */
    function tchp_process_article_anchors(string $content_html, array &$article_toc = [], array &$detected_duplicates = []): string {
        $article_toc = [];
        $detected_duplicates = [];
        
        // Системные зарезервированные ID интерфейса
        $reserved_ids = [
            'main-content'          => true,
            'article-faq'           => true,
            'reading-progress'      => true,
            'copy-article-link-btn' => true,
            'mobile-menu'           => true,
            'theme-toggle'          => true,
            'table-of-contents'     => true,
            'sec-tools'             => true,
            'sec-faq'               => true,
            'sec-sources'           => true,
            'sec-calc'              => true,
        ];
        
        $used_ids = $reserved_ids;

        // ЭТАП 1: Обработка всех элементов кроме <h2> (div, section, p, etc.)
        // Если у двух не-H2 элементов одинаковый ID, второму присваиваем -2, -3...
        // чтобы гарантировать отсутствие дублей ID в документе.
        $content_html = preg_replace_callback('/<([a-z0-9]+)\b([^>]*\bid=(["\'])([^"\']+)\3[^>]*)>/i', function($matches) use (&$used_ids, &$detected_duplicates) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];
            $id = $matches[4];

            if ($tag === 'h2') {
                return $matches[0]; // H2 обрабатываются на Этапе 2
            }

            if (!isset($used_ids[$id])) {
                $used_ids[$id] = true;
                return $matches[0];
            }

            // Коллизия обнаружена среди не-H2 тегов или с системным ID
            $detected_duplicates[] = $id;
            $counter = 2;
            $new_id = $id . '-' . $counter;
            while (isset($used_ids[$new_id])) {
                $counter++;
                $new_id = $id . '-' . $counter;
            }
            $used_ids[$new_id] = true;

            $esc_fn = function_exists('esc_attr') ? 'esc_attr' : 'htmlspecialchars';
            $new_attrs = preg_replace('/\bid=(["\'])' . preg_quote($id, '/') . '\1/i', 'id="' . $esc_fn($new_id) . '"', $attrs, 1);
            return '<' . $matches[1] . $new_attrs . '>';
        }, $content_html);

        // ЭТАП 2: Последовательная обработка <h2> заголовков
        $content_html = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/i', function($matches) use (&$article_toc, &$used_ids) {
            $attrs = $matches[1];
            $heading_content = $matches[2];
            $heading_title = trim(strip_tags($heading_content));
            $esc_fn = function_exists('esc_attr') ? 'esc_attr' : 'htmlspecialchars';

            // Вариант А: ID уже был задан в атрибутах
            if (preg_match('/\bid=(["\'])([^"\']+)\1/i', $attrs, $id_match)) {
                $existing_id = $id_match[2];
                if (!isset($used_ids[$existing_id])) {
                    $used_ids[$existing_id] = true;
                    $article_toc[] = [
                        'id'    => $existing_id,
                        'title' => $heading_title,
                    ];
                    return $matches[0];
                }

                // Коллизия: добавляем инкрементный суффикс
                $counter = 2;
                $candidate = $existing_id . '-' . $counter;
                while (isset($used_ids[$candidate])) {
                    $counter++;
                    $candidate = $existing_id . '-' . $counter;
                }
                $used_ids[$candidate] = true;
                $article_toc[] = [
                    'id'    => $candidate,
                    'title' => $heading_title,
                ];
                $new_attrs = preg_replace('/\bid=(["\'])' . preg_quote($existing_id, '/') . '\1/i', 'id="' . $esc_fn($candidate) . '"', $attrs, 1);
                return '<h2' . $new_attrs . '>' . $heading_content . '</h2>';
            }

            // Вариант Б: ID отсутствует -> генерируем slug из заголовка
            $slug_fn = function_exists('sanitize_title') ? 'sanitize_title' : function($str) {
                return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)$str), '-'));
            };
            $heading_slug = $slug_fn($heading_title);
            if (empty($heading_slug)) {
                $heading_slug = 'section';
            }
            $candidate = $heading_slug;
            $counter = 2;
            while (isset($used_ids[$candidate])) {
                $candidate = $heading_slug . '-' . $counter;
                $counter++;
            }
            $used_ids[$candidate] = true;
            $article_toc[] = [
                'id'    => $candidate,
                'title' => $heading_title,
            ];
            return '<h2 id="' . $esc_fn($candidate) . '"' . $attrs . '>' . $heading_content . '</h2>';
        }, $content_html);

        return $content_html;
    }
}

/**
 * GEO: машиночитаемые факты статьи из метаполей плагина tchp-content.
 * Возвращает PropertyValue (припой, температуры, стандарт), citation (источники)
 * и reviewedBy (редакционная проверка ОТК) для JSON-LD.
 */
if (!function_exists('tchp_get_geo_facts')) {
    function tchp_get_geo_facts($post_id) {
        $post_id = (int) $post_id;
        $empty = ['additionalProperty' => [], 'citation' => [], 'reviewedBy' => null, 'keywords' => []];
        if ($post_id <= 0 || !function_exists('get_post_meta')) {
            return $empty;
        }
        $props = [];
        $add = static function ($name, $value) use (&$props) {
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                $props[] = ['@type' => 'PropertyValue', 'name' => $name, 'value' => $value];
            }
        };
        // Припой и температуры — только если применимы к теме статьи.
        // 'universal' в метаполе означает «обзор/не про конкретный сплав».
        $alloy = trim((string) get_post_meta($post_id, 'tchp_solder_alloy', true));
        if ($alloy !== '' && $alloy !== 'universal') {
            $add('Припой (канонический ID)', $alloy);
        }
        $temp_range = trim((string) get_post_meta($post_id, 'tchp_temp_range', true));
        $temp_peak  = trim((string) get_post_meta($post_id, 'tchp_temp_peak', true));
        if ($temp_range !== '' || $temp_peak !== '') {
            $add('Тип температуры', get_post_meta($post_id, 'tchp_temp_kind', true));
            $add('Температурный диапазон, °C', $temp_range);
            $add('Пиковая температура, °C', $temp_peak);
        }
        $add('Стандарт', get_post_meta($post_id, 'tchp_standard', true));
        $add('Класс стандарта', get_post_meta($post_id, 'tchp_standard_class', true));
        $add('Пояснение к стандарту', get_post_meta($post_id, 'tchp_standard_description', true));

        $citations = [];
        if (function_exists('tchp_get_article_sources')) {
            foreach ((array) tchp_get_article_sources($post_id) as $src) {
                if (empty($src['title'])) {
                    continue;
                }
                $c = ['@type' => 'CreativeWork', 'name' => $src['title']];
                if (!empty($src['url'])) {
                    $c['url'] = $src['url'];
                }
                if (!empty($src['standard'])) {
                    $c['identifier'] = $src['standard'];
                }
                $citations[] = $c;
            }
        }

        $reviewed_by = null;
        if (function_exists('tchp_get_editorial_data')) {
            $ed = tchp_get_editorial_data($post_id);
            if (!empty($ed['is_verified']) && !empty($ed['reviewer_name'])) {
                $reviewed_by = ['@type' => 'Person', 'name' => $ed['reviewer_name']];
                if (!empty($ed['reviewed_at'])) {
                    $reviewed_by['dateModified'] = $ed['reviewed_at'];
                }
            }
        }

        $keywords = [];
        $alloy = trim((string) get_post_meta($post_id, 'tchp_solder_alloy', true));
        if ($alloy !== '' && $alloy !== 'universal') {
            $keywords[] = $alloy;
        }
        $standard = trim((string) get_post_meta($post_id, 'tchp_standard', true));
        if ($standard !== '') {
            $keywords[] = $standard;
        }
        if (function_exists('get_the_tags')) {
            foreach ((array) get_the_tags($post_id) as $tag) {
                if (!empty($tag->name)) {
                    $keywords[] = $tag->name;
                }
            }
        }
        if (function_exists('get_the_category')) {
            foreach ((array) get_the_category($post_id) as $cat) {
                if (!empty($cat->name)) {
                    $keywords[] = $cat->name;
                }
            }
        }

        return [
            'additionalProperty' => $props,
            'citation'           => $citations,
            'reviewedBy'         => $reviewed_by,
            'keywords'           => array_values(array_unique($keywords)),
        ];
    }
}

/**
 * GEO: полный JSON-LD статьи для WordPress (TechArticle + Breadcrumb + FAQPage).
 * Используется в single.php, когда разметкой владеет тема, а не Rank Math.
 */
if (!function_exists('tchp_render_wp_article_schema')) {
    function tchp_render_wp_article_schema($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !function_exists('get_permalink')) {
            return '';
        }
        $permalink  = get_permalink($post_id);
        $title      = get_the_title($post_id);
        $excerpt    = function_exists('get_the_excerpt') ? (string) get_the_excerpt($post_id) : '';
        $desc       = trim(wp_strip_all_tags($excerpt));
        if ($desc === '') {
            $desc = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 30, '…');
        }
        $author_id  = (int) get_post_field('post_author', $post_id);
        $author     = $author_id && function_exists('get_the_author_meta') ? (string) get_the_author_meta('display_name', $author_id) : '';
        $site_name  = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'ТОЧКА ПЛАВЛЕНИЯ';
        $home       = function_exists('home_url') ? home_url('/') : '/';
        $logo       = function_exists('get_template_directory_uri') ? get_template_directory_uri() . '/assets/img/og-cover.png' : '';
        $facts      = tchp_get_geo_facts($post_id);
        $content    = (string) get_post_field('post_content', $post_id);
        $words      = preg_split('/\s+/u', trim(wp_strip_all_tags($content)), -1, PREG_SPLIT_NO_EMPTY);

        $article = [
            '@type'              => 'TechArticle',
            '@id'                => $permalink . '#article',
            'headline'           => $title,
            'description'        => $desc,
            'inLanguage'         => 'ru-RU',
            'url'                => $permalink,
            'mainEntityOfPage'   => $permalink,
            'datePublished'      => get_the_date('c', $post_id),
            'dateModified'       => get_the_modified_date('c', $post_id),
            'isAccessibleForFree'=> true,
            'wordCount'          => is_array($words) ? count($words) : 0,
            'author'             => [
                '@type' => 'Person',
                'name'  => $author !== '' ? $author : $site_name,
            ],
            'publisher'          => [
                '@type' => 'Organization',
                'name'  => $site_name,
                'url'   => $home,
            ],
        ];
        if ($logo !== '') {
            $article['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }
        $difficulty = trim((string) get_post_meta($post_id, 'tchp_difficulty', true));
        if ($difficulty !== '') {
            $article['proficiencyLevel'] = $difficulty;
        }
        if (!empty($facts['additionalProperty'])) {
            $article['additionalProperty'] = $facts['additionalProperty'];
        }
        if (!empty($facts['citation'])) {
            $article['citation'] = $facts['citation'];
        }
        if (!empty($facts['reviewedBy'])) {
            $article['reviewedBy'] = $facts['reviewedBy'];
        }
        if (!empty($facts['keywords'])) {
            $article['keywords'] = implode(', ', $facts['keywords']);
        }
        $cats = function_exists('get_the_category') ? (array) get_the_category($post_id) : [];
        if (!empty($cats)) {
            $cat_names = [];
            foreach ($cats as $cat) {
                if (!empty($cat->name)) {
                    $cat_names[] = $cat->name;
                }
            }
            if ($cat_names) {
                $article['articleSection'] = implode(', ', $cat_names);
            }
        }

        $graph = [$article];

        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $home]];
        if (!empty($cats[0])) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => 2,
                'name'     => $cats[0]->name,
                'item'     => get_category_link($cats[0]->term_id),
            ];
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => count($items) + 1,
            'name'     => $title,
            'item'     => $permalink,
        ];
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];

        if (function_exists('tchp_get_article_faq')) {
            $faq = tchp_get_article_faq($post_id);
            if (!empty($faq)) {
                $questions = [];
                foreach ((array) $faq as $item) {
                    if (!empty($item['q']) && !empty($item['a'])) {
                        $questions[] = [
                            '@type'          => 'Question',
                            'name'           => $item['q'],
                            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
                        ];
                    }
                }
                if ($questions) {
                    $graph[] = ['@type' => 'FAQPage', '@id' => $permalink . '#faq', 'mainEntity' => $questions];
                }
            }
        }

        return "<script type=\"application/ld+json\">\n"
            . json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n</script>";
    }
}

