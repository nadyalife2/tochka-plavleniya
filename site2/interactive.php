<?php
$page_title = "Интерактивный верстак инженера — Калькуляторы и справочники";
$page_desc = "Интерактивный верстак инженера: термокалькулятор пайки, конфигуратор инструмента, дерево диагностики брака, расчет флюса и реестр сплавов.";
$current_page = 'interactive';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/data/solder-reference.php';
require_once __DIR__ . '/data/interactive-rules.php';

ob_start();
$extra_head = '<link rel="stylesheet" href="/assets/css/workbench.css">';
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Container -->
  <main class="interactive w-full flex-grow pt-8 pb-16">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-10">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5 flex-wrap" aria-label="Хлебные крошки">
        <a class="hover:text-ink transition-colors" href="/">Главная</a>
        <span>→</span>
        <span class="text-ink font-semibold">Интерактивный верстак инженера</span>
      </nav>

      <!-- HERO / HUB ZONE -->
      <section class="border-b border-paper-border pb-10 space-y-5">
        <div class="max-w-3xl space-y-4">
          
          <!-- Tape & Label Badge -->
          <div class="relative inline-block mb-1">
            <div class="absolute -top-2 left-6 w-10 h-3 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-sm rotate-[-2deg] z-10 pointer-events-none"></div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-[#fffdf5] dark:bg-[#ca8a04]/15 border border-[#fde047] dark:border-[#ca8a04]/40 text-ink font-mono text-[11px] shadow-sm rotate-[-1.2deg] sketch-border">
              <span class="w-2 h-2 rounded-full bg-accent inline-block shadow-[0_0_6px_rgba(235,82,17,0.8)]"></span>
              <span class="font-bold tracking-wider uppercase">ВЕРСТАК // LAB TOOLS v3.0</span>
              <span class="text-ink-faint">|</span>
              <span class="text-xs text-ink-muted">7 КАЛЬКУЛЯТОРОВ И СПРАВОЧНИКОВ</span>
            </div>
          </div>

          <!-- Metadata -->
          <div class="flex items-center gap-2 font-mono text-xs text-ink-muted">
            <span class="w-2 h-2 rounded-full bg-ink inline-block"></span>
            <span class="uppercase tracking-wider font-semibold text-ink">Инженерный верстак v2.4</span>
            <span>·</span>
            <span>Расчеты по ГОСТ 21931 и IPC J-STD-006</span>
          </div>

          <!-- Headline -->
          <h1 class="text-3xl sm:text-4xl lg:text-[46px] font-bold text-ink tracking-tight leading-[1.15] font-serif">
            Интерактивный верстак инженера
          </h1>

          <!-- Lead text -->
          <p class="text-base sm:text-lg text-ink/80 font-serif leading-relaxed">
            Решение конкретных монтажных задач за 1 клик: расчет безопасного терморежима станции, подбор паяльного оборудования под бюджет, интерактивная диагностика причин брака и нормы расхода химии.
          </p>

          <!-- Editorial Manifesto Note (Sketch Style) -->
          <div class="font-hand text-lg sm:text-xl text-ink-muted/80 italic rotate-[-1deg] inline-flex items-center gap-2 pt-0.5 pb-1">
            <span>«Точный расчёт режима спасает медь от расслоения»</span>
          </div>
        </div>

        <!-- Инженерный блок верстака в чертёжном стиле (FIG. 1.1 - 3.1) -->
        <div class="workbench-section mt-4">
          <div class="workbench-container">
            <div class="cards-grid">

              <!-- Карточка 1: Терморежим -->
              <div class="tech-card">
                <div class="card-header">
                  <div class="card-title-group">
                    <span class="icon-thermo">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 12.5V3.5a1.8 1.8 0 0 1 3.6 0v9a3.6 3.6 0 1 1-3.6 0z"/>
                        <path d="M10.8 8.5v3.5" stroke-width="2"/>
                        <circle cx="10.8" cy="15.5" r="1.8" fill="currentColor" stroke="none"/>
                        <line x1="12.6" y1="5" x2="15.5" y2="5" stroke-width="1.2"/>
                        <line x1="12.6" y1="7.5" x2="14.5" y2="7.5" stroke-width="1"/>
                        <line x1="12.6" y1="10" x2="15.5" y2="10" stroke-width="1.2"/>
                      </svg>
                    </span>
                    <span class="card-title">Терморежим</span>
                  </div>
                  <span class="fig-badge">FIG. 1.1</span>
                </div>

                <div class="card-diagram">
                  <!-- Векторная схема в разрезе -->
                  <img src="assets/schematics/tip-cutaway.svg" alt="Схема жала в разрезе" width="320" height="170" loading="lazy" />
                </div>

                <!-- Мини-селекторы прямо в карточке -->
                <div class="card-quick-inputs">
                  <div class="input-row">
                    <label for="quick-temp-work">Тип монтажа</label>
                    <select id="quick-temp-work" aria-label="Быстрый выбор типа монтажа">
                      <option value="smd_medium" selected>SMD 0603–1206</option>
                      <option value="smd_small">Прецизионный SMD</option>
                      <option value="pth">Выводной THT</option>
                      <option value="wire_medium">Провода / Разъемы</option>
                      <option value="smd_large">Полигоны / Медь</option>
                    </select>
                  </div>
                  <div class="input-row">
                    <label for="quick-temp-solder">Припой</label>
                    <select id="quick-temp-solder" aria-label="Быстрый выбор марки припоя">
                      <option value="sac305" selected>SAC305 (217–220 °C)</option>
                      <option value="pos61">ПОС-61 (183–190 °C)</option>
                      <option value="sn63pb37">Sn63Pb37 (183 °C)</option>
                      <option value="sn42bi58">Sn42Bi58 (138 °C)</option>
                    </select>
                  </div>
                </div>

                <div class="card-info">
                  <h3>ПОДБОР ТЕМПЕРАТУРЫ ПАЙКИ</h3>
                  <p>Узнайте оптимальный диапазон °C для станции по марке припоя и типу монтажа.</p>
                </div>

                <div class="card-footer">
                  <span class="spec-tag">J-STD-020E</span>
                  <a href="#temp" class="card-btn">Открыть калькулятор →</a>
                </div>
              </div>

              <!-- Карточка 2: Выбор железа -->
              <div class="tech-card">
                <div class="card-header">
                  <div class="card-title-group">
                    <span class="icon-tools">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    </span>
                    <span class="card-title">Выбор железа</span>
                  </div>
                  <span class="fig-badge">FIG. 2.1</span>
                </div>

                <div class="card-diagram">
                  <img src="assets/schematics/cartridges-compare.svg" alt="Сравнение картриджей T12 и C245" width="320" height="170" loading="lazy" />
                </div>

                <!-- Быстрые параметры картриджей -->
                <div class="card-quick-inputs">
                  <div class="input-row">
                    <span class="row-label">Картриджи:</span>
                    <span class="font-mono text-[11px] font-bold text-ink">T12 / C245 / C210</span>
                  </div>
                  <div class="input-row">
                    <span class="row-label">Нагрев:</span>
                    <span class="font-mono text-[11px] font-bold text-ink">2–8 сек (PID-контроль)</span>
                  </div>
                </div>

                <div class="card-info">
                  <h3>ВЫБОР ЖЕЛЕЗА И КАРТРИДЖЕЙ</h3>
                  <p>Подбор класса станции, картриджей и комплекта под профиль работ и бюджет.</p>
                </div>

                <div class="card-footer">
                  <span class="spec-tag">ГОСТ 21931</span>
                  <a href="#iron" class="card-btn">Сконфигурировать →</a>
                </div>
              </div>

              <!-- Карточка 3: Диагностика -->
              <div class="tech-card">
                <div class="card-header">
                  <div class="card-title-group">
                    <span class="icon-search">
                      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    </span>
                    <span class="card-title">Диагностика</span>
                  </div>
                  <span class="fig-badge">FIG. 3.1</span>
                </div>

                <div class="card-diagram">
                  <img src="assets/schematics/bga-defect-crosssection.svg" alt="Срез BGA-пайки и дефекты" width="320" height="170" loading="lazy" />
                </div>

                <!-- Быстрые параметры диагностики -->
                <div class="card-quick-inputs">
                  <div class="input-row">
                    <span class="row-label">Дефекты:</span>
                    <span class="font-mono text-[11px] font-bold text-ink">Пустоты, мостики, трещины</span>
                  </div>
                  <div class="input-row">
                    <span class="row-label">Стандарт:</span>
                    <span class="font-mono text-[11px] font-bold text-ink">Class 2 &amp; 3 (IPC-A-610)</span>
                  </div>
                </div>

                <div class="card-info">
                  <h3>ДИАГНОСТИКА БРАКА</h3>
                  <p>Припой скатывается шариком? Дорожка отслоилась? Найдем первопричину.</p>
                </div>

                <div class="card-footer">
                  <span class="spec-tag">IPC-A-610</span>
                  <a href="#defect" class="card-btn">Найти ошибку →</a>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Secondary P1 shortcut pills -->
        <div class="pt-2 flex flex-wrap items-center gap-2.5">
          <span class="text-xs font-mono text-ink-faint">Также на верстаке:</span>
          <a href="#flux-selector" class="inline-flex items-center gap-1.5 px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs">
            <svg class="w-3.5 h-3.5 text-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Селектор флюса</span>
          </a>
          <a href="#solder-consumption" class="inline-flex items-center gap-1.5 px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs">
            <svg class="w-3.5 h-3.5 text-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></svg>
            <span>Расход припоя</span>
          </a>
          <a href="#calculator" class="inline-flex items-center gap-1.5 px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs">
            <svg class="w-3.5 h-3.5 text-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/></svg>
            <span>Дозировка пасты</span>
          </a>
          <a href="#table" class="inline-flex items-center gap-1.5 px-3 py-1 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs">
            <svg class="w-3.5 h-3.5 text-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M3 15h18"/><path d="M12 3v18"/></svg>
            <span>Сплавы (ГОСТ)</span>
          </a>
        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 1: ТЕРМОКАЛЬКУЛЯТОР (#temp)                             -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="temp" class="card-tool space-y-6 relative bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <div class="absolute -top-2.5 left-8 w-12 h-3.5 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-2xs rotate-[-2.5deg] pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="sketch-pill-yellow text-ink font-mono text-[11px] font-bold">01 // ТЕРМОРЕЖИМ ПАЙКИ</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">ГОСТ 21931-76 / IPC J-STD-006C</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-2 font-serif">Какую температуру выставить на паяльнике?</h2>
            <p class="text-sm text-ink-muted mt-1.5 leading-relaxed">Узнайте точный температурный режим для вашей задачи, чтобы не сжечь компоненты, не отслоить дорожки и получить надежный блестящий контакт.</p>
            <div class="mt-3 flex items-center gap-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">Для новичков</span>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">~ 1 мин</span>
            </div>
          </div>
          <div class="font-hand text-base text-ink-muted/80 italic hidden sm:block text-right rotate-[-1deg]">
            «Замеряйте температуру контактно у галтели»
          </div>
        </div>

        <div class="tool-two-col">
          
          <!-- Controls -->
          <div class="space-y-4">
            <div>
              <label for="temp-work" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">1. Что вы собираетесь паять?</label>
              <select id="temp-work" class="input-base font-mono">
                <option value="" selected disabled>-- Выберите задачу --</option>
                <option value="smd_medium">Обычные SMD детали (0603–1206) или микросхемы (SOP, QFP)</option>
                <option value="smd_small">Очень мелкие SMD детали (0201–0402)</option>
                <option value="smd_large">Крупные микросхемы или транзисторы с теплоотводом (QFN, D-PAK)</option>
                <option value="pth">Детали в отверстия (выводной монтаж), штыревые разъемы</option>
                <option value="wire_thin">Тонкие проводки и перемычки (до 0.5 мм²)</option>
                <option value="wire_medium">Обычные провода (0.5 – 2.5 мм²)</option>
                <option value="wire_heavy">Толстые силовые провода (больше 2.5 мм²)</option>
                <option value="bga">BGA чипы (реболлинг или пайка феном)</option>
                <option value="copper_pipe">Медные трубы</option>
              </select>
            </div>

            <div>
              <label for="temp-solder" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">2. Какой припой вы используете?</label>
              <select id="temp-solder" class="input-base font-mono">
                <option value="" selected disabled>-- Выберите припой --</option>
                <option value="pos61">Свинцовый: ПОС-61 или аналог (183–190 °C)</option>
                <option value="sn63pb37">Свинцовый: Sn63Pb37 / ПОС-63 (183 °C)</option>
                <option value="sac305">Бессвинцовый: SAC305 или обычный без свинца (217–220 °C)</option>
                <option value="pos40">Грубый свинцовый: ПОС-40 (183–238 °C)</option>
                <option value="sn42bi58">Низкотемпературный (паста): Sn42Bi58 (138 °C)</option>
                <option value="roze">Сплав Розе (только для выпаивания) (94 °C)</option>
              </select>
            </div>

            <button type="button" id="temp-calc-btn" class="btn-primary w-full mt-4">Рассчитать терморежим</button>

            <!-- Temperature Meter Scale -->
            <div id="temp-meter-box" class="p-3.5 rounded border border-paper-border bg-paper-subtle space-y-2" style="display:none;">
              <div class="flex items-center justify-between text-[11px] font-mono">
                <span class="text-ink-muted uppercase">Шкала температуры станции:</span>
                <span class="text-ink-faint">100°C ───── 320°C ───── 450°C</span>
              </div>
              <div class="meter-bar">
                <div id="temp-bar-fill" class="meter-fill" style="width: 55%; background-color: var(--color-accent);"></div>
              </div>
            </div>
          </div>

          <!-- Result Board (Sketch Post-It Memo) -->
          <div class="relative pt-2">
            <!-- Washi Tape sticker on top -->
            <div class="sketch-washi-tape" style="top: 0px; left: 50%; transform: translateX(-50%) rotate(-1.2deg);" aria-hidden="true"></div>

            <div id="temp-result-board" class="sketch-sticky-note p-5 sm:p-6 space-y-4 transition-all">
              <!-- Folded corner triangle (bottom-right) -->
              <svg aria-hidden="true" class="sketch-corner-fold" viewBox="0 0 24 24" width="24" height="24">
                <polygon points="0,24 24,0 24,24" class="sketch-corner-triangle"></polygon>
                <line x1="0" y1="24" x2="24" y2="0" class="sketch-corner-crease"></line>
              </svg>

              <!-- Sticky Note Header -->
              <div class="flex items-center justify-between pb-3 border-b sketch-divider">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-accent inline-block"></span>
                  <span class="font-mono text-xs font-bold uppercase tracking-wider text-ink">КАРТА ТЕРМОРЕЖИМА</span>
                </div>
                <span class="font-mono text-[11px] text-ink-muted uppercase">IPC/JEDEC</span>
              </div>

              <!-- State 1: Empty state (in flow, no overlapping absolute elements) -->
              <div id="temp-empty-state" class="py-6 flex flex-col items-center justify-center text-center space-y-2">
                <div class="sketch-icon-badge mb-1" title="Лабораторный калибровочный термометр">
                  <svg class="w-7 h-7 text-accent" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <!-- Стеклянная колба термометра -->
                    <path d="M10.5 14.5V4.5a2 2 0 0 1 4 0v10a4 4 0 1 1-4 0z"></path>
                    <!-- Столбик и мениск ртути/нагрева -->
                    <path d="M12.5 10v6" stroke-width="2.2"></path>
                    <circle cx="12.5" cy="18" r="2.2" fill="currentColor" stroke="none"></circle>
                    <!-- Градуировочные риски шкалы температур -->
                    <line x1="14.5" y1="6" x2="18.5" y2="6" stroke-width="1.3"></line>
                    <line x1="14.5" y1="8.5" x2="17" y2="8.5" stroke-width="1"></line>
                    <line x1="14.5" y1="11" x2="18.5" y2="11" stroke-width="1.3"></line>
                    <line x1="14.5" y1="13.5" x2="17" y2="13.5" stroke-width="1"></line>
                    <!-- Инженерная отметка градусов °C -->
                    <circle cx="21" cy="6" r="1.1" stroke-width="1.1"></circle>
                    <path d="M25.5 6.2a2 2 0 0 0-1.6-.9c-1.1 0-1.8.8-1.8 1.9s.7 1.9 1.8 1.9c.7 0 1.3-.4 1.6-.9" stroke-width="1.2"></path>
                  </svg>
                </div>
                <div class="text-xs font-mono font-bold uppercase text-ink">Укажите параметры слева</div>
                <p class="text-xs font-mono text-ink-muted max-w-xs leading-relaxed">
                  Выберите задачу и марку припоя, чтобы рассчитать оптимальный диапазон жала
                </p>
                <div class="font-hand text-base text-ink-muted/90 italic pt-1" style="transform: rotate(-1deg);">
                  «Стартовый режим станции и защита от перегрева»
                </div>
              </div>
              
              <!-- State 2: Result content (hidden by default, revealed on calculation) -->
              <div id="temp-result-content" aria-live="polite" aria-atomic="true" class="hidden space-y-4">
                <div>
                  <span class="text-xs font-mono uppercase text-ink-muted font-bold block mb-1">Рекомендуемый диапазон уставки:</span>
                  <div class="flex items-baseline gap-2 flex-wrap">
                    <div id="temp-range-display" class="text-3xl sm:text-4xl font-bold font-mono text-accent leading-none">
                      260 – 310 °C
                    </div>
                    <span class="text-xs font-mono text-ink-faint">на индикаторе станции</span>
                  </div>
                </div>

                <!-- Tip & Geometry Note -->
                <div class="sketch-tip-box p-3.5 space-y-2">
                  <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-accent shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
                    <span id="temp-tip-display" class="text-xs font-mono font-bold text-ink">Форма жала: скошенное или «ложка»</span>
                  </div>
                  <div id="temp-advice-display" class="text-xs font-mono text-ink-muted leading-relaxed">
                    SOP/QFP: двигайтесь по выводам плавно. Флюс-гель помогает растечься припою между выводами без перемычек.
                  </div>
                </div>

                <!-- Warning Callout (margin-right prevents overlap with bottom-right folded corner) -->
                <div id="temp-warn-box" class="sketch-warn-box p-3 flex items-start gap-2.5" style="display:none; margin-right: 28px;">
                  <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                  <span id="temp-warn-display" class="text-xs font-mono leading-normal">
                    Мостики? Сначала добавьте флюс, затем снимите оплёткой — не поднимайте температуру выше предела.
                  </span>
                </div>

                <div class="font-hand text-base text-ink-muted/90 italic pt-2 border-t sketch-divider" style="padding-right: 28px; transform: rotate(-0.5deg);">
                  «Время контакта с выводом: 1.5–2.5 секунды. Не давите жалом на контактную площадку!»
                </div>
              </div>
            </div>
          </div>

        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 2: КОНФИГУРАТОР ПАЯЛЬНИКА (#iron)                      -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 2: КОНФИГУРАТОР ПАЯЛЬНИКА (#iron)                      -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="iron" class="card-tool space-y-6 relative bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <div class="absolute -top-2.5 left-8 w-12 h-3.5 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-2xs rotate-[1.8deg] pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="sketch-pill-gray text-ink font-mono text-[11px] font-bold">02 // КОНФИГУРАТОР ВЕРСТАКА</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">Спецификация оборудования</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-2 font-serif">Что купить для пайки?</h2>
            <p class="text-sm text-ink-muted mt-1.5 leading-relaxed">Ответьте на два вопроса, и мы подскажем оптимальный класс оборудования: от недорогого паяльника для дома до профессиональной станции.</p>
          </div>
          <div class="font-hand text-base text-ink-muted/80 italic hidden sm:block text-right rotate-[0.5deg]">
            «Подбор картриджа под теплоёмкость»
          </div>
        </div>

        <div class="tool-two-col">
          
          <!-- Selectors -->
          <div class="space-y-4">
            <div>
              <label for="iron-task" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">1. Что вы будете паять чаще всего?</label>
              <select id="iron-task" class="input-base font-mono">
                <option value="wire">Обычные провода, кабели, бытовая пайка для дома</option>
                <option value="boards_tht">Печатные платы (детали с ножками в отверстия)</option>
                <option value="smd" selected>Платы с мелкими SMD-деталями (базовый ремонт электроники)</option>
                <option value="smd_fine">Сложный ремонт: очень мелкие детали под микроскопом, телефоны</option>
                <option value="connectors">Толстые силовые разъемы (дроны, авто), клеммы</option>
                <option value="bga_rework">Замена BGA чипов (ноутбуки, видеокарты)</option>
                <option value="pipes">Медные водопроводные трубы</option>
              </select>
            </div>

            <div>
              <label for="iron-intensity" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">2. Как часто планируете паять?</label>
              <select id="iron-intensity" class="input-base font-mono">
                <option value="rare">Пару раз в месяц (починить наушники, собрать гирлянду)</option>
                <option value="regular" selected>Регулярно (хобби, радиолюбитель, сборка своих плат)</option>
                <option value="daily">Ежедневно (работа в сервисном центре)</option>
              </select>
            </div>

            <!-- Avoid Alert -->
            <div id="iron-avoid-box" class="p-3 rounded bg-red-50 dark:bg-red-950/30 border border-red-300 dark:border-red-800 flex items-start gap-2" style="display:none;">
              <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <div>
                <div class="text-xs font-mono uppercase font-bold text-red-700 dark:text-red-400">Чего избегать:</div>
                <div id="iron-avoid-display" class="text-xs font-mono text-red-800 dark:text-red-300"></div>
              </div>
            </div>
          </div>

          <!-- Recommendation Spec Card -->
          <div class="p-5 rounded-lg border border-paper-border bg-paper-subtle space-y-4 shadow-xs">
            <div>
              <span class="text-xs font-mono uppercase tracking-wider text-accent font-bold">Рекомендуемый класс станции:</span>
              <div id="iron-class-display" class="text-lg font-bold font-sans text-ink mt-0.5">
                Паяльная станция + термофен
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-paper-border text-xs font-mono">
              <div>
                <span class="text-ink-muted block text-xs uppercase">Термоконтроль:</span>
                <span id="iron-control-display" class="font-bold text-ink">Цифровой термостат</span>
              </div>
              <div>
                <span class="text-ink-muted block text-xs uppercase">Тип жал / картриджей:</span>
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
            <div class="font-hand text-sm text-ink-muted/80 italic pt-1 border-t border-paper-border/60">
              «Для массивных земляных полигонов выбирайте картриджи с быстрым термопрофилем (JBC C245 / T12)»
            </div>
          </div>

        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P0 ИНСТРУМЕНТ 3: ДЕРЕВО ДИАГНОСТИКИ ДЕФЕКТОВ (#defect)                 -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="defect" class="card-tool space-y-6 relative bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <div class="absolute -top-2.5 left-8 w-12 h-3.5 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-2xs rotate-[-2deg] pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="sketch-pill-yellow text-ink font-mono text-[11px] font-bold">03 // ДИАГНОСТИКА БРАКА</span>
              <span class="text-xs font-mono text-ink-muted hidden sm:inline-block">Дерево инженерных решений</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-2 font-serif">Пайка не получилась. Что делать?</h2>
            <p class="text-sm text-ink-muted mt-1.5 leading-relaxed">Укажите симптом брака, и мы шаг за шагом найдем причину и подскажем, как исправить ошибку без вреда для платы.</p>
            <div class="mt-3 flex items-center gap-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">Продвинутый</span>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">~ 3 мин</span>
            </div>
          </div>
          <div class="flex flex-col sm:items-end gap-2">
            <div class="font-hand text-base text-ink-muted/80 italic hidden sm:block text-right rotate-[-1deg]">
              «Пошаговое устранение первопричины»
            </div>
            <button type="button" id="defect-restart-btn" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded font-mono text-xs border border-paper-border bg-paper hover:border-ink transition-colors text-ink shadow-2xs" style="display:none;">
              <svg class="w-3.5 h-3.5 text-ink shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
              <span>Начать сначала</span>
            </button>
          </div>
        </div>

        <!-- Dynamic Decision Tree Container -->
        <div id="defect-tree-container" aria-live="polite" class="min-h-[220px]">
          <!-- Rendered dynamically by workbench.js -->
        </div>
      </section>


      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 4: КАЛЬКУЛЯТОР ФЛЮСА (#calculator)                       -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="calculator" class="card-tool relative space-y-6 bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <div class="absolute -top-2.5 left-8 w-12 h-3.5 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-2xs rotate-[-1.5deg] pointer-events-none"></div>
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="sketch-pill-gray text-ink font-mono text-[11px] font-bold">04 // ДОЗИРОВКА МАТЕРИАЛОВ</span>
              <span class="font-hand text-accent font-bold text-lg hidden sm:inline-flex items-center gap-1.5 italic rotate-[-1deg]">
                «Апертурный расчёт (Indium Corp)»
              </span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-2 font-serif">Сколько пасты или флюса наносить?</h2>
            <p class="text-sm text-ink-muted mt-1.5 leading-relaxed">Рассчитайте точную дозировку пасты или флюса для вашей платы, чтобы избежать замыканий из-за избытка материала.</p>
            <div class="mt-3 flex items-center gap-2">
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">Инженерный</span>
              <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">~ 2 мин</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2.5 py-1 rounded text-[11px] font-mono bg-paper-subtle border border-paper-border text-ink-muted">IPC-7525B / Indium Corp Model</span>
          </div>
        </div>

        <!-- Presets bar -->
        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-mono font-semibold text-ink-muted uppercase">Быстрые пресеты плат:</span>
            <span class="text-[11px] font-mono text-ink-faint">Кликните для авто-подстановки</span>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs" data-area="9">Arduino Nano (9 см²)</button>
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs" data-area="18">ESP32-WROOM (18 см²)</button>
            <button type="button" class="preset-btn active px-3 py-1.5 rounded text-xs font-mono border border-accent bg-paper font-bold text-accent transition-colors shadow-2xs" data-area="35">GPU VRAM (35 см²)</button>
            <button type="button" class="preset-btn px-3 py-1.5 rounded text-xs font-mono border border-paper-border bg-paper hover:border-accent text-ink transition-colors shadow-2xs" data-area="120">ATX Motherboard (120 см²)</button>
          </div>
        </div>

        <!-- Workbench Grid: Controls + Sticky Note Result -->
        <div id="calc-workbench-grid" style="display:flex; flex-direction:column; gap:1.5rem;">
          <div style="display:grid; grid-template-columns:1fr; gap:1.5rem; align-items:start;">
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
                  <label for="flux-type" style="display:block; font-size:11px; font-family:monospace; font-weight:700; text-transform:uppercase; color:var(--color-ink); margin-bottom:8px; letter-spacing:0.05em;">Материал и технология нанесения:</label>
                  <select id="flux-type" style="width:100%; padding:10px 14px; border-radius:6px; background:var(--color-paper); border:1px solid var(--color-paper-border-dark); color:var(--color-ink); font-family:monospace; font-size:13px; outline:none;">
                    <option value="paste_sac" selected>Паяльная паста SAC305 (Sn96.5Ag3Cu0.5, 88.5% металла) — трафарет</option>
                    <option value="paste_sn63">Паяльная паста Sn63Pb37 (эвтектика, 90% металла) — трафарет</option>
                    <option value="bga_nc">Безотмывочный гель BGA (NC-559 / ROL0) — тонкий слой 50–70 мкм</option>
                    <option value="smd_rma">Канифольный гель средней активности RMA (ROM1) — кисть/дозатор</option>
                    <option value="clean_ws">Водосмывной флюс высокой активности WS (ORH1) — смыв водой</option>
                  </select>
                </div>

                <!-- Stencil Thickness Control (shown for solder pastes) -->
                <div id="stencil-thickness-group">
                  <label for="stencil-thickness" style="display:block; font-size:11px; font-family:monospace; font-weight:700; text-transform:uppercase; color:var(--color-ink); margin-bottom:8px; letter-spacing:0.05em;">Толщина трафарета (апертура 18%):</label>
                  <select id="stencil-thickness" style="width:100%; padding:10px 14px; border-radius:6px; background:var(--color-paper); border:1px solid var(--color-paper-border-dark); color:var(--color-ink); font-family:monospace; font-size:13px; outline:none;">
                    <option value="100">100 мкм (0.10 мм — тонкий шаг, QFN, BGA 0.5 мм)</option>
                    <option value="120" selected>120 мкм (0.12 мм — стандартный SMD монтаж 0603/SOIC/QFP)</option>
                    <option value="130">130 мкм (0.13 мм — универсальный стандарт)</option>
                    <option value="150">150 мкм (0.15 мм — силовые платы, компоненты 0805–1206, разъёмы)</option>
                  </select>
                </div>

                <!-- Batch multiplier -->
                <div>
                  <span class="block text-[11px] font-mono font-bold uppercase text-ink mb-2 tracking-[0.05em]">Объём партии плат:</span>
                  <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <button type="button" class="batch-btn" data-qty="1" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; font-weight:700; border:1px solid var(--color-accent); background:var(--color-paper); color:var(--color-accent); cursor:pointer;">1 плата</button>
                    <button type="button" class="batch-btn" data-qty="5" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">5 плат</button>
                    <button type="button" class="batch-btn" data-qty="10" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">10 плат</button>
                    <button type="button" class="batch-btn" data-qty="50" style="padding:6px 12px; border-radius:6px; font-size:12px; font-family:monospace; border:1px solid var(--color-paper-border); background:var(--color-paper); color:var(--color-ink); cursor:pointer;">50 плат (серия)</button>
                  </div>
                </div>
              </div>

              <!-- Sticky Note Engineer's Memo -->
              <div class="relative pt-2">
                <!-- Washi Tape sticker on top -->
                <div class="sketch-washi-tape" style="top: 0px; left: 50%; transform: translateX(-50%) rotate(1.2deg);" aria-hidden="true"></div>

                <div id="engineer-memo" class="sketch-sticky-note p-5 sm:p-6 space-y-4 transition-all" style="transform: rotate(-1.2deg);">
                  <!-- Folded corner triangle (bottom-right) -->
                  <svg aria-hidden="true" class="sketch-corner-fold" viewBox="0 0 24 24" width="24" height="24">
                    <polygon points="0,24 24,0 24,24" class="sketch-corner-triangle"></polygon>
                    <line x1="0" y1="24" x2="24" y2="0" class="sketch-corner-crease"></line>
                  </svg>

                  <!-- Pin header -->
                  <div class="flex items-center justify-between pb-3 border-b sketch-divider">
                    <span class="inline-flex items-center gap-2">
                      <svg class="w-4 h-4 text-accent shrink-0 inline-block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a1 1 0 0 0 0-2H8a1 1 0 0 0 0 2h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>
                      <span class="font-hand text-xl font-bold text-ink tracking-tight">Заметка инженера</span>
                    </span>
                    <span class="font-mono text-[10px] font-bold text-ink-muted uppercase tracking-wider">IPC-A-610</span>
                  </div>

                  <!-- Dosage highlight -->
                  <div>
                    <div class="text-[10px] font-mono font-bold uppercase text-ink-muted mb-1">Рекомендуемая дозировка:</div>
                    <div class="flex items-baseline gap-2 flex-wrap">
                      <span id="res-volume" class="font-mono text-3xl sm:text-4xl font-bold text-accent leading-none">~0.18 мл</span>
                      <span id="batch-note" class="text-xs font-mono text-ink-faint">(на 1 плату)</span>
                    </div>
                  </div>

                  <!-- Hand-drawn PCB sketch card -->
                  <div class="sketch-tip-box p-3 flex items-center gap-3">
                    <svg id="pcb-svg" width="70" height="70" viewBox="0 0 80 80" fill="none"
                         stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                         class="text-ink-muted shrink-0 transition-all duration-200" style="display:block; opacity:0.85;">
                      <rect x="5" y="5" width="70" height="70" rx="3" stroke-width="1.4" stroke-dasharray="2.5 1.5" fill="none"/>
                      <circle cx="12" cy="12" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="68" cy="12" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="12" cy="68" r="2.2" stroke-width="1.1" fill="none"/>
                      <circle cx="68" cy="68" r="2.2" stroke-width="1.1" fill="none"/>
                      <rect x="26" y="26" width="28" height="28" rx="2" stroke-width="1.4" stroke-dasharray="3 1.5" fill="none"/>
                      <circle cx="33" cy="33" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="40" cy="33" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="47" cy="33" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="33" cy="40" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="40" cy="40" r="1.8" fill="var(--color-accent)" stroke="none" opacity="0.9"/>
                      <circle cx="47" cy="40" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="33" cy="47" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="40" cy="47" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
                      <circle cx="47" cy="47" r="1.3" fill="currentColor" stroke="none" opacity="0.6"/>
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
                      <div class="text-[10px] font-mono uppercase font-bold text-ink-muted mb-0.5">Зарисовка платы:</div>
                      <div id="pcb-dimensions" class="text-xs font-mono font-bold text-ink">59 × 59 мм (35 см²)</div>
                      <div class="text-[10px] font-mono text-ink-faint mt-0.5">↑ масштаб к площади</div>
                    </div>
                  </div>

                  <!-- Process description -->
                  <p id="res-desc" class="text-xs font-mono text-ink-muted leading-relaxed">
                    Для 35 см² при BGA реболлинге наносите тонкий слой 50-70 мкм. Избыток вызывает кипение и сдвиг чипа.
                  </p>

                  <!-- Wash tip -->
                  <div class="sketch-warn-box p-3 flex items-start gap-2 text-xs font-mono" style="margin-right: 28px;">
                    <svg class="w-4 h-4 text-accent shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span id="wash-tip" class="leading-normal">Отмывка: опциональна (No-Clean)</span>
                  </div>

                  <!-- Action copy button -->
                  <div style="padding-right: 28px;">
                    <button type="button" id="copy-flux-btn" class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded text-xs font-mono font-bold border border-paper-border-dark bg-paper text-ink hover:border-ink transition-colors cursor-pointer shadow-xs">
                      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" class="shrink-0" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                      </svg>
                      <span id="copy-flux-text" aria-live="polite">Скопировать параметры в журнал</span>
                    </button>
                  </div>
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
              <div class="text-xs font-mono text-ink-muted uppercase">Гель BGA (ROL0)</div>
              <div id="matrix-bga" class="text-sm font-mono font-bold text-ink">0.21 мл</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-xs font-mono text-ink-muted uppercase">RMA канифоль (ROM1)</div>
              <div id="matrix-rma" class="text-sm font-mono font-bold text-ink">0.28 мл</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-xs font-mono text-ink-muted uppercase">Паста SAC305 (120 мкм)</div>
              <div id="matrix-paste" class="text-sm font-mono font-bold text-ink">0.37 г</div>
            </div>
            <div class="p-3 rounded border border-paper-border bg-paper-subtle space-y-1">
              <div class="text-xs font-mono text-ink-muted uppercase">Водосмывной WS (ORH1)</div>
              <div id="matrix-ws" class="text-sm font-mono font-bold text-ink">0.21 мл</div>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 4.1: СЕЛЕКТОР ФЛЮСА (#flux-selector)                     -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="flux-selector" class="card-tool space-y-6 mt-8 relative bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <!-- Washi Tape Accent -->
        <div class="washi-tape -top-2.5 left-8 w-24 bg-[#ebdeb3]/80 dark:bg-[#786b4f]/40 border-y border-amber-900/10 rotate-[-1.5deg]" aria-hidden="true"></div>

        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">04.1 // ПОДБОР ФЛЮСА</span>
              <span class="sketch-pill-yellow">ГОСТ Р МЭК 61190</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1 font-serif">Какой флюс выбрать для работы?</h2>
            <p class="text-sm text-ink-muted mt-2">Ответьте на три вопроса, и мы подберем правильный класс флюса по стандартам ГОСТ и IPC, который справится с задачей и не вызовет коррозию.</p>
            <div class="mt-3 flex items-center gap-2 flex-wrap">
              <span class="sketch-pill-gray">J-STD-004B</span>
              <span class="sketch-pill-gray">~ 1 мин</span>
              <span class="font-hand text-base text-amber-800 dark:text-amber-400 font-semibold tracking-wide ml-2">«Флюс активируется теплом, смывается дисциплиной»</span>
            </div>
          </div>
          <div class="text-xs font-mono text-ink-faint hidden sm:block text-right">Стандарты IPC-A-610 / J-STD-001</div>
        </div>
        
        <div class="tool-two-col">
          <div class="space-y-4">
            <div>
            <div>
              <label for="flux-metal" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">1. В каком состоянии плата и детали?</label>
              <select id="flux-metal" class="input-base font-mono">
                <option value="enig_cu">Новые чистые детали и заводская плата (золото ENIG, HASL)</option>
                <option value="osp_oxidized">Старая, окисленная медь или плата после долгого хранения</option>
                <option value="steel_nickel">Сталь, нержавейка, никелированные клеммы или экраны</option>
                <option value="aluminum">Алюминиевые провода или радиаторы</option>
              </select>
            </div>
            <div>
              <label for="flux-process" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">2. Как именно вы будете паять?</label>
              <select id="flux-process" class="input-base font-mono">
                <option value="manual_smd">Ручной ремонт SMD, замена чипов, пайка феном или паяльником</option>
                <option value="tht_wire">Пайка обычных проводов, разъемов и деталей с ножками (THT)</option>
                <option value="stencil">Нанесение пасты через трафарет (для печи)</option>
                <option value="heavy">Пайка толстых проводов, массивных клемм, где нужен сильный прогрев</option>
              </select>
            </div>
            <div>
              <label for="flux-wash" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">3. Будете ли вы отмывать плату после пайки?</label>
              <select id="flux-wash" class="input-base font-mono">
                <option value="noclean">Нет, хочу оставить как есть (нужен безотмывочный No-Clean)</option>
                <option value="alcohol">Да, протру изопропиловым спиртом или специальной смывкой</option>
                <option value="diwater">Да, буду мыть в ультразвуковой ванне с водой</option>
              </select>
            </div>
          </div>
          
          <div class="p-5 rounded-lg border border-[#fde047] dark:border-amber-700/50 bg-[#fffdf5] dark:bg-card space-y-4 flex flex-col justify-center relative shadow-xs">
            <!-- Corner fold decoration -->
            <svg aria-hidden="true" class="absolute bottom-0 right-0 w-5 h-5 pointer-events-none" viewBox="0 0 20 20">
              <path d="M20 20 L0 20 L20 0 Z" fill="#fef08a" opacity="0.6"/>
              <path d="M20 0 L0 20" stroke="#eab308" stroke-width="0.8" fill="none"/>
            </svg>

            <div>
              <span class="text-xs font-mono uppercase tracking-wider text-accent font-bold">Класс по ГОСТ Р МЭК 61190 / J-STD-004B:</span>
              <div id="flux-code-display" class="text-2xl font-bold font-mono text-ink mt-0.5">ROL0</div>
              <div id="flux-title-display" class="text-xs font-mono font-bold text-ink mt-1">Безотмывочный канифольный гель (NC-559-V2-TF, Kester 959T)</div>
            </div>

            <div class="space-y-1.5 pt-2 border-t border-paper-border">
              <span class="text-xs font-mono uppercase tracking-wider text-ink-muted font-bold">Регламент отмывки (IPC-A-610H):</span>
              <p id="flux-wash-display" class="text-xs font-mono text-ink leading-relaxed">
                Отмывка опциональна: остатки химически инертны. Для Class 3 и под лакирование смывается IPA 99.7%.
              </p>
            </div>

            <div id="flux-warn-box" class="p-3 rounded bg-amber-50 border border-amber-300 dark:border-amber-800 dark:bg-amber-950/30 flex items-start gap-2" style="display:none;">
              <svg class="w-4 h-4 text-amber-700 dark:text-amber-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span id="flux-warn-display" class="text-xs font-mono text-amber-800 dark:text-amber-300 leading-normal"></span>
            </div>

            <div id="flux-article-link" class="pt-3 border-t border-paper-border">
              <a href="article.php?slug=gid-po-flyusam" class="inline-flex items-center gap-1.5 text-xs font-mono text-accent font-bold hover:underline">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-0.5-.05"/><path d="M6 2v17.5a2.5 2.5 0 0 1 2.5-2.5H20"/></svg>
                <span>Читать подробный гид по флюсам</span>
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 4.2: РАСХОД ПРИПОЯ (#solder-consumption)                 -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="solder-consumption" class="card-tool space-y-6 mt-8 mb-8 relative bg-paper border border-paper-border rounded-lg p-5 sm:p-7 shadow-xs">
        <!-- Washi Tape Accent -->
        <div class="washi-tape -top-2.5 right-10 w-24 bg-[#ebdeb3]/80 dark:bg-[#786b4f]/40 border-y border-amber-900/10 rotate-[2deg]" aria-hidden="true"></div>

        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">04.2 // РАСХОД МАТЕРИАЛОВ</span>
              <span class="sketch-pill-gray">Сборщику</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink mt-1 font-serif">Сколько припоя уйдет на работу?</h2>
            <p class="text-sm text-ink-muted mt-2">Оцените расход проволочного припоя перед началом большой сборки, чтобы не пришлось докупать катушку в середине процесса.</p>
            <div class="mt-3 flex items-center gap-2 flex-wrap">
              <span class="sketch-pill-gray">~ 1 мин</span>
              <span class="font-hand text-base text-ink-muted font-semibold tracking-wide ml-2">«Закладывайте +15% на обрезки и очистку жала»</span>
            </div>
          </div>
          <div class="text-xs font-mono text-ink-faint hidden sm:block text-right">Запас +15%</div>
        </div>
        
        <div class="tool-two-col">
          <div class="space-y-4">
            <div>
              <label for="cons-type" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">Что именно паяем?</label>
              <select id="cons-type" class="input-base font-mono">
                <option value="tht">Обычные детали с ножками в отверстия (THT)</option>
                <option value="smd">SMD компоненты (дозирование проволоки вручную)</option>
                <option value="wire">Сращивание и лужение проводов</option>
              </select>
            </div>
            <div>
              <label for="cons-diam" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">Какой толщины проволока припоя?</label>
              <select id="cons-diam" class="input-base font-mono">
                <option value="0.5">0.5 мм</option>
                <option value="0.8" selected>0.8 мм (стандарт)</option>
                <option value="1.0">1.0 мм</option>
              </select>
            </div>
            <div>
              <label for="cons-count" class="block text-xs font-mono font-bold uppercase text-ink mb-1.5">Сколько всего точек (или стыков) нужно запаять?</label>
              <input type="number" id="cons-count" value="100" min="1" max="10000" class="input-base font-mono">
            </div>
          </div>
          
          <div class="p-5 rounded-lg border border-paper-border bg-paper shadow-xs space-y-4 flex flex-col justify-center relative">
            <div class="text-center">
              <span class="text-xs font-mono uppercase tracking-wider text-ink-muted font-bold block mb-2">Ориентировочный расход:</span>
              <div id="cons-length" class="text-3xl font-bold font-mono text-ink">
                ~ 120 см
              </div>
              <div id="cons-weight" class="text-sm font-mono text-ink-muted mt-1">
                Вес: ~ 4.5 г
              </div>
            </div>
            <div class="pt-3 border-t border-paper-border text-center">
              <a href="article.php?slug=temperaturnye-profili" class="inline-flex items-center gap-1.5 text-xs font-mono text-accent font-bold hover:underline">
                <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-0.5-.05"/><path d="M6 2v17.5a2.5 2.5 0 0 1 2.5-2.5H20"/></svg>
                <span>Как правильно дозировать припой</span>
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <!-- P1 ИНСТРУМЕНТ 5: РЕЕСТР СПЛАВОВ И ПРИПОЕВ (#table, #solder-table)        -->
      <!-- ═══════════════════════════════════════════════════════════════════════ -->
      <section id="table" class="scroll-mt-20 card-tool space-y-6 relative">
        <a id="solder-table" class="block absolute -top-24 pointer-events-none" aria-hidden="true"></a>
        <!-- Washi Tape Accent -->
        <div class="washi-tape -top-2.5 left-12 w-28 bg-[#ebdeb3]/80 dark:bg-[#786b4f]/40 border-y border-amber-900/10 rotate-[-1deg]" aria-hidden="true"></div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-paper-border">
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-mono font-bold text-accent uppercase tracking-wider">05 // СПРАВОЧНИК МЕТАЛЛОВ</span>
              <span class="sketch-pill-yellow">ГОСТ 21930-76</span>
            </div>
            <div class="flex items-baseline gap-3 flex-wrap mt-0.5">
              <h2 class="text-xl font-bold text-ink font-serif">Реестр сплавов и температур плавления</h2>
              <span class="font-hand text-base text-ink-muted font-semibold tracking-wide">«IPC J-STD-006C & ГОСТ»</span>
            </div>
          </div>
          <div class="w-full sm:w-64">
            <input type="text" id="solder-search" aria-label="Поиск по реестру сплавов и припоев" placeholder="Поиск (ПОС-61, SAC305, 183°C)..." class="input-base font-mono text-xs"/>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table id="solder-table-grid" class="w-full text-left font-mono text-xs border-collapse">
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
                  <td class="py-2.5 px-3 font-bold text-ink whitespace-nowrap">
                    <?= e($solder['name']) ?>
                    <?php if ($solder['type'] === 'leadfree'): ?>
                      <span class="ml-1 px-1.5 py-0.5 rounded border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 text-xs">RoHS</span>
                    <?php elseif ($solder['type'] === 'lowtemp'): ?>
                      <span class="ml-1 px-1.5 py-0.5 rounded border border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-300 text-xs">Низкотемп.</span>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3">
                    <?php
                    $comps = [];
                    if (!empty($solder['sn_range'])) {
                        $comps[] = $solder['sn_range'] . '% Sn';
                    } elseif ($solder['sn_pct'] > 0) {
                        $comps[] = $solder['sn_pct'] . '% Sn';
                    }
                    if ($solder['pb_pct'] > 0) {
                        $comps[] = (!empty($solder['sn_range']) && $solder['id'] === 'pos61' ? 'ост. Pb (39–41%)' : $solder['pb_pct'] . '% Pb');
                    }
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
                    <span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-xs">
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

  <!-- Mobile Sticky Quick Bar (44px WCAG Touch Targets) -->
  <?php require_once __DIR__ . '/includes/mobile-bar.php'; ?>

  <!-- Global Engineering Search Modal (Ctrl+K) -->
  <?php require_once __DIR__ . '/includes/search-modal.php'; ?>

  <!-- Pass Rules to Client-Side Workbench Engine -->
  <script>
    window.TCHP_RULES = {
      temp: <?= json_encode($TEMP_RULES, JSON_UNESCAPED_UNICODE) ?>,
      iron: <?= json_encode($IRON_RULES, JSON_UNESCAPED_UNICODE) ?>,
      defect: <?= json_encode($DEFECT_TREE, JSON_UNESCAPED_UNICODE) ?>,
      flux: <?= json_encode($FLUX_RULES, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>

  <!-- Scripts -->
  <script src="assets/js/ui-helpers.js"></script>
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

      // Sync Card 1 mini-selectors with Temperature Calculator (#temp)
      const qWork = document.getElementById('quick-temp-work');
      const qSolder = document.getElementById('quick-temp-solder');
      const mainWork = document.getElementById('temp-work');
      const mainSolder = document.getElementById('temp-solder');

      const syncQuickToMain = () => {
        if (qWork && mainWork && qWork.value) {
          mainWork.value = qWork.value;
          mainWork.dispatchEvent(new Event('change'));
        }
        if (qSolder && mainSolder && qSolder.value) {
          mainSolder.value = qSolder.value;
          mainSolder.dispatchEvent(new Event('change'));
        }
      };

      if (qWork && mainWork) {
        qWork.addEventListener('change', syncQuickToMain);
      }
      if (qSolder && mainSolder) {
        qSolder.addEventListener('change', syncQuickToMain);
      }

      const tempCardBtn = document.querySelector('a[href="#temp"].card-btn');
      if (tempCardBtn) {
        tempCardBtn.addEventListener('click', syncQuickToMain);
      }
    })();
  </script>

</body>
</html>
