<?php
$page_title = "Интерактивный верстак — Калькуляторы и реестр сплавов";
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title><?= e($page_title) ?> — ТОЧКА ПЛАВЛЕНИЯ</title>
  <meta name="description" content="Интерактивный верстак инженера: калькулятор расхода флюса, квиз по дефектам монтажа и интерактивная таблица припоев."/>

  <!-- Immediate Theme Init Script (Zero FOUC) -->
  <script>
    (function() {
      const saved = localStorage.getItem('tp_theme');
      const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (saved === 'dark' || (!saved && prefersDark)) {
        document.documentElement.classList.add('dark');
      } else {
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
        $current_page = 'interactive';
        include __DIR__ . '/includes/header-nav.php'; 
        ?>
      </div>

      <div class="flex items-center gap-3">
        <a class="hidden sm:inline-block text-[12.5px] text-ink-muted hover:text-ink transition-colors font-mono" href="#calculator">
          [↓ к расчёту]
        </a>

        <!-- Theme Toggle (Sketch Style) -->
        <button id="theme-toggle" type="button" class="sketch-pill-gray hover:border-ink/50 text-ink font-mono text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:translate-y-0.5" title="Сменить тему (Светлая / Тёмная)" aria-label="Сменить тему">
          <svg class="w-3.5 h-3.5 dark:hidden stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
          </svg>
          <svg class="w-3.5 h-3.5 hidden dark:inline stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="4.5"></circle>
            <path d="M12 2.5v1.8M12 19.7v1.8M4.93 4.93l1.3 1.3M17.77 17.77l1.3 1.3M2.5 12h1.8M19.7 12h1.8M6.23 17.77l-1.3 1.3M19.07 4.93l-1.3 1.3"></path>
          </svg>
          <span class="hidden sm:inline text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="index.php">
          <span>Журнал статей</span>
          <span class="material-symbols-outlined text-[13px]">menu_book</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <main class="w-full flex-grow pt-8 pb-20">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5">
        <a class="hover:text-ink transition-colors" href="index.php">Главная</a>
        <span>→</span>
        <span class="text-ink">Интерактивный верстак инженера</span>
      </nav>

      <!-- Hero Header -->
      <div class="border-b border-paper-border pb-8 mb-10 space-y-3">
        <div class="flex items-center gap-2">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">LAB TOOLS v2.4</span>
          <span class="text-xs font-mono text-ink-faint">ИНЖЕНЕРНЫЙ РАСЧЕТ И СПРАВОЧНИКИ</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight font-sans">
          Интерактивный верстак инженера-электронщика
        </h1>
        <p class="text-sm sm:text-base text-ink-muted max-w-2xl font-serif italic">
          Быстрые инженерные расчеты расхода флюса, интерактивный поиск сплавов по ликвидусу и диагностика причин брака пайки.
        </p>
      </div>

      <!-- Grid of Tools -->
      <div class="space-y-12">

        <!-- 01: Калькулятор флюса -->
        <section id="calculator" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 relative">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-paper-border">
            <div>
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">01 // ДОЗИРОВКА ХИМИИ</span>
              <h2 class="text-xl font-bold text-ink mt-0.5">Калькулятор расхода флюса и паяльной пасты</h2>
            </div>
            <span class="text-xs font-mono text-ink-faint">IPC-7095C Standard</span>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
              <label class="block text-xs font-mono font-semibold text-ink uppercase mb-2">Площадь платы (см²):</label>
              <input type="number" id="flux-area" value="35" min="1" max="500" class="w-full px-3.5 py-2 rounded bg-paper border border-paper-border-dark text-ink font-mono text-sm focus:outline-none focus:border-ink"/>
              <span class="text-[11px] text-ink-faint font-mono mt-1 block">Пример: 35 см² (плата видеокарты / роутера)</span>
            </div>

            <div>
              <label class="block text-xs font-mono font-semibold text-ink uppercase mb-2">Тип флюса / монтажа:</label>
              <select id="flux-type" class="w-full px-3.5 py-2 rounded bg-paper border border-paper-border-dark text-ink font-mono text-sm focus:outline-none focus:border-ink">
                <option value="bga_nc">Безотмывочный гель BGA (NC-559 / RMA-218)</option>
                <option value="smd_rma">Канифольный средней активности (RMA-223)</option>
                <option value="paste_sac">Паяльная паста SAC305 (Sn96.5Ag3Cu0.5)</option>
                <option value="clean_ws">Водосмывной флюс высокой активности (WS)</option>
              </select>
              <span class="text-[11px] text-ink-faint font-mono mt-1 block">Выбор определяет удельную плотность слоя</span>
            </div>

            <div class="flex items-end">
              <button id="calc-flux-btn" type="button" class="w-full px-4 py-2.5 bg-ink text-paper font-mono font-semibold text-xs rounded hover:opacity-90 transition-opacity border border-paper-border-dark flex items-center justify-center gap-1.5">
                <span>Рассчитать дозировку</span>
                <span class="text-[11px]">→</span>
              </button>
            </div>
          </div>

          <!-- Result output -->
          <div id="flux-result" class="p-4 rounded-lg bg-paper-subtle border border-paper-border font-mono text-xs space-y-2">
            <div class="text-ink font-bold flex items-center justify-between">
              <span>РЕЗУЛЬТАТ РАСЧЕТА ДОЗИРОВКИ:</span>
              <span class="text-accent font-bold" id="res-volume">~0.18 мл</span>
            </div>
            <p class="text-ink-muted leading-relaxed" id="res-desc">
              Для платы 35 см² при реболлинге BGA рекомендуется наносить тонкий слой толщиной 50-70 мкм. Избыток флюса вызывает вскипание и смещение чипа.
            </p>
          </div>
        </section>

        <!-- 02: Таблица сплавов -->
        <section id="table" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
            <div>
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">02 // СПРАВОЧНИК МЕТАЛЛОВ</span>
              <h2 class="text-xl font-bold text-ink mt-0.5">Реестр сплавов и температур плавления</h2>
            </div>
            <div class="w-full sm:w-64">
              <input type="text" id="solder-search" placeholder="Поиск (ПОС-61, SAC305, 183°C)..." class="w-full px-3 py-1.5 rounded bg-paper border border-paper-border-dark text-ink font-mono text-xs focus:outline-none focus:border-ink"/>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table id="solder-table" class="w-full text-left font-mono text-xs border-collapse">
              <thead>
                <tr class="border-b border-paper-border bg-paper-subtle text-ink">
                  <th class="py-2.5 px-3">Марка сплава</th>
                  <th class="py-2.5 px-3">Химический состав</th>
                  <th class="py-2.5 px-3">T° солидус / ликвидус</th>
                  <th class="py-2.5 px-3">Стандарт</th>
                  <th class="py-2.5 px-3">Применение</th>
                </tr>
              </thead>
              <tbody id="solder-tbody" class="divide-y divide-paper-border text-ink-muted">
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">ПОС-61 (Эвтектика)</td>
                  <td class="py-2.5 px-3">61% Sn, 39% Pb</td>
                  <td class="py-2.5 px-3 font-bold text-ink">183°C</td>
                  <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px]">ГОСТ 21931</span></td>
                  <td class="py-2.5 px-3">Ремонт РЭА, монтаж THT и SMD компонентов</td>
                </tr>
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">SAC305 (Бессвинец)</td>
                  <td class="py-2.5 px-3">96.5% Sn, 3.0% Ag, 0.5% Cu</td>
                  <td class="py-2.5 px-3 font-bold text-ink">217°C – 220°C</td>
                  <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px] text-green-700 dark:text-green-400">RoHS</span></td>
                  <td class="py-2.5 px-3">Заводской монтаж BGA, материнские платы, смартфоны</td>
                </tr>
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">Sn42Bi58 (Низкотемп.)</td>
                  <td class="py-2.5 px-3">42% Sn, 58% Bi</td>
                  <td class="py-2.5 px-3 font-bold text-ink">138°C</td>
                  <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px] text-green-700 dark:text-green-400">RoHS</span></td>
                  <td class="py-2.5 px-3">Пайка термочувствительных LED-матриц и пластиковых разъемов</td>
                </tr>
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">Сплав Розе</td>
                  <td class="py-2.5 px-3">50% Bi, 25% Pb, 25% Sn</td>
                  <td class="py-2.5 px-3 font-bold text-ink">94°C</td>
                  <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px]">ГОСТ 21931</span></td>
                  <td class="py-2.5 px-3">Только демонтаж разъемов и лужение плат в кипятке</td>
                </tr>
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">Сплав Вуда</td>
                  <td class="py-2.5 px-3">50% Bi, 25% Pb, 12.5% Sn, 12.5% Cd</td>
                  <td class="py-2.5 px-3 font-bold text-ink">68°C – 72°C</td>
                  <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px] text-red-600">Toxic (Cd)</span></td>
                  <td class="py-2.5 px-3">Специальный прецизионный демонтаж в вытяжном шкафу</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Scripts -->
  <script src="assets/js/flux-calc.js"></script>
  <script src="assets/js/solder-table.js"></script>
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
