<?php
/**
 * category.php — Страница категории / тега
 * URL: /tag/{tag_key}/  →  category.php?tag={tag_key}
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$tag_key  = $_GET['tag'] ?? 'all';
$page_num = (int)($_GET['page'] ?? 1);

$all_tags = get_all_tags($articles);
$tag_label = $all_tags[$tag_key] ?? 'Все статьи';

$filtered   = get_articles_by_tag($articles, $tag_key);
$paginated  = paginate($filtered, 9, $page_num);

$page_title   = $tag_label . ' — Точка Плавления';
$page_desc    = 'Статьи по теме «' . $tag_label . '» на портале Точка Плавления.';
$current_page = 'sections';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Category hero -->
<section class="section" style="padding-bottom: 40px;">
  <nav class="breadcrumb" aria-label="Хлебные крошки">
    <a href="/">Главная</a>
    <span>›</span>
    <span><?= e($tag_label) ?></span>
  </nav>

  <div class="section-title-row">
    <div class="section-dash"></div>
    <h1 class="section-h2"><?= e($tag_label) ?></h1>
  </div>
  <p style="font-family: var(--font-mono); font-size: 13px; color: var(--text-muted); margin-bottom: 8px;">
    Найдено статей: <strong><?= count($filtered) ?></strong>
  </p>

  <!-- Tag filter row -->
  <div class="filter-row" style="margin-top: 20px;">
    <a href="/tag/all" class="filter-pill <?= $tag_key === 'all' ? 'active' : '' ?>">Все</a>
    <?php foreach ($all_tags as $k => $label): ?>
      <a href="/tag/<?= e($k) ?>" class="filter-pill <?= $tag_key === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<!-- Articles grid -->
<section class="section section--alt">
  <?php if (!empty($paginated['items'])): ?>
    <div class="articles-grid">
      <?php foreach ($paginated['items'] as $art): ?>
        <a href="/article/<?= e($art['slug']) ?>" class="article-card" data-cat="<?= e($art['tag_key']) ?>">
          <div class="card-img"><?= get_card_icon($art['icon']) ?></div>
          <div class="card-dash"></div>
          <span class="card-tag-pill"><?= e($art['tag']) ?></span>
          <h3 class="card-h3"><?= e($art['title']) ?></h3>
          <p class="card-body"><?= e($art['excerpt']) ?></p>
          <div class="card-topic-pills">
            <?php foreach ($art['topics'] as $t): ?>
              <span class="card-topic-pill"><?= e($t) ?></span>
            <?php endforeach; ?>
          </div>
          <div class="card-foot">
            <span>⏱ <?= (int)$art['read_min'] ?> мин · <?= format_date_ru($art['date']) ?></span>
            <div class="card-plus">+</div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?= render_pagination($paginated, '/tag/' . e($tag_key) . '?dummy=1') ?>

  <?php else: ?>
    <p style="font-family: var(--font-mono); color: var(--text-muted); padding: 40px 0; text-align: center;">
      По этому тегу статей пока нет.
    </p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/includes/cookie-banner.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
