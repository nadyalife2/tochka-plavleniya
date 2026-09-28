<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/articles-data.php';

$page_title = "Паяй уверенно — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc = "Инженерный справочник и открытая лаборатория: проверенные терморежимы, подбор паяльного оборудования и чек-листы радиомонтажа.";
$current_page = 'index';

// Берём только реальные статьи из базы знаний (без выдуманных данных)
$recent_articles = array_slice($articles, 0, 4);

include __DIR__ . '/includes/header.php';
?>

  <!-- Main Container: 64px vertical spacing between sections, strict 8-point grid -->
  <main class="w-full flex-grow pt-8 pb-16">
    <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 space-y-16">

      <!-- 1. ПЕРВЫЙ ЭКРАН (HERO): Заголовок «Паяй уверенно», одна кнопка, скотч и пометка только здесь -->
      <section class="relative pt-6 pb-4 border-b-2 border-ink/15 space-y-6" id="hero">
        
        <!-- Скотч и пометка (строго только у первого экрана) -->
        <div class="relative inline-block">
          <div class="sketch-washi-tape" style="top: -10px; left: 16px; transform: rotate(-2.5deg);" aria-hidden="true"></div>
          <div class="sketch-sticky-note inline-flex items-center gap-2 px-3 py-1 text-ink font-mono text-xs shadow-sm rotate-[-1deg] rounded-sm">
            <span class="w-2 h-2 rounded-full bg-accent inline-block"></span>
            <span class="font-bold tracking-wider uppercase">ОТК // ЛАБОРАТОРНЫЙ СПРАВОЧНИК 2026</span>
          </div>
        </div>

        <!-- Единственный h1 на странице -->
        <div class="space-y-4 max-w-3xl">
          <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-ink tracking-tight font-sans leading-[1.08]">
            Паяй уверенно
          </h1>
          <p class="text-base sm:text-lg text-ink-muted leading-relaxed font-serif max-w-2xl">
            Инженерный справочник без воды: точные ориентиры температуры жала, подбор инструмента под бюджет и правила монтажа без риска сжечь компоненты или отслоить дорожки.
          </p>
        </div>

        <!-- Ровно одна главная кнопка: высота не менее 48px, видна сразу без прокрутки -->
        <div class="pt-2">
          <a href="interactive.php" class="btn-primary">
            Рассчитать задачу в калькуляторе →
          </a>
        </div>
      </section>


      <!-- 2. МАРШРУТ НОВИЧКА: Ровно 3 шага (не 6 карточек и не 4) -->
      <section class="space-y-4" id="beginner-route">
        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">БЫСТРЫЙ СТАРТ</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Маршрут новичка
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
          
          <!-- Шаг 1 -->
          <a href="category.php?slug=instrumenty" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">ШАГ 01</span>
                <span>ИНСТРУМЕНТ</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Что купить для старта
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Базовый паяльник, припой ПОС-61 и нейтральный флюс-гель. Без переплаты за лишнее оборудование.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-ink flex items-center justify-between">
              <span>Собрать верстак</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

          <!-- Шаг 2 -->
          <a href="interactive.php#temp" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">ШАГ 02</span>
                <span>ТЕМПЕРАТУРА</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Первый контакт и нагрев
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Как выставить температуру на станции, зачем лудить жало и почему нельзя греть плату дольше 3 секунд.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-ink flex items-center justify-between">
              <span>Рассчитать режим</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

          <!-- Шаг 3 -->
          <a href="category.php?slug=oshibki" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">ШАГ 03</span>
                <span>ДИАГНОСТИКА</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Разбор ошибок и брака
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Припой скатывается шариком? Дорожка отслоилась? Интерактивный определитель первопричин брака.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-ink flex items-center justify-between">
              <span>Найти ошибку</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

        </div>
      </section>


      <!-- 3. НОВЫЕ СТАТЬИ: Только реальные статьи из базы, без «120+» -->
      <section class="space-y-4" id="articles">
        <div class="flex items-end justify-between flex-wrap gap-2">
          <div class="space-y-1">
            <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">БАЗА ЗНАНИЙ</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
              Новые статьи
            </h2>
          </div>
          <span class="font-mono text-xs text-ink-muted">Реальные замеры и лабораторные тесты</span>
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


      <!-- 4. ИНСТРУМЕНТЫ: Ровно 3 (Температура, Подбор, Чек-лист). Остальные спрятаны в «Практику» -->
      <section class="space-y-4" id="tools">
        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">ИНТЕРАКТИВНЫЙ ВЕРСТАК</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Инструменты в 1 клик
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
          
          <!-- Инструмент 1: Температура -->
          <a href="interactive.php?tool=temp" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">01 // ТЕРМОРЕЖИМ</span>
                <span>ГОСТ 21931</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Температура жала
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Точный ориентир уставки станции под ПОС-61, SAC305 и тип монтажа без перегрева полигонов.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-accent flex items-center justify-between">
              <span>Открыть расчёт</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

          <!-- Инструмент 2: Подбор паяльника -->
          <a href="interactive.php?tool=iron" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">02 // ОБОРУДОВАНИЕ</span>
                <span>T12 / C245</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Подбор паяльника
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Конфигуратор оборудования и геометрии картриджей под домашний верстак или сервисную мастерскую.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-accent flex items-center justify-between">
              <span>Сконфигурировать</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

          <!-- Инструмент 3: Чек-лист -->
          <a href="interactive.php?tool=checklist" class="sketch-card p-4 flex flex-col justify-between space-y-4 bg-card hover:border-accent transition-colors group">
            <div class="space-y-2">
              <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
                <span class="font-bold text-accent">03 // БЕЗОПАСНОСТЬ</span>
                <span>IPC-A-610</span>
              </div>
              <h3 class="text-lg font-bold text-ink group-hover:text-accent transition-colors leading-snug">
                Чек-лист подготовки
              </h3>
              <p class="text-sm text-ink-muted leading-relaxed font-serif">
                Проверка платы перед включением: антистатика (ESD), очистка от окислов и выбор безопасного флюса.
              </p>
            </div>
            <div class="pt-3 border-t border-paper-border font-mono text-xs font-semibold text-accent flex items-center justify-between">
              <span>Пройти чек-лист</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
          </a>

        </div>

        <!-- Сноска: остальные 5 спрятаны в «Практику» -->
        <div class="pt-2 text-xs font-mono text-ink-muted">
          <span>Остальные 5 инструментов (дозировка пасты, расчет расхода припоя, таблица сплавов и дерево дефектов) собраны в разделе</span>
          <a href="category.php?slug=praktika" class="text-ink font-bold hover:text-accent underline decoration-1 ml-1">Практика монтажа →</a>
        </div>
      </section>


      <!-- 5. ШЕСТЬ РУБРИК -->
      <section class="space-y-4" id="rubrics">
        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">СТРУКТУРА</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Шесть рубрик
          </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
          
          <!-- Рубрика 1 -->
          <a href="category.php?slug=start" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">01 // СТАРТ</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">С чего начать</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Первые шаги, базовая физика смачивания и безопасная организация рабочего места.
            </p>
          </a>

          <!-- Рубрика 2 -->
          <a href="category.php?slug=instrumenty" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">02 // СТАНЦИИ</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">Инструменты и станции</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Паяльники T12/JBC, термовоздушные фены, нижние подогревы и монтажная оптика.
            </p>
          </a>

          <!-- Рубрика 3 -->
          <a href="category.php?slug=materialy" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">03 // ХИМИЯ</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">Материалы и сплавы</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Припои ПОС-61/SAC305, флюсы No-Clean/RMA, пасты и смывки по IPC J-STD-004.
            </p>
          </a>

          <!-- Рубрика 4 -->
          <a href="category.php?slug=praktika" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">04 // ТЕХНОЛОГИЯ</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">Практика монтажа</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Ручной монтаж SMD 0402–1206, пайка корпусов QFN, BGA-реболлинг и прогрев полигонов.
            </p>
          </a>

          <!-- Рубрика 5 -->
          <a href="category.php?slug=oshibki" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">05 // ДИАГНОСТИКА</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">Проблемы и дефекты</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Холодная пайка, перемычки припоя, отрыв дорожек и появление эффекта tombstoning.
            </p>
          </a>

          <!-- Рубрика 6 -->
          <a href="category.php?slug=materialy" class="sketch-card p-4 bg-card hover:border-accent transition-colors group space-y-2">
            <div class="flex items-center justify-between font-mono text-xs text-ink-muted">
              <span class="font-bold text-ink">06 // СТАНДАРТЫ</span>
              <span class="group-hover:translate-x-1 transition-transform">→</span>
            </div>
            <div class="text-base font-bold text-ink group-hover:text-accent">Регламенты и стандарты</div>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Инженерные допуски ГОСТ 21931-76, критерии годности IPC-A-610 и профили J-STD-020E.
            </p>
          </a>

        </div>
      </section>


      <!-- 6. КОРОТКИЙ FAQ ИЗ 4 ВОПРОСОВ -->
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
              Припой ПОС-61 плавится при 183 °C. На паяльной станции выставляйте <strong>260–290 °C</strong>. Разница в 80–100 °C необходима, чтобы компенсировать отвод тепла в медные дорожки платы.
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


      <!-- 7. БЛОК «КАК ПРОВЕРЯЕМ»: кто пишет, откуда цифры, что это ориентир + опасный совет со скотчем -->
      <section class="space-y-4" id="how-we-verify">
        <div class="space-y-1">
          <span class="font-mono text-xs text-accent font-bold uppercase tracking-wider">ПРОЗРАЧНОСТЬ ЛАБОРАТОРИИ</span>
          <h2 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight font-sans">
            Как мы проверяем данные
          </h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
          
          <div class="sketch-card p-4 bg-card space-y-2">
            <div class="font-mono text-xs font-bold text-accent uppercase">01 // КТО ПИШЕТ</div>
            <h3 class="text-base font-bold text-ink">Инженеры-практики</h3>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Материалы готовят мастера сервисных центров и радиомонтажники с опытом сборки многослойных плат, BGA-чипов и мелкосерийного SMD-монтажа.
            </p>
          </div>

          <div class="sketch-card p-4 bg-card space-y-2">
            <div class="font-mono text-xs font-bold text-accent uppercase">02 // ОТКУДА ЦИФРЫ</div>
            <h3 class="text-base font-bold text-ink">Реальные замеры и стандарты</h3>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Температуры и тепловые окна калибруются контактной термопарой на полигонах плат и сверяются со стандартами ГОСТ 21931-76 и IPC J-STD-004/006.
            </p>
          </div>

          <div class="sketch-card p-4 bg-card space-y-2">
            <div class="font-mono text-xs font-bold text-accent uppercase">03 // ЭТО ОРИЕНТИР</div>
            <h3 class="text-base font-bold text-ink">Физический ориентир</h3>
            <p class="text-xs text-ink-muted font-serif leading-relaxed">
              Цифры на дисплее станции — ориентир для старта. Теплоёмкость полигонов и теплоотвод земляных слоев всегда требуют индивидуальной калибровки под конкретную плату.
            </p>
          </div>

        </div>

        <!-- Опасный совет: скотч и пометка-предупреждение (строго только здесь!) -->
        <div class="box-caution-orange relative p-4 rotate-[0.4deg] shadow-sm space-y-2 sketch-border mt-6">
          <div class="sketch-washi-tape" style="top: -9px; right: 28px; transform: rotate(-2deg);" aria-hidden="true"></div>
          <div class="flex items-center justify-between font-mono text-xs font-bold">
            <span class="flex items-center gap-1.5 text-[#d35400] dark:text-[#f39c12]">
              <svg class="w-4 h-4 stroke-current inline-block" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
              ОПАСНЫЙ СОВЕТ // КАТЕГОРИЧЕСКИ ЗАПРЕЩЕНО
            </span>
            <span class="px-2 py-0.5 text-[10px] bg-paper text-ink border border-paper-border-dark rounded font-bold">СТОП</span>
          </div>
          <p class="font-mono text-xs text-ink leading-relaxed">
            <strong>Никогда не используйте активную кислоту (паяльную кислоту, ортофосфорную) для пайки печатных плат!</strong> Кислота моментально впитывается в структуру стеклотекстолита FR-4, вызывает паразитные токи утечки и гарантированно съедает тонкие медные дорожки через 2–4 месяца. Для электроники допустимы исключительно нейтральные канифольные и No-Clean флюсы.
          </p>
        </div>
      </section>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Mobile Sticky Quick Bar (Поиск, Практика, Старт) -->
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
