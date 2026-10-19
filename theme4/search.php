<?php
/**
 * search.php — Результаты поиска WordPress
 *
 * Без этого файла запрос /?s=... отдавался через index.php, то есть показывал
 * главную вместо результатов. Шаблон использует те же карточки-модули.
 *
 * @package ТОЧКА ПЛАВЛЕНИЯ
 * @version 4.0.0
 */

require_once __DIR__ . '/includes/functions.php';

$site_root   = function_exists('home_url') ? home_url('/') : '/';
$assets_base = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';
$tchp_query  = function_exists('get_search_query') ? get_search_query() : '';

$page_title   = 'Поиск: ' . $tchp_query . ' — ТОЧКА ПЛАВЛЕНИЯ';
$page_desc    = 'Результаты поиска по базе знаний «Точка Плавления»: регламенты пайки, сплавы, флюсы, дефекты и стандарты.';
$current_page = 'search';

$extra_head = '<link rel="stylesheet" href="' . esc_url($assets_base) . '/assets/css/article.css">';

require_once __DIR__ . '/includes/header.php';

$tchp_found = function_exists('have_posts') ? (int) $GLOBALS['wp_query']->found_posts : 0;
?>

<main id="main-content" class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1000px] mx-auto px-5 sm:px-8 py-6 space-y-6">

    <nav class="text-xs font-mono text-ink-faint flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
      <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>">Главная</a>
      <span aria-hidden="true">→</span>
      <span class="text-ink font-semibold">Поиск</span>
    </nav>

    <div class="space-y-3">
      <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-ink font-mono text-xs">
        <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block" aria-hidden="true"></span>
        <span class="font-bold tracking-wider uppercase">Поиск</span>
        <span class="text-ink-faint">/</span>
        <span class="text-stamp text-ink-muted uppercase"><?= (int) $tchp_found ?> результат(ов)</span>
      </div>

      <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight font-serif">
        <?php if ($tchp_query !== '') : ?>
          Результаты по запросу «<?= esc_html($tchp_query) ?>»
        <?php else : ?>
          Поиск по базе знаний
        <?php endif; ?>
      </h1>

      <form role="search" method="get" action="<?= esc_url($site_root) ?>" class="flex flex-col sm:flex-row gap-2 max-w-xl">
        <label class="sr-only" for="tchp-search-input">Поисковый запрос</label>
        <input id="tchp-search-input" type="search" name="s" value="<?= esc_attr($tchp_query) ?>"
               placeholder="ПОС-61, RMA-218, T12, BGA, перегрев…"
               class="flex-1 min-h-[44px] px-3 py-2 rounded border border-paper-border bg-paper text-ink font-sans text-sm focus:outline-none focus:border-paper-border-dark">
        <button type="submit" class="min-h-[44px] px-4 py-2 rounded bg-ink text-paper text-xs font-mono font-bold hover:opacity-90 transition-opacity">
          Искать
        </button>
      </form>
    </div>

    <?php if (function_exists('have_posts') && have_posts()) : ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <?php while (have_posts()) : the_post(); ?>
          <article class="border border-paper-border rounded-lg bg-card p-4 flex flex-col justify-between space-y-2.5 hover:border-ink/40 transition-colors">
            <div class="space-y-1.5">
              <div class="flex items-center justify-between text-xs font-mono text-ink-muted">
                <span class="uppercase font-bold"><?= esc_html(get_post_type()) ?></span>
                <span><?= esc_html(get_the_date('d.m.Y')) ?></span>
              </div>
              <h2 class="font-bold text-ink text-base leading-snug">
                <a href="<?= esc_url(get_permalink()) ?>" class="hover:underline decoration-accent decoration-2 underline-offset-4">
                  <?= esc_html(get_the_title()) ?>
                </a>
              </h2>
              <p class="text-xs text-ink-muted font-sans leading-relaxed line-clamp-3">
                <?= esc_html(wp_strip_all_tags(get_the_excerpt())) ?>
              </p>
            </div>
            <a href="<?= esc_url(get_permalink()) ?>" class="pt-2 border-t border-paper-border text-xs font-mono font-bold text-ink inline-flex items-center gap-1">
              <span>Открыть</span><span aria-hidden="true">→</span>
            </a>
          </article>
        <?php endwhile; ?>
      </div>

      <nav class="pt-4 font-mono text-xs text-ink-muted" aria-label="Навигация по страницам результатов">
        <?= function_exists('paginate_links') ? paginate_links(['mid_size' => 1, 'prev_text' => '← Назад', 'next_text' => 'Вперёд →']) : '' ?>
      </nav>
    <?php else : ?>
      <div class="border border-dashed border-paper-border rounded-lg bg-paper-subtle p-6 space-y-3">
        <h2 class="text-lg font-bold text-ink font-serif">Ничего не найдено</h2>
        <p class="text-sm text-ink-muted leading-relaxed">
          По запросу «<?= esc_html($tchp_query) ?>» материалов нет. Попробуйте марку припоя (ПОС-61, SAC305),
          флюса (RMA-218, ROL0) или тип дефекта (мостик, непропай, tombstoning).
        </p>
        <div class="flex flex-wrap gap-2 text-xs font-mono">
          <a href="<?= esc_url($site_root) ?>interactive.php#table" class="px-2.5 py-1.5 rounded border border-paper-border bg-paper text-ink hover:border-accent transition-colors">Реестр сплавов</a>
          <a href="<?= esc_url($site_root) ?>interactive.php#defect" class="px-2.5 py-1.5 rounded border border-paper-border bg-paper text-ink hover:border-accent transition-colors">Дерево дефектов</a>
          <a href="<?= esc_url($site_root) ?>#articles" class="px-2.5 py-1.5 rounded border border-paper-border bg-paper text-ink hover:border-accent transition-colors">Все статьи</a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
