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

  <!-- OpenGraph Meta -->
  <meta property="og:type" content="website"/>
  <meta property="og:title" content="<?= e($page_title) ?> — ТОЧКА ПЛАВЛЕНИЯ"/>
  <meta property="og:description" content="Интерактивный верстак инженера: калькулятор расхода флюса, квиз по дефектам монтажа и интерактивная таблица припоев."/>
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

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="index.php" aria-label="Перейти в журнал статей">
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

        <!-- 01: Калькулятор флюса (Верстак инженера) -->
        <section id="calculator" class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 relative overflow-hidden">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
            <div>
              <div class="flex items-center gap-3">
                <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">01 // ДОЗИРОВКА ХИМИИ</span>
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
          <div style="display:grid; grid-template-columns:1fr; gap:1.5rem; align-items:start;">
            <style>
              @media(min-width:1024px){
                #calc-workbench-grid { grid-template-columns: 58% 40% !important; }
              }
            </style>
            <div id="calc-workbench-grid" style="display:contents;">

            <!-- Controls column -->
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
              <!-- Area Control (Slider + Number) -->
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
              <div class="postit-yellow" id="engineer-memo" style="padding:1.25rem; border-radius:10px; border:1px solid var(--color-paper-border); box-shadow:3px 4px 16px rgba(0,0,0,0.12); transform:rotate(-1deg); transition:transform 0.2s ease; display:flex; flex-direction:column; gap:1rem;">
                <!-- Pin header -->
                <div style="display:flex; align-items:center; justify-content:space-between; padding-bottom:10px; border-bottom:1px solid rgba(0,0,0,0.1);">
                  <span style="font-size:11px; font-family:monospace; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:var(--color-ink);">📌 ЗАМЕТКА ИНЖЕНЕРА</span>
                  <span class="handwriting" style="font-size:15px; color:var(--color-accent); font-weight:700;">IPC-A-610 Class 3</span>
                </div>

                <!-- Dosage highlight -->
                <div>
                  <div style="font-size:10px; font-family:monospace; text-transform:uppercase; color:var(--color-ink-muted); margin-bottom:4px;">Рекомендуемая дозировка:</div>
                  <div style="display:flex; align-items:baseline; gap:8px;">
                    <span id="res-volume" style="font-size:2.25rem; font-family:monospace; font-weight:900; color:var(--color-accent); line-height:1;">~0.18 мл</span>
                    <span id="batch-note" style="font-size:11px; font-family:monospace; color:var(--color-ink-muted);">(на 1 плату)</span>
                  </div>
                </div>

                <!-- Live PCB visualizer -->
                <div style="padding:10px 12px; background:rgba(0,0,0,0.04); border-radius:6px; border:1px dashed var(--color-paper-border-dark); display:flex; align-items:center; justify-content:space-between; gap:1rem;">
                  <div>
                    <div style="font-size:10px; font-family:monospace; text-transform:uppercase; font-weight:700; color:var(--color-ink-muted); margin-bottom:2px;">Визуализация платы:</div>
                    <div id="pcb-dimensions" style="font-size:11px; font-family:monospace; font-weight:600; color:var(--color-ink);">59 × 59 мм (35 см²)</div>
                    <div style="font-size:10px; font-family:monospace; color:var(--color-ink-faint); margin-top:2px;">Масштаб пропорционален площади</div>
                  </div>
                  <div style="width:72px; height:72px; display:flex; align-items:center; justify-content:center; background:var(--color-paper); border:1px dashed var(--color-paper-border-dark); border-radius:6px; flex-shrink:0;">
                    <svg id="pcb-svg" width="56" height="56" viewBox="0 0 100 100" style="transition:width 0.2s,height 0.2s; display:block;">
                      <rect x="5" y="5" width="90" height="90" rx="6" fill="#1b4d2c" stroke="#2e7d43" stroke-width="3"/>
                      <circle cx="15" cy="15" r="4" fill="#d4af37"/>
                      <circle cx="85" cy="15" r="4" fill="#d4af37"/>
                      <circle cx="15" cy="85" r="4" fill="#d4af37"/>
                      <circle cx="85" cy="85" r="4" fill="#d4af37"/>
                      <rect x="35" y="35" width="30" height="30" rx="3" fill="#2d3748" stroke="#cbd5e1" stroke-width="1.5"/>
                      <path d="M 25 35 L 35 35 M 25 50 L 35 50 M 25 65 L 35 65 M 65 35 L 75 35 M 65 50 L 75 50 M 65 65 L 75 65" stroke="#d4af37" stroke-width="1.5"/>
                    </svg>
                  </div>
                </div>

                <!-- Process description -->
                <p id="res-desc" style="font-size:12px; font-family:monospace; color:var(--color-ink-muted); line-height:1.55; margin:0;">
                  Для 35 см² при BGA реболлинге наносите тонкий слой 50-70 мкм. Избыток вызывает кипение и сдвиг чипа.
                </p>

                <!-- Wash tip -->
                <div style="font-size:11px; font-family:monospace; font-weight:600; color:var(--color-ink); padding-top:8px; border-top:1px solid rgba(0,0,0,0.1); display:flex; align-items:center; gap:6px;">
                  <span style="color:var(--color-accent);">ℹ</span>
                  <span id="wash-tip">Отмывка: опциональна (No-Clean)</span>
                </div>

                <!-- Copy button -->
                <button type="button" id="copy-flux-btn" style="width:100%; padding:8px 12px; border-radius:6px; background:var(--color-ink); color:var(--color-paper); font-family:monospace; font-size:12px; font-weight:700; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity 0.15s;">
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                  </svg>
                  <span id="copy-flux-text">Скопировать параметры в журнал</span>
                </button>
              </div>
            </div>

            </div><!-- /#calc-workbench-grid -->
          </div>
          <script>
            (function(){
              var g = document.getElementById('calc-workbench-grid');
              var memo = document.getElementById('engineer-memo');
              function applyGrid(){
                if(window.innerWidth >= 1024){
                  g.style.display='grid';
                  g.style.gridTemplateColumns='58% 40%';
                  g.style.gap='1.5rem';
                  g.style.alignItems='start';
                } else {
                  g.style.display='flex';
                  g.style.flexDirection='column';
                  g.style.gap='1.25rem';
                }
              }
              applyGrid();
              window.addEventListener('resize', applyGrid);
              if(memo){
                memo.addEventListener('mouseenter', function(){ this.style.transform='rotate(0deg)'; });
                memo.addEventListener('mouseleave', function(){ this.style.transform='rotate(-1deg)'; });
              }
            })();
          </script>

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
