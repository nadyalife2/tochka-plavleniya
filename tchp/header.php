<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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

    <?php wp_head(); ?>
</head>

<body <?php body_class('font-sans min-h-screen flex flex-col justify-between text-[17px] leading-[1.7] antialiased'); ?>>
<?php if (function_exists('wp_body_open')) wp_body_open(); ?>

<!-- Top Minimal Editorial Header -->
<header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
    <!-- Brand mark & Title -->
    <div class="flex items-center gap-6">
      <a class="logo" href="<?php echo esc_url(home_url('/')); ?>">
        ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
      </a>
      
      <!-- Primary Navigation -->
      <nav class="hidden md:flex items-center gap-5 text-[13.5px] text-ink-muted">
        <?php if (has_nav_menu('primary')) : ?>
          <?php
          wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'items_wrap'     => '%3$s',
            'fallback_cb'    => false,
          ]);
          ?>
        <?php else : ?>
          <a class="text-ink font-medium hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/#articles')); ?>">Статьи</a>
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/#calculator')); ?>">Калькулятор флюсов</a>
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/#table')); ?>">Таблица припоев</a>
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/')); ?>">Инструменты</a>
        <?php endif; ?>
      </nav>
    </div>

    <!-- Right Actions: Search Modal, Theme Toggle & Workbench Button -->
    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Global Search Trigger -->
      <button id="search-modal-trigger" type="button" class="inline-flex items-center gap-1.5 px-2.5 sm:px-3 min-h-[36px] border border-paper-border hover:border-paper-border-dark bg-paper text-ink font-mono text-xs rounded transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Поиск по базе знаний (Ctrl+K)" aria-label="Поиск по базе знаний">
        <span class="material-symbols-outlined text-[17px] text-accent">search</span>
        <span class="hidden md:inline text-xs text-ink-muted">Поиск</span>
        <kbd class="hidden sm:inline-block ml-1 px-1.5 py-0.5 text-[10px] font-mono text-ink-muted bg-paper-subtle border border-paper-border rounded">Ctrl K</kbd>
      </button>

      <button id="theme-toggle" type="button" class="px-2.5 min-h-[36px] rounded border border-paper-border bg-paper-subtle text-ink font-mono text-xs flex items-center gap-1.5 transition-all cursor-pointer hover:border-ink/40 shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
        <svg class="w-3.5 h-3.5 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
        <svg class="w-3.5 h-3.5 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="4.5"></circle>
          <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
        </svg>
        <span class="hidden sm:inline text-[11px] text-ink-muted font-mono">Тема</span>
      </button>

      <a class="inline-flex items-center gap-1.5 px-3 min-h-[36px] border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?php echo esc_url(home_url('/interactive/')); ?>">
        <span>Верстак / Тулзы</span>
        <span class="material-symbols-outlined text-[13px]">build</span>
      </a>
    </div>
  </div>
</header>
