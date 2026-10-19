<?php
/**
 * page.php — Универсальный шаблон статических страниц WordPress
 *
 * Без этого файла WordPress отдаёт любую страницу («О проекте», политика,
 * контакты) через index.php, то есть показывает главную. Шаблон сохраняет
 * редакционную сетку и типографику темы.
 *
 * @package ТОЧКА ПЛАВЛЕНИЯ
 * @version 4.0.0
 */

require_once __DIR__ . '/includes/functions.php';

$site_root    = function_exists('home_url') ? home_url('/') : '/';
$assets_base  = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';
$tchp_page_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

$tchp_page_name = $tchp_page_id && function_exists('get_the_title') ? get_the_title($tchp_page_id) : 'Страница';
$tchp_page_excerpt = '';
if ($tchp_page_id && function_exists('get_post_field')) {
    $tchp_page_excerpt = wp_strip_all_tags((string) get_post_field('post_excerpt', $tchp_page_id));
}

$page_title    = $tchp_page_name . ' — ТОЧКА ПЛАВЛЕНИЯ';
$page_desc     = $tchp_page_excerpt ?: 'Страница инженерного портала «Точка Плавления».';
$current_page  = 'page';
$canonical_url = $tchp_page_id && function_exists('get_permalink') ? get_permalink($tchp_page_id) : '';

$extra_head = '<link rel="stylesheet" href="' . esc_url($assets_base) . '/assets/css/article.css">';

require_once __DIR__ . '/includes/header.php';
?>

<main id="main-content" class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[860px] mx-auto px-5 sm:px-8 py-6 space-y-6">

    <nav class="text-xs font-mono text-ink-faint flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
      <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>">Главная</a>
      <span aria-hidden="true">→</span>
      <span class="text-ink font-semibold"><?= esc_html($tchp_page_name) ?></span>
    </nav>

    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-ink font-mono text-xs">
      <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block" aria-hidden="true"></span>
      <span class="font-bold tracking-wider uppercase">Страница</span>
      <span class="text-ink-faint">/</span>
      <span class="text-stamp text-ink-muted uppercase">Журнал ТЧП</span>
    </div>

    <h1 class="text-3xl sm:text-5xl font-bold text-ink tracking-tight font-serif leading-[1.15]">
      <?= esc_html($tchp_page_name) ?>
    </h1>

    <div class="article-content text-ink text-base leading-[1.75]">
      <?php if (function_exists('have_posts')) : ?>
        <?php while (have_posts()) : the_post(); ?>
          <?php the_content(); ?>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>

    <div class="pt-4 border-t border-paper-border flex flex-wrap items-center gap-3 text-xs font-mono text-ink-muted">
      <span>Полезное:</span>
      <a href="<?= esc_url($site_root) ?>interactive.php" class="text-ink underline hover:text-accent transition-colors">Интерактивный верстак</a>
      <span class="text-ink-faint">·</span>
      <a href="<?= esc_url($site_root) ?>#articles" class="text-ink underline hover:text-accent transition-colors">Статьи и регламенты</a>
    </div>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
