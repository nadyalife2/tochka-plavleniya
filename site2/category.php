<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

// Canonical Information Architecture Rubrics Configuration
$RUBRICS = [
    'materialy' => [
        'title'       => 'Материалы и сплавы',
        'badge'       => '02 // РУБРИКА ЖУРНАЛА',
        'subbadge'    => 'МАТЕРИАЛЫ И СПЛАВЫ',
        'lead'        => 'Разбираем металлургию и химию радиомонтажа: свойства припоев (ПОС-61, SAC305, сплав Розе), классификацию флюсов (RMA, No-Clean, водосмывные), реанимацию паяльных паст и паразитные токи утечки.',
        'filter_tags' => ['materials'],
        'article_ids' => [3, 7, 6, 12, 11],
        'featured_id' => 3,
        'cta' => [
            'icon'  => 'science',
            'title' => 'Реестр припоев и калькулятор расхода флюса',
            'desc'  => 'Расчёт дозировки флюса и паяльной пасты по площади платы (IPC-7095C) и справочник температур ликвидуса 9 марок сплавов по ГОСТ 21931.',
            'link1' => '/interactive.php#calculator',
            'lbl1'  => 'Калькулятор расхода флюса →',
            'link2' => '/interactive.php#table',
            'lbl2'  => 'Таблица сплавов'
        ]
    ],
    'start' => [
        'title'       => 'Начать паять',
        'badge'       => '01 // РУБРИКА ЖУРНАЛА',
        'subbadge'    => 'СТАРТ И БАЗА',
        'lead'        => 'Первые шаги в радиомонтаже, базовые принципы смачиваемости, выбор первого оборудования и безопасная организация рабочего места монтажника.',
        'filter_tags' => ['basics'],
        'article_ids' => [1, 8, 11],
        'featured_id' => 1,
        'cta' => [
            'icon'  => 'device_thermostat',
            'title' => 'Термокалькулятор монтажника',
            'desc'  => 'Подберите безопасное тепловое окно жала паяльной станции под марку припоя и тип соединяемых проводников.',
            'link1' => '/interactive.php#temp',
            'lbl1'  => 'Подобрать температуру →',
            'link2' => '/interactive.php#iron',
            'lbl2'  => 'Выбрать паяльник'
        ]
    ],
    'praktika' => [
        'title'       => 'Практика и монтаж',
        'badge'       => '03 // РУБРИКА ЖУРНАЛА',
        'subbadge'    => 'ПРАКТИКА И ТЕХНИКА',
        'lead'        => 'Техника ручного монтажа SMD 0402–1206, пайка безвыводных микросхем QFN, BGA-реболлинг, прогрев массивных полигонов и выбор геометрии жал.',
        'filter_tags' => ['smd', 'tools'],
        'article_ids' => [2, 4, 5, 9, 10],
        'featured_id' => 4,
        'cta' => [
            'icon'  => 'construction',
            'title' => 'Конфигуратор паяльного оборудования',
            'desc'  => 'Спецификация инструмента под ваши задачи: выбор класса станции (T12, C245, USB-PD) и комплекта жал под бюджет.',
            'link1' => '/interactive.php#iron',
            'lbl1'  => 'Сконфигурировать верстак →',
            'link2' => '/interactive.php#temp',
            'lbl2'  => 'Терморежимы'
        ]
    ],
    'oshibki' => [
        'title'       => 'Проблемы и дефекты',
        'badge'       => '04 // РУБРИКА ЖУРНАЛА',
        'subbadge'    => 'ДИАГНОСТИКА БРАКА',
        'lead'        => 'Диагностика и устранение типового брака пайки: холодный зернистый шов, паразитные перемычки, отслоение медных дорожек и tombstoning.',
        'filter_tags' => ['basics', 'materials', 'smd'],
        'article_ids' => [1, 7, 8, 12],
        'featured_id' => 7,
        'cta' => [
            'icon'  => 'search',
            'title' => 'Интерактивное дерево диагностики дефектов',
            'desc'  => 'Интерактивный определитель первопричин брака с пошаговыми действиями по устранению дефекта прямо на плате.',
            'link1' => '/interactive.php#defect',
            'lbl1'  => 'Диагностировать дефект →',
            'link2' => '/interactive.php#temp',
            'lbl2'  => 'Проверить температуру'
        ]
    ]
];

// Determine active slug or tag
$slug = $_GET['slug'] ?? null;
$tag_param = $_GET['tag'] ?? null;

if ($slug && isset($RUBRICS[$slug])) {
    $current_rubric = $RUBRICS[$slug];
    $current_slug   = $slug;
    $current_page   = $slug;
} elseif ($tag_param) {
    $tag_to_slug = ['materials' => 'materialy', 'basics' => 'start', 'smd' => 'praktika', 'tools' => 'praktika'];
    $current_slug   = $tag_to_slug[$tag_param] ?? 'materialy';
    $current_rubric = $RUBRICS[$current_slug] ?? $RUBRICS['materialy'];
    $current_page   = $current_slug;
} else {
    $current_rubric = $RUBRICS['materialy'];
    $current_slug   = 'materialy';
    $current_page   = 'materialy';
}

$page_title = $current_rubric['title'] . " — Журнал ТОЧКА ПЛАВЛЕНИЯ";

// Collect articles
if (isset($current_rubric['article_ids'])) {
    $matched_articles = [];
    foreach ($current_rubric['article_ids'] as $aid) {
        foreach ($articles as $art) {
            if ($art['id'] === $aid) {
                $matched_articles[] = $art;
                break;
            }
        }
    }
} else {
    $matched_articles = array_values(array_filter($articles, fn($a) => in_array($a['tag_key'], $current_rubric['filter_tags'])));
}

// Separate featured article
$featured_id = $current_rubric['featured_id'] ?? ($matched_articles[0]['id'] ?? null);
$featured_article = null;
$grid_articles = [];

foreach ($matched_articles as $art) {
    if ($art['id'] === $featured_id && !$featured_article) {
        $featured_article = $art;
    } else {
        $grid_articles[] = $art;
    }
}
if (!$featured_article && !empty($grid_articles)) {
    $featured_article = array_shift($grid_articles);
}

$page_num = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$paginated = paginate($grid_articles, 4, $page_num);

/**
 * Strict Editorial Semantic Color Logic:
 * - Orange (pill-orange / hl-orange): Thermal, soldering temperatures, copper, solder alloys, heating stations.
 * - Yellow (pill-yellow / hl-yellow): Chemistry, rosin, active fluxes, workshop master notes (post-it).
 * - Blue   (pill-blue   / hl-blue):   Standards (GOST, IPC), SMD precision, electronics, leakage currents.
 * - Orange / Amber (pill-orange / hl-orange): Thermal, soldering temperatures, copper, solder alloys, heating stations, defect warnings.
 */
function get_semantic_tag_pill(string $tag_key): string {
    return match(strtolower($tag_key)) {
        'materials'          => 'pill-orange', // Металлы, сплавы, нагрев
        'basics'             => 'pill-yellow', // База, флюсы, канифоль
        'smd', 'tools'       => 'pill-blue',   // SMD компоненты, приборы, точность
        'defects', 'oshibki' => 'pill-orange', // Ошибки, брак, предупреждения
        default              => 'pill-blue',
    };
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?= e($page_title) ?> — Точка Плавления // ТЧП</title>
  <meta name="description" content="<?= e($current_rubric['lead']) ?>">

  <!-- OpenGraph Meta -->
  <meta property="og:type" content="website"/>
  <meta property="og:title" content="<?= e($page_title) ?>"/>
  <meta property="og:description" content="<?= e($current_rubric['lead']) ?>"/>
  <meta property="og:site_name" content="ТОЧКА ПЛАВЛЕНИЯ"/>
  
  <!-- Immediate Theme Init Script (Zero FOUC) -->
  <script>
    (function() {
      const saved = localStorage.getItem('tp_theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <!-- Preconnect for Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Self-Hosted Fonts & Compiled Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/build.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Common Design Tokens & Base Styles -->
  <link rel="stylesheet" href="assets/css/common.css">

  <style>
    .font-hand { font-family: 'Caveat', cursive; }
  </style>
</head>
<body class="font-sans min-h-screen flex flex-col justify-between text-[15px] leading-[1.65]">

  <!-- Top Minimal Header Bar (Consistent with index.php & interactive.php) -->
  <header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      
      <!-- Brand mark & Navigation -->
      <div class="flex items-center gap-6">
        <a class="logo" href="/">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>

        <!-- Desktop Nav -->
        <?php 
        $current_page = $current_slug;
        include __DIR__ . '/includes/header-nav.php'; 
        ?>
      </div>

      <!-- Right Action / Dark Mode Toggle & Workbench link -->
      <div class="flex items-center gap-3">
        <!-- Dark/Light Mode Switcher (Sketch Style) -->
        <button id="theme-toggle" type="button" class="sketch-pill-gray hover:border-ink/50 text-ink font-mono text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <svg class="w-3.5 h-3.5 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-3.5 h-3.5 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4.5"></circle>
            <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
          </svg>
          <span class="hidden sm:inline text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="/interactive.php" aria-label="Открыть интерактивный верстак">
          <span>Верстак / Тулзы</span>
          <span class="material-symbols-outlined text-[13px]">build</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full flex-grow pt-8 pb-16">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint flex items-center gap-1.5 flex-wrap">
        <a class="hover:text-ink transition-colors" href="/">Главная</a>
        <span>→</span>
        <a class="hover:text-ink transition-colors" href="/index.php#articles">Рубрики журнала</a>
        <span>→</span>
        <span class="text-ink font-semibold"><?= e($current_rubric['title']) ?></span>
      </nav>

      <!-- HERO SECTION (Calm, Engineering Aesthetics with Strict Semantic Highlights) -->
      <section class="border-b border-paper-border pb-8">
        <div class="max-w-3xl space-y-4">
          
          <!-- Single Subtle Tape & Label Badge (Yellow = Workshop/Editorial Identity) -->
          <div class="relative inline-block mb-1">
            <div class="tape-strip tape-yellow w-7 -top-1.5 left-4 rotate-[-2deg]"></div>
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 bg-[#fefce8] dark:bg-[#ca8a04]/20 border border-[#fde047] dark:border-[#ca8a04]/60 text-ink font-mono text-[10.5px] shadow-sm rotate-[-0.8deg] sketch-border">
              <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span>
              <span class="font-bold tracking-wider uppercase"><?= e($current_rubric['badge']) ?></span>
              <span class="text-ink-faint">|</span>
              <span class="text-[9.5px] text-ink-muted uppercase"><?= e($current_rubric['subbadge']) ?></span>
            </div>
          </div>

          <!-- Headline -->
          <h1 class="text-3xl sm:text-5xl font-bold text-ink tracking-tight font-sans">
            <?= e($current_rubric['title']) ?>
          </h1>

          <!-- Lead Paragraph: Semantic Color Mapping:
               - Orange = Metallurgy, solder alloys, heating
               - Yellow = Chemistry, fluxes, rosin
               - Blue   = Electronic parameters, precision, standards
          -->
          <p class="text-base sm:text-lg text-ink/90 font-serif leading-[1.65]">
            <?php if ($current_slug === 'materialy'): ?>
              Разбираем металлургию и химию радиомонтажа: свойства припоев (<mark class="hl-orange">ПОС-61, SAC305</mark>), химию флюсов (<mark class="hl-yellow">RMA и No-Clean</mark>), риск разрушения шва от сплава Розе и защиту от <mark class="hl-blue">токов утечки</mark>.
            <?php elseif ($current_slug === 'start'): ?>
              Первые шаги в радиомонтаже: выбор <mark class="hl-orange">паяльной станции</mark>, основы работы с <mark class="hl-yellow">канифолью</mark>, физика <mark class="hl-blue">смачиваемости меди</mark> и защита от перегрева дорожек.
            <?php elseif ($current_slug === 'praktika'): ?>
              Техника точного ручного монтажа: работа с компонентами <mark class="hl-blue">SMD 0402–1206</mark>, геометрия картриджей <mark class="hl-orange">T12 и C245</mark>, дозировка <mark class="hl-yellow">паяльной пасты</mark> и пайка термочувствительных микросхем QFN.
            <?php elseif ($current_slug === 'oshibki'): ?>
              Диагностика и устранение типового брака: критический дефект <mark class="hl-orange">tombstoning и трещины</mark>, паразитные <mark class="hl-blue">оловянные перемычки</mark>, неактивированный флюс в <mark class="hl-yellow">холодном шве</mark> и сбои термопрофиля.
            <?php else: ?>
              <?= e($current_rubric['lead']) ?>
            <?php endif; ?>
          </p>

        </div>
      </section>

      <!-- CATEGORY NAVIGATION PILLS: Semantic Identity Color Coding:
           - start: Yellow (Basics/Rosin)
           - materialy: Orange (Alloys/Thermal)
           - praktika: Blue (SMD/Instruments)
           - oshibki: Amber (Defects/Damage)
      -->
      <div class="flex items-center gap-2 overflow-x-auto pb-2 font-mono text-xs">
        <?php
        $nav_pills = [
            ['slug' => 'start',     'label' => 'Начать паять',     'dot' => 'bg-amber-400'],
            ['slug' => 'materialy', 'label' => 'Материалы и сплавы', 'dot' => 'bg-orange-500'],
            ['slug' => 'praktika',  'label' => 'Практика монтажа',   'dot' => 'bg-sky-500'],
            ['slug' => 'oshibki',   'label' => 'Проблемы и дефекты', 'dot' => 'bg-amber-500'],
        ];
        foreach ($nav_pills as $pill):
            $is_curr = ($current_slug === $pill['slug']);
        ?>
          <a href="/category.php?slug=<?= $pill['slug'] ?>"
             class="inline-flex items-center gap-1.5 px-3 py-1 rounded transition-all whitespace-nowrap text-xs <?= $is_curr ? 'bg-ink text-paper font-bold shadow-sm' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $pill['dot'] ?>"></span>
            <span><?= $pill['label'] ?></span>
          </a>
        <?php endforeach; ?>
        <a href="/index.php#articles" class="px-3 py-1 rounded border border-paper-border bg-paper text-ink-faint hover:text-ink transition-colors whitespace-nowrap ml-auto text-xs">
          Все статьи журнала →
        </a>
      </div>

      <!-- MAIN EDITORIAL LAYOUT: 8 COLS (Articles) + 4 COLS (Sidebar) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Articles + Contextual CTA (8 cols) -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- CONTEXTUAL INTERACTIVE CTA BANNER (Blue = Engineering Standard / Tool) -->
          <?php if (!empty($current_rubric['cta'])): ?>
            <div class="border border-paper-border rounded-lg p-5 sm:p-6 bg-card shadow-sm space-y-3 relative overflow-hidden">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span class="pill-blue text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded">
                    ★ ИНТЕРАКТИВНЫЙ ИНСТРУМЕНТ
                  </span>
                </div>
                <span class="text-[11px] font-mono text-ink-faint">IPC-7095C / ГОСТ</span>
              </div>

              <h2 class="text-lg sm:text-xl font-bold text-ink font-sans">
                <?= e($current_rubric['cta']['title']) ?>
              </h2>
              
              <p class="text-xs sm:text-sm text-ink-muted font-mono leading-relaxed">
                <?= e($current_rubric['cta']['desc']) ?>
              </p>

              <div class="pt-2 flex flex-wrap items-center gap-3">
                <?php if (!empty($current_rubric['cta']['link1'])): ?>
                  <a href="<?= e($current_rubric['cta']['link1']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded bg-ink text-paper font-mono text-xs font-medium hover:opacity-90 transition-opacity shadow-sm">
                    <span><?= e($current_rubric['cta']['lbl1']) ?></span>
                  </a>
                <?php endif; ?>
                <?php if (!empty($current_rubric['cta']['link2'])): ?>
                  <a href="<?= e($current_rubric['cta']['link2']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded border border-paper-border bg-paper hover:border-accent text-ink font-mono text-xs transition-colors">
                    <span><?= e($current_rubric['cta']['lbl2']) ?></span>
                    <span class="text-xs">→</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- FEATURED MAIN ARTICLE -->
          <?php if ($featured_article): 
            $f_url = "/article.php?slug=" . urlencode($featured_article['slug']);
            $f_tag_pill = get_semantic_tag_pill($featured_article['tag_key'] ?? 'materials');
          ?>
            <article class="border border-paper-border rounded-lg bg-card p-6 sm:p-7 space-y-5 shadow-sm transition-all hover:border-paper-border-dark relative">
              
              <!-- Top Meta Line -->
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted pt-1">
                <div class="flex items-center gap-2">
                  <span class="text-ink font-hand text-lg font-bold italic tracking-wide rotate-[-1deg] inline-block">
                    <?= e($featured_article['author'] ?? 'Иван Пайкин') ?>
                  </span>
                  <span class="text-ink-faint">·</span>
                  <span>~<?= e($featured_article['read_min'] ?? 8) ?> мин чтения</span>
                </div>
                <span class="pill-yellow px-1.5 py-0.5 rounded text-[10px] font-mono uppercase font-bold">
                  ★ Флагман рубрики
                </span>
              </div>

              <!-- Title & Excerpt -->
              <div class="space-y-3">
                <h2 class="text-2xl sm:text-[28px] font-bold text-ink tracking-tight leading-[1.2]">
                  <a class="hover:underline decoration-ink underline-offset-4" href="<?= $f_url ?>">
                    <?= e($featured_article['title']) ?>
                  </a>
                </h2>
                <p class="text-ink/85 font-serif text-[16px] leading-relaxed">
                  <?= e($featured_article['excerpt']) ?>
                </p>
              </div>

              <!-- Technical Info Strip: Semantic Highlighting:
                   - RMA / NC: Yellow (Chemistry & fluxes)
                   - WS: Blue (Industrial wash precision)
                   - SPEC 02.1: Blue (Engineering standard)
              -->
              <div class="rounded border border-paper-border bg-paper-subtle p-3 flex items-center justify-between font-mono text-xs text-ink-muted overflow-x-auto gap-3">
                <div class="flex items-center gap-3 shrink-0">
                  <span>RMA-223: <span class="hl-yellow text-ink font-semibold">активный флюс</span></span>
                  <span class="text-ink-faint">→</span>
                  <span>NC-559: <span class="hl-yellow text-ink font-semibold">No-Clean гель</span></span>
                  <span class="text-ink-faint">→</span>
                  <span>WS: <span class="hl-blue text-ink font-semibold">водосмывной</span></span>
                </div>
                <span class="pill-blue text-[9.5px] font-mono font-bold px-1.5 py-0.5 rounded ml-2 shrink-0">SPEC 02.1</span>
              </div>

              <!-- Card Bottom Bar: Tag color derived strictly from topic -->
              <div class="flex items-center justify-between pt-2 border-t border-paper-border font-mono text-xs text-ink-muted">
                <div class="flex items-center gap-2">
                  <span class="<?= $f_tag_pill ?> px-1.5 py-0.5 rounded font-mono text-[10px] font-bold"><?= e($featured_article['tag']) ?></span>
                  <span class="text-ink-faint">·</span>
                  <span><?= e($featured_article['date'] ?? '2026') ?></span>
                </div>
                <a class="text-ink font-semibold hover:underline flex items-center gap-1 group" href="<?= $f_url ?>">
                  <span class="underline decoration-ink decoration-2 underline-offset-4">Читать статью полностью</span>
                  <span>→</span>
                </a>
              </div>

            </article>
          <?php endif; ?>

          <!-- 2-COLUMN ARTICLE CARDS GRID: Semantic Tag Color Assignment -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php 
            foreach ($paginated['items'] as $article): 
              $art_url = "/article.php?slug=" . urlencode($article['slug']);
              // Tag pill derived by semantic domain:
              $card_pill = get_semantic_tag_pill($article['tag_key'] ?? '');
            ?>
              <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-all">
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                    <span class="<?= $card_pill ?> px-1.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase">
                      <?= e($article['tag']) ?>
                    </span>
                    <span>~<?= e($article['read_min']) ?> мин</span>
                  </div>

                  <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                    <a class="hover:underline" href="<?= $art_url ?>">
                      <?= e($article['title']) ?>
                    </a>
                  </h3>

                  <p class="text-[13.5px] text-ink-muted leading-relaxed">
                    <?= e($article['excerpt']) ?>
                  </p>
                </div>

                <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-faint font-hand text-base font-bold italic"><?= e($article['author'] ?? 'Мария Канифоль') ?></span>
                  <a class="text-ink font-medium hover:underline flex items-center gap-0.5" href="<?= $art_url ?>">
                    <span>Читать</span>
                    <span>→</span>
                  </a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <!-- EDITORIAL PAGINATION -->
          <?php 
          $base_pag_url = "/category.php?slug=" . urlencode($current_slug);
          if ($paginated['total_pages'] > 1): 
          ?>
            <div class="flex items-center justify-center gap-2 pt-4 font-mono text-xs">
              <?php for ($p = 1; $p <= $paginated['total_pages']; $p++): ?>
                <a href="<?= $base_pag_url ?>&page=<?= $p ?>"
                   class="w-8 h-8 rounded flex items-center justify-center border transition-colors <?= $p === $page_num ? 'bg-ink text-paper font-bold border-ink' : 'border-paper-border bg-paper text-ink hover:border-accent' ?>">
                  <?= $p ?>
                </a>
              <?php endfor; ?>
            </div>
          <?php endif; ?>

        </div>

        <!-- RIGHT COLUMN: SIDEBAR (Strict Semantic Workbench Cards) -->
        <aside class="lg:col-span-4 space-y-5">
          
          <!-- 💛 YELLOW POST-IT NOTE: Chemistry / Rosin / Workshop Master Note -->
          <div class="sticker-yellow p-3.5 rounded-lg space-y-2 relative sketch-border shadow-sm rotate-[-0.8deg]">
            <div class="tape-strip tape-yellow w-8 -top-1.5 left-5 rotate-[-2deg]"></div>
            
            <div class="flex items-center justify-between font-mono text-[10px] uppercase font-bold border-b border-[#facc15]/40 pb-1">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#854d0e] dark:bg-[#facc15]"></span>
                📌 Заметка верстака // Химия
              </span>
              <span class="text-[9.5px] opacity-75">FLUX-QC</span>
            </div>

            <p class="font-hand text-[15.5px] leading-snug italic font-semibold">
              «Канифоль активируется при 150°C, но сгорает в золу при 300°C. Если жало дымит чёрным — убавь нагрев станции, а не заливай всё флюсом!»
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-[9.5px] opacity-80 border-t border-[#facc15]/35">
              <span>Норма нагрева флюса</span>
              <span class="font-bold">IPC-TM-650</span>
            </div>
          </div>

          <!-- TECHNICAL SPECIFICATION: Solder liquidus (Clean Reference Card) -->
          <div class="border border-paper-border rounded-lg bg-card p-3.5 space-y-2.5 shadow-sm relative">
            <div class="flex items-center justify-between font-mono text-[10.5px] uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span class="flex items-center gap-1.5 text-ink">
                <span class="material-symbols-outlined text-[14px] text-accent">thermostat</span>
                Ликвидус металлов
              </span>
              <span class="pill-blue text-[9.5px] font-mono uppercase px-1.5 py-0.2 rounded">ГОСТ 21931</span>
            </div>

            <div class="space-y-1 font-mono text-xs">
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">ПОС-61 (Sn63Pb37)</span>
                <span class="font-bold text-ink text-[11.5px]">183 °C</span>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">SAC305 (RoHS)</span>
                <span class="font-bold text-ink text-[11.5px]">217–220 °C</span>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">Sn42Bi58 (Низкотемп.)</span>
                <span class="font-bold text-ink text-[11.5px]">138 °C</span>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-ink">Сплав Розе (Демонтаж)</span>
                <span class="font-bold text-ink text-[11.5px]">94 °C</span>
              </div>
            </div>

            <div class="pt-0.5 text-right">
              <a href="/interactive.php#table" class="font-mono text-[10.5px] text-accent hover:underline font-bold">
                Вся таблица припоев (9 марок) →
              </a>
            </div>
          </div>

          <!-- ⚠️ WARNING CALLOUT: Critical Defect Warning (Rose Alloy Fragility Risk) -->
          <div class="border border-paper-border rounded-lg bg-card p-3.5 space-y-1.5 border-l-4 border-l-amber-500 shadow-sm">
            <div class="flex items-center justify-between font-mono text-[10px] uppercase font-bold border-b border-paper-border pb-1">
              <span class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400">
                <span>⚠️</span>
                Риск брака // Сплав Розе
              </span>
              <span class="pill-orange text-[9px] px-1.5 py-0.2 rounded font-mono font-bold">ОСТОРОЖНО</span>
            </div>

            <p class="font-hand text-[15px] leading-snug italic font-semibold">
              «Сплав Розе (94°C) — строго для демонтажа! После снятия разъёма остатки сплава нужно насухо вычистить оплёткой, иначе пайка рассыплется от малейшей вибрации.»
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-[9.5px] opacity-80 border-t border-paper-border">
              <span>Хрупкость шва</span>
              <span class="font-bold">Бинарная эвтектика</span>
            </div>
          </div>

          <!-- WORKBENCH TOOLS: Semantically Coded Shortcuts:
               - #temp:   Orange (Thermal window)
               - #iron:   Blue   (Tooling/Stations)
               - #defect: Orange (Defects/Troubleshooting)
          -->
          <div class="border border-paper-border rounded-lg bg-card p-3.5 space-y-2.5 shadow-sm">
            <div class="flex items-center justify-between font-mono text-[10.5px] uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span>Инструменты верстака</span>
              <span class="pill-blue text-[9.5px] px-1.5 py-0.2 rounded">3 ТУЛЗЫ</span>
            </div>
            <div class="space-y-1.5 font-mono text-xs">
              <a href="/interactive.php#temp" class="flex items-center justify-between p-1.5 rounded border border-paper-border bg-paper hover:border-orange-400 text-ink transition-colors group">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-[11.5px]">🌡️ Термокалькулятор</div>
                  <div class="text-[10.5px] text-ink-muted">Подбор °C под провод и припой</div>
                </div>
                <span class="pill-orange text-[10px] px-1.5 py-0.2 rounded font-bold">#temp</span>
              </a>

              <a href="/interactive.php#iron" class="flex items-center justify-between p-1.5 rounded border border-paper-border bg-paper hover:border-sky-400 text-ink transition-colors group">
                <div>
                  <div class="font-bold group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors text-[11.5px]">🔧 Подбор паяльника</div>
                  <div class="text-[10.5px] text-ink-muted">Станции T12, C245 под бюджет</div>
                </div>
                <span class="pill-blue text-[10px] px-1.5 py-0.2 rounded font-bold">#iron</span>
              </a>

              <a href="/interactive.php#defect" class="flex items-center justify-between p-1.5 rounded border border-paper-border bg-paper hover:border-amber-400 text-ink transition-colors group">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-[11.5px]">🔍 Дерево дефектов</div>
                  <div class="text-[10.5px] text-ink-muted">Диагностика причин брака</div>
                </div>
                <span class="pill-orange text-[10px] px-1.5 py-0.2 rounded font-bold">#defect</span>
              </a>
            </div>
          </div>

        </aside>

      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <script>
    (function() {
      const toggle = document.getElementById('theme-toggle');
      if (toggle) {
        toggle.addEventListener('click', function() {
          const isDark = document.documentElement.classList.toggle('dark');
          localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
        });
      }
    })();
  </script>

</body>
</html>
