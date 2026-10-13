<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/rubrics-data.php';

$page_title = "Паяй уверенно — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc  = "Инженерный справочник по пайке: точные ориентиры температуры жала, подбор инструмента и правила монтажа без риска перегреть компоненты.";
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
        $link = function_exists('home_url') 
            ? home_url('/' . urlencode($sa['slug']) . '/') 
            : ('/' . urlencode($sa['slug']) . '/');
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

ob_start();
?>
  <link rel="canonical" href="https://tochka-plavleniya.ru/">
  <style>
    .washi-tape-hero { top: -10px; left: 16px; transform: rotate(-2.5deg); }
    .washi-tape-warn { top: -9px; left: 50%; transform: translateX(-50%) rotate(-0.5deg); }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-iteration-count: 1 !important;
        scroll-behavior: auto !important;
      }
    }
  </style>
<?php
$extra_head = ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Content in Authentic Headphones Site («Звук в УХах») Neo-Brutalist Layout -->
  <main id="main-content">

    <!-- 1. ПЕРВЫЙ ЭКРАН (HERO): КАПСУЛА И ВЕРТИКАЛЬНЫЙ ЛОГОТИП В СТИЛЕ HEADPHONES SITE -->
    <header class="hero section" id="top">
      <div class="container">
        
        <!-- Вертикальный логотип-плашка слева (динамический элемент «Звук в УХах») -->
        <a href="#top" class="side-logo" id="sideLogo" aria-label="ТОЧКА ПЛАВЛЕНИЯ - Вернуться к началу">
          <span class="side-logo__text">ТОЧКА<span class="logo-dot">.</span>ПЛАВЛЕНИЯ</span>
        </a>

        <!-- Панель-капсула с рамкой 3px -->
        <div class="panel">
          <!-- Верхний служебный бар: ОТК бейдж + кнопки Поиск и Верстак -->
          <div class="hero-top-bar">
            <div class="badge badge-yellow otk-status-badge">
              <span class="status-dot"></span>
              <span>ОТК // ИНЖЕНЕРНЫЙ СПРАВОЧНИК 2026</span>
            </div>

            <div class="hero-tools">
              <button id="search-modal-trigger" type="button" class="badge badge-btn" title="Поиск по базе знаний (Ctrl+K)" aria-label="Открыть поиск по базе знаний">
                <span class="material-symbols-outlined text-[16px]">search</span>
                <span>Поиск</span>
                <span class="kbd-shortcut">Ctrl+K</span>
              </button>
              <button type="button" onclick="if(window.openLabDrawer)window.openLabDrawer();" class="badge badge-yellow" title="Открыть верстак" aria-label="Открыть интерактивный верстак">
                <span class="material-symbols-outlined text-[16px]">tune</span>
                <span>Верстак</span>
              </button>
            </div>
          </div>

          <!-- Главный манифест в Space Grotesk -->
          <p>СВЕЖИЕ РЕГЛАМЕНТЫ, ТОЧНЫЕ ТЕМПЕРАТУРНЫЕ КАРТЫ ПАЙКИ И ИНТЕРАКТИВНЫЙ ВЕРСТАК. ЖИВАЯ ИНЖЕНЕРИЯ, БЕЗ ЛИШНЕГО ШУМА.</p>

          <!-- Чипы-навигации в нижнем правом углу -->
          <nav class="top-nav" aria-label="Быстрая навигация">
            <ul class="nav-chips">
              <li><a href="<?= esc_url($site_root) ?>" class="active">Главная</a></li>
              <li><a href="<?= esc_url($site_root) ?>category.php?slug=materialy">Материалы</a></li>
              <li><a href="<?= esc_url($site_root) ?>category.php?slug=oborudovanie">Оборудование</a></li>
              <li><a href="<?= esc_url($site_root) ?>interactive.php">Верстак / Тулзы</a></li>
            </ul>
          </nav>
        </div>
      </div>
    </header>

    <!-- 2. СЕКЦИЯ БЕГУЩЕЙ СТРОКИ С ТЕГАМИ (MARQUEE) КАК В HEADPHONES_SITE -->
    <section class="section-tags" aria-label="Популярные темы и теги пайки">
      <div class="marquee">
        <div class="marquee-track">
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#ПОС-61</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Флюс NC-559</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=oborudovanie" class="tag">#Жала T12 / C245</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#Монтаж SMD 0805</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Сплав Розе</a>
          <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp" class="tag">#Калькулятор температуры</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#Пайка медных труб</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Бессвинцовый SAC305</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=oborudovanie" class="tag">#Термовоздушные станции</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#BGA реболлинг</a>
          <!-- Дублирование для бесконечного бесшовного скролла -->
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#ПОС-61</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Флюс NC-559</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=oborudovanie" class="tag">#Жала T12 / C245</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#Монтаж SMD 0805</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Сплав Розе</a>
          <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp" class="tag">#Калькулятор температуры</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#Пайка медных труб</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=materialy" class="tag">#Бессвинцовый SAC305</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=oborudovanie" class="tag">#Термовоздушные станции</a>
          <a href="<?= esc_url($site_root) ?>category.php?slug=praktika" class="tag">#BGA реболлинг</a>
        </div>
      </div>
    </section>

    <div class="container">

      <!-- 3. ИНЖЕНЕРНЫЙ ТЕРМИНАЛ-ПОИСК -->
      <section class="terminal-search-wrapper">
        <div id="quick-search-trigger" role="button" tabindex="0" aria-label="Открыть глобальный инженерный поиск по базе (Ctrl+K)"
             class="terminal-search">
          <div class="terminal-search-inner">
            <span class="badge badge-yellow text-[11px] py-0.5 px-2">TERMINAL</span>
            <span class="truncate">> припой ПОС-61, флюс RMA, жала T12/C245, дефекты пайки...</span>
          </div>
          <kbd class="terminal-search-kbd">
            <span>Ctrl</span><span>+</span><span>K</span>
          </kbd>
        </div>
      </section>

      <!-- 4. СЕКЦИЯ ЗАДАЧ И АНАТОМИИ ЖАЛА -->
      <section class="section" id="tasks">
        <header class="major">
          <h2><a href="<?= esc_url($site_root) ?>interactive.php">Паяй уверенно // Задачи монтажа</a></h2>
        </header>

        <div class="tasks-split-grid">
          
          <!-- Левая колонка: 4 задачи -->
          <div class="tasks-grid">
            
            <!-- Задача 1: Провод -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=wire" class="task-item">
              <div>
                <div class="task-head">
                  <span class="task-head-tag">[MOD.01 // ПРОВОД]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>280 °C</span>
                </div>
                <div class="task-title">Провод / кабель</div>
                <p class="task-desc">Многожильный медный провод, лужение жил, термоусадка</p>
              </div>
              <div class="task-foot">
                <span class="badge py-0.5 px-1.5">ПОС-61</span>
                <span class="task-foot-cta">Регламент →</span>
              </div>
            </a>

            <!-- Задача 2: Плата -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pcb" class="task-item">
              <div>
                <div class="task-head">
                  <span class="task-head-tag">[MOD.02 // ПЛАТА]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>240 °C</span>
                </div>
                <div class="task-title">Печатная плата</div>
                <p class="task-desc">SMD 0805–1206, QFN чипы, земляные полигоны платы</p>
              </div>
              <div class="task-foot">
                <span class="badge py-0.5 px-1.5">SAC305 // ROL0</span>
                <span class="task-foot-cta">Регламент →</span>
              </div>
            </a>

            <!-- Задача 3: Медная труба -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pipe" class="task-item">
              <div>
                <div class="task-head">
                  <span class="task-head-tag">[MOD.03 // МЕДЬ]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>650 °C</span>
                </div>
                <div class="task-title">Медная труба</div>
                <p class="task-desc">Трубы 15–28 мм, капиллярные муфты, горелка и твердый припой</p>
              </div>
              <div class="task-foot">
                <span class="badge py-0.5 px-1.5">П-14 // Cu-P</span>
                <span class="task-foot-cta">Регламент →</span>
              </div>
            </a>

            <!-- Задача 4: Экспресс-верстак -->
            <a href="javascript:void(0)" onclick="if(window.openLabDrawer) window.openLabDrawer();" class="task-item">
              <div>
                <div class="task-head">
                  <span class="task-head-tag">[MOD.04 // ВЕРСТАК]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>DRAWER</span>
                </div>
                <div class="task-title">Экспресс-верстак</div>
                <p class="task-desc">Выдвижная панель: мгновенный расчет сплава, тока провода и флюса</p>
              </div>
              <div class="task-foot">
                <span class="badge badge-yellow py-0.5 px-1.5">Быстрая панель</span>
                <span class="task-foot-cta">Открыть ↗</span>
              </div>
            </a>

          </div>

          <!-- Правая колонка: Чертёж № 01-Ж // Анатомия жала -->
          <div class="card schematic-card">
            <div>
              <div class="schematic-header">
                <span class="schematic-title">
                  <span class="schematic-dot"></span>
                  ЧЕРТЁЖ № 01-Ж // АНАТОМИЯ ЖАЛА
                </span>
                <span class="badge text-[10px] py-0.5 px-2">IPC-J-STD-001</span>
              </div>
              <div class="schematic-canvas">
                <img 
                  src="<?= $assets_base ?>/assets/schematics/tip-cutaway.svg" 
                  alt="Разрез паяльного жала: медный сердечник, нагреватель и рабочая поверхность" 
                  width="340" 
                  height="180" 
                  class="max-w-full h-auto object-contain"
                  loading="eager"
                />
              </div>
            </div>
            <p class="schematic-caption">
              Медный сердечник передаёт тепло, защитный слой железа предотвращает растворение в олове.
            </p>
          </div>

        </div>
      </section>

      <!-- 5. НОВЫЕ СТАТЬИ // АСИММЕТРИЧНАЯ СЕТКА ЖУРНАЛА (asym-grid) -->
      <section class="section" id="articles">
        <header class="major">
          <h2><a href="<?= esc_url($site_root) ?>category.php?slug=materialy">Новые статьи // Журнал</a></h2>
        </header>

        <div class="grid asym-grid">
          <?php 
          $layout_classes = ['card--big', 'card--tall', 'card--mid', 'card--mid'];
          foreach ($recent_articles as $idx => $article): 
            $cls = $layout_classes[$idx] ?? 'card--mid';
            $art_url = !empty($article['url']) ? $article['url'] : (function_exists('home_url') ? home_url('/' . urlencode($article['slug'] ?? '') . '/') : ('/' . urlencode($article['slug'] ?? '') . '/'));
            $art_img = !empty($article['image']) ? $article['image'] : ($assets_base . '/assets/img/og-cover.png');
          ?>
            <article class="card <?= $cls ?>">
              <a href="<?= htmlspecialchars($art_url) ?>">
                <img src="<?= htmlspecialchars($art_img) ?>" class="thumb" alt="<?= htmlspecialchars($article['title']) ?>" loading="lazy" />
              </a>
              <div class="pad">
                <div class="card-meta-top">
                  <span class="badge badge-yellow">
                    <?= htmlspecialchars($article['tag']) ?>
                  </span>
                  <span class="card-read-time">
                    ~<?= (int)$article['read_min'] ?> мин
                  </span>
                </div>
                <h3>
                  <a href="<?= htmlspecialchars($art_url) ?>">
                    <?= htmlspecialchars($article['title']) ?>
                  </a>
                </h3>
                <p class="excerpt">
                  <?= htmlspecialchars($article['excerpt']) ?>
                </p>
                <div class="badges">
                  <span class="badge card-author">
                    <?= htmlspecialchars($article['author']) ?>
                  </span>
                  <a href="<?= htmlspecialchars($art_url) ?>" class="badge badge-yellow ml-auto">
                    Читать →
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 6. РУБРИКИ ЖУРНАЛА (4 архитектурных раздела базы знаний) -->
      <section class="section" id="rubrics">
        <header class="major">
          <h2><a href="<?= esc_url($site_root) ?>category.php?slug=materialy">Тематические разделы</a></h2>
        </header>

        <div class="rubrics-grid">
          <?php 
          $rubric_order = ['start', 'materialy', 'praktika', 'oshibki'];
          foreach ($rubric_order as $rslug):
            if (!isset($RUBRICS[$rslug])) continue;
            $rub = $RUBRICS[$rslug];
          ?>
            <a href="<?= esc_url($site_root) ?>category.php?slug=<?= $rslug ?>" class="card rubric-card">
              <div class="rubric-head">
                <span class="badge badge-yellow text-[10px]">
                  <?= e($rub['subbadge']) ?>
                </span>
                <span class="rubric-sec-num">
                  SEC.0<?= match($rslug) {'start'=>1, 'materialy'=>2, 'praktika'=>3, 'oshibki'=>4} ?>
                </span>
              </div>
              <h3 class="mb-2">
                <?= e($rub['title']) ?>
              </h3>
              <p class="excerpt mb-4">
                <?= e($rub['lead']) ?>
              </p>
              <div class="rubric-foot">
                <span class="badge text-[10px]">
                  <?= count($rub['article_ids'] ?? []) ?> РЕГЛАМЕНТОВ
                </span>
                <span class="font-extrabold">Изучить →</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 7. ПРИКЛАДНЫЕ МИКРОУРОКИ: 10 цеховых регламентов по 2–3 мин -->
      <section class="section" id="lessons">
        <header class="major">
          <h2><a href="<?= esc_url($site_root) ?>category.php?slug=praktika">Прикладные микроуроки монтажа</a></h2>
        </header>

        <div class="lessons-grid">
          <?php foreach (get_all_lessons() as $idx => $l): 
            $l_url = function_exists('home_url') ? home_url('/' . urlencode($l['slug']) . '/') : ('/' . urlencode($l['slug']) . '/');
          ?>
            <article class="card lesson-card">
              <div class="lesson-head">
                <span class="badge badge-yellow py-0.5 px-1.5">РЕГЛАМЕНТ-<?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                <span class="text-ink-muted">~<?= $l['read_min'] ?> МИН</span>
              </div>
              <h3 class="lesson-title">
                <a href="<?= esc_url($l_url) ?>">
                  <?= htmlspecialchars($l['title']) ?>
                </a>
              </h3>
              <p class="excerpt lesson-desc">
                <?= htmlspecialchars($l['subtitle']) ?>
              </p>
              <div class="lesson-foot">
                <span class="text-ink-muted text-[10px]">ЧЕК-ЛИСТ</span>
                <a href="<?= esc_url($l_url) ?>" class="font-extrabold">
                  Открыть →
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 8. БЛОК ДОВЕРИЯ И ИСТОЧНИКОВ -->
      <section class="section" id="trust">
        <header class="major">
          <h2><a href="#">Откуда берутся цифры // Метрология</a></h2>
        </header>

        <div class="card trust-card">
          <div class="trust-header">
            <span class="trust-title">
              <span class="schematic-dot"></span>
              ПАСПОРТ МЕТРОЛОГИЧЕСКОЙ ПОВЕРКИ И НОРМАТИВНОЙ БАЗЫ
            </span>
            <span class="badge text-[10px]">ГОСТ & IPC-A-610</span>
          </div>
          <p class="trust-lead">
            Температуры плавления берём из нормативных документов и паспортов сплавов. Уставка паяльной станции — практический стартовый ориентир: её корректируют по теплоёмкости детали, жалу и времени контакта.
          </p>
          <div class="trust-grid">
            <div class="trust-col">
              <div class="text-ink-muted text-[10px] font-bold uppercase">Оловянно-свинцовые сплавы</div>
              <div class="font-extrabold text-[13px] my-1">ГОСТ 21930 / 21931</div>
              <div class="text-ink-muted">ПОС-61 (183–190°C)</div>
            </div>
            <div class="trust-col">
              <div class="text-ink-muted text-[10px] font-bold uppercase">Бессвинцовые сплавы</div>
              <div class="font-extrabold text-[13px] my-1">ISO 9453 / Паспорта</div>
              <div class="text-ink-muted">SAC305 (217–220°C)</div>
            </div>
            <div class="trust-col">
              <div class="text-ink-muted text-[10px] font-bold uppercase">Критерии приёмки соединений</div>
              <div class="font-extrabold text-[13px] my-1">IPC-A-610G (Класс 2/3)</div>
              <div class="text-ink-muted">Мениск, галтель, смачивание</div>
            </div>
          </div>
        </div>
      </section>

      <!-- 9. ЧАСТЫЕ ВОПРОСЫ (FAQ) -->
      <section class="section" id="faq">
        <header class="major">
          <h2><a href="#">Частые вопросы // Инженерный FAQ</a></h2>
        </header>

        <div class="faq-grid">
          
          <div class="card faq-card">
            <div class="faq-head">
              <span class="faq-head-id">[ЗАПРОС 01]</span>
              <span>ТЕМПЕРАТУРНЫЙ РЕЖИМ</span>
            </div>
            <h3 class="faq-title">Какую температуру выставить для припоя ПОС-61?</h3>
            <p class="faq-desc">
              Припой ПОС-61 плавится при 183–190 °C. На паяльной станции выставляйте <strong>260–290 °C</strong>. Запас в 80–100 °C необходим, чтобы компенсировать отвод тепла в медные дорожки платы.
            </p>
          </div>

          <div class="card faq-card">
            <div class="faq-head">
              <span class="faq-head-id">[ЗАПРОС 02]</span>
              <span>ВЫБОР ОБОРУДОВАНИЯ</span>
            </div>
            <h3 class="faq-title">Нужен ли новичку дорогой паяльник вроде JBC?</h3>
            <p class="faq-desc">
              Нет. Для старта достаточно станции на картриджах <strong>Hakko T12</strong> (или USB-PD паяльника вроде Pinecil). Они быстро греются и держат температуру благодаря встроенной в жало термопаре.
            </p>
          </div>

          <div class="card faq-card">
            <div class="faq-head">
              <span class="faq-head-id">[ЗАПРОС 03]</span>
              <span>ЗАЩИТА ПЛАТЫ</span>
            </div>
            <h3 class="faq-title">Как не отслоить медную дорожку от текстолита?</h3>
            <p class="faq-desc">
              Главное правило: <strong>не более 2.5–3 секунд</strong> контакта жала с площадкой. Если припой не плавится за это время — увеличьте площадь контакта жала, но не давите на дорожку силой.
            </p>
          </div>

          <div class="card faq-card">
            <div class="faq-head">
              <span class="faq-head-id">[ЗАПРОС 04]</span>
              <span>МОНТАЖНАЯ ХИМИЯ</span>
            </div>
            <h3 class="faq-title">Какой флюс выбрать для первого раза и надо ли его смывать?</h3>
            <p class="faq-desc">
              Выбирайте гелевый флюс класса <strong>No-Clean (ROL0)</strong>. Канифоль безопасна, но твердеет; активные кислотные флюсы для плат брать категорически запрещено — их остатки разъедают дорожки.
            </p>
          </div>

        </div>
      </section>

    </div>
  </main>

  <!-- Structured Data: WebSite & FAQPage JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebSite",
        "@id": "https://tochka-plavleniya.ru/#website",
        "url": "https://tochka-plavleniya.ru/",
        "name": "ТОЧКА ПЛАВЛЕНИЯ",
        "description": "Инженерный справочник по пайке, терморежимам и монтажному оборудованию",
        "inLanguage": "ru-RU"
      },
      {
        "@type": "FAQPage",
        "@id": "https://tochka-plavleniya.ru/#faq",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "Какую температуру выставить для припоя ПОС-61?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Припой ПОС-61 плавится в интервале 183–190 °C. На паяльной станции выставляйте 260–290 °C. Это практический стартовый ориентир для мелкой пайки, а не норма ГОСТ: запас в 80–100 °C необходим, чтобы компенсировать отвод тепла в медные дорожки платы."
            }
          },
          {
            "@type": "Question",
            "name": "Нужен ли новичку дорогой паяльник вроде JBC?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Нет. Для старта достаточно станции на картриджах Hakko T12 (или портативного USB-PD паяльника вроде Pinecil/T80). Они быстро греются и держат температуру благодаря встроенной в жало термопаре."
            }
          },
          {
            "@type": "Question",
            "name": "Как не отслоить медную дорожку от текстолита?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Главное правило: не более 2.5–3 секунд контакта жала с площадкой. Если припой не плавится за это время — увеличьте площадь контакта жала или используйте подогрев, но не давите на дорожку силой."
            }
          },
          {
            "@type": "Question",
            "name": "Какой флюс выбрать для первого раза и надо ли его смывать?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Выбирайте гелевый флюс класса No-Clean (ROL0). Канифоль безопасна, но твердеет; водосмывные и активные флюсы новичкам брать нельзя — их остатки вызывают коррозию дорожек."
            }
          }
        ]
      }
    ]
  }
  </script>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Mobile Sticky Quick Bar (Поиск, Верстак, Практика, Старт) -->
  <?php require_once __DIR__ . '/includes/mobile-bar.php'; ?>

  <!-- Tactile Lab Drawer (Teenage Engineering & Emil Kowalski pattern) -->
  <?php require_once __DIR__ . '/includes/lab-drawer.php'; ?>

  <!-- Global Engineering Search Modal (Ctrl+K) -->
  <?php require_once __DIR__ . '/includes/search-modal.php'; ?>

  <!-- Quick-Search Bar Hook & Theme Toggle JS -->
  <script>
    (function() {
      // Quick search click/keydown handler
      const qsTrigger = document.getElementById('quick-search-trigger');
      if (qsTrigger) {
        const openModal = function(e) {
          e.preventDefault();
          const trigger = document.getElementById('search-modal-trigger');
          if (trigger) trigger.click();
        };
        qsTrigger.addEventListener('click', openModal);
        qsTrigger.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            openModal(e);
          }
        });
      }

      // Theme toggle (Defaults to light mode)
      const toggle = document.getElementById('theme-toggle');
      if (toggle) {
        toggle.addEventListener('click', function() {
          const isDark = document.documentElement.classList.toggle('dark');
          localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
        });
      }

      // Dynamic Side-Logo Tracking (Authentic Headphones Site Behavior)
      const sideLogo = document.getElementById('sideLogo');
      const hero = document.getElementById('top') || document.querySelector('.hero');
      const container = document.querySelector('.hero .container') || document.querySelector('.container');
      const topElement = document.getElementById('top') || document.body;

      if (sideLogo) {
        sideLogo.addEventListener('click', function(e) {
          e.preventDefault();
          if (topElement) {
            topElement.scrollIntoView({ behavior: 'smooth' });
          } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
        });

        sideLogo.addEventListener('mousedown', function() {
          this.style.transform = 'scale(0.96)';
        });
        sideLogo.addEventListener('mouseup', function() {
          this.style.transform = 'scale(1)';
        });
        sideLogo.addEventListener('mouseleave', function() {
          this.style.transform = 'scale(1)';
        });

        const GAP = 12;
        function applyFixedLeft() {
          if (!sideLogo || !container) return;
          if (window.innerWidth <= 768) {
            sideLogo.style.left = '';
            return;
          }
          const containerRect = container.getBoundingClientRect();
          const logoWidth = sideLogo.offsetWidth || 36;
          if (containerRect.left > logoWidth + GAP) {
            sideLogo.style.left = (containerRect.left - logoWidth - GAP) + 'px';
          } else {
            sideLogo.style.left = '16px';
          }
        }

        function onScroll() {
          if (!sideLogo || !hero) return;
          const heroTop = hero.getBoundingClientRect().top + window.pageYOffset;

          if (window.innerWidth <= 768) {
            if (window.pageYOffset > 100) {
              sideLogo.classList.add('is-fixed');
            } else {
              sideLogo.classList.remove('is-fixed');
            }
            sideLogo.style.left = '';
            return;
          }

          if (window.pageYOffset >= heroTop + 40) {
            sideLogo.classList.add('is-fixed');
            applyFixedLeft();
          } else {
            sideLogo.classList.remove('is-fixed');
            sideLogo.style.left = '';
          }
        }

        let ticking = false;
        function updateScroll() {
          onScroll();
          ticking = false;
        }

        window.addEventListener('scroll', function() {
          if (!ticking) {
            requestAnimationFrame(updateScroll);
            ticking = true;
          }
        }, { passive: true });

        window.addEventListener('resize', function() {
          if (window.innerWidth <= 768) {
            sideLogo.style.left = '';
            return;
          }
          if (sideLogo.classList.contains('is-fixed')) {
            applyFixedLeft();
          }
        });

        // Initial check on load
        setTimeout(function() {
          if (sideLogo.classList.contains('is-fixed')) {
            applyFixedLeft();
          }
        }, 60);
      }
    })();
  </script>

</body>
</html>
