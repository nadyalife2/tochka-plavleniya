<?php
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="ru" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Пользовательское соглашение — ТОЧКА ПЛАВЛЕНИЯ</title>
  <meta name="description" content="Пользовательское соглашение, условия использования материалов и инженерный отказ от ответственности портала Точка Плавления."/>

  <!-- Immediate Theme Init Script -->
  <script>
    (function() {
      const saved = localStorage.getItem('tp_theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
        document.documentElement.classList.remove('light');
      } else {
        document.documentElement.classList.add('light');
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <!-- Self-Hosted Fonts & Compiled Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/build.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <style>
    :root {
      --color-paper: #faf8f5;
      --color-paper-subtle: #f3f0ea;
      --color-paper-border: #e6e2da;
      --color-paper-border-dark: #d3cdc2;
      --color-ink: #141414;
      --color-ink-muted: #5c5850;
      --color-ink-faint: #999388;
      --color-accent: #2563eb;
      --color-accent-light: #dbeafe;
      --color-accent-muted: #1d4ed8;
      --bg-color: #faf8f5;
      --dot-color: #d3cdc2;
      --card-bg: #ffffff;
      --card-border: #e6e2da;
    }

    html.dark {
      --color-paper: #18191b;
      --color-paper-subtle: #202226;
      --color-paper-border: #2e3238;
      --color-paper-border-dark: #40454e;
      --color-ink: #f3f4f6;
      --color-ink-muted: #a3aab5;
      --color-ink-faint: #6c7380;
      --color-accent: #60a5fa;
      --color-accent-light: #1e293b;
      --color-accent-muted: #93c5fd;
      --bg-color: #121315;
      --dot-color: #2b2e34;
      --card-bg: #1c1d21;
      --card-border: #2e3238;
    }

    body {
      background-color: var(--bg-color) !important;
      background-image: radial-gradient(var(--dot-color) 0.9px, transparent 0.9px) !important;
      background-size: 20px 20px !important;
      color: var(--color-ink);
      font-family: 'Space Grotesk', sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .bg-card {
      background-color: var(--card-bg);
      border-color: var(--card-border);
    }

    .sketch-pill-yellow {
      background: linear-gradient(104deg, rgba(254, 240, 138, 0.4) 0%, rgba(254, 240, 138, 0.9) 15%, rgba(253, 224, 71, 0.95) 85%, rgba(254, 240, 138, 0.4) 100%);
      border: 1px solid rgba(202, 138, 4, 0.5);
      border-radius: 255px 15px 225px / 15px 225px 15px 255px;
      padding: 0.15rem 0.6rem;
      display: inline-block;
    }
    html.dark .sketch-pill-yellow {
      background: linear-gradient(104deg, rgba(161, 98, 7, 0.3) 0%, rgba(161, 98, 7, 0.7) 15%, rgba(202, 138, 4, 0.8) 85%, rgba(161, 98, 7, 0.3) 100%);
      border: 1px solid rgba(234, 179, 8, 0.4);
      color: #fef08a !important;
    }

    /* CANONICAL BRAND LOGO — ТОЧКА ПЛАВЛЕНИЯ */
    .logo {
      font-family: 'Hanken Grotesk', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      font-size: 1.25rem;
      font-weight: 900;
      text-decoration: none;
      color: var(--color-ink);
      letter-spacing: -0.02em;
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      line-height: 1;
      transition: opacity 0.15s ease;
    }
    .logo:hover {
      opacity: 0.85;
    }
    .logo span {
      color: #141414;
      background: #facc15;
      padding: 0.05rem 0.35rem;
      border-radius: 3px;
      transform: skew(-6deg);
      display: inline-block;
      margin: 0 0.18rem;
      font-size: 1.05em;
      line-height: 0.9;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col">

  <!-- Header -->
  <header class="w-full border-b border-paper-border bg-paper/95 sticky top-0 z-40 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      <div class="flex items-center gap-6">
        <a class="logo" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        <nav class="hidden md:flex items-center gap-5 text-[13px] font-mono text-ink-muted">
          <a class="hover:text-ink transition-colors" href="index.php#articles">Статьи</a>
          <a class="hover:text-ink transition-colors" href="interactive.php">Верстак</a>
          <a class="hover:text-ink transition-colors" href="interactive.php#table">Сплавы</a>
        </nav>
      </div>

      <div class="flex items-center gap-3">
        <!-- Theme Toggle (Sketch Style) -->
        <button id="theme-toggle" type="button" class="sketch-pill-gray hover:border-ink/50 text-ink font-mono text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <svg class="w-3.5 h-3.5 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-3.5 h-3.5 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4.5"></circle>
            <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
          </svg>
          <span class="text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="interactive.php">
          <span>Верстак</span>
          <span class="material-symbols-outlined text-[13px]">build</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content -->
  <main class="w-full flex-grow pt-8 pb-20">
    <div class="max-w-[960px] mx-auto px-5 sm:px-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5">
        <a class="hover:text-ink transition-colors" href="index.php">Главная</a>
        <span>→</span>
        <span class="text-ink">Пользовательское соглашение</span>
      </nav>

      <!-- Hero -->
      <div class="border-b border-paper-border pb-6 mb-8 space-y-3">
        <div class="flex items-center gap-2">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">TERMS OF USE</span>
          <span class="text-xs font-mono text-ink-faint">РЕВИЗИЯ: СЕНТЯБРЬ 2026</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">
          Пользовательское соглашение и инженерный отказ от ответственности
        </h1>
        <p class="text-sm sm:text-base text-ink-muted leading-relaxed font-serif italic max-w-2xl">
          Условия использования материалов, расчетных моделей и интерактивных инструментов проекта «ТОЧКА ПЛАВЛЕНИЯ».
        </p>
      </div>

      <!-- Legal Content -->
      <article class="space-y-8 text-[14.5px] text-ink-muted leading-relaxed">

        <!-- 1. Предмет -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">01.</span> Предмет соглашения
          </h2>
          <p>
            1.1. Настоящее Пользовательское соглашение (далее — «Соглашение») регулирует порядок использования материалов, калькуляторов, таблиц термопрофилей и статей сайта <strong>«ТОЧКА ПЛАВЛЕНИЯ»</strong> (далее — «Сайт»).
          </p>
          <p>
            1.2. Использование любых разделов Сайта означает безоговорочное согласие пользователя с условиями настоящего Соглашения.
          </p>
        </section>

        <!-- 2. Инженерный дисклеймер -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">02.</span> Инженерный отказ от ответственности (Дисклеймер)
          </h2>
          <p>
            2.1. Все опубликованные материалы, включая температурные диапазоны, режимы преднагрева, рецептуры и симуляторы термопрофилей, носят <strong>исключительно научно-познавательный, справочный и ознакомительный характер</strong>.
          </p>
          <p>
            2.2. Пайка радиоэлектронных компонентов, работа с термовоздушными станциями, инфракрасными термостолами и химическими флюсами сопряжена с рисками термических ожогов, повреждения электронных компонентов и выделения аэрозолей.
          </p>
          <p>
            2.3. Администрация Сайта не несет ответственности за любой прямой или косвенный ущерб (повреждение печатных плат, кристалла BGA, оборудования), возникший в результате применения информации или расчетов, размещенных на Сайте. Инженер принимает решения на основе собственного производственного опыта и требований документации завода-изготовителя.
          </p>
        </section>

        <!-- 3. Авторские права -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">03.</span> Интеллектуальная собственность и правила цитирования
          </h2>
          <p>
            3.1. Текстовые материалы, интерактивные алгоритмы калькуляторов, авторские инженерные схемы и дизайн верстака являются объектами авторского права проекта «ТОЧКА ПЛАВЛЕНИЯ».
          </p>
          <p>
            3.2. Свободное цитирование и републикация фрагментов материалов разрешается при обязательном указании активной гиперссылки на первоисточник на сайте <code>tochka-plavleniya.ru</code>.
          </p>
        </section>

        <!-- 4. Изменения -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">04.</span> Изменение условий
          </h2>
          <p>
            4.1. Администрация Сайта оставляет за собой право вносить изменения в настоящее Соглашение в любое время без предварительного уведомления пользователей. Актуальная редакция всегда доступна по адресу <code>/terms.php</code>.
          </p>
        </section>

      </article>

      <div class="mt-10 pt-6 border-t border-paper-border flex flex-wrap items-center justify-between gap-4 text-xs font-mono text-ink-faint">
        <div>ТОЧКА ПЛАВЛЕНИЯ · 2026</div>
        <div class="flex items-center gap-4">
          <a class="text-ink hover:underline" href="privacy.php">Политика конфиденциальности →</a>
          <a class="text-ink hover:underline" href="index.php">На главную →</a>
        </div>
      </div>

    </div>
  </main>

  <!-- Footer -->
  <footer class="w-full border-t border-paper-border bg-paper py-8 text-ink-muted text-xs font-mono">
    <div class="max-w-[960px] mx-auto px-5 sm:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <a class="logo text-sm" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        <span class="text-ink-faint">·</span>
        <span class="text-ink-faint text-[11px]">Инженерная документация</span>
      </div>
      <div class="flex items-center gap-4 text-[12px]">
        <a class="hover:text-ink transition-colors" href="privacy.php">Конфиденциальность</a>
        <a class="hover:text-ink transition-colors" href="terms.php">Соглашение</a>
        <a class="hover:text-ink transition-colors" href="interactive.php">Верстак</a>
      </div>
    </div>
  </footer>

  <!-- Theme Toggle JS -->
  <script>
    (function() {
      const toggle = document.getElementById('theme-toggle');
      if (toggle) {
        toggle.addEventListener('click', function() {
          const isDark = document.documentElement.classList.toggle('dark');
          localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
        });
      }
    })();
  </script>

</body>
</html>
