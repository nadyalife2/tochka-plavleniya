<?php
$page_title = "Инструменты и Калькуляторы — ТЧП";
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$extra_css = ['article.css'];
$extra_js  = ['flux-calc.js', 'solder-quiz.js', 'solder-table.js'];
include __DIR__ . '/includes/header.php';
?>

<!-- GEO JSON-LD Разметка для ИИ-поисковиков -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebApplication",
      "name": "Калькулятор расхода флюса — Точка Плавления",
      "applicationCategory": "EducationalApplication",
      "operatingSystem": "All",
      "description": "Интерактивный расчет необходимой дозировки канифольного (RMA-223) и безотмывочного (NC-559) флюса по площади платы в см²."
    },
    {
      "@type": "Dataset",
      "name": "Сводная таблица физических свойств припоев",
      "description": "Справочник температур плавления, химического состава и соответствия стандарту RoHS для ПОС-61, SAC305, Сплава Розе.",
      "keywords": ["ПОС-61", "SAC305", "Сплав Розе", "RoHS", "Температура плавления припоя"]
    }
  ]
}
</script>

<section class="article-hero" style="background:var(--accent-yellow);">
    <span class="tag">Верстак ТЧП</span>
    <h1 class="wavy-underline">Инструменты инженера</h1>
    <p style="font-family:var(--font-mono); font-weight:700; margin-top:1rem;">Калькулятор расхода флюса, интерактивный тест и таблица припоев.</p>
</section>

<!-- КАЛЬКУЛЯТОР ФЛЮСА -->
<div class="interactive-widget" id="calculator" style="margin-top:0;">
    <h2>🧮 01 / Калькулятор расхода флюса</h2>
    <p style="margin-top:0.5rem; color:var(--text-muted);">Укажите площадь вашей платы и тип используемой химии.</p>
    
    <div class="widget-form">
        <input type="number" id="flux-area" class="widget-input" placeholder="Площадь платы (см²)" min="1" aria-label="Площадь платы в квадратных сантиметрах">
        <select id="flux-type" class="widget-input" aria-label="Тип используемого флюса">
            <option value="rma">RMA-223 (канифольный)</option>
            <option value="nc">NC-559 (безотмывочный)</option>
        </select>
        <button id="calc-flux-btn" class="widget-button">Рассчитать дозировку ⚡</button>
    </div>
    <div id="flux-result" class="callout tip" style="display:none; margin-top:1.5rem;" aria-live="polite"></div>
</div>

<!-- ТЕСТ ПО ПАЙКЕ -->
<div class="article-content" id="quiz" style="margin-bottom:3rem;">
    <h2>🎯 02 / Практический тест по пайке</h2>
    <p>Проверьте свои знания верстачных ситуаций:</p>
    
    <div id="quiz-wrap" style="margin-top:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; font-family:var(--font-mono); font-size:0.85rem; color:var(--text-muted);">
            <span id="quiz-counter">Вопрос 1 из 10</span>
        </div>
        <div style="width:100%; height:6px; background:var(--bg); border:1px solid var(--border); border-radius:3px; overflow:hidden; margin-bottom:1.5rem;">
            <div id="quiz-progress" style="width:10%; height:100%; background:var(--accent-orange); transition:width 0.3s ease;"></div>
        </div>

        <div id="quiz-question" style="font-family:var(--font-serif); font-size:1.3rem; font-weight:800; margin-bottom:1.25rem;">
            Загрузка вопроса...
        </div>
        <div id="quiz-options" style="display:flex; flex-direction:column; gap:0.75rem;"></div>
        <button id="quiz-next" class="btn-primary" style="display:none; margin-top:1.5rem;">Следующий вопрос →</button>
    </div>

    <div id="quiz-result" style="display:none; margin-top:1.5rem; text-align:center; padding:2rem; background:var(--bg-card); border:2px solid var(--border); border-radius:8px;">
        <h3 style="font-size:1.5rem; margin-bottom:0.5rem;">Результат теста</h3>
        <div id="quiz-score" style="font-family:var(--font-mono); font-size:2.5rem; font-weight:800; color:var(--accent-orange); margin-bottom:1rem;">10 / 10</div>
        <p id="quiz-score-label" style="font-size:1rem; color:var(--text-muted); margin-bottom:1.5rem;"></p>
        <button id="quiz-restart" class="btn-primary">Пройти заново 🔄</button>
    </div>
</div>

<!-- ТАБЛИЦА ПРИПОЕВ -->
<div class="article-content" id="table">
    <h2>📊 03 / Таблица свойств припоев</h2>
    <input type="text" id="solder-search" class="widget-input" placeholder="Поиск по названию или составу…" aria-label="Поиск припоя по названию или составу" style="margin:1rem 0; width:100%;">

    <table id="solder-table" style="width:100%; border-collapse:collapse; margin-top:1rem;">
        <thead>
            <tr style="background:var(--accent-yellow);">
                <th style="padding:0.75rem; border:2px solid var(--border); text-align:left;">Марка</th>
                <th style="padding:0.75rem; border:2px solid var(--border); text-align:left;">Состав</th>
                <th style="padding:0.75rem; border:2px solid var(--border); text-align:left;">T° плавления</th>
                <th style="padding:0.75rem; border:2px solid var(--border); text-align:left;">RoHS</th>
            </tr>
        </thead>
        <tbody id="solder-rows" aria-live="polite">
            <tr>
                <td style="padding:0.75rem; border:2px solid var(--border);"><strong>ПОС-61</strong></td>
                <td style="padding:0.75rem; border:2px solid var(--border);">63% Sn, 37% Pb</td>
                <td style="padding:0.75rem; border:2px solid var(--border);">183°C</td>
                <td style="padding:0.75rem; border:2px solid var(--border);"><span class="tag" style="background:var(--accent-peach); color:var(--text-main); margin:0;">Свинец</span></td>
            </tr>
            <tr>
                <td style="padding:0.75rem; border:2px solid var(--border);"><strong>SAC305</strong></td>
                <td style="padding:0.75rem; border:2px solid var(--border);">96.5% Sn, 3% Ag, 0.5% Cu</td>
                <td style="padding:0.75rem; border:2px solid var(--border);">217°C</td>
                <td style="padding:0.75rem; border:2px solid var(--border);"><span class="tag" style="background:var(--accent-green); color:var(--text-main); margin:0;">RoHS OK</span></td>
            </tr>
            <tr>
                <td style="padding:0.75rem; border:2px solid var(--border);"><strong>Сплав Розе</strong></td>
                <td style="padding:0.75rem; border:2px solid var(--border);">50% Bi, 25% Pb, 25% Sn</td>
                <td style="padding:0.75rem; border:2px solid var(--border);">94°C</td>
                <td style="padding:0.75rem; border:2px solid var(--border);"><span class="tag" style="background:var(--accent-peach); color:var(--text-main); margin:0;">Демонтаж</span></td>
            </tr>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
