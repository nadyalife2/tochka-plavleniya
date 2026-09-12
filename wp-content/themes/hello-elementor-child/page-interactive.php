<?php
/**
 * Template Name: Интерактивный верстак (Инструменты ТЧП)
 * Template Post Type: page
 * 
 * Точка Плавления — Верстак инженера
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();
?>

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
    color: var(--color-ink) !important;
    font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
</style>

<main class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
    
    <!-- Breadcrumbs -->
    <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5">
      <a class="hover:text-ink transition-colors" href="<?php echo home_url('/'); ?>">Главная</a>
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
            <button id="calc-flux-btn" type="button" class="w-full px-4 py-2.5 bg-ink text-paper font-mono font-semibold text-xs rounded hover:opacity-90 transition-opacity border border-paper-border-dark shadow-sm">
              Рассчитать дозировку ⚡
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
          <table class="w-full text-left font-mono text-xs border-collapse">
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
                <td class="py-2.5 px-3">Пайка термочувствительных LED-матриц и разъемов</td>
              </tr>
              <tr class="hover:bg-paper-subtle/50 transition-colors">
                <td class="py-2.5 px-3 font-bold text-ink">Сплав Розе</td>
                <td class="py-2.5 px-3">50% Bi, 25% Pb, 25% Sn</td>
                <td class="py-2.5 px-3 font-bold text-ink">94°C</td>
                <td class="py-2.5 px-3"><span class="px-1.5 py-0.5 rounded bg-paper border border-paper-border text-[10px]">ГОСТ 21931</span></td>
                <td class="py-2.5 px-3">Только демонтаж разъемов и лужение плат в кипятке</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </div>

  </div>
</main>

<script>
  (function() {
    // Flux Calculator
    const calcBtn = document.getElementById('calc-flux-btn');
    if (calcBtn) {
      calcBtn.addEventListener('click', function() {
        const area = parseFloat(document.getElementById('flux-area').value) || 35;
        const type = document.getElementById('flux-type').value;
        let vol = 0;
        let desc = '';

        if (type === 'bga_nc') {
          vol = (area * 0.005).toFixed(2);
          desc = `Для ${area} см² (BGA реболлинг) требуется ~${vol} мл флюса-геля. Наносите равномерно шпателем толщиной 50-70 мкм.`;
        } else if (type === 'smd_rma') {
          vol = (area * 0.008).toFixed(2);
          desc = `Для ${area} см² (SMD монтаж) требуется ~${vol} мл. Требуется обязательная отмывка изопропиловым спиртом после пайки.`;
        } else if (type === 'paste_sac') {
          vol = (area * 0.015).toFixed(2);
          desc = `Для ${area} см² нанесения через трафарет потребуется ~${vol} г паяльной пасты SAC305.`;
        } else {
          vol = (area * 0.006).toFixed(2);
          desc = `Для ${area} см² водосмывного флюса требуется ~${vol} мл. Обязательна деионизированная промывка в УЗ-ванне.`;
        }

        document.getElementById('res-volume').textContent = `~${vol} мл/г`;
        document.getElementById('res-desc').textContent = desc;
      });
    }

    // Solder Search Filter
    const searchInput = document.getElementById('solder-search');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#solder-tbody tr');
        rows.forEach(row => {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(q) ? '' : 'none';
        });
      });
    }
  })();
</script>

<?php get_footer(); ?>
