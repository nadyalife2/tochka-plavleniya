<?php
/**
 * The template for displaying all pages
 *
 * @package TCHP
 * @version 2.0.0
 */

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[800px] mx-auto px-5 sm:px-8 space-y-8">

    <?php while (have_posts()) : the_post(); ?>

      <article id="post-<?php the_ID(); ?>" <?php post_class('space-y-6'); ?>>
        <header class="border-b border-paper-border pb-6">
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink">
            <?php the_title(); ?>
          </h1>
        </header>

        <div class="prose prose-neutral max-w-none text-[17px] leading-[1.7] text-ink/85 space-y-6">
          <?php the_content(); ?>
        </div>
      </article>

    <?php endwhile; ?>

  </div>
</main>

<?php
get_footer();
