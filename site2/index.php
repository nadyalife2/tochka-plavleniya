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
  
  <!-- Google Fonts: Inter, IBM Plex Sans, JetBrains Mono, Caveat, Newsreader, Hanken Grotesk -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Caveat:wght@600;700&family=Hanken+Grotesk:wght@700;900&family=IBM+Plex+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:ital,wght@0,400;0,500;0,700;1,400&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,400&display=swap" rel="stylesheet">
  
  <!-- Tailwind CSS v3 -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            paper: '#faf8f5',
            'paper-subtle': '#f3f0ea',
            'paper-border': '#e6e2da',
            'paper-border-dark': '#d3cdc2',
            ink: '#141414',
            'ink-muted': '#6b665f',
            'ink-faint': '#9e988f',
            brand: {
              orange: '#eb5211',
              dark: '#0f0f0f',
              bg: '#faf8f5',
              tape: 'rgba(235, 222, 187, 0.85)',
              tapeBorder: 'rgba(210, 195, 155, 0.7)',
              yellowNote: '#fcf282'
            }
          },
          fontFamily: {
            sans: ['"IBM Plex Sans"', 'Inter', 'sans-serif'],
            serif: ['Newsreader', 'Georgia', 'serif'],
            mono: ['"JetBrains Mono"', 'monospace'],
            hand: ['"Caveat"', 'cursive'],
            logo: ['"Hanken Grotesk"', 'sans-serif']
          }
        }
      }
    }
  </script>

  <!-- Technical Grid Pattern, Tape Stickers, and Custom Logo -->
  <style>
    body {
      background-color: #faf8f5;
      background-image: radial-gradient(#d3cdc2 0.9px, transparent 0.9px);
      background-size: 20px 20px;
      color: #141414;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }

    ::selection {
      background-color: #fef08a;
      color: #141414;
    }

    /* CUSTOM LOGO (Exact brand standard) */
    .logo { 
      font-family: 'Hanken Grotesk', 'Inter', sans-serif; 
      font-size: 1.25rem; 
      font-weight: 900; 
      text-decoration: none; 
      color: #141414; 
      letter-spacing: -0.02em; 
      text-transform: uppercase; 
      display: inline-flex; 
      align-items: center; 
      line-height: 1; 
      transition: opacity 0.15s ease;
    }
    
    .logo:hover {
      opacity: 0.85;
    }

    .logo span { 
      color: #141414; 
      background: #facc15; 
      padding: 0.05rem 0.35rem; 
      border-radius: 3px; 
      transform: skew(-6deg); 
      display: inline-block; 
      margin: 0 0.15rem; 
      font-size: 1.05em; 
      line-height: 0.9; 
    }

    /* Masking tape sticker component */
    .masking-tape {
      position: absolute;
      background: rgba(234, 222, 184, 0.85);
      backdrop-filter: blur(1.5px);
      border-left: 2px dashed rgba(200, 185, 140, 0.6);
      border-right: 2px dashed rgba(200, 185, 140, 0.6);
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
      z-index: 20;
    }

    .sketch-border {
      border-radius: 255px 15px 225px / 15px 225px 15px 255px;
    }
  </style>
</head>
<body class="font-sans min-h-screen flex flex-col justify-between text-[15px] leading-[1.65]">

  <!-- Top Minimal Header Bar -->
  <header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
      
      <!-- Brand mark & Title -->
      <div class="flex items-center gap-6">
        <a class="logo" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
        
        <span class="hidden lg:inline-block font-hand italic text-ink-muted text-[15px] leading-none tracking-normal border-l border-paper-border pl-4 rotate-[-1deg]">
          «Анти-идеальный веб. Структура обнажена. Искренность форм.»
        </span>

        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-5 text-[13.5px] text-ink-muted">
          <a class="text-ink font-medium hover:text-ink transition-colors" href="index.php#articles">Статьи</a>
          <a class="hover:text-ink transition-colors" href="interactive.php#calculator">Калькулятор флюсов</a>
          <a class="hover:text-ink transition-colors" href="interactive.php#table">Таблица припоев</a>
          <a class="hover:text-ink transition-colors" href="interactive.php">Инструменты</a>
        </nav>
      </div>

      <!-- Right Action / Telegram button -->
      <div class="flex items-center gap-3">
        <a class="hidden sm:inline-block text-[12.5px] text-ink-muted hover:text-ink transition-colors font-mono" href="article.php#simulator">
          [↓ к расчёту]
        </a>
        <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-ink bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:bg-ink-muted transition-colors" href="https://t.me/" target="_blank" rel="noopener">
          <span>Клуб / Telegram</span>
          <span class="text-[10px]">↗</span>
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
            <div class="absolute -top-2 left-6 w-10 h-3 bg-[#ebdeb3]/80 border-l border-r border-[#d2c39b]/70 shadow-sm rotate-[-2deg] z-10 pointer-events-none" style="backdrop-filter: blur(1px);"></div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 bg-[#fef08a]/60 border border-[#fde047] text-ink font-mono text-[11px] shadow-sm rotate-[-1.2deg] sketch-border">
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
          <p class="text-lg sm:text-[19px] text-[#33302c] font-serif leading-[1.65]">
            Журнал и открытая документация о том, как превратить кучу разрозненных SMD/BGA компонентов в надежное устройство. Без воды, с интерактивными симуляторами термопрофилей, допусками IPC/JEDEC и проверенными режимами пайки.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-wrap items-center gap-3 pt-2">
            <a class="inline-flex items-center gap-2 px-4 py-2 border border-ink bg-ink text-paper text-xs font-mono font-medium rounded hover:bg-[#33302c] transition-colors" href="article.php">
              <span>Читать главный регламент</span>
              <span class="text-xs">→</span>
            </a>
            <a class="inline-flex items-center gap-2 px-3.5 py-2 border border-paper-border bg-paper text-ink text-xs font-mono font-medium rounded hover:border-ink transition-colors" href="article.php#simulator">
              <span>Инженерный калькулятор</span>
            </a>
            <div class="hidden sm:flex items-center gap-1.5 font-mono text-[11px] text-ink bg-[#fef08a] border border-[#fde047] px-2.5 py-0.5 shadow-sm sketch-border" style="transform: rotate(-0.5deg);">
              <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
              <span class="font-medium">rev 2.4.1 актуализирован</span>
            </div>
          </div>

        </div>
      </section>

      <!-- Category Filter Pills -->
      <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-8 font-mono text-xs" id="articles">
        <a href="index.php?tag=all#articles" class="px-3 py-1 rounded <?= $tag_filter === 'all' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-ink hover:text-ink' ?> transition-all">
          Все материалы
        </a>
        <a href="index.php?tag=basics#articles" class="px-3 py-1 rounded <?= $tag_filter === 'basics' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-ink hover:text-ink' ?> transition-all">
          Основы
        </a>
        <a href="index.php?tag=smd#articles" class="px-3 py-1 rounded <?= $tag_filter === 'smd' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-ink hover:text-ink' ?> transition-all">
          SMD и BGA
        </a>
        <a href="index.php?tag=tools#articles" class="px-3 py-1 rounded <?= $tag_filter === 'tools' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-ink hover:text-ink' ?> transition-all">
          Инструменты
        </a>
        <a href="index.php?tag=materials#articles" class="px-3 py-1 rounded <?= $tag_filter === 'materials' ? 'bg-ink text-paper font-medium' : 'border border-paper-border bg-paper text-ink-muted hover:border-ink hover:text-ink' ?> transition-all">
          Сплавы и флюсы
        </a>
      </div>

      <!-- Main Layout: 8 cols Editorial + 4 cols Sidebar -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        <!-- Left Column: Articles -->
        <div class="lg:col-span-8 space-y-8">
          
          <!-- FEATURED MAIN ARTICLE (Hero Card) -->
          <article class="border border-paper-border rounded-lg bg-white/70 p-6 sm:p-7 space-y-5 shadow-sm transition-all hover:border-paper-border-dark relative" id="featured">
            
            <!-- Image Frame -->
            <div class="overflow-hidden rounded border border-paper-border bg-paper relative">
              <img src="https://lh3.googleusercontent.com/aida/AEtjO1XPXO_7vFBi0-sZRVk7MH6OetXskpHt5Xcf3Ip6nfHDDYM7qdO0nERNQaH_49PYrri281JQZUD0JhgzliwsR7F5Ks8GvSMY32dTxDUIHzZ_P5Drz6niq2ZSyIRirmXvsdrZExlqKeZ_11m0Vf64Fa9fYCMG9SMrAAg0F5hGzsceoEP1ajdrLph6LgFKfgF6aLS30BF8hJJkW20S0l03CIQZ4uc7pmSJ_MFPJyqCHL4KNonVuGSJXGX1rAg" alt="Схема термопрофиля пайки BGA и распределения тепла" class="w-full h-52 sm:h-60 object-cover object-center" loading="lazy">
              <div class="absolute bottom-2 right-2 px-2 py-0.5 bg-paper/90 border border-paper-border text-[10px] font-mono text-ink-muted rounded backdrop-blur-sm">
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
              <p class="text-[#242220] font-serif text-[16px] leading-relaxed">
                Разбираем теплоёмкость текстолита и строим правильную кривую нагрева для BGA-монтажа и сложных многослойных плат. Разбираем, почему стандартная пайка «по цифрам на табло фена» убивает платы, и как выставить 4 фазы термопрофиля.
              </p>
            </div>

            <!-- Technical Flow Strip -->
            <div class="rounded border border-paper-border bg-[#fdfcfa] p-4 flex items-center justify-between font-mono text-xs text-ink-muted overflow-x-auto relative">
              <div class="flex items-center gap-4 shrink-0">
                <span class="text-[#52525b]">Ликвидус SAC305: <strong class="text-ink">217°C</strong></span>
                <span class="text-ink-faint">→</span>
                <span class="text-[#52525b]">ПОС-61: <strong class="text-ink">183°C</strong></span>
                <span class="text-ink-faint">→</span>
                <span class="text-[#52525b]">Окно TAL: <strong class="font-medium text-ink">45–75 с</strong></span>
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
            <article class="border border-paper-border rounded-lg bg-white/80 p-5 flex flex-col justify-between space-y-4 hover:border-ink transition-colors">
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
            <article class="border border-paper-border rounded-lg bg-white/80 p-5 flex flex-col justify-between space-y-4 hover:border-ink transition-colors">
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
            <article class="border border-paper-border rounded-lg bg-white/80 p-5 flex flex-col justify-between space-y-4 hover:border-ink transition-colors">
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
            <article class="border border-paper-border rounded-lg bg-paper p-5 flex flex-col justify-between space-y-4 hover:border-ink transition-colors" id="calc">
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
          <div class="border border-[#fde047] bg-[#fef08a]/40 p-4 rounded-lg space-y-2 text-xs shadow-sm sketch-border">
            <div class="flex items-center justify-between font-mono text-[10.5px] uppercase font-bold text-ink border-b border-[#fde047] pb-1.5">
              <span class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
                Заметка на верстак
              </span>
              <span class="text-[10px] text-ink-muted font-normal">LAB-QC</span>
            </div>
            <div class="space-y-1.5">
              <p class="font-hand text-[17px] leading-snug text-ink italic font-semibold rotate-[-0.5deg]">
                «Термопару фиксировать <span class="font-mono font-bold text-xs px-1.5 py-0.5 bg-[#fef08a] border border-[#fde047] not-italic inline-block">строго каптоном</span> прямо к галтелям BGA, иначе датчик меряет воздух фена!»
              </p>
              <p class="font-serif italic text-[11.5px] text-[#52525b] border-t border-[#fde047]/60 pt-1">
                — из полевого блокнота инженера: структура обнажена, без идеализации
              </p>
            </div>
            <div class="pt-1 flex items-center justify-between font-mono text-[10px] text-ink-muted">
              <span>Контроль t°C</span>
              <span class="font-semibold text-ink">J-STD-020E</span>
            </div>
          </div>

          <!-- Sections list -->
          <div class="relative border border-paper-border rounded-lg bg-white/80 p-5 space-y-3.5 shadow-sm">
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
              <span class="text-[10px] text-ink bg-paper-subtle border border-paper-border-dark px-2 py-0.5 font-mono font-bold inline-block sketch-border" style="transform: rotate(0.6deg);">LIVE</span>
            </div>
            <p class="text-ink-muted leading-relaxed">
              Практические замеры, отчеты дефектов пайки и тесты термоинтерфейсов в инженерном канале.
            </p>
            <a class="inline-block pt-1 font-mono text-[11.5px] font-semibold text-ink underline decoration-ink decoration-2 hover:decoration-ink-muted" href="https://t.me/" target="_blank" rel="noopener">
              Канал в Telegram →
            </a>
          </div>

          <!-- Tape & Caution Sketch Box -->
          <div class="relative p-3.5 bg-[#fdfcfa] border-2 border-dashed border-ink/70 rotate-[1.2deg] shadow-sm space-y-1.5 sketch-border">
            <div class="absolute -top-2.5 right-8 w-12 h-3.5 bg-[#ebdeb3]/80 border-l border-r border-[#d2c39b]/80 shadow-sm rotate-[-3deg] pointer-events-none" style="backdrop-filter: blur(1px);"></div>
            <div class="flex items-center justify-between font-mono text-[10.5px] font-bold text-ink">
              <span class="flex items-center gap-1.5">
                <span class="text-xs">⚡</span>НЕ ГРЕТЬ ВЫШЕ 245°C
              </span>
              <span class="px-1.5 py-0.2 text-[9.5px] bg-paper-subtle border border-paper-border-dark">J-STD</span>
            </div>
            <p class="font-mono text-[11px] text-[#52525b] leading-tight">
              Деградация подложки и интерметаллидов начинается через 8 секунд перегрева.
            </p>
          </div>

          <!-- Reference Temperatures Box -->
          <div class="border border-paper-border rounded-lg bg-white/70 p-5 space-y-3 relative">
            <div class="flex items-center justify-between">
              <div class="text-[11px] font-mono text-ink-faint uppercase">Опорные температуры:</div>
              <span class="font-mono text-[10px] text-ink-faint">FIG. 1.2 // REWORK</span>
            </div>
            <div class="space-y-2 font-mono text-xs">
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">SAC305 (ликвидус)</span>
                <span class="font-bold text-ink px-2 py-0.5 bg-paper-subtle border border-paper-border-dark text-[11px] inline-block sketch-border" style="transform: rotate(0.4deg);">217°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">ПОС-61 (эвтектика)</span>
                <span class="font-bold text-ink px-2 py-0.5 bg-[#fef08a] border border-[#fde047] text-[11px] inline-block sketch-border" style="transform: rotate(-0.5deg);">183°C</span>
              </div>
              <div class="flex items-center justify-between pb-1.5 border-b border-paper-border">
                <span class="text-ink-muted">Sn42Bi58 (низкотемп.)</span>
                <span class="font-medium text-ink text-[11px]">138°C</span>
              </div>
              <div class="flex items-center justify-between p-2 bg-[#fdfcfa] border border-paper-border rounded">
                <span class="text-ink font-medium flex items-center gap-1.5">
                  <span class="w-1.5 h-1.5 rounded-full bg-ink"></span>
                  Макс. пик кристалла
                </span>
                <span class="font-bold text-ink px-2.5 py-0.5 bg-paper-subtle border border-paper-border-dark text-[11px] inline-block shadow-sm sketch-border" style="transform: rotate(-0.6deg);">245°C</span>
              </div>
            </div>

            <!-- Hand drawn sketch doodle of BGA package with dimensions -->
            <div class="pt-2 border-t border-paper-border flex items-center justify-between text-ink-faint font-mono text-[10px]">
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

      <!-- Bottom Telegram CTA -->
      <div class="mt-12 border border-paper-border rounded-lg bg-white/80 p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="space-y-0.5">
          <div class="font-semibold text-sm text-ink">Инженерный справочник по пайке BGA</div>
          <p class="text-xs text-ink-muted">Таблицы термопрофилей, допуски IPC-A-610 и подбор флюсов в нашем канале.</p>
        </div>
        <a class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 border border-ink bg-ink text-paper text-xs font-mono font-medium rounded hover:bg-ink-muted transition-colors shrink-0 shadow-sm" href="https://t.me/" target="_blank" rel="noopener">
          <span>Забрать в Telegram →</span>
        </a>
      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <footer class="w-full border-t border-paper-border bg-paper py-10 text-ink-muted text-xs font-mono">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
      <div class="space-y-1.5">
        <div class="flex items-center gap-2 text-ink font-semibold">
          <a class="logo" href="index.php">ТОЧКА<span>.</span>ПЛАВЛЕНИЯ</a>
          <span class="text-ink-faint">·</span>
          <span class="text-[11px] font-normal text-ink-faint">Инженерный регламент v2.4</span>
        </div>
        <p class="text-[12px] text-ink-muted max-w-md">
          Инженерный справочник, регламенты поверхностного монтажа и открытая документация по пайке и теплофизике компонентов.
        </p>
        <div class="text-ink-faint text-[11px] pt-1">
          © <?= date('Y') ?> ТОЧКА ПЛАВЛЕНИЯ. Все права защищены.
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
        <a class="hover:text-ink transition-colors" href="index.php#articles">Статьи</a>
        <a class="hover:text-ink transition-colors" href="interactive.php#table">Реестр сплавов</a>
        <a class="hover:text-ink transition-colors" href="article.php#simulator">Калькулятор</a>
        <a class="hover:text-ink transition-colors" href="article.php">Регламенты</a>
        <a class="hover:text-ink transition-colors" href="https://t.me/" target="_blank" rel="noopener">Telegram</a>
      </div>
    </div>
  </footer>

</body>
</html>
