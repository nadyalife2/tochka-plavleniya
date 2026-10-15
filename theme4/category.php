<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/rubrics-data.php';

// Determine active slug or tag
$slug = $_GET['slug'] ?? null;
$tag_param = $_GET['tag'] ?? null;
$sub_param = $_GET['sub'] ?? 'all';
$sort_param = $_GET['sort'] ?? 'default';

// Detect WordPress queried taxonomy term if inside WP
$queried_cat = null;
$queried_tag = null;
$is_tag_view = false;

if (function_exists('is_category') && is_category()) {
    $queried_cat = get_queried_object();
    if ($queried_cat && !empty($queried_cat->slug)) {
        $raw_slug = $queried_cat->slug;
        $decoded_slug = urldecode($raw_slug);
        if (isset($RUBRICS[$raw_slug])) {
            $slug = $raw_slug;
        } elseif (isset($RUBRICS[$decoded_slug])) {
            $slug = $decoded_slug;
        } elseif (!empty($queried_cat->name)) {
            $cat_name = trim($queried_cat->name);
            if ($cat_name === 'Старт и база' || $cat_name === 'С чего начать') {
                $slug = 'start';
            } elseif ($cat_name === 'Практика и монтаж' || $cat_name === 'Практика') {
                $slug = 'praktika';
            } elseif (strpos($cat_name, 'Дефект') !== false || strpos($cat_name, 'Ошибк') !== false) {
                $slug = 'oshibki';
            } elseif (strpos($cat_name, 'Материал') !== false || strpos($cat_name, 'Сплав') !== false) {
                $slug = 'materialy';
            } else {
                $slug = $raw_slug;
            }
        } else {
            $slug = $raw_slug;
        }
    }
} elseif (function_exists('is_tag') && is_tag()) {
    $queried_tag = get_queried_object();
    if ($queried_tag && !empty($queried_tag->slug)) {
        $slug = $queried_tag->slug;
        $is_tag_view = true;
    }
}

if ($is_tag_view && $queried_tag) {
    $current_rubric = [
        'title'       => 'Метка: #' . $queried_tag->name,
        'tag'         => '#' . $queried_tag->name,
        'lead'        => $queried_tag->description ?: ('Все статьи, регламенты и материалы с меткой #' . $queried_tag->name . '.'),
        'badge'       => 'МЕТКА // БАЗА ЗНАНИЙ',
        'subbadge'    => '#' . $queried_tag->name,
        'article_ids' => [],
        'filter_tags' => [],
        'sub_tags'    => []
    ];
    $current_slug = $slug;
    $current_page = 'tag';
} elseif ($slug && isset($RUBRICS[$slug])) {
    $current_rubric = $RUBRICS[$slug];
    $current_slug   = $current_rubric['slug'] ?? $slug;
    $current_page   = $current_slug;
} elseif ($slug && isset($RUBRICS[urldecode($slug)])) {
    $current_rubric = $RUBRICS[urldecode($slug)];
    $current_slug   = $current_rubric['slug'] ?? 'start';
    $current_page   = $current_slug;
} elseif ($slug && $queried_cat) {
    $current_rubric = [
        'title'       => $queried_cat->name,
        'tag'         => $queried_cat->name,
        'lead'        => $queried_cat->description ?: ('Статьи и материалы рубрики «' . $queried_cat->name . '».'),
        'badge'       => 'РУБРИКА // ЖУРНАЛ',
        'subbadge'    => $queried_cat->name,
        'article_ids' => [],
        'filter_tags' => [$slug],
        'sub_tags'    => []
    ];
    $current_slug   = $slug;
    $current_page   = $slug;
} elseif ($tag_param) {
    $current_slug   = $TAG_TO_SLUG[$tag_param] ?? 'materialy';
    $current_rubric = $RUBRICS[$current_slug] ?? $RUBRICS['materialy'];
    $current_page   = $current_slug;
} else {
    // Полноценный общий архив всех публикаций и регламентов базы знаний
    $all_ids = [];
    foreach ($RUBRICS as $r) {
        if (!empty($r['article_ids'])) {
            $all_ids = array_merge($all_ids, $r['article_ids']);
        }
    }
    $all_ids = array_values(array_unique($all_ids));

    $current_rubric = [
        'title'       => 'Все публикации // База знаний',
        'tag'         => 'ВСЕ РЕГЛАМЕНТЫ',
        'lead'        => 'Полный систематизированный архив инженерных статей, руководств по монтажу РЭА, температурных профилей и стандартов IPC/ГОСТ.',
        'badge'       => 'АРХИВ // БАЗА ЗНАНИЙ',
        'subbadge'    => 'Все статьи',
        'article_ids' => $all_ids,
        'filter_tags' => ['Все', 'Сплавы', 'Флюсы', 'Монтаж', 'BGA', 'T12', 'IPC'],
        'sub_tags'    => [
            ['id' => 'all',       'label' => 'Все материалы',   'count' => count($articles ?? [])],
            ['id' => 'basics',    'label' => 'Старт и база',    'keywords' => ['старт', 'баз', 'паяльник', 'оборудован'], 'count' => 5],
            ['id' => 'solders',   'label' => 'Сплавы и припои', 'keywords' => ['припой', 'пос-61', 'sac305', 'сплав'],    'count' => 6],
            ['id' => 'assembly',  'label' => 'Монтаж и пайка',  'keywords' => ['монтаж', 'пайк', 'лужен', 'картридж'],    'count' => 8],
            ['id' => 'defects',   'label' => 'Дефекты и брак',  'keywords' => ['дефект', 'брак', 'перегрев', 'мостик'],   'count' => 5],
        ]
    ];
    $current_slug   = 'all';
    $current_page   = 'archive';
}

$site_root = function_exists('home_url') ? home_url('/') : '/';
$page_title = $current_rubric['title'] . " — Журнал ТОЧКА ПЛАВЛЕНИЯ";
$page_desc  = $current_rubric['lead'];

if ($is_tag_view && $queried_tag) {
    $canonical_url = get_tag_link($queried_tag->term_id);
} elseif ($queried_cat) {
    $canonical_url = get_category_link($queried_cat->term_id);
} else {
    $canonical_url = $site_root . "category.php?slug=" . urlencode($current_slug);
}

// Collect articles for this rubric
$matched_articles = [];

// 1. Fetch real published WordPress posts for this category / tag
if (function_exists('get_posts')) {
    $wp_args = [
        'numberposts' => 50,
        'post_status' => 'publish',
        'post_type'   => 'post',
        'orderby'     => 'date',
        'order'       => 'DESC'
    ];
    if ($is_tag_view && $queried_tag && !empty($queried_tag->term_id)) {
        $wp_args['tag_id'] = $queried_tag->term_id;
    } elseif ($queried_cat && !empty($queried_cat->term_id)) {
        $wp_args['cat'] = $queried_cat->term_id;
    } elseif ($slug && !$is_tag_view) {
        $wp_args['category_name'] = $slug;
    }
    $wp_posts = get_posts($wp_args);
    foreach ($wp_posts as $p) {
        $cats = get_the_category($p->ID);
        $primary_cat = !empty($cats) ? $cats[0]->name : $current_rubric['title'];
        $cat_slug = !empty($cats) ? $cats[0]->slug : $current_slug;
        $content_text = strip_tags($p->post_content);
        $read_min = max(2, min(15, (int)round(mb_strlen($content_text) / 1100)));
        if ($read_min < 2) $read_min = 3;
        $excerpt = !empty($p->post_excerpt) 
            ? $p->post_excerpt 
            : (function_exists('wp_trim_words') ? wp_trim_words($content_text, 24, '...') : mb_substr($content_text, 0, 150) . '...');
        $author = get_the_author_meta('display_name', $p->post_author) ?: 'Инженер ОТК';
        $thumb = get_the_post_thumbnail_url($p->ID, 'large');

        $matched_articles[] = [
            'id'       => $p->ID,
            'slug'     => $p->post_name,
            'title'    => get_the_title($p->ID),
            'url'      => get_permalink($p->ID),
            'tag'      => $primary_cat,
            'tag_key'  => $cat_slug,
            'read_min' => $read_min,
            'excerpt'  => $excerpt,
            'author'   => $author,
            'date'     => get_the_date('j F Y', $p->ID),
            'image'    => $thumb ?: null,
            'is_wp'    => true
        ];
    }
}

// 2. Append matching static articles if not already present from WP
if (!$is_tag_view) {
    $existing_slugs = array_filter(array_column($matched_articles, 'slug'));
    if (isset($current_rubric['article_ids'])) {
        foreach ($current_rubric['article_ids'] as $aid) {
            foreach ($articles as $art) {
                if ($art['id'] === $aid && empty($art['draft']) && !in_array($art['slug'], $existing_slugs)) {
                    $matched_articles[] = $art;
                    $existing_slugs[] = $art['slug'];
                    break;
                }
            }
        }
    }

    if (!empty($current_rubric['filter_tags'])) {
        foreach ($articles as $art) {
            if (!in_array($art['slug'], $existing_slugs) && empty($art['draft']) && in_array($art['tag_key'], $current_rubric['filter_tags'])) {
                $matched_articles[] = $art;
                $existing_slugs[] = $art['slug'];
            }
        }
    }
}

// Sub-tag filtering logic (if selected)
$active_sub = 'all';
if (!empty($current_rubric['sub_tags']) && $sub_param !== 'all') {
    foreach ($current_rubric['sub_tags'] as $st) {
        if ($st['id'] === $sub_param) {
            $active_sub = $st['id'];
            if (!empty($st['keywords'])) {
                $filtered = array_filter($matched_articles, function($art) use ($st) {
                    $haystack = mb_strtolower(($art['title'] ?? '') . ' ' . ($art['excerpt'] ?? '') . ' ' . ($art['tag'] ?? ''));
                    foreach ($st['keywords'] as $kw) {
                        if (mb_strpos($haystack, mb_strtolower($kw)) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
                $matched_articles = array_values($filtered);
            }
            break;
        }
    }
}

// Sorting logic (if selected)
if ($sort_param === 'time_desc') {
    usort($matched_articles, fn($a, $b) => ($b['read_min'] ?? 0) <=> ($a['read_min'] ?? 0));
} elseif ($sort_param === 'time_asc') {
    usort($matched_articles, fn($a, $b) => ($a['read_min'] ?? 0) <=> ($b['read_min'] ?? 0));
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

// Paginate grid articles (4 items per page)
$page_num = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$paginated = paginate($grid_articles, 4, $page_num);

// Prepare extra head elements: canonical link, schema.org, and custom styling
ob_start();
?>
  <link rel="canonical" href="<?= e($canonical_url) ?>">
  <style>
    .font-hand { font-family: 'Caveat', cursive; }
    .washi-tape-badge {
      top: -6px; left: 16px; width: 44px; height: 14px; transform: rotate(-0.8deg);
    }
    .washi-tape-banner {
      top: -8px; left: 24px; width: 50px; height: 14px; transform: rotate(-0.6deg);
    }
    .washi-tape-postit {
      top: 0px; left: 20px; width: 50px; height: 14px; transform: rotate(-0.8deg);
    }
    .featured-sketch-frame {
      border: 2px solid var(--color-accent);
      box-shadow: var(--shadow-card);
    }
    html.dark .featured-sketch-frame {
      border-color: var(--color-accent-border);
      box-shadow: 0 4px 20px -2px var(--border-strong);
    }
  </style>
  <?= render_rubric_schema($current_rubric, $matched_articles) ?>
<?php
$extra_head = ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Content -->
  <main class="w-full flex-grow pt-8 pb-16" id="main-content">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-8">
      
      <!-- Semantic Breadcrumbs (WCAG compliant) -->
      <nav aria-label="Хлебные крошки" class="text-xs font-mono text-ink-faint flex items-center gap-1.5 flex-wrap">
        <a class="hover:text-ink transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded px-1" href="<?= esc_url($site_root) ?>">Главная</a>
        <span aria-hidden="true">→</span>
        <a class="hover:text-ink transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded px-1" href="<?= esc_url($site_root) ?>index.php#articles">Рубрики журнала</a>
        <span aria-hidden="true">→</span>
        <span class="text-ink font-semibold px-1" aria-current="location"><?= e($current_rubric['title']) ?></span>
      </nav>

      <!-- HERO SECTION (Calm, Engineering Aesthetics with Strict Semantic Highlights) -->
      <section class="border-b border-paper-border pb-8">
        <div class="max-w-3xl space-y-4">
          
          <?php
          $sec_codes = [
              'start'     => 'SEC.01',
              'materialy' => 'SEC.02',
              'praktika'  => 'SEC.03',
              'oshibki'   => 'SEC.04',
          ];
          $current_sec_code = $sec_codes[$current_slug] ?? 'SEC.XX';
          ?>
          <!-- Single Subtle Workshop Stamp -->
          <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-ink font-mono text-xs">
            <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span>
            <span class="font-bold tracking-wider uppercase"><?= e($current_rubric['badge']) ?> // [<?= $current_sec_code ?>]</span>
            <span class="text-ink-faint">|</span>
            <span class="text-stamp text-ink-muted uppercase"><?= e($current_rubric['subbadge']) ?></span>
          </div>

          <!-- Headline -->
          <h1 class="text-3xl sm:text-5xl font-bold text-ink tracking-tight font-sans">
            <?= e($current_rubric['title']) ?>
          </h1>

          <!-- Lead Paragraph: Calm, Editorial Typography -->
          <p class="text-base sm:text-lg text-ink font-serif leading-[1.65]">
            <?php if ($current_slug === 'materialy'): ?>
              Разбираем металлургию и химию радиомонтажа: свойства припоев (<strong>ПОС-61, SAC305</strong>), классификацию флюсов (<strong>RMA и No-Clean</strong>), риск разрушения шва от сплава Розе и защиту от токов утечки.
            <?php elseif ($current_slug === 'start'): ?>
              Первые шаги в радиомонтаже: выбор паяльной станции, работа с канифолью, смачиваемость меди и защита контактных площадок от перегрева.
            <?php elseif ($current_slug === 'praktika'): ?>
              Техника точного ручного монтажа: работа с компонентами <strong>SMD 0402–1206</strong>, картриджи <strong>T12 и C245</strong>, дозировка пасты и пайка QFN-микросхем.
            <?php elseif ($current_slug === 'oshibki'): ?>
              Диагностика и устранение типового брака: дефекты <strong>tombstoning</strong>, паразитные перемычки припоя, неактивированный флюс и сбои термопрофиля.
            <?php else: ?>
              <?= e($current_rubric['lead']) ?>
            <?php endif; ?>
          </p>

        </div>
      </section>

      <!-- CATEGORY NAVIGATION PILLS: Semantic Identity Color Coding -->
      <nav aria-label="Переключение рубрик журнала" class="flex items-center gap-2 overflow-x-auto pb-2 font-mono text-xs">
        <?php
        $nav_pills = [
            ['slug' => 'start',     'code' => '01', 'label' => 'Начать паять',     'dot' => 'bg-amber-400'],
            ['slug' => 'materialy', 'code' => '02', 'label' => 'Материалы и сплавы', 'dot' => 'bg-orange-500'],
            ['slug' => 'praktika',  'code' => '03', 'label' => 'Практика монтажа',   'dot' => 'bg-sky-500'],
            ['slug' => 'oshibki',   'code' => '04', 'label' => 'Проблемы и дефекты', 'dot' => 'bg-amber-500'],
        ];
        foreach ($nav_pills as $pill):
            $is_curr = ($current_slug === $pill['slug']);
            $pill_url = esc_url($site_root) . "category.php?slug=" . $pill['slug'];
        ?>
          <a href="<?= $pill_url ?>"
             <?= $is_curr ? 'aria-current="page"' : '' ?>
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded transition-all whitespace-nowrap text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none <?= $is_curr ? 'bg-ink text-paper font-bold shadow-sm' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $pill['dot'] ?>" aria-hidden="true"></span>
            <span class="text-stamp opacity-75 font-mono">[SEC.<?= $pill['code'] ?>]</span>
            <span><?= $pill['label'] ?></span>
          </a>
        <?php endforeach; ?>
        <a href="<?= esc_url($site_root) ?>index.php#articles" class="px-3 py-1.5 rounded border border-paper-border bg-paper text-ink-faint hover:text-ink transition-colors whitespace-nowrap ml-auto text-xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          Все статьи журнала →
        </a>
      </nav>

      <!-- SUB-TAG FILTERS & IN-RUBRIC SORTING BAR -->
      <?php if (!empty($current_rubric['sub_tags'])): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 pt-1 pb-2 border-b border-paper-border/60">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs font-mono text-ink-muted uppercase mr-1">[ФИЛЬТР ТЕМЫ]:</span>
            <?php foreach ($current_rubric['sub_tags'] as $st): 
              $is_active_st = ($active_sub === $st['id']);
              $st_url = esc_url($site_root) . "category.php?slug=" . urlencode($current_slug) . ($st['id'] !== 'all' ? "&sub=" . urlencode($st['id']) : '') . ($sort_param !== 'default' ? "&sort=" . urlencode($sort_param) : '');
            ?>
              <a href="<?= e($st_url) ?>"
                 class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-mono transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none <?= $is_active_st ? 'bg-paper-border-dark text-ink font-bold border border-ink/40' : 'bg-paper border border-paper-border text-ink-muted hover:border-ink/30 hover:text-ink' ?>">
                <?php if (!empty($st['icon'])): ?>
                  <?= tp_icon($st['icon'], 'w-3.5 h-3.5 text-accent') ?>
                <?php endif; ?>
                <span><?= e($st['label']) ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <!-- Quick Sorting Options -->
          <div class="flex items-center gap-2 text-xs font-mono text-ink-muted ml-auto">
            <span>[РЕЖИМ ЧТЕНИЯ]:</span>
            <a href="<?= esc_url($site_root) ?>category.php?slug=<?= urlencode($current_slug) ?>&sub=<?= urlencode($active_sub) ?>&sort=<?= $sort_param === 'time_asc' ? 'time_desc' : 'time_asc' ?>"
               class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-paper-border bg-paper hover:border-ink/40 text-ink transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
              <span><?= $sort_param === 'time_desc' ? 'Сначала длинные ↓' : ($sort_param === 'time_asc' ? 'Сначала быстрые ↑' : 'По умолчанию') ?></span>
            </a>
          </div>
        </div>
      <?php endif; ?>

      <!-- MAIN EDITORIAL LAYOUT: 8 COLS (Articles) + 4 COLS (Sidebar) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- LEFT COLUMN: Articles + Contextual CTA (8 cols) -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- CONTEXTUAL INTERACTIVE CTA BANNER (Engineering Standard / Tool) -->
          <?php if (!empty($current_rubric['cta'])): ?>
            <div class="sketch-card p-5 sm:p-6 bg-card space-y-3 relative overflow-hidden">
              <div class="flex items-center justify-between gap-2 pt-1">
                <div class="flex items-center gap-2">
                  <span class="pill-blue text-xs font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded flex items-center gap-1.5">
                    <?= tp_icon('wrench', 'w-3.5 h-3.5 text-accent') ?>
                    [ИНТЕРАКТИВНЫЙ ВЕРСТАК // ИНСТРУМЕНТ-01]
                  </span>
                </div>
                <span class="text-xs font-mono text-ink-faint">СТАНДАРТ IPC / ГОСТ Р</span>
              </div>

              <h2 class="text-lg sm:text-xl font-bold text-ink font-sans">
                <?= e($current_rubric['cta']['title']) ?>
              </h2>
              
              <p class="text-xs sm:text-sm text-ink-muted font-mono leading-relaxed">
                <?= e($current_rubric['cta']['desc']) ?>
              </p>

              <div class="pt-2 flex flex-wrap items-center gap-2.5">
                <?php if (!empty($current_rubric['cta']['link1'])): ?>
                  <a href="<?= e($current_rubric['cta']['link1']) ?>" class="btn-primary inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-mono focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                    <span><?= e($current_rubric['cta']['lbl1']) ?></span>
                  </a>
                <?php endif; ?>
                <?php if (!empty($current_rubric['cta']['link2'])): ?>
                  <a href="<?= e($current_rubric['cta']['link2']) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink font-mono text-xs hover:border-accent hover:text-accent transition-colors shadow-2xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                    <span><?= e($current_rubric['cta']['lbl2']) ?></span>
                    <span class="text-xs" aria-hidden="true">→</span>
                  </a>
                <?php endif; ?>
                <?php if (!empty($current_rubric['cta']['link3'])): ?>
                  <a href="<?= e($current_rubric['cta']['link3']) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded border border-paper-border bg-paper text-ink font-mono text-xs hover:border-accent hover:text-accent transition-colors shadow-2xs focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                    <span><?= e($current_rubric['cta']['lbl3']) ?></span>
                    <span class="text-xs" aria-hidden="true">→</span>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- FEATURED MAIN ARTICLE (Visually Elevated with Engineering Stroke & Stamp Badge) -->
          <?php if ($featured_article): 
            $f_url = !empty($featured_article['url']) ? $featured_article['url'] : tchp_get_article_url($featured_article['slug'] ?? '', $featured_article['id'] ?? 0);
            $f_tag_pill = get_semantic_tag_pill($featured_article['tag_key'] ?? 'materials');
          ?>
            <article class="featured-sketch-frame rounded-lg bg-card p-6 sm:p-7 space-y-5 transition-all relative">
              
              <?php if (!empty($featured_article['image'])): ?>
                <!-- Image Frame with FIG badge -->
                <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
                  <img src="<?= e($featured_article['image']) ?>" alt="<?= e($featured_article['title']) ?>" class="w-full h-56 sm:h-64 object-contain object-center bg-paper-subtle p-2" loading="lazy">
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
                  <span>RMA-223: <span class="hl-yellow text-ink font-semibold">активный флюс</span></span>
                  <span class="text-ink-faint" aria-hidden="true">→</span>
                  <span>NC-559: <span class="hl-yellow text-ink font-semibold">No-Clean гель</span></span>
                  <span class="text-ink-faint" aria-hidden="true">→</span>
                  <span>WS: <span class="hl-blue text-ink font-semibold">водосмывной</span></span>
                </div>
                <span class="pill-blue text-xs font-mono font-bold px-2 py-0.5 rounded ml-2 shrink-0">ГОСТ 21931</span>
              </div>

              <!-- Card Bottom Bar -->
              <div class="flex items-center justify-between pt-3 border-t border-paper-border font-mono text-xs text-ink-muted flex-wrap gap-2">
                <div class="flex items-center gap-2">
                  <span class="<?= $f_tag_pill ?> px-2 py-0.5 rounded font-mono text-xs font-bold"><?= e($featured_article['tag']) ?></span>
                  <span class="text-ink-faint">·</span>
                  <span><?= e($featured_article['date'] ?? '2026') ?></span>
                </div>
                <a class="btn-primary inline-flex items-center gap-1.5 px-4 py-2 text-xs font-mono font-bold focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none" href="<?= $f_url ?>">
                  <span>Читать флагманский разбор</span>
                  <span aria-hidden="true">→</span>
                </a>
              </div>

            </article>
          <?php endif; ?>

          <?php if (empty($paginated['items']) && !$featured_article): ?>
            <!-- Hardened Empty State for filtered/empty categories -->
            <div class="border border-dashed border-paper-border rounded-lg bg-card p-8 sm:p-12 text-center space-y-4 my-6">
              <div class="w-12 h-12 rounded-full border border-paper-border bg-paper-subtle text-accent inline-flex items-center justify-center mx-auto">
                <?= tp_icon('search', 'w-6 h-6 text-accent') ?>
              </div>
              <div class="space-y-1">
                <h3 class="font-bold text-ink text-lg font-sans">В этой выборке пока нет опубликованных материалов</h3>
                <p class="text-sm text-ink-muted max-w-md mx-auto font-sans leading-relaxed">
                  Попробуйте сбросить фильтр или перейти в другие разделы базы знаний по радиомонтажу.
                </p>
              </div>
              <div class="pt-2">
                <a href="<?= esc_url($site_root) ?>category.php?slug=<?= urlencode($current_slug) ?>" class="btn-secondary text-xs font-mono inline-flex items-center gap-1.5 px-3 py-1.5 rounded">
                  <span>Сбросить фильтры</span>
                  <span aria-hidden="true">↺</span>
                </a>
              </div>
            </div>
          <?php endif; ?>

          <!-- 2-COLUMN ARTICLE CARDS GRID: Semantic Tag Color Assignment -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php 
            foreach ($paginated['items'] as $article): 
              $art_url = !empty($article['url']) ? $article['url'] : tchp_get_article_url($article['slug'] ?? '', $article['id'] ?? 0);
              $card_pill = get_semantic_tag_pill($article['tag_key'] ?? '');
              $art_idx = sprintf('%02d', (int)($article['id'] ?? 1));
            ?>
              <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-all">
                <?php if (!empty($article['image'])): ?>
                  <!-- Card Image Frame -->
                  <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
                    <img src="<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="w-full h-36 object-contain object-center bg-paper-subtle p-2" loading="lazy">
                    <div class="absolute bottom-1.5 right-1.5 px-2 py-0.5 bg-paper border border-paper-border text-stamp font-mono text-ink-muted rounded">
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
                      <span class="text-stamp font-mono text-ink-faint">#<?= e($article['id']) ?></span>
                    </div>
                    <div class="text-stamp font-mono text-ink-faint">Пошаговый регламент // IPC-A-610G</div>
                  </div>
                <?php endif; ?>

                <div class="space-y-2">
                  <div class="flex items-center justify-between text-xs font-mono text-ink-faint">
                    <span class="<?= $card_pill ?> px-1.5 py-0.5 rounded text-stamp font-mono font-bold uppercase">
                      <?= e($article['tag']) ?>
                    </span>
                    <span>~<?= e($article['read_min']) ?> мин</span>
                  </div>

                  <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                    <a class="text-ink hover:underline transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded" href="<?= $art_url ?>">
                      <?= e($article['title']) ?>
                    </a>
                  </h3>

                  <p class="text-sm text-ink-muted leading-relaxed line-clamp-3">
                    <?= e($article['excerpt']) ?>
                  </p>
                </div>

                <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-muted font-hand text-base font-bold italic"><?= e($article['author'] ?? 'Мария Канифоль') ?></span>
                  <a class="text-ink font-bold hover:underline flex items-center gap-1 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded px-1 py-0.5" href="<?= $art_url ?>">
                    <span>Читать</span>
                    <span aria-hidden="true">→</span>
                  </a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <!-- EDITORIAL PAGINATION (Semantic WCAG Nav with Prev/Next buttons) -->
          <?php 
          $base_pag_url = esc_url($site_root) . "category.php?slug=" . urlencode($current_slug) . ($active_sub !== 'all' ? "&sub=" . urlencode($active_sub) : '') . ($sort_param !== 'default' ? "&sort=" . urlencode($sort_param) : '');
          if ($paginated['total_pages'] > 1): 
          ?>
            <nav aria-label="Пагинация рубрики" class="flex items-center justify-center gap-2 pt-6 font-mono text-xs flex-wrap">
              <!-- Previous Button -->
              <?php if ($page_num > 1): ?>
                <a href="<?= $base_pag_url ?>&page=<?= $page_num - 1 ?>"
                   class="min-h-[44px] px-3.5 rounded flex items-center gap-1 border border-paper-border bg-paper text-ink hover:border-accent transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                  <span aria-hidden="true">←</span>
                  <span>Предыдущая</span>
                </a>
              <?php else: ?>
                <span aria-disabled="true" class="min-h-[44px] px-3.5 rounded flex items-center gap-1 border border-paper-border/40 bg-paper/50 text-ink-faint cursor-not-allowed">
                  <span aria-hidden="true">←</span>
                  <span>Предыдущая</span>
                </span>
              <?php endif; ?>

              <!-- Page Number Buttons -->
              <?php for ($p = 1; $p <= $paginated['total_pages']; $p++): 
                $is_curr_p = ($p === $page_num);
              ?>
                <a href="<?= $base_pag_url ?>&page=<?= $p ?>"
                   <?= $is_curr_p ? 'aria-current="page"' : '' ?>
                   class="min-h-[44px] min-w-[44px] px-3 rounded flex items-center justify-center border transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none <?= $is_curr_p ? 'bg-ink text-paper font-bold border-ink shadow-sm' : 'border-paper-border bg-paper text-ink hover:border-accent' ?>">
                  <?= $p ?>
                </a>
              <?php endfor; ?>

              <!-- Next Button -->
              <?php if ($page_num < $paginated['total_pages']): ?>
                <a href="<?= $base_pag_url ?>&page=<?= $page_num + 1 ?>"
                   class="min-h-[44px] px-3.5 rounded flex items-center gap-1 border border-paper-border bg-paper text-ink hover:border-accent transition-colors focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                  <span>Следующая</span>
                  <span aria-hidden="true">→</span>
                </a>
              <?php else: ?>
                <span aria-disabled="true" class="min-h-[44px] px-3.5 rounded flex items-center gap-1 border border-paper-border/40 bg-paper/50 text-ink-faint cursor-not-allowed">
                  <span>Следующая</span>
                  <span aria-hidden="true">→</span>
                </span>
              <?php endif; ?>
            </nav>
          <?php endif; ?>

        </div>

        <!-- RIGHT COLUMN: SIDEBAR (Contextual Strict Semantic Workbench Cards) -->
        <aside class="lg:col-span-4 space-y-5">
          
          <!-- CONTEXTUAL POST-IT NOTE: Workshop Master Note -->
          <?php 
          $sb = $current_rubric['sidebar'] ?? [];
          $sb_note = $sb['note'] ?? [
              'label' => 'Заметка верстака // Химия',
              'code' => 'FLUX-QC',
              'text' => '«Канифоль активируется при 150°C, но сгорает в золу при 300°C. Если жало дымит чёрным — убавь нагрев станции!»',
              'footer_l' => 'Норма нагрева флюса',
              'footer_r' => 'IPC-TM-650'
          ];
          ?>
          <div class="sketch-sticky-note p-4 rounded-lg space-y-2 relative shadow-sm" style="transform: rotate(-0.5deg);">
            <div class="sketch-washi-tape washi-tape-postit" aria-hidden="true"></div>
            
            <div class="flex items-center justify-between font-mono text-stamp uppercase font-bold border-b sketch-divider pb-1 text-ink">
              <span class="flex items-center gap-1.5">
                <?= tp_icon('pin', 'w-3.5 h-3.5 text-accent') ?>
                [<?= e($sb_note['label']) ?>]
              </span>
              <span class="text-stamp opacity-75"><?= e($sb_note['code']) ?></span>
            </div>

            <p class="font-hand text-base leading-snug italic font-semibold text-ink">
              <?= e($sb_note['text']) ?>
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-xs opacity-80 border-t sketch-divider text-ink-muted">
              <span><?= e($sb_note['footer_l']) ?></span>
              <span class="font-bold text-ink"><?= e($sb_note['footer_r']) ?></span>
            </div>
          </div>

          <!-- TECHNICAL SPECIFICATION REFERENCE (Contextual Clean Reference Card) -->
          <?php 
          $sb_spec = $sb['spec'] ?? [
              'title' => 'Плавление припоев',
              'tag' => 'ГОСТ / IPC',
              'tag_pill' => 'pill-blue',
              'rows' => [
                  ['label' => 'ПОС-61 (ГОСТ 21931)',  'val' => '183–190 °C'],
                  ['label' => 'Sn63Pb37 (Эвтектика)', 'val' => '183 °C'],
                  ['label' => 'SAC305 (RoHS)',        'val' => '217–220 °C'],
              ],
              'more_link' => esc_url($site_root) . 'interactive.php#table',
              'more_text' => 'Вся таблица припоев →'
          ];
          ?>
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm relative">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span class="flex items-center gap-1.5 text-ink">
                <?= tp_icon('thermometer', 'w-3.5 h-3.5 text-teal-600 dark:text-teal-400') ?>
                [<?= e($sb_spec['title']) ?>]
              </span>
              <span class="<?= e($sb_spec['tag_pill']) ?> text-stamp font-mono uppercase px-2 py-0.5 rounded"><?= e($sb_spec['tag']) ?></span>
            </div>

            <div class="space-y-1.5 font-mono text-xs">
              <?php foreach ($sb_spec['rows'] as $r): ?>
                <div class="flex items-center justify-between py-1 border-b border-paper-border/50">
                  <span class="text-ink"><?= e($r['label']) ?></span>
                  <span class="font-bold text-ink text-xs"><?= e($r['val']) ?></span>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="pt-1 text-right">
              <a href="<?= e($sb_spec['more_link']) ?>" class="font-mono text-xs text-ink hover:underline font-bold focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded">
                <?= e($sb_spec['more_text']) ?>
              </a>
            </div>
          </div>

          <!-- CONTEXTUAL WARNING CALLOUT (Defect Warning / Engineering Standard) -->
          <?php 
          $sb_warn = $sb['warning'] ?? [
              'title' => 'Риск брака // Сплав Розе',
              'badge' => 'ОСТОРОЖНО',
              'text' => '«Сплав Розе (94°C) — строго для демонтажа! После снятия разъёма остатки сплава нужно насухо вычистить оплёткой.»',
              'footer_l' => 'Хрупкость шва',
              'footer_r' => 'Бинарная эвтектика'
          ];
          ?>
          <div class="border border-warning-border rounded-lg bg-warning-bg/30 p-4 space-y-2 shadow-sm">
            <div class="flex items-center justify-between font-mono text-stamp uppercase font-bold border-b border-paper-border pb-1">
              <span class="flex items-center gap-1.5 text-warning">
                <?= tp_icon('alert-triangle', 'w-3.5 h-3.5 text-warning') ?>
                [<?= e($sb_warn['title']) ?>]
              </span>
              <span class="pill-orange text-stamp px-2 py-0.5 rounded font-mono font-bold"><?= e($sb_warn['badge']) ?></span>
            </div>

            <p class="font-hand text-base leading-snug italic font-semibold text-ink">
              <?= e($sb_warn['text']) ?>
            </p>

            <div class="pt-1 flex items-center justify-between font-mono text-xs opacity-80 border-t border-paper-border text-ink-muted">
              <span><?= e($sb_warn['footer_l']) ?></span>
              <span class="font-bold text-ink"><?= e($sb_warn['footer_r']) ?></span>
            </div>
          </div>

          <!-- WORKBENCH TOOLS: Semantically Coded Shortcuts with 44px touch targets -->
          <div class="border border-paper-border rounded-lg bg-card p-4 space-y-3 shadow-sm">
            <div class="flex items-center justify-between font-mono text-xs uppercase font-bold text-ink-muted border-b border-paper-border pb-1.5">
              <span>Инструменты верстака</span>
              <span class="pill-blue text-stamp px-2 py-0.5 rounded">[03 МОДУЛЯ]</span>
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
                <span class="pill-orange text-stamp px-2 py-0.5 rounded font-bold">#расчет</span>
              </a>

              <a href="<?= esc_url($site_root) ?>interactive.php#iron" class="min-h-[44px] flex items-center justify-between px-3 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-xs flex items-center gap-1.5">
                    <?= tp_icon('wrench', 'w-3.5 h-3.5 text-accent') ?>
                    [02] Подбор паяльника
                  </div>
                  <div class="text-xs text-ink-muted">Станции T12, C245 под бюджет</div>
                </div>
                <span class="pill-blue text-stamp px-2 py-0.5 rounded font-bold">#паяльник</span>
              </a>

              <a href="<?= esc_url($site_root) ?>interactive.php#defect" class="min-h-[44px] flex items-center justify-between px-3 py-2 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
                <div>
                  <div class="font-bold group-hover:text-accent transition-colors text-xs flex items-center gap-1.5">
                    <?= tp_icon('sliders', 'w-3.5 h-3.5 text-accent') ?>
                    [03] Дерево дефектов
                  </div>
                  <div class="text-xs text-ink-muted">Диагностика причин брака</div>
                </div>
                <span class="pill-orange text-stamp px-2 py-0.5 rounded font-bold">#дефекты</span>
              </a>
            </div>
          </div>

        </aside>

      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Mobile Sticky Quick Bar (44px WCAG Touch Targets) -->
  <?php require_once __DIR__ . '/includes/mobile-bar.php'; ?>

  <!-- Tactile Lab Drawer (Teenage Engineering & Emil Kowalski pattern) -->
  <?php require_once __DIR__ . '/includes/lab-drawer.php'; ?>

  <!-- Global Engineering Search Modal (Ctrl+K) -->
  <?php require_once __DIR__ . '/includes/search-modal.php'; ?>

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
