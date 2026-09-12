<?php
/**
 * Single post template — ТОЧКА ПЛАВЛЕНИЯ
 * Экспертный инженерный верстак (Neo-Brutalism & Sketch Style)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

// Fetch dynamic metadata & AI fields
$post_id          = get_the_ID();
$article_title    = get_the_title();
$article_excerpt  = get_the_excerpt() ?: 'Инженерный регламент, термические расчеты и допуски по стандартам IPC.';
$article_date     = get_the_date('d.m.Y');
$reading_time     = get_post_meta( $post_id, 'tchp_reading_time', true ) ?: '7';
$difficulty       = get_post_meta( $post_id, 'tchp_difficulty', true ) ?: 'Инженерный уровень';
$solder_alloy     = get_post_meta( $post_id, 'tchp_solder_alloy', true ) ?: 'SAC305 / ПОС-61';
$tools_json       = get_post_meta( $post_id, 'tchp_tools_needed', true );
$tools_list       = ! empty( $tools_json ) ? json_decode( $tools_json, true ) : [];
?>

<style>
  :root {
    --color-paper: #faf8f5;
    --color-paper-subtle: #f3f0ea;
    --color-paper-border: #e6e2da;
    --color-paper-border-dark: #d3cdc2;
    --color-ink: #141414;
    --color-ink-muted: #5c5850;
    --color-ink-faint: #999388;
    --color-accent: #2563eb;
    --color-accent-light: #dbeafe;
    --color-accent-muted: #1d4ed8;
    --color-callout: #fbf9f4;
    --bg-color: #faf8f5;
    --dot-color: #d3cdc2;
    --card-bg: #ffffff;
    --card-border: #e6e2da;
  }

  html.dark {
    --color-paper: #18191b;
    --color-paper-subtle: #202226;
    --color-paper-border: #2e3238;
    --color-paper-border-dark: #40454e;
    --color-ink: #f3f4f6;
    --color-ink-muted: #a3aab5;
    --color-ink-faint: #6c7380;
    --color-accent: #60a5fa;
    --color-accent-light: #1e293b;
    --color-accent-muted: #93c5fd;
    --color-callout: #1a1b1f;
    --bg-color: #121315;
    --dot-color: #2b2e34;
    --card-bg: #1c1d21;
    --card-border: #2e3238;
  }

  body {
    background-color: var(--bg-color) !important;
    background-image: radial-gradient(var(--dot-color) 0.9px, transparent 0.9px) !important;
    background-size: 20px 20px !important;
    color: var(--color-ink) !important;
    font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  }

  .bg-card {
    background-color: var(--card-bg);
    border-color: var(--card-border);
  }

  .sketch-border {
    border-radius: 255px 15px 225px / 15px 225px 15px 255px;
  }

  .sketch-pill-yellow {
    background: linear-gradient(104deg, rgba(254, 240, 138, 0.4) 0%, rgba(254, 240, 138, 0.9) 15%, rgba(253, 224, 71, 0.95) 85%, rgba(254, 240, 138, 0.4) 100%);
    border: 1px solid rgba(202, 138, 4, 0.5);
    border-radius: 255px 15px 225px / 15px 225px 15px 255px;
    padding: 0.15rem 0.6rem;
    display: inline-block;
  }
  html.dark .sketch-pill-yellow {
    background: linear-gradient(104deg, rgba(161, 98, 7, 0.3) 0%, rgba(161, 98, 7, 0.7) 15%, rgba(202, 138, 4, 0.8) 85%, rgba(161, 98, 7, 0.3) 100%);
    border: 1px solid rgba(234, 179, 8, 0.4);
    color: #fef08a !important;
  }

  .sketch-pill-gray {
    background: var(--color-paper-subtle);
    border: 1px solid var(--color-paper-border-dark);
    border-radius: 255px 15px 225px / 15px 225px 15px 255px;
    padding: 0.15rem 0.6rem;
    display: inline-block;
  }

  mark, .marker-yellow {
    background: linear-gradient(104deg, rgba(254, 240, 138, 0.1) 0%, rgba(254, 240, 138, 0.8) 10%, rgba(253, 224, 71, 0.9) 85%, rgba(254, 240, 138, 0.2) 100%);
    padding: 0.08em 0.35em;
    border-radius: 4px 1px 3px 2px;
    box-decoration-break: clone;
    color: inherit;
  }
  html.dark mark, html.dark .marker-yellow {
    background: linear-gradient(104deg, rgba(161, 98, 7, 0.1) 0%, rgba(161, 98, 7, 0.7) 10%, rgba(202, 138, 4, 0.8) 85%, rgba(161, 98, 7, 0.2) 100%);
    color: #fef08a;
  }

  /* Safety & Caution Callouts (Safety First for DIY & High Voltage) */
  .callout-safety {
    border: 1px solid rgba(234, 88, 12, 0.35);
    border-left: 4px solid #ea580c;
    background: rgba(255, 247, 237, 0.8);
    border-radius: 6px;
    padding: 1rem 1.25rem;
    margin: 1.5rem 0;
    color: #9a3412;
  }
  .callout-safety strong {
    color: #7c2d12;
  }
  html.dark .callout-safety {
    border-color: rgba(234, 88, 12, 0.4);
    border-left-color: #f97316;
    background: rgba(67, 26, 7, 0.3);
    color: #fed7aa;
  }
  html.dark .callout-safety strong {
    color: #ffedd5;
  }

  /* Responsive BOM / Data Tables for DIY Articles */
  .prose table,
  .bom-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    margin: 1.5rem 0;
    text-align: left;
    border: 1px solid var(--color-paper-border);
    border-radius: 6px;
    overflow: hidden;
  }
  .prose thead th,
  .bom-table th {
    background: var(--color-paper-subtle);
    padding: 0.65rem 0.85rem;
    font-family: var(--font-mono, monospace);
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-ink);
    border-bottom: 2px solid var(--color-paper-border-dark);
  }
  .prose td,
  .bom-table td {
    padding: 0.65rem 0.85rem;
    border-bottom: 1px solid var(--color-paper-border);
    color: var(--color-ink-muted);
  }
  .prose tr:last-child td,
  .bom-table tr:last-child td {
    border-bottom: none;
  }
  .prose tr:hover td,
  .bom-table tr:hover td {
    background: rgba(0, 0, 0, 0.02);
  }
  html.dark .prose tr:hover td,
  html.dark .bom-table tr:hover td {
    background: rgba(255, 255, 255, 0.03);
  }
  .table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 1.5rem 0;
  }

  /* Responsive video player embeds (VK Видео / RuTube / WebM) */
  .prose iframe,
  .prose video {
    width: 100%;
    max-width: 100%;
    aspect-ratio: 16 / 9;
    border-radius: 8px;
    border: 1px solid var(--color-paper-border);
  }
</style>

<main class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
    
    <!-- Breadcrumbs -->
    <nav class="text-[12px] font-mono text-ink-faint mb-8 flex items-center gap-1.5 flex-wrap">
      <a class="hover:text-ink transition-colors" href="<?php echo home_url('/'); ?>">Главная</a>
      <span>→</span>
      <a class="hover:text-ink transition-colors" href="<?php echo home_url('/category/guides/'); ?>">Инженерный журнал</a>
      <span>→</span>
      <span class="text-ink truncate max-w-xs sm:max-w-md"><?php echo esc_html($article_title); ?></span>
    </nav>

    <!-- Center Article + Table of Contents Layout -->
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-start">
      
      <!-- Central Editorial Column (740px wide max) -->
      <div class="lg:col-span-8 max-w-[730px] space-y-9">
        
        <!-- Retro Stamp Badge -->
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
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[13px] text-ink-muted font-mono pt-1 pb-2">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full border border-paper-border bg-paper-subtle text-[10px] flex items-center justify-center font-bold text-ink">ТП</span>
              <span class="text-ink"><?php the_author(); ?></span>
            </div>
            <span class="text-ink-faint">·</span>
            <time datetime="<?php echo get_the_date('c'); ?>">Обновлено <?php echo esc_html($article_date); ?></time>
            <span class="text-ink-faint">·</span>
            <span class="sketch-pill-yellow text-ink font-bold text-[11px]">~<?php echo esc_html($reading_time); ?> мин чтения</span>
            <span class="text-ink-faint">·</span>
            <span class="sketch-pill-gray text-ink text-[11px]"><?php echo esc_html($difficulty); ?></span>
          </div>

          <!-- Lead Paragraph -->
          <p class="text-lg text-ink font-serif leading-[1.65] pt-2 italic text-ink/90">
            <?php echo esc_html($article_excerpt); ?>
          </p>
        </header>

        <!-- Callout: "Как читать этот регламент" -->
        <div class="border border-paper-border border-l-4 border-l-ink bg-paper-subtle/50 p-5 rounded-lg text-[13.5px] leading-relaxed space-y-2">
          <div class="text-[11px] font-mono font-semibold uppercase tracking-wider text-ink flex items-center gap-2">
            <svg class="w-3.5 h-3.5 text-accent stroke-current inline-block" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2"></rect>
              <path d="M8 7v10M16 7v10M12 12h.01"></path>
            </svg>
            <span>ИНЖЕНЕРНЫЙ РЕГЛАМЕНТ ЛАБОРАТОРИИ ТЧП</span>
          </div>
          <p class="text-ink/80">
            Все расчетные параметры и теплофизические модели проверены на контактных термопарах K-типа. Материал соответствует нормам IPC/JEDEC J-STD-020D и ГОСТ 21931.
          </p>
        </div>

        <!-- WordPress Body Content -->
        <div class="prose max-w-none text-ink/85 space-y-6">
          <?php the_content(); ?>
        </div>

        <!-- In-Article Native RSYA Ad Container -->
        <div class="my-8 p-4 rounded border border-dashed border-paper-border bg-paper-subtle/40 text-center">
          <div class="text-[10px] font-mono text-ink-faint uppercase mb-1">РЕКЛАМА / ПАРТНЕРСКИЙ БЛОК ЯНДЕКСА</div>
          <div id="yandex_rtb_in_article" class="min-h-[100px] flex items-center justify-center text-xs font-mono text-ink-muted border border-paper-border rounded bg-paper">
            <!-- Yandex.RTB R-A-XXXXXX Container -->
            [Контейнер РСЯ In-Article · Адаптивный блок]
          </div>
        </div>

        <!-- Affiliate Product Showcase (Yandex Market / Chip & Dip) -->
        <section class="my-8 p-5 sm:p-6 rounded-lg border border-paper-border bg-card space-y-4">
          <div class="flex items-center justify-between border-b border-paper-border pb-3">
            <div class="flex items-center gap-2">
              <span class="text-sm">🛠️</span>
              <h3 class="font-bold text-xs sm:text-sm text-ink uppercase font-mono">Проверенное оборудование и химия</h3>
            </div>
            <span class="text-[10px] font-mono text-ink-faint">МАРКЕТ / ЧИПДИП</span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 font-sans">
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Термовоздушная станция</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">Quick 861DW (1000W)</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Турбированный термофен для BGA</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 24 500 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Флюс-гель No-Clean</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">Cyberflux RMA-218</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Безотмывочный гель для бессвинца</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 950 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Припой SAC305</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">BGA Balls 0.45mm</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Прецизионные шарики Sn96.5Ag3Cu0.5</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 1 200 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
          </div>

          <!-- 347-ФЗ Маркировка рекламы -->
          <div class="pt-3 border-t border-paper-border flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-[10px] font-mono text-ink-faint">
            <span>Реклама · erid и данные о рекламодателях доступны по ссылкам перехода</span>
            <span>ООО «Яндекс», ИНН 7736207543 / ООО «Интернет Решения», ИНН 7704217370</span>
          </div>
        </section>

        <!-- B2B / Lead-Gen Rework Module -->
        <div class="my-8 p-5 rounded-lg border-2 border-paper-border-dark bg-paper-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-4 sketch-border">
          <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="sketch-pill-yellow text-ink text-[10.5px] font-mono font-bold">СЕРВИСНЫЙ ЦЕНТР ТЧП</span>
              <span class="text-xs font-bold text-ink">Нужен сложный BGA-ремонт платы?</span>
            </div>
            <p class="text-xs text-ink-muted max-w-lg leading-relaxed">
              Диагностика и замена BGA-чипов, видеокарт и процессоров на оборудовании с термопрофилированием по IPC-A-610.
            </p>
          </div>
          <a href="<?php echo home_url('/interactive'); ?>" class="px-4 py-2 bg-ink text-paper text-xs font-mono font-semibold rounded hover:opacity-90 transition-opacity shrink-0 text-center border border-paper-border-dark shadow-sm">
            Заказать диагностику →
          </a>
        </div>

      </div>

      <!-- Sticky Floating Sidebar -->
      <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-6">
        
        <!-- Table of Contents -->
        <div class="border border-paper-border bg-paper p-4 rounded-lg space-y-3">
          <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
            <span>НАВИГАЦИЯ</span>
            <span>РЕГЛАМЕНТ</span>
          </div>
          <div class="text-xs font-mono text-ink-muted space-y-2">
            <div>Сплав: <strong class="text-ink"><?php echo esc_html($solder_alloy); ?></strong></div>
            <div>Сложность: <strong class="text-ink"><?php echo esc_html($difficulty); ?></strong></div>
          </div>
        </div>

        <!-- Sticky Sidebar РСЯ Slot -->
        <div class="border border-dashed border-paper-border bg-paper p-4 rounded-lg text-center space-y-2">
          <div class="text-[10px] font-mono text-ink-faint uppercase">РЕКЛАМА РСЯ</div>
          <div id="yandex_rtb_sidebar" class="min-h-[220px] flex items-center justify-center text-xs font-mono text-ink-muted bg-paper-subtle rounded border border-paper-border">
            [РСЯ Сайдбар · 300x250]
          </div>
        </div>

        <!-- Open Lab block -->
        <div class="border border-paper-border bg-paper-subtle/50 p-4 rounded-lg space-y-2 text-xs">
          <div class="flex items-center gap-1.5 font-mono text-[11px] font-bold uppercase text-ink">
            <span class="w-2 h-2 bg-ink rounded-full inline-block"></span>
            Лаборатория ТЧП
          </div>
          <p class="text-ink-muted leading-relaxed">
            Открытые инженерные калькуляторы и таблицы термопрофилей.
          </p>
          <a class="inline-block pt-1 font-mono text-[11.5px] font-semibold text-ink underline decoration-paper-border-dark" href="<?php echo home_url('/interactive'); ?>">
            Интерактивный верстак →
          </a>
        </div>

      </aside>

    </div>

  </div>
</main>

<?php get_footer(); ?>
