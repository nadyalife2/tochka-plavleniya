<?php
/**
 * article.php — шаблон одной статьи
 */
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/functions.php';

$slug    = preg_replace('/[^a-z0-9-]/', '', $_GET['slug'] ?? '');
$article = get_article_by_slug($slug);

if (!$article) {
  http_response_code(404);
  require __DIR__ . '/404.php';
  exit;
}

$page_title = e($article['title']) . ' — Точка плавления';
$meta_desc  = e($article['excerpt']);
$is_article = true;
$extra_css  = 'article.css';

require_once __DIR__ . '/includes/header.php';
?>

<main>
  <div class="container">

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Хлебные крошки">
      <a href="/">Главная</a>
      <span class="breadcrumb__sep">›</span>
      <a href="/tag/<?= e($article['tag_key']) ?>"><?= e($article['tag']) ?></a>
      <span class="breadcrumb__sep">›</span>
      <span><?= e($article['title']) ?></span>
    </nav>

    <div class="grid-2col">

      <!-- Article content -->
      <article>
        <header style="margin-bottom:var(--space-l)">
          <span class="card__tag"><?= e($article['tag']) ?></span>
          <h1 style="margin-top:0.5rem"><?= e($article['title']) ?></h1>
          <p class="mono" style="color:var(--text-secondary);font-size:0.8rem;margin-top:0.5rem">
            <?= $article['read_min'] ?> мин чтения
          </p>
        </header>

        <!-- TL;DR -->
        <?php render_tldr($article['tldr'] ?? []); ?>

        <!-- Prose content -->
        <div class="prose flow">
          <p>
            Добро пожаловать в подробный разбор темы «<?= e($article['title']) ?>».
            Здесь мы разберём все ключевые аспекты — от теории до практики.
          </p>

          <div class="callout callout--tip">
            <p class="callout__label">💡 Совет</p>
            <p>Перед началом убедитесь, что у вас есть базовые инструменты: паяльная станция, флюс и хороший припой.</p>
          </div>

          <h2>Основы и теория</h2>
          <p>
            Пайка — это процесс соединения металлических поверхностей с помощью более легкоплавкого металла (припоя).
            Ключевой принцип: правильная температура и достаточное количество флюса.
          </p>

          <h2>Практические шаги</h2>
          <p>
            Начнём с подготовки рабочего места и инструментов. Каждый шаг важен — пропущенный этап
            может привести к холодному шву или повреждению компонента.
          </p>

          <div class="callout callout--warn">
            <p class="callout__label">⚠️ Важно</p>
            <p>Не держите паяльник на одном месте дольше 3-4 секунд — перегрев убивает компоненты и поднимает дорожки.</p>
          </div>

          <h2>Частые ошибки</h2>
          <p>
            Большинство проблем у начинающих — следствие спешки и неправильной температуры.
            Разберём топ-5 ошибок и как их избежать.
          </p>

          <div class="callout callout--danger">
            <p class="callout__label">🚫 Опасно</p>
            <p>Никогда не паяйте без вентиляции — пары флюса токсичны при длительном воздействии.</p>
          </div>

          <h2>Результат</h2>
          <p>
            Если вы следовали инструкции — шов получился блестящим, с правильной формой конуса.
            Матовый или бугристый шов — повод переделать.
          </p>
        </div>

        <!-- Tags -->
        <div class="card__pills" style="margin-top:var(--space-l)">
          <?php foreach ($article['pills'] as $pill): ?>
          <a href="/tag/<?= e($article['tag_key']) ?>" class="pill"><?= e($pill) ?></a>
          <?php endforeach; ?>
        </div>
      </article>

      <!-- Sidebar -->
      <aside class="sidebar" aria-label="Навигация по статье">
        <div class="glass-panel">
          <p class="glass-panel__title">/ Оглавление</p>
          <ul class="toc__list" id="toc-list"><!-- JS --></ul>
        </div>
        <div class="glass-panel">
          <p class="glass-panel__title">/ Читать также</p>
          <ul class="footer__links" style="gap:0.8rem">
            <?php
            $related = array_filter($GLOBALS['articles'] ?? [], fn($a) =>
              $a['slug'] !== $slug && $a['tag_key'] === $article['tag_key']
            );
            $related = array_slice($related, 0, 3);
            foreach ($related as $r):
            ?>
            <li><a href="/article/<?= e($r['slug']) ?>/" style="font-size:0.85rem"><?= e($r['title']) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </aside>

    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
