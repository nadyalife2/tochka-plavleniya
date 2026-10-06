<?php
/**
 * The template for displaying 404 pages (Not Found)
 *
 * @package TCHP
 * @version 2.0.0
 */

get_header();
?>

<main class="w-full flex-grow pt-12 pb-20">
  <div class="max-w-[700px] mx-auto px-5 sm:px-8 text-center space-y-6">

    <!-- Broken PCB SVG Sketch -->
    <div class="max-w-[320px] mx-auto">
      <svg viewBox="0 0 320 200" fill="none" class="w-full h-auto" stroke="currentColor">
        <rect x="30" y="20" width="260" height="160" rx="8" class="fill-paper-subtle stroke-paper-border-dark" stroke-width="1.8" stroke-dasharray="6 4"/>
        <rect x="110" y="60" width="100" height="80" rx="4" class="fill-red-500/10 stroke-red-500" stroke-width="2"/>
        <text x="122" y="105" font-family="JetBrains Mono" font-size="14" class="fill-red-500" stroke="none" font-weight="700">404-ERR</text>
        <path d="M140 50 Q130 30 145 15 T135 0" class="stroke-ink-muted" opacity="0.6" stroke-width="1.4"/>
        <path d="M180 50 Q190 30 175 15 T185 0" class="stroke-ink-muted" opacity="0.6" stroke-width="1.4"/>
        <polyline points="50,100 80,100 95,85 110,85" class="stroke-red-500" stroke-width="2"/>
        <circle cx="80" cy="100" r="3" class="fill-red-500" stroke="none"/>
      </svg>
    </div>

    <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded bg-red-500/10 text-red-600 dark:text-red-400 font-mono text-xs font-semibold border border-red-500/20">
      <span class="material-symbols-outlined text-sm">warning</span>
      <span>Ошибка 404: Короткое замыкание</span>
    </div>

    <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-ink">
      Плата не найдена
    </h1>

    <p class="text-ink-muted text-sm leading-relaxed max-w-md mx-auto">
      Запрошенный адрес не существует или был перемещён. Проверьте правильность URL или воспользуйтесь оглавлением лаборатории.
    </p>

    <div class="flex items-center justify-center gap-4 pt-2">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="px-4 py-2 rounded bg-ink text-paper text-xs font-mono font-medium hover:opacity-90 transition-opacity">
        ← На главную
      </a>
      <a href="<?php echo esc_url(home_url('/interactive/')); ?>" class="px-4 py-2 rounded border border-paper-border bg-paper text-ink text-xs font-mono font-medium hover:border-paper-border-dark transition-colors">
        К калькуляторам
      </a>
    </div>

  </div>
</main>

<?php
get_footer();
