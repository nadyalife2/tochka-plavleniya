<?php
/**
 * The template for displaying the front page
 *
 * @package TCHP
 * @version 2.0.0
 */

get_header();

// 1. Query real posts from WordPress
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$args = [
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'paged'          => $paged,
];
$main_query = new WP_Query($args);

// 2. Fallback mock data if DB is completely fresh
$has_real_posts = $main_query->have_posts();
$fallback_articles = tchp_get_fallback_articles();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-12">

    <!-- Hero Section -->
    <section class="border border-paper-border rounded-lg bg-card p-6 sm:p-10 relative overflow-hidden shadow-sm">
      <div class="max-w-2xl space-y-4 relative z-10">
        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-[11px] font-mono text-ink-muted">
          <span class="w-2 h-2 rounded-full bg-brand-orange animate-pulse"></span>
          <span>ОТКРЫТАЯ ИНЖЕНЕРНАЯ ЛАБОРАТОРИЯ // РЕГЛАМЕНТЫ 2026</span>
        </div>

        <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-ink leading-[1.15]">
          Паяем. Проектируем. Прошиваем.
        </h1>

        <p class="text-base sm:text-lg text-ink-muted leading-relaxed font-sans">
          База знаний и прикладной верстак инженера-электронщика: физика термопрофилей BGA, металлургия сплавов, химия флюсов и защита от брака пайки.
        </p>

        <div class="flex flex-wrap items-center gap-3 pt-2">
          <a class="inline-flex items-center gap-2 px-4 py-2 border border-paper-border-dark bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="#articles">
            <span>Каталог материалов</span>
            <span class="text-xs">↓</span>
          </a>
          <a class="inline-flex items-center gap-2 px-3.5 py-2 border border-paper-border bg-paper text-ink text-xs font-mono font-medium rounded hover:border-paper-border-dark transition-colors" href="<?php echo esc_url(home_url('/interactive/')); ?>">
            <span>Инженерные калькуляторы</span>
          </a>
        </div>
      </div>
    </section>

    <!-- Articles Grid Header & Anchor -->
    <div id="articles" class="space-y-6">
      <div class="flex items-center justify-between border-b border-paper-border pb-4 flex-wrap gap-4">
        <div>
          <h2 class="text-2xl font-bold tracking-tight text-ink">Статьи и регламенты</h2>
          <p class="text-xs font-mono text-ink-muted mt-0.5">Практика без воды, проверенная на верстаке</p>
        </div>
        
        <div class="text-xs font-mono text-ink-faint">
          IPC/JEDEC J-STD-020D
        </div>
      </div>

      <!-- Main Layout: 8 cols Articles + 4 cols Sidebar -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

        <!-- Articles Column (8 cols) -->
        <div class="lg:col-span-8 space-y-6">
          <?php if ($has_real_posts) : ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <?php while ($main_query->have_posts()) : $main_query->the_post(); ?>
                <article <?php post_class('border border-paper-border rounded-lg bg-card p-5 space-y-3 shadow-sm hover:border-paper-border-dark transition-all flex flex-col justify-between'); ?>>
                  <div class="space-y-3">
                    <?php if (has_post_thumbnail()) : ?>
                      <div class="rounded overflow-hidden border border-paper-border h-40 bg-paper">
                        <?php the_post_thumbnail('medium_large', ['class' => 'w-full h-full object-cover']); ?>
                      </div>
                    <?php endif; ?>

                    <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                      <span><?php echo get_the_date('j M Y'); ?></span>
                      <span class="pill-tag-yellow text-[11px]"><?php echo get_the_category()[0]->name ?? 'Гайд'; ?></span>
                    </div>

                    <h3 class="font-bold text-lg text-ink leading-snug">
                      <a class="hover:underline decoration-ink" href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
                      </a>
                    </h3>

                    <p class="text-xs text-ink-muted leading-relaxed">
                      <?php echo wp_trim_words(get_the_excerpt(), 22); ?>
                    </p>
                  </div>

                  <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                    <span class="text-ink-faint">Автор: <?php the_author(); ?></span>
                    <a class="text-brand-orange font-medium hover:underline" href="<?php the_permalink(); ?>">Читать →</a>
                  </div>
                </article>
              <?php endwhile; wp_reset_postdata(); ?>
            </div>

          <?php else : ?>

            <!-- Fallback render from articles-data if no DB posts yet -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <?php foreach (array_slice($fallback_articles, 0, 6) as $article) : ?>
                <article class="border border-paper-border rounded-lg bg-card p-5 space-y-3 shadow-sm hover:border-paper-border-dark transition-all flex flex-col justify-between">
                  <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                      <span><?php echo esc_html($article['date'] ?? '2026'); ?></span>
                      <span class="pill-tag-yellow text-[11px]"><?php echo esc_html($article['tag'] ?? 'Основы'); ?></span>
                    </div>

                    <h3 class="font-bold text-lg text-ink leading-snug">
                      <a class="hover:underline decoration-ink" href="<?php echo esc_url(home_url('/article.php?slug=' . urlencode($article['slug'] ?? ''))); ?>">
                        <?php echo esc_html($article['title']); ?>
                      </a>
                    </h3>

                    <p class="text-xs text-ink-muted leading-relaxed">
                      <?php echo esc_html($article['excerpt']); ?>
                    </p>
                  </div>

                  <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                    <span class="text-ink-faint">Автор: <?php echo esc_html($article['author'] ?? 'ТЧП'); ?></span>
                    <a class="text-brand-orange font-medium hover:underline" href="<?php echo esc_url(home_url('/article.php?slug=' . urlencode($article['slug'] ?? ''))); ?>">Читать →</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>

          <?php endif; ?>
        </div>

        <!-- Right Column: Sidebar (4 cols) -->
        <aside class="lg:col-span-4 space-y-6">
          <!-- Laboratory Specs -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle/50 space-y-3">
            <div class="flex items-center justify-between text-xs font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
              <span>Регламенты ТЧП</span>
              <span class="pill-tag-mint text-[11px]">OPEN LAB</span>
            </div>
            <p class="text-xs text-ink-muted leading-relaxed">
              Все опубликованные профили проверены контактными термопарами K-типа на многослойных платах (до 8 слоёв FR-4).
            </p>
          </div>

          <!-- Reference Temperatures Box -->
          <div class="border border-paper-border rounded-lg bg-card p-5 space-y-3 relative shadow-sm">
            <div class="flex items-center justify-between">
              <div class="text-[11px] font-mono text-ink-faint uppercase">Опорные температуры:</div>
              <span class="font-mono text-[11px] text-ink-faint">FIG. 1.2</span>
            </div>
            <div class="space-y-2 font-mono text-xs">
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">SAC305 (ликвидус)</span>
                <span class="font-bold text-ink">217°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">ПОС-61 (эвтектика)</span>
                <span class="font-bold text-ink">183°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">Sn42Bi58 (низкотемп.)</span>
                <span class="font-bold text-ink">138°C</span>
              </div>
              <div class="flex items-center justify-between p-2 bg-paper-subtle border border-paper-border rounded">
                <span class="text-ink font-medium">Макс. пик кристалла</span>
                <span class="font-bold text-brand-orange">245°C</span>
              </div>
            </div>
          </div>
        </aside>

      </div>
    </div>

  </div>
</main>

<?php
get_footer();
