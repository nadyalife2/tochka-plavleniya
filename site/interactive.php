<?php
/**
 * interactive.php — Интерактивные инструменты инженера
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Инструменты и калькуляторы — Точка Плавления';
$page_desc    = 'Калькулятор расхода флюса, интерактивный тест по пайке и справочная таблица припоев.';
$current_page = 'interactive';
$extra_css    = '/assets/css/interactive.css';
$extra_js     = '/assets/js/flux-calc.js';
$extra_js2    = '/assets/js/solder-table.js';

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<div class="interactive-hero">
  <div class="hero-badge">◈ Инструменты инженера</div>
  <h1 class="interactive-h1">Верстак <span class="wavy">инженера</span></h1>
  <p class="interactive-sub">Калькуляторы расхода, проверка практических знаний и интерактивный табличник припоев. Всё работает прямо в браузере без серверов и регистрации.</p>
</div>

<!-- ── КАЛЬКУЛЯТОР ФЛЮСА ──────────────────────────── -->
<section class="tool-block" id="calculator">
  <div class="tool-label">[ Инструмент 01 ]</div>
  <h2 class="tool-h2">Калькулятор расхода флюса</h2>
  <p class="tool-desc">Укажите тип флюса, площадь вашей платы и характер монтажа — система рассчитает требуемый объём в миллилитрах и порекомендует способ нанесения.</p>

  <form class="calc-form" id="flux-calc-form" onsubmit="return false;">
    <div class="form-group">
      <label class="form-label" for="flux-type">Тип используемого флюса</label>
      <select class="form-select" id="flux-type" name="flux-type">
        <option value="">— Выберите из списка —</option>
        <option value="rma223">RMA-223 (канифольный, умеренно активный)</option>
        <option value="nc559">NC-559 (безотмывочный, высокий уровень активности)</option>
        <option value="flux_paste">Паяльная гель-паста</option>
        <option value="liquid">Жидкий канифольный/спиртовой флюс</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="board-area">Площадь платы или зоны монтажа (см²)</label>
      <input class="form-input" type="number" id="board-area" name="board-area" min="1" max="2000" placeholder="Например: 75">
    </div>
    <div class="form-group">
      <label class="form-label" for="comp-type">Тип монтажа</label>
      <select class="form-select" id="comp-type" name="comp-type">
        <option value="tht">THT (выводные компоненты / разводка)</option>
        <option value="smd">SMD (поверхностный монтаж 0805/0603/SOIC)</option>
        <option value="bga">BGA / QFN (микросхемы с центральным пятаком)</option>
        <option value="mix">Смешанный плотный монтаж</option>
      </select>
    </div>
    <button type="submit" class="btn-pill btn-dark">Рассчитать дозировку →</button>

    <div class="calc-result" id="flux-result">
      <div class="calc-result-label">Рекомендуемая дозировка</div>
      <div class="calc-result-value" id="result-value">—</div>
      <div class="calc-result-desc" id="result-desc"></div>
    </div>
  </form>
</section>

<!-- ── ТЕСТ ПО ПАЙКЕ ──────────────────────────────── -->
<section class="tool-block tool-block--alt" id="quiz">
  <div class="tool-label">[ Инструмент 02 ]</div>
  <h2 class="tool-h2">Тест на понимание пайки</h2>
  <p class="tool-desc">10 практических ситуаций из реальной работы за верстаком. Проверьте, готовы ли вы к сложным платам.</p>

  <div class="quiz-wrap" id="quiz-wrap">
    <div class="quiz-progress-bar-bg">
      <div class="quiz-progress-fill" id="quiz-progress" style="width: 0%"></div>
    </div>
    <div class="quiz-counter" id="quiz-counter">Вопрос 1 из 10</div>
    <div class="quiz-question" id="quiz-question">Загрузка вопросов...</div>
    <div class="quiz-options" id="quiz-options"></div>
    <div class="quiz-next">
      <button id="quiz-next" class="btn-pill btn-dark" style="margin-top: 16px;">Следующий вопрос →</button>
    </div>
  </div>

  <div class="quiz-result" id="quiz-result">
    <div class="quiz-score" id="quiz-score">0/10</div>
    <div class="quiz-score-label" id="quiz-score-label"></div>
    <button id="quiz-restart" class="btn-pill btn-dark">Пройти еще раз →</button>
  </div>

  <script src="/assets/js/solder-quiz.js"></script>
</section>

<!-- ── ТАБЛИЦА ПРИПОЕВ ────────────────────────────── -->
<section class="tool-block" id="table">
  <div class="tool-label">[ Инструмент 03 ]</div>
  <h2 class="tool-h2">Интерактивный справочник припоев</h2>
  <p class="tool-desc">Сортировка по температуре плавления, назначению и соответствию RoHS. Воспользуйтесь поиском для быстрой фильтрации.</p>

  <div class="solder-table-controls">
    <input type="search" class="solder-search" id="solder-search" placeholder="Поиск по названию, типу или назначению…">
  </div>

  <div class="solder-table-wrap">
    <table class="solder-table">
      <thead>
        <tr>
          <th data-sort="alloy">Сплав</th>
          <th data-sort="melt">T плавления, °C</th>
          <th data-sort="type">Тип</th>
          <th data-sort="use">Применение</th>
          <th data-sort="rohs">RoHS</th>
        </tr>
      </thead>
      <tbody id="solder-tbody"></tbody>
    </table>
  </div>
</section>

<?php require_once __DIR__ . '/includes/cookie-banner.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
