<?php
/**
 * demo-importer.php — Модуль импорта базы знаний и регламентов «Точка Плавления» в WordPress
 * 
 * Создает рубрики и полноценные инженерные статьи с 15 паттернами Gutenberg:
 * - TL;DR стикеры
 * - Заметки на полях с обтеканием
 * - Предупреждения по технике безопасности
 * - Советы мастера и стандарты IPC/ГОСТ
 * - Оснастка верстака и инструменты
 * - Пошаговые карточки регламента
 * - Чек-листы перед включением
 * - Сравнение «Дефект» vs «Норма»
 * - Рукописные маргиналии
 * - FAQ аккордеоны
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Генерация богатого контента Gutenberg для статьи/урока
 */
function tchp_build_gutenberg_content($item) {
    $out = [];

    // 1. Вводный лид
    $lead = !empty($item['subtitle']) ? $item['subtitle'] : (!empty($item['excerpt']) ? $item['excerpt'] : '');
    if ($lead) {
        $out[] = '<!-- wp:paragraph {"className":"text-lg font-serif leading-relaxed text-ink/90"} -->';
        $out[] = '<p class="text-lg font-serif leading-relaxed text-ink/90">' . esc_html($lead) . '</p>';
        $out[] = '<!-- /wp:paragraph -->';
    }

    // 2. Стикер TL;DR (Главный вывод)
    $tldr = !empty($item['tldr']) ? $item['tldr'] : (!empty($item['excerpt']) ? $item['excerpt'] : 'Перед началом пайки всегда прогревайте плату нижним подогревом и контролируйте смачивание галтели.');
    $out[] = '<!-- wp:group {"className":"callout-tldr"} -->';
    $out[] = '<div class="wp-block-group callout-tldr"><!-- wp:heading {"level":3,"className":"font-hand text-2xl font-bold mb-2 text-ink"} -->';
    $out[] = '<h3 class="font-hand text-2xl font-bold mb-2 text-ink">📌 Главный принцип (TL;DR)</h3>';
    $out[] = '<!-- /wp:heading --><!-- wp:paragraph {"className":"text-ink/90 leading-relaxed m-0"} -->';
    $out[] = '<p class="text-ink/90 leading-relaxed m-0">' . esc_html($tldr) . '</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 3. Заметка на полях (Aside Callout с обтеканием)
    $aside = !empty($item['danger_rule']['hand']) ? strip_tags($item['danger_rule']['hand']) : '«Не дави жалом на текстолит — тепло передаёт капля припоя и флюс, а не механическое усилие!»';
    $out[] = '<!-- wp:group {"className":"callout-aside"} -->';
    $out[] = '<div class="wp-block-group callout-aside"><!-- wp:paragraph {"className":"font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1"} -->';
    $out[] = '<p class="font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1">🏷️ ЗАМЕТКА НА ПОЛЯХ</p>';
    $out[] = '<!-- /wp:paragraph --><!-- wp:paragraph {"className":"text-xs leading-relaxed m-0"} -->';
    $out[] = '<p class="text-xs leading-relaxed m-0">' . esc_html($aside) . '</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 4. Техника безопасности (Safety First)
    $danger = !empty($item['danger_rule']['text']) ? $item['danger_rule']['text'] : 'Перегрев свыше 260°C дольше 5 секунд разрушает клеевой подслой печатных проводников и приводит к отрыву контактных площадок.';
    $danger_title = !empty($item['danger_rule']['title']) ? $item['danger_rule']['title'] : 'ОШИБКА: ПЕРЕГРЕВ И ТЕРМОУДАР';
    $out[] = '<!-- wp:group {"className":"callout-safety"} -->';
    $out[] = '<div class="wp-block-group callout-safety"><!-- wp:paragraph {"className":"m-0"} -->';
    $out[] = '<p class="m-0">⚠️ <strong>' . esc_html($danger_title) . ':</strong> ' . esc_html($danger) . '</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 5. Оснастка верстака и инструменты (Tools Box)
    $tools = !empty($item['required_tools']) ? $item['required_tools'] : ['Паяльная станция' => 'с контролем температуры жала', 'Флюс' => 'NC-559 или канифольный гель ROL0', 'Припой' => 'Sn63Pb37 Ø 0.5–0.8 мм'];
    $out[] = '<!-- wp:group {"className":"tools-box"} -->';
    $out[] = '<div class="wp-block-group tools-box"><!-- wp:heading {"level":3,"className":"text-sm font-mono font-bold uppercase tracking-wider text-ink mb-2"} -->';
    $out[] = '<h3 class="text-sm font-mono font-bold uppercase tracking-wider text-ink mb-2">🧰 НЕОБХОДИМЫЙ ИНСТРУМЕНТ И ОСНАСТКА</h3>';
    $out[] = '<!-- /wp:heading --><!-- wp:list {"className":"space-y-1 font-mono text-xs text-ink/90"} -->';
    $out[] = '<ul class="space-y-1 font-mono text-xs text-ink/90">';
    foreach ($tools as $t_name => $t_spec) {
        $out[] = '<li><strong>' . esc_html(is_numeric($t_name) ? $t_spec : $t_name) . '</strong>: ' . esc_html(is_numeric($t_name) ? 'регламентный допуск' : $t_spec) . '</li>';
    }
    $out[] = '</ul>';
    $out[] = '<!-- /wp:list --></div>';
    $out[] = '<!-- /wp:group -->';

    // 6. Пошаговый регламент операции (Step Cards)
    if (!empty($item['steps']) && is_array($item['steps'])) {
        $out[] = '<!-- wp:heading {"level":2,"className":"text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4"} -->';
        $out[] = '<h2 class="text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4">Пошаговый технологический регламент</h2>';
        $out[] = '<!-- /wp:heading -->';

        foreach ($item['steps'] as $idx => $step) {
            $num = $step['num'] ?? ($idx + 1);
            $num_pad = str_pad($num, 2, '0', STR_PAD_LEFT);
            $stitle = $step['title'] ?? ('Операция ' . $num);
            $sdesc = $step['desc'] ?? '';

            $out[] = '<!-- wp:group {"className":"step-card"} -->';
            $out[] = '<div class="wp-block-group step-card">';
            $out[] = '<span class="step-badge-orange">ШАГ ' . esc_html($num_pad) . '</span>';
            $out[] = '<!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mt-2 mb-1"} -->';
            $out[] = '<h4 class="text-base font-bold text-ink mt-2 mb-1">' . esc_html($stitle) . '</h4>';
            $out[] = '<!-- /wp:heading --><!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} -->';
            $out[] = '<p class="text-sm text-ink-muted leading-relaxed m-0">' . esc_html($sdesc) . '</p>';
            $out[] = '<!-- /wp:paragraph --></div>';
            $out[] = '<!-- /wp:group -->';
        }
    } else {
        // Стандартные шаги операции
        $out[] = '<!-- wp:heading {"level":2,"className":"text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4"} -->';
        $out[] = '<h2 class="text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4">Технологическая карта операции</h2>';
        $out[] = '<!-- /wp:heading -->';

        $out[] = '<!-- wp:group {"className":"step-card"} -->';
        $out[] = '<div class="wp-block-group step-card"><span class="step-badge-orange">ШАГ 01</span>';
        $out[] = '<!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mt-2 mb-1"} --><h4 class="text-base font-bold text-ink mt-2 mb-1">Подготовка поверхности и деоксидация</h4><!-- /wp:heading -->';
        $out[] = '<!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} --><p class="text-sm text-ink-muted leading-relaxed m-0">Очистите контактные площадки от оксидной пленки изопропиловым спиртом высокой чистоты (99.8%) и нанесите тонкий слой флюса-геля.</p><!-- /wp:paragraph --></div>';
        $out[] = '<!-- /wp:group -->';

        $out[] = '<!-- wp:group {"className":"step-card"} -->';
        $out[] = '<div class="wp-block-group step-card"><span class="step-badge-orange">ШАГ 02</span>';
        $out[] = '<!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mt-2 mb-1"} --><h4 class="text-base font-bold text-ink mt-2 mb-1">Термостабилизация и формирование галтели</h4><!-- /wp:heading -->';
        $out[] = '<!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} --><p class="text-sm text-ink-muted leading-relaxed m-0">Подведите жало паяльника одновременно к выводу детали и контактной площадке. Подайте припой в точку контакта на 1–2 секунды до образования вогнутой менисковой галтели.</p><!-- /wp:paragraph --></div>';
        $out[] = '<!-- /wp:group -->';

        $out[] = '<!-- wp:group {"className":"step-card"} -->';
        $out[] = '<div class="wp-block-group step-card"><span class="step-badge-orange">ШАГ 03</span>';
        $out[] = '<!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mt-2 mb-1"} --><h4 class="text-base font-bold text-ink mt-2 mb-1">Контроль остывания и смывка остатков</h4><!-- /wp:heading -->';
        $out[] = '<!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} --><p class="text-sm text-ink-muted leading-relaxed m-0">Не двигайте деталь до полного перехода припоя в твердую фазу. Промойте плату от остатков флюса, чтобы предотвратить коррозию и токи утечки.</p><!-- /wp:paragraph --></div>';
        $out[] = '<!-- /wp:group -->';
    }

    // 7. Сравнение «Дефект» vs «Норма» (Compare Grid)
    $out[] = '<!-- wp:heading {"level":2,"className":"text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4"} -->';
    $out[] = '<h2 class="text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4">ОТК Контроль: Типовой брак против стандарта</h2>';
    $out[] = '<!-- /wp:heading -->';

    $defect_txt = !empty($item['defects']['bad']) ? $item['defects']['bad'] : 'Шероховатая зернистая поверхность, угол смачивания больше 90°, шарообразный ком припоя без затекания в гильзу.';
    $norm_txt = !empty($item['defects']['good']) ? $item['defects']['good'] : 'Вогнутая гладкая галтель с зеркальным блеском, угол смачивания менее 40°, отчётливый контур вывода под слоем олова.';

    $out[] = '<!-- wp:group {"className":"compare-grid"} -->';
    $out[] = '<div class="wp-block-group compare-grid">';
    $out[] = '<div class="compare-card defect"><span class="badge-defect">ДЕФЕКТ // БРАК</span><!-- wp:paragraph {"className":"text-xs text-ink/80 leading-relaxed m-0 mt-2"} --><p class="text-xs text-ink/80 leading-relaxed m-0 mt-2">' . esc_html($defect_txt) . '</p><!-- /wp:paragraph --></div>';
    $out[] = '<div class="compare-card norm"><span class="badge-norm">НОРМА // ГОДНО</span><!-- wp:paragraph {"className":"text-xs text-ink/80 leading-relaxed m-0 mt-2"} --><p class="text-xs text-ink/80 leading-relaxed m-0 mt-2">' . esc_html($norm_txt) . '</p><!-- /wp:paragraph --></div>';
    $out[] = '</div>';
    $out[] = '<!-- /wp:group -->';

    // 8. Совет мастера (Pro Tip)
    $out[] = '<!-- wp:group {"className":"callout-pro"} -->';
    $out[] = '<div class="wp-block-group callout-pro"><!-- wp:paragraph {"className":"m-0"} -->';
    $out[] = '<p class="m-0">💡 <strong>СОВЕТ МАСТЕРА:</strong> Держите жало всегда залуженным свежей каплей припоя прямо перед выключением станции. Оксидная плёнка мгновенно растёт на сухом горячем никелевом покрытии и убивает теплопередачу.</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 9. Стандарт IPC-A-610 / ГОСТ
    $out[] = '<!-- wp:group {"className":"callout-ipc"} -->';
    $out[] = '<div class="wp-block-group callout-ipc"><!-- wp:paragraph {"className":"m-0"} -->';
    $out[] = '<p class="m-0">📜 <strong>СТАНДАРТ IPC-A-610G (Класс 3 / Промышленная надёжность):</strong> Толщина интерметаллического соединения (IMC) Cu6Sn5 на границе медь-припой должна быть в диапазоне 0.5–1.5 мкм. Превышение толщины из-за перегрева делает шов хрупким при термоциклировании.</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 10. Предполётный инженерный чек-лист (Checklist Box)
    $chk = !empty($item['checklist']) && is_array($item['checklist']) ? $item['checklist'] : [
        'Температура жала стабилизирована и откалибрована',
        'Плата подогрета до 100–120°C для снятия термических напряжений',
        'Флюс нанесен точечно без залива соседних разъемов и кнопок',
        'Контроль времени контакта: не более 2.5 секунд на точку монтажа'
    ];
    $out[] = '<!-- wp:group {"className":"checklist-box"} -->';
    $out[] = '<div class="wp-block-group checklist-box">';
    $out[] = '<span class="font-mono text-xs font-bold uppercase tracking-wider text-accent mb-2 block">☑️ ПРЕДПОЛЁТНЫЙ ЧЕК-ЛИСТ ОПЕРАЦИИ</span>';
    $out[] = '<!-- wp:list {"className":"space-y-1 font-mono text-xs text-ink/90"} -->';
    $out[] = '<ul class="space-y-1 font-mono text-xs text-ink/90">';
    foreach ($chk as $ci) {
        $out[] = '<li>[✓] ' . esc_html($ci) . '</li>';
    }
    $out[] = '</ul>';
    $out[] = '<!-- /wp:list --></div>';
    $out[] = '<!-- /wp:group -->';

    // 11. Рукописная маргиналия цеховика (Marginalia Box)
    $out[] = '<!-- wp:group {"className":"marginalia-box"} -->';
    $out[] = '<div class="wp-block-group marginalia-box"><!-- wp:paragraph {"className":"m-0"} -->';
    $out[] = '<p class="m-0">«Хорошая пайка блестит как ртуть, а плохая крошится как сахар под ногтем. Смотри в бинокуляр перед тем, как подать питание!»</p>';
    $out[] = '<!-- /wp:paragraph --></div>';
    $out[] = '<!-- /wp:group -->';

    // 12. FAQ (Частые вопросы и ошибки)
    if (!empty($item['faq']) && is_array($item['faq'])) {
        $out[] = '<!-- wp:heading {"level":2,"className":"text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4"} -->';
        $out[] = '<h2 class="text-2xl font-bold font-sans text-ink tracking-tight mt-8 mb-4">Вопросы и разбор ошибок (FAQ)</h2>';
        $out[] = '<!-- /wp:heading -->';

        foreach ($item['faq'] as $q => $a) {
            $out[] = '<!-- wp:group {"className":"faq-item"} -->';
            $out[] = '<div class="wp-block-group faq-item">';
            $out[] = '<!-- wp:heading {"level":4,"className":"text-sm font-bold text-ink m-0"} --><h4 class="text-sm font-bold text-ink m-0">❓ ' . esc_html($q) . '</h4><!-- /wp:heading -->';
            $out[] = '<!-- wp:paragraph {"className":"text-xs text-ink-muted leading-relaxed mt-1 m-0"} --><p class="text-xs text-ink-muted leading-relaxed mt-1 m-0">' . esc_html($a) . '</p><!-- /wp:paragraph -->';
            $out[] = '</div>';
            $out[] = '<!-- /wp:group -->';
        }
    }

    return implode("\n\n", $out);
}

/**
 * Основная функция выполнения импорта
 */
function tchp_run_demo_import() {
    require_once ABSPATH . 'wp-admin/includes/taxonomy.php';
    require_once ABSPATH . 'wp-admin/includes/post.php';

    // 1. Создание 4 рубрик журнала
    $categories = [
        'start' => [
            'name' => 'Старт и база',
            'desc' => 'Основы радиомонтажа, подготовка рабочего места, стандарты температуры и безопасность.'
        ],
        'materialy' => [
            'name' => 'Материалы и сплавы',
            'desc' => 'Припои ПОС-61, SAC305, сплав Розе, классификация флюсов, канифоль и паяльные пасты.'
        ],
        'praktika' => [
            'name' => 'Практика и монтаж',
            'desc' => 'Прикладные регламенты: лужение проводов, пайка SMD 0402/0603, BGA реболлинг и посадка QFN.'
        ],
        'oshibki' => [
            'name' => 'Дефекты и проблемы',
            'desc' => 'Диагностика брака пайки, холодная пайка, перегрев дорожек, мостики припоя и смывка флюса.'
        ]
    ];

    $cat_ids = [];
    $created_cats = 0;

    foreach ($categories as $cslug => $cinfo) {
        $term = get_term_by('slug', $cslug, 'category');
        if (!$term) {
            $res = wp_insert_term($cinfo['name'], 'category', [
                'slug'        => $cslug,
                'description' => $cinfo['desc']
            ]);
            if (!is_wp_error($res)) {
                $cat_ids[$cslug] = $res['term_id'];
                $created_cats++;
            }
        } else {
            $cat_ids[$cslug] = $term->term_id;
        }
    }

    // Подгрузка данных из файлов темы
    $theme_dir = get_template_directory();
    require_once $theme_dir . '/includes/articles-data.php';
    if (file_exists($theme_dir . '/data/lessons-data.php')) {
        require_once $theme_dir . '/data/lessons-data.php';
    }

    // Сбор всех элементов для создания
    global $articles, $lessons;
    $all_items = [];

    // Добавляем 10 микроуроков
    if (function_exists('get_all_lessons')) {
        foreach (get_all_lessons() as $lslug => $ldata) {
            $ldata['slug'] = $lslug;
            $all_items[$lslug] = $ldata;
        }
    }

    // Добавляем 12 основных статей (не перезаписывая уроки)
    if (!empty($articles) && is_array($articles)) {
        foreach ($articles as $art) {
            $slug = $art['slug'] ?? '';
            if ($slug && !isset($all_items[$slug])) {
                $all_items[$slug] = $art;
            }
        }
    }

    // Маппинг категорий по умолчанию
    $slug_to_cat = [
        'temperaturnye-profili'            => 'start',
        'smd-0402-vs-0603'                 => 'praktika',
        'gid-po-flyusam'                   => 'materialy',
        'zhala-payalnika'                  => 'start',
        'bga-rebolling'                    => 'praktika',
        'splav-roze'                       => 'materialy',
        'bezsvintsovaya-payka'             => 'materialy',
        'antistaticheskaya-zashchita'      => 'start',
        'payka-mikroskopy'                 => 'praktika',
        'reanimatsiya-zhal'                => 'oshibki',
        'defekty-payki'                    => 'oshibki',
        'vlagootmyvka-plat'                => 'materialy',
        'luzhenie-provoda'                 => 'praktika',
        'zamena-razema-payalnikom'         => 'praktika',
        'payka-smv-0603-mikrovolna'        => 'praktika',
        'demontazh-bga-fenom'              => 'praktika',
        'rebolling-bga-trafaret'           => 'praktika',
        'otmyvka-platy-ultrazvuk'          => 'materialy',
        'vosstanovlenie-otorvannoy-dorozhki' => 'oshibki',
        'ochistka-okislennogo-zhala'       => 'oshibki',
        'montazh-qfn-bez-zamykaniy'        => 'praktika',
        'zamena-kondensatora-na-poligone'  => 'start'
    ];

    $created_posts = [];
    $skipped_posts = [];

    foreach ($all_items as $slug => $item) {
        // Проверяем, существует ли уже запись с таким slug в WordPress (чтобы не затереть существующие записи пользователя!)
        $existing = get_page_by_path($slug, OBJECT, 'post');
        if (!$existing) {
            $found = get_posts([
                'name'        => $slug,
                'post_type'   => 'post',
                'post_status' => 'any',
                'numberposts' => 1
            ]);
            if (!empty($found)) {
                $existing = $found[0];
            }
        }

        if ($existing) {
            $skipped_posts[] = [
                'id'    => $existing->ID,
                'title' => $existing->post_title,
                'slug'  => $slug
            ];
            continue;
        }

        // Определение рубрики
        $target_cat_slug = $slug_to_cat[$slug] ?? 'materialy';
        $target_cat_id = $cat_ids[$target_cat_slug] ?? (get_option('default_category') ?: 1);

        $gutenberg_content = tchp_build_gutenberg_content($item);
        $title = $item['title'] ?? 'Инженерный регламент пайки';
        $excerpt = $item['subtitle'] ?? ($item['excerpt'] ?? '');
        $author_id = get_current_user_id() ?: 1;

        $post_data = [
            'post_title'    => $title,
            'post_name'     => $slug,
            'post_content'  => $gutenberg_content,
            'post_excerpt'  => $excerpt,
            'post_status'   => 'publish',
            'post_author'   => $author_id,
            'post_category' => [$target_cat_id],
            'tags_input'    => [$item['tag'] ?? 'Пайка'],
            'meta_input'    => [
                'tchp_reading_time' => $item['read_min'] ?? 4,
                'tchp_standard'     => $item['key_specs']['Стандарт'] ?? 'ГОСТ / IPC-A-610',
                'tchp_difficulty'   => $item['difficulty'] ?? 'Практика'
            ]
        ];

        $post_id = wp_insert_post($post_data);
        if ($post_id && !is_wp_error($post_id)) {
            $created_posts[] = [
                'id'    => $post_id,
                'title' => $title,
                'slug'  => $slug
            ];
        }
    }

    return [
        'created_cats'  => $created_cats,
        'created_posts' => $created_posts,
        'skipped_posts' => $skipped_posts
    ];
}

/**
 * Регистрация страницы в админке WordPress: Инструменты → Импорт статей ТЧП
 */
add_action('admin_menu', function() {
    add_management_page(
        'Импорт базы знаний «Точка Плавления»',
        'Импорт демо-статей ТЧП',
        'manage_options',
        'tchp-demo-importer',
        'tchp_render_import_admin_page'
    );
});

/**
 * Отрисовка страницы в админке
 */
function tchp_render_import_admin_page() {
    $result = null;
    if (isset($_POST['tchp_run_import_action']) && check_admin_referer('tchp_import_nonce')) {
        $result = tchp_run_demo_import();
    }
    ?>
    <div class="wrap" style="max-width: 900px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <h1 style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 28px;">🔥</span> 
            <span>Импорт базы знаний «ТОЧКА ПЛАВЛЕНИЯ»</span>
        </h1>
        
        <p style="font-size: 14px; color: #555; line-height: 1.5;">
            Этот инструмент за один клик наполнит ваш WordPress <strong>всеми рубриками и статьями инженерного журнала</strong> с полным набором из 15 паттернов Gutenberg (TL;DR стикеры, заметки на полях с обтеканием, чек-листы, предупреждения по технике безопасности, карточки шагов, сравнение «Дефект» vs «Норма»).
        </p>

        <?php if ($result): ?>
            <div class="notice notice-success is-dismissible" style="padding: 14px; border-left-color: #f59e0b;">
                <h3 style="margin-top: 0; color: #b45309;">✅ Импорт успешно завершён!</h3>
                <p>
                    <strong>Создано рубрик:</strong> <?= (int)$result['created_cats'] ?><br>
                    <strong>Создано новых статей:</strong> <?= count($result['created_posts']) ?><br>
                    <strong>Сохранено существующих статей:</strong> <?= count($result['skipped_posts']) ?> (ваши ранее созданные записи не были перезаписаны)
                </p>
                <p>
                    <a href="<?= esc_url(admin_url('edit.php')) ?>" class="button button-primary">Перейти в список записей →</a>
                </p>
            </div>
        <?php endif; ?>

        <div style="background: #ffffff; border: 2px solid #2c2523; box-shadow: 4px 4px 0 #2c2523; border-radius: 6px; padding: 24px; margin-top: 20px;">
            <h2 style="margin-top: 0; font-size: 18px; color: #2c2523;">Готовы к импорту:</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0;">
                <div style="background: #fdfbf7; border: 1px solid #e7e0d3; padding: 12px; border-radius: 4px;">
                    <strong>📁 4 рубрики:</strong>
                    <ul style="margin: 6px 0 0 16px; list-style: disc; font-size: 13px;">
                        <li>Старт и база (<code>start</code>)</li>
                        <li>Материалы и сплавы (<code>materialy</code>)</li>
                        <li>Практика и монтаж (<code>praktika</code>)</li>
                        <li>Дефекты и проблемы (<code>oshibki</code>)</li>
                    </ul>
                </div>
                <div style="background: #fdfbf7; border: 1px solid #e7e0d3; padding: 12px; border-radius: 4px;">
                    <strong>📝 22 инженерных регламента:</strong>
                    <ul style="margin: 6px 0 0 16px; list-style: disc; font-size: 13px;">
                        <li>12 флагманских статей журнала</li>
                        <li>10 прикладных микроуроков верстака</li>
                        <li>Все 15 паттернов Gutenberg включены</li>
                        <li>Существующие записи не затираются</li>
                    </ul>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('tchp_import_nonce'); ?>
                <input type="hidden" name="tchp_run_import_action" value="1">
                <button type="submit" class="button button-primary button-hero" style="background: #f59e0b; border-color: #d97706; color: #1c1917; font-weight: bold; text-shadow: none; box-shadow: 2px 2px 0 #2c2523;">
                    🚀 Создать все статьи и рубрики в базе данных
                </button>
            </form>
        </div>
    </div>
    <?php
}
