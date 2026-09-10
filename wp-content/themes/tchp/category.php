<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$tag_key  = $_GET['tag'] ?? 'basics';
$all_tags = ['basics' => 'Основы', 'smd' => 'SMD', 'tools' => 'Инструменты', 'materials' => 'Материалы'];
$tag_title = $all_tags[$tag_key] ?? 'Основы';

$filtered_articles = get_articles_by_tag($tag_key);
$page_num = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$paginated = paginate($filtered_articles, 6, $page_num);

$page_title = 'Категория: ' . $tag_title;
include __DIR__ . '/includes/header.php';
?>

<div class="article-hero" style="background:var(--accent-yellow); margin-bottom:2rem;">
    <span class="tag">Рубрика журнала</span>
    <h1 class="wavy-underline"><?= e($tag_title) ?></h1>
    <p style="font-family:var(--font-mono); font-weight:700; margin-top:1rem;">Материалы и практические руководства раздела «<?= e($tag_title) ?>».</p>
</div>

<div class="filters">
    <?php
    render_tag('all', 'Все статьи', $tag_key);
    foreach ($all_tags as $k => $label) {
        render_tag($k, $label, $tag_key);
    }
    ?>
</div>

<div class="grid">
    <?php foreach ($paginated['items'] as $index => $article): ?>
        <?php $article_url = "/article.php?slug=" . urlencode($article['slug']); ?>
        <article class="card card-half" data-tag="<?= e($article['tag_key']) ?>">
            <div class="card-img">
                <?= get_card_icon($article['icon'] ?? 'chip') ?>
            </div>
            <span class="tag"><?= e($article['tag']) ?></span>
            <h3><a href="<?= $article_url ?>"><?= e($article['title']) ?></a></h3>
            <p><?= e($article['excerpt']) ?></p>
            <a href="<?= $article_url ?>" class="read-more">Читать статью →</a>
        </article>
    <?php endforeach; ?>
</div>

<!-- PAGINATION -->
<?= render_pagination($paginated, "/category.php?tag=" . e($tag_key)) ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
