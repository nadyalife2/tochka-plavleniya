<?php
/**
 * Template Name: Политика Cookie
 *
 * @package TCHP
 * @version 2.1.0
 */

get_header();
?>

<main class="w-full flex-grow pt-8 pb-16">
  <div class="max-w-[840px] mx-auto px-5 sm:px-8 space-y-10">

    <!-- Breadcrumb -->
    <nav class="text-[12px] font-mono text-ink-faint flex items-center gap-1.5 flex-wrap">
      <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/')); ?>">Главная</a>
      <span>→</span>
      <span class="text-ink">Политика Cookie</span>
    </nav>

    <!-- Header Section -->
    <div class="space-y-3 border-b border-paper-border pb-6">
      <div class="inline-block pill-tag-yellow font-mono text-xs">Технический регламент</div>
      <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink font-sans">
        Политика использования файлов Cookie и локального хранилища
      </h1>
      <p class="text-sm sm:text-base text-ink-muted leading-relaxed font-serif italic">
        Мы уважительно относимся к вашей приватности. Ниже описано, какие технические данные и cookies сохраняются в браузере.
      </p>
    </div>

    <!-- Content Sections -->
    <div class="space-y-6 text-[15px] text-ink/85 leading-relaxed">

      <section class="border border-paper-border rounded-lg bg-paper-subtle/30 p-6 space-y-3">
        <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
          <span class="text-brand-orange font-bold">01.</span> Что такое файлы Cookie?
        </h2>
        <p>
          Cookie (куки) — это небольшие фрагменты текста, которые веб-сервер отправляет браузеру пользователя. При повторном посещении браузер передает эти данные сайту для восстановления персональных настроек отображения.
        </p>
      </section>

      <section class="border border-paper-border rounded-lg bg-paper-subtle/30 p-6 space-y-4">
        <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
          <span class="text-brand-orange font-bold">02.</span> Какие Cookie и параметры мы используем
        </h2>
        <p>Наш портал использует минимальный технический набор без сбора персональных данных:</p>

        <div class="overflow-x-auto">
          <table class="w-full text-left font-mono text-xs border border-paper-border rounded">
            <thead>
              <tr class="bg-paper-subtle text-ink border-b border-paper-border">
                <th class="p-3">Ключ</th>
                <th class="p-3">Хранилище</th>
                <th class="p-3">Назначение</th>
                <th class="p-3">Срок</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-paper-border text-ink-muted">
              <tr>
                <td class="p-3 font-bold text-ink"><code>tp_theme</code></td>
                <td class="p-3">localStorage</td>
                <td class="p-3">Сохраняет выбранную тему оформления (светлая / тёмная) для предотвращения мерцания экрана.</td>
                <td class="p-3">Постоянно</td>
              </tr>
              <tr>
                <td class="p-3 font-bold text-ink"><code>tp_cookie_consent</code></td>
                <td class="p-3">localStorage</td>
                <td class="p-3">Запоминает факт согласия с информационным баннером, чтобы не показывать его повторно.</td>
                <td class="p-3">1 год</td>
              </tr>
              <tr>
                <td class="p-3 font-bold text-ink"><code>_ym_uid</code></td>
                <td class="p-3">Cookie</td>
                <td class="p-3">Обезличенный идентификатор сессии Яндекс Метрики (агрегированная аналитика без PII).</td>
                <td class="p-3">1 год</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="border border-paper-border rounded-lg bg-paper-subtle/30 p-6 space-y-3">
        <h2 class="text-base font-bold text-ink font-mono uppercase tracking-wider flex items-center gap-2">
          <span class="text-brand-orange font-bold">03.</span> Как управлять Cookie
        </h2>
        <p>
          Вы можете в любой момент отключить или очистить сохраненные Cookie в настройках своего интернет-браузера (в разделах «Конфиденциальность и безопасность»). Отключение технических cookies может сбросить сохраненную тему сайта на светлую по умолчанию.
        </p>
      </section>

    </div>

  </div>
</main>

<?php
get_footer();
