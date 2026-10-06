<?php
/**
 * The template for displaying all single posts
 *
 * @package Site2
 * @version 2.2.0
 */

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$site_root = function_exists('home_url') ? home_url('/') : '/';
$assets_base = function_exists('get_template_directory_uri') ? get_template_directory_uri() : '';

// 1. If viewed in WordPress and post exists in database
if (have_posts()) {
    while (have_posts()) {
        the_post();

        $post_id    = get_the_ID();
        $page_title = get_the_title() . ' — ТОЧКА ПЛАВЛЕНИЯ';
        $excerpt_raw = has_excerpt() ? get_the_excerpt() : wp_trim_words(strip_tags(get_the_content()), 28, '...');
        $page_desc  = wp_strip_all_tags($excerpt_raw);
        $current_page = 'article';

        // Reading time calculation
        $reading_time = get_post_meta($post_id, 'tchp_reading_time', true);
        if (!$reading_time) {
            $content_text = strip_tags(get_the_content());
            $char_count   = function_exists('mb_strlen') ? mb_strlen($content_text, 'UTF-8') : strlen($content_text);
            $word_count   = $char_count > 0 ? (function_exists('mb_split') ? count(mb_split('\s+', $content_text)) : str_word_count($content_text)) : 80;
            $reading_time = max(2, ceil($word_count / 160));
        }

        // Categories & Standard badge
        $categories  = get_the_category();
        $primary_cat = !empty($categories) ? $categories[0] : null;
        $standard    = get_post_meta($post_id, 'tchp_standard', true) ?: 'ГОСТ / IPC-A-610';

        // Additional head tags (Schema.org & article CSS)
        ob_start();
        ?>
        <link rel="stylesheet" href="<?= esc_url($assets_base) ?>/assets/css/article.css">
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "TechArticle",
          "headline": <?= json_encode(get_the_title(), JSON_UNESCAPED_UNICODE) ?>,
          "description": <?= json_encode($page_desc, JSON_UNESCAPED_UNICODE) ?>,
          "datePublished": "<?= get_the_date('c') ?>",
          "dateModified": "<?= get_the_modified_date('c') ?>",
          "author": {
            "@type": "Person",
            "name": <?= json_encode(get_the_author(), JSON_UNESCAPED_UNICODE) ?>
          },
          "publisher": {
            "@type": "Organization",
            "name": "ТОЧКА ПЛАВЛЕНИЯ",
            "url": "<?= esc_url($site_root) ?>"
          }
        }
        </script>
        <?php
        $extra_head = ob_get_clean();

        // Include unified header
        include __DIR__ . '/includes/header.php';
        ?>

        <!-- Reading progress bar -->
        <div id="reading-progress"></div>
        <script>
          window.addEventListener('scroll', function() {
            var h = document.documentElement, b = document.body;
            var st = 'scrollTop', sh = 'scrollHeight';
            var percent = (h[st]||b[st]) / ((h[sh]||b[sh]) - h.clientHeight) * 100;
            var el = document.getElementById('reading-progress');
            if (el) el.style.width = Math.min(100, Math.max(0, percent)) + '%';
          });
        </script>

        <main class="w-full flex-grow pt-6 sm:pt-8 pb-16" id="main-content">
          <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 space-y-8 sm:space-y-10">

            <!-- Breadcrumbs & Category Badge -->
            <div class="flex items-center justify-between flex-wrap gap-4">
              <nav class="text-xs font-mono text-ink-muted flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
                <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>">Главная</a>
                <span>→</span>
                <?php if ($primary_cat): ?>
                  <a class="hover:text-ink transition-colors" href="<?= esc_url(get_category_link($primary_cat->term_id)) ?>"><?= esc_html($primary_cat->name) ?></a>
                  <span>→</span>
                <?php else: ?>
                  <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>#articles">Статьи</a>
                  <span>→</span>
                <?php endif; ?>
                <span class="text-ink font-medium truncate max-w-xs sm:max-w-md"><?= esc_html(get_the_title()) ?></span>
              </nav>
              <span class="pill-tag-orange text-xs font-mono font-medium">Регламент ТЧП</span>
            </div>

            <!-- Article Header Card -->
            <header class="bg-paper border border-paper-border rounded-lg p-6 sm:p-8 space-y-5 shadow-xs relative">
              <div class="space-y-4">
                <?php if ($primary_cat): ?>
                  <a href="<?= esc_url(get_category_link($primary_cat->term_id)) ?>" class="inline-block pill-tag-yellow font-mono text-xs hover:opacity-90 transition-opacity">
                    <?= esc_html($primary_cat->name) ?>
                  </a>
                <?php endif; ?>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-ink font-sans leading-[1.16]">
                  <?= esc_html(get_the_title()) ?>
                </h1>

                <!-- Meta Row -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs sm:text-sm text-ink-muted font-mono pt-2 border-t border-paper-border">
                  <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-accent">schedule</span>
                    <?= esc_html($reading_time) ?> мин чтения
                  </span>
                  <span class="text-ink-faint">·</span>
                  <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-ink-muted">calendar_today</span>
                    <time datetime="<?= get_the_date('c') ?>"><?= esc_html(get_the_date('j F Y')) ?></time>
                  </span>
                  <span class="text-ink-faint">·</span>
                  <span class="inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-ink-muted">person</span>
                    <?= esc_html(get_the_author()) ?>
                  </span>
                  <span class="text-ink-faint">·</span>
                  <span class="bg-paper-subtle px-1.5 py-0.5 rounded text-[11px] border border-paper-border text-ink-muted"><?= esc_html($standard) ?></span>
                </div>

                <?php if (has_excerpt()): ?>
                  <div class="text-lg text-ink font-serif leading-[1.65] pt-3 border-t border-paper-border">
                    <?= esc_html(get_the_excerpt()) ?>
                  </div>
                <?php endif; ?>
              </div>
            </header>

            <!-- Main Layout Grid (Content + Sidebar) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">

              <!-- Left/Center Column: Post Content -->
              <div class="lg:col-span-8 space-y-8">

                <?php if (has_post_thumbnail()): ?>
                  <div class="rounded-lg overflow-hidden border border-paper-border shadow-xs">
                    <?php the_post_thumbnail('large', ['class' => 'w-full h-auto block object-cover']); ?>
                  </div>
                <?php endif; ?>

                <!-- Gutenberg / Editor Dynamic Post Content -->
                <article class="article-content prose max-w-none text-ink font-serif leading-relaxed space-y-6">
                  <?php the_content(); ?>
                </article>

                <?php
                // Display Tags if present
                $post_tags = get_the_tags();
                if (!empty($post_tags)):
                ?>
                  <div class="flex items-center gap-2 flex-wrap pt-2">
                    <span class="font-mono text-xs text-ink-muted">Теги:</span>
                    <?php foreach ($post_tags as $tag): ?>
                      <a href="<?= esc_url(get_tag_link($tag->term_id)) ?>" class="text-xs font-mono px-2 py-0.5 rounded bg-paper-subtle border border-paper-border text-ink hover:border-paper-border-dark transition-colors">
                        #<?= esc_html($tag->name) ?>
                      </a>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <!-- Share Bar & Feedback -->
                <div class="flex flex-wrap items-center justify-between border border-paper-border bg-paper-subtle p-5 sm:p-6 rounded-lg gap-4">
                  <div>
                    <div class="font-bold text-ink text-sm font-mono">Понравился регламент?</div>
                    <div class="text-xs text-ink-muted font-sans mt-0.5">Поделитесь ссылкой с коллегами по монтажу и ремонту</div>
                  </div>
                  <button type="button" onclick="navigator.clipboard.writeText(window.location.href); alert('Ссылка на статью скопирована в буфер обмена!');" class="inline-flex items-center gap-2 px-4 py-2.5 rounded bg-ink text-paper text-xs font-mono font-medium hover:opacity-90 transition-opacity cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">link</span>
                    <span>Скопировать ссылку</span>
                  </button>
                </div>

                <!-- Post Navigation (Prev / Next) -->
                <nav class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2" aria-label="Навигация по статьям">
                  <?php
                  $prev_post = get_previous_post();
                  $next_post = get_next_post();
                  ?>
                  <?php if ($prev_post): ?>
                    <a href="<?= esc_url(get_permalink($prev_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 group">
                      <span class="text-[11px] font-mono text-ink-muted">← Предыдущая статья</span>
                      <span class="text-xs font-bold text-ink group-hover:text-accent transition-colors line-clamp-2"><?= esc_html(get_the_title($prev_post)) ?></span>
                    </a>
                  <?php else: ?>
                    <div></div>
                  <?php endif; ?>

                  <?php if ($next_post): ?>
                    <a href="<?= esc_url(get_permalink($next_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 sm:text-right group">
                      <span class="text-[11px] font-mono text-ink-muted">Следующая статья →</span>
                      <span class="text-xs font-bold text-ink group-hover:text-accent transition-colors line-clamp-2"><?= esc_html(get_the_title($next_post)) ?></span>
                    </a>
                  <?php endif; ?>
                </nav>

                <?php
                // Comments template
                if (comments_open() || get_comments_number()):
                  comments_template();
                endif;
                ?>

              </div>

              <!-- Right Column: Sidebar -->
              <aside class="lg:col-span-4 space-y-6">

                <!-- Author Card -->
                <div class="p-5 rounded-lg border border-paper-border bg-paper space-y-3 shadow-xs">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded border border-paper-border bg-ink text-paper font-mono font-bold flex items-center justify-center text-xs shrink-0">
                      ТП
                    </div>
                    <div>
                      <div class="font-bold text-ink text-sm font-sans"><?= esc_html(get_the_author()) ?></div>
                      <div class="text-xs text-ink-muted font-mono">Инженер ТОЧКА ПЛАВЛЕНИЯ</div>
                    </div>
                  </div>
                  <div class="border-t border-paper-border pt-2.5">
                    <span class="pill-tag-mint text-[11px] font-mono">
                      ✓ Инженерная верификация
                    </span>
                  </div>
                </div>

                <!-- Fast Tools Widget -->
                <div class="p-5 rounded-lg border border-paper-border bg-paper space-y-3 shadow-xs">
                  <h3 class="font-mono text-xs uppercase tracking-wider font-bold text-ink flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-accent">construction</span>
                    Инструменты инженера
                  </h3>
                  <div class="space-y-2 text-xs font-mono">
                    <a class="block p-2 rounded hover:bg-paper-subtle transition-colors text-ink border border-transparent hover:border-paper-border" href="<?= esc_url($site_root) ?>interactive.php#calculator">
                      → Калькулятор термопрофилей
                    </a>
                    <a class="block p-2 rounded hover:bg-paper-subtle transition-colors text-ink border border-transparent hover:border-paper-border" href="<?= esc_url($site_root) ?>interactive.php#table">
                      → Справочник сплавов и припоев
                    </a>
                    <a class="block p-2 rounded hover:bg-paper-subtle transition-colors text-ink border border-transparent hover:border-paper-border" href="<?= esc_url($site_root) ?>page-interactive.php">
                      → Полный верстак (8 утилит)
                    </a>
                  </div>
                </div>

                <!-- Recent / Related Articles Widget -->
                <div class="p-5 rounded-lg border border-paper-border bg-paper space-y-3 shadow-xs">
                  <h3 class="font-mono text-xs uppercase tracking-wider font-bold text-ink flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-accent">menu_book</span>
                    Другие материалы
                  </h3>
                  <div class="space-y-2 text-xs">
                    <?php
                    $related_query = new WP_Query([
                        'posts_per_page'      => 4,
                        'post__not_in'        => [$post_id],
                        'ignore_sticky_posts' => 1
                    ]);
                    if ($related_query->have_posts()):
                        while ($related_query->have_posts()): $related_query->the_post();
                    ?>
                      <a class="block p-2 rounded hover:bg-paper-subtle transition-colors text-ink font-medium" href="<?= esc_url(get_permalink()) ?>">
                        <div class="font-mono text-[10px] text-ink-muted"><?= esc_html(get_the_date('j M Y')) ?></div>
                        <div class="text-xs hover:text-accent transition-colors"><?= esc_html(get_the_title()) ?></div>
                      </a>
                    <?php
                        endwhile;
                        wp_reset_postdata();
                    else:
                    ?>
                      <a class="block p-2 rounded hover:bg-paper-subtle transition-colors text-ink font-mono text-xs" href="<?= esc_url($site_root) ?>#articles">
                        → Все статьи в журнале
                      </a>
                    <?php endif; ?>
                  </div>
                </div>

              </aside>

            </div>

          </div>
        </main>

        <?php
        include __DIR__ . '/includes/footer-editorial.php';
        break; // Display single post
    }
} else {
    // 2. Fallback: check static articles if viewed outside WordPress database
    $slug = $_GET['slug'] ?? '';
    if (!empty($slug) && (get_article_by_slug($slug) || get_lesson_by_slug($slug))) {
        require __DIR__ . '/article.php';
    } else {
        http_response_code(404);
        require __DIR__ . '/404.php';
    }
}
