<?php
/**
 * Theme Setup & Functions for ТОЧКА ПЛАВЛЕНИЯ (site2)
 *
 * @package Site2
 * @version 2.1.0
 */

defined('ABSPATH') || exit;

// 1. Load helper functions if available
if (file_exists(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
}

// 2. Theme Setup
add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(800, 450, true);
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets'
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('editor-styles');
    add_editor_style('assets/css/article.css');

    register_nav_menus([
        'primary' => __('Основное меню', 'site2'),
        'footer'  => __('Меню в подвале', 'site2'),
    ]);
});

// 3. Register Block Styles & 15 Gutenberg Patterns
add_action('init', function () {
    // 3.1. Block Styles for Images
    if (function_exists('register_block_style')) {
        register_block_style('core/image', [
            'name'  => 'blueprint',
            'label' => __('Инженерный чертёж', 'site2'),
        ]);
        register_block_style('core/image', [
            'name'  => 'polaroid',
            'label' => __('Лабораторный полароид', 'site2'),
        ]);
        register_block_style('core/image', [
            'name'  => 'washi',
            'label' => __('Со скотчем (Washi)', 'site2'),
        ]);
    }

    // 3.2. Block Patterns Category
    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('tochka-plavleniya', [
            'label' => __('ТОЧКА ПЛАВЛЕНИЯ', 'site2'),
        ]);
    }

    // 3.3. Block Patterns (15 complete editorial patterns)
    if (function_exists('register_block_pattern')) {

        // 1. TL;DR Стикер по центру
        register_block_pattern('tochka-plavleniya/tldr-center', [
            'title'       => __('1. Стикер: Главный принцип (TL;DR по центру)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Крафтовый жёлтый стикер с ключевым выводом за 10 секунд', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout tldr"} -->
<div class="wp-block-group callout tldr"><!-- wp:paragraph {"className":"callout-title font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1"} -->
<p class="callout-title font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1">📌 ГЛАВНЫЙ ИНЖЕНЕРНЫЙ ПРИНЦИП // 10 СЕКУНД</p>
<!-- /wp:paragraph --><!-- wp:paragraph {"className":"font-semibold leading-relaxed m-0"} -->
<p class="font-semibold leading-relaxed m-0">Нагрев платы должен быть строго двухсторонним. Нижний подогрев 140–160°C обязателен для предотвращения коробления текстолита и отрыва BGA-пятаков.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 2. Стикер на полях с обтеканием
        register_block_pattern('tochka-plavleniya/tldr-aside', [
            'title'       => __('2. Стикер: Заметка на полях (сбоку от текста)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Компактная жёлтая заметка сбоку с мягким обтеканием основного текста', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout-aside"} -->
<div class="wp-block-group callout-aside"><!-- wp:paragraph {"className":"font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1"} -->
<p class="font-mono text-xs font-bold uppercase tracking-wider text-ink mb-1">🏷️ ЗАМЕТКА НА ПОЛЯХ</p>
<!-- /wp:paragraph --><!-- wp:paragraph {"className":"text-xs leading-relaxed m-0"} -->
<p class="text-xs leading-relaxed m-0">Безотмывочный флюс ROL0 не требует смывки на обычных цепях, но для сигнальных линий выше 100 МГц ультразвуковая отмывка строго обязательна.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 3. Техника безопасности (Safety First)
        register_block_pattern('tochka-plavleniya/safety-warning', [
            'title'       => __('3. Врезка: Техника безопасности (Safety First)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Оранжевый блок предостережения от термоудара и повреждения чипов', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout-safety"} -->
<div class="wp-block-group callout-safety"><!-- wp:paragraph {"className":"m-0"} -->
<p class="m-0">⚠️ <strong>ВНИМАНИЕ / ТЕРМОУДАР:</strong> Скорость подъема температуры не должна превышать <strong>2–3 °C в секунду</strong>. Превышение скорости приводит к растрескиванию кремниевого кристалла и расслоению BGA-подложки («эффект попкорна»).</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 4. Совет мастера (Pro Tip)
        register_block_pattern('tochka-plavleniya/pro-tip', [
            'title'       => __('4. Врезка: Совет мастера (Pro Tip)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Мятно-зеленая рамка с тонким практическим лайфхаком', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout-pro"} -->
<div class="wp-block-group callout-pro"><!-- wp:paragraph {"className":"m-0"} -->
<p class="m-0">💡 <strong>СОВЕТ МАСТЕРА:</strong> Перед демонтажем бессвинцового BGA-чипа разбавьте тугоплавкий припой по периметру сплавом ПОС-61 или Розе при 200°C. Это снизит эффективную температуру плавления на 30°C и спасет дорожки.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 5. Стандарт IPC / ГОСТ
        register_block_pattern('tochka-plavleniya/ipc-note', [
            'title'       => __('5. Врезка: Стандарт IPC-A-610 / ГОСТ', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Строгий нормативный блок со ссылкой на отраслевой регламент', 'site2'),
            'content'     => '<!-- wp:group {"className":"callout-ipc"} -->
<div class="wp-block-group callout-ipc"><!-- wp:paragraph {"className":"m-0"} -->
<p class="m-0">📜 <strong>СТАНДАРТ IPC-A-610G (Класс 3 / Надежность РЭА):</strong> Заполнение припоем металлизированного сквозного отверстия должно составлять не менее 75% толщины платы при угле смачивания галтели &lt; 90°.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 6. Картинка слева + Текст справа
        register_block_pattern('tochka-plavleniya/media-text-left', [
            'title'       => __('6. Иллюстрация слева + Текст справа', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Двухколоночный блок с фото детали и пояснительным текстом', 'site2'),
            'content'     => '<!-- wp:media-text {"mediaPosition":"left","mediaType":"image","className":"my-6"} -->
<div class="wp-block-media-text alignwide is-stacked-on-mobile my-6"><figure class="wp-block-media-text__media"><img src="" alt="Контроль пайки под микроскопом"/></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Контроль галтели под микроскопом</h3>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>Качественное паяное соединение имеет зеркальную поверхность с вогнутым мениском смачивания. Тусклая зернистая структура свидетельствует о перегреве или вибрации платы в момент кристаллизации припоя.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->',
        ]);

        // 7. Текст слева + Картинка справа
        register_block_pattern('tochka-plavleniya/media-text-right', [
            'title'       => __('7. Текст слева + Иллюстрация справа', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Зеркальный блок для чередования ритма в лонгриде', 'site2'),
            'content'     => '<!-- wp:media-text {"mediaPosition":"right","mediaType":"image","className":"my-6"} -->
<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile my-6"><figure class="wp-block-media-text__media"><img src="" alt="Посадка шаров BGA"/></figure><div class="wp-block-media-text__content"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Равномерность посадки BGA-шаров</h3>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>При оплавлении шаров через трафарет прямого нагрева держите сопло фена на высоте не менее 20 мм. Слишком сильный воздушный поток сдувает шары до момента смачивания пятака.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->',
        ]);

        // 8. Сравнение «БРАК vs НОРМА (ДО / ПОСЛЕ)»
        register_block_pattern('tochka-plavleniya/compare-defect-norm', [
            'title'       => __('8. Сравнение: БРАК vs НОРМА (ДО / ПОСЛЕ)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Две колонки с цветными бейджами для наглядного контроля дефектов пайки', 'site2'),
            'content'     => '<!-- wp:columns {"className":"compare-grid"} -->
<div class="wp-block-columns compare-grid"><!-- wp:column {"className":"compare-col"} -->
<div class="wp-block-column compare-col"><!-- wp:paragraph -->
<p><span class="badge-defect">✕ ДЕФЕКТ (БРАК)</span></p>
<!-- /wp:paragraph --><!-- wp:image {"className":"my-2"} -->
<figure class="wp-block-image my-2"><img src="" alt="Дефект пайки"/></figure>
<!-- /wp:image --><!-- wp:paragraph {"className":"text-xs text-ink-muted leading-relaxed"} -->
<p class="text-xs text-ink-muted leading-relaxed"><strong>Холодная пайка:</strong> тусклая зернистая поверхность, микротрещина по границе смачивания из-за недогрева или раннего перемещения платы.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --><!-- wp:column {"className":"compare-col"} -->
<div class="wp-block-column compare-col"><!-- wp:paragraph -->
<p><span class="badge-norm">✓ ГОСТ / IPC-A-610</span></p>
<!-- /wp:paragraph --><!-- wp:image {"className":"my-2"} -->
<figure class="wp-block-image my-2"><img src="" alt="Норма пайки"/></figure>
<!-- /wp:image --><!-- wp:paragraph {"className":"text-xs text-ink-muted leading-relaxed"} -->
<p class="text-xs text-ink-muted leading-relaxed"><strong>Норма Class 3:</strong> плавный вогнутый мениск, угол контакта &lt; 30°, полное смачивание контактной площадки без пор и каверн.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
        ]);

        // 9. Векторная схема термопрофиля
        register_block_pattern('tochka-plavleniya/thermal-profile-svg', [
            'title'       => __('9. График: Инженерная кривая термопрофиля (SVG)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Инженерная кривая 4 фаз нагрева с отметками Preheat, Soak, Reflow и Cooling', 'site2'),
            'content'     => '<!-- wp:html -->
<div class="sketch-frame p-4 sm:p-6 my-6 relative overflow-hidden bg-card border border-paper-border rounded-lg shadow-xs">
  <div class="font-hand text-xl font-bold text-ink mb-3 flex items-center gap-2">
    <span class="material-symbols-outlined text-lg text-accent">show_chart</span>
    <span>Схема нагрева: T°C / Время (сек) — SAC305</span>
  </div>
  <div class="w-full overflow-x-auto">
    <svg viewBox="0 0 540 220" width="100%" height="auto" fill="none" class="max-w-full">
      <rect width="540" height="220" rx="8" class="fill-paper-subtle stroke-paper-border" stroke-width="1.5"/>
      <line x1="40" y1="180" x2="500" y2="180" stroke="currentColor" stroke-opacity="0.2" stroke-width="1.5" stroke-dasharray="4 4"/>
      <line x1="40" y1="110" x2="500" y2="110" stroke="currentColor" stroke-opacity="0.2" stroke-width="1" stroke-dasharray="3 4"/>
      <line x1="40" y1="50" x2="500" y2="50" stroke="currentColor" stroke-opacity="0.2" stroke-width="1" stroke-dasharray="2 4"/>
      <path d="M40 180 Q120 160 180 110 T320 50 T440 80 T500 180" stroke="var(--color-ink,#1c1917)" stroke-width="3" stroke-linecap="round" fill="none"/>
      <circle cx="180" cy="110" r="6" fill="#fde047" stroke="var(--color-ink,#1c1917)" stroke-width="2"/>
      <circle cx="320" cy="50" r="6" fill="var(--color-accent,#ea580c)" stroke="var(--color-ink,#1c1917)" stroke-width="2"/>
      <circle cx="440" cy="80" r="6" fill="#2dd4bf" stroke="var(--color-ink,#1c1917)" stroke-width="2"/>
      <text x="110" y="96" font-family="Caveat,cursive" font-size="18" font-weight="700" fill="var(--color-ink,#1c1917)">Preheat (150°C)</text>
      <text x="270" y="36" font-family="Caveat,cursive" font-size="19" font-weight="700" fill="var(--color-accent,#ea580c)">Peak Reflow (242°C) ★</text>
      <text x="430" y="115" font-family="Caveat,cursive" font-size="18" font-weight="700" fill="var(--color-ink,#1c1917)">Cooling (6°C/s)</text>
      <text x="40" y="202" font-family="JetBrains Mono,monospace" font-size="11" fill="currentColor" fill-opacity="0.6">0s</text>
      <text x="165" y="202" font-family="JetBrains Mono,monospace" font-size="11" fill="currentColor" fill-opacity="0.6">90s (Soak)</text>
      <text x="305" y="202" font-family="JetBrains Mono,monospace" font-size="11" fill="currentColor" fill-opacity="0.6">210s (TAL)</text>
      <text x="470" y="202" font-family="JetBrains Mono,monospace" font-size="11" fill="currentColor" fill-opacity="0.6">300s</text>
    </svg>
  </div>
  <div class="mt-2 text-[11px] font-mono text-ink-muted flex items-center justify-between">
    <span>Рис. Нормированный термопрофиль SAC305 (Liquidus 217°C)</span>
    <span>IPC/JEDEC J-STD-020D</span>
  </div>
</div>
<!-- /wp:html -->',
        ]);

        // 10. Пошаговый регламент операции (100% валидный синтаксис)
        register_block_pattern('tochka-plavleniya/step-procedure', [
            'title'       => __('10. Пошаговый регламент операции (Шаги 1–4)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Карточки пошагового технологического процесса с цветными бейджами', 'site2'),
            'content'     => '<!-- wp:group {"className":"step-card"} -->
<div class="wp-block-group step-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><span class="step-badge step-badge-yellow">ШАГ 01</span> Подготовка и преднагрев платы</h4>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>Зафиксируйте плату на термостоле. Разогрейте плату снизу со скоростью не более 2°C/сек до температуры 140–150°C для равномерного распределения тепла.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><!-- wp:group {"className":"step-card"} -->
<div class="wp-block-group step-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><span class="step-badge step-badge-orange">ШАГ 02</span> Нанесение флюса и оплавление</h4>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>Нанесите тонкий равномерный слой вязкого гелевого флюса ROL0. Включите верхний фен (330–350°C на индикаторе) и прогревайте чип круговыми движениями на высоте 20 мм.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><!-- wp:group {"className":"step-card"} -->
<div class="wp-block-group step-card"><!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><span class="step-badge step-badge-mint">ШАГ 03</span> Контролируемое охлаждение</h4>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>Не перемещайте плату до полного затвердевания припоя (ниже 180°C). Быстрое охлаждение воздухом формирует мелкозернистую прочную структуру галтели.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 11. Что понадобится (Оснастка и инструмент)
        register_block_pattern('tochka-plavleniya/tools-needed', [
            'title'       => __('11. Карточка: Оснастка и материалы (Что понадобится)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Стартовый блок со списком оборудования и расходников', 'site2'),
            'content'     => '<!-- wp:group {"className":"tools-box"} -->
<div class="wp-block-group tools-box"><!-- wp:heading {"level":4,"className":"font-mono text-xs uppercase tracking-wider text-ink pb-2 border-b border-paper-border flex items-center justify-between"} -->
<h4 class="wp-block-heading font-mono text-xs uppercase tracking-wider text-ink pb-2 border-b border-paper-border flex items-center justify-between"><span>🛠️ ТЕХНОЛОГИЧЕСКАЯ ОСНАСТКА</span><span class="text-accent text-[10px] font-bold">СПЕЦИФИКАЦИЯ</span></h4>
<!-- /wp:heading --><!-- wp:paragraph {"className":"text-xs font-mono leading-loose mt-2"} -->
<p class="text-xs font-mono leading-loose mt-2">▸ <strong>Термофен:</strong> турбинный/компрессорный с круглым соплом 10–12 мм<br>▸ <strong>Нижний подогрев:</strong> ИК-термостол с контролем термопары K-типа<br>▸ <strong>Флюс:</strong> безотмывочный ROL0 (Martin / SP-55 / NC-559-ASM)<br>▸ <strong>Оплётка:</strong> медная деоксидированная 2.5 мм (Goot Wick / Chemtronics)</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 12. Чек-лист готовности перед пайкой
        register_block_pattern('tochka-plavleniya/checklist-preflight', [
            'title'       => __('12. Чек-лист: Инженерный контроль перед операцией', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Список обязательных проверок с отметками готовности', 'site2'),
            'content'     => '<!-- wp:group {"className":"checklist-box"} -->
<div class="wp-block-group checklist-box"><!-- wp:heading {"level":4,"className":"font-mono text-xs uppercase tracking-wider font-bold text-ink mb-3"} -->
<h4 class="wp-block-heading font-mono text-xs uppercase tracking-wider font-bold text-ink mb-3">✅ ВХОДНОЙ ИНЖЕНЕРНЫЙ КОНТРОЛЬ ПЕРЕД ВКЛЮЧЕНИЕМ</h4>
<!-- /wp:heading --><!-- wp:paragraph {"className":"checklist-item"} -->
<p class="checklist-item"><span class="check-icon">[✓]</span> <strong>Влагосодержание чипов:</strong> микросхемы просушены в печи при 110°C (не менее 12 часов), индикатор HIC в норме.</p>
<!-- /wp:paragraph --><!-- wp:paragraph {"className":"checklist-item"} -->
<p class="checklist-item"><span class="check-icon">[✓]</span> <strong>Антистатическая защита ESD:</strong> антистатический коврик и браслет заземлены, сопротивление &lt; 1 МОм.</p>
<!-- /wp:paragraph --><!-- wp:paragraph {"className":"checklist-item"} -->
<p class="checklist-item"><span class="check-icon">[✓]</span> <strong>Калибровка термопары:</strong> датчик нижнего подогрева установлен на текстолит в 10 мм от зоны пайки.</p>
<!-- /wp:paragraph --><!-- wp:paragraph {"className":"checklist-item"} -->
<p class="checklist-item"><span class="check-icon">[✓]</span> <strong>Срок годности флюса:</strong> вязкость флюса нормальная, расслоения геля и осадка не наблюдается.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);

        // 13. BOM-таблица параметров
        register_block_pattern('tochka-plavleniya/bom-table', [
            'title'       => __('13. Таблица: Параметры режимов (BOM-table)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Инженерная спецификация режимов, температур и допусков', 'site2'),
            'content'     => '<!-- wp:table {"className":"bom-table"} -->
<figure class="wp-block-table bom-table"><table class="has-fixed-layout"><thead><tr><th>Параметр / Инструмент</th><th>Режим техпроцесса</th><th>Допуск / Спецификация</th></tr></thead><tbody><tr><td>Термофен (сопло 10 мм)</td><td>330–350 °C на индикаторе</td><td>±5 °C на выходе сопла</td></tr><tr><td>Нижний подогрев платы</td><td>140–160 °C на текстолите</td><td>Контроль термопарой K-типа</td></tr><tr><td>Флюс для пайки</td><td>ROL0 / RMA безотмывочный</td><td>Вязкость 35–45 Па·с</td></tr><tr><td>Время расплава (TAL)</td><td>45–60 секунд</td><td>Не более 75 секунд (IPC)</td></tr></tbody></table></figure>
<!-- /wp:table -->',
        ]);

        // 14. FAQ / Блок частых вопросов
        register_block_pattern('tochka-plavleniya/faq-accordion', [
            'title'       => __('14. Блок: Частые вопросы и ошибки (FAQ)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Карточки вопросов и ответов для читателей и поисковых сниппетов', 'site2'),
            'content'     => '<!-- wp:group {"className":"space-y-3 my-6"} -->
<div class="wp-block-group space-y-3 my-6"><!-- wp:group {"className":"faq-item"} -->
<div class="wp-block-group faq-item"><!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mb-1"} -->
<h4 class="wp-block-heading text-base font-bold text-ink mb-1">❓ Какая максимальная температура допустима для бессвинцовой пайки BGA?</h4>
<!-- /wp:heading --><!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} -->
<p class="text-sm text-ink-muted leading-relaxed m-0">По стандарту IPC/JEDEC J-STD-020 абсолютный пиковый предел составляет 250°C (не дольше 10 секунд). Оптимальный рабочий пик оплавления SAC305 — ровно 238–242°C.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --><!-- wp:group {"className":"faq-item"} -->
<div class="wp-block-group faq-item"><!-- wp:heading {"level":4,"className":"text-base font-bold text-ink mb-1"} -->
<h4 class="wp-block-heading text-base font-bold text-ink mb-1">❓ Сколько секунд припой должен находиться в жидком состоянии (TAL)?</h4>
<!-- /wp:heading --><!-- wp:paragraph {"className":"text-sm text-ink-muted leading-relaxed m-0"} -->
<p class="text-sm text-ink-muted leading-relaxed m-0">Время над ликвидусом (TAL) должно составлять от 45 до 60 секунд. Меньше 40 с — риск несмачивания. Дольше 90 с — интерметаллический слой становится слишком толстым и хрупким.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->',
        ]);

        // 15. Заметка на полях со стрелочкой (Marginalia)
        register_block_pattern('tochka-plavleniya/marginalia-note', [
            'title'       => __('15. Заметка на полях (Marginalia со стрелкой)', 'site2'),
            'categories'  => ['tochka-plavleniya'],
            'description' => __('Фирменная рукописная пометка от руки со скетчевой стрелочкой', 'site2'),
            'content'     => '<!-- wp:group {"className":"marginalia-box"} -->
<div class="wp-block-group marginalia-box"><!-- wp:paragraph {"className":"marginalia-text"} -->
<p class="marginalia-text">Важная деталь: никогда не дуйте воздухом прямо в центр кристалла! Направляйте фен по спирали от периметра к центру, иначе кремний треснет от перепада температур.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        ]);
    }
});

// 4. Fail-safe Routing & Fallbacks (Zero-404 Architecture)
add_action('template_redirect', function () {
    if (is_404()) {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = trim($uri, '/');

        // 4.1. Root URL
        if ($path === '' || empty($path)) {
            status_header(200);
            require __DIR__ . '/index.php';
            exit;
        }

        // 4.2. Interactive Workbench
        if ($path === 'interactive' || $path === 'interactive.php') {
            status_header(200);
            require __DIR__ . '/page-interactive.php';
            exit;
        }

        // 4.3. Legal Pages
        if ($path === 'privacy' || $path === 'privacy.php') {
            status_header(200);
            require __DIR__ . '/privacy.php';
            exit;
        }
        if ($path === 'terms' || $path === 'terms.php') {
            status_header(200);
            require __DIR__ . '/terms.php';
            exit;
        }
        if ($path === 'cookies' || $path === 'cookies.php') {
            status_header(200);
            require __DIR__ . '/cookies.php';
            exit;
        }
    }
});

// 5. Disable WordPress comments (Zero-PII / 152-ФЗ compliance)
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);
add_filter('comments_array', '__return_empty_array', 10, 2);

// 6. Подключение модуля импорта базы знаний и регламентов ТЧП
require_once __DIR__ . '/includes/demo-importer.php';
