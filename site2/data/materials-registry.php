<?php
/**
 * materials-registry.php — Единый источник правды по материалам ТЧП
 * Заменяет solder-reference.php с учётом замечаний аудита от 14.09.2026.
 */

$MATERIALS_REGISTRY = [
    'solders' => [
        // ── СВИНЕЦСОДЕРЖАЩИЕ ────────────────────────────────────────────────────
        'pos61' => [
            'id'         => 'pos61',
            'name'       => 'ПОС-61',
            'std'        => 'ГОСТ 21931-76', // Состав по ГОСТ 21930-76, сортамент по 21931-76
            'type'       => 'leaded',
            'sn_pct'     => 61,
            'pb_pct'     => 39,
            'ag_pct'     => 0,
            'bi_pct'     => 0,
            'cu_pct'     => 0,
            'other'      => '',
            't_melt_min' => 183,
            't_melt_max' => 190,
            't_work_min' => 240,
            't_work_max' => 320,
            'wet'        => 'excellent',
            'joints'     => 'bright',
            'uses'       => [
                'Монтаж и ремонт электронных плат',
                'SMD-компоненты (0402 и крупнее)',
                'Провода и кабели',
            ],
            'warnings'   => [
                'Содержит свинец — работайте с вентиляцией и мойте руки',
            ],
            'notes' => 'Классический припой. Не путать с Sn63Pb37.',
            'source' => 'Техлист Изагри',
        ],

        'sn63pb37' => [
            'id'         => 'sn63pb37',
            'name'       => 'Sn63Pb37',
            'std'        => 'J-STD-006C',
            'type'       => 'leaded',
            'sn_pct'     => 63,
            'pb_pct'     => 37,
            'ag_pct'     => 0,
            'bi_pct'     => 0,
            'cu_pct'     => 0,
            'other'      => '',
            't_melt_min' => 183,
            't_melt_max' => 183, // эвтектика
            't_work_min' => 240,
            't_work_max' => 320,
            'wet'        => 'excellent',
            'joints'     => 'bright',
            'uses'       => [
                'Прецизионная пайка',
                'BGA-реболлинг (свинцовый)',
            ],
            'warnings'   => [
                'Содержит свинец — работайте с вентиляцией',
            ],
            'notes' => 'Эвтектический состав.',
            'source' => 'Стандарты IPC',
        ],

        'pos40' => [
            'id'         => 'pos40',
            'name'       => 'ПОС-40',
            'std'        => 'ГОСТ 21931-76 / Sn40Pb60',
            'type'       => 'leaded',
            'sn_pct'     => 40,
            'pb_pct'     => 60,
            'ag_pct'     => 0,
            'bi_pct'     => 0,
            'cu_pct'     => 0,
            'other'      => '',
            't_melt_min' => 183,
            't_melt_max' => 238,
            't_work_min' => 270,
            't_work_max' => 340,
            'wet'        => 'good',
            'joints'     => 'dull',
            'uses'       => [
                'Пайка проводов',
                'Лужение медных шин',
            ],
            'warnings'   => [
                'Содержит свинец',
                'Широкая пастообразная зона',
            ],
            'notes' => 'Дешевле, хуже смачивает мелкие выводы.',
        ],

        // ── БЕССВИНЦОВЫЕ ────────────────────────────────────────────────────────
        'sac305' => [
            'id'         => 'sac305',
            'name'       => 'SAC305',
            'std'        => 'IPC J-STD-006C',
            'type'       => 'leadfree',
            'sn_pct'     => 96.5,
            'pb_pct'     => 0,
            'ag_pct'     => 3.0,
            'bi_pct'     => 0,
            'cu_pct'     => 0.5,
            'other'      => '',
            't_melt_min' => 217,
            't_melt_max' => 220, // Kester SAC305
            't_work_min' => 260,
            't_work_max' => 340,
            'wet'        => 'good',
            'joints'     => 'dull',
            'uses'       => [
                'Промышленный монтаж RoHS-изделий',
                'BGA-реболлинг',
            ],
            'warnings'   => [
                'Требует документального подтверждения RoHS для изделия',
                'Хуже смачивает по сравнению с ПОС-61',
            ],
            'notes' => 'Де-факто стандарт бессвинцовой промышленной пайки. Матовый шов — норма.',
            'source' => 'Kester SAC305 TDS',
        ],

        'sac405' => [
            'id'         => 'sac405',
            'name'       => 'SAC405',
            'std'        => 'Sn95.5Ag4Cu0.5',
            'type'       => 'leadfree',
            'sn_pct'     => 95.5,
            'pb_pct'     => 0,
            'ag_pct'     => 4.0,
            'bi_pct'     => 0,
            'cu_pct'     => 0.5,
            'other'      => '',
            't_melt_min' => 217,
            't_melt_max' => 220,
            't_work_min' => 265,
            't_work_max' => 345,
            'wet'        => 'good',
            'joints'     => 'dull',
            'uses'       => [
                'RoHS-совместимые платы',
            ],
            'warnings'   => [
                'Требует документального подтверждения RoHS для изделия',
            ],
            'notes' => 'Немного лучше смачиваемость за счёт большего содержания Ag.',
        ],

        // ── НИЗКОТЕМПЕРАТУРНЫЕ ──────────────────────────────────────────────────
        'sn42bi58' => [
            'id'         => 'sn42bi58',
            'name'       => 'Sn42Bi58',
            'std'        => 'Sn42Bi58',
            'type'       => 'lowtemp',
            'sn_pct'     => 42,
            'pb_pct'     => 0,
            'ag_pct'     => 0,
            'bi_pct'     => 58,
            'cu_pct'     => 0,
            'other'      => '',
            't_melt_min' => 138,
            't_melt_max' => 138,
            't_work_min' => 155,
            't_work_max' => 200,
            'wet'        => 'fair',
            'joints'     => 'bright',
            'uses'       => [
                'Пайка термочувствительных компонентов',
            ],
            'warnings'   => [
                'Хрупкость выше, чем у Sn-Pb',
                'Не смешивать с SAC — образуется непредвиденная фаза',
            ],
            'notes' => 'Хорош для термочувствительных компонентов.',
        ],

        'snbiago' => [
            'id'         => 'snbiago',
            'name'       => 'Sn42Bi57.6Ag0.4',
            'std'        => 'Sn42Bi57.6Ag0.4',
            'type'       => 'lowtemp',
            'sn_pct'     => 42,
            'pb_pct'     => 0,
            'ag_pct'     => 0.4,
            'bi_pct'     => 57.6,
            'cu_pct'     => 0,
            'other'      => '',
            't_melt_min' => 137,
            't_melt_max' => 140,
            't_work_min' => 155,
            't_work_max' => 195,
            'wet'        => 'fair',
            'joints'     => 'bright',
            'uses'       => [
                'Ремонт вблизи термочувствительных зон',
            ],
            'warnings'   => [
                'Хрупкость, как у Sn42Bi58',
            ],
            'notes' => 'Улучшенная версия Sn42Bi58: небольшое добавление Ag снижает хрупкость.',
            'source' => 'Chip Quik SMDLTLFP',
        ],

        // ── СПЕЦИАЛЬНЫЕ / ЛЕГКОПЛАВКИЕ ──────────────────────────────────────────
        'roze' => [
            'id'         => 'roze',
            'name'       => 'Сплав Розе',
            'std'        => 'Sn25Pb25Bi50 (приблизительно)',
            'type'       => 'special',
            'sn_pct'     => 25,
            'pb_pct'     => 25,
            'ag_pct'     => 0,
            'bi_pct'     => 50,
            'cu_pct'     => 0,
            'other'      => 'состав варьируется у производителей',
            't_melt_min' => 93,
            't_melt_max' => 96,
            't_work_min' => 110,
            't_work_max' => 140,
            'wet'        => 'fair',
            'joints'     => 'dull',
            'uses'       => [
                'Демонтаж компонентов',
            ],
            'warnings'   => [
                'Содержит свинец и висмут — крайне хрупкий шов',
                'После применения полностью удалите сплав',
            ],
            'notes' => 'Инструмент мастера для демонтажа, а не для пайки.',
        ],

        'vuda' => [
            'id'         => 'vuda',
            'name'       => 'Сплав Вуда',
            'std'        => 'Bi50Pb26.7Sn13.3Cd10',
            'type'       => 'special',
            'sn_pct'     => 13,
            'pb_pct'     => 27,
            'ag_pct'     => 0,
            'bi_pct'     => 50,
            'cu_pct'     => 0,
            'other'      => 'Cd 10% — кадмий, канцероген 1-й группы',
            't_melt_min' => 68,
            't_melt_max' => 72,
            't_work_min' => 80,
            't_work_max' => 100,
            'wet'        => 'poor',
            'joints'     => 'dull',
            'uses'       => [
                'Исторически: демонтаж',
            ],
            'warnings'   => [
                'СОДЕРЖИТ КАДМИЙ — канцероген',
                'Не рекомендуется для бытового применения и новичков',
            ],
            'notes' => 'Токсичность кадмия исключает бытовое применение.',
        ],
    ]
];

/**
 * Возвращает запись припоя по id.
 */
function get_solder(string $id): ?array {
    global $MATERIALS_REGISTRY;
    return $MATERIALS_REGISTRY['solders'][$id] ?? null;
}

/**
 * Возвращает все припои определённого типа.
 */
function get_solders_by_type(string $type = 'all'): array {
    global $MATERIALS_REGISTRY;
    if ($type === 'all') return $MATERIALS_REGISTRY['solders'];
    return array_filter($MATERIALS_REGISTRY['solders'], fn($s) => $s['type'] === $type);
}

/**
 * Возвращает метку типа на русском.
 */
function solder_type_label(string $type): string {
    return match($type) {
        'leaded'   => 'Свинецсодержащий',
        'leadfree' => 'Бессвинцовый (RoHS)',
        'lowtemp'  => 'Низкотемпературный',
        'special'  => 'Специальный',
        default    => $type,
    };
}
