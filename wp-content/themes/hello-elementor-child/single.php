<?php
/**
 * Single Article Template - Tochka Plavleniya (Stitch / TochkiCamp Editorial Style)
 */

get_header();

// Fetch article data / fallback
$article_title = get_the_title();
$article_date  = get_the_date('j F Y');
$article_excerpt = get_the_excerpt() ?: 'Практический инженерный регламент пайки BGA и SMD компонентов.';
?>

<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
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
        highlight: '#fef9c3',
        'highlight-strong': '#fef08a',
        callout: 'var(--color-callout)',
        card: 'var(--color-card)'
      },
      fontFamily: {
        sans: ['"IBM Plex Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        serif: ['Newsreader', 'Georgia', 'serif'],
        mono: ['"JetBrains Mono"', '"IBM Plex Mono"', 'monospace'],
        logo: ['"Hanken Grotesk"', 'sans-serif']
      }
    }
  }
}
</script>

<style>
  :root {
    --color-paper: #faf8f5;
    --color-paper-subtle: #f3f0ea;
    --color-paper-border: #e6e2da;
    --color-paper-border-dark: #d3cdc2;
    --color-ink: #141414;
    --color-ink-muted: #6b665f;
    --color-ink-faint: #9e988f;
    --color-callout: #fcfaf2;
    --color-card: rgba(255, 255, 255, 0.75);
    --dot-color: #d3cdc2;
    --bg-color: #faf8f5;
  }

  html.dark {
    --color-paper: #12141a;
    --color-paper-subtle: #191c24;
    --color-paper-border: #282d3b;
    --color-paper-border-dark: #373e52;
    --color-ink: #f3f4f6;
    --color-ink-muted: #9ca3af;
    --color-ink-faint: #6b7280;
    --color-callout: #1b1f2b;
    --color-card: rgba(24, 27, 36, 0.85);
    --dot-color: #2b3142;
    --bg-color: #12141a;
  }

  body {
    background-color: var(--bg-color) !important;
    background-image: radial-gradient(var(--dot-color) 0.9px, transparent 0.9px) !important;
    background-size: 20px 20px !important;
    color: var(--color-ink) !important;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  .logo { 
    font-family: 'Hanken Grotesk', 'Inter', sans-serif; 
    font-size: 1.25rem; 
    font-weight: 900; 
    text-decoration: none; 
    color: var(--color-ink); 
    letter-spacing: -0.02em; 
    text-transform: uppercase; 
    display: inline-flex; 
    align-items: center; 
    line-height: 1; 
  }
  .logo span { 
    color: #141414; 
    background: #facc15; 
    padding: 0.05rem 0.35rem; 
    border-radius: 3px; 
    transform: skew(-6deg); 
    display: inline-block; 
    margin: 0 0.15rem; 
    font-size: 1.05em; 
    line-height: 0.9; 
  }

  /* REALISTIC HAND-DRAWN FELT-TIP MARKER STROKES (Vecteezy / PNG style) */
  mark, .marker-yellow {
    position: relative;
    display: inline;
    background: transparent;
    color: #141414;
    padding: 0.18em 0.55em 0.22em 0.5em;
    margin: 0 -0.15em;
    box-decoration-break: clone;
    -webkit-box-decoration-break: clone;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 24' preserveAspectRatio='none'%3E%3Cpath d='M0.5 4.5 C 18 2, 48 5.5, 98.5 2.5 C 100 11, 98 18.5, 99.5 22 C 75 23.5, 30 20.5, 1.5 22 C 0 16, 1.5 8.5, 0.5 4.5 Z' fill='%23fef08a' fill-opacity='0.9'/%3E%3Cpath d='M3 7.5 C 25 5.5, 68 6, 96.5 5 C 97.5 12.5, 95 18, 97 20 C 70 21.5, 28 19.5, 4 19.5 Z' fill='%23fde047' fill-opacity='0.55'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    font-weight: 600;
  }
  html.dark mark, html.dark .marker-yellow {
    color: #fef08a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 24' preserveAspectRatio='none'%3E%3Cpath d='M0.5 4.5 C 18 2, 48 5.5, 98.5 2.5 C 100 11, 98 18.5, 99.5 22 C 75 23.5, 30 20.5, 1.5 22 C 0 16, 1.5 8.5, 0.5 4.5 Z' fill='%23ca8a04' fill-opacity='0.45'/%3E%3Cpath d='M3 7.5 C 25 5.5, 68 6, 96.5 5 C 97.5 12.5, 95 18, 97 20 C 70 21.5, 28 19.5, 4 19.5 Z' fill='%23eab308' fill-opacity='0.3'/%3E%3C/svg%3E");
  }

  /* Hand-drawn yellow marker pill */
  .sketch-pill-yellow {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: transparent;
    color: #141414;
    padding: 0.22rem 0.75rem 0.26rem 0.7rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 4 C 20 1.5, 68 3, 98 2 C 100 11.5, 98 19.5, 99 25.5 C 72 26.5, 26 24.5, 1 25.5 C 0 16.5, 1.5 8, 1.5 4 Z' fill='%23fef08a' fill-opacity='0.95' stroke='%23ca8a04' stroke-width='0.9' stroke-dasharray='40 1 20 1' stroke-linecap='round'/%3E%3Cpath d='M4 7 C 28 5, 72 5.5, 95.5 5 C 96.5 12, 94.5 18.5, 96 21.5 C 68 22.5, 26 21.5, 3 21.5 Z' fill='%23fde047' fill-opacity='0.65'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    transform: rotate(-0.9deg);
    transition: transform 0.15s ease;
  }
  .sketch-pill-yellow:hover {
    transform: rotate(0deg) scale(1.03);
  }
  html.dark .sketch-pill-yellow {
    color: #fef08a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 4 C 20 1.5, 68 3, 98 2 C 100 11.5, 98 19.5, 99 25.5 C 72 26.5, 26 24.5, 1 25.5 C 0 16.5, 1.5 8, 1.5 4 Z' fill='%23ca8a04' fill-opacity='0.4' stroke='%23eab308' stroke-width='0.9' stroke-dasharray='40 1 20 1' stroke-linecap='round'/%3E%3Cpath d='M4 7 C 28 5, 72 5.5, 95.5 5 C 96.5 12, 94.5 18.5, 96 21.5 C 68 22.5, 26 21.5, 3 21.5 Z' fill='%23a16207' fill-opacity='0.4'/%3E%3C/svg%3E");
  }

  /* Hand-drawn light-gray felt marker pill */
  .sketch-pill-gray {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: transparent;
    color: #141414;
    padding: 0.22rem 0.75rem 0.26rem 0.7rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 3 C 22 1.5, 62 4, 98 2 C 99.5 11.5, 98 19.5, 99 25.5 C 70 26.5, 25 24.5, 1 25.5 C 0.5 17, 0 8.5, 1.5 3 Z' fill='%23e9e5dc' fill-opacity='0.95' stroke='%23b8b1a2' stroke-width='0.9' stroke-dasharray='35 1 25 1' stroke-linecap='round'/%3E%3Cpath d='M4 6.5 C 30 5, 75 5.5, 95 4.5 C 96 12, 95 18, 96 22 C 68 23, 30 21.5, 3 22 Z' fill='%23ded8cd' fill-opacity='0.6'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    transform: rotate(0.6deg);
    transition: transform 0.15s ease;
  }
  .sketch-pill-gray:hover {
    transform: rotate(0deg) scale(1.03);
  }
  html.dark .sketch-pill-gray {
    color: #f3f4f6;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 3 C 22 1.5, 62 4, 98 2 C 99.5 11.5, 98 19.5, 99 25.5 C 70 26.5, 25 24.5, 1 25.5 C 0.5 17, 0 8.5, 1.5 3 Z' fill='%23222735' fill-opacity='0.95' stroke='%233e465c' stroke-width='0.9' stroke-dasharray='35 1 25 1' stroke-linecap='round'/%3E%3Cpath d='M4 6.5 C 30 5, 75 5.5, 95 4.5 C 96 12, 95 18, 96 22 C 68 23, 30 21.5, 3 22 Z' fill='%232c3346' fill-opacity='0.6'/%3E%3C/svg%3E");
  }

  /* Hand-drawn marker underline */
  .marker-underline {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 12' preserveAspectRatio='none'%3E%3Cpath d='M1 8 C 25 3, 60 10, 99 5 C 75 11, 30 7, 2 10 Z' fill='%23facc15' fill-opacity='0.85'/%3E%3C/svg%3E");
    background-position: 0 100%;
    background-size: 100% 0.35em;
    background-repeat: no-repeat;
    padding-bottom: 0.1em;
  }
</style>

<main class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
    
    <!-- Breadcrumbs -->
    <nav class="text-[12px] font-mono text-ink-faint mb-8 flex items-center gap-1.5 flex-wrap">
      <a class="hover:text-ink transition-colors" href="<?php echo home_url('/'); ?>">Главная</a>
      <span>→</span>
      <a class="hover:text-ink transition-colors" href="<?php echo home_url('/category/bga/'); ?>">Гайды</a>
      <span>→</span>
      <span class="text-ink truncate max-w-xs sm:max-w-md"><?php echo esc_html($article_title); ?></span>
    </nav>

    <!-- Center Article + Table of Contents Layout -->
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-start">
      
      <!-- Central Editorial Column (740px wide max) -->
      <div class="lg:col-span-8 max-w-[730px] space-y-9">
        
        <!-- Retro Illustration / Stamp Badge -->
        <div class="w-12 h-12 rounded border border-paper-border bg-paper-subtle flex items-center justify-center text-ink">
          <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewbox="0 0 24 24" width="24">
            <rect height="18" rx="2" width="18" x="3" y="3"></rect>
            <path d="M8 7v10"></path>
            <path d="M16 7v10"></path>
            <path d="M12 12h.01"></path>
            <circle cx="12" cy="7" r="1"></circle>
            <circle cx="12" cy="17" r="1"></circle>
          </svg>
        </div>

        <!-- Article Header Block -->
        <header class="space-y-4">
          <h1 class="text-3xl sm:text-[38px] font-bold text-ink tracking-[-0.03em] leading-[1.18] font-sans">
            <?php echo esc_html($article_title); ?>
          </h1>

          <!-- Meta line -->
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-ink-muted font-mono pt-1 pb-2">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full border border-paper-border bg-paper-subtle text-[10px] flex items-center justify-center font-bold text-ink">ТП</span>
              <span class="text-ink"><?php the_author(); ?></span>
            </div>
            <span class="text-ink-faint">·</span>
            <time datetime="<?php echo get_the_date('c'); ?>">Обновлено <?php echo esc_html($article_date); ?></time>
            <span class="text-ink-faint">·</span>
            <span>~8 мин чтения</span>
            <span class="text-ink-faint">·</span>
            <span class="bg-paper-subtle px-1.5 py-0.5 rounded text-[11px] border border-paper-border">IPC/JEDEC J-STD-020D</span>
          </div>

          <!-- Lead Paragraph -->
          <p class="text-lg text-ink font-serif leading-[1.65] pt-2 text-[#242220]">
            <?php echo esc_html($article_excerpt); ?>
          </p>
        </header>

        <!-- Callout: "Как читать этот регламент" -->
        <div class="border border-paper-border border-l-4 border-l-ink bg-paper-subtle/50 p-5 rounded-lg text-[13.5px] leading-relaxed space-y-2">
          <div class="text-[11px] font-mono font-semibold uppercase tracking-wider text-ink">
            КАК ЧИТАТЬ ЭТОТ РЕГЛАМЕНТ
          </div>
          <p class="text-ink/80">
            Если вы настраиваете термопрофиль под конкретный чип, сразу переходите к <a class="underline decoration-ink/40 underline-offset-2 hover:decoration-ink text-ink font-medium" href="#simulator">симулятору 4 фаз</a> или <a class="underline decoration-ink/40 underline-offset-2 hover:decoration-ink text-ink font-medium" href="#alloys">температурным окнам сплавов</a>. Все значения температур верифицированы контактными термопарами К-типа.
          </p>
        </div>

        <!-- WordPress Body Content -->
        <div class="prose max-w-none text-ink/85 space-y-6">
          <?php the_content(); ?>
        </div>

      </div>

      <!-- Sticky Floating Table of Contents Sidebar -->
      <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-6">
        <div class="border border-paper-border bg-paper p-4 rounded-lg space-y-3">
          <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
            <span>СОДЕРЖАНИЕ</span>
            <span>4 раздела</span>
          </div>
          <nav class="space-y-1 text-[13px] font-mono" id="toc-nav">
            <a class="toc-link block px-2 py-1 rounded text-ink font-semibold hover:bg-paper-subtle transition-colors" href="#step-1">
              1. Теплоемкость и датчики
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#step-2">
              2. Четыре фазы кривой
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#alloys">
              3. Температурные окна
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#step-4">
              4. Практика и защита от брака
            </a>
          </nav>
        </div>
      </aside>

    </div>

  </div>
</main>

<?php get_footer(); ?>
