<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';
require_once __DIR__ . '/includes/rubrics-data.php';

$page_title = "Паяй уверенно — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc  = "Инженерный справочник по пайке: точные ориентиры температуры жала, подбор инструмента и правила монтажа без риска перегреть компоненты.";
$current_page = 'index';

// Берём только реальные статьи из базы знаний (без выдуманных данных)
$recent_articles = array_slice($articles, 0, 4);

ob_start();
?>
  <link rel="canonical" href="https://tochka-plavleniya.ru/">
  <style>
    .washi-tape-hero { top: -10px; left: 16px; transform: rotate(-2.5deg); }
    .washi-tape-warn { top: -9px; left: 50%; transform: translateX(-50%) rotate(-0.5deg); }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }
  </style>
<?php
$extra_head = ob_get_clean();
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Container: Strict 8-point grid, WCAG accessible main anchor -->
  <main class="w-full flex-grow pt-6 sm:pt-8 pb-16" id="main-content">
    <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 space-y-14 sm:space-y-16">

      <!-- 1. ПЕРВЫЙ ЭКРАН (HERO): Заголовок «Паяй уверенно», 4 задачи, схема жала -->
      <section class="relative pt-4 pb-8 border-b border-paper-border space-y-8" id="hero">
        
        <!-- Скотч и пометка (строго только у первого экрана, честный текст без «лабораторный») -->
        <div class="relative inline-block">
          <div class="sketch-washi-tape washi-tape-hero" aria-hidden="true"></div>
          <div class="sketch-sticky-note inline-flex items-center gap-2 px-3 py-1 text-ink font-mono text-xs shadow-xs rotate-[-1deg] rounded-sm">
            <span class="w-2 h-2 rounded-full bg-accent inline-block" aria-hidden="true"></span>
            <span class="font-bold tracking-wider uppercase">ОТК // ИНЖЕНЕРНЫЙ СПРАВОЧНИК 2026</span>
          </div>
        </div>

        <div class="hero-layout">
          
          <!-- Левая колонка: H1, подзаголовок, 4 кнопки задач -->
          <div class="space-y-6">
            <div class="space-y-3">
              <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-ink tracking-tight font-sans leading-[1.08]">
                Паяй уверенно
              </h1>
              <p class="text-xl sm:text-2xl font-bold text-ink font-sans">
                Выберите, что нужно соединить
              </p>
              <p class="text-base sm:text-lg text-ink-muted leading-relaxed font-serif max-w-xl">
                Нажмите свою задачу — откроется готовый тепловой ориентир и регламент монтажа.
              </p>
            </div>

            <!-- Сетка 4 задач в 2 колонки с технической иконографикой и описаниями -->
            <div class="hero-tasks-grid pt-1">
              
              <!-- Задача 1: Провод / кабель -->
              <a href="interactive.php?tool=temp&task=wire" 
                 class="sketch-card p-3.5 sm:p-4 flex flex-col justify-between bg-card hover:border-accent group transition-all focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                 aria-label="Задача пайки: Провод или кабель. Подбор температуры для многожильного провода">
                <div>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded border border-paper-border bg-paper flex items-center justify-center text-accent shrink-0">
                        <span class="material-symbols-outlined text-[18px]">cable</span>
                      </div>
                      <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors leading-tight">Провод / кабель</span>
                    </div>
                    <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform text-lg font-bold" aria-hidden="true">→</span>
                  </div>
                  <p class="text-xs text-ink-muted font-sans mt-2 leading-snug">
                    Многожильный медный провод, лужение жил, термоусадка
                  </p>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-paper-border/60 font-mono text-xs text-ink-muted">
                  <span class="pill-orange text-[10px] uppercase font-bold">Основы пайки</span>
                  <span class="text-ink-faint text-[11px]">ПОС-61 // 280°C</span>
                </div>
              </a>

              <!-- Задача 2: Печатная плата -->
              <a href="interactive.php?tool=temp&task=pcb" 
                 class="sketch-card p-3.5 sm:p-4 flex flex-col justify-between bg-card hover:border-accent group transition-all focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                 aria-label="Задача пайки: Печатная плата. Монтаж SMD 0805, QFN и теплоемких полигонов">
                <div>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded border border-paper-border bg-paper flex items-center justify-center text-accent shrink-0">
                        <span class="material-symbols-outlined text-[18px]">developer_board</span>
                      </div>
                      <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors leading-tight">Печатная плата</span>
                    </div>
                    <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform text-lg font-bold" aria-hidden="true">→</span>
                  </div>
                  <p class="text-xs text-ink-muted font-sans mt-2 leading-snug">
                    SMD 0805–1206, QFN чипы, земляные полигоны платы
                  </p>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-paper-border/60 font-mono text-xs text-ink-muted">
                  <span class="pill-blue text-[10px] uppercase font-bold">SMD и термопрофили</span>
                  <span class="text-ink-faint text-[11px]">SAC305 // T12</span>
                </div>
              </a>

              <!-- Задача 3: Медная труба -->
              <a href="interactive.php?tool=temp&task=pipe" 
                 class="sketch-card p-3.5 sm:p-4 flex flex-col justify-between bg-card hover:border-accent group transition-all focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                 aria-label="Задача пайки: Медная труба. Капиллярные фитинги и твердый припой">
                <div>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded border border-paper-border bg-paper flex items-center justify-center text-accent shrink-0">
                        <span class="material-symbols-outlined text-[18px]">plumbing</span>
                      </div>
                      <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors leading-tight">Медная труба</span>
                    </div>
                    <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform text-lg font-bold" aria-hidden="true">→</span>
                  </div>
                  <p class="text-xs text-ink-muted font-sans mt-2 leading-snug">
                    Трубы 15–28 мм, капиллярные муфты, горелка и твердый припой
                  </p>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-paper-border/60 font-mono text-xs text-ink-muted">
                  <span class="pill-orange text-[10px] uppercase font-bold">Материалы и флюсы</span>
                  <span class="text-ink-faint text-[11px]">Горелка // 650°C</span>
                </div>
              </a>

              <!-- Задача 4: Не знаю — помочь -->
              <a href="interactive.php?tool=temp&task=unknown" 
                 class="sketch-card p-3.5 sm:p-4 flex flex-col justify-between bg-card hover:border-accent group transition-all focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none"
                 aria-label="Задача: Интерактивный помощник. Экспресс-подбор температуры жала и оборудования">
                <div>
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                      <div class="w-8 h-8 rounded border border-paper-border bg-paper flex items-center justify-center text-accent shrink-0">
                        <span class="material-symbols-outlined text-[18px]">troubleshoot</span>
                      </div>
                      <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors leading-tight">Не знаю — помочь</span>
                    </div>
                    <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform text-lg font-bold" aria-hidden="true">→</span>
                  </div>
                  <p class="text-xs text-ink-muted font-sans mt-2 leading-snug">
                    Пошаговый подбор температуры жала, типа флюса и станции
                  </p>
                </div>
                <div class="flex items-center justify-between gap-2 mt-3 pt-2 border-t border-paper-border/60 font-mono text-xs text-ink-muted">
                  <span class="pill-yellow text-[10px] uppercase font-bold">Интерактивный верстак</span>
                  <span class="text-ink-faint text-[11px]">Экспресс-тест</span>
                </div>
              </a>

            </div>
          </div>

          <!-- Правая колонка: Инженерная схема жала (Fixed aspect ratio to prevent CLS) -->
          <div class="flex flex-col items-center justify-center">
            <div class="sketch-card p-3.5 bg-paper w-full max-w-[440px] shadow-xs relative">
              <div class="font-mono text-xs text-ink-muted pb-2 border-b border-paper-border mb-2.5 flex items-center justify-between">
                <span class="font-bold text-ink uppercase tracking-wider">АНАТОМИЯ ЖАЛА</span>
                <span class="pill-blue text-[10px] font-mono font-bold uppercase px-1.5 py-0.5 rounded">IPC-J-STD</span>
              </div>
              <div class="w-full overflow-hidden rounded bg-white dark:bg-[#1a1f26] flex items-center justify-center border border-paper-border/60 p-1">
                <img 
                  src="assets/schematics/tip-cutaway.svg" 
                  alt="Разрез паяльного жала: медный сердечник, нагреватель и рабочая поверхность" 
                  width="340" 
                  height="180" 
                  class="w-full h-auto object-contain"
                  loading="eager"
                />
              </div>
              <p class="font-mono text-xs text-ink-muted mt-2.5 text-center leading-normal">
                Так устроено жало, которое греет соединение: медный сердечник передаёт тепло, защитный слой железа предотвращает растворение в олове
              </p>
            </div>
          </div>

        </div>
      </section>

      <!-- БЫСТРЫЙ ПОИСК ПО БАЗЕ ЗНАНИЙ (Стиль инженерной миллиметровки) -->
      <section class="space-y-2">
        <div id="quick-search-trigger" role="button" tabindex="0" aria-label="Открыть глобальный инженерный поиск по базе (Ctrl+K)"
             class="sketch-card p-3 sm:p-4 bg-paper flex items-center justify-between gap-3 cursor-pointer hover:border-accent transition-colors group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
          <div class="flex items-center gap-3 text-ink-muted text-xs sm:text-sm font-mono">
            <span class="material-symbols-outlined text-[20px] text-accent group-hover:scale-110 transition-transform">search</span>
            <span class="text-ink-muted group-hover:text-ink transition-colors">Поиск по базе: припой ПОС-61, флюс RMA, жала T12/C245, дефекты пайки...</span>
          </div>
          <kbd class="hidden sm:inline-flex items-center gap-1 px-2.5 py-1 text-xs font-mono border border-paper-border rounded bg-paper-subtle text-ink-muted">
            <span>Ctrl</span><span>+</span><span>K</span>
          </kbd>
        </div>
      </section>

      <!-- РУБРИКИ ЖУРНАЛА (4 архитектурных раздела базы знаний) -->
      <section class="space-y-4" id="rubrics-grid">
        <div class="flex items-end justify-between flex-wrap gap-2">
          <div class="space-y-1">
            <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">РУБРИКИ ЖУРНАЛА</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
              Тематические разделы
            </h2>
          </div>
          <a href="/category.php?slug=materialy" class="font-mono text-xs text-ink-muted hover:text-accent transition-colors flex items-center gap-1 focus-visible:ring-2 focus-visible:ring-accent rounded">
            <span>Все рубрики в каталоге</span>
            <span>→</span>
          </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-1">
          <?php 
          $rubric_order = ['start', 'materialy', 'praktika', 'oshibki'];
          foreach ($rubric_order as $rslug):
            if (!isset($RUBRICS[$rslug])) continue;
            $rub = $RUBRICS[$rslug];
            $pill_class = match($rslug) {
              'start'     => 'pill-yellow',
              'materialy' => 'pill-orange',
              'praktika'  => 'pill-blue',
              'oshibki'   => 'pill-orange',
              default     => 'pill-blue'
            };
          ?>
            <a href="/category.php?slug=<?= $rslug ?>"
               class="sketch-card p-4 sm:p-5 flex flex-col justify-between space-y-3 bg-card hover:border-accent transition-all group focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none">
              <div class="space-y-2">
                <div class="flex items-center justify-between font-mono text-xs">
                  <span class="<?= $pill_class ?> px-2 py-0.5 rounded text-[10px] font-mono font-bold uppercase tracking-wider">
                    <?= e($rub['subbadge']) ?>
                  </span>
                  <span class="text-ink-faint font-bold font-mono">0<?= match($rslug) {'start'=>1, 'materialy'=>2, 'praktika'=>3, 'oshibki'=>4} ?></span>
                </div>
                <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                  <?= e($rub['title']) ?>
                </h3>
                <p class="text-xs text-ink-muted leading-relaxed font-serif line-clamp-3">
                  <?= e($rub['lead']) ?>
                </p>
              </div>
              <div class="pt-3 border-t border-paper-border flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="text-xs text-ink-faint"><?= count($rub['article_ids'] ?? []) ?> материалов</span>
                <span class="text-ink font-bold group-hover:text-accent group-hover:translate-x-1 transition-all">Открыть →</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 2. НОВЫЕ СТАТЬИ: Только реальные статьи из базы знаний -->
      <section class="space-y-4" id="articles">
        <div class="flex items-end justify-between flex-wrap gap-2">
          <div class="space-y-1">
            <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">БАЗА ЗНАНИЙ</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
              Новые статьи
            </h2>
          </div>
          <a href="/category.php?slug=materialy" class="font-mono text-xs text-ink-muted hover:text-accent transition-colors flex items-center gap-1 focus-visible:ring-2 focus-visible:ring-accent rounded">
            <span>Все статьи журнала</span>
            <span>→</span>
          </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
          <?php foreach ($recent_articles as $article): 
            $tag_pill = get_semantic_tag_pill($article['tag_key'] ?? '');
          ?>
            <article class="sketch-card p-4 flex flex-col justify-between space-y-3 bg-card hover:border-accent transition-colors group">
              <div class="space-y-2">
                <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                  <span class="<?= $tag_pill ?> px-1.5 py-0.5 rounded text-[10px] font-mono font-bold uppercase">
                    <?= htmlspecialchars($article['tag']) ?>
                  </span>
                  <span>~<?= (int)$article['read_min'] ?> мин</span>
                </div>
                <h3 class="text-base font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                  <a href="article.php?slug=<?= urlencode($article['slug']) ?>" class="focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded">
                    <?= htmlspecialchars($article['title']) ?>
                  </a>
                </h3>
                <p class="text-xs text-ink-muted leading-relaxed font-serif line-clamp-3">
                  <?= htmlspecialchars($article['excerpt']) ?>
                </p>
              </div>

              <div class="pt-3 border-t border-paper-border flex items-center justify-between font-mono text-xs">
                <span class="text-ink-faint text-xs font-hand text-base font-bold italic"><?= htmlspecialchars($article['author']) ?></span>
                <a href="article.php?slug=<?= urlencode($article['slug']) ?>" class="text-ink font-bold group-hover:text-accent focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded px-1">
                  Читать →
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 2.1 ПРИКЛАДНЫЕ МИКРОУРОКИ: 10 цеховых регламентов по 2–3 мин -->
      <section class="space-y-4 scroll-mt-24" id="lessons">
        <div class="flex items-end justify-between flex-wrap gap-2">
          <div class="space-y-1">
            <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">ЦЕХОВОЙ ПРАКТИКУМ // 2–3 МИНУТЫ</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
              Прикладные микроуроки монтажа
            </h2>
          </div>
          <span class="font-mono text-xs text-ink-muted">10 регламентов с чек-листами</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2">
          <?php foreach (get_all_lessons() as $idx => $l): ?>
            <article class="sketch-card p-3.5 flex flex-col justify-between space-y-2.5 bg-card hover:border-paper-border-dark transition-all rounded-lg group shadow-xs">
              <div class="space-y-2">
                <div class="flex items-center justify-between font-mono text-xs">
                  <span class="pill-orange uppercase font-bold tracking-tight text-[10px]">Урок <?= $l['read_min'] ?>м</span>
                  <span class="text-ink-faint">#<?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                </div>
                <h3 class="text-sm font-bold text-ink group-hover:text-accent transition-colors leading-snug line-clamp-2">
                  <a href="article.php?slug=<?= urlencode($l['slug']) ?>" class="focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded">
                    <?= htmlspecialchars($l['title']) ?>
                  </a>
                </h3>
                <p class="text-xs text-ink-muted leading-relaxed font-serif line-clamp-2">
                  <?= htmlspecialchars($l['subtitle']) ?>
                </p>
              </div>

              <div class="pt-2 border-t border-paper-border flex items-center justify-between font-mono text-xs">
                <span class="text-ink-faint text-[10px] uppercase truncate max-w-[80px]"><?= htmlspecialchars($l['tag']) ?></span>
                <a href="article.php?slug=<?= urlencode($l['slug']) ?>" class="text-ink font-bold group-hover:text-accent shrink-0 focus-visible:ring-2 focus-visible:ring-accent focus-visible:outline-none rounded px-1">
                  Открыть →
                </a>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- 3. БЛОК ДОВЕРИЯ И ИСТОЧНИКОВ: честный и прозрачный -->
      <section class="space-y-4" id="trust">

        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">ПРОЗРАЧНОСТЬ И ИСТОЧНИКИ</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Откуда берутся цифры
          </h2>
        </div>

        <div class="sketch-card p-5 sm:p-6 bg-card space-y-3 border-l-4 border-l-accent">
          <p class="text-base sm:text-lg text-ink font-serif leading-relaxed">
            Температуры плавления берём из нормативных документов и паспортов сплавов. Уставка паяльной станции — практический стартовый ориентир: её корректируют по теплоёмкости детали, жалу и времени контакта.
          </p>
          <div class="pt-3 border-t border-paper-border flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-mono text-ink-muted">
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent" aria-hidden="true"></span>
              <strong>Оловянно-свинцовые припои (ПОС):</strong> ГОСТ 21930 / ГОСТ 21931
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent" aria-hidden="true"></span>
              <strong>Бессвинцовые сплавы (SAC305):</strong> Паспорта производителей / ISO 9453
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent" aria-hidden="true"></span>
              <strong>Критерии пайки:</strong> IPC-A-610 (внешний вид соединений)
            </span>
          </div>
        </div>

        <!-- Заметка инженера: предостережение по кислоте -->
        <div class="sketch-pencil-orange relative p-5 rounded-lg shadow-xs space-y-3 mt-5">
          <div class="sketch-washi-tape washi-tape-warn" aria-hidden="true"></div>
          
          <div class="flex items-center gap-2 text-amber-900 dark:text-amber-300">
            <svg width="16" height="16" class="w-4 h-4 stroke-current shrink-0" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            </svg>
            <span class="font-mono text-xs font-bold uppercase tracking-wider">Правило чистоты платы</span>
          </div>

          <p class="font-sans text-sm text-ink leading-relaxed">
            <strong>Никогда не используйте активную кислоту (паяльную, ортофосфорную) для печатных плат.</strong> Кислота капиллярно впитывается в слои стеклотекстолита FR-4: смыть её полностью невозможно. Через 2–4 месяца она вызывает утечки по питанию и гарантированно разъедает дорожки. Для электроники допустимы только нейтральные канифольные и No-Clean флюсы.
          </p>

          <div class="font-hand text-base text-ink-muted/80 italic">
            «Кислота хороша для кастрюль и медных труб, но убивает электронику»
          </div>
        </div>
      </section>

      <!-- 4. ЧАСТЫЕ ВОПРОСЫ (FAQ) ИЗ 4 ВОПРОСОВ -->
      <section class="space-y-4" id="faq">
        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">ВОПРОС — ОТВЕТ</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Частые вопросы
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
          
          <!-- Вопрос 1 -->
          <div class="sketch-card p-4 bg-card space-y-2">
            <h3 class="text-base font-bold text-ink leading-snug">
              Какую температуру выставить для припоя ПОС-61?
            </h3>
            <p class="text-sm text-ink-muted font-serif leading-relaxed">
              Припой ПОС-61 плавится в интервале 183–190 °C. На паяльной станции выставляйте <strong>260–290 °C</strong>. Это практический стартовый ориентир для мелкой пайки, а не норма ГОСТ: запас в 80–100 °C необходим, чтобы компенсировать отвод тепла в медные дорожки платы.
            </p>
          </div>

          <!-- Вопрос 2 -->
          <div class="sketch-card p-4 bg-card space-y-2">
            <h3 class="text-base font-bold text-ink leading-snug">
              Нужен ли новичку дорогой паяльник вроде JBC?
            </h3>
            <p class="text-sm text-ink-muted font-serif leading-relaxed">
              Нет. Для старта достаточно станции на картриджах <strong>Hakko T12</strong> (или портативного USB-PD паяльника вроде Pinecil/T80). Они быстро греются и держат температуру благодаря встроенной в жало термопаре.
            </p>
          </div>

          <!-- Вопрос 3 -->
          <div class="sketch-card p-4 bg-card space-y-2">
            <h3 class="text-base font-bold text-ink leading-snug">
              Как не отслоить медную дорожку от текстолита?
            </h3>
            <p class="text-sm text-ink-muted font-serif leading-relaxed">
              Главное правило: <strong>не более 2.5–3 секунд</strong> контакта жала с площадкой. Если припой не плавится за это время — увеличьте площадь контакта жала или используйте подогрев, но не давите на дорожку силой.
            </p>
          </div>

          <!-- Вопрос 4 -->
          <div class="sketch-card p-4 bg-card space-y-2">
            <h3 class="text-base font-bold text-ink leading-snug">
              Какой флюс выбрать для первого раза и надо ли его смывать?
            </h3>
            <p class="text-sm text-ink-muted font-serif leading-relaxed">
              Выбирайте гелевый флюс класса <strong>No-Clean (ROL0)</strong>. Канифоль безопасна, но твердеет; водосмывные и активные флюсы новичкам брать нельзя — их остатки вызывают коррозию дорожек.
            </p>
          </div>

        </div>
      </section>

    </div>
  </main>

  <!-- Structured Data: WebSite & FAQPage JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebSite",
        "@id": "https://tochka-plavleniya.ru/#website",
        "url": "https://tochka-plavleniya.ru/",
        "name": "ТОЧКА ПЛАВЛЕНИЯ",
        "description": "Инженерный справочник по пайке, терморежимам и монтажному оборудованию",
        "inLanguage": "ru-RU"
      },
      {
        "@type": "FAQPage",
        "@id": "https://tochka-plavleniya.ru/#faq",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "Какую температуру выставить для припоя ПОС-61?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Припой ПОС-61 плавится в интервале 183–190 °C. На паяльной станции выставляйте 260–290 °C. Это практический стартовый ориентир для мелкой пайки, а не норма ГОСТ: запас в 80–100 °C необходим, чтобы компенсировать отвод тепла в медные дорожки платы."
            }
          },
          {
            "@type": "Question",
            "name": "Нужен ли новичку дорогой паяльник вроде JBC?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Нет. Для старта достаточно станции на картриджах Hakko T12 (или портативного USB-PD паяльника вроде Pinecil/T80). Они быстро греются и держат температуру благодаря встроенной в жало термопаре."
            }
          },
          {
            "@type": "Question",
            "name": "Как не отслоить медную дорожку от текстолита?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Главное правило: не более 2.5–3 секунд контакта жала с площадкой. Если припой не плавится за это время — увеличьте площадь контакта жала или используйте подогрев, но не давите на дорожку силой."
            }
          },
          {
            "@type": "Question",
            "name": "Какой флюс выбрать для первого раза и надо ли его смывать?",
            "acceptedAnswer": {
              "@type": "Answer",
              "text": "Выбирайте гелевый флюс класса No-Clean (ROL0). Канифоль безопасна, но твердеет; водосмывные и активные флюсы новичкам брать нельзя — их остатки вызывают коррозию дорожек."
            }
          }
        ]
      }
    ]
  }
  </script>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Mobile Sticky Quick Bar (Поиск, Верстак, Практика, Старт) -->
  <?php require_once __DIR__ . '/includes/mobile-bar.php'; ?>

  <!-- Global Engineering Search Modal (Ctrl+K) -->
  <?php require_once __DIR__ . '/includes/search-modal.php'; ?>

  <!-- Quick-Search Bar Hook & Theme Toggle JS -->
  <script>
    (function() {
      // Quick search click/keydown handler
      const qsTrigger = document.getElementById('quick-search-trigger');
      if (qsTrigger) {
        const openModal = function(e) {
          e.preventDefault();
          const trigger = document.getElementById('search-modal-trigger');
          if (trigger) trigger.click();
        };
        qsTrigger.addEventListener('click', openModal);
        qsTrigger.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            openModal(e);
          }
        });
      }

      // Theme toggle
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
