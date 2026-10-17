<?php
/**
 * Plugin Name: ТЧП Контент и Регламент (TCHP Content Core)
 * Plugin URI: https://tochka-plavleniya.ru/
 * Description: Регистрация инженерных метаполей, защита прав на редакционную проверку, вычисление отпечатка контента и интерфейс редактора.
 * Version: 1.0.0
 * Author: Лаборатория «ТОЧКА ПЛАВЛЕНИЯ»
 * License: GPL-2.0+
 */

defined('ABSPATH') || exit;

define('TCHP_CONTENT_VERSION', '1.0.0');

/**
 * 1. Роли и права (Capabilities)
 */
function tchp_register_capabilities() {
    $roles = ['administrator', 'editor'];
    foreach ($roles as $role_slug) {
        $role = get_role($role_slug);
        if ($role && !$role->has_cap('tchp_review_content')) {
            $role->add_cap('tchp_review_content');
        }
    }
}
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'tchp_register_capabilities');
}
add_action('admin_init', 'tchp_register_capabilities');

/**
 * Проверка прав пользователя на редакционную проверку конкретной записи
 */
function tchp_can_user_review_post($user_id, $post_id) {
    if (!user_can($user_id, 'tchp_review_content')) {
        return false;
    }
    return user_can($user_id, 'edit_post', $post_id);
}

/**
 * 2. Регистрация мета-полей для REST API и системы
 */
add_action('init', function () {
    // 2.1. Стандартные инженерные поля (доступны авторам/автоматизации с правом edit_post)
    $auth_edit_post = function ($allowed, $meta_key, $post_id, $user_id) {
        return user_can($user_id, 'edit_post', $post_id);
    };

    // 2.2. Защищённые поля редакционной проверки (только с tchp_review_content + edit_post)
    $auth_review_post = function ($allowed, $meta_key, $post_id, $user_id) {
        return tchp_can_user_review_post($user_id, $post_id);
    };

    // Идентификация и версионирование
    register_post_meta('post', 'tchp_content_id', [
        'type'              => 'string',
        'description'       => 'Постоянный внешний идентификатор материала',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_schema_version', [
        'type'              => 'string',
        'description'       => 'Версия схемы контракта статьи',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Паспорт статьи
    register_post_meta('post', 'tchp_reading_time', [
        'type'              => 'integer',
        'description'       => 'Время чтения статьи в минутах',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'absint',
    ]);

    register_post_meta('post', 'tchp_difficulty', [
        'type'              => 'string',
        'description'       => 'Уровень сложности (Базовый, Инженер, Эксперт)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_solder_alloy', [
        'type'              => 'string',
        'description'       => 'Идентификатор припоя из реестра (pos61, sac305, roze и др.)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_key',
    ]);

    register_post_meta('post', 'tchp_temp_kind', [
        'type'              => 'string',
        'description'       => 'Тип температуры: station_setpoint, joint_temperature, reflow_profile',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_key',
    ]);

    register_post_meta('post', 'tchp_temp_range', [
        'type'              => 'string',
        'description'       => 'Рабочий диапазон температур',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_temp_peak', [
        'type'              => 'string',
        'description'       => 'Пиковая допустимая температура',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard', [
        'type'              => 'string',
        'description'       => 'Обозначение стандарта (ГОСТ 21931, IPC-A-610G)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard_class', [
        'type'              => 'string',
        'description'       => 'Класс стандарта (КЛАСС 1, КЛАСС 2, КЛАСС 3)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard_description', [
        'type'              => 'string',
        'description'       => 'Описание требований стандарта',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Структурированные массивы (Tools, FAQ, Sources)
    register_post_meta('post', 'tchp_tools', [
        'type'              => 'array',
        'description'       => 'Список необходимой оснастки и инструментов',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'name'    => ['type' => 'string'],
                        'model'   => ['type' => 'string'],
                        'purpose' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'tchp_sanitize_tools_meta',
    ]);

    register_post_meta('post', 'tchp_faq_items', [
        'type'              => 'array',
        'description'       => 'Вопросы и ответы (FAQ)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'q' => ['type' => 'string'],
                        'a' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'tchp_sanitize_faq_meta',
    ]);

    register_post_meta('post', 'tchp_sources', [
        'type'              => 'array',
        'description'       => 'Источники, регламенты и стандарты',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type'       => 'object',
                    'properties' => [
                        'title'    => ['type' => 'string'],
                        'standard' => ['type' => 'string'],
                        'url'      => ['type' => 'string'],
                    ],
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'tchp_sanitize_sources_meta',
    ]);

    // Защищенные редакционные поля
    register_post_meta('post', 'tchp_review_status', [
        'type'              => 'string',
        'description'       => 'Статус редакционной проверки (unreviewed, in_review, verified)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_review_post,
        'sanitize_callback' => function ($val) {
            $allowed = ['unreviewed', 'in_review', 'verified'];
            return in_array($val, $allowed, true) ? $val : 'unreviewed';
        },
    ]);

    register_post_meta('post', 'tchp_reviewer_id', [
        'type'              => 'integer',
        'description'       => 'ID проверившего редактора (WP User ID)',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_review_post,
        'sanitize_callback' => 'absint',
    ]);

    register_post_meta('post', 'tchp_reviewed_at', [
        'type'              => 'string',
        'description'       => 'Дата и время проверки в формате ISO 8601',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_review_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_reviewed_content_hash', [
        'type'              => 'string',
        'description'       => 'Серверный отпечаток проверенного содержимого статьи',
        'single'            => true,
        'show_in_rest'      => true,
        'auth_callback'     => $auth_review_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);
});

/**
 * Санитизация инструментов
 */
function tchp_sanitize_tools_meta($tools) {
    if (empty($tools)) {
        return [];
    }
    if (is_string($tools)) {
        $decoded = json_decode($tools, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $tools = $decoded;
        } else {
            $lines = array_map('trim', explode(',', $tools));
            $tools = array_map(function ($t) { return ['name' => $t, 'model' => '', 'purpose' => '']; }, $lines);
        }
    }
    if (!is_array($tools)) {
        return [];
    }
    $clean = [];
    foreach ($tools as $item) {
        if (is_string($item) && trim($item) !== '') {
            $clean[] = [
                'name'    => sanitize_text_field(trim($item)),
                'model'   => '',
                'purpose' => '',
            ];
        } elseif (is_array($item) && !empty($item['name'])) {
            $clean[] = [
                'name'    => sanitize_text_field($item['name']),
                'model'   => isset($item['model']) ? sanitize_text_field($item['model']) : '',
                'purpose' => isset($item['purpose']) ? sanitize_text_field($item['purpose']) : '',
            ];
        }
    }
    return $clean;
}

/**
 * Санитизация FAQ
 */
function tchp_sanitize_faq_meta($faq) {
    if (empty($faq)) {
        return [];
    }
    if (is_string($faq)) {
        $decoded = json_decode($faq, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $faq = $decoded;
        } else {
            return [];
        }
    }
    if (!is_array($faq)) {
        return [];
    }
    $clean = [];
    foreach ($faq as $item) {
        if (is_array($item) && !empty($item['q']) && !empty($item['a'])) {
            $clean[] = [
                'q' => wp_strip_all_tags(trim($item['q'])),
                'a' => wp_strip_all_tags(trim($item['a'])),
            ];
        }
    }
    return $clean;
}

/**
 * Санитизация источников
 */
function tchp_sanitize_sources_meta($sources) {
    if (empty($sources)) {
        return [];
    }
    if (is_string($sources)) {
        $decoded = json_decode($sources, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $sources = $decoded;
        } else {
            return [];
        }
    }
    if (!is_array($sources)) {
        return [];
    }
    $clean = [];
    foreach ($sources as $item) {
        if (is_array($item) && !empty($item['title'])) {
            $clean[] = [
                'title'    => sanitize_text_field($item['title']),
                'standard' => isset($item['standard']) ? sanitize_text_field($item['standard']) : '',
                'url'      => isset($item['url']) ? esc_url_raw($item['url']) : '',
            ];
        }
    }
    return $clean;
}

/**
 * 3. Серверный расчет отпечатка проверяемого контента статьи
 */
function tchp_calculate_article_hash($post_id) {
    $post = get_post($post_id);
    if (!$post) {
        return '';
    }

    $title   = trim(get_the_title($post_id));
    $content = trim($post->post_content);
    $excerpt = trim($post->post_excerpt);

    $tools   = tchp_get_article_tools($post_id);
    $faq     = tchp_get_article_faq($post_id);
    $sources = tchp_get_article_sources($post_id);

    $alloy   = (string) get_post_meta($post_id, 'tchp_solder_alloy', true);
    $t_kind  = (string) get_post_meta($post_id, 'tchp_temp_kind', true);
    $t_range = (string) get_post_meta($post_id, 'tchp_temp_range', true);
    $t_peak  = (string) get_post_meta($post_id, 'tchp_temp_peak', true);
    $std     = (string) get_post_meta($post_id, 'tchp_standard', true);
    $std_cls = (string) get_post_meta($post_id, 'tchp_standard_class', true);

    $fingerprint_data = [
        'title'      => $title,
        'content'    => $content,
        'excerpt'    => $excerpt,
        'tools'      => $tools,
        'faq'        => $faq,
        'sources'    => $sources,
        'alloy'      => $alloy,
        't_kind'     => $t_kind,
        't_range'    => $t_range,
        't_peak'     => $t_peak,
        'standard'   => $std,
        'std_class'  => $std_cls,
    ];

    return hash('sha256', json_encode($fingerprint_data, JSON_UNESCAPED_UNICODE));
}

/**
 * 4. Проверка и автоматический сброс устаревшей проверки
 * Вызывается после полного сохранения поста, терминов и метаданных
 */
function tchp_verify_content_fingerprint_after_save($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_type !== 'post') {
        return;
    }

    $current_status = get_post_meta($post_id, 'tchp_review_status', true);
    if ($current_status !== 'verified') {
        return;
    }

    $reviewed_hash = get_post_meta($post_id, 'tchp_reviewed_content_hash', true);
    if (empty($reviewed_hash)) {
        return;
    }

    $current_hash = tchp_calculate_article_hash($post_id);
    if ($current_hash !== $reviewed_hash) {
        // Контент изменился после верификации — помечаем как устаревшую
        update_post_meta($post_id, 'tchp_review_status', 'in_review');
        update_post_meta($post_id, 'tchp_review_outdated', '1');
    }
}
add_action('wp_after_insert_post', 'tchp_verify_content_fingerprint_after_save', 20, 3);
add_action('rest_after_insert_post', function ($post, $request, $creating) {
    $post_obj = get_post($post->ID);
    if ($post_obj) {
        tchp_verify_content_fingerprint_after_save($post->ID, $post_obj, true);
    }
}, 20, 3);

/**
 * 5. Нормализаторы данных (с поддержкой миграции и различия «нет» vs «очищено»)
 */

/**
 * Получить инструменты статьи
 *
 * @param int $post_id
 * @return array
 */
function tchp_get_article_tools($post_id) {
    // 1. Проверяем наличие основного метаполя tchp_tools
    if (metadata_exists('post', $post_id, 'tchp_tools')) {
        $meta = get_post_meta($post_id, 'tchp_tools', true);
        if ($meta === '' || $meta === [] || $meta === false) {
            // Метаполе существует, но пустое: НАМЕРЕННО ОЧИЩЕНО! Никаких фоллбеков!
            return [];
        }
        return tchp_sanitize_tools_meta($meta);
    }

    // 2. Проверяем старое поле tchp_tools_needed (от раннего генератора)
    if (metadata_exists('post', $post_id, 'tchp_tools_needed')) {
        $needed = get_post_meta($post_id, 'tchp_tools_needed', true);
        if ($needed === '' || $needed === [] || $needed === false) {
            return [];
        }
        return tchp_sanitize_tools_meta($needed);
    }

    // 3. Метаполе вообще не задано: фоллбек на старые PHP-данные уроков (обратная совместимость)
    $post_slug = get_post_field('post_name', $post_id);
    if (function_exists('get_lesson_by_slug')) {
        $lesson = get_lesson_by_slug($post_slug);
        if (!empty($lesson['required_tools'])) {
            $tools = [];
            foreach ($lesson['required_tools'] as $name => $desc) {
                $tools[] = [
                    'name'    => (string) $name,
                    'model'   => '',
                    'purpose' => (string) $desc,
                ];
            }
            return $tools;
        }
    }

    return [];
}

/**
 * Получить FAQ статьи
 *
 * @param int $post_id
 * @return array
 */
function tchp_get_article_faq($post_id) {
    if (metadata_exists('post', $post_id, 'tchp_faq_items')) {
        $meta = get_post_meta($post_id, 'tchp_faq_items', true);
        if ($meta === '' || $meta === [] || $meta === false) {
            return [];
        }
        return tchp_sanitize_faq_meta($meta);
    }
    return [];
}

/**
 * Получить источники статьи
 *
 * @param int $post_id
 * @return array
 */
function tchp_get_article_sources($post_id) {
    if (metadata_exists('post', $post_id, 'tchp_sources')) {
        $meta = get_post_meta($post_id, 'tchp_sources', true);
        if ($meta === '' || $meta === [] || $meta === false) {
            return [];
        }
        return tchp_sanitize_sources_meta($meta);
    }
    return [];
}

/**
 * Получить редакционные данные статьи
 *
 * @param int $post_id
 * @return array
 */
function tchp_get_editorial_data($post_id) {
    $status      = get_post_meta($post_id, 'tchp_review_status', true) ?: 'unreviewed';
    $reviewer_id = (int) get_post_meta($post_id, 'tchp_reviewer_id', true);
    $reviewed_at = (string) get_post_meta($post_id, 'tchp_reviewed_at', true);
    $is_outdated = (bool) get_post_meta($post_id, 'tchp_review_outdated', true);

    $reviewer_name = '';
    if ($reviewer_id > 0) {
        $user = get_userdata($reviewer_id);
        if ($user) {
            $reviewer_name = $user->display_name ?: $user->user_login;
        }
    }

    return [
        'status'        => $status,
        'reviewer_id'   => $reviewer_id,
        'reviewer_name' => $reviewer_name,
        'reviewed_at'   => $reviewed_at,
        'is_outdated'   => $is_outdated,
        'is_verified'   => ($status === 'verified' && !$is_outdated),
    ];
}

/**
 * 6. Определение владельца SEO Schema.org (Rank Math vs Theme)
 * Не отключает разметку темы, пока Rank Math фактически не активен и не настроен.
 */
function tchp_should_render_theme_schema($post_id) {
    // 1. Если плагин Rank Math не установлен/не активен — тема выводит резервную схему
    if (!class_exists('RankMath')) {
        return true;
    }

    // 2. Если модуль Schema в Rank Math отключен пользователем — тема выводит резервную
    if (function_exists('rank_math')) {
        $modules = get_option('rank_math_modules', []);
        if (is_array($modules) && empty($modules['rich-snippet'])) {
            return true;
        }
    }

    // 3. В остальных случаях разметкой управляет Rank Math, тему подавляем во избежание дублей
    return false;
}

/**
 * 7. Интерфейс управления полями в админке WordPress (Meta Boxes)
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tchp_article_spec_box',
        '🔬 Паспорт статьи // Точка Плавления',
        'tchp_render_article_spec_box',
        'post',
        'normal',
        'high'
    );

    add_meta_box(
        'tchp_article_editorial_box',
        '🛡️ Редакционная проверка (ОТК)',
        'tchp_render_article_editorial_box',
        'post',
        'side',
        'high'
    );
});

/**
 * Рендер метабокса характеристик, инструментов, FAQ и источников
 */
function tchp_render_article_spec_box($post) {
    wp_nonce_field('tchp_save_spec_box', 'tchp_spec_nonce');

    $post_id   = $post->ID;
    $diff      = get_post_meta($post_id, 'tchp_difficulty', true) ?: 'Инженер';
    $alloy     = get_post_meta($post_id, 'tchp_solder_alloy', true) ?: 'pos61';
    $t_kind    = get_post_meta($post_id, 'tchp_temp_kind', true) ?: 'joint_temperature';
    $t_range   = get_post_meta($post_id, 'tchp_temp_range', true) ?: '';
    $t_peak    = get_post_meta($post_id, 'tchp_temp_peak', true) ?: '';
    $standard  = get_post_meta($post_id, 'tchp_standard', true) ?: '';
    $std_class = get_post_meta($post_id, 'tchp_standard_class', true) ?: '';
    $tools     = tchp_get_article_tools($post_id);
    $faq       = tchp_get_article_faq($post_id);
    $sources   = tchp_get_article_sources($post_id);
    ?>
    <style>
      .tchp-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 16px; }
      .tchp-meta-field label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; }
      .tchp-meta-field input, .tchp-meta-field select { width: 100%; }
      .tchp-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
      .tchp-table th, .tchp-table td { padding: 6px 8px; border: 1px solid #ddd; text-align: left; }
      .tchp-table th { background: #f9f9f9; }
      .tchp-btn-del { color: #d63638; cursor: pointer; text-decoration: underline; background: none; border: none; }
      .tchp-section-h { font-size: 14px; font-weight: 700; margin: 18px 0 8px; border-bottom: 1px solid #eee; padding-bottom: 4px; }
    </style>

    <div class="tchp-meta-grid">
      <div class="tchp-meta-field">
        <label for="tchp_difficulty">Уровень сложности:</label>
        <select name="tchp_difficulty" id="tchp_difficulty">
          <option value="Базовый" <?= selected($diff, 'Базовый', false) ?>>Базовый</option>
          <option value="Инженер" <?= selected($diff, 'Инженер', false) ?>>Инженер</option>
          <option value="Эксперт" <?= selected($diff, 'Эксперт', false) ?>>Эксперт</option>
        </select>
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_solder_alloy">Припой (реестр):</label>
        <select name="tchp_solder_alloy" id="tchp_solder_alloy">
          <option value="pos61" <?= selected($alloy, 'pos61', false) ?>>ПОС-61 (183–190 °C)</option>
          <option value="sac305" <?= selected($alloy, 'sac305', false) ?>>SAC305 (217–220 °C)</option>
          <option value="pos40" <?= selected($alloy, 'pos40', false) ?>>ПОС-40 (183–238 °C)</option>
          <option value="roze" <?= selected($alloy, 'roze', false) ?>>Сплав Розе (94 °C)</option>
          <option value="sn42bi58" <?= selected($alloy, 'sn42bi58', false) ?>>Sn42Bi58 (138 °C)</option>
          <option value="wood" <?= selected($alloy, 'wood', false) ?>>Сплав Вуда (68–72 °C)</option>
          <option value="universal" <?= selected($alloy, 'universal', false) ?>>Универсальный / Обзор</option>
        </select>
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_temp_kind">Тип температуры:</label>
        <select name="tchp_temp_kind" id="tchp_temp_kind">
          <option value="joint_temperature" <?= selected($t_kind, 'joint_temperature', false) ?>>Температура соединения (пайки)</option>
          <option value="station_setpoint" <?= selected($t_kind, 'station_setpoint', false) ?>>Уставка станции (жала/фена)</option>
          <option value="reflow_profile" <?= selected($t_kind, 'reflow_profile', false) ?>>Профиль оплавления (печь/стол)</option>
        </select>
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_temp_range">Рабочий диапазон:</label>
        <input type="text" name="tchp_temp_range" id="tchp_temp_range" value="<?= esc_attr($t_range) ?>" placeholder="напр. 217 – 235 °C">
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_temp_peak">Пиковая допустимая T:</label>
        <input type="text" name="tchp_temp_peak" id="tchp_temp_peak" value="<?= esc_attr($t_peak) ?>" placeholder="напр. 245 °C">
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_standard">Стандарт:</label>
        <input type="text" name="tchp_standard" id="tchp_standard" value="<?= esc_attr($standard) ?>" placeholder="напр. IPC-A-610G">
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_standard_class">Класс стандарта:</label>
        <input type="text" name="tchp_standard_class" id="tchp_standard_class" value="<?= esc_attr($std_class) ?>" placeholder="напр. КЛАСС 2">
      </div>
    </div>

    <!-- Инструменты -->
    <div class="tchp-section-h">🛠️ Оснастка и инструменты</div>
    <table class="tchp-table" id="tchp-tools-table">
      <thead>
        <tr>
          <th style="width: 35%;">Название</th>
          <th style="width: 30%;">Модель/Марка</th>
          <th>Назначение</th>
          <th style="width: 40px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tools as $idx => $tool): ?>
          <tr>
            <td><input type="text" name="tchp_tools[<?= $idx ?>][name]" value="<?= esc_attr($tool['name']) ?>" style="width:100%;"></td>
            <td><input type="text" name="tchp_tools[<?= $idx ?>][model]" value="<?= esc_attr($tool['model']) ?>" style="width:100%;"></td>
            <td><input type="text" name="tchp_tools[<?= $idx ?>][purpose]" value="<?= esc_attr($tool['purpose']) ?>" style="width:100%;"></td>
            <td><button type="button" class="tchp-btn-del" onclick="this.closest('tr').remove()">Удалить</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><button type="button" class="button" onclick="tchpAddToolRow()">+ Добавить инструмент</button></p>

    <!-- FAQ -->
    <div class="tchp-section-h">❓ Часто задаваемые вопросы (FAQ)</div>
    <div id="tchp-faq-container">
      <?php foreach ($faq as $idx => $f): ?>
        <div style="border:1px solid #ddd; padding:8px; margin-bottom:8px; background:#fff;">
          <p style="margin:0 0 4px;"><b>Вопрос:</b> <input type="text" name="tchp_faq[<?= $idx ?>][q]" value="<?= esc_attr($f['q']) ?>" style="width:100%;"></p>
          <p style="margin:0 0 4px;"><b>Ответ:</b> <textarea name="tchp_faq[<?= $idx ?>][a]" rows="2" style="width:100%;"><?= esc_textarea($f['a']) ?></textarea></p>
          <button type="button" class="tchp-btn-del" onclick="this.closest('div').remove()">Удалить вопрос</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button type="button" class="button" onclick="tchpAddFaqItem()">+ Добавить вопрос</button></p>

    <!-- Источники -->
    <div class="tchp-section-h">📚 Источники и регламенты</div>
    <table class="tchp-table" id="tchp-sources-table">
      <thead>
        <tr>
          <th style="width: 40%;">Название</th>
          <th style="width: 25%;">Стандарт</th>
          <th>URL</th>
          <th style="width: 40px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sources as $idx => $src): ?>
          <tr>
            <td><input type="text" name="tchp_sources[<?= $idx ?>][title]" value="<?= esc_attr($src['title']) ?>" style="width:100%;"></td>
            <td><input type="text" name="tchp_sources[<?= $idx ?>][standard]" value="<?= esc_attr($src['standard']) ?>" style="width:100%;"></td>
            <td><input type="url" name="tchp_sources[<?= $idx ?>][url]" value="<?= esc_attr($src['url']) ?>" style="width:100%;"></td>
            <td><button type="button" class="tchp-btn-del" onclick="this.closest('tr').remove()">Удалить</button></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p><button type="button" class="button" onclick="tchpAddSourceRow()">+ Добавить источник</button></p>

    <script>
      function tchpAddToolRow() {
        const tbody = document.querySelector('#tchp-tools-table tbody');
        const idx = Date.now();
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input type="text" name="tchp_tools[${idx}][name]" value="" style="width:100%;"></td>
          <td><input type="text" name="tchp_tools[${idx}][model]" value="" style="width:100%;"></td>
          <td><input type="text" name="tchp_tools[${idx}][purpose]" value="" style="width:100%;"></td>
          <td><button type="button" class="tchp-btn-del" onclick="this.closest('tr').remove()">Удалить</button></td>
        `;
        tbody.appendChild(tr);
      }

      function tchpAddFaqItem() {
        const c = document.getElementById('tchp-faq-container');
        const idx = Date.now();
        const div = document.createElement('div');
        div.style = 'border:1px solid #ddd; padding:8px; margin-bottom:8px; background:#fff;';
        div.innerHTML = `
          <p style="margin:0 0 4px;"><b>Вопрос:</b> <input type="text" name="tchp_faq[${idx}][q]" value="" style="width:100%;"></p>
          <p style="margin:0 0 4px;"><b>Ответ:</b> <textarea name="tchp_faq[${idx}][a]" rows="2" style="width:100%;"></textarea></p>
          <button type="button" class="tchp-btn-del" onclick="this.closest('div').remove()">Удалить вопрос</button>
        `;
        c.appendChild(div);
      }

      function tchpAddSourceRow() {
        const tbody = document.querySelector('#tchp-sources-table tbody');
        const idx = Date.now();
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input type="text" name="tchp_sources[${idx}][title]" value="" style="width:100%;"></td>
          <td><input type="text" name="tchp_sources[${idx}][standard]" value="" style="width:100%;"></td>
          <td><input type="url" name="tchp_sources[${idx}][url]" value="" style="width:100%;"></td>
          <td><button type="button" class="tchp-btn-del" onclick="this.closest('tr').remove()">Удалить</button></td>
        `;
        tbody.appendChild(tr);
      }
    </script>
    <?php
}

/**
 * Рендер метабокса редакционной проверки (ОТК)
 */
function tchp_render_article_editorial_box($post) {
    wp_nonce_field('tchp_save_editorial_box', 'tchp_editorial_nonce');

    $post_id    = $post->ID;
    $editorial  = tchp_get_editorial_data($post_id);
    $can_review = tchp_can_user_review_post(get_current_user_id(), $post_id);
    $current_hash = tchp_calculate_article_hash($post_id);
    $stored_hash  = get_post_meta($post_id, 'tchp_reviewed_content_hash', true);
    ?>
    <div style="font-size:12px; line-height:1.5;">
      <?php if ($editorial['is_verified']): ?>
        <div style="background:#e6f4ea; color:#137333; padding:8px; border-radius:4px; font-weight:bold; margin-bottom:10px;">
          ✓ Проверено редакцией
        </div>
      <?php elseif ($editorial['is_outdated']): ?>
        <div style="background:#fef7e0; color:#b06000; padding:8px; border-radius:4px; font-weight:bold; margin-bottom:10px;">
          ⚠️ Проверка устарела (контент был изменен)
        </div>
      <?php else: ?>
        <div style="background:#f1f3f4; color:#5f6368; padding:8px; border-radius:4px; font-weight:bold; margin-bottom:10px;">
          ⏳ Редакционная проверка не завершена
        </div>
      <?php endif; ?>

      <p style="margin:4px 0;"><b>Статус:</b> <code><?= esc_html($editorial['status']) ?></code></p>
      <?php if (!empty($editorial['reviewer_name'])): ?>
        <p style="margin:4px 0;"><b>Проверил:</b> <?= esc_html($editorial['reviewer_name']) ?></p>
      <?php endif; ?>
      <?php if (!empty($editorial['reviewed_at'])): ?>
        <p style="margin:4px 0;"><b>Дата:</b> <?= esc_html(date_i18n('d.m.Y H:i', strtotime($editorial['reviewed_at']))) ?></p>
      <?php endif; ?>

      <hr style="margin:10px 0; border:0; border-top:1px solid #ddd;">

      <?php if ($can_review): ?>
        <label for="tchp_review_status" style="display:block; font-weight:600; margin-bottom:4px;">Изменить статус:</label>
        <select name="tchp_review_status" id="tchp_review_status" style="width:100%; margin-bottom:8px;">
          <option value="unreviewed" <?= selected($editorial['status'], 'unreviewed', false) ?>>Не проверено (unreviewed)</option>
          <option value="in_review" <?= selected($editorial['status'], 'in_review', false) ?>>На проверке (in_review)</option>
          <option value="verified" <?= selected($editorial['status'], 'verified', false) ?>>Утверждено ОТК (verified)</option>
        </select>
        <p class="description" style="margin:0 0 8px;">При выборе «Утверждено ОТК» отпечаток статьи будет зафиксирован текущим моментом от вашего имени.</p>
      <?php else: ?>
        <p class="description" style="color:#d93025;">У вас нет права <code>tchp_review_content</code> для утверждения проверки статьи.</p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * Сохранение данных метабоксов из панели админки
 */
add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Сохранение паспорта статьи
    if (isset($_POST['tchp_spec_nonce']) && wp_verify_nonce($_POST['tchp_spec_nonce'], 'tchp_save_spec_box')) {
        if (isset($_POST['tchp_difficulty'])) {
            update_post_meta($post_id, 'tchp_difficulty', sanitize_text_field($_POST['tchp_difficulty']));
        }
        if (isset($_POST['tchp_solder_alloy'])) {
            update_post_meta($post_id, 'tchp_solder_alloy', sanitize_key($_POST['tchp_solder_alloy']));
        }
        if (isset($_POST['tchp_temp_kind'])) {
            update_post_meta($post_id, 'tchp_temp_kind', sanitize_key($_POST['tchp_temp_kind']));
        }
        if (isset($_POST['tchp_temp_range'])) {
            update_post_meta($post_id, 'tchp_temp_range', sanitize_text_field($_POST['tchp_temp_range']));
        }
        if (isset($_POST['tchp_temp_peak'])) {
            update_post_meta($post_id, 'tchp_temp_peak', sanitize_text_field($_POST['tchp_temp_peak']));
        }
        if (isset($_POST['tchp_standard'])) {
            update_post_meta($post_id, 'tchp_standard', sanitize_text_field($_POST['tchp_standard']));
        }
        if (isset($_POST['tchp_standard_class'])) {
            update_post_meta($post_id, 'tchp_standard_class', sanitize_text_field($_POST['tchp_standard_class']));
        }

        // Инструменты: если переданы — санитизируем, если нет — сохраняем пустой массив (намеренно очищены!)
        $tools = isset($_POST['tchp_tools']) ? tchp_sanitize_tools_meta($_POST['tchp_tools']) : [];
        update_post_meta($post_id, 'tchp_tools', $tools);

        // FAQ: аналогично
        $faq = isset($_POST['tchp_faq']) ? tchp_sanitize_faq_meta($_POST['tchp_faq']) : [];
        update_post_meta($post_id, 'tchp_faq_items', $faq);

        // Источники
        $sources = isset($_POST['tchp_sources']) ? tchp_sanitize_sources_meta($_POST['tchp_sources']) : [];
        update_post_meta($post_id, 'tchp_sources', $sources);
    }

    // Сохранение редакционной проверки (только при наличии прав)
    if (isset($_POST['tchp_editorial_nonce']) && wp_verify_nonce($_POST['tchp_editorial_nonce'], 'tchp_save_editorial_box')) {
        if (tchp_can_user_review_post(get_current_user_id(), $post_id)) {
            $new_status = isset($_POST['tchp_review_status']) ? sanitize_key($_POST['tchp_review_status']) : 'unreviewed';
            $old_status = get_post_meta($post_id, 'tchp_review_status', true);

            if ($new_status === 'verified') {
                update_post_meta($post_id, 'tchp_review_status', 'verified');
                update_post_meta($post_id, 'tchp_reviewer_id', get_current_user_id());
                update_post_meta($post_id, 'tchp_reviewed_at', current_time('c'));
                delete_post_meta($post_id, 'tchp_review_outdated');
                // Фиксируем хэш контента
                $hash = tchp_calculate_article_hash($post_id);
                update_post_meta($post_id, 'tchp_reviewed_content_hash', $hash);
            } else {
                update_post_meta($post_id, 'tchp_review_status', $new_status);
            }
        }
    }
});
