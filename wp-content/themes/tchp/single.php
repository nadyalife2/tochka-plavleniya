<?php
/**
 * The template for displaying all single posts
 *
 * @package TCHP
 * @version 2.1.0
 */

get_header();

// Fallback data if viewed outside standard WP loop or on fresh installation
$fallback_articles = tchp_get_fallback_articles();
$active_slug = get_query_var('name') ?: ($_GET['slug'] ?? '');
$fallback_item = null;
if (!empty($active_slug)) {
    foreach ($fallback_articles as $item) {
        if (($item['slug'] ?? '') === $active_slug) {
            $fallback_item = $item;
            break;
        }
    }
}
if (!$fallback_item && !empty($fallback_articles)) {
    $fallback_item = $fallback_articles[0];
}
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">

    <?php if (have_posts()) : while (have_posts()) : the_post(); 
      $reading_time = get_post_meta(get_the_ID(), 'tchp_reading_time', true) ?: (function_exists('get_reading_time') ? get_reading_time(get_the_content()) : '8');
      $standard = get_post_meta(get_the_ID(), 'tchp_standard', true) ?: 'IPC/JEDEC J-STD-020D';
      $categories = get_the_category();
    ?>

      <!-- Breadcrumbs & Category Badge -->
      <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
        <nav class="text-[12px] font-mono text-ink-faint flex items-center gap-1.5 flex-wrap">
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
          <span>→</span>
          <?php
          if (!empty($categories)) {
              echo '<a class="hover:text-ink transition-colors" href="' . esc_url(get_category_link($categories[0]->term_id)) . '">' . esc_html($categories[0]->name) . '</a>';
              echo '<span>→</span>';
          }
          ?>
          <span class="text-ink truncate max-w-xs sm:max-w-md"><?php the_title(); ?></span>
        </nav>
        <span class="pill-tag-orange text-xs font-mono font-medium">Регламент ТЧП v2.0</span>
      </div>

      <!-- Title Block -->
      <div class="mb-10 space-y-4">
        <?php if (!empty($categories)) : ?>
          <div class="inline-block pill-tag-yellow font-mono text-xs mb-1"><?php echo esc_html($categories[0]->name); ?></div>
        <?php endif; ?>

        <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-ink leading-[1.18] font-sans">
          <?php the_title(); ?>
        </h1>

        <!-- Meta Info Row -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-ink-muted font-mono pt-1">
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-brand-orange">schedule</span>
            <?php echo esc_html($reading_time); ?> мин чтения
          </span>
          <span class="text-ink-faint">·</span>
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-ink-muted">calendar_today</span>
            <?php echo get_the_date('j M Y'); ?>
          </span>
          <span class="text-ink-faint">·</span>
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-ink-muted">person</span>
            <?php the_author(); ?>
          </span>
          <span class="text-ink-faint">·</span>
          <span class="bg-paper-subtle px-1.5 py-0.5 rounded text-[11px] border border-paper-border"><?php echo esc_html($standard); ?></span>
        </div>

        <?php if (has_excerpt()) : ?>
          <p class="text-[19px] text-ink font-serif leading-[1.65] pt-3 text-ink/90 border-b border-paper-border pb-6">
            <?php echo get_the_excerpt(); ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- Article Layout -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start relative">

        <!-- Left Column / Marginalia Sticky Note (Desktop) -->
        <div class="hidden xl:block lg:col-span-1 relative">
          <div class="sticky top-28 flex flex-col gap-6 items-center text-center">
            <div class="pill-tag-yellow font-mono text-[11px] whitespace-nowrap">
              Введение
            </div>
            <div class="font-hand text-xl text-brand-orange font-bold flex flex-col items-center">
              <span class="material-symbols-outlined text-lg mb-1">edit_note</span>
              Заметки<br>на полях
            </div>
            <svg class="w-8 h-12 text-ink-muted transform rotate-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 48">
              <path d="M12 2v38m-6-6l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <!-- Center Column: Content Body -->
        <div class="lg:col-span-8 max-w-[740px] space-y-8">

          <!-- TL;DR Sticky Post-It Note -->
          <div class="postit-yellow p-6 rounded-lg relative my-2">
            <div class="flex items-center gap-2 mb-2">
              <span class="material-symbols-outlined text-xl text-brand-orange">push_pin</span>
              <h3 class="font-hand text-2xl font-bold text-ink">В двух словах (TL;DR)</h3>
            </div>
            <p class="text-[16px] leading-relaxed text-ink/90">
              <strong>Ключевая мысль:</strong> Нагрев должен быть двухсторонним (нижний подогрев 140–160°C обязателен). Без нижнего подогрева верхний фен перегревает чип до деструкции кремния, пока нижние слои меди остаются холодными.
            </p>
          </div>

          <!-- Featured Image -->
          <?php if (has_post_thumbnail()) : ?>
            <div class="rounded-lg overflow-hidden border border-paper-border my-6">
              <?php the_post_thumbnail('large', ['class' => 'w-full h-auto block object-cover']); ?>
            </div>
          <?php endif; ?>

          <!-- Main Gutenberg / Editorial Content -->
          <article class="prose prose-neutral max-w-none text-[17px] leading-[1.7] space-y-6 text-ink/85">
            <?php the_content(); ?>
          </article>

          <!-- SVG Thermal Profile Curve Component -->
          <div class="sketch-frame p-4 sm:p-6 my-6 relative overflow-hidden">
            <div class="font-hand text-xl font-bold text-ink mb-3 flex items-center gap-2">
              <span class="material-symbols-outlined text-lg text-brand-orange">show_chart</span>
              Схема нагрева: T°C / Время (сек)
            </div>
            
            <div class="w-full overflow-x-auto">
              <svg viewBox="0 0 540 220" width="100%" height="auto" fill="none" class="max-w-full">
                <rect width="540" height="220" rx="8" class="fill-paper-subtle/50 stroke-paper-border" stroke-width="1.5"/>
                <line x1="40" y1="180" x2="500" y2="180" class="stroke-paper-border-dark" stroke-width="1.5" stroke-dasharray="4 4"/>
                <line x1="40" y1="110" x2="500" y2="110" class="stroke-paper-border-dark" stroke-width="1" stroke-dasharray="3 4"/>
                <line x1="40" y1="50" x2="500" y2="50" class="stroke-paper-border-dark" stroke-width="1" stroke-dasharray="2 4"/>
                <path d="M40 180 Q120 160 180 110 T320 50 T440 80 T500 180" class="stroke-ink" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="180" cy="110" r="7" class="fill-[#fde047] stroke-ink" stroke-width="2"/>
                <circle cx="320" cy="50" r="7" class="fill-brand-orange stroke-ink" stroke-width="2"/>
                <circle cx="440" cy="80" r="7" class="fill-[#2dd4bf] stroke-ink" stroke-width="2"/>
                <text x="110" y="96" font-family="Caveat" font-size="19" font-weight="700" class="fill-ink">Preheat (150°C)</text>
                <text x="280" y="36" font-family="Caveat" font-size="20" font-weight="700" class="fill-brand-orange">Peak (245°C) ★</text>
                <text x="430" y="115" font-family="Caveat" font-size="19" font-weight="700" class="fill-ink">Cooling (6°C/s)</text>
                <text x="40" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">0s</text>
                <text x="170" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">90s (Soak)</text>
                <text x="305" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">210s (Reflow)</text>
                <text x="470" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">300s</text>
              </svg>
            </div>
            <div class="mt-3 text-[11px] font-mono text-ink-faint flex items-center justify-between">
              <span>Рис. 2. Нормированная кривая SAC305 (Liquidus 217°C)</span>
              <span class="uppercase"><?php echo esc_html($standard); ?></span>
            </div>
          </div>

          <!-- Share Bar -->
          <div class="flex flex-wrap items-center justify-between border-t border-paper-border pt-6 mt-8 gap-4">
            <span class="font-hand text-2xl font-bold text-ink">Понравился регламент?</span>
            <button onclick="navigator.clipboard.writeText(window.location.href); alert('Ссылка скопирована!');" class="inline-flex items-center gap-2 px-5 py-2.5 rounded border border-paper-border-dark bg-ink text-paper text-sm font-mono font-medium hover:opacity-90 transition-all cursor-pointer">
              <span class="material-symbols-outlined text-base">link</span>
              <span>Скопировать ссылку</span>
            </button>
          </div>

        </div>

        <!-- Right Column: Sidebar -->
        <div class="lg:col-span-4 space-y-6">
          <!-- Author Card -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle/50 space-y-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded border border-paper-border bg-ink text-paper font-mono font-bold flex items-center justify-center text-xs">
                ТП
              </div>
              <div>
                <div class="font-bold text-ink text-sm"><?php the_author(); ?></div>
                <div class="font-hand text-lg text-ink-muted">Инженер-практик ТЧП</div>
              </div>
            </div>
            <div class="border-t border-paper-border pt-2.5">
              <span class="pill-tag-mint text-[11px] font-mono">
                Верифицировано лабораторией
              </span>
            </div>
          </div>

          <!-- Quick Navigation -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle/30 space-y-3">
            <h3 class="font-mono text-xs uppercase tracking-wider font-semibold text-ink flex items-center gap-2">
              <span class="material-symbols-outlined text-sm text-brand-orange">menu_book</span>
              Материалы раздела
            </h3>
            <div class="space-y-2 text-xs font-mono">
              <a class="block p-2 rounded hover:bg-paper transition-colors text-ink" href="<?php echo esc_url(home_url('/interactive/')); ?>">
                → Интерактивные калькуляторы
              </a>
              <a class="block p-2 rounded hover:bg-paper transition-colors text-ink" href="<?php echo esc_url(home_url('/')); ?>">
                → Все статьи журнала
              </a>
            </div>
          </div>
        </div>

      </div>

    <?php endwhile; elseif ($fallback_item) : ?>
      
      <!-- Fallback display when previewing before WP posts are imported -->
      <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
        <nav class="text-[12px] font-mono text-ink-faint flex items-center gap-1.5 flex-wrap">
          <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
          <span>→</span>
          <span class="hover:text-ink transition-colors"><?php echo esc_html($fallback_item['category'] ?? 'Пайка'); ?></span>
          <span>→</span>
          <span class="text-ink truncate max-w-xs sm:max-w-md"><?php echo esc_html($fallback_item['title']); ?></span>
        </nav>
        <span class="pill-tag-orange text-xs font-mono font-medium">Регламент ТЧП v2.0 (Демо)</span>
      </div>

      <div class="mb-10 space-y-4">
        <div class="inline-block pill-tag-yellow font-mono text-xs mb-1"><?php echo esc_html($fallback_item['category'] ?? 'Техпроцесс'); ?></div>
        <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-ink leading-[1.18] font-sans">
          <?php echo esc_html($fallback_item['title']); ?>
        </h1>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-ink-muted font-mono pt-1">
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-brand-orange">schedule</span>
            <?php echo esc_html($fallback_item['reading_time'] ?? '8'); ?> мин чтения
          </span>
          <span class="text-ink-faint">·</span>
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-ink-muted">calendar_today</span>
            <?php echo esc_html($fallback_item['date'] ?? date('d.m.Y')); ?>
          </span>
          <span class="text-ink-faint">·</span>
          <span class="inline-flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-ink-muted">person</span>
            <?php echo esc_html($fallback_item['author'] ?? 'Лаборатория ТЧП'); ?>
          </span>
          <span class="text-ink-faint">·</span>
          <span class="bg-paper-subtle px-1.5 py-0.5 rounded text-[11px] border border-paper-border">IPC/JEDEC J-STD-020D</span>
        </div>
        <p class="text-[19px] text-ink font-serif leading-[1.65] pt-3 text-ink/90 border-b border-paper-border pb-6">
          <?php echo esc_html($fallback_item['excerpt']); ?>
        </p>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start relative">
        <div class="hidden xl:block lg:col-span-1 relative">
          <div class="sticky top-28 flex flex-col gap-6 items-center text-center">
            <div class="pill-tag-yellow font-mono text-[11px] whitespace-nowrap">Введение</div>
            <div class="font-hand text-xl text-brand-orange font-bold flex flex-col items-center">
              <span class="material-symbols-outlined text-lg mb-1">edit_note</span>
              Заметки<br>на полях
            </div>
            <svg class="w-8 h-12 text-ink-muted transform rotate-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 48">
              <path d="M12 2v38m-6-6l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <div class="lg:col-span-8 max-w-[740px] space-y-8">
          <div class="postit-yellow p-6 rounded-lg relative my-2">
            <div class="flex items-center gap-2 mb-2">
              <span class="material-symbols-outlined text-xl text-brand-orange">push_pin</span>
              <h3 class="font-hand text-2xl font-bold text-ink">В двух словах (TL;DR)</h3>
            </div>
            <p class="text-[16px] leading-relaxed text-ink/90">
              <strong>Ключевая мысль:</strong> Нагрев должен быть двухсторонним (нижний подогрев 140–160°C обязателен). Без нижнего подогрева верхний фен перегревает чип до деструкции кремния, пока нижние слои меди остаются холодными.
            </p>
          </div>

          <article class="prose prose-neutral max-w-none text-[17px] leading-[1.7] space-y-6 text-ink/85">
            <?php 
            if (!empty($fallback_item['content_html'])) {
                echo $fallback_item['content_html'];
            } else {
                echo '<p>' . esc_html($fallback_item['excerpt']) . '</p>';
                echo '<p>Термопрофиль пайки определяет качество паяного соединения и гарантирует сохранность компонентов при монтаже BGA и SMD элементов высокой плотности.</p>';
            }
            ?>
          </article>

          <!-- SVG Thermal Profile Curve Component -->
          <div class="sketch-frame p-4 sm:p-6 my-6 relative overflow-hidden">
            <div class="font-hand text-xl font-bold text-ink mb-3 flex items-center gap-2">
              <span class="material-symbols-outlined text-lg text-brand-orange">show_chart</span>
              Схема нагрева: T°C / Время (сек)
            </div>
            
            <div class="w-full overflow-x-auto">
              <svg viewBox="0 0 540 220" width="100%" height="auto" fill="none" class="max-w-full">
                <rect width="540" height="220" rx="8" class="fill-paper-subtle/50 stroke-paper-border" stroke-width="1.5"/>
                <line x1="40" y1="180" x2="500" y2="180" class="stroke-paper-border-dark" stroke-width="1.5" stroke-dasharray="4 4"/>
                <line x1="40" y1="110" x2="500" y2="110" class="stroke-paper-border-dark" stroke-width="1" stroke-dasharray="3 4"/>
                <line x1="40" y1="50" x2="500" y2="50" class="stroke-paper-border-dark" stroke-width="1" stroke-dasharray="2 4"/>
                <path d="M40 180 Q120 160 180 110 T320 50 T440 80 T500 180" class="stroke-ink" stroke-width="3" stroke-linecap="round" fill="none"/>
                <circle cx="180" cy="110" r="7" class="fill-[#fde047] stroke-ink" stroke-width="2"/>
                <circle cx="320" cy="50" r="7" class="fill-brand-orange stroke-ink" stroke-width="2"/>
                <circle cx="440" cy="80" r="7" class="fill-[#2dd4bf] stroke-ink" stroke-width="2"/>
                <text x="110" y="96" font-family="Caveat" font-size="19" font-weight="700" class="fill-ink">Preheat (150°C)</text>
                <text x="280" y="36" font-family="Caveat" font-size="20" font-weight="700" class="fill-brand-orange">Peak (245°C) ★</text>
                <text x="430" y="115" font-family="Caveat" font-size="19" font-weight="700" class="fill-ink">Cooling (6°C/s)</text>
                <text x="40" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">0s</text>
                <text x="170" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">90s (Soak)</text>
                <text x="305" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">210s (Reflow)</text>
                <text x="470" y="202" font-family="JetBrains Mono" font-size="11" class="fill-ink-muted">300s</text>
              </svg>
            </div>
            <div class="mt-3 text-[11px] font-mono text-ink-faint flex items-center justify-between">
              <span>Рис. 2. Нормированная кривая SAC305 (Liquidus 217°C)</span>
              <span class="uppercase">IPC/JEDEC J-STD-020</span>
            </div>
          </div>
        </div>

        <div class="lg:col-span-4 space-y-6">
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle/50 space-y-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded border border-paper-border bg-ink text-paper font-mono font-bold flex items-center justify-center text-xs">ТП</div>
              <div>
                <div class="font-bold text-ink text-sm"><?php echo esc_html($fallback_item['author'] ?? 'Лаборатория ТЧП'); ?></div>
                <div class="font-hand text-lg text-ink-muted">Инженер-практик ТЧП</div>
              </div>
            </div>
            <div class="border-t border-paper-border pt-2.5">
              <span class="pill-tag-mint text-[11px] font-mono">Верифицировано лабораторией</span>
            </div>
          </div>
        </div>
      </div>

    <?php else : ?>
      
      <div class="text-center py-12 space-y-4">
        <h1 class="text-3xl font-bold text-ink">Статья не найдена</h1>
        <p class="text-ink-muted">Похоже, в базе данных WordPress пока нет записей. Добавьте первую запись в админке.</p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-block px-4 py-2 rounded bg-ink text-paper font-mono text-xs">На главную</a>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
