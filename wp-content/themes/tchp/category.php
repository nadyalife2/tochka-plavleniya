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

      <!-- HERO SECTION (Calm, Engineering Aesthetics) -->
      <section class="border-b border-paper-border pb-8">
        <div class="max-w-3xl space-y-4">
          
          <!-- Single Subtle Tape & Label Badge -->
          <div class="relative inline-block mb-1">
            <div class="tape-strip tape-yellow w-10 -top-2 left-5 rotate-[-2deg]"></div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-[#fefce8] dark:bg-[#ca8a04]/20 border border-[#fde047] dark:border-[#ca8a04]/60 text-ink font-mono text-[11px] shadow-sm rotate-[-0.8deg] sketch-border">
              <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span>
              <span class="font-bold tracking-wider uppercase"><?= e($current_rubric['badge']) ?></span>
              <span class="text-ink-faint">|</span>
              <span class="text-[10px] text-ink-muted uppercase"><?= e($current_rubric['subbadge']) ?></span>
            </div>
          </div>

          <!-- Headline -->
          <h1 class="text-3xl sm:text-5xl font-bold text-ink tracking-tight font-sans">
            <?= e($current_rubric['title']) ?>
          </h1>

          <!-- Lead Paragraph (Clean, Legible Typography) -->
          <p class="text-base sm:text-lg text-ink/90 font-serif leading-[1.65]">
            <?= e($current_rubric['lead']) ?>
          </p>

        </div>
      </section>

      <!-- CATEGORY NAVIGATION PILLS -->
      <div class="flex items-center gap-2 overflow-x-auto pb-2 font-mono text-xs">
        <?php
        $nav_pills = [
            ['slug' => 'start',     'label' => 'Начать паять'],
            ['slug' => 'materialy', 'label' => 'Материалы и сплавы'],
            ['slug' => 'praktika',  'label' => 'Практика монтажа'],
            ['slug' => 'oshibki',   'label' => 'Проблемы и дефекты'],
        ];
        foreach ($nav_pills as $pill):
            $is_curr = ($current_slug === $pill['slug']);
        ?>
          <a href="/category.php?slug=<?= $pill['slug'] ?>"
             class="px-3.5 py-1.5 rounded transition-all whitespace-nowrap <?= $is_curr ? 'bg-ink text-paper font-bold shadow-sm' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?>">
            <?= $pill['label'] ?>
          </a>
        <?php endforeach; ?>
        <a href="/index.php#articles" class="px-3.5 py-1.5 rounded border border-paper-border bg-paper text-ink-faint hover:text-ink transition-colors whitespace-nowrap ml-auto">
          Все статьи журнала →
        </a>
      </div>

      <!-- MAIN EDITORIAL LAYOUT: 8 COLS (Articles) + 4 COLS (Sidebar) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Articles + Contextual CTA (8 cols) -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- CONTEXTUAL INTERACTIVE CTA BANNER (Calm Workbench Style) -->
          <?php if (!empty($current_rubric['cta'])): ?>
            <div class="border border-paper-border rounded-lg p-5 sm:p-6 bg-card shadow-sm space-y-3">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-mono font-bold uppercase tracking-wider text-accent">
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
                  <a href="<?= e($current_rubric['cta']['link1']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded bg-ink text-paper font-mono text-xs font-medium hover:opacity-90 transition-opacity">
                    <span><?= e($current_rubric['cta']['lbl1']) ?></span>
                  </a>
                <?php endif; ?>
                <?php if (!empty($current_rubric['cta']['link2'])): ?>
                  <a href="<?= e($current_rubric['cta']['link2']) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink font-mono text-xs transition-colors">
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
                <span class="px-2 py-0.5 rounded text-[10px] font-mono uppercase bg-paper-subtle border border-paper-border text-ink-muted">
                  Флагман рубрики
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

              <!-- Technical Info Strip -->
              <div class="rounded border border-paper-border bg-paper-subtle p-3.5 flex items-center justify-between font-mono text-xs text-ink-muted overflow-x-auto">
                <div class="flex items-center gap-3 shrink-0">
                  <span>RMA-223: <strong class="text-ink">активность средняя</strong></span>
                  <span class="text-ink-faint">→</span>
                  <span>NC-559: <strong class="text-ink">No-Clean гель</strong></span>
                  <span class="text-ink-faint">→</span>
                  <span>WS: <strong class="text-ink">водосмывной</strong></span>
                </div>
                <span class="text-[10px] text-ink-faint font-mono ml-2">SPEC 02.1</span>
              </div>

              <!-- Card Bottom Bar -->
              <div class="flex items-center justify-between pt-2 border-t border-paper-border font-mono text-xs text-ink-muted">
                <div class="flex items-center gap-2">
                  <span class="text-ink font-medium"><?= e($featured_article['tag']) ?></span>
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

          <!-- 2-COLUMN ARTICLE CARDS GRID -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php 
            foreach ($paginated['items'] as $article): 
              $art_url = "/article.php?slug=" . urlencode($article['slug']);
            ?>
              <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-all">
                <div class="space-y-2">
                  <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                    <span class="uppercase font-medium text-ink border-b border-paper-border pb-0.5">
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

        <!-- RIGHT COLUMN: SIDEBAR (Restrained, Tasteful Workbench Accents) -->
        <aside class="lg:col-span-4 space-y-6">
          
          <!-- 💛 SINGLE AUTHENTIC YELLOW POST-IT (Caveat Handwriting) -->
          <div class="sticker-yellow p-4 rounded-lg space-y-2.5 relative sketch-border shadow-sm rotate-[-0.8deg]">
            <div class="tape-strip tape-yellow w-12 -top-2 left-6 rotate-[-2deg]"></div>
            
            <div class="flex items-center justify-between font-mono text-[10.5px] uppercase font-bold border-b border-[#facc15]/50 pb-1.5">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-[#854d0e] dark:bg-[#facc15]"></span>
                📌 Заметка верстака
              </span>
              <span class="text-[10px] opacity-75">FLUX-QC</span>
            </div>

            <p class="font-hand text-[17px] leading-snug italic font-semibold">
              «Канифоль активируется при 150°C, но сгорает в золу при 300°C. Если жало дымит чёрным — убавь нагрев станции, а не заливай всё флюсом!»
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-[10px] opacity-80 border-t border-[#facc15]/40">
              <span>Норма нагрева</span>
              <span class="font-bold">IPC-TM-650</span>
            </div>
          </div>

          <!-- TECHNICAL SPECIFICATION: Solder liquidus (Clean Card) -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between font-mono text-[11px] uppercase font-bold text-ink-muted border-b border-paper-border pb-2">
              <span class="flex items-center gap-1.5 text-ink">
                <span class="material-symbols-outlined text-[15px] text-accent">thermostat</span>
                Ликвидус металлов
              </span>
              <span class="text-[10px] font-normal text-ink-faint">ГОСТ 21931</span>
            </div>

            <div class="space-y-1.5 font-mono text-xs">
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">ПОС-61 (Sn63Pb37)</span>
                <strong class="text-accent font-bold">183 °C</strong>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">SAC305 (RoHS)</span>
                <strong class="text-ink font-bold">217–220 °C</strong>
              </div>
              <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                <span class="text-ink">Sn42Bi58 (Низкотемп.)</span>
                <strong class="text-ink font-bold">138 °C</strong>
              </div>
              <div class="flex items-center justify-between py-1">
                <span class="text-ink">Сплав Розе (Демонтаж)</span>
                <strong class="text-ink font-bold">94 °C</strong>
              </div>
            </div>

            <div class="pt-1 text-right">
              <a href="/interactive.php#table" class="font-mono text-[11px] text-accent hover:underline font-bold">
                Вся таблица припоев (9 марок) →
              </a>
            </div>
          </div>

          <!-- SUBTLE WARNING NOTE (Clean Alert) -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-2 border-l-4 border-l-amber-500 shadow-sm">
            <div class="flex items-center gap-1.5 font-mono text-[11px] uppercase font-bold text-amber-700 dark:text-amber-400">
              <span>⚠️</span>
              Правило работы с Розе и Вудом
            </div>
            <p class="text-xs font-mono text-ink-muted leading-relaxed">
              Сплав Розе (94°C) — строго для бережного демонтажа разъёмов. После снятия детали остатки сплава необходимо полностью удалить оплёткой перед повторной пайкой.
            </p>
          </div>

          <!-- WORKBENCH SHORTCUTS -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm">
            <div class="font-mono text-[11px] uppercase font-bold text-ink-muted border-b border-paper-border pb-2">
              Шорткаты калькуляторов
            </div>
            <div class="space-y-1.5 font-mono text-xs">
              <a href="/interactive.php#temp" class="block p-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors">
                <div class="font-bold text-accent">🌡️ Термокалькулятор</div>
                <div class="text-[11px] text-ink-muted">Подбор °C под провод и припой</div>
              </a>
              <a href="/interactive.php#iron" class="block p-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors">
                <div class="font-bold text-ink">🔧 Подбор паяльника</div>
                <div class="text-[11px] text-ink-muted">Станции T12, C245 под бюджет</div>
              </a>
              <a href="/interactive.php#defect" class="block p-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors">
                <div class="font-bold text-ink">🔍 Дерево дефектов</div>
                <div class="text-[11px] text-ink-muted">Диагностика причин брака</div>
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
