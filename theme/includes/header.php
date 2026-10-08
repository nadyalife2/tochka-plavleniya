<?php
/**
 * header.php — Единый шаблон шапки сайта ТОЧКА ПЛАВЛЕНИЯ (Editorial Lab)
 * 
 * Параметры:
 * - $page_title (string): Заголовок страницы
 * - $page_desc (string): Мета-описание (SEO)
 * - $current_page (string): Активный пункт навигации ('index', 'article', 'interactive', 'start', etc.)
 * - $extra_head (string): Дополнительные теги в <head>
 */
$page_title = $page_title ?? 'Точка Плавления // ТЧП';
$page_desc  = $page_desc ?? 'Инженерный медиа-портал, открытые регламенты монтажа и интерактивный верстак инженера-электронщика.';
$current_page = $current_page ?? '';

$site_root = function_exists('home_url') ? home_url('/') : '/';
$assets_base = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';
?>
<!DOCTYPE html>
<html lang="ru" <?php if (function_exists('language_attributes')) language_attributes(); ?>>
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?= htmlspecialchars($page_title) ?></title>
  <meta name="description" content="<?= htmlspecialchars($page_desc) ?>">

  <!-- OpenGraph Meta -->
  <meta property="og:type" content="website"/>
  <meta property="og:title" content="<?= htmlspecialchars($page_title) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta property="og:site_name" content="ТОЧКА ПЛАВЛЕНИЯ"/>
  <meta property="og:image" content="<?= htmlspecialchars($page_og_image ?? ($assets_base . '/assets/img/og-cover.png')) ?>"/>

  <!-- Twitter Card Meta -->
  <meta name="twitter:card" content="summary_large_image"/>
  <meta name="twitter:title" content="<?= htmlspecialchars($page_title) ?>"/>
  <meta name="twitter:description" content="<?= htmlspecialchars($page_desc) ?>"/>
  <meta name="twitter:image" content="<?= htmlspecialchars($page_og_image ?? ($assets_base . '/assets/img/og-cover.png')) ?>"/>

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

  <!-- Preconnect for Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Design Tokens (Single Source of Truth) -->
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/tokens.css">

  <!-- Self-Hosted Fonts & Compiled Tailwind CSS -->
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/fonts.css">
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/build.css">
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Base UI Components & Reset -->
  <link rel="stylesheet" href="<?= $assets_base ?>/assets/css/base.css">

  <?php if (!empty($extra_head)) echo $extra_head; ?>
  <?php if (function_exists('wp_head')) wp_head(); ?>
</head>
<body class="font-sans min-h-screen flex flex-col justify-between text-[17px] leading-[1.7]">

  <!-- Accessibility Skip Link (WCAG 2.1 AAA) -->
  <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:top-3 focus:left-3 focus:bg-accent focus:text-white focus:px-4 focus:py-2 focus:rounded focus:font-mono focus:text-xs focus:shadow-md focus:outline-none">
    Перейти к основному содержимому
  </a>

  <!-- Top Minimal Header Bar -->
  <header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      
      <!-- Brand mark & Title -->
      <div class="flex items-center gap-6">
        <a class="logo" href="<?= $site_root ?>">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>

        <!-- Desktop Nav -->
        <?php include __DIR__ . '/header-nav.php'; ?>
      </div>

      <!-- Right Action / Search, Dark Mode Toggle & Workbench link -->
      <div class="flex items-center gap-2 sm:gap-3">
        <!-- Global Search Trigger -->
        <button id="search-modal-trigger" type="button" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 min-h-[40px] border border-paper-border hover:border-paper-border-dark bg-paper text-ink font-mono text-xs rounded transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Поиск по базе знаний (Ctrl+K)" aria-label="Поиск по базе знаний">
          <span class="material-symbols-outlined text-[18px] text-accent">search</span>
          <span class="hidden md:inline text-xs text-ink-muted">Поиск</span>
          <kbd class="hidden md:inline-block text-[10px] font-mono px-1.5 py-0.2 bg-paper-border/60 text-ink-faint rounded border border-paper-border">Ctrl+K</kbd>
        </button>

        <!-- Dark/Light Mode Switcher (Sketch Style) -->
        <button id="theme-toggle" type="button" class="hidden sm:inline-flex sketch-pill-gray hover:border-ink/50 text-ink font-mono text-xs min-h-[40px] px-3 py-1.5 items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <svg class="w-4 h-4 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-4 h-4 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4.5"></circle>
            <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
          </svg>
          <span class="text-xs text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <?php if ($current_page === 'interactive'): ?>
          <a class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[40px] border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?= esc_url($site_root) ?>#articles" aria-label="Читать статьи журнала">
            <span>Журнал / Статьи</span>
            <span class="material-symbols-outlined text-[14px]">menu_book</span>
          </a>
        <?php else: ?>
          <a class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-2 min-h-[40px] border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?= esc_url($site_root) ?>interactive.php" aria-label="Открыть интерактивный верстак инженера">
            <span>Верстак / Тулзы</span>
            <span class="material-symbols-outlined text-[14px]">build</span>
          </a>
        <?php endif; ?>
      </div>
    </div>
  </header>
