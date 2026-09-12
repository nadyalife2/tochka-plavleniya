<?php
/**
 * The template for displaying archive pages (categories, tags, dates, authors)
 *
 * @package TCHP
 * @version 2.0.0
 */

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-8">

    <header class="border-b border-paper-border pb-6 space-y-2">
      <div class="pill-tag-yellow font-mono text-xs inline-block">Рубрика / Архив</div>
      <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink">
        <?php the_archive_title(); ?>
      </h1>
      <?php if (get_the_archive_description()) : ?>
        <div class="text-sm font-mono text-ink-muted">
          <?php the_archive_description(); ?>
        </div>
      <?php endif; ?>
    </header>

    <?php if (have_posts()) : ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while (have_posts()) : the_post(); ?>
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
      <p class="text-ink-muted py-8 font-mono text-center">В этой рубрике пока нет материалов.</p>
    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
