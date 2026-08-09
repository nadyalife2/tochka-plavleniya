<?php
/**
 * index.php — Главная страница (Журнал & Верстак инженера)
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$page_title   = 'Точка Плавления — Честный портал о пайке и микроэлектронике';
$page_desc    = 'Практические гайды, честные тесты паяльных станций, калькуляторы и курсы от инженеров.';
$current_page = 'home';

$featured  = get_featured_articles($articles);
$all_tags  = get_all_tags($articles);

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── HERO ──────────────────────────────────────────── -->
<section class="hero">
  <div>
    <div class="hero-badge">◉ Портал и лаборатория мейкеров</div>
    <h1 class="hero-h1">
      Паяй.<br>
      Твори.<br>
      <span class="wavy">Понимай.</span>
    </h1>
    <p class="hero-sub">
      Практический инди-портал о микроэлектронике. От выбора первого жала T12-K и температуры 340°C до BGA-реболлинга и восстановления сложных плат под микроскопом.
    </p>
    <div class="hero-btns">
      <a href="#articles" class="btn-pill btn-dark">Читать статьи →</a>
      <a href="/interactive" class="btn-pill btn-outline">Калькуляторы и тест</a>
    </div>
    <div class="hero-social">
      <div class="hero-avatars">
        <span>А</span><span>И</span><span>М</span><span>К</span>
      </div>
      <div class="hero-proof">
        Сообщество из <strong>+2 500</strong> инженеров и мейкеров
      </div>
    </div>
  </div>

  <div class="hero-illustration">
    <div class="hero-svg-wrap">
      <!-- PCB + Soldering Iron sketch -->
      <svg viewBox="0 0 360 260" fill="none" stroke="#1c242b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
        <rect x="30" y="40" width="220" height="170" rx="8" stroke-width="1.8"/>
        <polyline points="60,70 100,70 100,110 140,110"/>
        <polyline points="60,90 80,90 80,130 160,130"/>
        <polyline points="200,70 200,90 170,90"/>
        <polyline points="220,120 180,120 180,150 120,150"/>
        <polyline points="60,160 90,160 90,180 130,180"/>
        <polyline points="200,160 200,180 160,180 160,170"/>
        <rect x="100" y="80" width="60" height="50" rx="4" fill="rgba(46,91,72,0.08)" stroke="#2e5b48" stroke-width="1.8"/>
        <text x="118" y="108" font-family="IBM Plex Mono" font-size="9" fill="#2e5b48" stroke="none" font-weight="700">IC-01</text>
        <line x1="100" y1="92" x2="88" y2="92"/><line x1="100" y1="102" x2="88" y2="102"/><line x1="100" y1="112" x2="88" y2="112"/>
        <line x1="160" y1="92" x2="172" y2="92"/><line x1="160" y1="102" x2="172" y2="102"/><line x1="160" y1="112" x2="172" y2="112"/>
        <circle cx="80" cy="70" r="4"/><circle cx="80" cy="70" r="2" fill="#1c242b"/>
        <circle cx="200" cy="70" r="4"/><circle cx="200" cy="70" r="2" fill="#1c242b"/>
        <rect x="55" y="155" width="10" height="6" rx="2" fill="rgba(46,91,72,0.2)"/>
        <rect x="195" y="155" width="10" height="6" rx="2" fill="rgba(46,91,72,0.2)"/>
        <g transform="translate(268, 28) rotate(40)">
          <rect x="0" y="0" width="16" height="90" rx="4" fill="rgba(249,247,242,0.9)" stroke="#1c242b" stroke-width="1.4"/>
          <polygon points="0,90 16,90 8,115" fill="rgba(249,247,242,0.9)" stroke="#1c242b" stroke-width="1.4"/>
          <polygon points="5,115 11,115 8,126" fill="#888"/>
          <circle cx="8" cy="126" r="4" fill="#fc6c2b" class="solder-dot"/>
          <g class="solder-heat" stroke="#fc6c2b" stroke-width="1.2" fill="none" opacity="0.7">
            <path d="M4 132 Q8 138 12 132"/>
            <path d="M2 140 Q8 148 14 140"/>
          </g>
        </g>
      </svg>
    </div>
  </div>
</section>

<!-- ── ГОРЯЧИЕ СТАТЬИ ─────────────────────────── -->
<section id="articles" class="section">
  <div class="section-title-row">
    <div class="section-dash"></div>
    <h2 class="section-h2">Горячие гайды года</h2>
  </div>

  <!-- Pill Filter -->
  <div class="filter-row" id="filter-row">
    <button class="filter-pill active" data-filter="all">Все категории</button>
    <?php foreach ($all_tags as $key => $label): ?>
      <button class="filter-pill" data-filter="<?= e($key) ?>"><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="articles-grid" id="articles-grid">
    <?php foreach ($articles as $art): ?>
      <a href="/article/<?= e($art['slug']) ?>" class="article-card" data-cat="<?= e($art['tag_key']) ?>">
        <div class="card-img">
          <?= get_card_icon($art['icon']) ?>
        </div>
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
          <span>⏱ <?= (int)$art['read_min'] ?> мин · ✍ <?= e($art['author'] ?? 'Инженер') ?></span>
          <div class="card-plus">+</div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<!-- ── РАЗДЕЛЫ + САЙДБАР ─────────────────────────── -->
<section id="sections" class="section section--alt">
  <div class="section-title-row">
    <div class="section-dash"></div>
    <h2 class="section-h2">Лента всех материалов</h2>
  </div>
  <div class="sections-layout">
    <div class="articles-list">
      <?php foreach ($articles as $art): ?>
        <a href="/article/<?= e($art['slug']) ?>" class="article-row" data-cat="<?= e($art['tag_key']) ?>">
          <span class="row-tag"><?= e($art['tag']) ?></span>
          <span class="row-title"><?= e($art['title']) ?></span>
          <span class="row-time">⏱ <?= (int)$art['read_min'] ?> мин</span>
        </a>
      <?php endforeach; ?>
    </div>

    <aside class="sidebar">
      <div class="sidebar-card">
        <div class="sidebar-title">Теги базы знаний</div>
        <div class="tag-cloud">
          <button class="tag-btn active" data-tag="all">Все</button>
          <?php foreach ($all_tags as $key => $label): ?>
            <button class="tag-btn" data-tag="<?= e($key) ?>"><?= e($label) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="sidebar-card">
        <div class="sidebar-title">Быстрый переход</div>
        <nav class="sidebar-nav">
          <a href="#articles">Горячие статьи</a>
          <a href="#sections">Все материалы</a>
          <a href="/interactive">Калькуляторы флюса</a>
          <a href="/interactive#quiz">Тест по пайке</a>
          <a href="#courses">Живой кэмп</a>
        </nav>
      </div>
    </aside>
  </div>
</section>

<!-- ── ИНТЕРАКТИВ ────────────────────────────────── -->
<section id="interactive-preview" class="section">
  <div class="section-title-row">
    <div class="section-dash"></div>
    <h2 class="section-h2">Инструменты инженера</h2>
  </div>
  <div class="interactive-grid">
    <div class="icard">
      <div class="icard-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M12 2v20M2 12h20M7 7l10 10M17 7L7 17"/></svg>
      </div>
      <h3 class="icard-h3">Калькулятор флюса</h3>
      <p class="icard-desc">Рассчитайте точную дозировку и способ нанесения флюса под вашу плату и тип монтажа.</p>
      <a href="/interactive#calculator" class="icard-link">Открыть калькулятор →</a>
    </div>
    <div class="icard">
      <div class="icard-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
      </div>
      <h3 class="icard-h3">Тест на мастера пайки</h3>
      <p class="icard-desc">10 практических вопросов о температурных профилях, сплавах Розе и BGA-монтаже.</p>
      <a href="/interactive#quiz" class="icard-link">Пройти тест →</a>
    </div>
    <div class="icard">
      <div class="icard-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
      </div>
      <h3 class="icard-h3">Справочник припоев</h3>
      <p class="icard-desc">Интерактивная таблица: ПОС-61, SAC305, Розе, Вуда — температуры, назначение, RoHS.</p>
      <a href="/interactive#table" class="icard-link">Открыть табличник →</a>
    </div>
  </div>
</section>

<!-- ── CTA / КУРСЫ ───────────────────────────────── -->
<section id="courses" class="section section--alt">
  <div class="cta-card">
    <div class="cta-hatch"></div>
    <div class="cta-body">
      <div class="cta-tag">[ Практический онлайн-кэмп ]</div>
      <h2 class="cta-h2">Освой микроэлектронику <span class="wavy">с практикующим наставником</span></h2>
      <p class="cta-sub">
        Разбираем все инструменты на практике: от первой пайки контактов до BGA-реболлинга под микроскопом. Маленькие группы, индивидуальный разбор ошибок и работа до результата.
      </p>
      <div class="cta-btns">
        <a href="#" class="btn-pill btn-dark">Записаться на кэмп →</a>
        <a href="#" class="btn-pill btn-outline">Программа обучения</a>
      </div>
    </div>
    <div class="cta-sketch">
      <svg width="180" height="140" viewBox="0 0 180 140" fill="none" stroke="#1c242b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" opacity="0.6">
        <rect x="20" y="20" width="120" height="90" rx="6"/>
        <polyline points="40,50 70,50 70,80 100,80"/>
        <polyline points="40,65 55,65 55,85"/>
        <rect x="70" y="42" width="36" height="28" rx="3" fill="rgba(46,91,72,0.1)" stroke="#2e5b48"/>
        <circle cx="48" cy="48" r="3" fill="#1c242b"/>
        <g transform="translate(130,20) rotate(35)">
          <rect x="0" y="0" width="10" height="55" rx="3" fill="rgba(249,247,242,0.8)" stroke="#1c242b" stroke-width="1.4"/>
          <polygon points="0,55 10,55 5,72" stroke="#1c242b" fill="rgba(249,247,242,0.8)"/>
          <circle cx="5" cy="72" r="3" fill="#fc6c2b" class="solder-dot"/>
        </g>
      </svg>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/cookie-banner.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
