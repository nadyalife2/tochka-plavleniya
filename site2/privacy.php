<?php
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="ru" class="light">
<head>
  <meta charset="UTF-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title>Политика конфиденциальности — ТОЧКА ПЛАВЛЕНИЯ</title>
  <meta name="description" content="Политика конфиденциальности и положение о неприменении сбора персональных данных портала Точка Плавления."/>

  <!-- Immediate Theme Init Script (Zero Flash) -->
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

  <!-- Common Design Tokens & Base Styles -->
  <link rel="stylesheet" href="assets/css/common.css">
</head>
<body class="min-h-screen flex flex-col">

  <!-- Header -->
  <header class="w-full border-b border-paper-border bg-paper/95 sticky top-0 z-40 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      <div class="flex items-center gap-6">
        <a class="logo" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        <!-- Desktop Nav -->
        <?php 
        $current_page = 'privacy';
        include __DIR__ . '/includes/header-nav.php'; 
        ?>
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
        <span class="text-ink">Политика конфиденциальности</span>
      </nav>

      <!-- Document Hero -->
      <div class="border-b border-paper-border pb-6 mb-8 space-y-3">
        <div class="flex items-center gap-2">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">152-ФЗ COMPLIANT</span>
          <span class="text-xs font-mono text-ink-faint">РЕВИЗИЯ: СЕНТЯБРЬ 2026</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">
          Политика конфиденциальности и положение о нулевом сборе персональных данных
        </h1>
        <p class="text-sm sm:text-base text-ink-muted leading-relaxed font-serif italic max-w-2xl">
          Сайт «ТОЧКА ПЛАВЛЕНИЯ» функционирует в режиме открытого образовательного справочника. Мы принципиально не собираем персональные данные пользователей.
        </p>
      </div>

      <!-- Legal Sections -->
      <article class="space-y-8 text-[14.5px] text-ink-muted leading-relaxed">

        <!-- Section 1 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">01.</span> Общие положения и статус ресурса
          </h2>
          <p>
            1.1. Настоящая Политика определяет порядок обработки информации на сайте <strong>«ТОЧКА ПЛАВЛЕНИЯ»</strong> (далее — «Сайт»).
          </p>
          <p>
            1.2. Сайт является исключительно <strong>информационно-справочным и образовательным ресурсом</strong>, посвященным технологиям монтажа РЭА, радиоэлектронике, свойствам сплавов и теплофизике пайки.
          </p>
          <p>
            1.3. В соответствии с Федеральным законом РФ № 152-ФЗ «О персональных данных», Сайт <strong>не осуществляет сбор, обработку, систематизацию или хранение персональных данных физических лиц</strong>.
          </p>
        </section>

        <!-- Section 2 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">02.</span> Отсутствие регистрации и форм сбора данных
          </h2>
          <p>
            2.1. На Сайте <strong>отключена регистрация пользователей</strong>, закрыты комментарии, отсутствуют формы подписки с вводом e-mail, формы обратной связи и любые иные интерфейсы ввода персональных данных (ФИО, телефоны, адреса).
          </p>
          <p>
            2.2. Сайт не требует авторизации и доступен всем посетителям на полностью анонимной основе.
          </p>
          <p>
            2.3. В связи с отсутствием сбора и обработки персональных данных, Сайт не является оператором персональных данных и освобожден от необходимости подачи уведомления в Роскомнадзор в порядке ст. 22 Федерального закона № 152-ФЗ.
          </p>
        </section>

        <!-- Section 3 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">03.</span> Использование файлов Cookie и локального хранилища
          </h2>
          <p>
            3.1. Для удобства взаимодействия Сайт использует исключительно технические файлы <code>Cookie</code> и локальное хранилище браузера (<code>localStorage</code>):
          </p>
          <ul class="list-disc list-inside space-y-1.5 pl-2 font-mono text-xs text-ink-muted">
            <li><code>tp_theme</code> — сохранение выбранной цветовой темы (светлая / тёмная);</li>
            <li><code>tp_cookie_consent</code> — сохранение факта ознакомления с уведомлением о cookie;</li>
            <li>Параметры состояния интерактивных калькуляторов в рамках текущей сессии браузера.</li>
          </ul>
          <p>
            3.2. Указанные данные хранятся исключительно на устройстве пользователя и не передаются на сторонние серверы.
          </p>
        </section>

        <!-- Section 4 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">04.</span> Обезличенная веб-аналитика (Яндекс Метрика)
          </h2>
          <p>
            4.1. Для подсчета общей посещаемости, анализа популярных разделов и оптимизации скорости загрузки страниц на Сайте может применяться сервис веб-аналитики <strong>«Яндекс Метрика»</strong> (ООО «Яндекс»).
          </p>
          <p>
            4.2. Сервис собирает исключительно <strong>обезличенные статистические сведения</strong> (количество просмотров, тип браузера, разрешение экрана, источник перехода) в агрегированном виде без идентификации конкретного пользователя.
          </p>
        </section>

        <!-- Section 5 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">05.</span> Внешние ссылки и партнерские материалы
          </h2>
          <p>
            5.1. Сайт может содержать гиперссылки на сторонние ресурсы (производители оборудования, маркетплейсы, технические стандарты IPC/ГОСТ). Сайт не несет ответственности за политику конфиденциальности сторонних ресурсов.
          </p>
        </section>

      </article>

      <div class="mt-10 pt-6 border-t border-paper-border flex flex-wrap items-center justify-between gap-4 text-xs font-mono text-ink-faint">
        <div>ТОЧКА ПЛАВЛЕНИЯ · 2026</div>
        <div class="flex items-center gap-4">
          <a class="text-ink hover:underline" href="terms.php">Пользовательское соглашение →</a>
          <a class="text-ink hover:underline" href="index.php">На главную →</a>
        </div>
      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

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
