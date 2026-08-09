<?php
/**
 * article.php — Шаблон одной статьи с комментариями и встроенными виджетами
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$slug    = $_GET['slug'] ?? '';
$article = get_article_by_slug($articles, $slug);

if (!$article) {
    header('Location: /404.php', true, 302);
    exit;
}

// ── ОБРАБОТКА КОММЕНТАРИЕВ ──────────────────────────────
$comments_file = __DIR__ . '/data/comments.json';
$comments = [];
if (file_exists($comments_file)) {
    $comments = json_decode(file_get_contents($comments_file), true) ?? [];
}

$comment_status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    $author_name = trim($_POST['author_name'] ?? '');
    $comment_text = trim($_POST['comment_text'] ?? '');
    
    if (!empty($author_name) && !empty($comment_text)) {
        $new_comment = [
            'slug' => $slug,
            'name' => htmlspecialchars($author_name),
            'date' => date('Y-m-d H:i'),
            'text' => htmlspecialchars($comment_text)
        ];
        $comments[] = $new_comment;
        file_put_contents($comments_file, json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $comment_status = 'success';
    } else {
        $comment_status = 'error';
    }
}

// Фильтр комментариев для данной статьи
$article_comments = array_filter($comments, fn($c) => ($c['slug'] ?? '') === $slug);

// Похожие статьи
$related = array_slice(
    array_filter($articles, fn($a) => $a['tag_key'] === $article['tag_key'] && $a['slug'] !== $slug),
    0, 3
);

$page_title   = e($article['title']) . ' — Точка Плавления';
$page_desc    = e($article['excerpt']);
$current_page = 'home';
$extra_css    = '/assets/css/article.css';

// Если статья про флюс — подключаем калькулятор и его стили/скрипты
if ($article['tag_key'] === 'flux') {
    $extra_css = '/assets/css/article.css';
    $extra_js  = '/assets/js/flux-calc.js';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Article Hero -->
<div class="article-hero">
  <nav class="breadcrumb" aria-label="Хлебные крошки">
    <a href="/">Главная</a>
    <span>›</span>
    <a href="/tag/<?= e($article['tag_key']) ?>"><?= e($article['tag']) ?></a>
    <span>›</span>
    <span><?= e($article['title']) ?></span>
  </nav>

  <div class="article-hero-img">
    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="#2e5b48" stroke-width="1.2" stroke-linecap="round">
      <?= get_card_icon($article['icon']) ?>
    </svg>
  </div>

  <div class="article-tag">
    <span class="card-tag-pill"><?= e($article['tag']) ?></span>
  </div>

  <h1 class="article-h1"><span class="wavy"><?= e($article['title']) ?></span></h1>

  <div class="article-meta">
    <span>✍ Автор: <strong><?= e($article['author'] ?? 'Инженер кэмпа') ?></strong></span>
    <span>⏱ <?= (int)$article['read_min'] ?> мин чтения</span>
    <span>📅 <?= format_date_ru($article['date']) ?></span>
  </div>
</div>

<!-- Layout: Article Body + Sidebar -->
<div class="article-layout">

  <!-- MAIN BODY -->
  <article class="article-body">
    <p class="lead-text" style="font-size:17px; font-weight:500; line-height:1.65; color:var(--text-main); margin-bottom:24px;">
      <?= e($article['excerpt']) ?>
    </p>

    <h2 id="intro">1. В чём на самом деле проблема?</h2>
    <p>
      Каждый, кто впервые берёт в руки паяльник с регулятором температуры, попадает в одну и ту же ловушку: «Плашка не плавит? Добавлю-ка я 400 градусов!». В итоге флюс сгорает в мгновение ока, образуя мгновенный нагар, олово окисляется и превращается в серую кашу, а медный пятак на текстолите отваливается из-за разрушения эпоксидного клея.
    </p>
    <p>
      Реальность в том, что температура на индикаторе станции — это температура датчика <em>внутри жала</em>, а не самого медного контакта. Когда вы касаетесь массивной ноги трансформатора или полигона заземления, тепло мгновенно уходит в плату, и реальная температура в точке пайки падает на 60–100°C.
    </p>

    <div class="callout callout--tip">
      <div class="callout-label">💡 Опыт верстака</div>
      Не крутите регулятор температуры вверх! Возьмите жало с большей теплоёмкостью (например, скос типа <strong>K</strong> или микроклиновое <strong>D24</strong> вместо тонкого игольчатого <strong>I</strong>). Площадь контакта решает 80% проблем с прогревом.
    </div>

    <h2 id="physics">2. Физика смачивания и температурный коридор</h2>
    <p>
      Чтобы получить зеркальный галтель с идеальной адгезией, нужно попасть в довольно узкий «температурный коридор»:
    </p>
    <ul>
      <li><strong>Нижняя граница:</strong> T_плавления + 40°C. Ниже этого припой паяется «всухую», образуя трещины и холодную пайку.</li>
      <li><strong>Оптимальный диапазон:</strong> T_плавления + 60…80°C. Припой мгновенно смачивает металл и растекается под действием поверхностного натяжения.</li>
      <li><strong>Верхняя граница:</strong> > 360°C. Активные компоненты флюса испаряются до того, как успеют снять оксидную плёнку.</li>
    </ul>

    <div class="callout callout--important">
      <div class="callout-label">⚠ Опасно для текстолита</div>
      При нагреве текстолита FR-4 выше 260°C на протяжении более 10 секунд начинает расслаиваться стеклотекстолитовая основа. Всегда держите контакт не дольше 3–4 секунд!
    </div>

    <h2 id="tables">3. Справочник температур под основные сплавы</h2>
    <p>Сохраните себе этот ориентир для настройки станций T12 / JBC / Quick:</p>

    <table>
      <thead>
        <tr>
          <th>Сплав</th>
          <th>T плавления</th>
          <th>T на станции (мелкий монтаж)</th>
          <th>T на станции (полигоны)</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>ПОС-61 (Sn63/Pb37)</strong></td>
          <td>183°C</td>
          <td>270–290°C</td>
          <td>310–330°C</td>
        </tr>
        <tr>
          <td><strong>SAC305 (Sn-Ag-Cu)</strong></td>
          <td>217°C</td>
          <td>320–340°C</td>
          <td>350–370°C</td>
        </tr>
        <tr>
          <td><strong>Сплав Розе (Bi/Pb/Sn)</strong></td>
          <td>94°C</td>
          <td>130–150°C</td>
          <td>160°C</td>
        </tr>
        <tr>
          <td><strong>Sn42/Bi58 (низкотемпературный)</strong></td>
          <td>138°C</td>
          <td>180–200°C</td>
          <td>220°C</td>
        </tr>
      </tbody>
    </table>

    <!-- ВСТРОЕННЫЙ ИНТЕРАКТИВНЫЙ ВИДЖЕТ (Если статья про флюс или инструменты) -->
    <?php if ($article['tag_key'] === 'flux'): ?>
      <div style="margin:40px 0; padding:28px; background:var(--bg-alt); border-radius:var(--r-inner); border:1.5px solid var(--border);">
        <div style="font-family:var(--font-mono); font-size:11px; font-weight:700; color:var(--accent-orange); text-transform:uppercase; margin-bottom:8px;">
          ⚡ Встроенный калькулятор флюса
        </div>
        <h3 style="font-family:var(--font-head); font-size:20px; font-weight:800; margin-bottom:12px;">Рассчитайте дозировку для этой операции</h3>
        <p style="font-family:var(--font-mono); font-size:13px; color:var(--text-muted); margin-bottom:20px;">
          Введите параметры вашей платы, чтобы узнать точно, сколько мл флюса понадобится и как его наносить.
        </p>

        <form class="calc-form" id="flux-calc-form" onsubmit="return false;" style="max-width:100%; background:#fff;">
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="form-group">
              <label class="form-label" for="flux-type">Тип флюса</label>
              <select class="form-select" id="flux-type" name="flux-type">
                <option value="rma223">RMA-223 (канифольный)</option>
                <option value="nc559" selected>NC-559 (безотмывочный)</option>
                <option value="liquid">Жидкий флюс</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="board-area">Площадь платы (см²)</label>
              <input class="form-input" type="number" id="board-area" name="board-area" value="50">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="comp-type">Тип монтажа</label>
            <select class="form-select" id="comp-type" name="comp-type">
              <option value="smd">SMD монтаж</option>
              <option value="bga">BGA / QFN корпуса</option>
              <option value="tht">THT выводной</option>
            </select>
          </div>
          <div class="calc-result visible" id="flux-result">
            <div class="calc-result-label">Результат расчёта</div>
            <div class="calc-result-value" id="result-value">3.75 мл</div>
            <div class="calc-result-desc" id="result-desc">NC-559: нанесите кистью тонким слоем.</div>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <h2 id="summary">4. Итог</h2>
    <p>
      Хорошая пайка — это реакция, в которой всё происходит вовремя. Подбирайте правильное жало, используйте качественный флюс и не перегревайте контакты. Если остались вопросы — задавайте в комментариях ниже!
    </p>

    <!-- (Раздел комментариев удалён по требованию) -->
  </article>

  <!-- SIDEBAR -->
  <aside class="article-sidebar">
    <!-- TOC -->
    <div class="toc-card">
      <div class="toc-title">Содержание</div>
      <ul class="toc-list" id="toc-list">
        <!-- Main.js autogenerates links from H2/H3 -->
      </ul>
    </div>

    <!-- Tags -->
    <div class="sidebar-card">
      <div class="sidebar-title">Теги статьи</div>
      <div class="tag-cloud">
        <?php foreach ($article['topics'] as $t): ?>
          <a href="/tag/<?= urlencode(strtolower($t)) ?>" class="tag-btn"><?= e($t) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Related -->
    <?php if (!empty($related)): ?>
    <div class="sidebar-card">
      <div class="sidebar-title">Читать также</div>
      <div class="related-list">
        <?php foreach ($related as $rel): ?>
          <a href="/article/<?= e($rel['slug']) ?>" class="related-item">
            <div class="related-icon"><?= get_card_icon($rel['icon']) ?></div>
            <div>
              <div class="related-title"><?= e($rel['title']) ?></div>
              <div class="related-time">⏱ <?= (int)$rel['read_min'] ?> мин</div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </aside>

</div><!-- /article-layout -->

<?php require_once __DIR__ . '/includes/cookie-banner.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
