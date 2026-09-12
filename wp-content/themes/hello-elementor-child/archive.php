<?php
/**
 * Archive & Category template — ТОЧКА ПЛАВЛЕНИЯ
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();
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
</style>

<main class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
    
    <!-- Breadcrumbs -->
    <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5">
      <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
      <span>→</span>
      <span class="text-ink"><?php the_archive_title(); ?></span>
    </nav>

    <!-- Archive Header -->
    <div class="border-b border-paper-border pb-8 mb-10 space-y-3">
      <div class="flex items-center gap-2">
        <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">РУБРИКА ЛАБОРАТОРИИ</span>
      </div>
      <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight font-sans">
        <?php the_archive_title( '', '' ); ?>
      </h1>
      <?php if ( get_the_archive_description() ) : ?>
        <p class="text-sm sm:text-base text-ink-muted max-w-2xl font-serif italic">
          <?php echo get_the_archive_description(); ?>
        </p>
      <?php endif; ?>
    </div>

    <!-- Articles Grid -->
    <?php if ( have_posts() ) : ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php while ( have_posts() ) : the_post(); ?>
          <?php
            $post_id      = get_the_ID();
            $reading_time = get_post_meta( $post_id, 'tchp_reading_time', true ) ?: '6';
            $difficulty   = get_post_meta( $post_id, 'tchp_difficulty', true ) ?: 'Инженерный уровень';
            $solder_alloy = get_post_meta( $post_id, 'tchp_solder_alloy', true ) ?: 'SAC305';
          ?>
          <article class="border border-paper-border rounded-lg bg-card p-5 space-y-4 hover:border-paper-border-dark transition-all flex flex-col justify-between group">
            <div class="space-y-2.5">
              <div class="flex items-center justify-between font-mono text-[11px] text-ink-faint">
                <span class="sketch-pill-gray text-ink"><?php echo esc_html( $solder_alloy ); ?></span>
                <span><?php echo esc_html( $reading_time ); ?> мин</span>
              </div>
              <h2 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h2>
              <p class="text-xs text-ink-muted leading-relaxed font-sans line-clamp-3">
                <?php echo get_the_excerpt(); ?>
              </p>
            </div>

            <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
              <span class="text-ink-faint text-[11px]"><?php echo get_the_date( 'd.m.Y' ); ?></span>
              <a class="text-ink font-semibold group-hover:underline text-[11.5px]" href="<?php the_permalink(); ?>">Читать →</a>
            </div>
          </article>
        <?php endwhile; ?>
      </div>

      <!-- Pagination -->
      <div class="mt-12 text-center font-mono text-xs">
        <?php the_posts_pagination( [ 'mid_size' => 2, 'prev_text' => '← Назад', 'next_text' => 'Вперед →' ] ); ?>
      </div>

    <?php else : ?>
      <div class="p-8 border border-paper-border rounded-lg bg-card text-center space-y-2">
        <p class="font-mono text-sm text-ink-muted">В этой рубрике пока нет материалов.</p>
        <a class="inline-block font-mono text-xs text-ink underline" href="<?php echo esc_url( home_url( '/' ) ); ?>">Вернуться на главную →</a>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php get_footer(); ?>
