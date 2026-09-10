<?php
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/functions.php';

// Select article
$slug = $_GET['slug'] ?? 'temperaturnye-profili';
$article = get_article_by_slug($slug);
if (!$article) { 
    $article = $articles[0]; 
}

// Related articles (pick 3 other articles)
$related_articles = array_filter($articles, function($item) use ($article) {
    return $item['id'] !== $article['id'];
});
$related_articles = array_slice($related_articles, 0, 3);

// FAQ data for Schema.org & Accordion
$faq_items = [
    "Какая максимальная температура допустима для бессвинцовой пайки BGA?" => "Для бессвинцовых припоев группы SAC305 (Sn96.5Ag3.0Cu0.5) пиковая температура на поверхности чипа должна находиться строго в диапазоне 235°C – 245°C. Нагрев свыше 250°C ведет к разрушению кремниевого кристалла и необратимой деламинации (вздутию) текстолита.",
    "Сколько секунд припой должен находиться в расплавленном состоянии (TAL)?" => "Оптимальное время над точкой ликвидуса (Time Above Liquidus, TAL) для SAC305 (217°C) составляет от 45 до 75 секунд. За это время флюс полностью удаляет окислы и формируется прочный интерметаллический слой толщиной 1–2 мкм.",
    "Как избежать эффекта 'попкорна' при пайке влажных микросхем?" => "Микросхемы, хранившиеся вне герметичной влагозащитной упаковки (MSL 3 и выше), перед монтажом необходимо просушить в термошкафу при температуре 100°C – 125°C в течение 8–24 часов для постепенного удаления влаги из полимерного компаунда.",
    "Какой флюс использовать для реболлинга BGA: RMA или No-Clean?" => "Для прецизионного реболлинга рекомендуется использовать среднеактивные гелевые флюсы класса ROL0 / REL0 (No-Clean) с высокой липкостью (tackiness), которые надежно удерживают шарики и не кипят при нагреве."
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title><?= e($article['title']) ?> | Точка Плавления</title>
<meta name="description" content="<?= e($article['excerpt']) ?>">

<!-- OpenGraph Meta -->
<meta property="og:type" content="article">
<meta property="og:title" content="<?= e($article['title']) ?>">
<meta property="og:description" content="<?= e($article['excerpt']) ?>">
<meta property="og:site_name" content="Точка Плавления">

<!-- Tailwind & Fonts -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@700;900&family=IBM+Plex+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

<!-- Theme Custom Styles -->
<link rel="stylesheet" href="assets/css/article-pro.css">

<script id="tailwind-config">
tailwind.config = {
    theme: {
        extend: {
            fontFamily: {
                sans: ["Inter", "system-ui", "-apple-system", "sans-serif"],
                mono: ["IBM Plex Mono", "monospace"],
                logo: ["Hanken Grotesk", "sans-serif"]
            }
        }
    }
};
</script>

<!-- Schema.org JSON-LD Microdata -->
<?= render_article_schema($article, $faq_items) ?>
</head>

<body class="min-h-screen flex flex-col bg-white text-gray-900 antialiased selection:bg-orange-100 selection:text-orange-900">

<!-- 1. Полоса прогресса чтения -->
<div id="reading-progress"></div>

<!-- 2. Шапка сайта (TochkiCamp style с оптимизированным логотипом) -->
<header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-14 flex items-center justify-between">
        <a href="index.php" class="brand-logo">
            Точка<span>.</span>Плавления
        </a>

        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-gray-600">
            <a href="index.php" class="hover:text-orange-600 transition-colors">Статьи</a>
            <a href="interactive.php" class="hover:text-orange-600 transition-colors">Калькулятор флюсов</a>
            <a href="interactive.php#solder-table" class="hover:text-orange-600 transition-colors">Таблица припоев</a>
        </nav>

        <div class="flex items-center gap-3">
            <a href="interactive.php" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 bg-orange-50 text-orange-700 hover:bg-orange-100 border border-orange-200 rounded text-xs font-semibold transition-colors">
                <span class="material-symbols-outlined text-xs">construction</span>
                Верстак
            </a>
            <a href="index.php" class="text-xs font-medium text-gray-500 hover:text-gray-900 transition-colors">
                ← К гайдам
            </a>
        </div>
    </div>
</header>

<!-- 3. Основной контейнер статьи (760px текст + сайдбар) -->
<main class="flex-grow max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 w-full">

    <!-- Хлебные крошки -->
    <nav class="flex items-center gap-1.5 text-xs text-gray-400 mb-5 flex-wrap" aria-label="Breadcrumb">
        <a href="index.php" class="hover:text-gray-700 transition-colors font-mono">Главная</a>
        <span>→</span>
        <a href="index.php?tag=<?= e($article['tag_key'] ?? 'bga') ?>" class="hover:text-gray-700 transition-colors font-mono">
            <?= e($article['category'] ?? 'BGA & SMD') ?>
        </a>
        <span>→</span>
        <span class="text-gray-700 truncate max-w-xs sm:max-w-md font-mono"><?= e($article['title']) ?></span>
    </nav>

    <!-- Заголовок и метаданные (TochkiCamp Hero) -->
    <header class="max-w-3xl mb-6">
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight text-gray-950 leading-[1.2] mb-3">
            <?= e($article['title']) ?>
        </h1>

        <!-- Мета-строка в стиле TochkiCamp -->
        <p class="text-xs text-gray-500 font-mono mb-4">
            <?= e($article['author'] ?? 'Инженер Лаборатории ТЧП') ?> · Обновлено <time datetime="<?= e($article['date'] ?? '2026-08-20') ?>"><?= e($article['date'] ?? '20 августа 2026') ?></time>
        </p>

        <!-- Лид-абзац статьи -->
        <p class="gd-lead">
            <?= e($article['excerpt']) ?> Разбираем, почему стандартная пайка «по цифрам на табло фена» гарантированно убивает многослойные платы, как выставить 4 фазы термопрофиля по стандарту IPC/JEDEC и не допустить коробления текстолита.
        </p>

        <!-- Блок "Как читать" в стиле TochkiCamp -->
        <div class="gd-disclaimer">
            <span class="gd-disclaimer__label">Как читать этот регламент</span>
            <p>Это практический производственный регламент. Если вы настраиваете термопрофиль под конкретный чип, сразу переходите к <a href="#step-2">симулятору 4 фаз</a> или <a href="#step-3">температурным окнам сплавов</a>. Все значения температур верифицированы контактными термопарами K-типа.</p>
        </div>
    </header>

    <!-- Двухколоночный макет: Статья + Липкий TOC -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

        <!-- Основной текст статьи (8 из 12 колонок) -->
        <article class="lg:col-span-8 text-gray-800 text-[16px] leading-[1.75] space-y-6">

            <!-- Шаг 1: Теплоемкость и датчики -->
            <section id="step-1">
                <h2 class="text-lg sm:text-xl font-bold text-gray-950 tracking-tight mb-3">
                    Шаг 1. Теплоемкость текстолита и почему фен всегда врет
                </h2>
                <p>
                    Стандартная ошибка при пайке сложных чипов — выставлять на станции фиксированные $350^\circ\text{C}$ и греть плату сверху. Датчик станции измеряет температуру спирали внутри фена, а на поверхности текстолита и тем более под корпусом BGA температура оказывается на <strong>80–120°C ниже</strong>.
                </p>

                <!-- Цитата-инсайт в стиле TochkiCamp -->
                <blockquote class="gd-quote">
                    <p>Температура на сопле фена не имеет ничего общего с температурой припоя под чипом. Без замера на плате вы паяете вслепую.</p>
                </blockquote>

                <p>
                    Плата состоит из 6–12 слоев FR-4 и сплошных полигонов питания/земли (GND). Медь моментально отводит тепло от точки нагрева. Попытка прогреть только верх чипа создает мощный тепловой градиент: текстолит изгибается «лодочкой» (<span class="tag-mono">warpage</span>), а центральные шарики слипаются в короткое замыкание.
                </p>

                <!-- Инлайн-параметры для копирования -->
                <div class="flex flex-wrap items-center gap-2 pt-2">
                    <span class="text-xs font-mono text-gray-400">Быстрые пороги:</span>
                    <button class="param-pill" data-copy="217°C" title="Нажмите для копирования">
                        <span>Ликвидус SAC305:</span>
                        <span class="text-orange-600 font-bold">217°C</span>
                    </button>
                    <button class="param-pill" data-copy="183°C" title="Нажмите для копирования">
                        <span>Эвтектика ПОС-61:</span>
                        <span class="text-orange-600 font-bold">183°C</span>
                    </button>
                    <button class="param-pill" data-copy="45-75 сек" title="Нажмите для копирования">
                        <span>Время TAL:</span>
                        <span class="text-orange-600 font-bold">45–75с</span>
                    </button>
                </div>
            </section>

            <hr class="gd-divider">

            <!-- Шаг 2: Симулятор 4 фаз нагрева -->
            <section id="step-2">
                <h2 class="text-lg sm:text-xl font-bold text-gray-950 tracking-tight mb-3">
                    Шаг 2. Четыре фазы кривой пайки (Интерактивный расчет)
                </h2>
                <p>
                    Правильный термопрофиль по стандарту <span class="tag-mono">J-STD-020D</span> сводится к четырехступенчатому циклу:
                </p>

                <!-- Карта этапов в стиле TochkiCamp -->
                <div class="gd-map" role="img" aria-label="Карта фаз термопрофиля">
                    <div class="gd-map__cell">
                        <span class="gd-map__verb">1. Прогрев (Preheat)</span>
                        <span class="gd-map__what">Плавный подъем до 150°C со скоростью 1–3°C/сек</span>
                    </div>
                    <div class="gd-map__cell">
                        <span class="gd-map__verb">2. Активация (Soak)</span>
                        <span class="gd-map__what">Выравнивание температур и удаление оксидов 150–200°C</span>
                    </div>
                    <div class="gd-map__cell">
                        <span class="gd-map__verb">3. Оплавление (Reflow)</span>
                        <span class="gd-map__what">Пик 235–245°C, время TAL над ликвидусом 45–75 сек</span>
                    </div>
                    <div class="gd-map__cell">
                        <span class="gd-map__verb">4. Охлаждение (Cooling)</span>
                        <span class="gd-map__what">Контролируемый спад 2–4°C/сек для мелкозернистой галтели</span>
                    </div>
                </div>
                <p class="gd-mock__cap">Инженерная модель 4 фаз пайки. Рассчитайте параметры в симуляторе ниже:</p>

                <!-- Интерактивный виджет симулятора -->
                <div id="thermal-simulator-widget"></div>
            </section>

            <hr class="gd-divider">

            <!-- Шаг 3: Температурные окна сплавов -->
            <section id="step-3">
                <h2 class="text-lg sm:text-xl font-bold text-gray-950 tracking-tight mb-3">
                    Шаг 3. Температурные окна основных паяльных сплавов
                </h2>
                <p>
                    Подбирайте температурный коридор в зависимости от металлургии используемого припоя:
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 my-4">
                    
                    <!-- SAC305 -->
                    <div class="alloy-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-mono text-gray-400 uppercase block">Бессвинцовый стандарт</span>
                                <h4 class="font-bold text-gray-900 text-sm">SAC305</h4>
                                <span class="text-xs text-gray-500 font-mono">Sn96.5 Ag3.0 Cu0.5</span>
                            </div>
                            <div class="text-right">
                                <span class="temp-badge">217°C</span>
                                <span class="text-[10px] text-gray-400 block font-mono">ликвидус</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                            <span>Окно пайки: <strong>235°C – 245°C</strong></span>
                            <button class="text-orange-600 hover:text-orange-700 font-mono text-[11px]" data-copy="217°C SAC305: 235-245°C">копировать</button>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1.5 leading-snug">
                            Заводской монтаж BGA, современные материнские платы и видеокарты.
                        </p>
                    </div>

                    <!-- ПОС-61 -->
                    <div class="alloy-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-mono text-gray-400 uppercase block">Свинцовый эвтектик</span>
                                <h4 class="font-bold text-gray-900 text-sm">ПОС-61 / Sn63Pb37</h4>
                                <span class="text-xs text-gray-500 font-mono">Sn63 Pb37</span>
                            </div>
                            <div class="text-right">
                                <span class="temp-badge text-gray-900">183°C</span>
                                <span class="text-[10px] text-gray-400 block font-mono">эвтектика</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                            <span>Окно пайки: <strong>210°C – 220°C</strong></span>
                            <button class="text-orange-600 hover:text-orange-700 font-mono text-[11px]" data-copy="183°C ПОС-61: 210-220°C">копировать</button>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1.5 leading-snug">
                            Сервисный ремонт, реболлинг на свинец, мягкая текучесть и зеркальная галтель.
                        </p>
                    </div>

                    <!-- Sn42Bi58 -->
                    <div class="alloy-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-mono text-gray-400 uppercase block">Низкотемпературный</span>
                                <h4 class="font-bold text-gray-900 text-sm">Sn42Bi58</h4>
                                <span class="text-xs text-gray-500 font-mono">Sn42 Bi58</span>
                            </div>
                            <div class="text-right">
                                <span class="temp-badge text-blue-600">138°C</span>
                                <span class="text-[10px] text-gray-400 block font-mono">эвтектика</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                            <span>Окно пайки: <strong>165°C – 175°C</strong></span>
                            <button class="text-orange-600 hover:text-orange-700 font-mono text-[11px]" data-copy="138°C Sn42Bi58: 165-175°C">копировать</button>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1.5 leading-snug">
                            Монтаж пластиковых FPC-разъемов, OLED-дисплеев и термочувствительных датчиков.
                        </p>
                    </div>

                    <!-- Сплав Розе -->
                    <div class="alloy-card">
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <span class="text-[10px] font-mono text-gray-400 uppercase block">Сверхнизкоплавкий</span>
                                <h4 class="font-bold text-gray-900 text-sm">Сплав Розе</h4>
                                <span class="text-xs text-gray-500 font-mono">Bi50 Pb32 Sn18</span>
                            </div>
                            <div class="text-right">
                                <span class="temp-badge text-emerald-600">96°C</span>
                                <span class="text-[10px] text-gray-400 block font-mono">плавление</span>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                            <span>Окно пайки: <strong>130°C – 140°C</strong></span>
                            <button class="text-orange-600 hover:text-orange-700 font-mono text-[11px]" data-copy="96°C Сплав Розе: 130-140°C">копировать</button>
                        </div>
                        <p class="text-[11px] text-gray-500 mt-1.5 leading-snug">
                            Исключительно для безопасного демонтажа чипов (разбавление тугоплавкого припоя).
                        </p>
                    </div>

                </div>
            </section>

            <hr class="gd-divider">

            <!-- Шаг 4: Типовые ошибки и защита от брака -->
            <section id="step-4">
                <h2 class="text-lg sm:text-xl font-bold text-gray-950 tracking-tight mb-3">
                    Шаг 4. Практические нюансы: термопары, влага и деламинация
                </h2>

                <div class="note-editorial">
                    <strong>Точка замера температуры:</strong> Закрепляйте термопару типа K каптоновым скотчем непосредственно возле корпуса микросхемы на поверхности платы. Только это дает объективную температуру в зоне пайки.
                </div>

                <div class="note-editorial note-warning">
                    <strong>Влажность микросхем (MSL 3):</strong> Если BGA-компонент лежал на воздухе дольше 72 часов, влага в полимере закипает при 230°C и распирает корпус изнутри. Обязательно сушите чип 12–24 часа при 100–110°C перед пайкой.
                </div>

                <div class="note-editorial note-danger">
                    <strong>Критическая скорость нагрева:</strong> Скорость нарастания температуры быстрее 2.5–3.0°C/сек приводит к расслоению стеклотекстолита (деламинации) и обрыву внутренних переходных отверстий в плате.
                </div>
            </section>

            <!-- Блок "Что запомнить" (TochkiCamp Summary Style) -->
            <div class="takeaways-box">
                <h3>
                    <span class="text-orange-600 font-mono">■</span>
                    Что запомнить
                </h3>
                <ul class="space-y-2 text-xs sm:text-sm text-gray-700 list-disc list-inside leading-relaxed">
                    <li>Фен без нижнего преднагревателя гарантированно изгибает многослойную плату.</li>
                    <li>Всегда фиксируйте термопару на плате — температура на выходе сопла фена завышена на 80–100°C.</li>
                    <li>Оптимальное время над ликвидусом (TAL) для SAC305 составляет строго 45–75 секунд.</li>
                    <li>Бессвинцовая пайка требует пика 235–245°C; нагрев свыше 250°C разрушает кристалл и плату.</li>
                    <li>Влажные BGA-микросхемы перед монтажом обязательно требуют просушки 12 часов при 100°C.</li>
                </ul>
            </div>

            <!-- Раздел FAQ -->
            <section id="faq">
                <h2 class="text-lg sm:text-xl font-bold text-gray-950 tracking-tight mb-3">
                    Частые вопросы
                </h2>
                <div>
                    <?php foreach ($faq_items as $question => $answer): ?>
                        <div class="faq-clean">
                            <div class="faq-clean-header">
                                <span class="pr-2"><?= e($question) ?></span>
                                <span class="material-symbols-outlined faq-clean-icon text-sm">expand_more</span>
                            </div>
                            <div class="faq-clean-body">
                                <p><?= e($answer) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- CTA Playbook блок (TochkiCamp Style) -->
            <div class="p-6 bg-gray-50 border border-gray-200 rounded-lg my-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <h4 class="font-bold text-sm text-gray-900">Инженерный справочник по пайке BGA</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Таблицы температурных профилей, допуски IPC-A-610 и подбор флюсов в Telegram.</p>
                </div>
                <a href="https://t.me/" target="_blank" rel="noopener" class="px-4 py-2 bg-gray-900 hover:bg-black text-white text-xs font-semibold rounded transition-colors whitespace-nowrap">
                    Забрать в Telegram →
                </a>
            </div>

            <!-- Связанные статьи -->
            <div class="pt-6 border-t border-gray-200">
                <h3 class="text-sm font-mono uppercase tracking-wider text-gray-400 font-semibold mb-4">
                    Другие материалы
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?php foreach ($related_articles as $rel): ?>
                        <a href="article.php?slug=<?= e($rel['slug']) ?>" class="p-3.5 bg-white hover:bg-gray-50 border border-gray-200 rounded-lg transition-colors flex flex-col justify-between group">
                            <div>
                                <span class="text-[10px] font-mono font-medium text-orange-600 block mb-1">
                                    <?= e($rel['category'] ?? 'Материал') ?>
                                </span>
                                <h5 class="font-medium text-xs text-gray-900 group-hover:text-orange-600 transition-colors line-clamp-2">
                                    <?= e($rel['title']) ?>
                                </h5>
                            </div>
                            <span class="text-[11px] font-mono text-gray-400 mt-2">
                                Читать →
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </article>

        <!-- Сайдбар (4 из 12) с липким оглавлением TochkiCamp -->
        <aside class="lg:col-span-4 space-y-6">

            <div class="toc-sidebar">
                <div class="text-[11px] font-mono font-semibold text-gray-400 uppercase tracking-wider mb-2.5">
                    Содержание
                </div>
                <nav class="space-y-0.5">
                    <a href="#step-1" class="toc-link">Шаг 1. Теплоемкость и датчики</a>
                    <a href="#step-2" class="toc-link">Шаг 2. Четыре фазы кривой</a>
                    <a href="#step-3" class="toc-link">Шаг 3. Температурные окна</a>
                    <a href="#step-4" class="toc-link">Шаг 4. Практика и защита от брака</a>
                    <a href="#faq" class="toc-link">Частые вопросы</a>
                </nav>
            </div>

            <!-- Краткая справка -->
            <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg text-xs">
                <span class="font-semibold text-gray-900 block mb-1">Лаборатория ТЧП</span>
                <p class="text-gray-500 mb-3 leading-relaxed">
                    Практические заметки и температурные карты в нашем канале.
                </p>
                <a href="https://t.me/" target="_blank" rel="noopener" class="inline-block w-full text-center py-1.5 px-3 bg-white hover:bg-gray-100 border border-gray-300 rounded font-medium text-gray-800 transition-colors">
                    Канал в Telegram
                </a>
            </div>

        </aside>

    </div>

</main>

<!-- Мобильное оглавление -->
<button id="mobile-toc-fab" class="mobile-toc-fab">
    <span class="material-symbols-outlined text-xs">list</span>
    <span>Содержание</span>
</button>

<div id="mobile-toc-drawer" class="fixed inset-0 bg-black/40 z-[1000] hidden flex items-end">
    <div class="bg-white border-t border-gray-200 w-full max-h-[70vh] overflow-y-auto p-5 rounded-t-xl shadow-xl">
        <div class="flex items-center justify-between pb-3 border-b border-gray-200 mb-3">
            <span class="font-bold text-sm text-gray-900">Содержание</span>
            <button id="mobile-toc-close" class="text-gray-400 hover:text-gray-900 text-sm">✕</button>
        </div>
        <nav class="space-y-1 text-sm">
            <a href="#step-1" class="block p-2 text-gray-700 hover:bg-gray-50 rounded">Шаг 1. Теплоемкость и датчики</a>
            <a href="#step-2" class="block p-2 text-gray-700 hover:bg-gray-50 rounded">Шаг 2. Четыре фазы кривой</a>
            <a href="#step-3" class="block p-2 text-gray-700 hover:bg-gray-50 rounded">Шаг 3. Температурные окна</a>
            <a href="#step-4" class="block p-2 text-gray-700 hover:bg-gray-50 rounded">Шаг 4. Практика и защита от брака</a>
            <a href="#faq" class="block p-2 text-gray-700 hover:bg-gray-50 rounded">Частые вопросы</a>
        </nav>
    </div>
</div>

<div id="copy-toast">Скопировано в буфер!</div>

<!-- Подвал сайта -->
<footer class="bg-gray-50 border-t border-gray-200 mt-16 py-8 text-xs text-gray-500">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <span class="font-semibold text-gray-900">Точка Плавления</span>
            <span>·</span>
            <span>Журнал и верстак инженера</span>
        </div>
        <div class="flex gap-4">
            <a href="privacy.php" class="hover:text-gray-900">Конфиденциальность</a>
            <a href="cookies.php" class="hover:text-gray-900">Cookies</a>
        </div>
    </div>
</footer>

<script src="assets/js/article-ux.js"></script>
<script src="assets/js/thermal-slider.js"></script>

</body>
</html>
