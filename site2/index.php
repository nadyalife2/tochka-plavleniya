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
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>ТОЧКА ПЛАВЛЕНИЯ // Журнал и открытая лаборатория ТЧП</title>
  
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

  <!-- Self-Hosted Fonts & Compiled Tailwind CSS -->
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/build.css">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <!-- Common Design Tokens & Base Styles -->
  <link rel="stylesheet" href="assets/css/common.css">
</head>
<body class="font-sans min-h-screen flex flex-col justify-between text-[15px] leading-[1.65]">

  <!-- Top Minimal Header Bar -->
  <header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      
      <!-- Brand mark & Title -->
      <div class="flex items-center gap-6">
        <a class="logo" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>

        <!-- Desktop Nav -->
        <?php 
        $current_page = 'index';
        include __DIR__ . '/includes/header-nav.php'; 
        ?>
      </div>

      <!-- Right Action / Dark Mode Toggle & Telegram -->
      <div class="flex items-center gap-3">
        <a class="hidden sm:inline-block text-[12.5px] text-ink-muted hover:text-ink transition-colors font-mono" href="article.php#simulator">
          [↓ к расчёту]
        </a>

        <!-- Dark/Light Mode Switcher (Sketch Style) -->
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

        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="interactive.php">
          <span>Верстак / Тулзы</span>
          <span class="material-symbols-outlined text-[13px]">build</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <main class="w-full flex-grow pt-8 pb-16">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5 flex-wrap">
        <a class="hover:text-ink transition-colors" href="index.php">Главная</a>
        <span>→</span>
        <span class="text-ink">Журнал и открытая лаборатория ТЧП</span>
      </nav>

      <!-- Hero Section -->
      <section class="border-b border-paper-border pb-10 mb-10">
        <div class="max-w-3xl space-y-5">
          
          <!-- Tape & Label Badge -->
          <div class="relative inline-block mb-1">
            <div class="absolute -top-2 left-6 w-10 h-3 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/70 dark:border-[#968458]/70 shadow-sm rotate-[-2deg] z-10 pointer-events-none" style="backdrop-filter: blur(1px);"></div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-[#fef08a]/60 dark:bg-[#ca8a04]/25 border border-[#fde047] dark:border-[#ca8a04]/60 text-ink font-mono text-[11px] shadow-sm rotate-[-1.2deg] sketch-border">
              <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
              <span class="font-bold tracking-wider uppercase">ВЕРСТАК // QC PASSED 2026</span>
              <span class="text-ink-faint">|</span>
              <span class="text-[10px] text-ink-muted">ПРАКТИКА БЕЗ ТЕОРИИ</span>
            </div>
          </div>

          <!-- Metadata -->
          <div class="inline-flex items-center gap-2 font-mono text-xs text-ink-muted">
            <span class="w-2 h-2 rounded-full bg-ink inline-block"></span>
            <span class="uppercase tracking-wider font-semibold text-ink">Инженерный регламент v2.4</span>
            <span>·</span>
            <span>Лаборатория поверхностного монтажа</span>
          </div>

          <!-- Headline -->
          <h1 class="text-4xl sm:text-5xl lg:text-[54px] font-bold text-ink tracking-[-0.03em] leading-[1.1] font-sans">
            Паяем. Проектируем. Прошиваем.
          </h1>

          <!-- Lead text -->
          <p class="text-lg sm:text-[19px] text-ink/90 font-serif leading-[1.65]">
            Журнал и открытая документация о том, как превратить кучу разрозненных SMD/BGA компонентов в надежное устройство. Без воды, с интерактивными симуляторами термопрофилей, допусками IPC/JEDEC и проверенными режимами пайки.
          </p>

          <!-- Editorial Manifesto Note (Sketch Style) -->
          <div class="font-hand text-lg sm:text-xl text-ink-muted/80 italic rotate-[-1deg] inline-flex items-center gap-2 pt-0.5 pb-1">
            <span>«Анти-идеальный веб. Структура обнажена. Искренность форм.»</span>
          </div>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap items-center gap-3 pt-2">
            <a class="inline-flex items-center gap-2 px-4 py-2 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity" href="article.php">
              <span>Читать главный регламент</span>
              <span class="text-xs">→</span>
            </a>
            <a class="inline-flex items-center gap-2 px-3.5 py-2 border border-paper-border bg-paper text-ink text-xs font-mono font-medium rounded hover:border-paper-border-dark transition-colors" href="article.php#simulator">
              <span>Инженерный калькулятор</span>
            </a>
            <div class="hidden sm:flex items-center gap-1.5 font-mono text-[11px] text-ink bg-[#fef08a] dark:bg-[#ca8a04]/30 border border-[#fde047] dark:border-[#ca8a04] px-2.5 py-0.5 shadow-sm sketch-border" style="transform: rotate(-0.5deg);">
              <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
              <span class="font-medium">rev 2.4.1 актуализирован</span>
            </div>
          </div>

        </div>
      </section>

      <!-- Category Filter Pills -->
      <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-8 font-mono text-xs" id="articles">
        <a href="index.php?tag=all#articles" class="px-3 py-1 rounded <?= $tag_filter === 'all' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?> transition-all">
          Все материалы
        </a>
        <a href="index.php?tag=basics#articles" class="px-3 py-1 rounded <?= $tag_filter === 'basics' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?> transition-all">
          Основы
        </a>
        <a href="index.php?tag=smd#articles" class="px-3 py-1 rounded <?= $tag_filter === 'smd' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?> transition-all">
          SMD и BGA
        </a>
        <a href="index.php?tag=tools#articles" class="px-3 py-1 rounded <?= $tag_filter === 'tools' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?> transition-all">
          Инструменты
        </a>
        <a href="index.php?tag=materials#articles" class="px-3 py-1 rounded <?= $tag_filter === 'materials' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-paper-border-dark hover:text-ink' ?> transition-all">
          Сплавы и флюсы
        </a>
      </div>

      <!-- Main Layout: 8 cols Editorial + 4 cols Sidebar -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left Column: Articles -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- FEATURED MAIN ARTICLE (Hero Card) -->
          <article class="border border-paper-border rounded-lg bg-card p-6 sm:p-7 space-y-5 shadow-sm transition-all hover:border-paper-border-dark relative" id="featured">
            
            <!-- Image Frame -->
            <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
              <img src="https://lh3.googleusercontent.com/aida/AEtjO1XPXO_7vFBi0-sZRVk7MH6OetXskpHt5Xcf3Ip6nfHDDYM7qdO0nERNQaH_49PYrri281JQZUD0JhgzliwsR7F5Ks8GvSMY32dTxDUIHzZ_P5Drz6niq2ZSyIRirmXvsdrZExlqKeZ_11m0Vf64Fa9fYCMG9SMrAAg0F5hGzsceoEP1ajdrLph6LgFKfgF6aLS30BF8hJJkW20S0l03CIQZ4uc7pmSJ_MFPJyqCHL4KNonVuGSJXGX1rAg" alt="Схема термопрофиля пайки BGA и распределения тепла" class="w-full h-52 sm:h-60 object-cover object-center" loading="lazy">
              <div class="absolute bottom-2 right-2 px-2 py-0.5 bg-paper/90 border border-paper-border text-[11px] font-mono text-ink-muted rounded backdrop-blur-sm">
                FIG. 4.0 // SCHEMATIC
              </div>
            </div>

            <!-- Meta Top -->
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted pt-1">
              <div class="flex items-center gap-2">
                <span class="text-ink font-hand text-lg font-bold italic tracking-wide rotate-[-1.5deg] inline-block">Иван Пайкин</span>
                <span class="text-ink-faint">·</span>
                <span>~8 мин чтения</span>
              </div>
              <span class="text-[11px] text-ink-faint uppercase font-mono">IPC/JEDEC</span>
            </div>

            <!-- Title & Excerpt -->
            <div class="space-y-3">
              <h2 class="text-2xl sm:text-[28px] font-bold text-ink tracking-tight leading-[1.2]">
                <a class="hover:underline decoration-ink underline-offset-4" href="article.php">
                  Температурные профили: как не перегреть плату за $300
                </a>
              </h2>
              <p class="text-ink/85 font-serif text-[16px] leading-relaxed">
                Разбираем теплоёмкость текстолита и строим правильную кривую нагрева для BGA-монтажа и сложных многослойных плат. Разбираем, почему стандартная пайка «по цифрам на табло фена» убивает платы, и как выставить 4 фазы термопрофиля.
              </p>
            </div>

            <!-- Technical Flow Strip -->
            <div class="rounded border border-paper-border bg-paper-subtle p-4 flex items-center justify-between font-mono text-xs text-ink-muted overflow-x-auto relative">
              <div class="flex items-center gap-4 shrink-0">
                <span class="text-ink-muted">Ликвидус SAC305: <strong class="text-ink">217°C</strong></span>
                <span class="text-ink-faint">→</span>
                <span class="text-ink-muted">ПОС-61: <strong class="text-ink">183°C</strong></span>
                <span class="text-ink-faint">→</span>
                <span class="text-ink-muted">Окно TAL: <strong class="font-medium text-ink">45–75 с</strong></span>
              </div>
              <span class="text-[11px] text-ink-faint font-mono">FIG. 4.1</span>
            </div>

            <!-- Card Bottom Bar -->
            <div class="flex items-center justify-between pt-2 border-t border-paper-border font-mono text-xs text-ink-muted">
              <div class="flex items-center gap-2">
                <span class="text-ink">Регламент BGA</span>
                <span class="text-ink-faint">·</span>
                <span>10 авг 2026</span>
              </div>
              <a class="text-ink font-semibold hover:underline flex items-center gap-1 group" href="article.php">
                <span class="underline decoration-ink decoration-2 underline-offset-4">Читать регламент</span>
                <span>→</span>
              </a>
            </div>

          </article>

          <!-- 2x2 Secondary Articles Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            
            <!-- Card 1: SMD 0402 vs 0603 -->
            <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-colors">
              <div class="overflow-hidden rounded border border-paper-border bg-paper">
                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuAYi7MdtqY4d65d01YGA76Nb9UaAhDQ7QkjjWLOubwy90v1ERBKSlSZppwjkUC5jpi9kY1HdnQ5FHJR21MXRmgii-iF6CYNt9W4mNydzzoSQsgYTW_6wcWir8FOtb6ioNVbDgKW05RwHoKlGk90RvpJXFOV0hLsAaFjRijsi-njQlJ2_aoNGm_Q0M2v_1jGJM6d0HEoWjg1G2ov5xWDRXeKNl2a49NrjH0NmXjEyipzYiA6d1bDpnFu" alt="SMD 0402 vs 0603 сравнение" class="w-full h-36 object-cover object-center" loading="lazy">
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                  <span class="uppercase font-medium text-ink border-b border-paper-border pb-0.5">SMD</span>
                  <span>~4 мин</span>
                </div>
                <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                  <a class="hover:underline" href="article.php">SMD 0402 vs 0603: что выбрать для прототипа</a>
                </h3>
                <p class="text-[13.5px] text-ink-muted leading-relaxed">
                  Плотность монтажа против ремонтопригодности — детальный анализ паразитных емкостей и удобства ручной пайки на верстаке.
                </p>
              </div>
              <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="text-ink-faint">Компоненты</span>
                <a class="text-ink font-medium hover:underline" href="article.php">Подробнее →</a>
              </div>
            </article>

            <!-- Card 2: T12 vs JBC C245 -->
            <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-colors">
              <div class="overflow-hidden rounded border border-paper-border bg-paper">
                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuChE9QvE8LozVtk7VrKxyYI-SrHgrPhImpEKRwWLy8fCBhxDhgQJbz22eBoGpZ97AEmkDILNPB6nXPLwk4Pq62v8qVL76AJmtm7Y3M86EKjJYmx-yPJkCc-vq1O-rNVWNo_vypZINr_lZHpl4QKTYJnCzMarAZm24hHEabOepCGf90eCac2R0yEBmu4eXa8cQtRGKfECNtCPUpfGFTV4N28XpeOWVBdpVriBQO9ZdB_yuU45CFoJi1n" alt="Картриджи жал T12 vs JBC C245 в разрезе" class="w-full h-36 object-cover object-center" loading="lazy">
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                  <span class="uppercase font-medium text-ink border-b border-paper-border pb-0.5">Инструменты</span>
                  <span>~6 мин</span>
                </div>
                <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                  <a class="hover:underline" href="article.php">Жала паяльника: T12 против JBC C245 на верстаке</a>
                </h3>
                <p class="text-[13.5px] text-ink-muted leading-relaxed">
                  Сравниваем скорость компенсации тепла на земляных полигонах, ресурс картриджей и экономику работы под микроскопом.
                </p>
              </div>
              <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="text-ink-faint">Оборудование</span>
                <a class="text-ink font-medium hover:underline" href="article.php">Подробнее →</a>
              </div>
            </article>

            <!-- Card 3: Гид по флюсам -->
            <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-colors">
              <div class="overflow-hidden rounded border border-paper-border bg-paper">
                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuDq0hH5xM39QDK1ZqCle8_Wk1nH4vsPEFi-qLOCIaH0mt5-NgDzrCsJkTQtjTFrNG8X9c4iXDGbdoYyxa9w9vsD5lhmodhFeYkAWkHXAFy0RloCfAkIN97lpnpFIzEqofnLwCZ_hIQ31MPvwYjr0YKml4OBALGI73SBp5g7T0cpRCK35Hp4utgd9ONG1OB3QN5BnZnqPkHXUtJPjiJgakBYuQhMka55il18DlWwDTGLTl5ZZrP1LM9R" alt="Дозатор флюс-геля для монтажа BGA" class="w-full h-36 object-cover object-center" loading="lazy">
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                  <span class="uppercase font-medium text-ink border-b border-paper-border pb-0.5">Материалы</span>
                  <span>~5 мин</span>
                </div>
                <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                  <a class="hover:underline" href="article.php">Гид по флюсам: RMA, NC и No-Clean в шприце</a>
                </h3>
                <p class="text-[13.5px] text-ink-muted leading-relaxed">
                  Какой флюс оставить, а какой смывать до блеска изопропиловым спиртом в УЗ-ванне, чтобы плата не деградировала через полгода.
                </p>
              </div>
              <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="text-ink-faint">Химия</span>
                <a class="text-ink font-medium hover:underline" href="article.php">Подробнее →</a>
              </div>
            </article>

            <!-- Card 4: Симулятор термопрофиля -->
            <article class="border border-paper-border rounded-lg bg-card p-5 flex flex-col justify-between space-y-4 hover:border-paper-border-dark transition-colors" id="calc">
              <div class="overflow-hidden rounded border border-paper-border bg-paper">
                <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuCz3EwM-yiiGuYAXSgycnw3IamGAkG45KS9762AyA9_0RrrlkIL1iHEpmka6kq84n028UCS8F5Fng_yDk-0oMCYSywnUmcfYDGuiLzBNmGEgPgYJ8ARSDGgl4mTd4p03iPJtH9_gFS7toreuV8Ud6t3Xmsz0WiLpKTjwhgbdra-vGgts3_XRkNXVfXR3rTGpqCpuw9nDyhp6gptDUKonPp2XCLgE7N0Aidcy7Pd8JuRU4TaqQqNEHPP" alt="Схема термовоздушной станции и профиля пайки" class="w-full h-36 object-cover object-center" loading="lazy">
              </div>
              <div class="space-y-2">
                <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint">
                  <span class="uppercase font-semibold text-ink px-1.5 py-0.5 rounded bg-paper-subtle border border-paper-border">Интерактивно</span>
                  <span class="text-ink font-medium">Калькулятор</span>
                </div>
                <h3 class="text-base font-bold text-ink leading-snug tracking-tight">
                  <a class="hover:underline" href="article.php#simulator">Симулятор термопрофиля фена и стола</a>
                </h3>
                <p class="text-[13.5px] text-ink-muted leading-relaxed">
                  Расчет 4 фаз нагрева: Preheat, Soak, Reflow и Cooling под сплавы SAC305, ПОС-61 и Sn42Bi58 по контактной термопаре.
                </p>
              </div>
              <div class="pt-3 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="text-ink-faint">SAC305 / ПОС-61</span>
                <a class="text-ink font-semibold hover:underline" href="article.php#simulator">Открыть калькулятор →</a>
              </div>
            </article>

          </div>

        </div>

        <!-- Right Column: Sidebar -->
        <aside class="lg:col-span-4 space-y-6">
          
          <!-- Post-it Note Sticker (Caveat font) -->
          <div class="border border-[#fde047] dark:border-[#ca8a04]/70 bg-[#fef08a]/40 dark:bg-[#ca8a04]/15 p-4 rounded-lg space-y-2 text-xs shadow-sm sketch-border">
            <div class="flex items-center justify-between font-mono text-[10.5px] uppercase font-bold text-ink border-b border-[#fde047] dark:border-[#ca8a04]/50 pb-1.5">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
                Заметка на верстак
              </span>
              <span class="text-[10px] text-ink-muted font-normal">LAB-QC</span>
            </div>
            <div class="space-y-1.5">
              <p class="font-hand text-[17px] leading-snug text-ink italic font-semibold rotate-[-0.5deg]">
                «Термопару фиксировать <span class="font-mono font-bold text-xs px-1.5 py-0.5 bg-[#fef08a] dark:bg-[#ca8a04]/40 border border-[#fde047] dark:border-[#ca8a04] not-italic inline-block">строго каптоном</span> прямо к галтелям BGA, иначе датчик меряет воздух фена!»
              </p>
              <p class="font-serif italic text-[11.5px] text-ink-muted border-t border-[#fde047]/60 dark:border-[#ca8a04]/40 pt-1">
                — из полевого блокнота инженера: структура обнажена, без идеализации
              </p>
            </div>
            <div class="pt-1 flex items-center justify-between font-mono text-[10px] text-ink-muted">
              <span>Контроль t°C</span>
              <span class="font-semibold text-ink">J-STD-020E</span>
            </div>
          </div>

          <!-- Sections list -->
          <div class="relative border border-paper-border rounded-lg bg-card p-5 space-y-3.5 shadow-sm">
            <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
              <span>Регламенты ТЧП</span>
              <span>Разделы</span>
            </div>
            <div class="space-y-2 text-xs font-mono">
              <a class="flex items-center justify-between py-1 text-ink hover:text-ink-muted transition-colors" href="article.php#step-1">
                <span>1. Профилирование BGA</span>
                <span class="text-ink-faint">J-STD</span>
              </a>
              <a class="flex items-center justify-between py-1 text-ink hover:text-ink-muted transition-colors" href="article.php#alloys">
                <span>2. Металлургия сплавов</span>
                <span class="text-ink-faint">Таблица</span>
              </a>
              <a class="flex items-center justify-between py-1 text-ink hover:text-ink-muted transition-colors" href="article.php#step-4">
                <span>3. Защита от влаги MSL</span>
                <span class="text-ink-faint">Сушка</span>
              </a>
              <a class="flex items-center justify-between py-1 text-ink hover:text-ink-muted transition-colors" href="interactive.php#calculator">
                <span>4. Выбор флюсов</span>
                <span class="text-ink-faint">RMA/NC</span>
              </a>
            </div>
          </div>

          <!-- Live Lab box -->
          <div class="border border-paper-border bg-paper-subtle/60 p-5 rounded-lg space-y-2.5 text-xs relative">
            <div class="flex items-center justify-between font-mono text-[11px] font-bold uppercase text-ink">
              <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 bg-ink rounded-full inline-block"></span>
                Лаборатория ТЧП
              </div>
              <span class="text-[11px] text-ink bg-paper-subtle border border-paper-border-dark px-2 py-0.5 font-mono font-bold inline-block sketch-border" style="transform: rotate(0.6deg);">OPEN</span>
            </div>
            <p class="text-ink-muted leading-relaxed">
              Практические замеры, отчеты дефектов пайки и тесты термоинтерфейсов в открытой базе знаний.
            </p>
            <a class="inline-block pt-1 font-mono text-[11.5px] font-semibold text-ink underline decoration-ink decoration-2 hover:decoration-ink-muted" href="interactive.php">
              Интерактивные расчеты →
            </a>
          </div>

          <!-- Tape & Caution Sketch Box -->
          <div class="relative p-3.5 bg-paper-subtle border-2 border-dashed border-ink/70 rotate-[1.2deg] shadow-sm space-y-1.5 sketch-border">
            <div class="absolute -top-2.5 right-8 w-12 h-3.5 bg-[#ebdeb3]/80 dark:bg-[#786a48]/70 border-l border-r border-[#d2c39b]/80 dark:border-[#968458]/70 shadow-sm rotate-[-3deg] pointer-events-none" style="backdrop-filter: blur(1px);"></div>
            <div class="flex items-center justify-between font-mono text-[11px] font-bold text-ink">
              <span class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-accent stroke-current inline-block" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>НЕ ГРЕТЬ ВЫШЕ 245°C
              </span>
              <span class="px-1.5 py-0.5 text-[11px] bg-paper border border-paper-border-dark">J-STD</span>
            </div>
            <p class="font-mono text-[11px] text-ink-muted leading-tight">
              Деградация подложки и интерметаллидов начинается через 8 секунд перегрева.
            </p>
          </div>

          <!-- Reference Temperatures Box -->
          <div class="border border-paper-border rounded-lg bg-card p-5 space-y-3 relative">
            <div class="flex items-center justify-between">
              <div class="text-[11px] font-mono text-ink-faint uppercase">Опорные температуры:</div>
              <span class="font-mono text-[11px] text-ink-faint">FIG. 1.2 // REWORK</span>
            </div>
            <div class="space-y-2 font-mono text-xs">
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">SAC305 (ликвидус)</span>
                <span class="sketch-pill-gray text-ink font-bold text-[11px]">217°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">ПОС-61 (эвтектика)</span>
                <span class="sketch-pill-yellow text-ink font-bold text-[11px]">183°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">Sn42Bi58 (низкотемп.)</span>
                <span class="sketch-pill-gray text-ink font-medium text-[11px]">138°C</span>
              </div>
              <div class="flex items-center justify-between p-2 bg-paper-subtle border border-paper-border rounded">
                <span class="text-ink font-medium flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
                  Макс. пик кристалла
                </span>
                <span class="sketch-pill-gray text-ink font-bold text-[11px]">245°C</span>
              </div>
            </div>

            <!-- Hand drawn sketch doodle of BGA package with dimensions -->
            <div class="pt-2 border-t border-paper-border flex items-center justify-between text-ink-faint font-mono text-[11px]">
              <div class="flex items-center gap-1.5">
                <svg class="w-10 h-5 text-ink-muted" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" viewBox="0 0 50 24">
                  <rect height="14" stroke-dasharray="2 2" width="34" x="8" y="3"></rect>
                  <circle cx="14" cy="19" r="1.5"></circle>
                  <circle cx="25" cy="19" r="1.5"></circle>
                  <circle cx="36" cy="19" r="1.5"></circle>
                  <line x1="2" x2="6" y1="3" y2="3"></line>
                  <line x1="2" x2="6" y1="17" y2="17"></line>
                  <line x1="4" x2="4" y1="3" y2="17"></line>
                </svg>
                <span>BGA BALL MATRIX</span>
              </div>
              <span class="text-ink-muted font-medium">d = 0.45mm</span>
            </div>
          </div>

        </aside>

      </div>

      <!-- Bottom Interactive CTA -->
      <div class="mt-12 border border-paper-border rounded-lg bg-card p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-0.5">
          <div class="font-semibold text-sm text-ink">Инженерный справочник и калькуляторы ТЧП</div>
          <p class="text-xs text-ink-muted">Таблицы термопрофилей, допуски IPC-A-610 и подбор флюсов в интерактивном верстаке.</p>
        </div>
        <a class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity shrink-0 shadow-sm" href="interactive.php">
          <span>Открыть калькуляторы →</span>
        </a>
      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Theme Toggle JS -->
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
