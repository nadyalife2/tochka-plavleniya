<?php
/**
 * Shared header. WordPress owns the document title; Rank Math owns standard SEO meta when active.
 */
$page_title = $page_title ?? 'Точка Плавления // ТЧП';
$page_desc = $page_desc ?? 'Инженерный медиа-портал, открытые регламенты монтажа и интерактивный верстак инженера-электронщика.';
$current_page = $current_page ?? '';
$site_root = function_exists('home_url') ? home_url('/') : '/';
$assets_base = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';
$tchp_rank_math_active = defined('RANK_MATH_VERSION');
$tchp_wp_owns_title = function_exists('wp_head') && function_exists('current_theme_supports') && current_theme_supports('title-tag');
$tchp_head_escape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>
<!DOCTYPE html>
<html <?php if (function_exists('language_attributes')) { language_attributes(); } else { echo 'lang="ru"'; } ?>>
<head>
  <meta charset="<?php echo $tchp_head_escape(function_exists('get_bloginfo') ? get_bloginfo('charset') : 'UTF-8'); ?>">
  <meta content="width=device-width, initial-scale=1.0, viewport-fit=cover" name="viewport">
  <?php if (!$tchp_wp_owns_title): ?>
  <title><?= $tchp_head_escape($page_title) ?></title>
  <?php endif; ?>
  <?php if (!$tchp_rank_math_active): ?>
  <meta name="description" content="<?= $tchp_head_escape($page_desc) ?>">
  <?php if (!empty($canonical_url)): ?>
  <link rel="canonical" href="<?= $tchp_head_escape($canonical_url) ?>">
  <?php endif; ?>
  <meta property="og:type" content="<?= function_exists('is_singular') && is_singular('post') ? 'article' : 'website' ?>"/>
  <meta property="og:title" content="<?= $tchp_head_escape($page_title) ?>"/>
  <meta property="og:description" content="<?= $tchp_head_escape($page_desc) ?>"/>
  <meta property="og:site_name" content="<?= $tchp_head_escape(function_exists('get_bloginfo') ? get_bloginfo('name') : 'ТОЧКА ПЛАВЛЕНИЯ') ?>"/>
  <meta property="og:image" content="<?= $tchp_head_escape($page_og_image ?? ($assets_base . '/assets/img/og-cover.png')) ?>"/>
  <meta name="twitter:card" content="summary_large_image"/>
  <meta name="twitter:title" content="<?= $tchp_head_escape($page_title) ?>"/>
  <meta name="twitter:description" content="<?= $tchp_head_escape($page_desc) ?>"/>
  <meta name="twitter:image" content="<?= $tchp_head_escape($page_og_image ?? ($assets_base . '/assets/img/og-cover.png')) ?>"/>
  <?php endif; ?>

  <!-- Immediate Theme Init Script (Zero FOUC, Default Light like headphones_site) -->
  <script>
    (function() {
      const saved = localStorage.getItem('tp_theme');
      if (saved === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>
  <?php
    $tchp_tokens_path = dirname(__DIR__) . '/assets/css/tokens.css';
    $tchp_hp_path     = dirname(__DIR__) . '/assets/css/headphones-style.css';
    $tchp_tokens_ver  = file_exists($tchp_tokens_path) ? filemtime($tchp_tokens_path) : '1.0.0';
    $tchp_hp_ver      = file_exists($tchp_hp_path) ? filemtime($tchp_hp_path) : '1.0.0';
  ?>
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/tokens.css?v=<?= $tchp_tokens_ver ?>">
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/build.css">
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/base.css">
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/headphones-style.css?v=<?= $tchp_hp_ver ?>">
  <?php if (!empty($extra_head)) echo $extra_head; ?>
  <?php if (function_exists('wp_head')) wp_head(); ?>
  <script>
    window.TCHP_MATERIALS = <?= json_encode(function_exists('tchp_get_materials_registry') ? (tchp_get_materials_registry()['solders'] ?? []) : [], JSON_UNESCAPED_UNICODE) ?>;
    window.TCHP_EXPRESS_MATRIX = <?= json_encode(function_exists('tchp_get_express_workbench_matrix') ? tchp_get_express_workbench_matrix() : [], JSON_UNESCAPED_UNICODE) ?>;
  </script>
</head>
<body class="tp-headphones font-sans min-h-screen flex flex-col justify-between text-base leading-relaxed">
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:bg-accent focus:text-ink focus:px-4 focus:py-2 focus:rounded focus:font-mono focus:text-xs focus:shadow-md focus:outline-none">
    Перейти к основному содержимому
  </a>
  <header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper">
    <div class="max-w-[1140px] mx-auto px-3 sm:px-8 h-14 flex items-center justify-between gap-1.5 sm:gap-6">
      <div class="flex items-center gap-2 sm:gap-6 min-w-0">
        <a class="logo shrink-0" href="<?= $site_root ?>">ТОЧКА<span class="logo-squircle" aria-hidden="true"><span class="logo-dot"></span></span>ПЛАВЛЕНИЯ</a>
        <?php include __DIR__ . '/header-nav.php'; ?>
      </div>
      <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
        <button id="search-modal-trigger" type="button" class="inline-flex w-10 h-10 min-w-[40px] min-h-[40px] border border-paper-border hover:border-paper-border-dark bg-paper text-ink font-mono text-xs rounded transition-all cursor-pointer shadow-sm active:translate-y-0.5 items-center justify-center shrink-0" title="Поиск по базе знаний (Ctrl+K)" aria-label="Поиск по базе знаний">
          <?= tp_icon('search', 'w-4 h-4 text-accent') ?>
        </button>
        <button id="header-drawer-trigger" type="button" onclick="if(window.openLabDrawer)window.openLabDrawer();" class="inline-flex w-10 h-10 min-w-[40px] min-h-[40px] border border-paper-border hover:border-accent bg-paper text-ink font-mono text-xs rounded transition-all cursor-pointer shadow-sm active:translate-y-0.5 group items-center justify-center shrink-0" title="Выдвижной микро-верстак (Сплавы, токи, флюсы)" aria-label="Открыть выдвижной верстак" aria-haspopup="dialog" aria-expanded="false" aria-controls="lab-drawer-backdrop">
          <?= tp_icon('sliders', 'w-4 h-4 text-cyan group-hover:text-accent transition-colors') ?>
        </button>
        <button id="theme-toggle" type="button" class="inline-flex border border-paper-border hover:border-paper-border-dark bg-paper text-ink font-mono text-xs rounded min-h-[40px] h-10 px-2.5 sm:px-3 items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5 shrink-0" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <?= tp_icon('moon', 'w-4 h-4 dark:hidden') ?>
          <?= tp_icon('sun', 'w-4 h-4 hidden dark:inline') ?>
          <span class="text-xs text-ink-muted dark:text-ink font-mono">Тема</span>
        </button>
        <?php if ($current_page === 'interactive'): ?>
          <a class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[44px] border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?= esc_url($site_root) ?>#articles" aria-label="Читать статьи журнала">
            <span>База знаний</span>
            <?= tp_icon('book-open', 'w-3.5 h-3.5') ?>
          </a>
        <?php else: ?>
          <a class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[44px] border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?= esc_url($site_root) ?>interactive.php" aria-label="Открыть интерактивный верстак инженера">
            <span>Верстак</span>
            <?= tp_icon('wrench', 'w-3.5 h-3.5') ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>
