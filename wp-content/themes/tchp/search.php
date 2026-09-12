<?php
/**
 * The template for displaying Search Results pages
 *
 * @package TCHP
 * @version 2.0.0
 */

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-8">

    <header class="border-b border-paper-border pb-6 space-y-2">
      <div class="pill-tag-yellow font-mono text-xs inline-block">Поиск по лаборатории</div>
      <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink">
        Результаты поиска: «<?php echo esc_html(get_search_query()); ?>»
      </h1>
    </header>

    <?php if (have_posts()) : ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while (have_posts()) : the_post(); ?>
          <article <?php post_class('border border-paper-border rounded-lg bg-card p-5 space-y-3 shadow-sm hover:border-paper-border-dark transition-all flex flex-col justify-between'); ?>>
            <div class="space-y-3">
              <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                <span><?php echo get_the_date('j M Y'); ?></span>
                <span class="pill-tag-yellow text-[11px]"><?php echo get_the_category()[0]->name ?? 'Гайд'; ?></span>
              </div>

              <h2 class="font-bold text-lg text-ink leading-snug">
                <a class="hover:underline decoration-ink" href="<?php the_permalink(); ?>">
                  <?php the_title(); ?>
                </a>
              </h2>

              <p class="text-xs text-ink-muted leading-relaxed">
                <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
              </p>
            </div>

            <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
              <span class="text-ink-faint">Автор: <?php the_author(); ?></span>
              <a class="text-brand-orange font-medium hover:underline" href="<?php the_permalink(); ?>">Читать →</a>
            </div>
          </article>
        <?php endwhile; ?>
      </div>

      <div class="pt-8 flex justify-center">
        <?php
        the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '← Назад',
            'next_text' => 'Вперёд →',
        ]);
        ?>
      </div>

    <?php else : ?>
      <div class="p-8 border border-paper-border rounded-lg bg-paper-subtle/50 text-center space-y-3">
        <p class="text-ink-muted font-mono">По вашему запросу ничего не найдено.</p>
        <p class="text-xs text-ink-faint">Попробуйте ввести другие ключевые слова (например: «флюс», «BGA», «припой»).</p>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
