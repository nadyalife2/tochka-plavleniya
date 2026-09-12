<?php
$page_title = "Интерактивный верстак инженера — Калькуляторы и справочники";
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/data/solder-reference.php';
require_once __DIR__ . '/data/interactive-rules.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <title><?= e($page_title) ?> — ТОЧКА ПЛАВЛЕНИЯ</title>
  <meta name="description" content="Интерактивный верстак инженера: термокалькулятор пайки, конфигуратор инструмента, дерево диагностики брака, расчет флюса и реестр сплавов."/>

  <!-- OpenGraph Meta -->
  <meta property="og:type" content="website"/>
  <meta property="og:title" content="<?= e($page_title) ?> — ТОЧКА ПЛАВЛЕНИЯ"/>
  <meta property="og:description" content="Интерактивный верстак инженера: термокалькулятор пайки, конфигуратор инструмента, дерево диагностики брака, расчет флюса и реестр сплавов."/>
  <meta property="og:site_name" content="ТОЧКА ПЛАВЛЕНИЯ"/>

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

  <!-- Preconnect for Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <!-- Self-Hosted Fonts & Compiled Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/build.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Common Design Tokens & Base Styles -->
  <link rel="stylesheet" href="assets/css/common.css">

  <style>
    /* Smooth Anchor Scrolling with Offset */
    html {
      scroll-behavior: smooth;
      scroll-padding-top: 5rem;
    }
    .hub-card {
      transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .hub-card:hover {
      transform: translateY(-2px);
      border-color: var(--color-accent) !important;
      box-shadow: 4px 6px 16px rgba(0,0,0,0.06);
    }
    .meter-bar {
      height: 8px;
      border-radius: 4px;
      background: var(--color-paper-border);
      overflow: hidden;
      position: relative;
    }
    .meter-fill {
      height: 100%;
      transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease;
    }
  </style>
</head>
<body class="min-h-screen flex flex-col">

  <!-- Header -->
  <header class="w-full border-b border-paper-border bg-paper/95 sticky top-0 z-40 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      <div class="flex items-center gap-6">
        <a class="logo" href="/">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        <!-- Desktop Nav -->
        <?php 
        $current_page = 'interactive';
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
          <span class="hidden sm:inline text-[11px] text-ink-muted dark:text-ink-faint font-mono">Тема</span>
        </button>

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="/category.php?slug=start" aria-label="Рубрика для новичков">
          <span>С чего начать</span>
          <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <main class="w-full flex-grow pt-6 pb-20">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-12">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint flex items-center gap-1.5">
        <a class="hover:text-ink transition-colors" href="/">Главная</a>
        <span>→</span>
        <span class="text-ink">Интерактивный верстак инженера</span>
      </nav>

      <!-- HERO / HUB ZONE -->
      <div class="border-b border-paper-border pb-8 space-y-4">
        <div class="flex items-center gap-2">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">LAB TOOLS v3.0</span>
          <span class="text-xs font-mono text-ink-faint">ИНЖЕНЕРНЫЙ ХАБ И КАЛЬКУЛЯТОРЫ</span>
        </div>
        <h1 class="text-2xl sm:text-4xl font-bold text-ink tracking-tight font-sans">
          Интерактивный верстак монтажника
        </h1>
        <p class="text-sm sm:text-base text-ink-muted max-w-2xl font-serif italic">
          Решение конкретных инженерных задач за 1 клик: подбор температуры жала станции, выбор паяльного оборудования, диагностика причин дефектов пайки и расчет химии.
        </p>

        <!-- Quick Jump Hub Grid -->
        <div class="pt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          
          <!-- P0-1: Температура -->
          <a href="#temp" class="hub-card block p-4 sm:p-5 rounded-lg border border-paper-border bg-card text-decoration-none">
            <div class="flex items-center justify-between gap-2 mb-2">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">🌡️ P0 // ТЕРМОРЕЖИМ</span>
              <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-paper-subtle border border-paper-border text-ink-muted">~30 сек</span>
            </div>
            <div class="text-base font-bold text-ink font-sans mb-1">Подбор температуры пайки</div>
            <p class="text-xs text-ink-muted font-mono leading-relaxed mb-3">
              Узнайте оптимальный диапазон °C для станции по марке припоя и типу монтажа (провода, SMD, THT).
            </p>
            <div class="text-xs font-mono font-bold text-accent">Открыть калькулятор →</div>
          </a>

          <!-- P0-2: Паяльник -->
          <a href="#iron" class="hub-card block p-4 sm:p-5 rounded-lg border border-paper-border bg-card text-decoration-none">
            <div class="flex items-center justify-between gap-2 mb-2">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">🔧 P0 // ВЫБОР ЖЕЛЕЗА</span>
              <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-paper-subtle border border-paper-border text-ink-muted">~1 мин</span>
            </div>
            <div class="text-base font-bold text-ink font-sans mb-1">Конфигуратор паяльника</div>
            <p class="text-xs text-ink-muted font-mono leading-relaxed mb-3">
              Подбор класса станции, картриджей (T12, C245, USB-PD) и комплекта под профиль работ и бюджет.
            </p>
            <div class="text-xs font-mono font-bold text-accent">Сконфигурировать →</div>
          </a>

          <!-- P0-3: Дефекты -->
          <a href="#defect" class="hub-card block p-4 sm:p-5 rounded-lg border border-paper-border bg-card text-decoration-none">
            <div class="flex items-center justify-between gap-2 mb-2">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">🔍 P0 // ДИАГНОСТИКА</span>
              <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-paper-subtle border border-paper-border text-ink-muted">~1 мин</span>
            </div>
            <div class="text-base font-bold text-ink font-sans mb-1">Дерево причин брака</div>
            <p class="text-xs text-ink-muted font-mono leading-relaxed mb-3">
              Припой скатывается шариком? Дорожка отслоилась? Интерактивный поиск первопричины и рецепт починки.
            </p>
            <div class="text-xs font-mono font-bold text-accent">Найти ошибку →</div>
          </a>

        </div>

        <!-- Secondary P1 shortcut pills -->
        <div class="pt-2 flex flex-wrap items-center gap-3">
          <span class="text-xs font-mono text-ink-faint">Также на верстаке:</span>
          <a href="#calculator" class="px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors">
            🧪 Калькулятор дозировки флюса (IPC-7095C)
          </a>
          <a href="#table" class="px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors">
            📋 Реестр точек плавления сплавов (ГОСТ 21931)
          </a>
        </div>
      </div>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 1: ТЕРМОКАЛЬКУЛЯТОР (#temp)                             -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="temp" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">01 // ТЕРМОРЕЖИМ ПАЙКИ</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">ГОСТ 21931-76 / IPC J-STD-006C</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1">Калькулятор рабочей температуры станции</h2>
          </div>
          <div class="text-xs font-mono text-ink-faint">
            Динамический расчёт теплового окна
          </div>
        </div>

        <div class="tool-two-col">
          
          <!-- Controls -->
          <div class="space-y-4">
            <div>
              <label for="temp-solder" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">1. Марка используемого припоя:</label>
              <select id="temp-solder" class="w-full p-2.5 rounded bg-paper border border-paper-border-dark text-ink font-mono text-xs focus:outline-none focus:border-accent">
                <option value="pos61" selected>ПОС-61 (Sn63Pb37) — эвтектика 183 °C (стандарт РЭА)</option>
                <option value="sac305">SAC305 (Sn96.5Ag3Cu0.5) — бессвинец RoHS 217–220 °C</option>
                <option value="pos40">ПОС-40 (Sn40Pb60) — кабельный 183–238 °C</option>
                <option value="sn42bi58">Sn42Bi58 — низкотемпературный 138 °C (термочувствительный)</option>
                <option value="roze">Сплав Розе (Bi50Pb25Sn25) — 94 °C (только демонтаж)</option>
              </select>
            </div>

            <div>
              <label for="temp-work" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">2. Тип детали и монтажной операции:</label>
              <select id="temp-work" class="w-full p-2.5 rounded bg-paper border border-paper-border-dark text-ink font-mono text-xs focus:outline-none focus:border-accent">
                <option value="smd_medium" selected>SMD 0603–1206 / Микросхемы SOP, QFP (стандарт)</option>
                <option value="smd_small">Мелкие SMD 0201–0402 (термочувствительные чипы)</option>
                <option value="smd_large">Массивные SMD: QFN, DFN, силовые транзисторы D-PAK</option>
                <option value="pth">Монтаж в металлизированные отверстия (THT / разъёмы)</option>
                <option value="wire_thin">Тонкие провода и перемычки (до 0.5 мм²)</option>
                <option value="wire_medium">Силовые монтажные провода (0.5 – 2.5 мм²)</option>
                <option value="wire_heavy">Тяжёлые шины питания / провода более 2.5 мм²</option>
                <option value="bga">BGA-реболлинг (пайка феном/станцией)</option>
                <option value="copper_pipe">Медные трубы и фитинги (горелка)</option>
              </select>
            </div>

            <!-- Temperature Meter Scale -->
            <div class="p-3.5 rounded border border-paper-border bg-paper-subtle space-y-2">
              <div class="flex items-center justify-between text-[11px] font-mono">
                <span class="text-ink-muted uppercase">Шкала температуры станции:</span>
                <span class="text-ink-faint">100°C ───── 320°C ───── 450°C</span>
              </div>
              <div class="meter-bar">
                <div id="temp-bar-fill" class="meter-fill" style="width: 55%; background-color: #eb5211;"></div>
              </div>
            </div>
          </div>

          <!-- Result Board -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle space-y-4">
            <div>
              <div class="text-[11px] font-mono uppercase text-ink-muted font-bold mb-1">Рекомендуемый диапазон жала:</div>
              <div id="temp-range-display" class="text-3xl font-bold font-mono text-accent leading-none">
                260 – 310 °C
              </div>
            </div>

            <div class="pt-3 border-t border-paper-border space-y-2">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[16px] text-accent">hardware</span>
                <span id="temp-tip-display" class="text-xs font-mono font-bold text-ink">Форма жала: скошенное или «ложка»</span>
              </div>
              <p id="temp-advice-display" class="text-xs font-mono text-ink-muted leading-relaxed">
                SOP/QFP: двигайтесь по выводам плавно. Флюс-гель помогает растечься припою между выводами без перемычек.
              </p>
            </div>

            <!-- Warning Callout -->
            <div id="temp-warn-box" class="p-3 rounded bg-amber-50 dark:bg-amber-950/30 border border-amber-300 dark:border-amber-800 flex items-start gap-2">
              <span class="text-amber-700 dark:text-amber-400 font-bold text-xs">⚠️</span>
              <span id="temp-warn-display" class="text-xs font-mono text-amber-800 dark:text-amber-300 leading-normal">
                Мостики? Сначала добавьте флюс, затем снимите оплёткой — не поднимайте температуру выше предела.
              </span>
            </div>
          </div>

        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 2: КОНФИГУРАТОР ПАЯЛЬНИКА (#iron)                      -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="iron" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">02 // КОНФИГУРАТОР ВЕРСТАКА</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">Спецификация оборудования</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1">Подбор паяльника и картриджей под задачи</h2>
          </div>
          <div class="text-xs font-mono text-ink-faint">
            От бытового ремонта до сервисного центра
          </div>
        </div>

        <div class="tool-two-col">
          
          <!-- Selectors -->
          <div class="space-y-4">
            <div>
              <label for="iron-task" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">Основной сценарий пайки:</label>
              <select id="iron-task" class="w-full p-2.5 rounded bg-paper border border-paper-border-dark text-ink font-mono text-xs focus:outline-none focus:border-accent">
                <option value="smd" selected>SMD компоненты (0603–1206, SOIC, базовый ремонт)</option>
                <option value="smd_fine">Прецизионный SMD (0402, QFP с мелким шагом, IC микросхемы)</option>
                <option value="boards_tht">Печатные платы, монтаж в отверстия (THT / разъёмы)</option>
                <option value="wire">Провода, кабели, электромонтаж и бытовая пайка</option>
                <option value="connectors">Силовые разъёмы (XT60/XT90), толстые клеммы и шины</option>
                <option value="bga_rework">BGA чипы и микросхемы под компаундом</option>
                <option value="pipes">Медные водопроводные трубы и холодильная техника</option>
              </select>
            </div>

            <div>
              <label for="iron-intensity" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">Интенсивность и бюджет:</label>
              <select id="iron-intensity" class="w-full p-2.5 rounded bg-paper border border-paper-border-dark text-ink font-mono text-xs focus:outline-none focus:border-accent">
                <option value="rare">Редкий ремонт (1–2 раза в месяц, бюджетный сегмент)</option>
                <option value="regular" selected>Регулярная мастерская / радиолюбитель (оптимум цена/качество)</option>
                <option value="daily">Ежедневная профессиональная загрузка (сервисный центр)</option>
              </select>
            </div>

            <!-- Avoid Alert -->
            <div id="iron-avoid-box" class="p-3 rounded bg-red-50 dark:bg-red-950/30 border border-red-300 dark:border-red-800 flex items-start gap-2" style="display:none;">
              <span class="text-red-600 font-bold text-xs">🛑</span>
              <div>
                <div class="text-[10px] font-mono uppercase font-bold text-red-700 dark:text-red-400">Чего избегать:</div>
                <div id="iron-avoid-display" class="text-xs font-mono text-red-800 dark:text-red-300"></div>
              </div>
            </div>
          </div>

          <!-- Recommendation Spec Card -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle space-y-4">
            <div>
              <span class="text-[10px] font-mono uppercase tracking-wider text-accent font-bold">Рекомендуемый класс станции:</span>
              <div id="iron-class-display" class="text-lg font-bold font-sans text-ink mt-0.5">
                Паяльная станция + термофен
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-paper-border text-xs font-mono">
              <div>
                <span class="text-ink-muted block text-[10px] uppercase">Термоконтроль:</span>
                <span id="iron-control-display" class="font-bold text-ink">Цифровой термостат</span>
              </div>
              <div>
                <span class="text-ink-muted block text-[10px] uppercase">Тип жал / картриджей:</span>
                <span id="iron-tip-display" class="font-bold text-ink">Конус 0.2–0.5 мм, скос 45°</span>
              </div>
            </div>

            <div class="pt-2 border-t border-paper-border space-y-2">
              <div class="text-[11px] font-mono uppercase font-bold text-ink">Обязательный минимум на верстаке:</div>
              <ul id="iron-must-display" class="space-y-1"></ul>
            </div>

            <div class="pt-2 border-t border-paper-border space-y-1">
              <div class="text-[11px] font-mono uppercase font-bold text-ink-muted">Рекомендуемые расширения:</div>
              <ul id="iron-nice-display" class="space-y-1"></ul>
            </div>

            <p id="iron-note-display" class="text-xs font-mono text-ink-faint italic pt-1"></p>
          </div>

        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 3: ДЕРЕВО ДИАГНОСТИКИ ДЕФЕКТОВ (#defect)                 -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="defect" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">03 // ДИАГНОСТИКА БРАКА</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">Дерево инженерных решений</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1">Интерактивный определитель дефектов монтажа</h2>
          </div>
          <div>
            <button type="button" id="defect-restart-btn" class="text-xs font-mono px-3 py-1 rounded border border-paper-border bg-paper hover:border-accent text-ink transition-colors cursor-pointer" style="display:none;">
              ↺ Начать сначала
            </button>
          </div>
        </div>

        <p class="text-xs sm:text-sm font-mono text-ink-muted">
          Выберите визуальный признак проблемы на плате или кабеле — алгоритм определит причину и выдаст пошаговую инструкцию по исправлению.
        </p>

        <!-- Dynamic Decision Tree Container -->
        <div id="defect-tree-container" class="min-h-[220px]">
          <!-- Rendered dynamically by workbench.js -->
        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 4: КАЛЬКУЛЯТОР ФЛЮСА (#calculator)                       -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="calculator" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 relative overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">04 // ДОЗИРОВКА ХИМИИ</span>
              <span class="handwriting text-accent font-bold text-base hidden sm:inline-block">✎ Толщина слоя: 50–70 мкм</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1">Калькулятор расхода флюса и паяльной пасты</h2>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-mono px-2.5 py-1 rounded bg-paper-subtle border border-paper-border text-ink-muted">IPC-7095C Standard</span>
          </div>
        </div>

        <!-- Presets bar -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-semibold text-ink-muted uppercase">Быстрые пресеты плат:</span>
            <span class="text-[11px] font-mono text-ink-faint">Кликните для авто-подстановки</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors" data-area="9">Arduino Nano (9 см²)</button>
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors" data-area="18">ESP32-WROOM (18 см²)</button>
            <button type="button" class="preset-btn active px-3 py-1.5 rounded text-xs font-mono border border-accent bg-paper font-bold text-accent transition-colors" data-area="35">GPU VRAM (35 см²)</button>
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors" data-area="120">ATX Motherboard (120 см²)</button>
          </div>
        </div>

        <!-- Workbench Grid: Controls + Sticky Note Result -->
        <div id="calc-workbench-grid" style="display:flex; flex-direction:column; gap:1.5rem;">
          <div style="display:grid; grid-template-columns:1fr; gap:1.5rem; align-items:start;">
            <style>
              @media(min-width:1024px){
                #calc-workbench-grid-inner { grid-template-columns: 58% 40% !important; display: grid !important; }
              }
            </style>
            <div id="calc-workbench-grid-inner" style="display:contents;">

              <!-- Controls column -->
              <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Area Control -->
                <div style="padding:1rem; border-radius:8px; background:var(--color-paper-subtle); border:1px solid var(--color-paper-border);">
                  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
                    <label for="flux-area-slider" style="font-size:11px; font-family:monospace; font-weight:700; text-transform:uppercase; color:var(--color-ink); letter-spacing:0.05em;">Площадь монтажной зоны:</label>
                    <div style="display:flex; align-items:center; gap:6px;">
                      <input type="number" id="flux-area" value="35" min="1" max="500" style="width:72px; padding:4px 8px; text-align:right; border-radius:6px; background:var(--color-paper); border:1px solid var(--color-paper-border-dark); color:var(--color-ink); font-family:monospace; font-size:14px; font-weight:700; outline:none;"/>
                      <span style="font-size:11px; font-family:monospace; font-weight:700; color:var(--color-ink-muted);">см²</span>
                    </div>
                  </div>
                  <input type="range" id="flux-area-slider" min="1" max="200" value="35" class="range-slider" style="width:100%; cursor:pointer; margin-bottom:0.5rem;"/>
                  <div style="display:flex; justify-content:space-between; font-size:10px; font-family:monospace; color:var(--color-ink-faint);">
                    <span>1 см²</span>
                    <span>50 см²</span>
                    <span>100 см²</span>
                    <span>200 см²</span>
                  </div>
                </div>

                <!-- Chemistry Selector -->
                <div>
                  <label for="flux-type" style="display:block; font-size:11px; font-family:monospace; font-weight:700; text-transform:uppercase; color:var(--color-ink); margin-bottom:8px; letter-spacing:0.05em;">Тип флюса / монтажа:</label>
                  <select id="flux-type" style="width:100%; padding:10px 14px; border-radius:6px; background:var(--color-paper); border:1px solid var(--color-paper-border-dark); color:var(--color-ink); font-family:monospace; font-size:13px; outline:none;">
                    <option value="bga_nc">Безотмывочный гель BGA (NC-559 / RMA-218) — 50-70 мкм</option>
                    <option value="smd_rma">Канифольный средней активности (RMA-223) — кисть/дозатор</option>
                    <option value="paste_sac">Паяльная паста SAC305 (Sn96.5Ag3Cu0.5) — трафарет 120 мкм</option>
                    <option value="clean_ws">Водосмывной высокой активности (WS) — для стойких оксидов</option>
                  </select>
                </div>

                <!-- Batch multiplier -->
                <div>
                  <label style="display:block; font-size:11px; font-family:monospace; font-weight:700; text-transform:uppercase; color:var(--color-ink); margin-bottom:8px; letter-spacing:0.05em;">Объём партии плат:</label>
                  <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <button type="button" class="batch-btn" data-qty="1" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; font-weight:700; border:1px solid var(--color-accent); background:var(--color-paper); color:var(--color-accent); cursor:pointer;">1 плата</button>
                    <button type="button" class="batch-btn" data-qty="5" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">5 плат</button>
                    <button type="button" class="batch-btn" data-qty="10" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">10 плат</button>
                    <button type="button" class="batch-btn" data-qty="50" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">50 плат (серия)</button>
                  </div>
                </div>
              </div>

              <!-- Sticky Note Engineer's Memo -->
              <div>
                <div id="engineer-memo" style="
                  position:relative;
                  padding:1.25rem 1.25rem 1.5rem;
                  background:#fef9c3;
                  transform:rotate(-1.2deg);
                  transition:transform 0.2s ease;
                  display:flex; flex-direction:column; gap:0.9rem;
                  box-shadow:3px 5px 18px rgba(0,0,0,0.13), -1px 1px 0 rgba(0,0,0,0.05);
                  border-radius:2px 2px 2px 2px;
                ">
                  <!-- SVG sketch outline -->
                  <svg aria-hidden="true" style="position:absolute;inset:0;width:100%;height:100%;pointer-events:none;overflow:visible;" preserveAspectRatio="none" viewBox="0 0 200 260">
                    <path d="M2 4 C 40 1.5, 140 3, 198 2.5 C 199.5 60, 199 140, 198.5 258 C 150 259.5, 55 258, 2.5 258.5 C 1.5 190, 1 80, 2 4 Z"
                          fill="#fef9c3" fill-opacity="0" stroke="#eab308" stroke-width="1.2"
                          stroke-dasharray="38 1.5 22 1" stroke-linecap="round"/>
                  </svg>
                  <!-- Folded corner triangle -->
                  <svg aria-hidden="true" style="position:absolute;bottom:0;right:0;width:22px;height:22px;pointer-events:none;" viewBox="0 0 22 22">
                    <path d="M22 22 L0 22 L22 0 Z" fill="#e5d68a" opacity="0.7"/>
                    <path d="M22 0 L0 22" stroke="#ca8a04" stroke-width="0.8" fill="none"/>
                  </svg>

                  <!-- Pin header -->
                  <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:9px; border-bottom:1px dashed rgba(0,0,0,0.15);">
                    <span class="handwriting" style="font-size:17px; font-weight:700; color:#78350f; letter-spacing:0.01em;">📌 Заметка инженера</span>
                    <span style="font-size:10px; font-family:monospace; font-weight:700; color:#92400e; opacity:0.8; text-transform:uppercase; letter-spacing:0.06em;">IPC-A-610</span>
                  </div>

                  <!-- Dosage highlight -->
                  <div>
                    <div style="font-size:10px; font-family:monospace; text-transform:uppercase; color:#92400e; margin-bottom:3px; opacity:0.75;">Рекомендуемая дозировка:</div>
                    <div style="display:flex; align-items:baseline; gap:8px;">
                      <span id="res-volume" class="handwriting" style="font-size:2.5rem; font-weight:700; color:var(--color-accent); line-height:1;">~0.18 мл</span>
                      <span id="batch-note" style="font-size:11px; font-family:monospace; color:#92400e; opacity:0.75;">(на 1 плату)</span>
                    </div>
                  </div>

                  <!-- Hand-drawn PCB sketch -->
                  <div style="display:flex; align-items:center; gap:10px;">
                    <svg id="pcb-svg" width="80" height="80" viewBox="0 0 80 80" fill="none"
                         stroke="#78350f" stroke-linecap="round" stroke-linejoin="round"
                         style="flex-shrink:0; transition:width 0.2s,height 0.2s; display:block; opacity:0.75;">
                      <rect x="5" y="5" width="70" height="70" rx="3" stroke-width="1.4" stroke-dasharray="2.5 1.5" fill="none"/>
                      <circle cx="12" cy="12" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="68" cy="12" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="12" cy="68" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="68" cy="68" r="2.2" stroke-width="1.1" fill="none"/>
                      <rect x="26" y="26" width="28" height="28" rx="2" stroke-width="1.4" stroke-dasharray="3 1.5" fill="none"/>
                      <circle cx="33" cy="33" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="40" cy="33" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="47" cy="33" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="33" cy="40" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="40" cy="40" r="1.8" fill="#78350f" stroke="none" opacity="0.8"/>
                      <circle cx="47" cy="40" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="33" cy="47" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="40" cy="47" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <circle cx="47" cy="47" r="1.3" fill="#78350f" stroke="none" opacity="0.55"/>
                      <line x1="5" y1="33" x2="26" y2="33" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="5" y1="40" x2="26" y2="40" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="5" y1="47" x2="26" y2="47" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="54" y1="33" x2="75" y2="33" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="54" y1="40" x2="75" y2="40" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="54" y1="47" x2="75" y2="47" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="33" y1="5" x2="33" y2="26" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="47" y1="5" x2="47" y2="26" stroke-width="0.9" stroke-dasharray="1.5 1.2"/>
                      <line x1="5" y1="77" x2="75" y2="77" stroke-width="0.7" stroke-dasharray="1 1" opacity="0.5"/>
                      <line x1="5" y1="74" x2="5" y2="80" stroke-width="0.7" opacity="0.5"/>
                      <line x1="75" y1="74" x2="75" y2="80" stroke-width="0.7" opacity="0.5"/>
                    </svg>
                    <div>
                      <div style="font-size:10px; font-family:monospace; text-transform:uppercase; font-weight:700; color:#78350f; opacity:0.7; margin-bottom:2px;">Зарисовка платы:</div>
                      <div id="pcb-dimensions" style="font-size:11px; font-family:monospace; font-weight:700; color:#78350f;">59 × 59 мм (35 см²)</div>
                      <div style="font-size:10px; font-family:monospace; color:#78350f; opacity:0.6; margin-top:2px;">↑ масштаб к площади</div>
                    </div>
                  </div>

                  <!-- Process description -->
                  <p id="res-desc" style="font-size:12px; font-family:monospace; color:#78350f; line-height:1.55; margin:0; opacity:0.85;">
                    Для 35 см² при BGA реболлинге наносите тонкий слой 50-70 мкм. Избыток вызывает кипение и сдвиг чипа.
                  </p>

                  <!-- Wash tip -->
                  <div style="font-size:11px; font-family:monospace; font-weight:600; color:#78350f; padding-top:8px; border-top:1px dashed rgba(0,0,0,0.15); display:flex; align-items:center; gap:6px; opacity:0.85;">
                    <span style="color:var(--color-accent);">ℹ</span>
                    <span id="wash-tip">Отмывка: опциональна (No-Clean)</span>
                  </div>

                  <button type="button" id="copy-flux-btn" style="padding:6px 10px; border-radius:4px; border:1px solid #ca8a04; background:#fef08a; color:#78350f; font-family:monospace; font-size:11px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.15s;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    <span id="copy-flux-text">Скопировать параметры в журнал</span>
                  </button>
                </div>
              </div>

            </div><!-- /#calc-workbench-grid-inner -->
          </div>
        </div>

        <!-- Bottom: Chemical Comparison Matrix -->
        <div class="pt-4 border-t border-paper-border space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-bold uppercase text-ink">Сравнение расхода по типам химии для текущей зоны (<span id="matrix-area" class="text-accent">35 см²</span>):</span>
            <span class="text-[11px] font-mono text-ink-faint">Мгновенный пересчёт</span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-[10px] font-mono text-ink-muted uppercase">BGA Гель (NC-559)</div>
              <div id="matrix-bga" class="text-sm font-mono font-bold text-ink">0.18 мл</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-[10px] font-mono text-ink-muted uppercase">RMA-223 Канифоль</div>
              <div id="matrix-rma" class="text-sm font-mono font-bold text-ink">0.28 мл</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-[10px] font-mono text-ink-muted uppercase">Паста SAC305 (120 мкм)</div>
              <div id="matrix-paste" class="text-sm font-mono font-bold text-ink">0.53 г</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-[10px] font-mono text-ink-muted uppercase">Водосмывной (WS)</div>
              <div id="matrix-ws" class="text-sm font-mono font-bold text-ink">0.21 мл</div>
            </div>
          </div>
        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 5: РЕЕСТР СПЛАВОВ И ПРИПОЕВ (#table)                      -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="table" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">05 // СПРАВОЧНИК МЕТАЛЛОВ</span>
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
              <?php foreach ($SOLDER_DATA as $solder): ?>
                <tr class="hover:bg-paper-subtle/50 transition-colors">
                  <td class="py-2.5 px-3 font-bold text-ink">
                    <?= e($solder['name']) ?>
                    <?php if ($solder['type'] === 'leadfree'): ?>
                      <span class="ml-1 px-1 py-0.2 rounded bg-green-100 dark:bg-green-950/40 text-green-800 dark:text-green-300 text-[10px]">RoHS</span>
                    <?php elseif ($solder['type'] === 'lowtemp'): ?>
                      <span class="ml-1 px-1 py-0.2 rounded bg-blue-100 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300 text-[10px]">Низкотемп.</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <?php
                    $comps = [];
                    if ($solder['sn_pct'] > 0) $comps[] = $solder['sn_pct'] . '% Sn';
                    if ($solder['pb_pct'] > 0) $comps[] = $solder['pb_pct'] . '% Pb';
                    if ($solder['ag_pct'] > 0) $comps[] = $solder['ag_pct'] . '% Ag';
                    if ($solder['bi_pct'] > 0) $comps[] = $solder['bi_pct'] . '% Bi';
                    if ($solder['cu_pct'] > 0) $comps[] = $solder['cu_pct'] . '% Cu';
                    if (!empty($solder['other'])) $comps[] = $solder['other'];
                    echo implode(', ', $comps);
                    ?>
                  </td>
                  <td class="py-2.5 px-3 font-bold text-ink">
                    <?php if ($solder['t_melt_min'] === $solder['t_melt_max']): ?>
                      <?= $solder['t_melt_min'] ?> °C
                    <?php else: ?>
                      <?= $solder['t_melt_min'] ?> – <?= $solder['t_melt_max'] ?> °C
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px]">
                      <?= e($solder['std']) ?>
                    </span>
                  </td>
                  <td class="py-2.5 px-3">
                    <?= e(implode(', ', array_slice($solder['uses'], 0, 2))) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Pass Rules to Client-Side Workbench Engine -->
  <script>
    window.TCHP_RULES = {
      temp: <?= json_encode($TEMP_RULES, JSON_UNESCAPED_UNICODE) ?>,
      iron: <?= json_encode($IRON_RULES, JSON_UNESCAPED_UNICODE) ?>,
      defect: <?= json_encode($DEFECT_TREE, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>

  <!-- Scripts -->
  <script src="assets/js/workbench.js"></script>
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
