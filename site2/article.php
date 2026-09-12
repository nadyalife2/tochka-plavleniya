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
    "Какая максимальная температура допустима для бессвинцовой пайки BGA?" => "По стандарту IPC/JEDEC J-STD-020D абсолютный пиковый предел для большинства полупроводниковых BGA-корпусов составляет 250°C (не более 10 секунд). Оптимальный рабочий пик оплавления — ровно 238–242°C.",
    "Сколько секунд припой должен находиться в расплавленном состоянии (TAL)?" => "Время над ликвидусом (TAL) для сплава SAC305 (217°C) должно составлять от 45 до 75 секунд. Если TAL меньше 40 с — возможны непропаи («холодная пайка»). Если дольше 90 с — интерметаллический слой становится слишком толстым и хрупким, контакт отрывается при вибрации.",
    "Как избежать эффекта 'попкорна' при пайке влажных микросхем?" => "Храните чипы в заводских вакуумных влагозащитных пакетах с индикатором влажности. Если пакет вскрыт, поместите микросхемы в конвекционную печь при 100–110°C на 12–24 часа до пайки.",
    "Какой флюс использовать для реболлинга BGA: RMA или No-Clean?" => "Для реболлинга шаров рекомендуется канифольный среднеактивированный флюс (RMA или безотмывочный ROL0 с высокой вязкостью). Подложки BGA требуют тщательной промывки изопропанолом или спецраствором в ультразвуковой ванне после посадки."
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?= e($article['title']) ?> — ТОЧКА ПЛАВЛЕНИЯ</title>

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
<meta name="description" content="<?= e($article['excerpt']) ?>"/>

<!-- OpenGraph -->
<meta property="og:type" content="article"/>
<meta property="og:title" content="<?= e($article['title']) ?>"/>
<meta property="og:description" content="<?= e($article['excerpt']) ?>"/>
<meta property="og:site_name" content="ТОЧКА ПЛАВЛЕНИЯ"/>

<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@700;900&family=IBM+Plex+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=JetBrains+Mono:wght@400;500;600&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,400&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        paper: 'var(--color-paper)',
        'paper-subtle': 'var(--color-paper-subtle)',
        'paper-border': 'var(--color-paper-border)',
        'paper-border-dark': 'var(--color-paper-border-dark)',
        ink: 'var(--color-ink)',
        'ink-muted': 'var(--color-ink-muted)',
        'ink-faint': 'var(--color-ink-faint)',
        highlight: '#fef9c3',
        'highlight-strong': '#fef08a',
        callout: 'var(--color-callout)',
        card: 'var(--color-card)'
      },
      fontFamily: {
        sans: ['"IBM Plex Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        serif: ['Newsreader', 'Georgia', 'serif'],
        mono: ['"JetBrains Mono"', '"IBM Plex Mono"', 'monospace'],
        logo: ['"Hanken Grotesk"', 'sans-serif']
      }
    }
  }
}
</script>

<style>
  :root {
    --color-paper: #faf8f5;
    --color-paper-subtle: #f3f0ea;
    --color-paper-border: #e6e2da;
    --color-paper-border-dark: #d3cdc2;
    --color-ink: #141414;
    --color-ink-muted: #6b665f;
    --color-ink-faint: #9e988f;
    --color-callout: #fcfaf2;
    --color-card: rgba(255, 255, 255, 0.75);
    --dot-color: #d3cdc2;
    --bg-color: #faf8f5;
  }

  html.dark {
    --color-paper: #12141a;
    --color-paper-subtle: #191c24;
    --color-paper-border: #282d3b;
    --color-paper-border-dark: #373e52;
    --color-ink: #f3f4f6;
    --color-ink-muted: #9ca3af;
    --color-ink-faint: #6b7280;
    --color-callout: #1b1f2b;
    --color-card: rgba(24, 27, 36, 0.85);
    --dot-color: #2b3142;
    --bg-color: #12141a;
  }

  body {
    background-color: var(--bg-color);
    background-image: radial-gradient(var(--dot-color) 0.9px, transparent 0.9px);
    background-size: 20px 20px;
    color: var(--color-ink);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  ::selection {
    background-color: #fef08a;
    color: #141414;
  }
  /* REALISTIC HAND-DRAWN FELT-TIP MARKER STROKES (Vecteezy / PNG style) */
  mark, .marker-yellow {
    position: relative;
    display: inline;
    background: transparent;
    color: #141414;
    padding: 0.18em 0.55em 0.22em 0.5em;
    margin: 0 -0.15em;
    box-decoration-break: clone;
    -webkit-box-decoration-break: clone;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 24' preserveAspectRatio='none'%3E%3Cpath d='M0.5 4.5 C 18 2, 48 5.5, 98.5 2.5 C 100 11, 98 18.5, 99.5 22 C 75 23.5, 30 20.5, 1.5 22 C 0 16, 1.5 8.5, 0.5 4.5 Z' fill='%23fef08a' fill-opacity='0.9'/%3E%3Cpath d='M3 7.5 C 25 5.5, 68 6, 96.5 5 C 97.5 12.5, 95 18, 97 20 C 70 21.5, 28 19.5, 4 19.5 Z' fill='%23fde047' fill-opacity='0.55'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    font-weight: 600;
  }
  html.dark mark, html.dark .marker-yellow {
    color: #fef08a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 24' preserveAspectRatio='none'%3E%3Cpath d='M0.5 4.5 C 18 2, 48 5.5, 98.5 2.5 C 100 11, 98 18.5, 99.5 22 C 75 23.5, 30 20.5, 1.5 22 C 0 16, 1.5 8.5, 0.5 4.5 Z' fill='%23ca8a04' fill-opacity='0.45'/%3E%3Cpath d='M3 7.5 C 25 5.5, 68 6, 96.5 5 C 97.5 12.5, 95 18, 97 20 C 70 21.5, 28 19.5, 4 19.5 Z' fill='%23eab308' fill-opacity='0.3'/%3E%3C/svg%3E");
  }

  /* Hand-drawn yellow marker pill */
  .sketch-pill-yellow {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: transparent;
    color: #141414;
    padding: 0.22rem 0.75rem 0.26rem 0.7rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 4 C 20 1.5, 68 3, 98 2 C 100 11.5, 98 19.5, 99 25.5 C 72 26.5, 26 24.5, 1 25.5 C 0 16.5, 1.5 8, 1.5 4 Z' fill='%23fef08a' fill-opacity='0.95' stroke='%23ca8a04' stroke-width='0.9' stroke-dasharray='40 1 20 1' stroke-linecap='round'/%3E%3Cpath d='M4 7 C 28 5, 72 5.5, 95.5 5 C 96.5 12, 94.5 18.5, 96 21.5 C 68 22.5, 26 21.5, 3 21.5 Z' fill='%23fde047' fill-opacity='0.65'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    transform: rotate(-0.9deg);
    transition: transform 0.15s ease;
  }
  .sketch-pill-yellow:hover {
    transform: rotate(0deg) scale(1.03);
  }
  html.dark .sketch-pill-yellow {
    color: #fef08a;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 4 C 20 1.5, 68 3, 98 2 C 100 11.5, 98 19.5, 99 25.5 C 72 26.5, 26 24.5, 1 25.5 C 0 16.5, 1.5 8, 1.5 4 Z' fill='%23ca8a04' fill-opacity='0.4' stroke='%23eab308' stroke-width='0.9' stroke-dasharray='40 1 20 1' stroke-linecap='round'/%3E%3Cpath d='M4 7 C 28 5, 72 5.5, 95.5 5 C 96.5 12, 94.5 18.5, 96 21.5 C 68 22.5, 26 21.5, 3 21.5 Z' fill='%23a16207' fill-opacity='0.4'/%3E%3C/svg%3E");
  }

  /* Hand-drawn light-gray felt marker pill */
  .sketch-pill-gray {
    position: relative;
    display: inline-flex;
    align-items: center;
    background: transparent;
    color: #141414;
    padding: 0.22rem 0.75rem 0.26rem 0.7rem;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 3 C 22 1.5, 62 4, 98 2 C 99.5 11.5, 98 19.5, 99 25.5 C 70 26.5, 25 24.5, 1 25.5 C 0.5 17, 0 8.5, 1.5 3 Z' fill='%23e9e5dc' fill-opacity='0.95' stroke='%23b8b1a2' stroke-width='0.9' stroke-dasharray='35 1 25 1' stroke-linecap='round'/%3E%3Cpath d='M4 6.5 C 30 5, 75 5.5, 95 4.5 C 96 12, 95 18, 96 22 C 68 23, 30 21.5, 3 22 Z' fill='%23ded8cd' fill-opacity='0.6'/%3E%3C/svg%3E");
    background-size: 100% 100%;
    background-repeat: no-repeat;
    transform: rotate(0.6deg);
    transition: transform 0.15s ease;
  }
  .sketch-pill-gray:hover {
    transform: rotate(0deg) scale(1.03);
  }
  html.dark .sketch-pill-gray {
    color: #f3f4f6;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 28' preserveAspectRatio='none'%3E%3Cpath d='M1.5 3 C 22 1.5, 62 4, 98 2 C 99.5 11.5, 98 19.5, 99 25.5 C 70 26.5, 25 24.5, 1 25.5 C 0.5 17, 0 8.5, 1.5 3 Z' fill='%23222735' fill-opacity='0.95' stroke='%233e465c' stroke-width='0.9' stroke-dasharray='35 1 25 1' stroke-linecap='round'/%3E%3Cpath d='M4 6.5 C 30 5, 75 5.5, 95 4.5 C 96 12, 95 18, 96 22 C 68 23, 30 21.5, 3 22 Z' fill='%232c3346' fill-opacity='0.6'/%3E%3C/svg%3E");
  }

  /* Hand-drawn marker underline */
  .marker-underline {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 12' preserveAspectRatio='none'%3E%3Cpath d='M1 8 C 25 3, 60 10, 99 5 C 75 11, 30 7, 2 10 Z' fill='%23facc15' fill-opacity='0.85'/%3E%3C/svg%3E");
    background-position: 0 100%;
    background-size: 100% 0.35em;
    background-repeat: no-repeat;
    padding-bottom: 0.1em;
  }

  .custom-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
  }
  .custom-scrollbar::-webkit-scrollbar-thumb {
    background: var(--color-paper-border-dark);
    border-radius: 2px;
  }

  /* USER'S ORIGINAL BRAND LOGO */
  .logo { 
    font-family: 'Hanken Grotesk', 'Inter', sans-serif; 
    font-size: 1.25rem; 
    font-weight: 900; 
    text-decoration: none; 
    color: var(--color-ink); 
    letter-spacing: -0.02em; 
    text-transform: uppercase; 
    display: inline-flex; 
    align-items: center; 
    line-height: 1; 
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
</style>

<!-- Schema.org JSON-LD -->
<?= render_article_schema($article, $faq_items) ?>
</head>

<body class="font-sans min-h-screen flex flex-col justify-between text-[15px] leading-[1.65]">

<!-- Top Minimal Header Bar (tochkicamp style) -->
<header class="w-full border-b border-paper-border sticky top-0 z-40 bg-paper/95 backdrop-blur-sm">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 h-14 flex items-center justify-between gap-6">
    <!-- Brand mark & Title -->
    <div class="flex items-center gap-6">
      <a class="logo hover:opacity-85 transition-opacity" href="index.php">
        ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
      </a>
      <!-- Desktop Nav -->
      <nav class="hidden md:flex items-center gap-5 text-[13.5px] text-ink-muted">
        <a class="text-ink font-medium hover:text-ink transition-colors" href="index.php">Статьи</a>
        <a class="hover:text-ink transition-colors" href="interactive.php">Калькулятор флюсов</a>
        <a class="hover:text-ink transition-colors" href="interactive.php#solder-table">Таблица припоев</a>
        <a class="hover:text-ink transition-colors" href="interactive.php">Инструменты</a>
      </nav>
    </div>
    <!-- Right Action -->
    <div class="flex items-center gap-3">
      <a class="hidden sm:inline-block text-[12.5px] text-ink-muted hover:text-ink transition-colors font-mono" href="#simulator">
        [↓ к расчёту]
      </a>
      
      <!-- Theme Switcher Button -->
      <button id="theme-toggle" type="button" class="p-1.5 px-2 rounded border border-paper-border bg-paper hover:border-paper-border-dark text-ink font-mono text-xs flex items-center gap-1.5 transition-colors" title="Переключить тему (Светлая / Тёмная)" aria-label="Переключить тему">
        <span class="dark:hidden">🌙</span>
        <span class="hidden dark:inline">☀️</span>
        <span class="hidden sm:inline text-[11px] text-ink-muted dark:text-ink-faint font-mono font-medium">Тема</span>
      </button>

      <a class="inline-flex items-center gap-1.5 px-3 py-1 border border-paper-border-dark dark:border-paper-border bg-ink text-paper text-[12.5px] font-mono font-medium rounded hover:opacity-90 transition-opacity" href="interactive.php">
        <span>Верстак / Тулзы</span>
        <span class="text-[10px]">⚙</span>
      </a>
    </div>
  </div>
</header>

<!-- Main Container -->
<main class="w-full flex-grow pt-8 pb-20">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8">
    
    <!-- Breadcrumbs -->
    <nav class="text-[12px] font-mono text-ink-faint mb-8 flex items-center gap-1.5 flex-wrap">
      <a class="hover:text-ink transition-colors" href="index.php">Главная</a>
      <span>→</span>
      <a class="hover:text-ink transition-colors" href="index.php?tag=<?= e($article['tag_key'] ?? 'bga') ?>">Гайды</a>
      <span>→</span>
      <span class="text-ink truncate max-w-xs sm:max-w-md"><?= e($article['title']) ?></span>
    </nav>

    <!-- Center Article + Table of Contents Layout -->
    <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-14 items-start">
      
      <!-- Central Editorial Column (740px wide max) -->
      <div class="lg:col-span-8 max-w-[730px] space-y-9">
        
        <!-- Retro Illustration / Stamp Badge -->
        <div class="w-12 h-12 rounded border border-paper-border bg-paper-subtle flex items-center justify-center text-ink">
          <svg fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" viewBox="0 0 24 24" width="24">
            <rect height="18" rx="2" width="18" x="3" y="3"></rect>
            <path d="M8 7v10"></path>
            <path d="M16 7v10"></path>
            <path d="M12 12h.01"></path>
            <circle cx="12" cy="7" r="1"></circle>
            <circle cx="12" cy="17" r="1"></circle>
          </svg>
        </div>

        <!-- Article Header Block -->
        <header class="space-y-4">
          <h1 class="text-3xl sm:text-[38px] font-bold text-ink tracking-[-0.03em] leading-[1.18] font-sans">
            <?= e($article['title']) ?>
          </h1>

          <!-- Meta line -->
          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-ink-muted font-mono pt-1 pb-2">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full border border-paper-border bg-paper-subtle text-[10px] flex items-center justify-center font-bold text-ink">ТП</span>
              <span class="text-ink"><?= e($article['author'] ?? 'Инженер Лаборатории ТЧП') ?></span>
            </div>
            <span class="text-ink-faint">·</span>
            <time datetime="<?= e($article['date'] ?? '2026-08-20') ?>">Обновлено <?= e($article['date'] ?? '20 августа 2026') ?></time>
            <span class="text-ink-faint">·</span>
            <span>~8 мин чтения</span>
            <span class="text-ink-faint">·</span>
            <span class="bg-paper-subtle px-1.5 py-0.5 rounded text-[11px] border border-paper-border">IPC/JEDEC J-STD-020D</span>
          </div>

          <!-- Lead Paragraph -->
          <p class="text-lg text-ink font-serif leading-[1.65] pt-2 text-[#242220]">
            <?= e($article['excerpt']) ?> Разбираем, почему стандартная пайка «по цифрам на табло фена» гарантированно убивает многослойные платы, как выставить 4 фазы термопрофиля по стандарту IPC/JEDEC и не допустить коробления текстолита.
          </p>
        </header>

        <!-- Callout: "Как читать этот регламент" (tochkicamp style box) -->
        <div class="border border-paper-border border-l-4 border-l-ink bg-paper-subtle/50 p-5 rounded-lg text-[13.5px] leading-relaxed space-y-2">
          <div class="text-[11px] font-mono font-semibold uppercase tracking-wider text-ink">
            КАК ЧИТАТЬ ЭТОТ РЕГЛАМЕНТ
          </div>
          <p class="text-ink/80">
            Если вы настраиваете термопрофиль под конкретный чип, сразу переходите к <a class="underline decoration-ink/40 underline-offset-2 hover:decoration-ink text-ink font-medium" href="#simulator">симулятору 4 фаз</a> или <a class="underline decoration-ink/40 underline-offset-2 hover:decoration-ink text-ink font-medium" href="#alloys">температурным окнам сплавов</a>. Все значения температур верифицированы контактными термопарами К-типа.
          </p>
        </div>

        <!-- Section 1 -->
        <section class="scroll-mt-20 pt-4 space-y-4" id="step-1">
          <h2 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
            1. Теплоемкость текстолита и почему фен всегда врет
          </h2>
          <p class="text-ink/85">
            Стандартная ошибка при пайке сложных чипов — выставлять на станции фиксированные 350°C и греть плату сверху. Датчик станции измеряет температуру спирали внутри фена, а на поверхности текстолита и тем более под корпусом BGA температура оказывается <mark>на 80–120°C ниже</mark>.
          </p>
          
          <!-- Inline quote/highlight line -->
          <div class="relative border-l-4 border-ink bg-paper-subtle/70 pr-4 pl-5 py-3.5 my-4 rounded-r shadow-sm">
            <div class="text-lg sm:text-[21px] font-serif italic text-ink leading-relaxed tracking-[-0.01em]">«Температура на сопле фена не имеет ничего общего с температурой припоя под чипом. Без замера на плате вы паяете вслепую».</div>
            <div class="mt-2 font-serif italic text-[13px] text-ink-muted flex items-center gap-1.5"><span class="font-mono not-italic text-xs text-ink">—</span> заметка на полях лабораторного журнала</div>
          </div>

          <p class="text-ink/85">
            Плата состоит из 6–12 слоев FR-4 и сплошных полигонов питания/земли (GND). Медь моментально отводит тепло от точки нагрева. Попытка прогреть только верх чипа создает мощный тепловой градиент: текстолит изгибается «лодочкой» (<span class="font-mono text-[13px] bg-paper-subtle px-1 rounded">warpage</span>), а центральные шарики слипаются в короткое замыкание.
          </p>

          <!-- Рис. 1: Эскиз термического градиента из Stitch -->
          <figure class="my-6 rounded-lg border border-paper-border-dark bg-white/80 p-2.5 sm:p-3 shadow-sm space-y-2.5">
            <div class="overflow-hidden rounded border border-paper-border bg-[#fdfcfa]">
              <img src="https://lh3.googleusercontent.com/aida/AEtjO1VPsrSrCUy54hlNvHq3-Bi3BKtsJ-HwVhUxUONjwD8ZW0JSYUhhCuE48K8uFAbVhm1l6z5bcfKlnWxsiJr8i-lI5dRkG6Iq2l-n00la6L6BC75KwJkw-kUiJ47GXICVho8XMATZw8RLlRI3tpSAkvmZT52hn5vSTCl2e71Md3Q3zGQ48s2w26JMOB_rnaw7fgtN-WHJUqkwlP4tX_D5R9x006Zys-oVO1_EwhYmZFvSU24W0G04DQtmqfA" alt="Эскиз термического градиента при локальном нагреве соплом фена" class="w-full h-auto block object-cover" loading="lazy">
            </div>
            <figcaption class="px-1 text-xs font-mono text-ink-muted leading-relaxed flex items-center justify-between">
              <span><span class="text-ink font-semibold">Рис. 1.</span> Эскиз термического градиента при локальном нагреве соплом фена.</span>
              <span class="hidden sm:inline-block text-[11px] text-ink-faint uppercase">IPC/JEDEC</span>
            </figcaption>
          </figure>

          <!-- Minimal Specs Flow Chips -->
          <div class="py-2">
            <div class="text-[11px] font-mono text-ink-faint uppercase mb-2">Опорные значения стандарта IPC/JEDEC:</div>
            <div class="flex flex-wrap items-center gap-2 font-mono text-xs">
              <span class="sketch-pill-gray text-ink">Ликвидус SAC305: <span class="font-bold">217°C</span></span>
              <span class="text-ink-faint">→</span>
              <span class="sketch-pill-yellow text-ink">ПОС-61: <span class="font-bold">183°C</span></span>
              <span class="text-ink-faint">→</span>
              <span class="sketch-pill-gray text-ink">Окно TAL: <span class="font-bold">45–75 с</span></span>
              <span class="text-ink-faint">→</span>
              <span class="sketch-pill-gray text-ink">Пик: <span class="font-bold">до 245°C</span></span>
            </div>
          </div>
        </section>

        <hr class="border-paper-border my-8"/>

        <!-- Section 2: 4 Stages + Interactive Calculator -->
        <section class="scroll-mt-20 space-y-6" id="step-2">
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
              2. Четыре фазы кривой пайки (Интерактивный расчет)
            </h2>
            <p class="text-ink/85 mt-2">
              Правильный термопрофиль по стандарту <code class="font-mono text-xs font-medium px-1.5 py-0.5 bg-paper-subtle border border-paper-border rounded text-ink">J-STD-020D</code> сводится к четырехступенчатому контролируемому циклу:
            </p>
            <div class="my-3 inline-flex flex-wrap items-center gap-3 p-2.5 rounded border border-ink/40 bg-callout transform -rotate-[0.5deg] text-xs font-mono text-ink shadow-sm">
              <div class="flex items-center gap-1.5 px-2 py-0.5 bg-ink text-paper rounded text-[10px] font-bold tracking-wider uppercase"><span>QC PASSED</span><span>✓</span></div>
              <div class="text-ink text-[11.5px] font-semibold tracking-tight">J-STD-020E // COMPLIANT</div>
              <span class="text-ink-faint text-[10px] hidden sm:inline-block">|</span>
              <div class="text-ink-muted text-[11px] font-sans flex items-center gap-1"><span class="text-[#854d0e] font-bold font-mono">⚠</span>ВНИМАНИЕ: ОПАСНОСТЬ ДЕЛАМИНАЦИИ ПРИ СКОРОСТИ > 3°C/с</div>
            </div>
          </div>

          <!-- Clean 2x2 Minimal Stage Overview -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-sans">
            <div class="p-4 bg-paper rounded border border-paper-border space-y-1.5">
              <div class="flex items-center justify-between text-xs font-mono pb-1">
                <span class="px-1.5 py-0.5 text-[10px] uppercase font-mono text-ink-muted bg-paper-subtle border border-paper-border rounded">ФАЗА 1</span>
                <span class="text-ink-muted font-mono text-xs">1–3°C/с</span>
              </div>
              <div class="font-semibold text-ink text-sm">Прогрев (Preheat)</div>
              <p class="text-[13px] text-ink-muted leading-snug">
                Плавный подъем до 150°C для испарения легких фракций растворителя флюса.
              </p>
            </div>
            <div class="p-4 bg-paper rounded border border-paper-border space-y-1.5">
              <div class="flex items-center justify-between text-xs font-mono pb-1">
                <span class="px-1.5 py-0.5 text-[10px] uppercase font-mono text-ink-muted bg-paper-subtle border border-paper-border rounded">ФАЗА 2</span>
                <span class="text-ink-muted font-mono text-xs">150–200°C</span>
              </div>
              <div class="font-semibold text-ink text-sm">Активация (Soak)</div>
              <p class="text-[13px] text-ink-muted leading-snug">
                Выравнивание температурного поля чипа и платы, удаление поверхностных оксидов.
              </p>
            </div>
            <div class="p-4 bg-[#fcfaf2] rounded border border-ink/40 space-y-1.5">
              <div class="flex items-center justify-between text-xs font-mono pb-1">
                <span class="inline-block px-1.5 py-0.5 text-[10px] uppercase font-mono font-semibold text-ink border transform -rotate-[0.5deg]" style="border-radius: 255px 15px 225px / 15px 225px 15px 255px; background-color: rgb(236, 233, 223); border-color: rgb(220, 215, 203); color: rgb(28, 25, 23);">ФАЗА 3 · КЛЮЧЕВАЯ</span>
                <span class="font-semibold text-ink font-mono text-xs">Пик 235–245°C</span>
              </div>
              <div class="font-semibold text-ink text-sm">Оплавление (Reflow)</div>
              <p class="text-[13px] text-ink-muted leading-snug">
                Время TAL над ликвидусом 45–75 сек. Формирование интерметаллического слоя.
              </p>
            </div>
            <div class="p-3.5 rounded border border-paper-border bg-paper">
              <div class="flex items-center justify-between text-xs font-mono text-ink-faint pb-1">
                <span>ФАЗА 4</span>
                <span>2–4°C/с</span>
              </div>
              <div class="font-semibold text-ink text-sm">Охлаждение (Cooling)</div>
              <p class="text-[13px] text-ink-muted mt-1 leading-snug">
                Контролируемый спад для мелкозернистой кристаллической структуры галтели.
              </p>
            </div>
          </div>

          <!-- SIMULATOR WIDGET (Minimal editorial aesthetic from Stitch) -->
          <div class="border border-paper-border rounded-lg bg-paper-subtle/70 p-5 sm:p-6 space-y-5" id="simulator">
            <div class="flex flex-col sm:flex-row sm:items-baseline justify-between gap-3 pb-2 border-b border-paper-border">
              <div>
                <div class="text-[11px] font-mono text-ink-faint uppercase tracking-wider">Инженерный калькулятор</div>
                <h3 class="text-lg font-bold text-ink">Профиль термостола и фена</h3>
              </div>
              
              <!-- Alloy pills -->
              <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs" id="alloy-selector">
                <button class="alloy-btn px-2.5 py-1 rounded border border-ink bg-ink text-paper transition-all font-medium" data-alloy="sac305" type="button">
                  SAC305 (217°C)
                </button>
                <button class="alloy-btn px-2.5 py-1 rounded border border-paper-border bg-paper text-ink hover:border-ink transition-all" data-alloy="pos61" type="button">
                  ПОС-61 (183°C)
                </button>
                <button class="alloy-btn px-2.5 py-1 rounded border border-paper-border bg-paper text-ink hover:border-ink transition-all" data-alloy="sn42" type="button">
                  Sn42Bi58 (138°C)
                </button>
              </div>
            </div>

            <!-- Phase Switcher Tabs -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-left" id="stage-tabs">
              <button class="stage-btn p-2.5 rounded border border-paper-border bg-paper text-left transition-all hover:border-paper-border-dark" data-stage="0" type="button">
                <div class="text-[10px] font-mono text-ink-faint">01 ПРОГРЕВ</div>
                <div class="text-xs font-mono text-ink mt-0.5" id="tab-temp-0">25°C → 150°C</div>
              </button>
              <button class="stage-btn p-2.5 rounded border border-paper-border bg-paper text-left transition-all hover:border-paper-border-dark" data-stage="1" type="button">
                <div class="text-[10px] font-mono text-ink-faint">02 АКТИВАЦИЯ</div>
                <div class="text-xs font-mono text-ink mt-0.5" id="tab-temp-1">150°C → 190°C</div>
              </button>
              <button class="stage-btn p-2.5 rounded border border-ink bg-callout text-left transition-all shadow-sm" data-stage="2" type="button">
                <div class="text-[10px] font-mono text-ink font-semibold">03 ОПЛАВЛЕНИЕ *</div>
                <div class="text-xs font-mono text-ink font-semibold mt-0.5" id="tab-temp-2">217°C → 240°C</div>
              </button>
              <button class="stage-btn p-2.5 rounded border border-paper-border bg-paper text-left transition-all hover:border-paper-border-dark" data-stage="3" type="button">
                <div class="text-[10px] font-mono text-ink-faint">04 ОХЛАЖДЕНИЕ</div>
                <div class="text-xs font-mono text-ink mt-0.5" id="tab-temp-3">240°C → 100°C</div>
              </button>
            </div>

            <!-- Detailed parameters card -->
            <div class="bg-paper p-4 rounded border border-paper-border space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3 pb-2 border-b border-paper-border">
                <div class="flex items-center gap-2">
                  <span class="text-xs font-mono px-1.5 py-0.5 bg-ink text-paper rounded font-bold" id="detail-tag">ФАЗА 03</span>
                  <span class="font-semibold text-ink text-[15px]" id="detail-title">Оплавление (Reflow) — 217°C → 240°C</span>
                </div>
                <button class="font-mono text-xs text-ink-muted hover:text-ink underline decoration-paper-border-dark flex items-center gap-1" id="copy-stage-btn" type="button">
                  <span id="copy-btn-text">Скопировать параметры</span>
                </button>
              </div>

              <!-- Numerical parameters grid -->
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-mono">
                <div class="p-2 bg-paper-subtle rounded border border-paper-border">
                  <div class="text-ink-faint text-[10px]">СКОРОСТЬ / TAL</div>
                  <div class="text-ink font-semibold mt-1" id="detail-speed">TAL: 45 — 75 сек</div>
                </div>
                <div class="p-2 bg-paper-subtle rounded border border-paper-border">
                  <div class="text-ink-faint text-[10px]">ДЛИТЕЛЬНОСТЬ</div>
                  <div class="text-ink font-semibold mt-1" id="detail-duration">Пик: 10 — 20 сек</div>
                </div>
                <div class="p-2 bg-paper-subtle rounded border border-paper-border">
                  <div class="text-ink-faint text-[10px]">НИЖНИЙ ПОДОГРЕВ</div>
                  <div class="text-ink font-semibold mt-1" id="detail-bottom">170°C — 185°C</div>
                </div>
                <div class="p-2 bg-paper-subtle rounded border border-paper-border">
                  <div class="text-ink-faint text-[10px]">СОПЛО ФЕНА</div>
                  <div class="text-ink font-semibold mt-1" id="detail-top">255°C (35 л/мин)</div>
                </div>
              </div>

              <!-- Text notes -->
              <div class="text-[13px] text-ink-muted space-y-2 pt-1">
                <div>
                  <strong class="text-ink font-medium">Физика процесса:</strong>
                  <span id="detail-desc">Полный переход шариков в жидкую фазу, смачивание контактных площадок и диффузионный рост интерметаллического слоя Cu6Sn5.</span>
                </div>
                <div class="p-3 rounded border border-paper-border bg-paper-subtle/50 text-[13px] leading-relaxed flex items-start gap-2.5 text-ink/85 mt-2">
                  <span class="font-mono text-[11px] uppercase tracking-wider text-ink font-semibold shrink-0 pt-0.5">Внимание:</span>
                  <span id="detail-warning" class="text-ink/80">Превышение 248–250°C на кристалле ведет к деламинации кремниевой подложки и необратимому перегреву.</span>
                </div>
              </div>
            </div>

          </div>
        </section>

        <hr class="border-paper-border my-8"/>

        <!-- Section 3: Alloy Temperature Windows -->
        <section class="scroll-mt-20 space-y-5" id="alloys">
          <div>
            <h2 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
              3. Температурные окна основных паяльных сплавов
            </h2>
            <p class="text-ink/85 mt-2">
              Подбирайте температурный коридор в зависимости от металлургии используемого припоя:
            </p>
          </div>

          <!-- 4 Minimalist Alloy Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            
            <!-- SAC305 -->
            <div class="p-4 rounded border border-paper-border bg-paper flex flex-col justify-between space-y-3 shadow-sm">
              <div class="space-y-2">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="text-[11px] font-mono text-ink-faint uppercase font-semibold">Бессвинцовый стандарт</div>
                    <h3 class="font-bold text-base text-ink">SAC305</h3>
                    <div class="text-xs font-mono text-ink-muted">Sn96.5 Ag3.0 Cu0.5</div>
                  </div>
                  <div class="text-right">
                    <div class="font-mono text-lg font-bold text-ink leading-none">217°C</div>
                    <div class="text-[10px] font-mono text-ink-faint uppercase mt-1">ликвидус</div>
                  </div>
                </div>
                <div class="my-2.5 py-1.5 border-y border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-faint uppercase">Окно пайки:</span>
                  <span class="sketch-pill-gray text-ink font-bold text-xs">235°C — 245°C</span>
                </div>
                <p class="text-[13px] text-ink-muted leading-relaxed">
                  Заводской монтаж BGA, современные материнские платы и видеокарты. Высокая механическая прочность.
                </p>
              </div>
              <div class="pt-2 text-right">
                <button class="text-xs font-mono text-ink-muted hover:text-ink transition-colors underline decoration-paper-border" onclick="navigator.clipboard.writeText('SAC305 | Liquidus: 217C | Reflow Window: 235-245C | TAL: 45-75s'); this.innerText='скопировано!';" type="button">
                  копировать
                </button>
              </div>
            </div>

            <!-- ПОС-61 -->
            <div class="p-4 rounded border border-paper-border bg-paper flex flex-col justify-between space-y-3 shadow-sm">
              <div class="space-y-2">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="text-[11px] font-mono text-ink-faint uppercase font-semibold">Свинцовый эвтектик</div>
                    <h3 class="font-bold text-base text-ink">ПОС-61 / Sn63Pb37</h3>
                    <div class="text-xs font-mono text-ink-muted">Sn63 Pb37</div>
                  </div>
                  <div class="text-right">
                    <div class="font-mono text-lg font-bold text-ink leading-none">183°C</div>
                    <div class="text-[10px] font-mono text-ink-faint uppercase mt-1">эвтектика</div>
                  </div>
                </div>
                <div class="my-2.5 py-1.5 border-y border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-faint uppercase">Окно пайки:</span>
                  <span class="sketch-pill-yellow text-ink font-bold text-xs">210°C — 220°C</span>
                </div>
                <p class="text-[13px] text-ink-muted leading-relaxed">
                  Сервисный ремонт, реболлинг на свинец, мягкая текучесть и зеркальная галтель без микротрещин.
                </p>
              </div>
              <div class="pt-2 text-right">
                <button class="text-xs font-mono text-ink-muted hover:text-ink transition-colors underline decoration-paper-border" onclick="navigator.clipboard.writeText('ПОС-61 | Eutectic: 183C | Reflow Window: 210-220C | TAL: 30-50s'); this.innerText='скопировано!';" type="button">
                  копировать
                </button>
              </div>
            </div>

            <!-- Sn42Bi58 -->
            <div class="p-4 rounded border border-paper-border bg-paper flex flex-col justify-between space-y-3 shadow-sm">
              <div class="space-y-2">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="text-[11px] font-mono text-ink-faint uppercase font-semibold">Низкотемпературный</div>
                    <h3 class="font-bold text-base text-ink">Sn42Bi58</h3>
                    <div class="text-xs font-mono text-ink-muted">Sn42 Bi58 (Висмутовый)</div>
                  </div>
                  <div class="text-right">
                    <div class="font-mono text-lg font-bold text-ink leading-none">138°C</div>
                    <div class="text-[10px] font-mono text-ink-faint uppercase mt-1">эвтектика</div>
                  </div>
                </div>
                <div class="my-2.5 py-1.5 border-y border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-faint uppercase">Окно пайки:</span>
                  <span class="sketch-pill-gray text-ink font-bold text-xs">165°C — 175°C</span>
                </div>
                <p class="text-[13px] text-ink-muted leading-relaxed">
                  Монтаж пластиковых FPC-разъемов, OLED-шлейфов и термочувствительных датчиков MEMS без коробления.
                </p>
              </div>
              <div class="pt-2 text-right">
                <button class="text-xs font-mono text-ink-muted hover:text-ink transition-colors underline decoration-paper-border" onclick="navigator.clipboard.writeText('Sn42Bi58 | Eutectic: 138C | Reflow Window: 165-175C | TAL: 30-45s'); this.innerText='скопировано!';" type="button">
                  копировать
                </button>
              </div>
            </div>

            <!-- Сплав Розе -->
            <div class="p-4 rounded border border-paper-border bg-paper flex flex-col justify-between space-y-3 shadow-sm">
              <div class="space-y-2">
                <div class="flex items-start justify-between">
                  <div>
                    <div class="text-[11px] font-mono text-ink-faint uppercase font-semibold">Сверхнизкоплавкий</div>
                    <h3 class="font-bold text-base text-ink">Сплав Розе</h3>
                    <div class="text-xs font-mono text-ink-muted">Bi50 Pb32 Sn18</div>
                  </div>
                  <div class="text-right">
                    <div class="font-mono text-lg font-bold text-ink leading-none">96°C</div>
                    <div class="text-[10px] font-mono text-ink-faint uppercase mt-1">плавление</div>
                  </div>
                </div>
                <div class="my-2.5 py-1.5 border-y border-paper-border flex items-center justify-between text-xs font-mono">
                  <span class="text-ink-faint uppercase">Окно пайки:</span>
                  <span class="sketch-pill-gray text-ink font-bold text-xs">130°C — 140°C</span>
                </div>
                <p class="text-[13px] text-ink-muted leading-relaxed">
                  Исключительно для безопасного демонтажа чипов (разбавление тугоплавкого припоя). В чистом виде запрещен!
                </p>
              </div>
              <div class="pt-2 text-right">
                <button class="text-xs font-mono text-ink-muted hover:text-ink transition-colors underline decoration-paper-border" onclick="navigator.clipboard.writeText('Сплав Розе | Melting: 96C | Desoldering Window: 130-140C'); this.innerText='скопировано!';" type="button">
                  копировать
                </button>
              </div>
            </div>

          </div>
        </section>

        <hr class="border-paper-border my-8"/>

        <!-- Section 4: Practical details -->
        <section class="scroll-mt-20 space-y-4" id="step-4">
          <h2 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
            4. Практические нюансы: термопары, влага и деламинация
          </h2>
          <div class="space-y-4">
            
            <div class="p-4 rounded border border-paper-border bg-paper space-y-1.5">
              <div class="font-semibold text-ink text-sm">Точка замера температуры</div>
              <p class="text-[13.5px] text-ink-muted leading-relaxed">
                Закрепляйте термопару типа К каптоновым скотчем непосредственно возле корпуса микросхемы на поверхности платы. Только это дает объективную температуру в зоне пайки.
              </p>
            </div>

            <!-- Рис. 2: Схема монтажа термопары К-типа из Stitch -->
            <figure class="my-6 rounded-lg border border-paper-border-dark bg-white/80 p-2.5 sm:p-3 shadow-sm space-y-2.5">
              <div class="overflow-hidden rounded border border-paper-border bg-[#fdfcfa]">
                <img src="https://lh3.googleusercontent.com/aida/AEtjO1XPXO_7vFBi0-sZRVk7MH6OetXskpHt5Xcf3Ip6nfHDDYM7qdO0nERNQaH_49PYrri281JQZUD0JhgzliwsR7F5Ks8GvSMY32dTxDUIHzZ_P5Drz6niq2ZSyIRirmXvsdrZExlqKeZ_11m0Vf64Fa9fYCMG9SMrAAg0F5hGzsceoEP1ajdrLph6LgFKfgF6aLS30BF8hJJkW20S0l03CIQZ4uc7pmSJ_MFPJyqCHL4KNonVuGSJXGX1rAg" alt="Схема монтажа термопары К-типа возле галтелей шариковых выводов BGA" class="w-full h-auto block object-cover" loading="lazy">
              </div>
              <figcaption class="px-1 text-xs font-mono text-ink-muted leading-relaxed flex items-center justify-between">
                <span><span class="text-ink font-semibold">Рис. 2.</span> Схема монтажа термопары К-типа возле галтелей шариковых выводов BGA.</span>
                <span class="hidden sm:inline-block text-[11px] text-ink-faint uppercase">К-тип / Каптон</span>
              </figcaption>
            </figure>

            <div class="border border-paper-border bg-callout p-4 rounded space-y-1.5">
              <div class="flex items-center gap-2">
                <span class="font-semibold text-ink text-sm">Влажность микросхем (MSL 3)</span>
                <span class="font-mono text-[10px] px-1.5 py-0.5 rounded bg-paper-subtle text-ink-muted border border-paper-border font-medium">ВАЖНО</span>
              </div>
              <p class="text-[13.5px] text-ink-muted leading-relaxed">
                Если BGA-компонент лежал на воздухе дольше 72 часов, влага в полимере закипает при 230°C и распирает корпус изнутри (<mark>«эффект попкорна»</mark>). Обязательно сушите чип 12–24 часа при 100–110°C перед пайкой.
              </p>
            </div>

            <div class="p-4 rounded border border-paper-border bg-paper space-y-1.5">
              <div class="font-semibold text-ink text-sm">Критическая скорость нагрева</div>
              <p class="text-[13.5px] text-ink-muted leading-relaxed">
                Скорость нарастания температуры быстрее <strong class="text-ink">2.5–3.0°C/сек</strong> приводит к расслоению стеклотекстолита (деламинации) и обрыву внутренних переходных отверстий в плате.
              </p>
            </div>

          </div>
        </section>

        <!-- Summary Box ("Что запомнить") -->
        <div class="border border-paper-border bg-paper p-5 sm:p-6 rounded-lg space-y-3 font-sans">
          <div class="text-[11px] font-mono font-bold uppercase tracking-wider text-ink flex items-center gap-1.5">
            <span>●</span> ЧТО ЗАПОМНИТЬ
          </div>
          <ul class="space-y-2 text-[13.5px] text-ink/85 leading-relaxed">
            <li class="flex items-start gap-2.5">
              <span class="text-ink-faint font-mono">—</span>
              <span>Фен без нижнего подогревателя гарантированно изгибает многослойную плату.</span>
            </li>
            <li class="flex items-start gap-2.5">
              <span class="text-ink-faint font-mono">—</span>
              <span>Всегда фиксируйте термопару на плате — температура на выходе сопла фена завышена на 80–100°C.</span>
            </li>
            <li class="flex items-start gap-2.5">
              <span class="text-ink-faint font-mono">—</span>
              <span>Оптимальное время над ликвидусом (TAL) для SAC305 составляет строго <mark>45–75 секунд</mark>.</span>
            </li>
            <li class="flex items-start gap-2.5">
              <span class="text-ink-faint font-mono">—</span>
              <span>Бессвинцовая пайка требует пика 235–245°C; нагрев свыше 250°C разрушает кристалл и текстолит.</span>
            </li>
            <li class="flex items-start gap-2.5">
              <span class="text-ink-faint font-mono">—</span>
              <span>Влажные BGA-микросхемы перед монтажом обязательно требуют просушки 12 часов при 100°C.</span>
            </li>
          </ul>
        </div>

        <hr class="border-paper-border my-8"/>

        <!-- In-Article Native RSYA Ad Container -->
        <div class="my-8 p-4 rounded border border-dashed border-paper-border bg-paper-subtle/40 text-center">
          <div class="text-[10px] font-mono text-ink-faint uppercase mb-1">РЕКЛАМА / ПАРТНЕРСКИЙ БЛОК ЯНДЕКСА</div>
          <div id="yandex_rtb_in_article" class="min-h-[100px] flex items-center justify-center text-xs font-mono text-ink-muted border border-paper-border rounded bg-paper">
            [Контейнер РСЯ In-Article · Адаптивный блок]
          </div>
        </div>

        <!-- Section: FAQ Accordion -->
        <section class="scroll-mt-20 space-y-4" id="faq">
          <h2 class="text-xl sm:text-2xl font-bold text-ink tracking-tight">
            Частые вопросы
          </h2>
          <div class="border-t border-paper-border divide-y divide-paper-border">
            <?php foreach ($faq_items as $question => $answer): ?>
              <div class="py-3.5">
                <button class="faq-toggle w-full text-left flex items-start justify-between gap-4 font-medium text-ink hover:text-ink-muted transition-colors text-[14.5px]" type="button">
                  <span><?= e($question) ?></span>
                  <span class="font-mono text-ink-faint text-xs mt-0.5 plus-icon">+</span>
                </button>
                <div class="faq-content hidden pt-2 text-[13.5px] text-ink-muted leading-relaxed">
                  <?= e($answer) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Affiliate Product Showcase (Yandex Market / Chip & Dip) -->
        <section class="my-8 p-5 sm:p-6 rounded-lg border border-paper-border bg-card space-y-4">
          <div class="flex items-center justify-between border-b border-paper-border pb-3">
            <div class="flex items-center gap-2">
              <span class="text-sm">🛠️</span>
              <h3 class="font-bold text-xs sm:text-sm text-ink uppercase font-mono">Проверенное оборудование и химия к статье</h3>
            </div>
            <span class="text-[10px] font-mono text-ink-faint">МАРКЕТ / ЧИПДИП</span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 font-sans">
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Термовоздушная станция</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">Quick 861DW (1000W)</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Турбированный термофен для BGA чипов</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 24 500 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Флюс-гель No-Clean</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">Cyberflux RMA-218</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Безотмывочный гель для бессвинца</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 950 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
            <a href="https://market.yandex.ru" target="_blank" rel="nofollow noopener" class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-all block group">
              <div class="text-[10px] font-mono text-ink-faint uppercase">Припой SAC305</div>
              <div class="font-bold text-xs text-ink group-hover:text-accent mt-0.5">BGA Balls 0.45mm</div>
              <div class="text-[11px] text-ink-muted mt-1 leading-snug">Прецизионные шарики Sn96.5Ag3Cu0.5</div>
              <div class="mt-3 pt-2 border-t border-paper-border flex items-center justify-between text-xs font-mono">
                <span class="font-bold text-ink">от 1 200 ₽</span>
                <span class="text-accent underline text-[11px]">Маркет →</span>
              </div>
            </a>
          </div>
        </section>

        <!-- B2B / Lead-Gen Rework Module -->
        <div class="my-8 p-5 rounded-lg border-2 border-paper-border-dark bg-paper-subtle flex flex-col sm:flex-row sm:items-center justify-between gap-4 sketch-border">
          <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="sketch-pill-yellow text-ink text-[10.5px] font-mono font-bold">СЕРВИСНЫЙ ЦЕНТР ТЧП</span>
              <span class="text-xs font-bold text-ink">Нужен сложный BGA-ремонт платы?</span>
            </div>
            <p class="text-xs text-ink-muted max-w-lg leading-relaxed">
              Диагностика и замена BGA-чипов, видеокарт и процессоров на оборудовании с термопрофилированием по IPC-A-610.
            </p>
          </div>
          <a href="interactive.php" class="px-4 py-2 bg-ink text-paper text-xs font-mono font-semibold rounded hover:opacity-90 transition-opacity shrink-0 text-center border border-paper-border-dark shadow-sm">
            Заказать диагностику →
          </a>
        </div>

        <!-- Interactive Tools Box (tochkicamp style bottom CTA) -->
        <div class="border border-paper-border rounded-lg bg-paper p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-0.5">
            <div class="font-semibold text-sm text-ink">Инженерный справочник и калькуляторы ТЧП</div>
            <p class="text-xs text-ink-muted">Таблицы термопрофилей, допуски IPC-A-610 и подбор флюсов в интерактивном верстаке.</p>
          </div>
          <a class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 border border-ink bg-ink text-paper text-xs font-mono font-medium rounded hover:bg-ink-muted transition-colors shrink-0 shadow-sm" href="interactive.php">
            <span>Открыть калькуляторы →</span>
          </a>
        </div>

        <!-- Related Reading -->
        <div class="pt-4 space-y-3">
          <div class="text-[11px] font-mono text-ink-faint uppercase">Другие материалы:</div>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <?php foreach ($related_articles as $rel): ?>
              <a class="p-3 border border-paper-border rounded bg-paper hover:border-paper-border-dark transition-colors block" href="article.php?slug=<?= e($rel['slug']) ?>">
                <div class="text-[10px] font-mono text-ink-faint uppercase"><?= e($rel['category'] ?? 'Материал') ?></div>
                <div class="font-medium text-ink mt-1"><?= e($rel['title']) ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

      <!-- Sticky Floating Table of Contents Sidebar (Desktop) -->
      <aside class="hidden lg:block lg:col-span-4 sticky top-20 space-y-6">
        
        <!-- Table of Contents -->
        <div class="border border-paper-border bg-paper p-4 rounded-lg space-y-3">
          <div class="flex items-center justify-between text-[11px] font-mono text-ink-faint uppercase pb-2 border-b border-paper-border">
            <span>СОДЕРЖАНИЕ</span>
            <span>4 раздела</span>
          </div>
          <nav class="space-y-1 text-[13px] font-mono" id="toc-nav">
            <a class="toc-link block px-2 py-1 rounded text-ink font-semibold hover:bg-paper-subtle transition-colors" href="#step-1">
              1. Теплоемкость и датчики
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#step-2">
              2. Четыре фазы кривой
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#alloys">
              3. Температурные окна
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#step-4">
              4. Практика и защита от брака
            </a>
            <a class="toc-link block px-2 py-1 rounded text-ink-muted hover:text-ink hover:bg-paper-subtle transition-colors" href="#faq">
              Частые вопросы
            </a>
          </nav>
        </div>

        <!-- Sticky Sidebar РСЯ Slot -->
        <div class="border border-dashed border-paper-border bg-paper p-4 rounded-lg text-center space-y-2">
          <div class="text-[10px] font-mono text-ink-faint uppercase">РЕКЛАМА РСЯ</div>
          <div id="yandex_rtb_sidebar" class="min-h-[220px] flex items-center justify-center text-xs font-mono text-ink-muted bg-paper-subtle rounded border border-paper-border">
            [РСЯ Сайдбар · 300x250]
          </div>
        </div>

        <!-- Open Lab block -->
        <div class="border border-paper-border bg-paper-subtle/50 p-4 rounded-lg space-y-2 text-xs">
          <div class="flex items-center gap-1.5 font-mono text-[11px] font-bold uppercase text-ink">
            <span class="w-2 h-2 bg-ink rounded-full inline-block"></span>
            Лаборатория ТЧП
          </div>
          <p class="text-ink-muted leading-relaxed">
            Практические заметки, профили реболлинга и тесты термоинтерфейсов в открытой базе знаний.
          </p>
          <a class="inline-block pt-1 font-mono text-[11.5px] font-semibold text-ink underline decoration-paper-border-dark" href="interactive.php">
            Интерактивные расчеты →
          </a>
        </div>

      </aside>

    </div>

  </div>
</main>

<!-- Editorial Minimal Footer (tochkicamp style) -->
<footer class="w-full border-t border-paper-border bg-paper py-10 mt-12 text-ink-muted text-xs font-mono">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div class="space-y-1.5">
      <div class="flex items-center gap-2">
        <a class="logo text-sm hover:opacity-85 transition-opacity" href="index.php">
          ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
        </a>
        <span class="text-ink-faint">·</span>
        <span class="text-[11px] font-normal text-ink-faint font-mono">Инженерный регламент v2.4</span>
      </div>
      <p class="text-[12px] text-ink-muted max-w-md">
        Инженерный справочник, регламенты поверхностного монтажа и открытая документация по пайке и теплофизике компонентов.
      </p>
      <div class="text-ink-faint text-[11px] pt-1">
        © 2026 ТОЧКА ПЛАВЛЕНИЯ. Все права защищены.
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
      <a class="hover:text-ink transition-colors" href="index.php">Статьи</a>
      <a class="hover:text-ink transition-colors" href="interactive.php#solder-table">Реестр сплавов</a>
      <a class="hover:text-ink transition-colors" href="privacy.php">Конфиденциальность</a>
      <a class="hover:text-ink transition-colors" href="terms.php">Соглашение</a>
      <a class="hover:text-ink transition-colors" href="interactive.php">Верстак</a>
    </div>
  </div>
</footer>

<!-- Cookie Consent Banner -->
<?php require_once __DIR__ . '/includes/cookie-banner.php'; ?>

<!-- Interactive Simulator & UX Scripts (Stitch code) -->
<script>
(function() {
  const alloyData = {
    sac305: {
      name: 'SAC305 (Sn96.5 Ag3.0 Cu0.5)',
      stages: [
        {
          tabTemp: '25°C → 150°C',
          tag: 'ФАЗА 01',
          title: 'Прогрев (Preheat) — 25°C → 150°C',
          speed: '1.0 — 2.5°C/сек',
          duration: '60 — 90 сек',
          bottom: '120°C — 140°C',
          top: '180°C (25 л/мин)',
          desc: 'Плавный подогрев многослойной платы для исключения термического удара и коробления текстолита.',
          warning: 'Скорость свыше 3.0°C/с приводит к микротрещинам в керамических конденсаторах MLCC.'
        },
        {
          tabTemp: '150°C → 190°C',
          tag: 'ФАЗА 02',
          title: 'Активация (Soak) — 150°C → 190°C',
          speed: '0.5 — 1.0°C/сек',
          duration: '60 — 120 сек',
          bottom: '150°C — 160°C',
          top: '210°C (30 л/мин)',
          desc: 'Выравнивание температурного поля под чипом и по краям платы. Химическое травление оксидов меди флюсом.',
          warning: 'Слишком долгий soak (>120с) полностью выжигает активность флюса до фазы оплавления.'
        },
        {
          tabTemp: '217°C → 240°C',
          tag: 'ФАЗА 03',
          title: 'Оплавление (Reflow) — 217°C → 240°C',
          speed: 'TAL: 45 — 75 сек',
          duration: 'Пик: 10 — 20 сек',
          bottom: '170°C — 185°C',
          top: '255°C (35 л/мин)',
          desc: 'Полный переход шариков в жидкую фазу, смачивание контактных площадок и диффузионный рост интерметаллического слоя Cu6Sn5.',
          warning: 'Превышение 248–250°C на кристалле ведет к деламинации кремниевой подложки и необратимому перегреву.'
        },
        {
          tabTemp: '240°C → 100°C',
          tag: 'ФАЗА 04',
          title: 'Охлаждение (Cooling) — 240°C → 100°C',
          speed: '2.0 — 4.0°C/сек',
          duration: '40 — 60 сек',
          bottom: 'Отключен',
          top: 'Остывание (обдув)',
          desc: 'Быстрая контролируемая кристаллизация припоя, обеспечивающая мелкодисперсную пластичную структуру галтелей.',
          warning: 'Охлаждение медленнее 1°C/с образует хрупкие игольчатые кристаллы интерметаллида.'
        }
      ]
    },
    pos61: {
      name: 'ПОС-61 (Sn63 Pb37)',
      stages: [
        {
          tabTemp: '25°C → 130°C',
          tag: 'ФАЗА 01',
          title: 'Прогрев (Preheat) — 25°C → 130°C',
          speed: '1.0 — 2.0°C/сек',
          duration: '60 — 80 сек',
          bottom: '110°C — 120°C',
          top: '160°C (25 л/мин)',
          desc: 'Плавный подъем температуры для безопасного старта испарения растворителей флюса.',
          warning: 'Избегайте локального нагрева: нижний подогрев обязателен для свинца на платах толще 1.2 мм.'
        },
        {
          tabTemp: '130°C → 165°C',
          tag: 'ФАЗА 02',
          title: 'Активация (Soak) — 130°C → 165°C',
          speed: '0.5 — 1.0°C/сек',
          duration: '50 — 90 сек',
          bottom: '135°C — 145°C',
          top: '185°C (30 л/мин)',
          desc: 'Равномерный прогрев шариков BGA и активация смоляных кислот флюса.',
          warning: 'Не задерживайте температуру выше 170°C, чтобы не истощить флюс.'
        },
        {
          tabTemp: '183°C → 215°C',
          tag: 'ФАЗА 03',
          title: 'Оплавление (Reflow) — 183°C → 215°C',
          speed: 'TAL: 35 — 60 сек',
          duration: 'Пик: 10 — 15 сек',
          bottom: '150°C — 160°C',
          top: '225°C (30 л/мин)',
          desc: 'Мгновенный эвтектический переход в жидкость при 183°C, зеркальная галтель и самоцентрирование чипа.',
          warning: 'Нагрев свыше 225°C не нужен и ускоряет окисление свинца.'
        },
        {
          tabTemp: '215°C → 90°C',
          tag: 'ФАЗА 04',
          title: 'Охлаждение (Cooling) — 215°C → 90°C',
          speed: '2.0 — 3.5°C/сек',
          duration: '35 — 50 сек',
          bottom: 'Отключен',
          top: 'Остывание (обдув)',
          desc: 'Формирование блестящей гладкой структуры паяного соединения без раковин.',
          warning: 'Не допускайте резкого удара холодом из компрессора (термошок).'
        }
      ]
    },
    sn42: {
      name: 'Sn42Bi58 (Низкотемпературный)',
      stages: [
        {
          tabTemp: '25°C → 100°C',
          tag: 'ФАЗА 01',
          title: 'Прогрев (Preheat) — 25°C → 100°C',
          speed: '1.0°C/сек',
          duration: '50 — 70 сек',
          bottom: '80°C — 90°C',
          top: '130°C (20 л/мин)',
          desc: 'Деликатный нагрев термопластичных шлейфов и тонких подложек.',
          warning: 'Осторожно с давлением воздуха из сопла: пластик разъемов размягчается.'
        },
        {
          tabTemp: '100°C → 125°C',
          tag: 'ФАЗА 02',
          title: 'Активация (Soak) — 100°C → 125°C',
          speed: '0.4 — 0.8°C/сек',
          duration: '40 — 60 сек',
          bottom: '100°C — 110°C',
          top: '145°C (25 л/мин)',
          desc: 'Стабилизация температуры перед мгновенной эвтектикой висмута.',
          warning: 'Не применяйте высокоактивные спирто-канифольные флюсы с высокой температурой активации.'
        },
        {
          tabTemp: '138°C → 165°C',
          tag: 'ФАЗА 03',
          title: 'Оплавление (Reflow) — 138°C → 165°C',
          speed: 'TAL: 30 — 45 сек',
          duration: 'Пик: 8 — 12 сек',
          bottom: '120°C — 130°C',
          top: '175°C (25 л/мин)',
          desc: 'Низкотемпературная посадка чувствительных кремниевых датчиков и светодиодов.',
          warning: 'Висмут хрупок на изгиб: не используйте для плат с механической нагрузкой.'
        },
        {
          tabTemp: '165°C → 80°C',
          tag: 'ФАЗА 04',
          title: 'Охлаждение (Cooling) — 165°C → 80°C',
          speed: '1.5 — 2.5°C/сек',
          duration: '30 — 40 сек',
          bottom: 'Отключен',
          top: 'Остывание (обдув)',
          desc: 'Аккуратный естественный спад температуры без температурного шока полимеров.',
          warning: 'Не подвергайте плату механическим вибрациям до полного затвердевания (130°C).'
        }
      ]
    }
  };

  let currentAlloy = 'sac305';
  let currentStage = 2; // Reflow active

  function renderSimulator() {
    const alloy = alloyData[currentAlloy];

    // Update tab temps
    for (let i = 0; i < 4; i++) {
      const tabElem = document.getElementById('tab-temp-' + i);
      if (tabElem) tabElem.textContent = alloy.stages[i].tabTemp;
    }

    // Style stage tabs
    const stageButtons = document.querySelectorAll('.stage-btn');
    stageButtons.forEach((btn, idx) => {
      const titleDiv = btn.querySelector('div:first-child');
      const tempDiv = btn.querySelector('div:last-child');
      if (idx === currentStage) {
        btn.className = 'stage-btn p-2.5 rounded border border-ink bg-callout text-left transition-all shadow-sm';
        if (titleDiv) titleDiv.className = 'text-[10px] font-mono text-ink font-semibold';
        if (tempDiv) tempDiv.className = 'text-xs font-mono text-ink font-semibold mt-0.5';
      } else {
        btn.className = 'stage-btn p-2.5 rounded border border-paper-border bg-paper text-left transition-all hover:border-paper-border-dark';
        if (titleDiv) titleDiv.className = 'text-[10px] font-mono text-ink-faint';
        if (tempDiv) tempDiv.className = 'text-xs font-mono text-ink mt-0.5';
      }
    });

    // Update active stage details
    const st = alloy.stages[currentStage];
    document.getElementById('detail-tag').textContent = st.tag;
    document.getElementById('detail-title').textContent = st.title;
    document.getElementById('detail-speed').textContent = st.speed;
    document.getElementById('detail-duration').textContent = st.duration;
    document.getElementById('detail-bottom').textContent = st.bottom;
    document.getElementById('detail-top').textContent = st.top;
    document.getElementById('detail-desc').textContent = st.desc;
    document.getElementById('detail-warning').textContent = st.warning;
  }

  // Alloy clicks
  const alloyButtons = document.querySelectorAll('.alloy-btn');
  alloyButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      currentAlloy = btn.getAttribute('data-alloy');
      alloyButtons.forEach(b => {
        b.className = 'alloy-btn px-2.5 py-1 rounded border border-paper-border bg-paper text-ink hover:border-ink transition-all';
      });
      btn.className = 'alloy-btn px-2.5 py-1 rounded border border-ink bg-ink text-paper transition-all font-medium';
      renderSimulator();
    });
  });

  // Stage tab clicks
  const stageButtons = document.querySelectorAll('.stage-btn');
  stageButtons.forEach((btn, idx) => {
    btn.addEventListener('click', () => {
      currentStage = idx;
      renderSimulator();
    });
  });

  // Copy stage parameters
  const copyBtn = document.getElementById('copy-stage-btn');
  const copyBtnText = document.getElementById('copy-btn-text');
  if (copyBtn) {
    copyBtn.addEventListener('click', () => {
      const st = alloyData[currentAlloy].stages[currentStage];
      const text = `${alloyData[currentAlloy].name} | ${st.title} | Скорость/TAL: ${st.speed} | Стол: ${st.bottom} | Фен: ${st.top}`;
      navigator.clipboard.writeText(text).then(() => {
        copyBtnText.textContent = 'Скопировано в буфер!';
        setTimeout(() => {
          copyBtnText.textContent = 'Скопировать параметры';
        }, 2000);
      });
    });
  }

  // FAQ accordion
  const faqToggles = document.querySelectorAll('.faq-toggle');
  faqToggles.forEach(btn => {
    btn.addEventListener('click', () => {
      const content = btn.nextElementSibling;
      const plus = btn.querySelector('.plus-icon');
      const isClosed = content.classList.contains('hidden');
      
      if (isClosed) {
        content.classList.remove('hidden');
        if (plus) plus.textContent = '—';
      } else {
        content.classList.add('hidden');
        if (plus) plus.textContent = '+';
      }
    });
  });

  // Highlight active TOC link on scroll
  const sections = document.querySelectorAll('section[id]');
  const tocLinks = document.querySelectorAll('.toc-link');

  window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(sec => {
      const top = sec.offsetTop - 110;
      if (window.pageYOffset >= top) {
        current = sec.getAttribute('id');
      }
    });

    tocLinks.forEach(link => {
      const href = link.getAttribute('href').replace('#', '');
      if (href === current) {
        link.classList.add('text-ink', 'font-semibold', 'bg-paper-subtle');
        link.classList.remove('text-ink-muted');
      } else {
        link.classList.remove('text-ink', 'font-semibold', 'bg-paper-subtle');
        link.classList.add('text-ink-muted');
      }
    });
  });

  // Initial run
  renderSimulator();

  // Theme Toggle listener
  const toggleBtn = document.getElementById('theme-toggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
    });
  }
})();
</script>

</body>
</html>
