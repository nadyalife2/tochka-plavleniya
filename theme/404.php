<?php
/**
 * 404.php — Страница ошибки 404
 * Точка Плавления
 */

// Fail-safe: If WordPress mistakenly sends root requests to 404, load home page
if ((function_exists('is_front_page') && is_front_page()) || (isset($_SERVER['REQUEST_URI']) && trim($_SERVER['REQUEST_URI'], '/') === '')) {
    require __DIR__ . '/index.php';
    exit;
}

require_once __DIR__ . '/includes/functions.php';

$page_title   = '404 — Плата не найдена | Точка Плавления';
$page_desc    = 'Запрошенная страница не существует или была перемещена.';
$current_page = '404';
$site_root    = function_exists('home_url') ? home_url('/') : '/';

require_once __DIR__ . '/includes/header.php';
?>

<main class="w-full flex-grow pt-12 pb-20" id="main-content">
<div class="max-w-[800px] mx-auto px-5 sm:px-8 text-center space-y-6">
  
  <div class="inline-flex items-center gap-2 px-3 py-1 font-mono text-xs font-bold rounded bg-red-100 dark:bg-red-950/40 border border-red-300 dark:border-red-800/60 text-red-700 dark:text-red-400">
    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" aria-hidden="true"></span>
    <span>[ОШИБКА 404 // ОБРЫВ ЦЕПИ]</span>
  </div>

  <!-- Broken PCB SVG Sketch Frame -->
  <div class="sketch-card p-6 sm:p-8 bg-card mx-auto relative overflow-hidden" style="max-width: 480px; margin: 0 auto;">
    <div class="text-[11px] font-mono text-ink-muted flex items-center justify-between pb-3 border-b border-paper-border mb-4">
      <span class="font-bold text-red-600 dark:text-red-400">[ЧЕРТЁЖ // FIG. 404]</span>
      <span>СБОЙ ТЕХПРОЦЕССА</span>
    </div>

    <svg viewBox="0 0 320 200" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-red-500 dark:text-red-400" style="max-width: 320px; width: 100%; height: auto; display: block; margin: 0 auto;" aria-label="Схема повреждённой печатной платы">
      <rect x="30" y="20" width="260" height="160" rx="8" fill="var(--color-card, #ffffff)" stroke="currentColor" stroke-dasharray="6 4"/>
      <!-- Burnt chip -->
      <rect x="110" y="60" width="100" height="80" rx="4" fill="currentColor" fill-opacity="0.1" stroke="currentColor" stroke-width="2"/>
      <text x="122" y="105" font-family="var(--font-mono, monospace)" font-size="13" fill="currentColor" stroke="none" font-weight="700">404 // SHORT</text>
      <!-- Smoke waves -->
      <path d="M140 50 Q130 30 145 15 T135 0" stroke="var(--color-ink-muted, #706d64)" opacity="0.6" stroke-width="1.4"/>
      <path d="M180 50 Q190 30 175 15 T185 0" stroke="var(--color-ink-muted, #706d64)" opacity="0.6" stroke-width="1.4"/>
      <!-- Burnt trace crack -->
      <polyline points="50,100 80,100 95,85 110,85" stroke="currentColor"/>
      <circle cx="80" cy="100" r="3" fill="currentColor"/>
      <!-- Warning trace node -->
      <circle cx="245" cy="65" r="8" fill="currentColor" fill-opacity="0.15" stroke="currentColor" stroke-width="1.5"/>
      <path d="M245 60v6m0 3v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </svg>

    <div class="pt-3 text-[10px] font-mono text-ink-faint border-t border-paper-border mt-3 text-center">
      РЕВИЗИЯ СХЕМЫ: ТП-404-ERR // СМАЧИВАНИЕ НЕ ДОСТИГНУТО
    </div>
  </div>

  <div class="space-y-3">
    <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight font-sans">
      Плата <span class="text-red-600 dark:text-red-400 underline decoration-wavy decoration-red-500">не найдена</span>
    </h1>
    <p class="text-base text-ink-muted font-serif max-w-lg mx-auto leading-relaxed">
      Запрошенный регламент не существует, был перемещён в архив или повреждён при термическом демонтаже. Воспользуйтесь навигацией верстака.
    </p>
  </div>

  <div class="flex items-center justify-center gap-3 flex-wrap font-mono text-xs pt-2">
    <a href="<?= esc_url($site_root) ?>" class="btn-primary inline-flex items-center gap-1.5 px-4 py-2.5">
      <span>[01] Главный пульт</span>
      <span aria-hidden="true">→</span>
    </a>
    <a href="<?= esc_url($site_root) ?>interactive.php" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded border border-paper-border bg-paper text-ink hover:border-accent hover:text-accent transition-colors shadow-2xs">
      <span>[02] Интерактивный верстак</span>
      <span aria-hidden="true">→</span>
    </a>
    <a href="<?= esc_url($site_root) ?>interactive.php#defect" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded border border-paper-border bg-paper text-ink hover:border-amber-500 hover:text-amber-600 transition-colors shadow-2xs">
      <span>[03] База дефектов</span>
      <span aria-hidden="true">→</span>
    </a>
  </div>

  <div class="pt-4">
    <button type="button" onclick="window.openSearchModal &amp;&amp; window.openSearchModal()" class="inline-flex items-center gap-2 px-3 py-1.5 rounded border border-paper-border bg-paper-subtle text-xs font-mono text-ink-muted hover:text-ink hover:border-ink/40 transition-colors">
      <span class="material-symbols-outlined text-[15px] text-accent">search</span>
      <span>Поиск по базе знаний</span>
      <kbd class="px-1.5 py-0.5 text-[10px] bg-paper border border-paper-border rounded">Ctrl+K</kbd>
    </button>
  </div>

</div>
</main>

<!-- Global Engineering Search Modal (Ctrl+K) -->
<?php require_once __DIR__ . '/includes/search-modal.php'; ?>

<!-- Editorial Minimal Footer -->
<?php require_once __DIR__ . '/includes/footer-editorial.php'; ?>
</body>
</html>
