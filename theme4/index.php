<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/rubrics-data.php';

$page_title = "Точка Плавления — Инженерный справочник по пайке и монтажу";
$page_desc  = "Открытые технологические регламенты пайки, карты температур жала станции по ГОСТ и IPC, химическая классификация флюсов и интерактивный верстак инженера.";
$current_page = 'index';

$assets_base = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';
$site_root = function_exists('home_url') ? home_url('/') : '/';

// Динамическая выборка опубликованных статей из WordPress с fallback на справочник
$recent_articles = [];

if (function_exists('get_posts')) {
    $wp_recent = get_posts([
        'numberposts' => 4,
        'post_status' => 'publish',
        'post_type'   => 'post',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    if (!empty($wp_recent)) {
        foreach ($wp_recent as $p) {
            $cats = get_the_category($p->ID);
            $primary_cat = !empty($cats) ? $cats[0]->name : 'Инженерия';
            $cat_slug = !empty($cats) ? $cats[0]->slug : 'materialy';
            
            $content_text = strip_tags($p->post_content);
            $char_count = function_exists('mb_strlen') ? mb_strlen($content_text, 'UTF-8') : strlen($content_text);
            $read_min = max(2, min(15, (int)round($char_count / 1100)));
            if ($read_min < 2) $read_min = 3;

            $excerpt = !empty($p->post_excerpt) 
                ? $p->post_excerpt 
                : (function_exists('wp_trim_words') ? wp_trim_words($content_text, 22, '...') : mb_substr($content_text, 0, 140) . '...');

            $author = get_the_author_meta('display_name', $p->post_author) ?: 'Инженер ОТК';

            $recent_articles[] = [
                'id'       => $p->ID,
                'slug'     => $p->post_name,
                'title'    => get_the_title($p->ID),
                'url'      => get_permalink($p->ID),
                'tag'      => $primary_cat,
                'tag_key'  => $cat_slug,
                'read_min' => $read_min,
                'excerpt'  => $excerpt,
                'author'   => $author,
                'image'    => (function_exists('get_the_post_thumbnail_url') ? get_the_post_thumbnail_url($p->ID, 'large') : '') ?: ($assets_base . '/assets/img/og-cover.png'),
                'is_wp'    => true
            ];
        }
    }
}

// Если в WP меньше 4 статей или запуск автономный — добираем из базы знаний articles-data.php
if (count($recent_articles) < 4) {
    $needed = 4 - count($recent_articles);
    $static_candidates = array_slice($articles, 0, $needed);
    foreach ($static_candidates as $sa) {
        $link = tchp_get_article_url($sa['slug'], $sa['id'] ?? 0);
        $sa_img = $sa['image'] ?? ($assets_base . '/assets/img/og-cover.png');
        if (str_starts_with($sa_img, '/') && !empty($assets_base)) {
            $sa_img = rtrim($assets_base, '/') . $sa_img;
        }
        $recent_articles[] = [
            'id'       => $sa['id'] ?? 0,
            'slug'     => $sa['slug'],
            'title'    => $sa['title'],
            'url'      => $link,
            'tag'      => $sa['tag'],
            'tag_key'  => $sa['tag_key'] ?? '',
            'read_min' => $sa['read_min'] ?? 4,
            'excerpt'  => $sa['excerpt'] ?? '',
            'author'   => $sa['author'] ?? 'Инженер ОТК',
            'image'    => $sa_img,
            'is_wp'    => false
        ];
    }
}

// Флагманский материал для главной страницы
$featured_article = $articles[3] ?? $articles[0]; // Жало T12 vs C245 на верстаке
$f_url = tchp_get_article_url($featured_article['slug'], $featured_article['id'] ?? 0);
$f_tag_pill = get_semantic_tag_pill($featured_article['tag_key'] ?? 'tools');

ob_start();
?>
  <link rel="canonical" href="https://tochka-plavleniya.ru/">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "ТОЧКА ПЛАВЛЕНИЯ",
    "url": "https://tochka-plavleniya.ru/",
    "description": "Инженерный справочник по пайке, температурные режимы жала и интерактивный верстак.",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://tochka-plavleniya.ru/?s={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
<?php
$extra_head = ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Content in Unified Editorial Lab Blueprint Layout -->
  <main id="main-content" class="w-full flex-grow">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 py-6 space-y-6">

      <!-- 1. EDITORIAL HEADER (UNIFIED WITH CATEGORY.PHP) -->
      <section class="border-b border-paper-border pb-6 space-y-4">
        <div class="max-w-3xl space-y-3">
          
          <!-- Single Subtle Workshop Stamp -->
          <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-ink font-mono text-xs">
            <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span>
            <span class="font-bold tracking-wider uppercase">ОТК-2026</span>
            <span class="text-ink-faint">/</span>
            <span class="text-stamp text-ink-muted uppercase">ГОСТ Р & IPC-A-610</span>
          </div>

          <!-- Headline -->
          <h1 class="text-3xl sm:text-5xl font-bold text-ink tracking-tight font-sans">
            Инженерный справочник по пайке и радиомонтажу
          </h1>

          <!-- Lead Paragraph: Calm, Editorial Typography -->
          <p class="text-base sm:text-lg text-ink font-serif leading-[1.65]">
            Открытые регламенты радиомонтажа, температурные профили сплавов (<strong>ПОС-61, SAC305</strong>), классификация флюсов (<strong>RMA и No-Clean</strong>), геометрия картриджей <strong>T12 и C245</strong> и интерактивный верстак инженера.
          </p>

        </div>
      </section>

      <!-- 2. CATEGORY NAVIGATION PILLS BAR -->
      <nav aria-label="Переключение рубрик журнала" class="flex items-center gap-2 overflow-x-auto pb-2 font-mono text-xs">
        <a href="<?= esc_url($site_root) ?>" aria-current="page" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded transition-all whitespace-nowrap text-xs bg-ink text-paper font-bold shadow-sm focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <span class="w-1.5 h-1.5 rounded-full bg-accent" aria-hidden="true"></span>
          <span class="opacity-75 font-mono">[ГЛАВНАЯ]</span>
          <span>Все разделы</span>
        </a>
        <a href="<?= esc_url($site_root) ?>category.php?slug=start" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink transition-all whitespace-nowrap text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-400" aria-hidden="true"></span>
          <span class="opacity-75 font-mono">[SEC.01]</span>
          <span>Начать паять</span>
        </a>
        <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink transition-all whitespace-nowrap text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <span class="w-1.5 h-1.5 rounded-full bg-orange-500" aria-hidden="true"></span>
          <span class="opacity-75 font-mono">[SEC.02]</span>
          <span>Материалы и сплавы</span>
        </a>
        <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink transition-all whitespace-nowrap text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <span class="w-1.5 h-1.5 rounded-full bg-sky-500" aria-hidden="true"></span>
          <span class="opacity-75 font-mono">[SEC.03]</span>
          <span>Практика монтажа</span>
        </a>
        <a href="<?= esc_url($site_root) ?>category.php?slug=oshibki" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink transition-all whitespace-nowrap text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500" aria-hidden="true"></span>
          <span class="opacity-75 font-mono">[SEC.04]</span>
          <span>Проблемы и дефекты</span>
        </a>
        <a href="<?= esc_url($site_root) ?>interactive.php" class="px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-faint hover:text-ink transition-colors whitespace-nowrap ml-auto text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none flex items-center gap-1.5">
          <?= tp_icon('sliders', 'w-3.5 h-3.5 text-accent') ?>
          <span>Интерактивный верстак →</span>
        </a>
      </nav>

      <!-- 3. MAIN EDITORIAL LAYOUT: 8 COLS (Articles) + 4 COLS (Sidebar) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- LEFT COLUMN: 8 Columns of Rich Content -->
        <div class="lg:col-span-8 space-y-8">

          <!-- CONTEXTUAL INTERACTIVE BANNER: LIVE EXPRESS WORKBENCH WIDGET -->
          <div id="express-workbench-card" class="sketch-card p-5 sm:p-6 bg-card space-y-4 relative overflow-hidden">
            <div class="flex items-center justify-between gap-2 pt-1">
              <span class="pill-blue text-xs font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded flex items-center gap-1.5">
                <?= tp_icon('sliders', 'w-3.5 h-3.5 text-accent') ?>
                <span>[ЭКСПРЕСС-ВЕРСТАК // ТЕРМООКНО ЖАЛА]</span>
              </span>
              <span class="badge-stamp text-ink-faint">ГОСТ 21931 / IPC-A-610</span>
            </div>

            <div>
              <h2 class="text-lg sm:text-xl font-bold text-ink font-sans">
                Калькулятор безопасной температуры паяльного жала
              </h2>
              <p class="text-xs sm:text-sm text-ink-muted font-mono leading-relaxed mt-1">
                Выберите сплав припоя и тип паяемого узла — табло мгновенно рассчитает безопасную температуру жала станции.
              </p>
            </div>

            <!-- Interactive Express Controls -->
            <div class="rounded border border-paper-border bg-paper-subtle p-3 sm:p-4 space-y-3 font-mono text-xs">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <!-- Solder selector -->
                <div class="space-y-1">
                  <label for="exp-solder-select" class="block font-bold text-ink text-xs uppercase tracking-wide">
                    Припой / Марка сплава:
                  </label>
                  <select id="exp-solder-select" class="w-full bg-paper border border-paper-border-dark rounded px-2.5 py-1.5 text-ink font-mono text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <option value="pos61" data-liq="183">ПОС-61 // Свинцовый (183 °C)</option>
                    <option value="sac305" data-liq="217">SAC305 // Бессвинец RoHS (217 °C)</option>
                    <option value="sn42bi58" data-liq="138">Sn42Bi58 // Низкотемпературный (138 °C)</option>
                    <option value="rose" data-liq="94">Сплав Розе // Демонтажный (94 °C)</option>
                  </select>
                </div>

                <!-- Board / Mass selector -->
                <div class="space-y-1">
                  <label for="exp-mass-select" class="block font-bold text-ink text-xs uppercase tracking-wide">
                    Теплоёмкость соединения:
                  </label>
                  <select id="exp-mass-select" class="w-full bg-paper border border-paper-border-dark rounded px-2.5 py-1.5 text-ink font-mono text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                    <option value="smd" data-delta="85">SMD чипы и тонкие дорожки (1–2 слоя)</option>
                    <option value="pth" data-delta="105">Выводной монтаж (DIP, провода, разъемы)</option>
                    <option value="copper" data-delta="125">Массивная медь / Земляные полигоны GND</option>
                  </select>
                </div>
              </div>

              <!-- Digital Readout Display -->
              <div class="rounded border border-paper-border bg-paper p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3" aria-live="polite">
                <div class="space-y-0.5">
                  <div class="flex items-center gap-2">
                    <span class="text-ink-muted text-xs font-bold uppercase">Режим станции:</span>
                    <span id="exp-safety-badge" class="pill-blue text-xs font-bold px-2 py-0.5 rounded">
                      ЗЕЛЁНАЯ ЗОНА // БЕЗОПАСНО
                    </span>
                  </div>
                  <div class="text-xs text-ink-muted">
                    Ликвидус: <span id="exp-liq-val" class="font-bold text-ink">183 °C</span> · Дельта теплопередачи: <span id="exp-delta-val" class="font-bold text-accent">+85 °C</span>
                  </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                  <div class="px-3 py-1.5 rounded bg-ink text-paper font-mono font-bold text-lg tabular-nums tracking-wider border border-ink flex items-center gap-1 shadow-2xs">
                    <span id="exp-temp-out">268</span>
                    <span class="text-accent text-sm">°C</span>
                  </div>
                  <button type="button" id="exp-copy-btn" class="px-2.5 py-1.5 rounded border border-paper-border hover:border-accent text-ink hover:text-accent transition-colors text-xs font-mono font-bold" title="Скопировать значение">
                    Копировать
                  </button>
                </div>
              </div>
            </div>

            <!-- Action buttons: 1 Clear Primary CTA + 2 Quiet Secondary Links -->
            <div class="pt-1 flex flex-wrap items-center gap-3">
              <a href="<?= esc_url($site_root) ?>interactive.php#temp" class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-mono font-bold shadow-sm">
                <span>Открыть полный верстак (8 модулей) →</span>
              </a>
              <span class="text-ink-faint text-xs">·</span>
              <a href="<?= esc_url($site_root) ?>interactive.php#iron" class="text-xs font-mono text-ink-muted hover:text-ink hover:underline transition-colors">
                Подбор паяльника T12/C245
              </a>
              <span class="text-ink-faint text-xs">·</span>
              <a href="<?= esc_url($site_root) ?>interactive.php#table" class="text-xs font-mono text-ink-muted hover:text-ink hover:underline transition-colors">
                Реестр 9 сплавов ГОСТ
              </a>
            </div>
          </div>

          <!-- FEATURED MAIN ARTICLE -->
          <?php if ($featured_article): ?>
            <article class="featured-sketch-frame rounded-lg bg-card p-6 sm:p-7 space-y-5 transition-all relative">
              <?php if (!empty($featured_article['image'])): ?>
                <!-- Image Frame with FIG badge -->
                <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
                  <img src="<?= e($featured_article['image']) ?>" alt="<?= e($featured_article['title']) ?>" class="w-full h-56 sm:h-64 object-cover object-center" loading="lazy">
                  <div class="absolute bottom-2 right-2 px-2.5 py-1 bg-paper border border-paper-border text-xs font-mono text-ink-muted rounded shadow-2xs">
                    [ЧЕРТЁЖ // FIG. <?= e($featured_article['id'] ?? '1') ?>.0] // КЛЮЧЕВОЙ РАЗБОР
                  </div>
                </div>
              <?php endif; ?>

              <!-- Top Meta Line with Stamp Badge -->
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted pt-1 flex-wrap gap-2">
                <div class="flex items-center gap-2">
                  <span class="text-ink font-hand text-lg font-bold italic tracking-wide rotate-[-1deg] inline-block">
                    <?= e($featured_article['author'] ?? 'Инженер ОТК') ?>
                  </span>
                  <span class="text-ink-faint">·</span>
                  <span>~<?= e($featured_article['read_min'] ?? 8) ?> мин чтения</span>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-900 dark:text-blue-200 text-xs font-mono uppercase font-bold">
                  <?= tp_icon('shield-check', 'w-3.5 h-3.5 text-blue-700 dark:text-blue-300') ?>
                  [ФЛАГМАНСКИЙ ТЕХРЕГЛАМЕНТ // ОТК]
                </div>
              </div>

              <!-- Title & Excerpt -->
              <div class="space-y-2">
                <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight leading-snug">
                  <a class="hover:underline decoration-ink underline-offset-4 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded" href="<?= $f_url ?>">
                    <?= e($featured_article['title']) ?>
                  </a>
                </h2>
                <p class="text-ink/85 font-serif text-base sm:text-lg leading-relaxed">
                  <?= e($featured_article['excerpt']) ?>
                </p>
              </div>

              <!-- Technical Info Strip -->
              <div class="rounded border border-paper-border bg-paper-subtle p-3 flex items-center justify-between font-mono text-xs text-ink-muted overflow-x-auto gap-3">
                <div class="flex items-center gap-3 shrink-0">
                  <span>T12: <span class="hl-yellow text-ink font-semibold">керамика 70 Вт</span></span>
                  <span class="text-ink-faint" aria-hidden="true">→</span>
                  <span>C245: <span class="hl-orange text-ink font-semibold">картридж 130 Вт</span></span>
                  <span class="text-ink-faint" aria-hidden="true">→</span>
                  <span>GND: <span class="hl-blue text-ink font-semibold">прогрев за 1.5 с</span></span>
                </div>
                <span class="pill-blue text-xs font-mono font-bold px-2 py-0.5 rounded ml-2 shrink-0">НОРМАТИВ</span>
              </div>

              <!-- Card Bottom Bar -->
              <div class="flex items-center justify-between pt-3 border-t border-paper-border font-mono text-xs text-ink-muted flex-wrap gap-2">
                <div class="flex items-center gap-2">
                  <span class="<?= $f_tag_pill ?> px-2 py-0.5 rounded font-mono text-xs font-bold"><?= e($featured_article['tag']) ?></span>
                  <span class="text-ink-faint">·</span>
                  <span>2026</span>
                </div>
                <a class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-mono font-bold focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none" href="<?= $f_url ?>">
                  <span>Читать флагманский разбор</span>
                  <span aria-hidden="true">→</span>
                </a>
              </div>
            </article>
          <?php endif; ?>

          <!-- ПРИКЛАДНЫЕ ЗАДАЧИ МОНТАЖА (4 МОДУЛЯ В СТИЛЕ КАРТОЧЕК СТАТЕЙ) -->
          <div class="space-y-3">
            <div class="flex items-center justify-between border-b border-paper-border pb-2">
              <h2 class="text-base sm:text-lg font-bold font-mono uppercase text-ink flex items-center gap-2">
                <?= tp_icon('wrench', 'w-4 h-4 text-accent') ?>
                <span>Паяй уверенно // Задачи цеха</span>
              </h2>
              <span class="text-xs font-mono text-ink-muted">IPC-J-STD-001</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Задача 1: Провод -->
              <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=wire" class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-3 hover:border-paper-border-dark transition-all group">
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span class="pill-blue font-bold uppercase">[МОДУЛЬ 01 // ПРОВОД]</span>
                    <span class="font-bold text-accent">280 °C</span>
                  </div>
                  <h3 class="font-bold text-ink text-sm sm:text-base group-hover:text-accent transition-colors">Многожильный провод и кабель</h3>
                  <p class="text-xs text-ink-muted font-sans leading-relaxed">Лужение тонких медных жил, капиллярный затек припоя, защита ПВХ-изоляции от оплавления.</p>
                </div>
                <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                  <span class="badge py-0.5 px-1.5">ПОС-61</span>
                  <span class="font-bold text-ink group-hover:underline">Регламент →</span>
                </div>
              </a>

              <!-- Задача 2: Плата -->
              <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pcb" class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-3 hover:border-paper-border-dark transition-all group">
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span class="pill-blue font-bold uppercase">[МОДУЛЬ 02 // ПЛАТА]</span>
                    <span class="font-bold text-accent">240 °C</span>
                  </div>
                  <h3 class="font-bold text-ink text-sm sm:text-base group-hover:text-accent transition-colors">Печатная плата и SMD чипы</h3>
                  <p class="text-xs text-ink-muted font-sans leading-relaxed">Монтаж SMD 0805–1206, QFN безвыводных микросхем и прогрев земляных полигонов GND.</p>
                </div>
                <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                  <span class="badge py-0.5 px-1.5">SAC305 // ROL0</span>
                  <span class="font-bold text-ink group-hover:underline">Регламент →</span>
                </div>
              </a>

              <!-- Задача 3: Медь -->
              <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pipe" class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-3 hover:border-paper-border-dark transition-all group">
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span class="pill-blue font-bold uppercase">[МОДУЛЬ 03 // МЕДЬ]</span>
                    <span class="font-bold text-accent">650 °C</span>
                  </div>
                  <h3 class="font-bold text-ink text-sm sm:text-base group-hover:text-accent transition-colors">Медные трубы и фитинги</h3>
                  <p class="text-xs text-ink-muted font-sans leading-relaxed">Трубы 15–28 мм, капиллярные муфты, газовая горелка и твердый медно-фосфорный припой.</p>
                </div>
                <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                  <span class="badge py-0.5 px-1.5">П-14 // Cu-P</span>
                  <span class="font-bold text-ink group-hover:underline">Регламент →</span>
                </div>
              </a>

              <!-- Задача 4: Экспресс-верстак -->
              <div onclick="if(window.openLabDrawer) window.openLabDrawer();" class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-3 hover:border-paper-border-dark transition-all group cursor-pointer">
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs font-mono">
                    <span class="pill-blue font-bold uppercase">[МОДУЛЬ 04 // ВЕРСТАК]</span>
                    <span class="font-bold text-accent">ВЫДВИЖНОЙ</span>
                  </div>
                  <h3 class="font-bold text-ink text-sm sm:text-base group-hover:text-accent transition-colors">Экспресс-панель верстака</h3>
                  <p class="text-xs text-ink-muted font-sans leading-relaxed">Быстрый расчет ликвидуса сплава, сечения провода по току и выбор флюса без перезагрузки.</p>
                </div>
                <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                  <span class="badge badge-yellow py-0.5 px-1.5">Панель</span>
                  <span class="font-bold text-ink group-hover:underline">Открыть ↗</span>
                </div>
              </div>
            </div>
          </div>

          <!-- СВЕЖИЕ СТАТЬИ ЖУРНАЛА (2-КОЛОНОЧНАЯ СЕТКА) -->
          <div class="space-y-3">
            <div class="flex items-center justify-between border-b border-paper-border pb-2">
              <h2 class="text-base sm:text-lg font-bold font-mono uppercase text-ink flex items-center gap-2">
                <?= tp_icon('book-open', 'w-4 h-4 text-accent') ?>
                <span>Свежие публикации // База знаний</span>
              </h2>
              <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="text-xs font-mono text-ink hover:underline font-bold">Все публикации →</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <?php 
              foreach ($recent_articles as $article): 
                $art_url = !empty($article['url']) ? $article['url'] : tchp_get_article_url($article['slug'] ?? '', $article['id'] ?? 0);
                $card_pill = get_semantic_tag_pill($article['tag_key'] ?? '');
                $art_idx = sprintf('%02d', (int)($article['id'] ?? 1));
              ?>
                <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-all">
                  <?php if (!empty($article['image'])): ?>
                    <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
                      <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="w-full h-36 object-cover object-center" loading="lazy">
                      <div class="absolute bottom-1.5 right-1.5 px-2 py-0.5 bg-paper border border-paper-border text-xs font-mono text-ink-muted rounded">
                        [СХЕМА // FIG. <?= e($article['id']) ?>.0]
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="h-28 rounded border border-paper-border bg-paper-subtle flex flex-col justify-between p-3.5 relative overflow-hidden">
                      <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                        <span class="flex items-center gap-1.5 font-bold text-accent">
                          <?= tp_icon('book-open', 'w-3.5 h-3.5 text-accent') ?>
                          [РЕГЛАМЕНТ-<?= $art_idx ?>]
                        </span>
                        <span class="text-xs font-mono text-ink-faint">#<?= e($article['id']) ?></span>
                      </div>
                      <div class="text-xs font-mono text-ink-faint">Пошаговый регламент // IPC-A-610G</div>
                    </div>
                  <?php endif; ?>

                  <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-mono text-ink-faint">
                      <span class="<?= $card_pill ?> px-1.5 py-0.5 rounded font-mono font-bold uppercase">
                        <?= e($article['tag']) ?>
                      </span>
                      <span>~<?= e($article['read_min'] ?? 4) ?> мин</span>
                    </div>

                    <h3 class="font-bold text-ink text-base font-sans line-clamp-2 leading-snug">
                      <a class="hover:underline decoration-ink underline-offset-2 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded" href="<?= $art_url ?>">
                        <?= e($article['title']) ?>
                      </a>
                    </h3>

                    <p class="text-xs text-ink-muted font-sans line-clamp-2 leading-relaxed">
                      <?= e($article['excerpt']) ?>
                    </p>
                  </div>

                  <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                    <span class="font-hand text-sm font-semibold text-ink">
                      <?= e($article['author'] ?? 'Инженер ОТК') ?>
                    </span>
                    <a class="text-ink hover:underline font-bold inline-flex items-center gap-1 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded" href="<?= $art_url ?>">
                      <span>Читать</span>
                      <span aria-hidden="true">→</span>
                    </a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ТЕМАТИЧЕСКИЕ РАЗДЕЛЫ ЖУРНАЛА -->
          <div class="space-y-3">
            <div class="flex items-center justify-between border-b border-paper-border pb-2">
              <h2 class="text-base sm:text-lg font-bold font-mono uppercase text-ink flex items-center gap-2">
                <?= tp_icon('sliders', 'w-4 h-4 text-accent') ?>
                <span>Тематические разделы</span>
              </h2>
              <span class="text-xs font-mono text-ink-muted">4 РАЗДЕЛА</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <?php 
              $rubric_order = ['start', 'materialy', 'praktika', 'oshibki'];
              foreach ($rubric_order as $rslug):
                if (!isset($RUBRICS[$rslug])) continue;
                $rub = $RUBRICS[$rslug];
              ?>
                <a href="<?= esc_url($site_root) ?>category.php?slug=<?= $rslug ?>" class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-3 hover:border-paper-border-dark transition-all group">
                  <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs font-mono">
                      <span class="badge badge-yellow"><?= e($rub['subbadge']) ?></span>
                      <span class="font-mono text-ink-faint">SEC.0<?= match($rslug) {'start'=>1, 'materialy'=>2, 'praktika'=>3, 'oshibki'=>4} ?></span>
                    </div>
                    <h3 class="font-bold text-ink text-base group-hover:text-accent transition-colors"><?= e($rub['title']) ?></h3>
                    <p class="text-xs text-ink-muted font-sans leading-relaxed line-clamp-2"><?= e($rub['lead']) ?></p>
                  </div>
                  <div class="pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono text-ink-faint">
                    <span><?= count($rub['article_ids'] ?? []) ?> регламентов</span>
                    <span class="font-bold text-ink group-hover:underline">Изучить →</span>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- ИНЖЕНЕРНЫЙ FAQ -->
          <div class="border border-paper-border rounded-lg bg-card p-5 sm:p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between pb-3 border-b border-paper-border">
              <h2 class="text-base sm:text-lg font-bold font-mono uppercase text-ink flex items-center gap-2">
                <?= tp_icon('alert-triangle', 'w-4 h-4 text-accent') ?>
                <span>Частые вопросы // Инженерный FAQ</span>
              </h2>
              <span class="text-xs font-mono text-ink-muted">ПРАКТИКА</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-mono">
              <div class="p-3.5 rounded bg-paper-subtle border border-paper-border space-y-1.5">
                <div class="font-bold text-ink text-xs">Какую температуру выставлять для припоя ПОС-61?</div>
                <p class="text-ink-muted leading-relaxed font-sans text-xs">Рабочее окно жала — 260–290 °C. Ликвидус сплава 183 °C; дельта в 80–100 °C необходима для быстрого прогрева площадок платы без сжигания канифоли.</p>
              </div>
              <div class="p-3.5 rounded bg-paper-subtle border border-paper-border space-y-1.5">
                <div class="font-bold text-ink text-xs">Нужно ли смывать флюс-гель No-Clean (NC-559)?</div>
                <p class="text-ink-muted leading-relaxed font-sans text-xs">В низковольтных схемах остатки пассивны. Но в ВЧ-трактах, цепях выше 50 В и под BGA смывка изопропанолом обязательна из-за риска микротоков утечки.</p>
              </div>
              <div class="p-3.5 rounded bg-paper-subtle border border-paper-border space-y-1.5">
                <div class="font-bold text-ink text-xs">Как не оторвать пятаки при демонтаже разъема?</div>
                <p class="text-ink-muted leading-relaxed font-sans text-xs">Разбавьте тугоплавкий бессвинец каплей сплава Розе (94 °C), удалите расплав медной оплеткой с флюсом и снимайте разъем пинцетом без механического усилия.</p>
              </div>
              <div class="p-3.5 rounded bg-paper-subtle border border-paper-border space-y-1.5">
                <div class="font-bold text-ink text-xs">Какой паяльник выбрать: T12 или JBC C245?</div>
                <p class="text-ink-muted leading-relaxed font-sans text-xs">T12 — надежный стандарт до 70 Вт для домашней лаборатории. C245 с картриджами 130 Вт незаменим для профессионального ремонта многослойных плат с земляными полигонами.</p>
              </div>
            </div>
          </div>

        </div>

        <!-- RIGHT COLUMN: Sticky Engineering Reference Sidebar (4 cols) -->
        <aside class="lg:col-span-4 space-y-6 sticky top-20">

          <!-- 1. WORKBENCH STICKY NOTE (Handwritten Lab Tip) -->
          <div class="sketch-sticky-note p-4 rounded-lg space-y-2 relative shadow-sm" style="transform: rotate(-0.5deg);">
            <div class="sketch-washi-tape washi-tape-postit" aria-hidden="true"></div>
            
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold border-b sketch-divider pb-1 text-ink">
              <span class="flex items-center gap-1.5">
                <?= tp_icon('pin', 'w-3.5 h-3.5 text-accent') ?>
                [ЗАМЕТКА ВЕРСТАКА // БАЗА]
              </span>
              <span class="text-xs opacity-75">БАЗА-01</span>
            </div>

            <p class="font-hand text-base leading-snug italic font-semibold text-ink">
              «Греем не припой, а спаиваемые выводы детали и контактную площадку. Припой сам потечет в зону наивысшей температуры!»
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-xs opacity-80 border-t sketch-divider text-ink-muted">
              <span>Закон теплопередачи</span>
              <span class="font-bold text-ink">ГОСТ / IPC</span>
            </div>
          </div>

          <!-- 2. TECHNICAL SPECIFICATION REFERENCE: INTERACTIVE LIQUIDUS TABLE -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm relative">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span class="flex items-center gap-1.5 text-ink">
                <?= tp_icon('thermometer', 'w-3.5 h-3.5 text-teal-600 dark:text-teal-400') ?>
                [ЛИКВИДУС МЕТАЛЛОВ]
              </span>
              <span class="pill-blue text-xs font-mono uppercase px-2 py-0.5 rounded">ГОСТ 21931</span>
            </div>

            <p class="text-xs font-mono text-ink-muted">
              Кликните марку припоя для просмотра формулы состава:
            </p>

            <div id="liquidus-interactive-list" class="space-y-1.5 font-mono text-xs">
              <!-- Сплав 1: ПОС-61 -->
              <details class="group border border-paper-border/70 rounded bg-paper-subtle/50 transition-colors open:bg-paper-subtle">
                <summary class="flex items-center justify-between p-2 cursor-pointer select-none hover:text-accent transition-colors list-none">
                  <span class="font-bold text-ink group-open:text-accent">ПОС-61 // Sn63Pb37</span>
                  <span class="font-bold text-ink tabular-nums text-xs bg-paper border border-paper-border px-1.5 py-0.5 rounded shadow-2xs">183 °C</span>
                </summary>
                <div class="px-2.5 pb-2.5 pt-1.5 border-t border-paper-border/50 text-xs text-ink-muted space-y-1 bg-paper/60 rounded-b">
                  <div class="flex justify-between"><span>Состав:</span><span class="font-bold text-ink">63% Sn, 37% Pb</span></div>
                  <div class="flex justify-between"><span>Окно жала:</span><span class="text-accent font-bold">260–290 °C</span></div>
                  <div class="flex justify-between"><span>Тип:</span><span>Эвтектика (без кашевидной фазы)</span></div>
                  <div class="pt-1 text-right">
                    <button type="button" class="copy-solder-temp text-xs text-ink hover:underline font-bold" data-temp="183 °C">Скопировать 183 °C</button>
                  </div>
                </div>
              </details>

              <!-- Сплав 2: SAC305 -->
              <details class="group border border-paper-border/70 rounded bg-paper-subtle/50 transition-colors open:bg-paper-subtle">
                <summary class="flex items-center justify-between p-2 cursor-pointer select-none hover:text-accent transition-colors list-none">
                  <span class="font-bold text-ink group-open:text-accent">SAC305 // Бессвинец</span>
                  <span class="font-bold text-ink tabular-nums text-xs bg-paper border border-paper-border px-1.5 py-0.5 rounded shadow-2xs">217 °C</span>
                </summary>
                <div class="px-2.5 pb-2.5 pt-1.5 border-t border-paper-border/50 text-xs text-ink-muted space-y-1 bg-paper/60 rounded-b">
                  <div class="flex justify-between"><span>Состав:</span><span class="font-bold text-ink">96.5% Sn, 3.0% Ag, 0.5% Cu</span></div>
                  <div class="flex justify-between"><span>Окно жала:</span><span class="text-accent font-bold">310–335 °C</span></div>
                  <div class="flex justify-between"><span>Стандарт:</span><span>RoHS / IPC-J-STD-006</span></div>
                  <div class="pt-1 text-right">
                    <button type="button" class="copy-solder-temp text-xs text-ink hover:underline font-bold" data-temp="217 °C">Скопировать 217 °C</button>
                  </div>
                </div>
              </details>

              <!-- Сплав 3: Sn42Bi58 -->
              <details class="group border border-paper-border/70 rounded bg-paper-subtle/50 transition-colors open:bg-paper-subtle">
                <summary class="flex items-center justify-between p-2 cursor-pointer select-none hover:text-accent transition-colors list-none">
                  <span class="font-bold text-ink group-open:text-accent">Sn42Bi58 // Низкотемп.</span>
                  <span class="font-bold text-ink tabular-nums text-xs bg-paper border border-paper-border px-1.5 py-0.5 rounded shadow-2xs">138 °C</span>
                </summary>
                <div class="px-2.5 pb-2.5 pt-1.5 border-t border-paper-border/50 text-xs text-ink-muted space-y-1 bg-paper/60 rounded-b">
                  <div class="flex justify-between"><span>Состав:</span><span class="font-bold text-ink">42% Sn, 58% Bi</span></div>
                  <div class="flex justify-between"><span>Окно жала:</span><span class="text-accent font-bold">200–230 °C</span></div>
                  <div class="flex justify-between"><span>Назначение:</span><span>LED-ленты, термоусадка</span></div>
                  <div class="pt-1 text-right">
                    <button type="button" class="copy-solder-temp text-xs text-ink hover:underline font-bold" data-temp="138 °C">Скопировать 138 °C</button>
                  </div>
                </div>
              </details>

              <!-- Сплав 4: Сплав Розе -->
              <details class="group border border-paper-border/70 rounded bg-paper-subtle/50 transition-colors open:bg-paper-subtle">
                <summary class="flex items-center justify-between p-2 cursor-pointer select-none hover:text-accent transition-colors list-none">
                  <span class="font-bold text-ink group-open:text-accent">Сплав Розе // Демонтаж</span>
                  <span class="font-bold text-ink tabular-nums text-xs bg-paper border border-paper-border px-1.5 py-0.5 rounded shadow-2xs">94 °C</span>
                </summary>
                <div class="px-2.5 pb-2.5 pt-1.5 border-t border-paper-border/50 text-xs text-ink-muted space-y-1 bg-paper/60 rounded-b">
                  <div class="flex justify-between"><span>Состав:</span><span class="font-bold text-ink">25% Sn, 25% Pb, 50% Bi</span></div>
                  <div class="flex justify-between"><span>Окно жала:</span><span class="text-accent font-bold">140–180 °C</span></div>
                  <div class="flex justify-between"><span>Назначение:</span><span>Выпайка разъемов без перегрева</span></div>
                  <div class="pt-1 text-right">
                    <button type="button" class="copy-solder-temp text-xs text-ink hover:underline font-bold" data-temp="94 °C">Скопировать 94 °C</button>
                  </div>
                </div>
              </details>
            </div>

            <div class="pt-1 text-right">
              <a href="<?= esc_url($site_root) ?>interactive.php#table" class="font-mono text-xs text-ink hover:underline font-bold focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded">
                Вся таблица припоев (9 марок) →
              </a>
            </div>
          </div>

          <!-- 3. CONTEXTUAL WARNING CALLOUT -->
          <div class="border border-warning-border rounded-lg bg-warning-bg/30 p-4 space-y-2 shadow-sm">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold border-b border-paper-border pb-1">
              <span class="flex items-center gap-1.5 text-warning">
                <?= tp_icon('alert-triangle', 'w-3.5 h-3.5 text-warning') ?>
                [ЗАЩИТА МЕДИ // ПЕРЕГРЕВ]
              </span>
              <span class="pill-orange text-xs px-2 py-0.5 rounded font-mono font-bold">ВНИМАНИЕ</span>
            </div>

            <p class="font-hand text-base leading-snug italic font-semibold text-ink">
              «Удержание жала горячее 340°C дольше 3 секунд разрушает эпоксидный слой текстолита FR-4 и отрывает контактную дорожку платы.»
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-xs opacity-80 border-t border-paper-border text-ink-muted">
              <span>Деградация текстолита</span>
              <span class="font-bold text-ink">IPC-A-610G</span>
            </div>
          </div>

          <!-- 4. WORKBENCH TOOLS SHORTCUTS -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span>Инструменты верстака</span>
              <span class="pill-blue text-xs px-2 py-0.5 rounded">[03 МОДУЛЯ]</span>
            </div>
            <div class="space-y-2 font-mono text-xs">
              <a href="<?= esc_url($site_root) ?>interactive.php#temp" class="min-h-[44px] flex items-center justify-between px-3 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-xs flex items-center gap-1.5">
                    <?= tp_icon('thermometer', 'w-3.5 h-3.5 text-accent') ?>
                    [01] Термокалькулятор
                  </div>
                  <div class="text-xs text-ink-muted">Подбор °C под провод и припой</div>
                </div>
                <span class="pill-orange text-xs px-2 py-0.5 rounded font-bold">#расчет</span>
              </a>

              <a href="<?= esc_url($site_root) ?>interactive.php#iron" class="min-h-[44px] flex items-center justify-between px-3 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-xs flex items-center gap-1.5">
                    <?= tp_icon('wrench', 'w-3.5 h-3.5 text-accent') ?>
                    [02] Подбор паяльника
                  </div>
                  <div class="text-xs text-ink-muted">Станции T12, C245 под бюджет</div>
                </div>
                <span class="pill-blue text-xs px-2 py-0.5 rounded font-bold">#паяльник</span>
              </a>

              <a href="<?= esc_url($site_root) ?>interactive.php#defect" class="min-h-[44px] flex items-center justify-between px-3 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-xs flex items-center gap-1.5">
                    <?= tp_icon('sliders', 'w-3.5 h-3.5 text-accent') ?>
                    [03] Дерево дефектов
                  </div>
                  <div class="text-xs text-ink-muted">Диагностика причин брака</div>
                </div>
                <span class="pill-orange text-xs px-2 py-0.5 rounded font-bold">#дефекты</span>
              </a>
            </div>
          </div>

          <!-- 5. ЧЕРТЁЖ № 01-Ж // АНАТОМИЯ ЖАЛА -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span>Чертёж № 01-Ж // Жало</span>
              <span class="pill-blue text-xs px-2 py-0.5 rounded">СХЕМА</span>
            </div>
            <div class="rounded border border-paper-border bg-paper p-2 flex items-center justify-center">
              <img 
                src="<?= $assets_base ?>/assets/schematics/tip-cutaway.svg" 
                alt="Разрез паяльного жала: медный сердечник, защитный слой железа и рабочая поверхность" 
                width="300" 
                height="150" 
                class="max-w-full h-auto object-contain"
                loading="lazy"
              />
            </div>
            <p class="text-xs font-mono text-ink-muted leading-relaxed">
              Медный сердечник передаёт тепло, слой железа защищает медь от растворения в расплавленном олове.
            </p>
          </div>

          <!-- 6. МЕТРОЛОГИЯ И НОРМАТИВНАЯ БАЗА -->
          <div class="border border-paper-border rounded-lg bg-paper-subtle p-4 space-y-2 shadow-sm font-mono text-xs">
            <div class="flex items-center justify-between border-b border-paper-border pb-1 text-xs font-bold text-ink uppercase">
              <span>Метрология</span>
              <span class="text-ink-faint">ОТК-2026</span>
            </div>
            <div class="space-y-1 text-ink-muted text-xs">
              <div>• ГОСТ 21930 / 21931: ПОС-61 (183 °C)</div>
              <div>• ISO 9453: Бессвинец SAC305 (217 °C)</div>
              <div>• IPC-A-610G: Стандарты приемки РЭА</div>
              <div>• IEC 61340-5-1: Электростатическая защита</div>
            </div>
          </div>

        </aside>

      </div>

    </div>
  </main>

  <script>
    // Живой интерактивный калькулятор безопасной температуры жала на главной странице
    (function initExpressWorkbench() {
      const solderEl = document.getElementById('exp-solder-select');
      const massEl = document.getElementById('exp-mass-select');
      const tempOutEl = document.getElementById('exp-temp-out');
      const liqValEl = document.getElementById('exp-liq-val');
      const deltaValEl = document.getElementById('exp-delta-val');
      const safetyEl = document.getElementById('exp-safety-badge');
      const copyBtn = document.getElementById('exp-copy-btn');

      if (!solderEl || !massEl || !tempOutEl) return;

      function updateCalc() {
        const sOpt = solderEl.options[solderEl.selectedIndex];
        const mOpt = massEl.options[massEl.selectedIndex];

        const liq = parseInt(sOpt.getAttribute('data-liq') || '183', 10);
        const delta = parseInt(mOpt.getAttribute('data-delta') || '85', 10);
        const temp = liq + delta;

        tempOutEl.textContent = temp;
        liqValEl.textContent = liq + ' °C';
        deltaValEl.textContent = '+' + delta + ' °C';

        if (temp > 340) {
          safetyEl.className = 'pill-orange text-xs font-bold px-2 py-0.5 rounded';
          safetyEl.textContent = 'ОРАНЖЕВАЯ ЗОНА // РИСК ПЕРЕГРЕВА МЕДИ';
        } else if (temp < 220) {
          safetyEl.className = 'pill-yellow text-xs font-bold px-2 py-0.5 rounded';
          safetyEl.textContent = 'НИЗКАЯ ТЕМПЕРАТУРА // ДЕМОНТАЖНЫЙ РЕЖИМ';
        } else {
          safetyEl.className = 'pill-blue text-xs font-bold px-2 py-0.5 rounded';
          safetyEl.textContent = 'ЗЕЛЁНАЯ ЗОНА // БЕЗОПАСНО ДЛЯ ПЛАТЫ';
        }
      }

      solderEl.addEventListener('change', updateCalc);
      massEl.addEventListener('change', updateCalc);

      if (copyBtn) {
        copyBtn.addEventListener('click', function() {
          const val = tempOutEl.textContent + ' °C';
          navigator.clipboard.writeText(val).then(function() {
            const old = copyBtn.textContent;
            copyBtn.textContent = '✓ Скопировано';
            copyBtn.classList.add('border-accent', 'text-accent');
            setTimeout(function() {
              copyBtn.textContent = old;
              copyBtn.classList.remove('border-accent', 'text-accent');
            }, 1500);
          }).catch(function() {
            prompt('Значение температуры:', val);
          });
        });
      }

      // Кнопки копирования в таблице ликвидуса
      document.querySelectorAll('.copy-solder-temp').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const t = btn.getAttribute('data-temp');
          navigator.clipboard.writeText(t).then(function() {
            const oldText = btn.textContent;
            btn.textContent = '✓ Скопировано!';
            setTimeout(function() {
              btn.textContent = oldText;
            }, 1500);
          });
        });
      });
    })();
  </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
