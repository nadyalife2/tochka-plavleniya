<?php
$page_title = "Точка Плавления — Журнал и верстак инженера";
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/functions.php';

$active_tag = $_GET['tag'] ?? 'all';
$page_num   = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$filtered   = get_articles_by_tag($active_tag);
$paginated  = paginate($filtered, 6, $page_num);

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div>
        <h1>Паяем.<br><span>Проектируем.</span><br>Прошиваем.</h1>
        <p>Блог и лаборатория о том, как превратить кучу компонентов в работающее устройство. Без воды, с интерактивными калькуляторами и справочниками.</p>
        <a href="/interactive.php" class="btn-dark">Перейти к инструментам</a>
    </div>
    <div class="hero-img">
        <svg viewBox="0 0 320 220" width="100%" height="100%" fill="none" stroke="#1A1A1A" stroke-width="2.5" stroke-linecap="round">
            <rect x="20" y="20" width="280" height="180" rx="12" fill="#FFFFFF"/>
            <polyline points="40,60 90,60 90,110 140,110" stroke="#1A1A1A" stroke-width="3"/>
            <polyline points="190,60 190,90 160,90" stroke="#1A1A1A" stroke-width="3"/>
            <rect x="90" y="75" width="80" height="70" rx="8" fill="#FFD447" stroke="#1A1A1A" stroke-width="3"/>
            <text x="106" y="117" font-family="JetBrains Mono" font-size="14" font-weight="800" fill="#1A1A1A">IC-ТЧП</text>
            <circle cx="40" cy="60" r="5" fill="#C8F0C0" stroke="#1A1A1A" stroke-width="2"/>
            <circle cx="190" cy="60" r="5" fill="#FFB8A8" stroke="#1A1A1A" stroke-width="2"/>
        </svg>
    </div>
</section>

<div class="filters" id="article-filters">
    <?php
    render_tag('all', 'Все статьи', $active_tag);
    render_tag('basics', 'Основы', $active_tag);
    render_tag('smd', 'SMD', $active_tag);
    render_tag('tools', 'Инструменты', $active_tag);
    render_tag('materials', 'Материалы', $active_tag);
    ?>
</div>

<div class="grid" id="articles-grid">
    <?php foreach ($paginated['items'] as $index => $article): ?>
        <?php 
        $article_url = "/article.php?slug=" . urlencode($article['slug']); 
        $rating = (($article['id'] * 7) % 35) + 12;
        $comments = (($article['id'] * 3) % 8) + 2;
        $views = (($article['id'] * 243) % 900) + 150;
        ?>
        <?php if ($index === 0 && $paginated['current'] === 1): ?>
            <!-- Featured Card span 7 -->
            <article class="card featured" data-tag="<?= e($article['tag_key']) ?>">
                <div class="card-img">
                    <?= get_card_icon($article['icon'] ?? 'chip') ?>
                </div>
                <div>
                    <div class="card-author-row">
                        <span class="author-avatar-small"><?= get_avatar_char($article['author']) ?></span>
                        <span class="author-name-small"><?= e($article['author']) ?></span>
                        <span class="card-date-small"><?= e($article['date']) ?></span>
                    </div>
                    <span class="tag">Выбор редакции · <?= e($article['tag']) ?></span>
                    <h3><a href="<?= $article_url ?>" class="card-link"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['excerpt']) ?></p>
                    <div class="card-footer-metrics">
                        <div class="metric-rating">
                            <span class="vote-up">▲</span>
                            <span class="rating-value">+<?= $rating ?></span>
                            <span class="vote-down">▼</span>
                        </div>
                        <div class="metric-comments">💬 <?= $comments ?></div>
                        <div class="metric-views">👁️ <?= $views ?></div>
                        <span class="bookmark-btn">🔖</span>
                    </div>
                </div>
            </article>
        <?php else: ?>
            <!-- Regular Card span 5 or half -->
            <article class="card <?= ($index === 1 && $paginated['current'] === 1) ? '' : 'card-half' ?>" data-tag="<?= e($article['tag_key']) ?>">
                <div class="card-author-row">
                    <span class="author-avatar-small"><?= get_avatar_char($article['author']) ?></span>
                    <span class="author-name-small"><?= e($article['author']) ?></span>
                    <span class="card-date-small"><?= e($article['date']) ?></span>
                </div>
                <div class="card-img">
                    <?= get_card_icon($article['icon'] ?? 'tools') ?>
                </div>
                <span class="tag"><?= e($article['tag']) ?></span>
                <h3><a href="<?= $article_url ?>" class="card-link"><?= e($article['title']) ?></a></h3>
                <p><?= e($article['excerpt']) ?></p>
                <div class="card-footer-metrics">
                    <div class="metric-rating">
                        <span class="vote-up">▲</span>
                        <span class="rating-value">+<?= $rating ?></span>
                        <span class="vote-down">▼</span>
                    </div>
                    <div class="metric-comments">💬 <?= $comments ?></div>
                    <div class="metric-views">👁️ <?= $views ?></div>
                    <span class="bookmark-btn">🔖</span>
                </div>
            </article>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<!-- PAGINATION -->
<?= render_pagination($paginated, "/") ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
