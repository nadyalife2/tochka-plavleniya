<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 *
 * @package TCHP
 * @version 2.1.0
 */

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-8">

    <header class="border-b border-paper-border pb-6 space-y-2">
      <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink font-sans">Журнал и база знаний</h1>
      <p class="text-sm font-mono text-ink-muted">Открытая база инженерных публикаций «Точка Плавления»</p>
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
                <?php
                $cats = get_the_category();
                if (!empty($cats)) :
                ?>
                  <span class="pill-tag-yellow text-[11px]"><?php echo esc_html($cats[0]->name); ?></span>
                <?php endif; ?>
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

      <!-- Pagination -->
      <div class="pt-8 flex justify-center">
        <?php
        the_posts_pagination([
            'mid_size'  => 2,
            'prev_text' => '← Назад',
            'next_text' => 'Вперёд →',
        ]);
        ?>
      </div>

    <?php else : 
      // Fallback articles if fresh database
      $fallback = tchp_get_fallback_articles();
      if (!empty($fallback)) :
    ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($fallback as $item) : ?>
          <article class="border border-paper-border rounded-lg bg-card p-5 space-y-3 shadow-sm hover:border-paper-border-dark transition-all flex flex-col justify-between">
            <div class="space-y-3">
              <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                <span><?php echo esc_html($item['date'] ?? date('d.m.Y')); ?></span>
                <span class="pill-tag-yellow text-[11px]"><?php echo esc_html($item['category'] ?? 'Гайд'); ?></span>
              </div>

              <h2 class="font-bold text-lg text-ink leading-snug">
                <a class="hover:underline decoration-ink" href="<?php echo esc_url(home_url('/article.php?slug=' . urlencode($item['slug']))); ?>">
                  <?php echo esc_html($item['title']); ?>
                </a>
              </h2>

              <p class="text-xs text-ink-muted leading-relaxed">
                <?php echo esc_html($item['excerpt']); ?>
              </p>
            </div>

            <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
              <span class="text-ink-faint"><?php echo esc_html($item['author'] ?? 'Лаборатория ТЧП'); ?></span>
              <a class="text-brand-orange font-medium hover:underline" href="<?php echo esc_url(home_url('/article.php?slug=' . urlencode($item['slug']))); ?>">Читать →</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <div class="text-center py-12 space-y-4">
        <h2 class="text-2xl font-bold text-ink">Статей пока нет</h2>
        <p class="text-ink-muted">Добавьте первую публикацию в консоли управления WordPress.</p>
      </div>
    <?php endif; endif; ?>

  </div>
</main>

<?php
get_footer();
