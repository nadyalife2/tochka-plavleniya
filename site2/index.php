<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$page_title = "Паяй уверенно — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc = "Инженерный справочник по пайке: точные ориентиры температуры жала, подбор инструмента и правила монтажа без риска перегреть компоненты.";
$current_page = 'index';

// Берём только реальные статьи из базы знаний (без выдуманных данных)
$recent_articles = array_slice($articles, 0, 4);

include __DIR__ . '/includes/header.php';
?>

  <!-- Main Container: 64px vertical spacing between sections, strict 8-point grid -->
  <main class="w-full flex-grow pt-6 sm:pt-8 pb-16">
    <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 space-y-14 sm:space-y-16">

      <!-- 1. ПЕРВЫЙ ЭКРАН (HERO): Заголовок «Паяй уверенно», 4 задачи, схема жала -->
      <section class="relative pt-4 pb-8 border-b border-paper-border space-y-8" id="hero">
        
        <!-- Скотч и пометка (строго только у первого экрана, честный текст без «лабораторный») -->
        <div class="relative inline-block">
          <div class="sketch-washi-tape" style="top: -10px; left: 16px; transform: rotate(-2.5deg);" aria-hidden="true"></div>
          <div class="sketch-sticky-note inline-flex items-center gap-2 px-3 py-1 text-ink font-mono text-xs shadow-xs rotate-[-1deg] rounded-sm">
            <span class="w-2 h-2 rounded-full bg-accent inline-block"></span>
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
                Нажмите свою задачу — откроется готовый ориентир.
              </p>
            </div>

            <!-- Сетка 4 задач в 2 колонки (крупные, без лишнего кода) -->
            <div class="hero-tasks-grid pt-1">
              
              <!-- Задача 1: Провод / кабель -->
              <a href="interactive.php?tool=temp&task=wire" class="sketch-card p-4 min-h-[52px] flex items-center justify-between bg-card hover:border-accent group transition-all">
                <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors">Провод / кабель</span>
                <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform ml-2 text-xl font-bold">→</span>
              </a>

              <!-- Задача 2: Печатная плата -->
              <a href="interactive.php?tool=temp&task=pcb" class="sketch-card p-4 min-h-[52px] flex items-center justify-between bg-card hover:border-accent group transition-all">
                <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors">Печатная плата</span>
                <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform ml-2 text-xl font-bold">→</span>
              </a>

              <!-- Задача 3: Медная труба -->
              <a href="interactive.php?tool=temp&task=pipe" class="sketch-card p-4 min-h-[52px] flex items-center justify-between bg-card hover:border-accent group transition-all">
                <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors">Медная труба</span>
                <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform ml-2 text-xl font-bold">→</span>
              </a>

              <!-- Задача 4: Не знаю — помочь -->
              <a href="interactive.php?tool=temp&task=unknown" class="sketch-card p-4 min-h-[52px] flex items-center justify-between bg-card hover:border-accent group transition-all">
                <span class="text-base sm:text-lg font-bold text-ink group-hover:text-accent transition-colors">Не знаю — помочь</span>
                <span class="font-mono text-ink-muted group-hover:text-accent group-hover:translate-x-1 transition-transform ml-2 text-xl font-bold">→</span>
              </a>

            </div>
          </div>

          <!-- Правая колонка: Инженерная схема жала -->
          <div class="flex flex-col items-center justify-center">
            <div class="sketch-card p-3.5 bg-paper w-full max-w-[440px] shadow-xs relative">
              <div class="font-mono text-[11px] text-ink-muted pb-2 border-b border-paper-border mb-2.5">
                <span class="font-bold text-ink uppercase tracking-wider">АНАТОМИЯ ЖАЛА</span>
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
              <p class="font-mono text-[11px] text-ink-muted mt-2.5 text-center leading-normal">
                Так устроено жало, которое греет соединение: медный сердечник передаёт тепло, защитный слой железа предотвращает растворение в олове
              </p>
            </div>
          </div>

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
          <span class="font-mono text-xs text-ink-muted">Инженерные инструкции и практический опыт</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
          <?php foreach ($recent_articles as $article): ?>
            <article class="sketch-card p-4 flex flex-col justify-between space-y-3 bg-card hover:border-accent transition-colors group">
              <div class="space-y-2">
                <div class="flex items-center justify-between font-mono text-[11px] text-ink-muted">
                  <span class="pill-orange uppercase"><?= htmlspecialchars($article['tag']) ?></span>
                  <span>~<?= (int)$article['read_min'] ?> мин</span>
                </div>
                <h3 class="text-base font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                  <a href="article.php?slug=<?= urlencode($article['slug']) ?>">
                    <?= htmlspecialchars($article['title']) ?>
                  </a>
                </h3>
                <p class="text-xs text-ink-muted leading-relaxed font-serif line-clamp-3">
                  <?= htmlspecialchars($article['excerpt']) ?>
                </p>
              </div>

              <div class="pt-3 border-t border-paper-border flex items-center justify-between font-mono text-xs">
                <span class="text-ink-faint text-[11px]"><?= htmlspecialchars($article['author']) ?></span>
                <a href="article.php?slug=<?= urlencode($article['slug']) ?>" class="text-ink font-bold group-hover:text-accent">
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
          <span class="font-mono text-xs text-ink-muted">10 быстрых регламентов</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 pt-2">
          <?php foreach (get_all_lessons() as $idx => $l): ?>
            <article class="sketch-card p-3.5 flex flex-col justify-between space-y-2.5 bg-card hover:border-paper-border-dark transition-all rounded-lg group shadow-xs">
              <div class="space-y-2">
                <div class="flex items-center justify-between font-mono text-[10px]">
                  <span class="pill-orange uppercase font-bold tracking-tight">Урок <?= $l['read_min'] ?>м</span>
                  <span class="text-ink-faint">#<?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                </div>
                <h3 class="text-sm font-bold text-ink group-hover:text-accent transition-colors leading-snug line-clamp-2">
                  <a href="article.php?slug=<?= urlencode($l['slug']) ?>">
                    <?= htmlspecialchars($l['title']) ?>
                  </a>
                </h3>
                <p class="text-[11px] text-ink-muted leading-relaxed font-serif line-clamp-2">
                  <?= htmlspecialchars($l['subtitle']) ?>
                </p>
              </div>

              <div class="pt-2 border-t border-paper-border flex items-center justify-between font-mono text-[11px]">
                <span class="text-ink-faint text-[10px] uppercase truncate max-w-[80px]"><?= htmlspecialchars($l['tag']) ?></span>
                <a href="article.php?slug=<?= urlencode($l['slug']) ?>" class="text-ink font-bold group-hover:text-accent shrink-0">
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
              <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
              <strong>Оловянно-свинцовые припои (ПОС):</strong> ГОСТ 21930 / ГОСТ 21931
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
              <strong>Бессвинцовые сплавы (SAC305):</strong> Паспорта производителей / ISO 9453
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
              <strong>Критерии пайки:</strong> IPC-A-610 (внешний вид соединений)
            </span>
          </div>
        </div>

        <!-- Заметка инженера: предостережение по кислоте -->
        <div class="sketch-pencil-orange relative p-5 rounded-lg shadow-xs space-y-3 mt-5">
          <div class="sketch-washi-tape" style="top: -9px; left: 50%; transform: translateX(-50%) rotate(-0.5deg);" aria-hidden="true"></div>
          
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
