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
if (function_exists('have_posts') && have_posts()) {
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
        $standard    = get_post_meta($post_id, 'tchp_standard', true) ?: '';
        $std_class   = get_post_meta($post_id, 'tchp_standard_class', true) ?: '';

        // Difficulty, editorial, tools & FAQ resolution
        $post_slug   = get_post_field('post_name', $post_id);
        $lesson_data = function_exists('get_lesson_by_slug') ? get_lesson_by_slug($post_slug) : null;
        $article_data = function_exists('get_article_by_slug') ? get_article_by_slug($post_slug) : null;

        $difficulty = get_post_meta($post_id, 'tchp_difficulty', true);
        if (!$difficulty) {
            if (!empty($lesson_data['difficulty'])) {
                $difficulty = $lesson_data['difficulty'];
            } elseif (!empty($article_data['difficulty'])) {
                $difficulty = $article_data['difficulty'];
            } else {
                $difficulty = 'Инженер';
            }
        }

        // Editorial data (Status, Reviewer, Date)
        $editorial = function_exists('tchp_get_editorial_data') 
            ? tchp_get_editorial_data($post_id) 
            : [
                'status'        => 'unreviewed',
                'reviewer_id'   => 0,
                'reviewer_name' => '',
                'reviewed_at'   => '',
                'is_outdated'   => false,
                'is_verified'   => false,
            ];

        // Tools resolution (Meta takes precedence, empty meta stays empty, lesson is fallback only if meta absent)
        $required_tools = function_exists('tchp_get_article_tools') 
            ? tchp_get_article_tools($post_id) 
            : [];

        if (empty($required_tools) && !metadata_exists('post', $post_id, 'tchp_tools') && !metadata_exists('post', $post_id, 'tchp_tools_needed')) {
            if (!empty($lesson_data['required_tools'])) {
                foreach ($lesson_data['required_tools'] as $name => $desc) {
                    $required_tools[] = ['name' => (string) $name, 'model' => '', 'purpose' => (string) $desc];
                }
            } elseif (!empty($article_data['required_tools'])) {
                foreach ($article_data['required_tools'] as $item) {
                    $required_tools[] = ['name' => (string) $item, 'model' => '', 'purpose' => ''];
                }
            }
        }

        // FAQ items
        $article_faq = function_exists('tchp_get_article_faq') ? tchp_get_article_faq($post_id) : [];

        // Additional head tags (Schema.org & article CSS)
        ob_start();
        ?>
        <link rel="stylesheet" href="<?= esc_url($assets_base) ?>/assets/css/article.css">
        <?php
        $should_render_schema = function_exists('tchp_should_render_theme_schema')
            ? tchp_should_render_theme_schema($post_id)
            : !class_exists('RankMath');
        if ($should_render_schema):
        ?>
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
        <?php endif; ?>
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

        <!-- Main Container: 100% Identical to site2/article.php -->
        <main class="w-full flex-grow pt-8 pb-20" id="main-content">
          <div class="max-w-[1140px] mx-auto px-5 sm:px-8">

            <!-- Breadcrumbs -->
            <nav class="text-xs font-mono text-ink-faint mb-8 flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
              <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>">Главная</a>
              <span>→</span>
              <?php if ($primary_cat): ?>
                <a class="hover:text-ink transition-colors" href="<?= esc_url(get_category_link($primary_cat->term_id)) ?>"><?= esc_html($primary_cat->name) ?></a>
                <span>→</span>
              <?php else: ?>
                <a class="hover:text-ink transition-colors" href="<?= esc_url($site_root) ?>#articles">Гайды</a>
                <span>→</span>
              <?php endif; ?>
              <span class="text-ink truncate max-w-xs sm:max-w-md"><?= esc_html(get_the_title()) ?></span>
            </nav>

            <!-- Center Article + Table of Contents Layout -->
            <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-start">

              <!-- Central Editorial Column (730px wide max, space-y-5) -->
              <div class="lg:col-span-8 max-w-[730px] space-y-5">

                <!-- Article Header Card -->
                <div class="bg-paper border border-paper-border rounded-lg p-5 sm:p-8 space-y-5 shadow-sm">

                  <!-- Header Stamp & Technical Spec Bar -->
                  <div class="flex items-center justify-between pb-3 border-b border-paper-border text-xs font-mono">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded border border-paper-border bg-paper-subtle flex items-center justify-center text-ink shrink-0">
                        <svg fill="none" height="16" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewBox="0 0 24 24" width="16">
                          <rect height="18" rx="2" width="18" x="3" y="3"></rect>
                          <path d="M8 7v10"></path>
                          <path d="M16 7v10"></path>
                          <circle cx="12" cy="7" r="1"></circle>
                          <circle cx="12" cy="17" r="1"></circle>
                        </svg>
                      </div>
                      <span class="font-bold text-ink uppercase tracking-wider">
                        <?= !empty($standard) ? esc_html($standard) : 'ТЕХРЕГЛАМЕНТ РАБОТ' ?>
                      </span>
                    </div>
                    <div class="flex items-center gap-2">
                      <button type="button" onclick="window.print()" class="no-print hidden sm:inline-flex items-center gap-1.5 px-2 py-0.5 rounded border border-paper-border bg-paper hover:bg-paper-subtle text-ink-muted hover:text-ink text-stamp font-mono font-medium transition-colors cursor-pointer" title="Распечатать технологическую карту (Ctrl+P)">
                        <?= tp_icon('printer', 'w-3.5 h-3.5') ?>
                        <span>Печать техкарты</span>
                      </button>
                      <?php if (!empty($std_class)): ?>
                        <span class="font-mono text-stamp font-bold uppercase px-2 py-0.5 rounded border border-paper-border bg-paper-subtle text-ink">
                          <?= esc_html($std_class) ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Article Header Block -->
                  <header class="space-y-4">
                    <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-[-0.02em] leading-[1.18] font-serif">
                      <?= esc_html(get_the_title()) ?>
                    </h1>

                    <!-- Meta line -->
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs sm:text-sm text-ink-muted font-mono pt-1 pb-2">
                      <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full border border-paper-border bg-paper-subtle text-stamp flex items-center justify-center font-bold text-ink">ТП</span>
                        <span class="text-ink font-semibold"><?= esc_html(get_the_author()) ?></span>
                      </div>
                      <span class="text-ink-faint">·</span>
                      <time datetime="<?= esc_attr(get_the_date('c')) ?>"><?= esc_html(get_the_date('j F Y')) ?></time>
                      <span class="text-ink-faint">·</span>
                      <span class="flex items-center gap-1"><?= tp_icon('clock', 'w-3.5 h-3.5') ?>~<?= esc_html($reading_time) ?> мин чтения</span>
                      <span class="text-ink-faint">·</span>
                      <span class="flex items-center gap-1"><?= tp_icon('graduation-cap', 'w-3.5 h-3.5') ?>Уровень: <?= esc_html($difficulty) ?></span>
                      <span class="text-ink-faint">·</span>
                      <?php if (!empty($editorial['is_verified'])): ?>
                        <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-500 font-bold" title="<?= esc_attr('Проверил инженер: ' . ($editorial['reviewer_name'] ?: 'ОТК') . (!empty($editorial['reviewed_at']) ? ' (' . date_i18n('d.m.Y', strtotime($editorial['reviewed_at'])) . ')' : '')) ?>">
                          <?= tp_icon('shield-check', 'w-3.5 h-3.5') ?>Проверено редакцией
                        </span>
                      <?php else: ?>
                        <span class="flex items-center gap-1 text-ink-muted font-mono text-xs" title="Материал ожидает подтверждения инженером ОТК">
                          <?= tp_icon('clock', 'w-3.5 h-3.5') ?>Редакционная проверка не завершена
                        </span>
                      <?php endif; ?>
                    </div>

                    <?php if (!empty($required_tools)): ?>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs font-mono">
                      <span class="text-ink-muted uppercase font-bold text-stamp">Оснастка:</span>
                      <?php foreach ($required_tools as $tool): ?>
                        <?php
                        $tool_name = is_array($tool) ? ($tool['name'] ?? '') : (string) $tool;
                        $tool_model = is_array($tool) ? ($tool['model'] ?? '') : '';
                        $tool_purpose = is_array($tool) ? ($tool['purpose'] ?? '') : '';
                        if (empty($tool_name)) continue;
                        ?>
                        <span class="bg-paper-subtle px-2 py-0.5 rounded border border-paper-border text-ink-muted font-mono font-medium" <?= !empty($tool_purpose) ? 'title="' . esc_attr($tool_purpose) . '"' : '' ?>>
                          <?= esc_html($tool_name) ?><?= !empty($tool_model) ? ' (' . esc_html($tool_model) . ')' : '' ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Lead Paragraph -->
                    <?php if (has_excerpt()): ?>
                      <p class="text-lg text-ink font-serif leading-[1.65] pt-2">
                        <?= esc_html(get_the_excerpt()) ?>
                      </p>
                    <?php endif; ?>
                  </header>

                </div><!-- /Article Header Card -->

                <?php if (has_post_thumbnail()): ?>
                  <div class="rounded-lg overflow-hidden border border-paper-border shadow-xs bg-paper">
                    <?php the_post_thumbnail('large', ['class' => 'w-full h-auto block object-cover']); ?>
                  </div>
                <?php endif; ?>

                <?php
                // Clean content: remove duplicate lead paragraph and extract H2 headings for TOC
                $post_content_raw = get_the_content();
                $post_content_html = apply_filters('the_content', $post_content_raw);
                $excerpt_text = trim(strip_tags(get_the_excerpt()));

                // 1. Remove duplicate lead paragraph if the content starts with it
                if (!empty($excerpt_text)) {
                    $post_content_html = preg_replace_callback('/^\s*<p[^>]*>(.*?)<\/p>/is', function($matches) use ($excerpt_text) {
                        $p_text = trim(strip_tags($matches[1]));
                        $match_start = function_exists('mb_stripos') && function_exists('mb_substr')
                            ? (mb_stripos($p_text, mb_substr($excerpt_text, 0, 35, 'UTF-8'), 0, 'UTF-8') === 0)
                            : (stripos($p_text, substr($excerpt_text, 0, 35)) === 0);
                        if ($p_text === $excerpt_text || $match_start) {
                            return ''; // Drop duplicate lead
                        }
                        return $matches[0];
                    }, $post_content_html);
                }

                // 2. Extract Headings for TOC and ensure document-wide anchor uniqueness
                $article_toc = [];
                // Pre-reserve template IDs to prevent collisions with UI controls
                $seen_ids = [
                    'main-content'          => true,
                    'article-faq'           => true,
                    'reading-progress'      => true,
                    'copy-article-link-btn' => true,
                    'mobile-menu'           => true,
                    'theme-toggle'          => true,
                    'table-of-contents'     => true,
                ];

                // PASS 1: Pre-collect all existing IDs from non-H2 tags in article content
                // (e.g. <section id="...">, <div id="...">, etc.) so no H2 can clash with them!
                if (preg_match_all('/<([a-z0-9]+)\b[^>]*\bid=(["\'])([^"\']+)\2[^>]*>/i', $post_content_html, $all_tag_matches, PREG_SET_ORDER)) {
                    foreach ($all_tag_matches as $tag_match) {
                        $tag_name = strtolower($tag_match[1]);
                        $found_id = $tag_match[3];
                        if ($tag_name !== 'h2') {
                            $seen_ids[$found_id] = true;
                        }
                    }
                }

                // PASS 2: Process H2 headings sequentially, ensuring 100% document-wide uniqueness
                $post_content_html = preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/i', function($matches) use (&$article_toc, &$seen_ids) {
                    $attrs = $matches[1];
                    $heading_content = $matches[2];
                    $heading_title = trim(strip_tags($heading_content));

                    // Case A: id is already present in attributes
                    if (preg_match('/\bid=(["\'])([^"\']+)\1/i', $attrs, $id_match)) {
                        $existing_id = $id_match[2];
                        if (!isset($seen_ids[$existing_id])) {
                            // Unique and not colliding with template: preserve untouched
                            $seen_ids[$existing_id] = true;
                            $article_toc[] = [
                                'id'    => $existing_id,
                                'title' => $heading_title,
                            ];
                            return $matches[0];
                        }

                        // Duplicate or template collision: generate unique suffix (-2, -3, ...)
                        $counter = 2;
                        $candidate = $existing_id . '-' . $counter;
                        while (isset($seen_ids[$candidate])) {
                            $counter++;
                            $candidate = $existing_id . '-' . $counter;
                        }
                        $seen_ids[$candidate] = true;
                        $article_toc[] = [
                            'id'    => $candidate,
                            'title' => $heading_title,
                        ];
                        // Replace existing id in attrs
                        $new_attrs = preg_replace('/\bid=(["\'])' . preg_quote($existing_id, '/') . '\1/i', 'id="' . esc_attr($candidate) . '"', $attrs, 1);
                        return '<h2' . $new_attrs . '>' . $heading_content . '</h2>';
                    }

                    // Case B: No id present -> generate slug from title
                    $heading_slug = sanitize_title($heading_title);
                    if (empty($heading_slug)) {
                        $heading_slug = 'section';
                    }
                    $candidate = $heading_slug;
                    $counter = 2;
                    while (isset($seen_ids[$candidate])) {
                        $candidate = $heading_slug . '-' . $counter;
                        $counter++;
                    }
                    $seen_ids[$candidate] = true;
                    $article_toc[] = [
                        'id'    => $candidate,
                        'title' => $heading_title,
                    ];
                    return '<h2 id="' . esc_attr($candidate) . '"' . $attrs . '>' . $heading_content . '</h2>';
                }, $post_content_html);
                ?>

                <!-- Gutenberg / Editor Dynamic Post Content (Cards rendered inside) -->
                <article class="article-content space-y-5 text-ink leading-relaxed">
                  <?= $post_content_html; ?>
                </article>

                <?php if (!empty($article_faq)): ?>
                  <!-- Visible FAQ Section (Single Source of Truth) -->
                  <section class="border border-paper-border rounded-lg bg-paper p-5 sm:p-6 space-y-4 shadow-xs" id="article-faq">
                    <div class="flex items-center gap-2 pb-2 border-b border-paper-border">
                      <div class="w-7 h-7 rounded border border-paper-border bg-paper-subtle flex items-center justify-center text-ink shrink-0">
                        <?= tp_icon('help-circle', 'w-4 h-4 text-accent') ?>
                      </div>
                      <h2 class="text-lg sm:text-xl font-bold font-serif text-ink m-0">
                        Часто задаваемые вопросы (FAQ)
                      </h2>
                    </div>
                    <div class="space-y-3 pt-1">
                      <?php foreach ($article_faq as $faq_item): ?>
                        <details class="group border border-paper-border rounded bg-paper-subtle/50 p-3.5 open:bg-paper transition-colors">
                          <summary class="font-bold text-xs sm:text-sm text-ink cursor-pointer list-none flex items-center justify-between gap-2 select-none">
                            <span><?= esc_html($faq_item['q']) ?></span>
                            <span class="text-ink-muted text-xs font-mono transition-transform group-open:rotate-180" aria-hidden="true">▾</span>
                          </summary>
                          <div class="mt-2 text-xs sm:text-sm text-ink-muted leading-relaxed font-sans border-t border-paper-border/60 pt-2">
                            <?= esc_html($faq_item['a']) ?>
                          </div>
                        </details>
                      <?php endforeach; ?>
                    </div>
                  </section>
                <?php endif; ?>

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

                <!-- Interactive Tools Box (tochkicamp style bottom CTA) -->
                <div class="border border-paper-border rounded-lg bg-paper p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
                  <div class="space-y-0.5">
                    <div class="font-semibold text-sm text-ink">Инженерный справочник и калькуляторы ТЧП</div>
                    <p class="text-xs text-ink-muted">Таблицы термопрофилей, допуски IPC-A-610 и подбор флюсов в интерактивном верстаке.</p>
                  </div>
                  <a class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 border border-ink bg-ink text-paper text-xs font-mono font-medium rounded hover:bg-ink-muted transition-colors shrink-0 shadow-sm" href="<?= esc_url($site_root) ?>interactive.php">
                    <span>Открыть калькуляторы →</span>
                  </a>
                </div>

                <!-- Share Bar & Feedback -->
                <div class="flex flex-wrap items-center justify-between border border-paper-border bg-paper-subtle p-5 sm:p-6 rounded-lg gap-4 shadow-xs">
                  <div>
                    <div class="font-bold text-ink text-sm font-mono">Понравился регламент?</div>
                    <div class="text-xs text-ink-muted font-sans mt-0.5">Поделитесь ссылкой с коллегами по монтажу и ремонту</div>
                  </div>
                  <button type="button" id="copy-article-link-btn" onclick="navigator.clipboard.writeText(window.location.href).then(()=>{var t=this.querySelector('.btn-label');if(t){t.textContent='Скопировано!';setTimeout(()=>{t.textContent='Скопировать ссылку';},2500);}});" class="inline-flex items-center gap-2 px-4 py-2.5 rounded bg-ink text-paper text-xs font-mono font-medium hover:opacity-90 transition-opacity cursor-pointer focus-visible:ring-2 focus-visible:ring-accent">
                    <?= tp_icon('link', 'w-4 h-4') ?>
                    <span class="btn-label">Скопировать ссылку</span>
                  </button>
                </div>

                <!-- Post Navigation (Prev / Next) -->
                <nav class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2" aria-label="Навигация по статьям">
                  <?php
                  $prev_post = get_previous_post();
                  $next_post = get_next_post();
                  ?>
                  <?php if ($prev_post): ?>
                    <a href="<?= esc_url(get_permalink($prev_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 group shadow-xs">
                      <span class="text-stamp font-mono text-ink-muted">← Предыдущая статья</span>
                      <span class="text-xs font-bold text-ink group-hover:text-accent transition-colors line-clamp-2"><?= esc_html(get_the_title($prev_post)) ?></span>
                    </a>
                  <?php else: ?>
                    <div></div>
                  <?php endif; ?>

                  <?php if ($next_post): ?>
                    <a href="<?= esc_url(get_permalink($next_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 sm:text-right group shadow-xs">
                      <span class="text-stamp font-mono text-ink-muted">Следующая статья →</span>
                      <span class="text-xs font-bold text-ink group-hover:text-accent transition-colors line-clamp-2"><?= esc_html(get_the_title($next_post)) ?></span>
                    </a>
                  <?php endif; ?>
                </nav>

                <!-- Related Reading (3 articles) -->
                <?php
                $related_posts = get_posts([
                    'posts_per_page'      => 3,
                    'post__not_in'        => [$post_id],
                    'ignore_sticky_posts' => 1
                ]);
                if (!empty($related_posts)):
                ?>
                <div class="pt-4 space-y-3">
                  <div class="text-stamp font-mono text-ink-faint uppercase">Другие материалы:</div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <?php foreach ($related_posts as $rel): ?>
                      <?php
                      $rel_cats = get_the_category($rel->ID);
                      $rel_cat_name = !empty($rel_cats) ? $rel_cats[0]->name : 'Материал';
                      ?>
                      <a class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-colors block shadow-xs" href="<?= esc_url(get_permalink($rel)) ?>">
                        <div class="text-stamp font-mono text-ink-faint uppercase"><?= esc_html($rel_cat_name) ?></div>
                        <div class="font-medium text-ink mt-1 line-clamp-2"><?= esc_html(get_the_title($rel)) ?></div>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>

                <?php
                // Comments template
                if (function_exists('comments_open') && (comments_open() || get_comments_number())):
                  if (function_exists('comments_template')) comments_template();
                endif;
                ?>

              </div><!-- /Central Editorial Column -->

              <!-- Sticky Floating Sidebar (Desktop) -->
              <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-6">

                <!-- Article Passport Card -->
                <div class="border border-paper-border bg-paper p-4.5 rounded-lg space-y-3.5 shadow-sm">
                  <div class="flex items-center justify-between text-stamp font-mono pb-2.5 border-b border-paper-border">
                    <span class="font-bold text-ink uppercase flex items-center gap-1.5">
                      <span class="w-1.5 h-1.5 rounded-full bg-accent inline-block"></span>
                      ПАСПОРТ ТЕХПРОЦЕССА
                    </span>
                    <span class="text-accent font-bold"><?= esc_html($reading_time) ?> МИН</span>
                  </div>
                  <div class="space-y-2 text-xs font-mono">
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Статус:</span>
                      <?php if (!empty($editorial['is_verified'])): ?>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Регламент готов
                        </span>
                      <?php elseif (!empty($editorial['is_outdated'])): ?>
                        <span class="font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1" title="Контент изменен после последней проверки">
                          <span class="w-1.5 h-1.5 rounded-full bg-amber-500 inline-block"></span> На ревизии
                        </span>
                      <?php else: ?>
                        <span class="font-bold text-ink-muted flex items-center gap-1">
                          <span class="w-1.5 h-1.5 rounded-full bg-gray-400 inline-block"></span> Черновик
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Категория:</span>
                      <span class="text-ink font-bold"><?= esc_html($primary_cat ? $primary_cat->name : 'Монтаж РЭА') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Сложность:</span>
                      <span class="text-ink font-bold"><?= esc_html($difficulty) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Хронометраж:</span>
                      <span class="text-ink font-bold">~<?= esc_html($reading_time) ?> мин</span>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                      <span class="text-ink-muted">Стандарт:</span>
                      <span class="font-bold text-accent font-mono"><?= esc_html($standard) ?></span>
                    </div>
                  </div>
                </div>

                <!-- Article Table of Contents (TOC) -->
                <?php if (!empty($article_toc)): ?>
                <div class="border border-paper-border bg-paper p-4.5 rounded-lg space-y-3 shadow-sm">
                  <div class="flex items-center justify-between text-stamp font-mono text-ink pb-2 border-b border-paper-border">
                    <span class="font-bold uppercase tracking-wider">ОГЛАВЛЕНИЕ РЕГЛАМЕНТА</span>
                    <span class="text-accent font-bold"><?= count($article_toc) ?> ЭТАПА</span>
                  </div>
                  <nav class="space-y-1 text-xs font-mono">
                    <?php foreach ($article_toc as $idx => $toc_item): ?>
                      <a href="#<?= esc_attr($toc_item['id']) ?>" 
                         class="flex items-center gap-2 px-2.5 py-1.5 rounded transition-all text-ink-muted hover:text-ink hover:bg-paper-subtle group">
                        <span class="text-stamp text-ink-faint font-bold shrink-0 group-hover:text-accent">§ <?= sprintf('%02d', $idx + 1) ?></span>
                        <span class="truncate"><?= esc_html($toc_item['title']) ?></span>
                      </a>
                    <?php endforeach; ?>
                  </nav>
                </div>
                <?php endif; ?>

                <!-- Series / Category Articles Navigation -->
                <?php
                $series_query_args = [
                    'posts_per_page'      => 6,
                    'post__not_in'        => [$post_id],
                    'ignore_sticky_posts' => 1
                ];
                if ($primary_cat) {
                    $series_query_args['cat'] = $primary_cat->term_id;
                }
                $series_posts = get_posts($series_query_args);
                if (!empty($series_posts)):
                ?>
                <div class="border border-paper-border bg-paper p-4 rounded-lg space-y-3 shadow-sm">
                  <div class="flex items-center justify-between text-stamp font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
                    <span>СТАТЬИ РАЗДЕЛА</span>
                    <span>СЕРИЯ ТЧП</span>
                  </div>
                  <nav class="space-y-1 text-xs font-mono max-h-[360px] overflow-y-auto pr-1">
                    <a href="<?= esc_url(get_permalink($post_id)) ?>" class="block px-2.5 py-2 rounded transition-all bg-ink text-paper font-bold shadow-xs">
                      <div class="flex items-center justify-between gap-2">
                        <span class="truncate">→ <?= esc_html(get_the_title()) ?></span>
                        <span class="text-stamp text-paper/70 shrink-0"><?= esc_html($reading_time) ?>м</span>
                      </div>
                    </a>
                    <?php foreach ($series_posts as $idx => $sp): ?>
                      <a href="<?= esc_url(get_permalink($sp)) ?>" 
                         class="block px-2.5 py-2 rounded transition-all text-ink-muted hover:text-ink hover:bg-paper-subtle">
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate"><?= ($idx + 2) ?>. <?= esc_html(get_the_title($sp)) ?></span>
                          <span class="text-stamp text-ink-faint shrink-0">~5м</span>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </nav>
                </div>
                <?php endif; ?>

                <!-- Interactive Workbench Bridge -->
                <div class="sketch-card p-4 bg-paper border border-paper-border rounded-lg space-y-3 shadow-xs">
                  <div class="flex items-center justify-between font-mono text-xs pb-1.5 border-b border-paper-border">
                    <span class="pill-orange text-stamp uppercase font-bold">ВЕРСТАК</span>
                    <span class="text-ink-faint">LAB-TOOL</span>
                  </div>
                  <div class="space-y-1">
                    <div class="text-xs font-bold text-ink font-mono uppercase tracking-wide">
                      Калькуляторы и сплавы
                    </div>
                    <p class="text-xs text-ink-muted leading-relaxed font-serif">
                      Рассчитайте безопасный терморежим жала или подберите флюс под ваш сплав в 1 клик.
                    </p>
                  </div>
                  <a class="btn btn-primary w-full text-xs font-mono py-2 flex items-center justify-center gap-1.5" href="<?= esc_url($site_root) ?>interactive.php">
                    <span>Открыть калькуляторы</span>
                    <span>→</span>
                  </a>
                </div>

                <!-- Verified Engineer Badge -->
                <div class="p-4 rounded-lg border border-paper-border bg-paper space-y-2.5 shadow-xs">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded border border-paper-border bg-ink text-paper font-mono font-bold flex items-center justify-center text-xs shrink-0">
                      ТП
                    </div>
                    <div>
                      <div class="font-bold text-ink text-xs font-sans"><?= !empty($editorial['reviewer_name']) ? esc_html($editorial['reviewer_name']) : esc_html(get_the_author()) ?></div>
                      <div class="text-stamp text-ink-muted font-mono">Лаборатория ТОЧКА ПЛАВЛЕНИЯ</div>
                    </div>
                  </div>
                  <div class="border-t border-paper-border pt-2">
                    <?php if (!empty($editorial['is_verified'])): ?>
                      <span class="pill-tag-mint text-stamp font-mono">
                        ✓ Инженерная верификация
                      </span>
                    <?php elseif (!empty($editorial['is_outdated'])): ?>
                      <span class="text-stamp font-mono text-amber-600 dark:text-amber-400">
                        ⚠️ Требуется повторная верификация
                      </span>
                    <?php else: ?>
                      <span class="text-stamp font-mono text-ink-muted">
                        ⏳ Ожидает проверки инженером
                      </span>
                    <?php endif; ?>
                  </div>
                </div>

              </aside>

            </div><!-- /Layout Grid -->

          </div>
        </main>

        <!-- Interactive Checklist Client Script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          const checkboxes = document.querySelectorAll('.checklist-box input[type="checkbox"], .article-content input[type="checkbox"]');
          if (!checkboxes.length) return;
          const pageKey = 'tp_chk_' + window.location.pathname;

          try {
            const saved = JSON.parse(localStorage.getItem(pageKey) || '[]');
            checkboxes.forEach((cb, idx) => {
              if (saved.includes(idx)) {
                cb.checked = true;
                const parent = cb.closest('li') || cb.closest('label');
                if (parent) parent.classList.add('line-through', 'opacity-70');
              }
            });
          } catch(e) {}

          checkboxes.forEach((cb, idx) => {
            cb.addEventListener('change', function() {
              const parent = this.closest('li') || this.closest('label');
              if (parent) {
                if (this.checked) {
                  parent.classList.add('line-through', 'opacity-70');
                } else {
                  parent.classList.remove('line-through', 'opacity-70');
                }
              }
              const checkedIndices = Array.from(checkboxes)
                .map((c, i) => c.checked ? i : null)
                .filter(i => i !== null);
              localStorage.setItem(pageKey, JSON.stringify(checkedIndices));
            });
          });
        });
        </script>

        <?php
        include __DIR__ . '/includes/footer.php';
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
