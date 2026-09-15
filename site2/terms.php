<?php
require_once __DIR__ . '/includes/functions.php';
?>
$page_title = "Пользовательское соглашение — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc = "Пользовательское соглашение, условия использования материалов и инженерный отказ от ответственности портала Точка Плавления.";
$current_page = 'terms';
include __DIR__ . '/includes/header.php';
?>

  <!-- Main Content -->
  <main class="w-full flex-grow pt-8 pb-20">
    <div class="max-w-[960px] mx-auto px-5 sm:px-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5">
        <a class="hover:text-ink transition-colors" href="index.php">Главная</a>
        <span>→</span>
        <span class="text-ink">Пользовательское соглашение</span>
      </nav>

      <!-- Hero -->
      <div class="border-b border-paper-border pb-6 mb-8 space-y-3">
        <div class="flex items-center gap-2">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">TERMS OF USE</span>
          <span class="text-xs font-mono text-ink-faint">РЕВИЗИЯ: СЕНТЯБРЬ 2026</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">
          Пользовательское соглашение и инженерный отказ от ответственности
        </h1>
        <p class="text-sm sm:text-base text-ink-muted leading-relaxed font-serif italic max-w-2xl">
          Условия использования материалов, расчетных моделей и интерактивных инструментов проекта «ТОЧКА ПЛАВЛЕНИЯ».
        </p>
      </div>

      <!-- Legal Content -->
      <article class="space-y-8 text-sm sm:text-base text-ink-muted leading-relaxed">

        <!-- 1. Предмет -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">01.</span> Предмет соглашения
          </h2>
          <p>
            1.1. Настоящее Пользовательское соглашение (далее — «Соглашение») регулирует порядок использования материалов, калькуляторов, таблиц термопрофилей и статей сайта <strong>«ТОЧКА ПЛАВЛЕНИЯ»</strong> (далее — «Сайт»).
          </p>
          <p>
            1.2. Использование любых разделов Сайта означает безоговорочное согласие пользователя с условиями настоящего Соглашения.
          </p>
        </section>

        <!-- 2. Инженерный дисклеймер -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">02.</span> Инженерный отказ от ответственности (Дисклеймер)
          </h2>
          <p>
            2.1. Все опубликованные материалы, включая температурные диапазоны, режимы преднагрева, рецептуры и симуляторы термопрофилей, носят <strong>исключительно научно-познавательный, справочный и ознакомительный характер</strong>.
          </p>
          <p>
            2.2. Пайка радиоэлектронных компонентов, работа с термовоздушными станциями, инфракрасными термостолами и химическими флюсами сопряжена с рисками термических ожогов, повреждения электронных компонентов и выделения аэрозолей.
          </p>
          <p>
            2.3. Администрация Сайта не несет ответственности за любой прямой или косвенный ущерб (повреждение печатных плат, кристалла BGA, оборудования), возникший в результате применения информации или расчетов, размещенных на Сайте. Инженер принимает решения на основе собственного производственного опыта и требований документации завода-изготовителя.
          </p>
        </section>

        <!-- 3. Авторские права -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">03.</span> Интеллектуальная собственность и правила цитирования
          </h2>
          <p>
            3.1. Текстовые материалы, интерактивные алгоритмы калькуляторов, авторские инженерные схемы и дизайн верстака являются объектами авторского права проекта «ТОЧКА ПЛАВЛЕНИЯ».
          </p>
          <p>
            3.2. Свободное цитирование и републикация фрагментов материалов разрешается при обязательном указании активной гиперссылки на первоисточник на сайте <code>tochka-plavleniya.ru</code>.
          </p>
        </section>

        <!-- 4. Изменения -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">04.</span> Изменение условий
          </h2>
          <p>
            4.1. Администрация Сайта оставляет за собой право вносить изменения в настоящее Соглашение в любое время без предварительного уведомления пользователей. Актуальная редакция всегда доступна по адресу <code>/terms.php</code>.
          </p>
        </section>

      </article>

      <div class="mt-10 pt-6 border-t border-paper-border flex flex-wrap items-center justify-between gap-4 text-xs font-mono text-ink-faint">
        <div>ТОЧКА ПЛАВЛЕНИЯ · 2026</div>
        <div class="flex items-center gap-4">
          <a class="text-ink hover:underline" href="privacy.php">Политика конфиденциальности →</a>
          <a class="text-ink hover:underline" href="index.php">На главную →</a>
        </div>
      </div>

    </div>
  </main>

  <!-- Editorial Minimal Footer -->
  <?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>

  <!-- Mobile Sticky Quick-Bar -->
  <?php require_once __DIR__ . '/includes/mobile-bar.php'; ?>

  <!-- Global Engineering Search Modal -->
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
