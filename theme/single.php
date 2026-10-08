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

        // Difficulty & tools resolution from meta or lessons data
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

        $required_tools = [];
        if (!empty($lesson_data['required_tools'])) {
            $required_tools = array_keys($lesson_data['required_tools']);
        } elseif (!empty($article_data['required_tools'])) {
            $required_tools = $article_data['required_tools'];
        } else {
            $tools_meta = get_post_meta($post_id, 'tchp_tools', true);
            if (!empty($tools_meta)) {
                $required_tools = is_array($tools_meta) ? $tools_meta : array_map('trim', explode(',', $tools_meta));
            }
        }
        if (empty($required_tools)) {
            $required_tools = ['Паяльная станция', 'Термопара', 'Флюс'];
        }

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

        <!-- Main Container: 100% Identical to site2/article.php -->
        <main class="w-full flex-grow pt-8 pb-20" id="main-content">
          <div class="max-w-[1140px] mx-auto px-5 sm:px-8">

            <!-- Breadcrumbs -->
            <nav class="text-[12px] font-mono text-ink-faint mb-8 flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
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

                  <!-- Retro Illustration / Stamp Badge -->
                  <div class="w-12 h-12 rounded border border-paper-border bg-paper-subtle flex items-center justify-center text-ink">
                    <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewBox="0 0 24 24" width="24">
                      <rect height="18" rx="2" width="18" x="3" y="3"></rect>
                      <path d="M8 7v10"></path>
                      <path d="M16 7v10"></path>
                      <path d="M12 12h.01"></path>
                      <circle cx="12" cy="7" r="1"></circle>
                      <circle cx="12" cy="17" r="1"></circle>
                    </svg>
                  </div>

                  <!-- Article Header Block -->
                  <header class="space-y-4">
                    <h1 class="text-3xl sm:text-[38px] font-bold text-ink tracking-[-0.03em] leading-[1.18] font-sans">
                      <?= esc_html(get_the_title()) ?>
                    </h1>

                    <!-- Meta line -->
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-[13px] text-ink-muted font-mono pt-1 pb-2">
                      <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full border border-paper-border bg-paper-subtle text-[11px] flex items-center justify-center font-bold text-ink">ТП</span>
                        <span class="text-ink"><?= esc_html(get_the_author()) ?></span>
                      </div>
                      <span class="text-ink-faint">·</span>
                      <time datetime="<?= esc_attr(get_the_date('c')) ?>"><?= esc_html(get_the_date('j F Y')) ?></time>
                      <span class="text-ink-faint">·</span>
                      <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span>~<?= esc_html($reading_time) ?> мин чтения</span>
                      <span class="text-ink-faint">·</span>
                      <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">school</span>Уровень: <?= esc_html($difficulty) ?></span>
                      <span class="text-ink-faint">·</span>
                      <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-500 font-bold" title="Регламент соответствует стандарту IPC J-STD"><span class="material-symbols-outlined text-[14px]">verified</span>Проверено</span>
                    </div>

                    <?php if (!empty($required_tools)): ?>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[12px] font-mono">
                      <span class="text-ink-muted">Оснастка:</span>
                      <?php foreach ($required_tools as $tool): ?>
                        <span class="bg-paper-subtle px-1.5 py-0.5 rounded border border-paper-border text-ink"><?= esc_html($tool) ?></span>
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

                <!-- Gutenberg / Editor Dynamic Post Content (Cards rendered inside) -->
                <article class="article-content space-y-5 text-ink leading-relaxed">
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
                    <a href="<?= esc_url(get_permalink($prev_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 group shadow-xs">
                      <span class="text-[11px] font-mono text-ink-muted">← Предыдущая статья</span>
                      <span class="text-xs font-bold text-ink group-hover:text-accent transition-colors line-clamp-2"><?= esc_html(get_the_title($prev_post)) ?></span>
                    </a>
                  <?php else: ?>
                    <div></div>
                  <?php endif; ?>

                  <?php if ($next_post): ?>
                    <a href="<?= esc_url(get_permalink($next_post)) ?>" class="p-4 rounded-lg border border-paper-border bg-paper hover:bg-paper-subtle transition-colors flex flex-col gap-1 sm:text-right group shadow-xs">
                      <span class="text-[11px] font-mono text-ink-muted">Следующая статья →</span>
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
                  <div class="text-[11px] font-mono text-ink-faint uppercase">Другие материалы:</div>
                  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <?php foreach ($related_posts as $rel): ?>
                      <?php
                      $rel_cats = get_the_category($rel->ID);
                      $rel_cat_name = !empty($rel_cats) ? $rel_cats[0]->name : 'Материал';
                      ?>
                      <a class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-colors block shadow-xs" href="<?= esc_url(get_permalink($rel)) ?>">
                        <div class="text-[11px] font-mono text-ink-faint uppercase"><?= esc_html($rel_cat_name) ?></div>
                        <div class="font-medium text-ink mt-1 line-clamp-2"><?= esc_html(get_the_title($rel)) ?></div>
                      </a>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php endif; ?>

                <?php
                // Comments template
                if (comments_open() || get_comments_number()):
                  comments_template();
                endif;
                ?>

              </div><!-- /Central Editorial Column -->

              <!-- Sticky Floating Sidebar (Desktop) -->
              <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-6">

                <!-- Article Passport Card -->
                <div class="border border-paper-border bg-paper p-4 rounded-lg space-y-3 shadow-sm">
                  <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
                    <span>ПАСПОРТ СТАТЬИ</span>
                    <span class="text-accent font-bold"><?= esc_html($reading_time) ?> МИН</span>
                  </div>
                  <div class="space-y-2 text-xs font-mono">
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Статус:</span>
                      <span class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Регламент готов
                      </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Категория:</span>
                      <span class="text-ink font-bold"><?= esc_html($primary_cat ? $primary_cat->name : 'Монтаж РЭА') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Сложность:</span>
                      <span class="text-ink"><?= esc_html($difficulty) ?></span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-paper-border/60">
                      <span class="text-ink-muted">Чтение:</span>
                      <span class="text-ink">~<?= esc_html($reading_time) ?> мин</span>
                    </div>
                    <div class="flex items-center justify-between pt-1">
                      <span class="text-ink-muted">Стандарт:</span>
                      <span class="font-bold text-accent"><?= esc_html($standard) ?></span>
                    </div>
                  </div>
                </div>

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
                  <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
                    <span>СТАТЬИ РАЗДЕЛА</span>
                    <span>СЕРИЯ ТЧП</span>
                  </div>
                  <nav class="space-y-1 text-xs font-mono max-h-[360px] overflow-y-auto pr-1">
                    <a href="<?= esc_url(get_permalink($post_id)) ?>" class="block px-2.5 py-2 rounded transition-all bg-ink text-paper font-bold shadow-xs">
                      <div class="flex items-center justify-between gap-2">
                        <span class="truncate">→ <?= esc_html(get_the_title()) ?></span>
                        <span class="text-[10px] text-paper/70 shrink-0"><?= esc_html($reading_time) ?>м</span>
                      </div>
                    </a>
                    <?php foreach ($series_posts as $idx => $sp): ?>
                      <a href="<?= esc_url(get_permalink($sp)) ?>" 
                         class="block px-2.5 py-2 rounded transition-all text-ink-muted hover:text-ink hover:bg-paper-subtle">
                        <div class="flex items-center justify-between gap-2">
                          <span class="truncate"><?= ($idx + 2) ?>. <?= esc_html(get_the_title($sp)) ?></span>
                          <span class="text-[10px] text-ink-faint shrink-0">~5м</span>
                        </div>
                      </a>
                    <?php endforeach; ?>
                  </nav>
                </div>
                <?php endif; ?>

                <!-- Interactive Workbench Bridge -->
                <div class="sketch-card p-4 bg-paper border border-paper-border rounded-lg space-y-3 shadow-xs">
                  <div class="flex items-center justify-between font-mono text-xs pb-1.5 border-b border-paper-border">
                    <span class="pill-orange text-[10px] uppercase font-bold">ВЕРСТАК</span>
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
                      <div class="font-bold text-ink text-xs font-sans"><?= esc_html(get_the_author()) ?></div>
                      <div class="text-[11px] text-ink-muted font-mono">Лаборатория ТОЧКА ПЛАВЛЕНИЯ</div>
                    </div>
                  </div>
                  <div class="border-t border-paper-border pt-2">
                    <span class="pill-tag-mint text-[10px] font-mono">
                      ✓ Инженерная верификация
                    </span>
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
