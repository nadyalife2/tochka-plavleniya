<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

// Map of canonical categories according to Information Architecture
$RUBRICS = [
    'start' => [
        'title'       => 'Начать паять',
        'badge'       => '01 // СТАРТ И БАЗА',
        'desc'        => 'Первые шаги в радиомонтаже, базовые правила физики пайки, организация безопасного рабочего места и термопрофили.',
        'filter_tags' => ['basics'],
        'cta' => [
            'icon'  => 'thermometer',
            'title' => 'Термокалькулятор монтажника',
            'desc'  => 'Не уверены, какую температуру выставить на станции? Подберите безопасный режим под ваш припой и провод/плату.',
            'link'  => '/interactive.php#temp',
            'label' => 'Подобрать температуру →'
        ]
    ],
    'materialy' => [
        'title'       => 'Материалы и сплавы',
        'badge'       => '02 // ХИМИЯ И МЕТАЛЛУРГИЯ',
        'desc'        => 'Химия флюсов (RMA, no-clean, водосмываемые), свойства припоев (ПОС-61, SAC305, Розе), паяльные пасты и отмывка.',
        'filter_tags' => ['materials'],
        'cta' => [
            'icon'  => 'drop',
            'title' => 'Реестр припоев и расчет флюса',
            'desc'  => 'Справочник точек плавления сплавов по ГОСТ и калькулятор расхода дозировки флюса на см² платы.',
            'link'  => '/interactive.php#solder',
            'label' => 'Открыть реестр сплавов →'
        ]
    ],
    'praktika' => [
        'title'       => 'Практика и монтаж',
        'badge'       => '03 // ВЕРСТАК И ТЕХНИКА',
        'desc'        => 'Техника монтажа SMD (0402–1206), QFN, BGA-реболлинг, прогрев полигонов и выбор геометрии жал (T12 vs C245).',
        'filter_tags' => ['smd', 'tools'],
        'cta' => [
            'icon'  => 'tools',
            'title' => 'Подбор паяльника под задачу',
            'desc'  => 'Конфигуратор инструмента: выберите ваши задачи, частоту работ и бюджет для подбора оптимальной паяльной станции.',
            'link'  => '/interactive.php#iron',
            'label' => 'Подобрать паяльник →'
        ]
    ],
    'oshibki' => [
        'title'       => 'Проблемы и дефекты',
        'badge'       => '04 // ДИАГНОСТИКА БРАКА',
        'desc'        => 'Холодная пайка, перегрев дорожек, паразитные перемычки, трещины в галтелях шва и реанимация высохшей пасты.',
        'filter_tags' => ['basics', 'materials', 'smd'],
        'article_ids' => [1, 7, 8, 12], // статьи, посвященные устранению ошибок
        'cta' => [
            'icon'  => 'warning',
            'title' => 'Интерактивное дерево диагностики дефектов',
            'desc'  => 'Припой сворачивается в шарик? Отваливается дорожка? Пройдите по симптомам и получите причину и решение за 3 клика.',
            'link'  => '/interactive.php#defect',
            'label' => 'Диагностировать ошибку →'
        ]
    ]
];

// Compatibility: slug or tag
$slug = $_GET['slug'] ?? null;
$tag_param = $_GET['tag'] ?? null;

if ($slug && isset($RUBRICS[$slug])) {
    $current_rubric = $RUBRICS[$slug];
    $current_slug   = $slug;
    $current_page   = $slug;
    $page_title     = $current_rubric['title'] . ' — Журнал ТОЧКА ПЛАВЛЕНИЯ';
    
    // Filter articles
    if (isset($current_rubric['article_ids'])) {
        $filtered_articles = array_values(array_filter($articles, fn($a) => in_array($a['id'], $current_rubric['article_ids'])));
    } else {
        $filtered_articles = array_values(array_filter($articles, fn($a) => in_array($a['tag_key'], $current_rubric['filter_tags'])));
    }
} elseif ($tag_param) {
    $current_slug = $tag_param;
    $current_page = 'category';
    $tag_names = ['basics' => 'Основы', 'smd' => 'SMD', 'tools' => 'Инструменты', 'materials' => 'Материалы', 'all' => 'Все статьи'];
    $tag_title = $tag_names[$tag_param] ?? 'Материалы';
    $page_title = 'Статьи: ' . $tag_title . ' — ТОЧКА ПЛАВЛЕНИЯ';
    
    $current_rubric = [
        'title' => 'Статьи: ' . $tag_title,
        'badge' => 'МАТЕРИАЛЫ ЖУРНАЛА',
        'desc'  => 'Практические статьи и инженерные руководства по тегу «' . $tag_title . '».',
        'cta'   => null
    ];
    $filtered_articles = get_articles_by_tag($tag_param);
} else {
    // Default fallback to "start"
    $current_rubric = $RUBRICS['start'];
    $current_slug   = 'start';
    $current_page   = 'start';
    $page_title     = $current_rubric['title'] . ' — Журнал ТОЧКА ПЛАВЛЕНИЯ';
    $filtered_articles = array_values(array_filter($articles, fn($a) => in_array($a['tag_key'], $current_rubric['filter_tags'])));
}

$page_num = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$paginated = paginate($filtered_articles, 6, $page_num);

include __DIR__ . '/includes/header.php';
?>

<div class="article-hero" style="background:var(--accent-yellow); margin-bottom:2rem; position:relative;">
    <span class="tag"><?= e($current_rubric['badge']) ?></span>
    <h1 class="wavy-underline" style="margin-top:0.5rem; margin-bottom:0.75rem;"><?= e($current_rubric['title']) ?></h1>
    <p style="font-family:var(--font-mono); font-weight:600; max-width:720px; line-height:1.5;">
        <?= e($current_rubric['desc']) ?>
    </p>
</div>

<?php if (!empty($current_rubric['cta'])): ?>
    <!-- Interactive Tool Quick Shortcut Callout -->
    <div style="background:var(--bg-card); border:2px solid var(--border); border-radius:10px; padding:1.25rem 1.5rem; margin-bottom:2rem; box-shadow:3px 3px 0px var(--shadow-color); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
        <div style="max-width:650px;">
            <div style="font-family:var(--font-mono); font-size:0.8rem; font-weight:700; color:var(--accent-orange); text-transform:uppercase; margin-bottom:0.25rem;">
                ★ Рекомендуемый интерактивный инструмент раздела
            </div>
            <div style="font-size:1.1rem; font-weight:800; font-family:var(--font-sans); margin-bottom:0.25rem;">
                <?= e($current_rubric['cta']['title']) ?>
            </div>
            <div style="font-size:0.9rem; color:var(--text-muted, #666); font-family:var(--font-sans);">
                <?= e($current_rubric['cta']['desc']) ?>
            </div>
        </div>
        <div>
            <a href="<?= e($current_rubric['cta']['link']) ?>" class="btn-primary" style="display:inline-flex; align-items:center; gap:0.5rem; background:var(--accent-orange); color:#fff; font-family:var(--font-mono); font-weight:700; font-size:0.88rem; padding:0.65rem 1.1rem; border-radius:6px; text-decoration:none; box-shadow:2px 2px 0px var(--shadow-color); border:2px solid var(--border); white-space:nowrap;">
                <?= e($current_rubric['cta']['label']) ?>
            </a>
        </div>
    </div>
<?php endif; ?>

<!-- Quick Tag Filter Pills -->
<div class="filters" style="margin-bottom:2rem; display:flex; gap:0.6rem; flex-wrap:wrap;">
    <?php
    $nav_pills = [
        ['slug' => 'start',     'label' => 'Начать паять'],
        ['slug' => 'materialy', 'label' => 'Материалы'],
        ['slug' => 'praktika',  'label' => 'Практика'],
        ['slug' => 'oshibki',   'label' => 'Проблемы'],
    ];
    foreach ($nav_pills as $pill):
        $is_curr = ($current_slug === $pill['slug']);
    ?>
        <a href="/category.php?slug=<?= $pill['slug'] ?>"
           class="tag <?= $is_curr ? 'active' : '' ?>"
           style="text-decoration:none; padding:0.4rem 0.9rem; font-family:var(--font-mono); font-size:0.85rem; font-weight:700; border-radius:6px; border:2px solid var(--border); <?= $is_curr ? 'background:var(--accent-orange); color:#fff;' : 'background:var(--bg-card); color:var(--text-main);' ?>">
            <?= $pill['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Articles Grid -->
<div class="grid">
    <?php if (empty($paginated['items'])): ?>
        <div style="grid-column:1/-1; padding:3rem 1rem; text-align:center; font-family:var(--font-mono);">
            <p>В этой рубрике готовятся новые материалы. Загляните в <a href="/interactive.php" style="color:var(--accent-orange); font-weight:bold;">интерактивный верстак</a>!</p>
        </div>
    <?php else: ?>
        <?php foreach ($paginated['items'] as $article): ?>
            <?php $article_url = "/article.php?slug=" . urlencode($article['slug']); ?>
            <article class="card card-half" data-tag="<?= e($article['tag_key']) ?>">
                <div class="card-img">
                    <?= get_card_icon($article['icon'] ?? 'chip') ?>
                </div>
                <span class="tag"><?= e($article['tag']) ?></span>
                <h3><a href="<?= $article_url ?>"><?= e($article['title']) ?></a></h3>
                <p><?= e($article['excerpt']) ?></p>
                <a href="<?= $article_url ?>" class="read-more">Читать статью →</a>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- PAGINATION -->
<?php 
$base_pag_url = $slug ? "/category.php?slug=" . urlencode($slug) : "/category.php?tag=" . urlencode($tag_param ?? 'basics');
echo render_pagination($paginated, $base_pag_url); 
?>

<?php include __DIR__ . '/includes/footer.php'; ?>
