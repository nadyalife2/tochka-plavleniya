<?php
/**
 * index.php — Главная страница «Точка плавления»
 */
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'Точка плавления — паяй, твори, понимай';
$meta_desc  = 'Образовательный блог о пайке электроники: статьи, инструменты и курсы для мейкеров';

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="section" id="hero" aria-label="Главный экран">
  <div class="container">
    <div class="hero">

      <div class="hero__left flow">
        <span class="hero__badge">Платформа для мейкеров</span>
        <h1 class="hero__title">
          Паяй.<br>
          Твори.<br>
          <span class="accent-underline">Понимай.</span>
        </h1>
        <p class="hero__sub mono">
          Разбираем электронику честно — без магии, зато с флюсом и осциллографом.
          10 глубоких статей, калькуляторы и живое сообщество.
        </p>
        <div class="hero__actions">
          <a href="#articles" class="btn btn--dark">К статьям →</a>
          <a href="#courses"  class="btn btn--outline">Наши курсы</a>
        </div>
        <div class="hero__social-proof">
          <div class="hero__avatars">
            <span>М</span><span>А</span><span>И</span>
          </div>
          <span>+2 500 мейкеров уже читают</span>
        </div>
      </div>

      <div class="hero__visual" aria-hidden="true">
        <svg viewBox="0 0 400 340" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Плата -->
          <rect x="40" y="80" width="300" height="200" rx="8" stroke="#fc6c2b" stroke-width="1.5" stroke-dasharray="4 3"/>
          <!-- Чип IC -->
          <rect x="130" y="140" width="100" height="70" rx="4" stroke="#1a1a1a" stroke-width="2" fill="#f5f0e8"/>
          <text x="180" y="180" font-family="IBM Plex Mono" font-size="9" fill="#1a1a1a" text-anchor="middle">IC-01</text>
          <!-- Выводы чипа -->
          <?php for($i=0;$i<5;$i++): ?>
          <line x1="<?= 140+$i*18 ?>" y1="140" x2="<?= 140+$i*18 ?>" y2="125" stroke="#1a1a1a" stroke-width="1.5"/>
          <line x1="<?= 140+$i*18 ?>" y1="210" x2="<?= 140+$i*18 ?>" y2="225" stroke="#1a1a1a" stroke-width="1.5"/>
          <?php endfor; ?>
          <!-- Дорожки -->
          <path d="M40 160 L130 160" stroke="#fc6c2b" stroke-width="1.2"/>
          <path d="M230 160 L340 160" stroke="#fc6c2b" stroke-width="1.2"/>
          <path d="M40 200 L130 200" stroke="#fc6c2b" stroke-width="1.2"/>
          <path d="M230 200 L340 200" stroke="#fc6c2b" stroke-width="1.2"/>
          <!-- Паяльник -->
          <g transform="translate(290,60) rotate(45)">
            <rect x="-6" y="0" width="12" height="60" rx="3" fill="#6b6560"/>
            <polygon points="-6,60 6,60 0,80" fill="#fc6c2b"/>
            <!-- Капля жара -->
            <circle cx="0" cy="88" r="4" fill="#fc6c2b" opacity="0.7">
              <animate attributeName="r" values="4;6;4" dur="1.2s" repeatCount="indefinite"/>
              <animate attributeName="opacity" values="0.7;0.2;0.7" dur="1.2s" repeatCount="indefinite"/>
            </circle>
          </g>
          <!-- Метка точки пайки -->
          <circle cx="130" cy="160" r="5" fill="#fc6c2b">
            <animate attributeName="r" values="5;8;5" dur="2s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values="1;0.4;1" dur="2s" repeatCount="indefinite"/>
          </circle>
        </svg>
      </div>

    </div>
  </div>
</section>

<!-- ФИЛЬТР -->
<div class="container">
  <div class="filter-bar" role="group" aria-label="Фильтр по темам">
    <button class="pill active" data-filter="all">Все</button>
    <button class="pill" data-filter="basics">Основы</button>
    <button class="pill" data-filter="smd">SMD</button>
    <button class="pill" data-filter="bga">BGA</button>
    <button class="pill" data-filter="tht">THT</button>
    <button class="pill" data-filter="remont">Ремонт</button>
    <button class="pill" data-filter="instrumenty">Инструменты</button>
    <button class="pill" data-filter="materialy">Материалы</button>
  </div>
</div>

<!-- ГОРЯЧИЕ СТАТЬИ -->
<section class="section" id="articles" aria-label="Горячие статьи">
  <div class="container">
    <div class="section-header">
      <div>
        <span class="section-header__tag">/ Горячее</span>
        <h2>Рекомендуемые статьи</h2>
      </div>
      <a href="/tag/basics" class="section-header__link">Все статьи →</a>
    </div>

    <div class="grid-articles">
      <?php
      $featured = get_featured_articles();
      foreach ($featured as $article) {
        render_card($article);
      }
      ?>
    </div>
  </div>
</section>

<!-- ЛЕНТА СТАТЕЙ + САЙДБАР -->
<section class="section section--muted" id="sections" aria-label="Все статьи">
  <div class="container">
    <div class="grid-2col">

      <!-- Лента -->
      <div>
        <div class="section-header">
          <div>
            <span class="section-header__tag">/ Лента</span>
            <h2>Все материалы</h2>
          </div>
        </div>

        <?php foreach ($articles as $a): ?>
        <a href="/article/<?= e($a['slug']) ?>/" class="article-row anim-in">
          <span class="article-row__tag"><?= e($a['tag']) ?></span>
          <span class="article-row__title"><?= e($a['title']) ?></span>
          <span class="article-row__time"><?= $a['read_min'] ?> мин</span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Сайдбар -->
      <aside class="sidebar" aria-label="Дополнительная навигация">
        <div class="glass-panel">
          <p class="glass-panel__title">/ Теги</p>
          <div class="card__pills">
            <a href="/tag/basics"     class="pill">Основы</a>
            <a href="/tag/smd"        class="pill">SMD</a>
            <a href="/tag/bga"        class="pill">BGA</a>
            <a href="/tag/tht"        class="pill">THT</a>
            <a href="/tag/remont"     class="pill">Ремонт</a>
            <a href="/tag/instrumenty" class="pill">Инструменты</a>
            <a href="/tag/materialy"  class="pill">Материалы</a>
          </div>
        </div>
        <div class="glass-panel">
          <p class="glass-panel__title">/ Разделы</p>
          <ul class="footer__links" style="gap:0.6rem">
            <li><a href="/interactive">🔧 Калькулятор флюса</a></li>
            <li><a href="/interactive#quiz">📋 Тест по пайке</a></li>
            <li><a href="/interactive#table">📊 Таблица припоев</a></li>
          </ul>
        </div>
      </aside>

    </div>
  </div>
</section>

<!-- ИНТЕРАКТИВ -->
<section class="section" id="interactive" aria-label="Интерактивные инструменты">
  <div class="container">
    <div class="section-header">
      <div>
        <span class="section-header__tag">/ Инструменты</span>
        <h2>Интерактив</h2>
      </div>
      <a href="/interactive" class="section-header__link">Открыть все →</a>
    </div>
    <div class="grid-articles">
      <div class="icard anim-in">
        <svg class="icard__icon" viewBox="0 0 32 32" fill="none"><rect x="4" y="4" width="24" height="24" rx="4" stroke="currentColor" stroke-width="1.5"/><path d="M10 16h12M16 10v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <h3 class="icard__title">Калькулятор флюса</h3>
        <p class="icard__desc mono">Введи тип флюса и площадь платы — получи точную дозировку и способ нанесения.</p>
        <a href="/interactive" class="icard__link">Открыть →</a>
      </div>
      <div class="icard anim-in">
        <svg class="icard__icon" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="12" stroke="currentColor" stroke-width="1.5"/><path d="M12 16l3 3 5-6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
        <h3 class="icard__title">Тест по пайке</h3>
        <p class="icard__desc mono">10 вопросов — от новичка до профи. Проверь знания и узнай, где пробелы.</p>
        <a href="/interactive#quiz" class="icard__link">Пройти →</a>
      </div>
      <div class="icard anim-in">
        <svg class="icard__icon" viewBox="0 0 32 32" fill="none"><rect x="4" y="8" width="24" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M4 14h24M10 14v12M22 14v12" stroke="currentColor" stroke-width="1.5"/></svg>
        <h3 class="icard__title">Таблица припоев</h3>
        <p class="icard__desc mono">Сравни 15+ сплавов по температуре плавления, составу и применению. С фильтрами.</p>
        <a href="/interactive#table" class="icard__link">Смотреть →</a>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="section section--muted" id="courses" aria-label="Курсы">
  <div class="container">
    <div class="cta-block">
      <span class="section-header__tag">[ Курсы ]</span>
      <h2 style="margin-top:0.5rem">Начни паять <span class="accent-underline">уверенно</span></h2>
      <p style="margin:1rem auto;max-width:50ch;color:var(--text-secondary)" class="mono">
        Живые онлайн-курсы с разбором реальных плат. От пайки первого резистора до BGA-реболлинга.
      </p>
      <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin-top:1.5rem">
        <a href="#" class="btn btn--dark">Записаться на курс →</a>
        <a href="#" class="btn btn--outline">Программа обучения</a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
