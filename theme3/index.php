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
    <section class="hero" id="hero">
      <div class="container">
        
        <!-- Вертикальный логотип-плашка слева (фирменный элемент «Звук в УХах») -->
        <a href="#hero" class="side-logo" aria-label="ТОЧКА ПЛАВЛЕНИЯ - Наверх">
          <span class="side-logo__text">ТОЧКА ПЛАВЛЕНИЯ</span>
        </a>

        <!-- Панель-капсула с рамкой 3px -->
        <div class="panel">
          <!-- Верхний служебный бар: ОТК бейдж + кнопки Поиск и Верстак -->
          <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 2rem;">
            <div class="badge badge-yellow" style="display: inline-flex; align-items: center; gap: 8px;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #000; display: inline-block;"></span>
              <span>ОТК // ИНЖЕНЕРНЫЙ СПРАВОЧНИК 2026</span>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
              <button id="search-modal-trigger" type="button" class="badge" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; background: #ffffff;" title="Поиск по базе знаний (Ctrl+K)">
                <span class="material-symbols-outlined" style="font-size: 16px;">search</span>
                <span>Поиск</span>
                <span style="font-size: 11px; opacity: 0.7; font-family: monospace;">Ctrl+K</span>
              </button>
              <button type="button" onclick="if(window.openLabDrawer)window.openLabDrawer();" class="badge badge-yellow" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px;" title="Открыть верстак">
                <span class="material-symbols-outlined" style="font-size: 16px;">tune</span>
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
    </section>

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
      <section style="margin: 28px 0 16px;">
        <div id="quick-search-trigger" role="button" tabindex="0" aria-label="Открыть глобальный инженерный поиск по базе (Ctrl+K)"
             class="terminal-search">
          <div style="display: flex; align-items: center; gap: 12px; font-family: monospace; font-size: 0.9rem; color: var(--muted); overflow: hidden;">
            <span class="badge badge-yellow" style="padding: 2px 8px; font-size: 11px;">TERMINAL</span>
            <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">> припой ПОС-61, флюс RMA, жала T12/C245, дефекты пайки...</span>
          </div>
          <kbd style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; font-family: monospace; font-size: 12px; border: 2px solid var(--accent); border-radius: 6px; background: #ffffff; color: var(--ink);">
            <span>Ctrl</span><span>+</span><span>K</span>
          </kbd>
        </div>
      </section>

      <!-- 4. СЕКЦИЯ ЗАДАЧ И АНАТОМИИ ЖАЛА -->
      <section class="section" id="tasks">
        <header class="major">
          <h2><a href="<?= esc_url($site_root) ?>interactive.php">Паяй уверенно // Задачи монтажа</a></h2>
        </header>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; align-items: stretch;">
          
          <!-- Левая колонка: 4 задачи -->
          <div class="tasks-grid">
            
            <!-- Задача 1: Провод -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=wire" class="task-item">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: monospace; font-size: 12px;">
                  <span style="font-weight: 700;">[MOD.01 // ПРОВОД]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>280 °C</span>
                </div>
                <div style="font-weight: 800; font-size: 1.15rem; margin: 4px 0;">Провод / кабель</div>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 4px 0 12px;">Многожильный медный провод, лужение жил, термоусадка</p>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 8px; font-family: monospace; font-size: 11px;">
                <span class="badge" style="padding: 2px 6px;">ПОС-61</span>
                <span style="font-weight: 700;">Регламент →</span>
              </div>
            </a>

            <!-- Задача 2: Плата -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pcb" class="task-item">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: monospace; font-size: 12px;">
                  <span style="font-weight: 700;">[MOD.02 // ПЛАТА]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>240 °C</span>
                </div>
                <div style="font-weight: 800; font-size: 1.15rem; margin: 4px 0;">Печатная плата</div>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 4px 0 12px;">SMD 0805–1206, QFN чипы, земляные полигоны платы</p>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 8px; font-family: monospace; font-size: 11px;">
                <span class="badge" style="padding: 2px 6px;">SAC305 // ROL0</span>
                <span style="font-weight: 700;">Регламент →</span>
              </div>
            </a>

            <!-- Задача 3: Медная труба -->
            <a href="<?= esc_url($site_root) ?>interactive.php?tool=temp&task=pipe" class="task-item">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: monospace; font-size: 12px;">
                  <span style="font-weight: 700;">[MOD.03 // МЕДЬ]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>650 °C</span>
                </div>
                <div style="font-weight: 800; font-size: 1.15rem; margin: 4px 0;">Медная труба</div>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 4px 0 12px;">Трубы 15–28 мм, капиллярные муфты, горелка и твердый припой</p>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 8px; font-family: monospace; font-size: 11px;">
                <span class="badge" style="padding: 2px 6px;">П-14 // Cu-P</span>
                <span style="font-weight: 700;">Регламент →</span>
              </div>
            </a>

            <!-- Задача 4: Экспресс-верстак -->
            <a href="javascript:void(0)" onclick="if(window.openLabDrawer) window.openLabDrawer();" class="task-item">
              <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: monospace; font-size: 12px;">
                  <span style="font-weight: 700;">[MOD.04 // ВЕРСТАК]</span>
                  <span class="temp-pill"><span class="temp-dot"></span>DRAWER</span>
                </div>
                <div style="font-weight: 800; font-size: 1.15rem; margin: 4px 0;">Экспресс-верстак</div>
                <p style="font-size: 0.85rem; color: var(--muted); margin: 4px 0 12px;">Выдвижная панель: мгновенный расчет сплава, тока провода и флюса</p>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 8px; font-family: monospace; font-size: 11px;">
                <span class="badge badge-yellow" style="padding: 2px 6px;">Быстрая панель</span>
                <span style="font-weight: 700;">Открыть ↗</span>
              </div>
            </a>

          </div>

          <!-- Правая колонка: Чертёж № 01-Ж // Анатомия жала -->
          <div class="card" style="padding: 20px; display: flex; flex-direction: column; justify-content: space-between;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 2px solid var(--line); margin-bottom: 14px; font-family: monospace; font-size: 12px;">
                <span style="font-weight: 800; display: flex; align-items: center; gap: 8px;">
                  <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--yellow); border: 1.5px solid #000; display: inline-block;"></span>
                  ЧЕРТЁЖ № 01-Ж // АНАТОМИЯ ЖАЛА
                </span>
                <span class="badge" style="font-size: 10px; padding: 2px 8px;">IPC-J-STD-001</span>
              </div>
              <div style="background: #ffffff; border: 2px solid var(--line); border-radius: 10px; padding: 12px; display: flex; align-items: center; justify-content: center; min-height: 180px;">
                <img 
                  src="<?= $assets_base ?>/assets/schematics/tip-cutaway.svg" 
                  alt="Разрез паяльного жала: медный сердечник, нагреватель и рабочая поверхность" 
                  width="340" 
                  height="180" 
                  style="max-width: 100%; height: auto; object-fit: contain;"
                  loading="eager"
                />
              </div>
            </div>
            <p style="font-family: monospace; font-size: 12px; color: var(--muted); margin: 14px 0 0; text-align: center; line-height: 1.4;">
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                  <span class="badge badge-yellow">
                    <?= htmlspecialchars($article['tag']) ?>
                  </span>
                  <span style="font-family: monospace; font-size: 12px; color: var(--muted);">
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
                  <span class="badge" style="font-family: monospace;">
                    <?= htmlspecialchars($article['author']) ?>
                  </span>
                  <a href="<?= htmlspecialchars($art_url) ?>" class="badge badge-yellow" style="margin-left: auto;">
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

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 18px;">
          <?php 
          $rubric_order = ['start', 'materialy', 'praktika', 'oshibki'];
          foreach ($rubric_order as $rslug):
            if (!isset($RUBRICS[$rslug])) continue;
            $rub = $RUBRICS[$rslug];
          ?>
            <a href="<?= esc_url($site_root) ?>category.php?slug=<?= $rslug ?>" class="card" style="padding: 20px; text-decoration: none; color: inherit;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <span class="badge badge-yellow" style="font-size: 10px;">
                  <?= e($rub['subbadge']) ?>
                </span>
                <span style="font-family: monospace; font-size: 11px; font-weight: 700; color: var(--muted);">
                  SEC.0<?= match($rslug) {'start'=>1, 'materialy'=>2, 'praktika'=>3, 'oshibki'=>4} ?>
                </span>
              </div>
              <h3 style="margin-bottom: 8px;">
                <?= e($rub['title']) ?>
              </h3>
              <p class="excerpt" style="margin-bottom: 16px;">
                <?= e($rub['lead']) ?>
              </p>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 12px; margin-top: auto; font-family: monospace; font-size: 12px;">
                <span class="badge" style="font-size: 10px;">
                  <?= count($rub['article_ids'] ?? []) ?> РЕГЛАМЕНТОВ
                </span>
                <span style="font-weight: 800;">Изучить →</span>
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

        <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px;">
          <?php foreach (get_all_lessons() as $idx => $l): 
            $l_url = function_exists('home_url') ? home_url('/' . urlencode($l['slug']) . '/') : ('/' . urlencode($l['slug']) . '/');
          ?>
            <article class="card" style="padding: 16px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: monospace; font-size: 10px;">
                <span class="badge badge-yellow" style="padding: 1px 6px;">РЕГЛАМЕНТ-<?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                <span style="color: var(--muted);">~<?= $l['read_min'] ?> МИН</span>
              </div>
              <h3 style="font-size: 0.95rem; line-height: 1.35; margin-bottom: 6px;">
                <a href="<?= esc_url($l_url) ?>">
                  <?= htmlspecialchars($l['title']) ?>
                </a>
              </h3>
              <p class="excerpt" style="font-size: 0.8rem; line-height: 1.4; margin-bottom: 12px;">
                <?= htmlspecialchars($l['subtitle']) ?>
              </p>
              <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--line); padding-top: 8px; margin-top: auto; font-family: monospace; font-size: 11px;">
                <span style="color: var(--muted); font-size: 10px;">ЧЕК-ЛИСТ</span>
                <a href="<?= esc_url($l_url) ?>" style="font-weight: 800;">
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

        <div class="card" style="padding: 24px;">
          <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--line); padding-bottom: 10px; margin-bottom: 16px; font-family: monospace; font-size: 12px;">
            <span style="font-weight: 800; display: flex; align-items: center; gap: 8px;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--yellow); border: 1.5px solid #000; display: inline-block;"></span>
              ПАСПОРТ МЕТРОЛОГИЧЕСКОЙ ПОВЕРКИ И НОРМАТИВНОЙ БАЗЫ
            </span>
            <span class="badge" style="font-size: 10px;">ГОСТ & IPC-A-610</span>
          </div>
          <p style="font-size: 1.05rem; line-height: 1.6; margin-bottom: 20px;">
            Температуры плавления берём из нормативных документов и паспортов сплавов. Уставка паяльной станции — практический стартовый ориентир: её корректируют по теплоёмкости детали, жалу и времени контакта.
          </p>
          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; font-family: monospace; font-size: 12px;">
            <div style="padding: 12px; border: 2px solid var(--line); border-radius: 8px; background: var(--bg);">
              <div style="color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase;">Оловянно-свинцовые сплавы</div>
              <div style="font-weight: 800; font-size: 13px; margin: 4px 0;">ГОСТ 21930 / 21931</div>
              <div style="color: var(--muted);">ПОС-61 (183–190°C)</div>
            </div>
            <div style="padding: 12px; border: 2px solid var(--line); border-radius: 8px; background: var(--bg);">
              <div style="color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase;">Бессвинцовые сплавы</div>
              <div style="font-weight: 800; font-size: 13px; margin: 4px 0;">ISO 9453 / Паспорта</div>
              <div style="color: var(--muted);">SAC305 (217–220°C)</div>
            </div>
            <div style="padding: 12px; border: 2px solid var(--line); border-radius: 8px; background: var(--bg);">
              <div style="color: var(--muted); font-size: 10px; font-weight: 700; text-transform: uppercase;">Критерии приёмки соединений</div>
              <div style="font-weight: 800; font-size: 13px; margin: 4px 0;">IPC-A-610G (Класс 2/3)</div>
              <div style="color: var(--muted);">Мениск, галтель, смачивание</div>
            </div>
          </div>
        </div>
      </section>

      <!-- 9. ЧАСТЫЕ ВОПРОСЫ (FAQ) -->
      <section class="section" id="faq">
        <header class="major">
          <h2><a href="#">Частые вопросы // Инженерный FAQ</a></h2>
        </header>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
          
          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; font-family: monospace; font-size: 11px; color: var(--muted); border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 10px;">
              <span style="font-weight: 800;">[ЗАПРОС 01]</span>
              <span>ТЕМПЕРАТУРНЫЙ РЕЖИМ</span>
            </div>
            <h3 style="font-size: 1.05rem; margin-bottom: 8px;">Какую температуру выставить для припоя ПОС-61?</h3>
            <p style="font-size: 0.9rem; color: var(--muted); line-height: 1.5; margin: 0;">
              Припой ПОС-61 плавится при 183–190 °C. На паяльной станции выставляйте <strong>260–290 °C</strong>. Запас в 80–100 °C необходим, чтобы компенсировать отвод тепла в медные дорожки платы.
            </p>
          </div>

          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; font-family: monospace; font-size: 11px; color: var(--muted); border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 10px;">
              <span style="font-weight: 800;">[ЗАПРОС 02]</span>
              <span>ВЫБОР ОБОРУДОВАНИЯ</span>
            </div>
            <h3 style="font-size: 1.05rem; margin-bottom: 8px;">Нужен ли новичку дорогой паяльник вроде JBC?</h3>
            <p style="font-size: 0.9rem; color: var(--muted); line-height: 1.5; margin: 0;">
              Нет. Для старта достаточно станции на картриджах <strong>Hakko T12</strong> (или USB-PD паяльника вроде Pinecil). Они быстро греются и держат температуру благодаря встроенной в жало термопаре.
            </p>
          </div>

          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; font-family: monospace; font-size: 11px; color: var(--muted); border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 10px;">
              <span style="font-weight: 800;">[ЗАПРОС 03]</span>
              <span>ЗАЩИТА ПЛАТЫ</span>
            </div>
            <h3 style="font-size: 1.05rem; margin-bottom: 8px;">Как не отслоить медную дорожку от текстолита?</h3>
            <p style="font-size: 0.9rem; color: var(--muted); line-height: 1.5; margin: 0;">
              Главное правило: <strong>не более 2.5–3 секунд</strong> контакта жала с площадкой. Если припой не плавится за это время — увеличьте площадь контакта жала, но не давите на дорожку силой.
            </p>
          </div>

          <div class="card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; font-family: monospace; font-size: 11px; color: var(--muted); border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 10px;">
              <span style="font-weight: 800;">[ЗАПРОС 04]</span>
              <span>МОНТАЖНАЯ ХИМИЯ</span>
            </div>
            <h3 style="font-size: 1.05rem; margin-bottom: 8px;">Какой флюс выбрать для первого раза и надо ли его смывать?</h3>
            <p style="font-size: 0.9rem; color: var(--muted); line-height: 1.5; margin: 0;">
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

      // Theme toggle
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
