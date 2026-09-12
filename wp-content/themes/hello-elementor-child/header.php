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
  <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@700;900&family=JetBrains+Mono:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400;1,6..72,500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>

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
    /* CANONICAL BRAND LOGO — ТОЧКА ПЛАВЛЕНИЯ */
    .logo {
      font-family: 'Hanken Grotesk', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 1.25rem;
      font-weight: 900;
      text-decoration: none;
      color: var(--color-ink);
      letter-spacing: -0.02em;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      line-height: 1;
      transition: opacity 0.15s ease;
    }
    .logo:hover {
      opacity: 0.85;
    }
    .logo span {
      color: #141414;
      background: #facc15;
      padding: 0.05rem 0.35rem;
      border-radius: 3px;
      transform: skew(-6deg);
      display: inline-block;
      margin: 0 0.18rem;
      font-size: 1.05em;
      line-height: 0.9;
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
        <!-- Theme Switcher Button (Sketch Style) -->
        <button id="theme-toggle" type="button" class="sketch-pill-gray hover:border-ink/50 text-ink font-mono text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <svg class="w-3.5 h-3.5 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-3.5 h-3.5 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4.5"></circle>
            <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
          </svg>
          <span class="text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="<?php echo esc_url( home_url( '/interactive' ) ); ?>">
          <span>Верстак</span>
          <span class="text-[10px]">⚙</span>
        </a>
      </div>
    </div>
  </header>
