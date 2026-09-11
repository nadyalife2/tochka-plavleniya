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
