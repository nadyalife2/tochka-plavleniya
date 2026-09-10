<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

// Filter by category
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : 'all';
$filtered_articles = [];
foreach ($articles as $article) {
    if ($tag_filter === 'all' || $article['tag_key'] === $tag_filter) {
        $filtered_articles[] = $article;
    }
}

// Pagination
$items_per_page = 6;
$total_items = count($filtered_articles);
$total_pages = ceil($total_items / $items_per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$offset = ($current_page - 1) * $items_per_page;
$paginated_articles = array_slice($filtered_articles, $offset, $items_per_page);
?>
<!DOCTYPE html><html class="" lang="ru"><head>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>Точка Плавления — Журнал и верстак инженера</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@400;700&amp;family=Hanken+Grotesk:wght@400;700;900&amp;family=Inter:wght@400;500;700;800;900&amp;family=Eb+Garamond:wght@400;500;700&amp;display=swap" rel="stylesheet">
<script>tailwind.config = {theme: {extend: {colors: {surface: "#faf5ee", "surface-dim": "#dcd6cc", primary: "#c2652a", accent: "#fc6c2b", tape: "#d3c9a3", "tape-red": "#e05942", "tape-yellow": "#D4AF37", "inverse-on-surface": "#faf5ee", "tertiary-container": "#d47070", "secondary-container": "#eae2da", "on-secondary-fixed-variant": "#504840", "surface-container": "#f2ece4", "secondary-fixed-dim": "#cec6be", background: "#faf5ee", "inverse-surface": "#3a302a", "tertiary-fixed-dim": "#e8a0a0", "surface-container-low": "#f6f0e8", "on-secondary-fixed": "#2a2420", "secondary-fixed": "#eae2da", secondary: "#78706a", outline: "#9a9088", "surface-container-highest": "#e6e0d6", "on-error-container": "#7a1a10", "outline-variant": "#d8d0c8", "surface-bright": "#faf5ee", "surface-tint": "#c2652a", "primary-fixed": "#fbe8d8", "on-background": "#3a302a", "primary-fixed-dim": "#f0a878", "surface-container-lowest": "#ffffff", "on-surface-variant": "#605850", "on-tertiary": "#ffffff", "on-primary": "#ffffff", error: "#c0392b", "tertiary-fixed": "#fce0e0", "on-tertiary-container": "#3a2020", "primary-container": "#e08850", "surface-container-high": "#ece6dc", "on-tertiary-fixed-variant": "#6e3030", "on-secondary-container": "#605850", "on-error": "#ffffff", "on-primary-container": "#fbe8d8", "on-tertiary-fixed": "#2e1515", "on-secondary": "#ffffff", "on-surface": "#3a302a", "surface-variant": "#ece6dc", tertiary: "#8c3c3c", "error-container": "#fce4e0", "on-primary-fixed-variant": "#8a4518", "inverse-primary": "#f0a878", "on-primary-fixed": "#401a08", "craft-paper": "#e3dcc8"}, fontFamily: {sans: ["Inter", "sans-serif"], hand: ["Caveat", "cursive"], logo: ["Hanken Grotesk", "sans-serif"], headline: ["Eb Garamond"], display: ["Eb Garamond"]}, boxShadow: {brutal: "6px 6px 0px 0px rgba(0,0,0,1)", "brutal-hover": "2px 2px 0px 0px rgba(0,0,0,1)", "brutal-lg": "10px 10px 0px 0px rgba(0,0,0,1)"}, borderRadius: {DEFAULT: "0.25rem", lg: "0.5rem", xl: "0.75rem", full: "9999px"}}}};</script>
<style data-purpose="brutal-styles">
    body {
      background-color: #ffffff;
      background-image: url('data:image/svg+xml,%3Csvg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"%3E%3Cpath d="M0 0h40v40H0V0zm1 1h38v38H1V1z" fill="%23000" fill-opacity="0.05" fill-rule="evenodd"%3E%3C/svg%3E');
      color: black;
    }
    
    .bento-card {
      background-color: white;
      border: 3px solid black;
      box-shadow: 6px 6px 0px 0px rgba(0,0,0,1);
      transition: all 0.2s ease-in-out;
      position: relative;
    }
    
    .bento-card:hover {
      transform: translate(4px, 4px);
      box-shadow: 2px 2px 0px 0px rgba(0,0,0,1);
    }

    .tape-piece {
      position: absolute;
      height: 24px;
      width: 80px;
      background-color: #d3c9a3;
      opacity: 0.9;
      transform: rotate(-3deg);
      z-index: 10;
      box-shadow: 1px 1px 3px rgba(0,0,0,0.2);
    }

    .tape-top-center { top: -12px; left: 50%; transform: translateX(-50%) rotate(2deg); }
    .tape-top-left { top: -10px; left: -10px; transform: rotate(-15deg); }
    .tape-top-right { top: -10px; right: -10px; transform: rotate(15deg); }
    
    .inventory-label { background-color: #e05942; color: white; padding: 4px 12px; font-weight: 800; font-size: 0.875rem; display: inline-block; box-shadow: 2px 2px 0px rgba(0,0,0,1); border: 2px solid black; letter-spacing: 0.05em; }
    .tape-yellow { background-color: #D4AF37; padding: 4px 12px; font-weight: 700; transform: rotate(1deg); display: inline-block; box-shadow: 4px 4px 0px rgba(0,0,0,1); border: 2px solid black; }

    .scribble-box {
      border: 2px dashed black;
      border-radius: 4px;
      padding: 1rem;
      position: relative;
    }
    
    .scribble-box::before {
      content: '';
      position: absolute;
      top: -4px; left: -4px; right: -4px; bottom: -4px;
      border: 1px solid black;
      border-radius: 8px;
      pointer-events: none;
      transform: rotate(1deg);
    }

    .plate-orange {
      background-color: #fc6c2b;
      color: white;
      padding: 0.25rem 1rem;
      display: inline-block;
      transform: rotate(-2deg);
      box-shadow: 4px 4px 0px rgba(0,0,0,1);
      border: 3px solid black;
    }

    /* CUSTOM LOGO (OPTIMIZED SIZE) */
    .logo { 
      font-family: 'Hanken Grotesk', 'Inter', sans-serif; 
      font-size: 1.5rem; 
      font-weight: 900; 
      text-decoration: none; 
      color: #000000; 
      letter-spacing: -0.02em; 
      text-transform: uppercase;
      display: inline-flex;
      align-items: center;
      line-height: 1;
    }
    .logo span { 
      color: #FFFFFF; 
      background: #ea580c; 
      padding: 0.05rem 0.35rem; 
      border-radius: 3px; 
      transform: skew(-6deg); 
      display: inline-block; 
      margin: 0 0.15rem;
      font-size: 1.05em;
      line-height: 0.9;
    }
</style>
</head>
<body class="font-sans antialiased min-h-screen p-4 md:p-8 lg:p-12">
<!-- BEGIN: MainHeader -->
<header class="mb-12 relative max-w-6xl mx-auto">
<div class="bento-card p-6 md:p-8 bg-white flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
<div class="tape-piece tape-top-center w-32"></div>
<div class="flex flex-col gap-2">
<div class="inventory-label mb-2 uppercase tracking-tight transform -rotate-2">ТЧП // ТОЧКА ПЛАВЛЕНИЯ</div>
<!-- USER'S BRAND LOGO -->
<a href="index.php" class="logo">Точка<span>.</span>Плавления</a>
<p class="font-hand text-2xl mt-2 text-gray-800">Анти-идеальный веб. Структура обнажена. Искренность форм.</p>
</div>
<nav class="flex flex-wrap gap-4 mt-4 md:mt-0">
<a class="font-bold border-2 border-black px-4 py-2 hover:bg-black hover:text-white transition-colors bg-white text-black" href="index.php" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Статьи</a>
<a class="font-bold border-2 border-black px-4 py-2 hover:bg-black hover:text-white transition-colors bg-white text-black" href="interactive.php" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Инструменты</a>
<a class="font-bold border-2 border-black px-4 py-2 hover:bg-black hover:text-white transition-colors bg-white text-black" href="cookies.php" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Cookies</a>
</nav>
</div>
</header>
<!-- END: MainHeader -->

<!-- BEGIN: Hero Section -->
<section class="max-w-6xl mx-auto mb-12">
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
<div>
<!-- USER'S BRAND HERO H1 -->
<h1 class="text-5xl md:text-7xl font-black uppercase leading-[1.1] tracking-tighter mb-6">
        Паяем.<br>
<span class="plate-orange my-2">Проектируем.</span><br>
        Прошиваем.
</h1>
<p class="text-xl mb-8 font-medium">Блог и лаборатория о том, как превратить кучу компонентов в работающее устройство. Без воды, с интерактивными калькуляторами и справочниками.</p>
<a class="inline-block bg-black text-white font-bold px-8 py-3 rounded-full border-2 border-black hover:bg-white hover:text-black transition-colors" href="interactive.php" style="box-shadow: 4px 4px 0px rgba(0,0,0,1);">Начать проект</a>
</div>
<div class="bento-card p-8 bg-white group cursor-crosshair">
<div class="tape-piece tape-top-right w-24"></div>
<svg class="w-full h-auto text-black group-hover:text-accent transition-colors duration-300" fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 400 300">
<rect height="200" rx="10" stroke-dasharray="10 10" width="300" x="50" y="50"></rect>
<circle class="group-hover:fill-accent group-hover:stroke-accent transition-all duration-300" cx="200" cy="150" r="40"></circle>
<path d="M50 150 H160 M240 150 H350 M200 50 V110 M200 190 V250" stroke-linecap="round"></path>
<text fill="currentColor" font-family="monospace" font-size="14" stroke="none" x="10" y="30">IC-ТЧП-01</text>
</svg>
</div>
</div>
</section>
<!-- END: Hero Section -->

<!-- BEGIN: Filter Panel -->
<section class="max-w-6xl mx-auto mb-8">
<div class="flex flex-wrap gap-3">
<a class="px-6 py-2 rounded-full border-2 border-black font-bold <?= $tag_filter === 'all' ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=all" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Все</a>
<a class="px-6 py-2 rounded-full border-2 border-black font-bold <?= $tag_filter === 'basics' ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=basics" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Основы</a>
<a class="px-6 py-2 rounded-full border-2 border-black font-bold <?= $tag_filter === 'smd' ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=smd" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">SMD</a>
<a class="px-6 py-2 rounded-full border-2 border-black font-bold <?= $tag_filter === 'tools' ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=tools" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Инструменты</a>
<a class="px-6 py-2 rounded-full border-2 border-black font-bold <?= $tag_filter === 'materials' ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=materials" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Материалы</a>
</div>
</section>
<!-- END: Filter Panel -->

<!-- BEGIN: MainContent -->
<main class="max-w-6xl mx-auto mb-12">
<!-- Bento Grid Container -->
<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 md:gap-8">

<?php if (isset($paginated_articles[0])): ?>
<!-- Featured Anchor Article (Span 2 cols, 2 rows) -->
<?php $art = $paginated_articles[0]; $url = "article.php?slug=" . urlencode($art['slug']); ?>
<article class="bento-card col-span-1 md:col-span-2 lg:col-span-2 row-span-2 p-6 flex flex-col justify-between group">
<div class="tape-piece tape-top-left"></div>
<div>
<div class="tape-yellow mb-4 text-sm"><?= e($art['tag']) ?> // Главный материал</div>
<div class="w-full h-64 bg-gray-100 border-2 border-black mb-6 relative overflow-hidden flex items-center justify-center grayscale group-hover:grayscale-0 transition-all duration-300">
    <div class="transform group-hover:scale-110 transition-transform duration-300">
        <?= get_card_icon($art['icon'] ?? 'chip') ?>
    </div>
</div>
<h2 class="text-3xl font-black mb-4 leading-tight uppercase group-hover:text-accent transition-colors">
    <a href="<?= $url ?>"><?= e($art['title']) ?></a>
</h2>
<p class="text-lg mb-6"><?= e($art['excerpt']) ?></p>
</div>
<div class="flex items-center justify-between border-t-2 border-black pt-4">
<span class="font-hand text-xl"><?= e($art['date']) ?> · <?= e($art['author']) ?></span>
<a class="inline-flex items-center font-bold hover:text-accent transition-colors uppercase tracking-widest text-sm" href="<?= $url ?>">Читать <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 5l7 7m0 0l-7 7m7-7H3" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>
</div>
</article>
<?php endif; ?>

<!-- Manifesto Widget -->
<aside class="bento-card col-span-1 p-6 bg-[#e0e4e8]">
<div class="tape-piece tape-top-center w-16"></div>
<h3 class="text-xl font-black mb-4 uppercase tracking-tighter">Anti-perfect UI</h3>
<p class="font-hand text-2xl leading-relaxed mb-4">В эпоху стерильных дашбордов и вылизанных пикселей мы транслируем человечность, честность и крафт.</p>
<div class="scribble-box mt-4 bg-white/50">
<p class="text-sm font-bold text-center uppercase tracking-wide">Интерфейс, который не скрывает свою структуру.</p>
</div>
</aside>

<?php if (isset($paginated_articles[1])): ?>
<!-- Standard Article Card -->
<?php $art = $paginated_articles[1]; $url = "article.php?slug=" . urlencode($art['slug']); ?>
<article class="bento-card col-span-1 p-5 flex flex-col justify-between group">
<div>
<div class="w-full h-32 bg-gray-100 border-2 border-black mb-4 relative overflow-hidden flex items-center justify-center grayscale group-hover:grayscale-0 transition-all duration-300">
    <div class="transform group-hover:scale-110 transition-transform duration-300">
        <?= get_card_icon($art['icon'] ?? 'chip') ?>
    </div>
</div>
<div class="text-xs font-bold uppercase mb-2 border-2 border-black inline-block px-2 py-1"><?= e($art['tag']) ?></div>
<h3 class="text-xl font-black mb-2 uppercase group-hover:text-accent transition-colors">
    <a href="<?= $url ?>"><?= e($art['title']) ?></a>
</h3>
<p class="text-sm mb-4"><?= e($art['excerpt']) ?></p>
</div>
<a class="text-sm font-bold border-2 border-black inline-block text-center py-2 hover:bg-black hover:text-white transition-colors uppercase" href="<?= $url ?>">Подробнее</a>
</article>
<?php endif; ?>

<!-- Action Widget Card -->
<div class="bento-card col-span-1 md:col-span-2 lg:col-span-1 row-span-1 p-6 flex flex-col items-center justify-center text-center bg-gray-100">
<div class="tape-piece tape-top-right w-20"></div>
<svg class="w-16 h-16 mb-4 animate-pulse text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" stroke-linecap="round" stroke-linejoin="round"></path></svg>
<h3 class="font-black uppercase mb-2 text-lg">Калькуляторы</h3>
<p class="text-xs mb-4">Строгий DOM-порядок. Доступность как приоритет.</p>
<a class="w-full bg-black text-white font-bold py-2 border-2 border-black hover:bg-white hover:text-black transition-colors inline-block text-center" href="interactive.php" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);">Открыть</a>
</div>

<!-- Small Sticky Note Card -->
<div class="bento-card col-span-1 p-6 flex flex-col justify-center bg-[#ffe873]">
<div class="tape-piece tape-top-left w-12"></div>
<p class="font-hand text-3xl text-center transform -rotate-3 leading-tight">
          "Тренд 2026:<br>Неидеальные тени и текстуры."
</p>
</div>

<?php if (isset($paginated_articles[2])): ?>
<!-- Standard Article Card 2 -->
<?php $art = $paginated_articles[2]; $url = "article.php?slug=" . urlencode($art['slug']); ?>
<article class="bento-card col-span-1 p-5 flex flex-col justify-between group">
<div>
<div class="w-full h-32 bg-gray-100 border-2 border-black mb-4 relative overflow-hidden flex items-center justify-center grayscale group-hover:grayscale-0 transition-all duration-300">
    <div class="transform group-hover:scale-110 transition-transform duration-300">
        <?= get_card_icon($art['icon'] ?? 'chip') ?>
    </div>
</div>
<div class="text-xs font-bold uppercase mb-2 border-2 border-black inline-block px-2 py-1"><?= e($art['tag']) ?></div>
<h3 class="text-xl font-black mb-2 uppercase group-hover:text-accent transition-colors">
    <a href="<?= $url ?>"><?= e($art['title']) ?></a>
</h3>
<p class="text-sm mb-4"><?= e($art['excerpt']) ?></p>
</div>
<a class="text-sm font-bold border-2 border-black inline-block text-center py-2 hover:bg-black hover:text-white transition-colors uppercase" href="<?= $url ?>">Подробнее</a>
</article>
<?php endif; ?>

<?php if (isset($paginated_articles[3])): ?>
<!-- Wide Article Card (Spans 2 cols) -->
<?php $art = $paginated_articles[3]; $url = "article.php?slug=" . urlencode($art['slug']); ?>
<article class="bento-card col-span-1 md:col-span-2 lg:col-span-2 p-6 flex flex-col sm:flex-row gap-6 group">
<div class="w-full sm:w-1/3 h-48 sm:h-auto border-2 border-black flex items-center justify-center bg-gray-100 group-hover:bg-accent/10 transition-colors grayscale group-hover:grayscale-0">
    <div class="transform group-hover:scale-110 transition-transform duration-300">
        <?= get_card_icon($art['icon'] ?? 'chip') ?>
    </div>
</div>
<div class="flex flex-col justify-between flex-grow">
<div>
<div class="text-xs font-bold uppercase mb-2 border-2 border-black inline-block px-2 py-1"><?= e($art['tag']) ?></div>
<h2 class="text-2xl font-black mb-2 uppercase group-hover:text-accent transition-colors">
    <a href="<?= $url ?>"><?= e($art['title']) ?></a>
</h2>
<p class="text-sm mb-4 font-medium"><?= e($art['excerpt']) ?></p>
</div>
<a class="self-end font-bold text-sm uppercase tracking-wider hover:text-accent transition-colors flex items-center gap-1" href="<?= $url ?>">Читать кейс <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 8l4 4m0 0l-4 4m4-4H3" stroke-linecap="round" stroke-linejoin="round"></path></svg></a>
</div>
</article>
<?php endif; ?>

</div>
</main>
<!-- END: MainContent -->

<!-- BEGIN: Pagination -->
<?php if ($total_pages > 1): ?>
<div class="max-w-6xl mx-auto flex justify-center gap-2 mb-16">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a class="w-10 h-10 flex items-center justify-center rounded-full border-2 border-black font-bold <?= $i === $current_page ? 'bg-accent text-white' : 'bg-white text-black hover:bg-black hover:text-white' ?> transition-colors" href="index.php?tag=<?= urlencode($tag_filter) ?>&amp;page=<?= $i ?>" style="box-shadow: 2px 2px 0px rgba(0,0,0,1);"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<!-- END: Pagination -->

<!-- BEGIN: Footer -->
<footer class="max-w-6xl mx-auto mb-8">
<div class="bento-card p-6 flex flex-col md:flex-row justify-between items-center text-sm font-bold uppercase tracking-wider">
<p class="mb-4 md:mb-0">© 2026 Точка Плавления. Anti-perfect.</p>
<div class="flex gap-6">
<a class="hover:text-accent transition-colors border-b-2 border-transparent hover:border-accent" href="interactive.php">Инструменты</a>
<a class="hover:text-accent transition-colors border-b-2 border-transparent hover:border-accent" href="cookies.php">Cookies</a>
<a class="hover:text-accent transition-colors border-b-2 border-transparent hover:border-accent" href="privacy.php">Privacy</a>
</div>
</div>
</footer>
<!-- END: Footer -->

</body></html>
