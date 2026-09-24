<?php
/**
 * cookies.php — Политика использования файлов cookie
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';

$page_title = "Политика использования файлов cookie — ТОЧКА ПЛАВЛЕНИЯ";
$page_desc = "Информация об использовании файлов cookie и аналогичных технологий на портале Точка Плавления.";
$current_page = 'cookies';

include __DIR__ . '/includes/header.php';
?>

  <!-- Main Content -->
  <main class="w-full flex-grow pt-8 pb-20">
    <div class="max-w-[960px] mx-auto px-5 sm:px-8">
      
      <!-- Breadcrumbs -->
      <nav class="text-[12px] font-mono text-ink-faint mb-6 flex items-center gap-1.5" aria-label="Хлебные крошки">
        <a class="hover:text-ink transition-colors" href="/">Главная</a>
        <span>→</span>
        <span class="text-ink">Политика использования файлов cookie</span>
      </nav>

      <!-- Document Hero -->
      <div class="border-b border-paper-border pb-6 mb-8 space-y-3">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="sketch-pill-yellow text-ink font-mono text-xs font-bold">COOKIE POLICY</span>
          <span class="text-xs font-mono text-ink-faint">РЕВИЗИЯ: СЕНТЯБРЬ 2026</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-ink tracking-tight">
          Политика использования файлов cookie
        </h1>
        <p class="text-sm sm:text-base text-ink-muted leading-relaxed font-serif italic max-w-2xl">
          Мы используем минимальный набор технических cookie и локальных настроек, чтобы сайт запоминал тему оформления и ваше согласие.
        </p>
      </div>

      <!-- Legal Sections -->
      <article class="space-y-8 text-sm sm:text-base text-ink-muted leading-relaxed">

        <!-- Section 1 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">01.</span> Что такое файлы cookie?
          </h2>
          <p>
            Cookie (куки) — это небольшие текстовые фрагменты данных, которые ваш браузер сохраняет на устройстве при посещении веб-страниц. Они помогают сайту запомнить ваши предпочтения.
          </p>
        </section>

        <!-- Section 2 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">02.</span> Какие куки мы используем
          </h2>
          <p>Наш портал использует минимальный технический набор cookie-файлов и локальных хранилищ:</p>
          
          <div class="overflow-x-auto mt-4">
            <table class="w-full text-left border-collapse font-sans text-[13px]">
              <thead>
                <tr class="border-b border-paper-border-dark text-ink font-bold">
                  <th class="py-2 px-3">Имя Cookie / Данных</th>
                  <th class="py-2 px-3">Назначение</th>
                  <th class="py-2 px-3">Срок хранения</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-paper-border">
                <tr>
                  <td class="py-3 px-3 font-mono text-ink">tp_cookie_consent</td>
                  <td class="py-3 px-3">Хранит выбор согласия на использование необязательных технологий.</td>
                  <td class="py-3 px-3">1 год</td>
                </tr>
                <tr>
                  <td class="py-3 px-3 font-mono text-ink">tp_theme</td>
                  <td class="py-3 px-3">Сохраняет выбранную тему (светлая/тёмная) в локальном хранилище (localStorage).</td>
                  <td class="py-3 px-3">До очистки кэша</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Section 3 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">03.</span> Как отключить cookie?
          </h2>
          <p>
            Вы можете запретить сохранение cookie в настройках любого браузера (Chrome, Firefox, Safari, Edge). Обратите внимание, что в этом случае баннер куки будет выводиться при каждом визите, а выбранная тема оформления может не сохраняться.
          </p>
        </section>

        <!-- Section 4 -->
        <section class="border border-paper-border rounded-lg bg-card p-6 space-y-3">
          <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
            <span class="text-accent font-bold">04.</span> Контакты
          </h2>
          <p>
            Если у вас есть вопросы по работе портала, напишите нам по адресу: <code class="font-mono text-ink bg-paper-subtle px-1 py-0.5 rounded border border-paper-border">privacy@tochka-plavleniya.ru</code>
          </p>
        </section>

      </article>

      <div class="mt-10 pt-6 border-t border-paper-border flex flex-wrap items-center justify-between gap-4 text-xs font-mono text-ink-faint">
        <div>ТОЧКА ПЛАВЛЕНИЯ · 2026</div>
        <div class="flex items-center gap-4">
          <a class="text-ink hover:underline" href="terms.php">Пользовательское соглашение →</a>
          <a class="text-ink hover:underline" href="privacy.php">Политика конфиденциальности →</a>
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
