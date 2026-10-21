<?php
/**
 * Plugin Name: ТЧП Контент и Регламент (TCHP Content Core)
 * Plugin URI: https://tochka-plavleniya.ru/
 * Description: Регистрация инженерных метаполей, строгая REST-валидация, защита прав на редакционную проверку, вычисление отпечатка контента и интерфейс редактора.
 * Version: 1.1.2
 * Author: Лаборатория «ТОЧКА ПЛАВЛЕНИЯ»
 * License: GPL-2.0+
 */

defined('ABSPATH') || exit;

define('TCHP_CONTENT_VERSION', '1.1.2');

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
 * 2. Канонический реестр припоев и нормализатор алиасов
 */
function tchp_get_canonical_alloys() {
    return [
        'pos61',
        'sn63pb37',
        'pos40',
        'sac305',
        'sac405',
        'sn42bi58',
        'snbiago',
        'roze',
        'vuda',
        'universal',
    ];
}

/**
 * Нормализация идентификатора сплава (алиасы -> канонические ID)
 * wood -> vuda, rose -> roze.
 * universal — обзорный материал / регламент, а не физический сплав.
 */
function tchp_normalize_solder_alloy($alloy) {
    if (!is_string($alloy)) {
        return '';
    }
    $alloy = sanitize_key(trim($alloy));
    if ($alloy === 'wood') {
        $alloy = 'vuda';
    } elseif ($alloy === 'rose') {
        $alloy = 'roze';
    }
    $canonical = tchp_get_canonical_alloys();
    if (in_array($alloy, $canonical, true)) {
        return $alloy;
    }
    return '';
}

/**
 * Предварительная нормализация REST-запросов до валидации схемы
 */
add_filter('rest_pre_dispatch', function ($result, $server, $request) {
    if ($request instanceof WP_REST_Request) {
        $meta = $request->get_param('meta');
        if (is_array($meta) && isset($meta['tchp_solder_alloy'])) {
            $raw_alloy = (string) $meta['tchp_solder_alloy'];
            $normalized = tchp_normalize_solder_alloy($raw_alloy);
            if (!empty($normalized)) {
                $meta['tchp_solder_alloy'] = $normalized;
                $request->set_param('meta', $meta);
            }
        }
    }
    return $result;
}, 10, 3);

/**
 * Валидация метаполей перед записью в базу через REST API
 * Защищает от молчаливой очистки существующих данных и отклоняет пустые/невалидные значения с кодом 400.
 */
add_filter('rest_pre_insert_post', function ($prepared_post, $request) {
    $meta = $request->get_param('meta');
    if (!is_array($meta)) {
        return $prepared_post;
    }

    // 1. Проверка FAQ: если поле передано, валидируем каждый элемент
    if (array_key_exists('tchp_faq_items', $meta)) {
        if (!is_array($meta['tchp_faq_items'])) {
            return new WP_Error('rest_invalid_param', 'Поле meta.tchp_faq_items должно быть массивом.', ['status' => 400]);
        }
        foreach ($meta['tchp_faq_items'] as $idx => $item) {
            if (!is_array($item)) {
                return new WP_Error('rest_invalid_param', sprintf('Элемент meta.tchp_faq_items[%d] должен быть объектом.', $idx), ['status' => 400]);
            }
            $stripped_q = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($item['q']) : strip_tags($item['q']);
            if (!isset($item['q']) || !is_string($item['q']) || trim($stripped_q) === '') {
                return new WP_Error('rest_invalid_param', sprintf('Вопрос в meta.tchp_faq_items[%d].q не может быть пустым (в том числе после очистки HTML).', $idx), ['status' => 400]);
            }
            $stripped_a = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($item['a']) : strip_tags($item['a']);
            if (!isset($item['a']) || !is_string($item['a']) || trim($stripped_a) === '') {
                return new WP_Error('rest_invalid_param', sprintf('Ответ в meta.tchp_faq_items[%d].a не может быть пустым (в том числе после очистки HTML).', $idx), ['status' => 400]);
            }
        }
    }

    // 2. Проверка Инструментов
    if (array_key_exists('tchp_tools', $meta)) {
        if (!is_array($meta['tchp_tools'])) {
            return new WP_Error('rest_invalid_param', 'Поле meta.tchp_tools должно быть массивом.', ['status' => 400]);
        }
        foreach ($meta['tchp_tools'] as $idx => $item) {
            if (!is_array($item)) {
                return new WP_Error('rest_invalid_param', sprintf('Элемент meta.tchp_tools[%d] должен быть объектом.', $idx), ['status' => 400]);
            }
            $stripped_name = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($item['name']) : strip_tags($item['name']);
            if (!isset($item['name']) || !is_string($item['name']) || trim($stripped_name) === '') {
                return new WP_Error('rest_invalid_param', sprintf('Название инструмента в meta.tchp_tools[%d].name не может быть пустым (в том числе после очистки HTML).', $idx), ['status' => 400]);
            }
        }
    }

    // 3. Проверка Источников
    if (array_key_exists('tchp_sources', $meta)) {
        if (!is_array($meta['tchp_sources'])) {
            return new WP_Error('rest_invalid_param', 'Поле meta.tchp_sources должно быть массивом.', ['status' => 400]);
        }
        foreach ($meta['tchp_sources'] as $idx => $item) {
            if (!is_array($item)) {
                return new WP_Error('rest_invalid_param', sprintf('Элемент meta.tchp_sources[%d] должен быть объектом.', $idx), ['status' => 400]);
            }
            $stripped_title = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($item['title']) : strip_tags($item['title']);
            if (!isset($item['title']) || !is_string($item['title']) || trim($stripped_title) === '') {
                return new WP_Error('rest_invalid_param', sprintf('Название источника в meta.tchp_sources[%d].title не может быть пустым (в том числе после очистки HTML).', $idx), ['status' => 400]);
            }
        }
    }

    // 4. Проверка припоя (неизвестный припой -> отказ 400)
    if (array_key_exists('tchp_solder_alloy', $meta)) {
        $raw_alloy = (string) $meta['tchp_solder_alloy'];
        $norm_alloy = tchp_normalize_solder_alloy($raw_alloy);
        if (empty($norm_alloy)) {
            $allowed = tchp_get_canonical_alloys();
            return new WP_Error(
                'rest_invalid_param',
                sprintf('Недопустимый припой "%s". Разрешенные канонические: %s', esc_html($raw_alloy), implode(', ', $allowed)),
                ['status' => 400]
            );
        }
    }

    return $prepared_post;
}, 10, 2);

/**
 * 3. Регистрация мета-полей для REST API и системы
 */
add_action('init', function () {
    // Авторизация стандартных полей статьи (авторы / редакторы с edit_post)
    $auth_edit_post = function ($allowed, $meta_key, $post_id, $user_id) {
        return user_can($user_id, 'edit_post', $post_id);
    };

    // Защищенные серверные поля (запрет прямого изменения через REST meta)
    $auth_server_only = function () {
        return false;
    };

    // Идентификация и версионирование
    register_post_meta('post', 'tchp_content_id', [
        'type'              => 'string',
        'description'       => 'Постоянный внешний идентификатор материала',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 100,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_schema_version', [
        'type'              => 'string',
        'description'       => 'Версия схемы контракта статьи',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => ['1.0'],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Паспорт статьи
    register_post_meta('post', 'tchp_reading_time', [
        'type'              => 'integer',
        'description'       => 'Время чтения статьи в минутах (1–60)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'    => 'integer',
                'minimum' => 1,
                'maximum' => 60,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            if (!is_numeric($val) || (int) $val < 1 || (int) $val > 60) {
                return new WP_Error(
                    'rest_invalid_param',
                    'Поле tchp_reading_time должно быть целым числом от 1 до 60 минут.',
                    ['status' => 400]
                );
            }
            return true;
        },
        'sanitize_callback' => 'absint',
    ]);

    register_post_meta('post', 'tchp_difficulty', [
        'type'              => 'string',
        'description'       => 'Уровень сложности (Базовый, Инженер, Эксперт)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => ['Базовый', 'Инженер', 'Эксперт'],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            $allowed = ['Базовый', 'Инженер', 'Эксперт'];
            if (!in_array($val, $allowed, true)) {
                return new WP_Error(
                    'rest_invalid_param',
                    sprintf('Недопустимый уровень сложности "%s". Допустимые: %s', esc_html($val), implode(', ', $allowed)),
                    ['status' => 400]
                );
            }
            return true;
        },
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_solder_alloy', [
        'type'              => 'string',
        'description'       => 'Идентификатор припоя из реестра (pos61, sac305, roze, vuda, universal и др.)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => tchp_get_canonical_alloys(),
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            $normalized = tchp_normalize_solder_alloy($val);
            if (empty($normalized)) {
                $canonical = tchp_get_canonical_alloys();
                return new WP_Error(
                    'rest_invalid_param',
                    sprintf('Недопустимый припой "%s". Разрешенные канонические сплавы: %s', esc_html($val), implode(', ', $canonical)),
                    ['status' => 400]
                );
            }
            return true;
        },
        'sanitize_callback' => 'tchp_normalize_solder_alloy',
    ]);

    register_post_meta('post', 'tchp_temp_kind', [
        'type'              => 'string',
        'description'       => 'Тип температуры: station_setpoint, joint_temperature, reflow_profile',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => ['station_setpoint', 'joint_temperature', 'reflow_profile'],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            $allowed = ['station_setpoint', 'joint_temperature', 'reflow_profile'];
            if (!in_array($val, $allowed, true)) {
                return new WP_Error(
                    'rest_invalid_param',
                    sprintf('Недопустимый тип температуры "%s". Допустимые: %s', esc_html($val), implode(', ', $allowed)),
                    ['status' => 400]
                );
            }
            return true;
        },
        'sanitize_callback' => 'sanitize_key',
    ]);

    register_post_meta('post', 'tchp_temp_range', [
        'type'              => 'string',
        'description'       => 'Рабочий диапазон температур',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 100,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_temp_peak', [
        'type'              => 'string',
        'description'       => 'Пиковая допустимая температура',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 100,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard', [
        'type'              => 'string',
        'description'       => 'Обозначение стандарта (ГОСТ 21931, IPC-A-610G)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 120,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard_class', [
        'type'              => 'string',
        'description'       => 'Класс стандарта (КЛАСС 1, КЛАСС 2, КЛАСС 3)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 60,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    register_post_meta('post', 'tchp_standard_description', [
        'type'              => 'string',
        'description'       => 'Описание требований стандарта',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'      => 'string',
                'maxLength' => 500,
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'sanitize_callback' => 'sanitize_text_field',
    ]);

    // Структурированные массивы (Tools, FAQ, Sources) с запретом additionalProperties
    register_post_meta('post', 'tchp_tools', [
        'type'              => 'array',
        'description'       => 'Список необходимой оснастки и инструментов',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type'  => 'array',
                'items' => [
                    'type'                 => 'object',
                    'required'             => ['name'],
                    'properties'           => [
                        'name'    => ['type' => 'string', 'minLength' => 1, 'pattern' => '\\S', 'maxLength' => 150],
                        'model'   => ['type' => 'string', 'maxLength' => 100],
                        'purpose' => ['type' => 'string', 'maxLength' => 250],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            if (!is_array($val)) {
                return new WP_Error('rest_invalid_param', 'Поле tchp_tools должно быть массивом.', ['status' => 400]);
            }
            foreach ($val as $idx => $item) {
                if (!is_array($item) || empty($item['name']) || !is_string($item['name']) || trim($item['name']) === '') {
                    return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_tools[%d] должен содержать непустое свойство "name".', $idx), ['status' => 400]);
                }
                $allowed_keys = ['name', 'model', 'purpose'];
                foreach (array_keys($item) as $k) {
                    if (!in_array($k, $allowed_keys, true)) {
                        return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_tools[%d] содержит недопустимое свойство "%s".', $idx, esc_html($k)), ['status' => 400]);
                    }
                }
            }
            return true;
        },
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
                    'type'                 => 'object',
                    'required'             => ['q', 'a'],
                    'properties'           => [
                        'q' => ['type' => 'string', 'minLength' => 1, 'pattern' => '\\S', 'maxLength' => 250],
                        'a' => ['type' => 'string', 'minLength' => 1, 'pattern' => '\\S', 'maxLength' => 2000],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            if (!is_array($val)) {
                return new WP_Error('rest_invalid_param', 'Поле tchp_faq_items должно быть массивом.', ['status' => 400]);
            }
            foreach ($val as $idx => $item) {
                if (!is_array($item) || empty($item['q']) || empty($item['a']) || !is_string($item['q']) || !is_string($item['a']) || trim($item['q']) === '' || trim($item['a']) === '') {
                    return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_faq_items[%d] должен содержать непустые свойства "q" и "a".', $idx), ['status' => 400]);
                }
                $allowed_keys = ['q', 'a'];
                foreach (array_keys($item) as $k) {
                    if (!in_array($k, $allowed_keys, true)) {
                        return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_faq_items[%d] содержит недопустимое свойство "%s".', $idx, esc_html($k)), ['status' => 400]);
                    }
                }
            }
            return true;
        },
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
                    'type'                 => 'object',
                    'required'             => ['title'],
                    'properties'           => [
                        'title'    => ['type' => 'string', 'minLength' => 1, 'pattern' => '\\S', 'maxLength' => 200],
                        'standard' => ['type' => 'string', 'maxLength' => 120],
                        'url'      => ['type' => 'string', 'format' => 'uri'],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'auth_callback'     => $auth_edit_post,
        'validate_callback' => function ($val) {
            if (!is_array($val)) {
                return new WP_Error('rest_invalid_param', 'Поле tchp_sources должно быть массивом.', ['status' => 400]);
            }
            foreach ($val as $idx => $item) {
                if (!is_array($item) || empty($item['title']) || !is_string($item['title']) || trim($item['title']) === '') {
                    return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_sources[%d] должен содержать непустое свойство "title".', $idx), ['status' => 400]);
                }
                $allowed_keys = ['title', 'standard', 'url'];
                foreach (array_keys($item) as $k) {
                    if (!in_array($k, $allowed_keys, true)) {
                        return new WP_Error('rest_invalid_param', sprintf('Элемент tchp_sources[%d] содержит недопустимое свойство "%s".', $idx, esc_html($k)), ['status' => 400]);
                    }
                }
            }
            return true;
        },
        'sanitize_callback' => 'tchp_sanitize_sources_meta',
    ]);

    // Редакционные мета-поля
    register_post_meta('post', 'tchp_review_status', [
        'type'              => 'string',
        'description'       => 'Статус редакционной проверки (unreviewed, in_review, verified)',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => ['unreviewed', 'in_review', 'verified'],
            ],
        ],
        'auth_callback'     => function ($allowed, $meta_key, $post_id, $user_id) {
            return tchp_can_user_review_post($user_id, $post_id);
        },
        'validate_callback' => function ($val) {
            $allowed = ['unreviewed', 'in_review', 'verified'];
            if (!in_array($val, $allowed, true)) {
                return new WP_Error('rest_invalid_param', sprintf('tchp_review_status должно быть одним из: %s', implode(', ', $allowed)), ['status' => 400]);
            }
            return true;
        },
        'sanitize_callback' => function ($val) {
            $allowed = ['unreviewed', 'in_review', 'verified'];
            return in_array($val, $allowed, true) ? $val : 'unreviewed';
        },
    ]);

    // Серверные неизменяемые напрямую поля проверки (вычисляются только сервером)
    register_post_meta('post', 'tchp_reviewer_id', [
        'type'          => 'integer',
        'description'   => 'ID проверившего редактора (WP User ID)',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => $auth_server_only,
    ]);

    register_post_meta('post', 'tchp_reviewed_at', [
        'type'          => 'string',
        'description'   => 'Дата и время проверки в формате ISO 8601',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => $auth_server_only,
    ]);

    register_post_meta('post', 'tchp_reviewed_content_hash', [
        'type'          => 'string',
        'description'   => 'Серверный отпечаток проверенного содержимого статьи',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => $auth_server_only,
    ]);

    register_post_meta('post', 'tchp_review_outdated', [
        'type'          => 'string',
        'description'   => 'Флаг устаревания проверки после изменения контента (1 или пусто)',
        'single'        => true,
        'show_in_rest'  => true,
        'auth_callback' => $auth_server_only,
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
 * 4. Серверный расчет отпечатка проверяемого контента статьи
 * Включает: заголовок, текст, лид, инструменты, FAQ, источники, канонический припой,
 * температурный режим, стандарт, класс и ОПИСАНИЕ СТАНДАРТА.
 */
function tchp_calculate_article_hash($post_id) {
    if (is_array($post_id)) {
        $data     = $post_id;
        $title    = trim((string)($data['title'] ?? ''));
        $content  = trim((string)($data['content_html'] ?? ($data['content'] ?? '')));
        $excerpt  = trim((string)($data['excerpt'] ?? ''));

        $tools    = tchp_sanitize_tools_meta($data['tools'] ?? ($data['tchp_tools'] ?? []));
        $faq      = tchp_sanitize_faq_meta($data['faq'] ?? ($data['tchp_faq_items'] ?? []));
        $sources  = tchp_sanitize_sources_meta($data['sources'] ?? ($data['tchp_sources'] ?? []));

        $alloy    = tchp_normalize_solder_alloy((string)($data['solder_alloy'] ?? ($data['alloy'] ?? ($data['tchp_solder_alloy'] ?? ''))));
        $temp_data = is_array($data['temp_data'] ?? null) ? $data['temp_data'] : [];
        $t_kind   = (string)($temp_data['kind'] ?? ($data['t_kind'] ?? ($data['tchp_temp_kind'] ?? '')));
        $t_range  = (string)($temp_data['range'] ?? ($data['t_range'] ?? ($data['tchp_temp_range'] ?? '')));
        $t_peak   = (string)($temp_data['peak'] ?? ($data['t_peak'] ?? ($data['tchp_temp_peak'] ?? '')));

        $std_data = is_array($data['standard_data'] ?? null) ? $data['standard_data'] : [];
        $std      = (string)($std_data['standard'] ?? ($data['standard'] ?? ($data['tchp_standard'] ?? '')));
        $std_cls  = (string)($std_data['class'] ?? ($data['std_class'] ?? ($data['tchp_standard_class'] ?? '')));
        $std_desc = (string)($std_data['description'] ?? ($data['standard_desc'] ?? ($data['tchp_standard_description'] ?? '')));

        $fingerprint_data = [
            'title'         => $title,
            'content'       => $content,
            'excerpt'       => $excerpt,
            'tools'         => $tools,
            'faq'           => $faq,
            'sources'       => $sources,
            'alloy'         => $alloy,
            't_kind'        => $t_kind,
            't_range'       => $t_range,
            't_peak'        => $t_peak,
            'standard'      => $std,
            'std_class'     => $std_cls,
            'standard_desc' => $std_desc,
        ];

        return hash('sha256', json_encode($fingerprint_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

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

    $alloy     = tchp_normalize_solder_alloy((string) get_post_meta($post_id, 'tchp_solder_alloy', true));
    $t_kind    = (string) get_post_meta($post_id, 'tchp_temp_kind', true);
    $t_range   = (string) get_post_meta($post_id, 'tchp_temp_range', true);
    $t_peak    = (string) get_post_meta($post_id, 'tchp_temp_peak', true);
    $std       = (string) get_post_meta($post_id, 'tchp_standard', true);
    $std_cls   = (string) get_post_meta($post_id, 'tchp_standard_class', true);
    $std_desc  = (string) get_post_meta($post_id, 'tchp_standard_description', true);

    $fingerprint_data = [
        'title'         => $title,
        'content'       => $content,
        'excerpt'       => $excerpt,
        'tools'         => $tools,
        'faq'           => $faq,
        'sources'       => $sources,
        'alloy'         => $alloy,
        't_kind'        => $t_kind,
        't_range'       => $t_range,
        't_peak'        => $t_peak,
        'standard'      => $std,
        'std_class'     => $std_cls,
        'standard_desc' => $std_desc,
    ];

    return hash('sha256', json_encode($fingerprint_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * 5. Проверка и автоматический сброс устаревшей проверки при изменении контента
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
        // Контент изменился после верификации — помечаем как требующий повторной проверки
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
 * 6. Нормализаторы данных (с поддержкой миграции и различия «нет» vs «очищено»)
 */
function tchp_get_article_tools($post_id) {
    if (metadata_exists('post', $post_id, 'tchp_tools')) {
        $meta = get_post_meta($post_id, 'tchp_tools', true);
        if ($meta === '' || $meta === [] || $meta === false) {
            return [];
        }
        return tchp_sanitize_tools_meta($meta);
    }

    if (metadata_exists('post', $post_id, 'tchp_tools_needed')) {
        $needed = get_post_meta($post_id, 'tchp_tools_needed', true);
        if ($needed === '' || $needed === [] || $needed === false) {
            return [];
        }
        return tchp_sanitize_tools_meta($needed);
    }

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
 * Получить редакционные данные статьи (с полной проверкой 5 условий валидности)
 */
function tchp_get_editorial_data($post_id) {
    $status      = (string) get_post_meta($post_id, 'tchp_review_status', true) ?: 'unreviewed';
    $reviewer_id = (int) get_post_meta($post_id, 'tchp_reviewer_id', true);
    $reviewed_at = (string) get_post_meta($post_id, 'tchp_reviewed_at', true);
    $is_outdated = (bool) get_post_meta($post_id, 'tchp_review_outdated', true);
    $stored_hash = (string) get_post_meta($post_id, 'tchp_reviewed_content_hash', true);

    $reviewer_name = '';
    if ($reviewer_id > 0 && function_exists('get_userdata')) {
        $user = get_userdata($reviewer_id);
        if ($user) {
            $reviewer_name = $user->display_name ?: $user->user_login;
        }
    }

    $current_hash = tchp_calculate_article_hash($post_id);

    // 5 условий для утвержденной верификации:
    // 1) Статус строго 'verified'
    // 2) Сохраненный отпечаток не пустой
    // 3) ID проверившего редактора > 0
    // 4) Дата проверки заполнена
    // 5) Флаг устаревания не взведен И текущий отпечаток совпадает с проверенным
    $is_verified = (
        $status === 'verified' &&
        !empty($stored_hash) &&
        $reviewer_id > 0 &&
        !empty($reviewed_at) &&
        !$is_outdated &&
        ($current_hash === $stored_hash)
    );

    return [
        'status'        => $status,
        'reviewer_id'   => $reviewer_id,
        'reviewer_name' => $reviewer_name,
        'reviewed_at'   => $reviewed_at,
        'stored_hash'   => $stored_hash,
        'current_hash'  => $current_hash,
        'is_outdated'   => $is_outdated || ($status === 'verified' && $stored_hash !== $current_hash),
        'is_verified'   => $is_verified,
    ];
}

/**
 * 7. Определение владельца SEO Schema.org (Rank Math vs Theme)
 * Не отключает разметку темы, пока Rank Math фактически не активен и модуль rich-snippet не включен.
 */
function tchp_should_render_theme_schema($post_id = 0) {
    // 1. Проверяем официальный API Rank Math, если он доступен
    if (class_exists('\\RankMath\\Helper') && method_exists('\\RankMath\\Helper', 'is_module_active')) {
        if (\RankMath\Helper::is_module_active('rich-snippet')) {
            return false; // Модуль разметки активен — тему отключаем
        }
        return true; // Rank Math активен, но модуль rich-snippet отключен — тема выводит резерв
    }

    // 2. Фоллбек по опции rank_math_modules (в WP это обычный индексный список строк)
    if (class_exists('RankMath')) {
        $modules = get_option('rank_math_modules', []);
        if (is_array($modules) && in_array('rich-snippet', $modules, true)) {
            return false;
        }
        return true;
    }

    // 3. Плагин Rank Math не установлен/не активен — тема выводит резервную схему
    return true;
}

/**
 * 8. Выделенный REST API Endpoint для подтверждения редакционной проверки (ОТК)
 * POST /wp-json/tchp/v1/posts/{id}/verify
 */
add_action('rest_api_init', function () {
    register_rest_route('tchp/v1', '/posts/(?P<id>\d+)/verify', [
        'methods'             => 'POST',
        'callback'            => 'tchp_rest_verify_post',
        'permission_callback' => function ($request) {
            $post_id = is_object($request) && method_exists($request, 'get_param') ? (int) $request->get_param('id') : (int) ($request['id'] ?? 0);
            return tchp_can_user_review_post(get_current_user_id(), $post_id);
        },
        'args' => [
            'id' => [
                'validate_callback' => function ($param) {
                    return is_numeric($param) && get_post((int) $param);
                },
            ],
        ],
    ]);
});

function tchp_rest_verify_post($request) {
    $post_id = is_object($request) && method_exists($request, 'get_param') ? (int) $request->get_param('id') : (int) ($request['id'] ?? 0);
    $user_id = get_current_user_id();

    // 1. Проверка прав
    if (!tchp_can_user_review_post($user_id, $post_id)) {
        return new WP_Error('rest_forbidden', 'Недостаточно прав для подтверждения проверки.', ['status' => 403]);
    }

    // 2. Получение expected_hash
    $params = $request->get_json_params();
    if (!is_array($params)) {
        $params = $request->get_params();
    }
    $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : '';

    if (empty($expected_hash)) {
        return new WP_Error(
            'tchp_missing_expected_hash',
            'Параметр expected_hash обязателен для подтверждения редакции.',
            ['status' => 400]
        );
    }

    // 3. Расчет актуального отпечатка статьи
    $current_hash = tchp_calculate_article_hash($post_id);

    // 4. Сравнение с expected_hash (при несовпадении — 409 Conflict без изменения полей!)
    if ($expected_hash !== $current_hash) {
        return new WP_Error(
            'tchp_hash_conflict',
            sprintf('Версия статьи была изменена. Ожидался отпечаток %s, текущий: %s', $expected_hash, $current_hash),
            [
                'status'        => 409,
                'expected_hash' => $expected_hash,
                'current_hash'  => $current_hash,
            ]
        );
    }

    // 5. Отпечаток строго совпадает — применяем подтверждение
    update_post_meta($post_id, 'tchp_review_status', 'verified');
    update_post_meta($post_id, 'tchp_reviewer_id', $user_id);
    update_post_meta($post_id, 'tchp_reviewed_at', current_time('c'));
    delete_post_meta($post_id, 'tchp_review_outdated');
    update_post_meta($post_id, 'tchp_reviewed_content_hash', $current_hash);

    $editorial = tchp_get_editorial_data($post_id);
    return rest_ensure_response([
        'success'   => true,
        'post_id'   => $post_id,
        'hash'      => $current_hash,
        'editorial' => $editorial,
    ]);
}

/**
 * 9. Интерфейс управления полями в админке WordPress (Meta Boxes)
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
    $alloy_raw = trim((string) get_post_meta($post_id, 'tchp_solder_alloy', true));
    $alloy     = $alloy_raw !== '' ? tchp_normalize_solder_alloy($alloy_raw) : 'none';
    if ($alloy === '') {
        $alloy = 'none';
    }
    $t_kind    = get_post_meta($post_id, 'tchp_temp_kind', true) ?: 'joint_temperature';
    $t_range   = get_post_meta($post_id, 'tchp_temp_range', true) ?: '';
    $t_peak    = get_post_meta($post_id, 'tchp_temp_peak', true) ?: '';
    $temp_applicable = ($t_range !== '' || $t_peak !== '');
    $standard  = get_post_meta($post_id, 'tchp_standard', true) ?: '';
    $std_class = get_post_meta($post_id, 'tchp_standard_class', true) ?: '';
    $std_desc  = get_post_meta($post_id, 'tchp_standard_description', true) ?: '';
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
        <label for="tchp_solder_alloy">Припой (канонический реестр):</label>
        <select name="tchp_solder_alloy" id="tchp_solder_alloy">
          <option value="none" <?= selected($alloy, 'none', false) ?>>— не применимо (статья не про конкретный сплав) —</option>
          <option value="pos61" <?= selected($alloy, 'pos61', false) ?>>ПОС-61 (183–190 °C)</option>
          <option value="sn63pb37" <?= selected($alloy, 'sn63pb37', false) ?>>Sn63Pb37 (183 °C эвтектика)</option>
          <option value="pos40" <?= selected($alloy, 'pos40', false) ?>>ПОС-40 (183–238 °C)</option>
          <option value="sac305" <?= selected($alloy, 'sac305', false) ?>>SAC305 (217–220 °C)</option>
          <option value="sac405" <?= selected($alloy, 'sac405', false) ?>>SAC405 (217–220 °C)</option>
          <option value="sn42bi58" <?= selected($alloy, 'sn42bi58', false) ?>>Sn42Bi58 (138 °C)</option>
          <option value="snbiago" <?= selected($alloy, 'snbiago', false) ?>>SnBiAg (138–140 °C)</option>
          <option value="roze" <?= selected($alloy, 'roze', false) ?>>Сплав Розе (94 °C)</option>
          <option value="vuda" <?= selected($alloy, 'vuda', false) ?>>Сплав Вуда (68–72 °C)</option>
          <option value="universal" <?= selected($alloy, 'universal', false) ?>>Универсальный / Обзор регламента</option>
        </select>
      </div>

      <div class="tchp-meta-field" style="grid-column: 1 / -1;">
        <label style="display:flex; align-items:center; gap:6px; font-weight:600;">
          <input type="checkbox" name="tchp_temp_applicable" id="tchp_temp_applicable" value="1" <?= $temp_applicable ? 'checked' : '' ?>>
          Температурный режим применим к этой статье
        </label>
        <p class="description" style="margin:2px 0 0; font-size:11px;">Не для каждой темы: статьи про оборудование, химию или организацию места могут быть без температур.</p>
      </div>

      <div id="tchp-temp-fields" style="<?= $temp_applicable ? 'display: contents;' : 'display: none;' ?>">
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
      </div><!-- /#tchp-temp-fields -->

      <div class="tchp-meta-field">
        <label for="tchp_standard">Стандарт:</label>
        <input type="text" name="tchp_standard" id="tchp_standard" value="<?= esc_attr($standard) ?>" placeholder="напр. IPC-A-610G" list="tchp-standard-presets">
        <datalist id="tchp-standard-presets">
          <option value="IPC-A-610G"></option>
          <option value="J-STD-001"></option>
          <option value="J-STD-020E"></option>
          <option value="J-STD-004B"></option>
          <option value="IPC-7093"></option>
          <option value="IPC-7095D"></option>
          <option value="ГОСТ 21931-76"></option>
          <option value="ГОСТ 21930-76"></option>
          <option value="ANSI/ESD S20.20"></option>
          <option value="IEC 61010-1"></option>
        </datalist>
      </div>

      <div class="tchp-meta-field">
        <label for="tchp_standard_class">Класс стандарта:</label>
        <input type="text" name="tchp_standard_class" id="tchp_standard_class" value="<?= esc_attr($std_class) ?>" placeholder="напр. КЛАСС 2">
      </div>
    </div>

    <div class="tchp-meta-field" style="margin-bottom:16px;">
      <label for="tchp_standard_description">Описание требований стандарта:</label>
      <input type="text" name="tchp_standard_description" id="tchp_standard_description" value="<?= esc_attr($std_desc) ?>" style="width:100%;" placeholder="Краткое описание требований и допусков стандарта">
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
    ?>
    <div style="font-size:12px; line-height:1.5;">
      <?php if ($editorial['is_verified']): ?>
        <div style="background:#e6f4ea; color:#137333; padding:8px; border-radius:4px; font-weight:bold; margin-bottom:10px;">
          ✓ Утверждено ОТК (Все 5 условий соблюдены)
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
        <p style="margin:4px 0;"><b>Проверил:</b> <?= esc_html($editorial['reviewer_name']) ?> (ID: <?= (int)$editorial['reviewer_id'] ?>)</p>
      <?php endif; ?>
      <?php if (!empty($editorial['reviewed_at'])): ?>
        <p style="margin:4px 0;"><b>Дата проверки:</b> <?= esc_html(date_i18n('d.m.Y H:i', strtotime($editorial['reviewed_at']))) ?></p>
      <?php endif; ?>
      <?php if (!empty($editorial['stored_hash'])): ?>
        <p style="margin:4px 0; word-break:break-all;"><b>Хэш проверки:</b> <code style="font-size:10px;"><?= esc_html(substr($editorial['stored_hash'], 0, 16)) ?>…</code></p>
      <?php endif; ?>

      <hr style="margin:10px 0; border:0; border-top:1px solid #ddd;">

      <?php if ($can_review): ?>
        <div style="margin-bottom:8px;">
          <label for="tchp_review_status" style="display:block; font-weight:600; margin-bottom:4px;">Изменить рабочий статус:</label>
          <select name="tchp_review_status" id="tchp_review_status" style="width:100%;">
            <option value="unreviewed" <?= selected($editorial['status'], 'unreviewed', false) ?>>Не проверено (unreviewed)</option>
            <option value="in_review" <?= selected($editorial['status'], 'in_review', false) ?>>На проверке (in_review)</option>
            <?php if ($editorial['is_verified']): ?>
              <option value="verified" selected>Утверждено ОТК (verified)</option>
            <?php endif; ?>
          </select>
          <p class="description" style="margin:4px 0 0; font-size:11px;">Обычное сохранение статьи НЕ подтверждает верификацию.</p>
        </div>

        <div style="margin-top:12px; padding-top:10px; border-top:1px dashed #ccc;">
          <input type="hidden" name="tchp_expected_hash" value="<?= esc_attr($editorial['current_hash']) ?>">
          <button type="submit" name="tchp_action_verify_now" value="1" class="button button-primary" style="width:100%; text-align:center;">
            🛡️ Подтвердить проверку (ОТК)
          </button>
          <p class="description" style="margin:4px 0 0; font-size:11px; color:#137333;">
            Подтверждает текущую сохранённую редакцию статьи.
          </p>
        </div>
      <?php else: ?>
        <p class="description" style="color:#d93025;">У вас нет права <code>tchp_review_content</code> для утверждения проверки статьи.</p>
      <?php endif; ?>
    </div>
    <?php
}

/**
 * 10. Сохранение данных метабоксов из панели админки
 * Разделение «Сохранить черновик / запись» и «Подтвердить редакционную проверку».
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
            $raw_alloy = sanitize_text_field(wp_unslash((string) $_POST['tchp_solder_alloy']));
            if ($raw_alloy === 'none' || $raw_alloy === '') {
                delete_post_meta($post_id, 'tchp_solder_alloy');
            } else {
                $norm = tchp_normalize_solder_alloy($raw_alloy);
                if ($norm !== '') {
                    update_post_meta($post_id, 'tchp_solder_alloy', $norm);
                }
            }
        }
        $temp_applicable = !empty($_POST['tchp_temp_applicable']);
        if ($temp_applicable) {
            if (isset($_POST['tchp_temp_kind'])) {
                update_post_meta($post_id, 'tchp_temp_kind', sanitize_key($_POST['tchp_temp_kind']));
            }
            if (isset($_POST['tchp_temp_range'])) {
                update_post_meta($post_id, 'tchp_temp_range', sanitize_text_field($_POST['tchp_temp_range']));
            }
            if (isset($_POST['tchp_temp_peak'])) {
                update_post_meta($post_id, 'tchp_temp_peak', sanitize_text_field($_POST['tchp_temp_peak']));
            }
        } else {
            delete_post_meta($post_id, 'tchp_temp_kind');
            delete_post_meta($post_id, 'tchp_temp_range');
            delete_post_meta($post_id, 'tchp_temp_peak');
        }
        if (isset($_POST['tchp_standard'])) {
            update_post_meta($post_id, 'tchp_standard', sanitize_text_field($_POST['tchp_standard']));
        }
        if (isset($_POST['tchp_standard_class'])) {
            update_post_meta($post_id, 'tchp_standard_class', sanitize_text_field($_POST['tchp_standard_class']));
        }
        if (isset($_POST['tchp_standard_description'])) {
            update_post_meta($post_id, 'tchp_standard_description', sanitize_text_field($_POST['tchp_standard_description']));
        }

        // Инструменты: если переданы — санитизируем, если нет — сохраняем пустой массив (намеренно очищены!)
        $tools = isset($_POST['tchp_tools']) ? tchp_sanitize_tools_meta($_POST['tchp_tools']) : [];
        update_post_meta($post_id, 'tchp_tools', $tools);

        // FAQ
        $faq = isset($_POST['tchp_faq']) ? tchp_sanitize_faq_meta($_POST['tchp_faq']) : [];
        update_post_meta($post_id, 'tchp_faq_items', $faq);

        // Источники
        $sources = isset($_POST['tchp_sources']) ? tchp_sanitize_sources_meta($_POST['tchp_sources']) : [];
        update_post_meta($post_id, 'tchp_sources', $sources);
    }

    // Сохранение редакционной проверки (только при наличии прав)
    if (isset($_POST['tchp_editorial_nonce']) && wp_verify_nonce($_POST['tchp_editorial_nonce'], 'tchp_save_editorial_box')) {
        if (tchp_can_user_review_post(get_current_user_id(), $post_id)) {
            // Случай 1: Явное подтверждение проверки кнопкой
            if (!empty($_POST['tchp_action_verify_now'])) {
                $expected_hash = isset($_POST['tchp_expected_hash']) ? sanitize_text_field($_POST['tchp_expected_hash']) : '';
                $current_hash  = tchp_calculate_article_hash($post_id);

                if (!empty($expected_hash) && $expected_hash === $current_hash) {
                    update_post_meta($post_id, 'tchp_review_status', 'verified');
                    update_post_meta($post_id, 'tchp_reviewer_id', get_current_user_id());
                    update_post_meta($post_id, 'tchp_reviewed_at', current_time('c'));
                    delete_post_meta($post_id, 'tchp_review_outdated');
                    update_post_meta($post_id, 'tchp_reviewed_content_hash', $current_hash);
                } else {
                    // Контент изменился или не сохранен перед подтверждением — отправляем на ревизию
                    update_post_meta($post_id, 'tchp_review_status', 'in_review');
                    update_post_meta($post_id, 'tchp_review_outdated', '1');
                }
            } else {
                // Случай 2: Обычное сохранение — обновляем только не-verified статусы
                $req_status = isset($_POST['tchp_review_status']) ? sanitize_key($_POST['tchp_review_status']) : '';
                if ($req_status === 'unreviewed' || $req_status === 'in_review') {
                    update_post_meta($post_id, 'tchp_review_status', $req_status);
                }
            }
        }
    }
});

/**
 * 11. UX паспорта статьи: автоподстановка температур по сплаву,
 * скрытие температурных полей для «не применимо» и колонка GEO в списке записей.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'post') {
        return;
    }
    wp_register_script('tchp-geo-admin', '', [], TCHP_CONTENT_VERSION, true);
    wp_enqueue_script('tchp-geo-admin');

    $presets = [
        'pos61'     => ['range' => '260–295 °C', 'peak' => '320 °C'],
        'sn63pb37'  => ['range' => '260–290 °C', 'peak' => '300 °C'],
        'pos40'     => ['range' => '280–330 °C', 'peak' => '350 °C'],
        'sac305'    => ['range' => '310–350 °C', 'peak' => '245 °C'],
        'sac405'    => ['range' => '310–350 °C', 'peak' => '245 °C'],
        'sn42bi58'  => ['range' => '200–230 °C', 'peak' => '190 °C'],
        'snbiago'   => ['range' => '200–230 °C', 'peak' => '190 °C'],
        'roze'      => ['range' => '140–180 °C', 'peak' => ''],
        'vuda'      => ['range' => '90–120 °C',  'peak' => ''],
    ];
    wp_add_inline_script('tchp-geo-admin', 'window.TCHP_GEO_PRESETS = ' . wp_json_encode($presets, JSON_UNESCAPED_UNICODE) . ';');

    $js = <<<'JS'
(function () {
  var D = window.TCHP_GEO_PRESETS || {};
  function init() {
    var alloy = document.getElementById('tchp_solder_alloy');
    var toggle = document.getElementById('tchp_temp_applicable');
    var fields = document.getElementById('tchp-temp-fields');
    var range = document.getElementById('tchp_temp_range');
    var peak = document.getElementById('tchp_temp_peak');
    if (!alloy || !toggle || !fields) { return false; }
    function visibility() { fields.style.display = toggle.checked ? 'contents' : 'none'; }
    function preset() {
      var p = D[alloy.value];
      if (!p) { return; }
      if (range && !range.value.trim()) { range.value = p.range || ''; }
      if (peak && !peak.value.trim()) { peak.value = p.peak || ''; }
    }
    toggle.addEventListener('change', function () { visibility(); if (toggle.checked) { preset(); } });
    alloy.addEventListener('change', function () {
      toggle.checked = (alloy.value !== 'none');
      visibility();
      if (toggle.checked) { preset(); }
    });
    visibility();
    if (toggle.checked) { preset(); }
    return true;
  }
  function boot() {
    if (init()) { return; }
    var tries = 0;
    var timer = setInterval(function () {
      if (init() || ++tries > 20) { clearInterval(timer); }
    }, 250);
  }
  if (document.readyState !== 'loading') { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }
})();
JS;
    wp_add_inline_script('tchp-geo-admin', $js);
});

add_filter('manage_post_posts_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['tchp_geo'] = 'GEO';
        }
    }
    return $new;
});

add_action('manage_post_posts_custom_column', function ($column, $post_id) {
    if ($column !== 'tchp_geo') {
        return;
    }
    $score = 0;
    $missing = [];
    $alloy = trim((string) get_post_meta($post_id, 'tchp_solder_alloy', true));
    if ($alloy !== '') { $score++; } else { $missing[] = 'припой'; }
    if (trim((string) get_post_meta($post_id, 'tchp_temp_range', true)) !== ''
        || trim((string) get_post_meta($post_id, 'tchp_temp_peak', true)) !== '') {
        $score++;
    } else { $missing[] = 'температуры'; }
    if (trim((string) get_post_meta($post_id, 'tchp_standard', true)) !== '') { $score++; } else { $missing[] = 'стандарт'; }
    $sources = function_exists('tchp_get_article_sources') ? tchp_get_article_sources($post_id) : [];
    if (!empty($sources)) {
        $score++;
        if (count($sources) < 2) {
            $missing[] = 'источники (рекомендуется ≥2)';
        }
    } else {
        $missing[] = 'источники (≥2)';
    }

    $faq = function_exists('tchp_get_article_faq') ? tchp_get_article_faq($post_id) : [];
    if (!empty($faq)) {
        $score++;
        if (count($faq) < 4) {
            $missing[] = 'FAQ (рекомендуется 4–6)';
        }
    } else {
        $missing[] = 'FAQ (4–6)';
    }

    $editorial = function_exists('tchp_get_editorial_data') ? tchp_get_editorial_data($post_id) : null;
    if (!empty($editorial['is_verified'])) { $score++; } else { $missing[] = 'ОТК'; }
    $color = $score >= 6 ? '#137333' : ($score >= 3 ? '#b06000' : '#d93025');
    $title = empty($missing) ? 'Все GEO-поля заполнены' : 'Не заполнено: ' . implode(', ', $missing);
    printf(
        '<span style="display:inline-block;min-width:32px;text-align:center;padding:2px 6px;border-radius:10px;background:%s;color:#fff;font-weight:600;" title="%s">%d/6</span>',
        esc_attr($color),
        esc_attr($title),
        (int) $score
    );
}, 10, 2);
