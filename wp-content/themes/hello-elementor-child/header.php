<?php
/**
 * Header template — ТОЧКА ПЛАВЛЕНИЯ (Hello Elementor Child)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="light">
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  
  <!-- Immediate Theme Init Script (Zero Flash) -->
  <script>
    (function() {
      const saved = localStorage.getItem('tp_theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
      } else {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400;1,6..72,500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            paper: 'var(--color-paper)',
            'paper-subtle': 'var(--color-paper-subtle)',
            'paper-border': 'var(--color-paper-border)',
            'paper-border-dark': 'var(--color-paper-border-dark)',
            ink: 'var(--color-ink)',
            'ink-muted': 'var(--color-ink-muted)',
            'ink-faint': 'var(--color-ink-faint)',
            accent: 'var(--color-accent)',
            'accent-light': 'var(--color-accent-light)',
            'accent-muted': 'var(--color-accent-muted)',
          },
          fontFamily: {
            sans: ['Space Grotesk', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
            serif: ['Newsreader', 'Georgia', 'serif'],
            mono: ['JetBrains Mono', 'monospace'],
          }
        }
      }
    }
  </script>

  <style>
    .logo {
      font-family: 'Space Grotesk', sans-serif;
      font-weight: 700;
      font-size: 17px;
      letter-spacing: -0.02em;
      color: var(--color-ink);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }
    .logo span {
      color: #eab308;
      margin: 0 1px;
    }
  </style>

  <?php wp_head(); ?>
</head>
<body <?php body_class( 'min-h-screen flex flex-col' ); ?>>
<?php wp_body_open(); ?>

  <!-- Header -->
  <header class="w-full border-b border-paper-border bg-paper/90 sticky top-0 z-40 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-6">
        <a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        <nav class="hidden md:flex items-center gap-5 text-[13px] font-mono text-ink-muted">
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/' ) ); ?>">Статьи</a>
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/interactive' ) ); ?>">Верстак</a>
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/interactive#table' ) ); ?>">Реестр сплавов</a>
        </nav>
      </div>

      <div class="flex items-center gap-3">
        <!-- Theme Switcher Button -->
        <button id="theme-toggle" type="button" class="p-1.5 px-2.5 rounded border border-paper-border bg-paper hover:border-paper-border-dark text-ink font-mono text-xs flex items-center gap-1.5 transition-colors" title="Переключить тему (Светлая / Тёмная)" aria-label="Переключить тему">
          <span class="dark:hidden">🌙</span>
          <span class="hidden dark:inline">☀️</span>
          <span class="text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?php echo esc_url( home_url( '/interactive' ) ); ?>">
          <span>Верстак</span>
          <span class="text-[10px]">⚙</span>
        </a>
      </div>
    </div>
  </header>
