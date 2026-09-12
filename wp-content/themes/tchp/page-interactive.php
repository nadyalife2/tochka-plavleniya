<?php
/**
 * Template Name: Интерактивный верстак (Калькуляторы)
 * Description: Калькулятор расхода флюса, интерактивный тест и таблица сплавов
 *
 * @package TCHP
 * @version 2.0.0
 */

// Enqueue scripts for interactive calculators
wp_enqueue_script('tchp-flux-calc', get_template_directory_uri() . '/assets/js/flux-calc.js', [], '2.0.0', true);
wp_enqueue_script('tchp-solder-quiz', get_template_directory_uri() . '/assets/js/solder-quiz.js', [], '2.0.0', true);
wp_enqueue_script('tchp-solder-table', get_template_directory_uri() . '/assets/js/solder-table.js', [], '2.0.0', true);

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 space-y-12">

    <!-- Hero Section -->
    <section class="border border-paper-border rounded-lg bg-card p-6 sm:p-10 relative overflow-hidden shadow-sm">
      <div class="max-w-2xl space-y-3">
        <span class="pill-tag-yellow font-mono text-xs">Инженерный верстак ТЧП</span>
        <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-ink">
          Инструменты и калькуляторы
        </h1>
        <p class="text-base text-ink-muted leading-relaxed font-sans">
          Калькулятор расхода флюса, экспресс-тест на знание стандартов пайки и справочная таблица припоев.
        </p>
      </div>
    </section>

    <!-- 01: КАЛЬКУЛЯТОР РАСХОДА ФЛЮСА -->
    <section class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 shadow-sm" id="calc">
      <div class="border-b border-paper-border pb-4">
        <div class="inline-flex items-center gap-2 text-xs font-mono text-brand-orange uppercase mb-1">
          <span class="material-symbols-outlined text-base">calculate</span>
          Модуль 01
        </div>
        <h2 class="text-2xl font-bold text-ink">Калькулятор расхода флюса</h2>
        <p class="text-xs font-mono text-ink-muted mt-1">Укажите площадь печатной платы и тип используемой химии</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-mono text-ink-muted mb-1" for="flux-area">Площадь платы (см²)</label>
          <input type="number" id="flux-area" class="w-full px-3 py-2 rounded border border-paper-border bg-paper text-ink font-mono text-sm focus:border-brand-orange outline-none" placeholder="Например: 50" min="1">
        </div>
        <div>
          <label class="block text-xs font-mono text-ink-muted mb-1" for="flux-type">Тип флюса</label>
          <select id="flux-type" class="w-full px-3 py-2 rounded border border-paper-border bg-paper text-ink font-mono text-sm focus:border-brand-orange outline-none">
            <option value="rma">RMA-223 (канифольный активированный)</option>
            <option value="nc">NC-559 (безотмывочный No-Clean)</option>
          </select>
        </div>
        <div class="flex items-end">
          <button id="calc-flux-btn" type="button" class="w-full px-4 py-2 rounded border border-paper-border-dark bg-ink text-paper text-xs font-mono font-medium hover:opacity-90 transition-opacity cursor-pointer flex items-center justify-center gap-2">
            <span>Рассчитать дозировку</span>
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </button>
        </div>
      </div>

      <div id="flux-result" class="p-4 rounded border border-paper-border bg-paper-subtle text-xs font-mono text-ink hidden"></div>
    </section>

    <!-- 02: ПРАКТИЧЕСКИЙ ТЕСТ ПО ПАЙКЕ -->
    <section class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 shadow-sm" id="quiz">
      <div class="border-b border-paper-border pb-4">
        <div class="inline-flex items-center gap-2 text-xs font-mono text-brand-orange uppercase mb-1">
          <span class="material-symbols-outlined text-base">quiz</span>
          Модуль 02
        </div>
        <h2 class="text-2xl font-bold text-ink">Экспресс-тест: Проверь свои знания</h2>
        <p class="text-xs font-mono text-ink-muted mt-1">Реальные верстачные ситуации и стандарты IPC</p>
      </div>

      <div id="quiz-wrap" class="space-y-4">
        <div class="flex justify-between items-center font-mono text-xs text-ink-muted">
          <span id="quiz-counter">Вопрос 1 из 10</span>
        </div>
        <div class="w-full h-2 bg-paper-subtle rounded border border-paper-border overflow-hidden">
          <div id="quiz-progress" class="h-full bg-brand-orange transition-all duration-300" style="width: 10%;"></div>
        </div>
        <div id="quiz-question" class="text-lg font-bold text-ink pt-2">
          Загрузка вопроса...
        </div>
        <div id="quiz-options" class="space-y-2 pt-2"></div>
        <button id="quiz-next" type="button" class="hidden px-4 py-2 rounded bg-ink text-paper text-xs font-mono font-medium cursor-pointer">
          Следующий вопрос →
        </button>
      </div>

      <div id="quiz-result" class="hidden p-6 rounded border border-paper-border bg-paper-subtle text-center space-y-3">
        <h3 class="text-xl font-bold text-ink">Результат теста</h3>
        <div id="quiz-score" class="font-mono text-3xl font-bold text-brand-orange">10 / 10</div>
        <p id="quiz-score-label" class="text-xs font-mono text-ink-muted"></p>
        <button id="quiz-restart" type="button" class="px-4 py-2 rounded bg-ink text-paper text-xs font-mono font-medium cursor-pointer">
          Пройти заново
        </button>
      </div>
    </section>

    <!-- 03: СПРАВОЧНАЯ ТАБЛИЦА ПРИПОЕВ -->
    <section class="border border-paper-border rounded-lg bg-card p-6 sm:p-8 space-y-6 shadow-sm" id="solder-table">
      <div class="border-b border-paper-border pb-4">
        <div class="inline-flex items-center gap-2 text-xs font-mono text-brand-orange uppercase mb-1">
          <span class="material-symbols-outlined text-base">table_chart</span>
          Модуль 03
        </div>
        <h2 class="text-2xl font-bold text-ink">Таблица паяльных сплавов</h2>
        <p class="text-xs font-mono text-ink-muted mt-1">Опорные физико-химические параметры и температурные окна</p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs font-mono border-collapse">
          <thead>
            <tr class="border-b-2 border-paper-border-dark bg-paper-subtle text-ink">
              <th class="p-3">Сплав</th>
              <th class="p-3">Состав</th>
              <th class="p-3">Ликвидус</th>
              <th class="p-3">Солидус</th>
              <th class="p-3">Назначение</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-paper-border text-ink-muted">
            <tr class="hover:bg-paper-subtle/40">
              <td class="p-3 font-bold text-ink">SAC305</td>
              <td class="p-3">Sn96.5 Ag3.0 Cu0.5</td>
              <td class="p-3 text-brand-orange font-semibold">217°C</td>
              <td class="p-3">217°C</td>
              <td class="p-3">Бессвинцовый промышленный стандарт</td>
            </tr>
            <tr class="hover:bg-paper-subtle/40">
              <td class="p-3 font-bold text-ink">ПОС-61</td>
              <td class="p-3">Sn61 Pb39</td>
              <td class="p-3 text-brand-orange font-semibold">183°C</td>
              <td class="p-3">183°C</td>
              <td class="p-3">Эвтектика, классический радиомонтаж</td>
            </tr>
            <tr class="hover:bg-paper-subtle/40">
              <td class="p-3 font-bold text-ink">Sn42Bi58</td>
              <td class="p-3">Sn42 Bi58</td>
              <td class="p-3 text-brand-orange font-semibold">138°C</td>
              <td class="p-3">138°C</td>
              <td class="p-3">Низкотемпературный монтаж термочувствительных LED/линз</td>
            </tr>
            <tr class="hover:bg-paper-subtle/40">
              <td class="p-3 font-bold text-ink">Сплав Розе</td>
              <td class="p-3">Bi50 Pb32 Sn18</td>
              <td class="p-3 text-brand-orange font-semibold">94°C</td>
              <td class="p-3">94°C</td>
              <td class="p-3">Лужение дорожек, демонтаж многовыводных микросхем</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

  </div>
</main>

<?php
get_footer();
