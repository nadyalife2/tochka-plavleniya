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

<main class="w-full flex-grow pt-6 sm:pt-12 pb-32 sm:pb-24 min-h-[75vh] flex flex-col justify-center" id="main-content">
<div class="max-w-[720px] mx-auto px-4 sm:px-8 text-center space-y-5">
  
  <div class="inline-flex items-center gap-2 px-3 py-1 font-mono text-stamp font-bold rounded border border-paper-border bg-paper-subtle text-ink uppercase tracking-wider">
    <span class="w-1.5 h-1.5 rounded-full bg-danger inline-block" aria-hidden="true"></span>
    <span>ОШИБКА 404 // ОБРЫВ ЦЕПИ</span>
  </div>

  <!-- Broken PCB SVG Sketch Frame -->
  <div class="sketch-card p-4 sm:p-6 bg-card mx-auto relative overflow-hidden" style="max-width: 420px; margin: 0 auto;">
    <div class="text-xs font-mono text-ink-muted flex items-center justify-between pb-2 border-b border-paper-border mb-3">
      <span class="font-bold text-ink">[ЧЕРТЁЖ // FIG. 404]</span>
      <span class="text-stamp uppercase">СБОЙ ТЕХПРОЦЕССА</span>
    </div>

    <svg viewBox="0 0 320 200" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="mx-auto text-danger" style="max-width: 250px; width: 100%; height: auto; display: block; margin: 0 auto;" aria-label="Схема повреждённой печатной платы">
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

    <div class="pt-3 text-xs font-mono text-ink-faint border-t border-paper-border mt-3 text-center">
      РЕВИЗИЯ СХЕМЫ: ТП-404-ERR // СМАЧИВАНИЕ НЕ ДОСТИГНУТО
    </div>
  </div>

  <div class="space-y-3">
    <h1 class="text-3xl sm:text-4xl font-bold text-ink tracking-tight font-serif">
      Плата не найдена
    </h1>
    <p class="text-base text-ink-muted font-serif max-w-lg mx-auto leading-relaxed">
      Запрошенный регламент не существует, был перемещён в архив или повреждён при термическом демонтаже. Воспользуйтесь навигацией верстака.
    </p>
  </div>

  <div class="space-y-3 pt-2">
    <div>
      <a href="<?= esc_url($site_root) ?>" class="btn btn-primary inline-flex items-center gap-2 px-5 py-2.5 text-xs font-mono font-medium rounded">
        <span>Вернуться на главную</span>
        <span aria-hidden="true">→</span>
      </a>
    </div>
    <div class="text-xs font-mono text-ink-muted flex items-center justify-center gap-2 flex-wrap">
      <span>Или перейти:</span>
      <a href="<?= esc_url($site_root) ?>interactive.php" class="text-ink underline hover:text-accent transition-colors">Интерактивный верстак</a>
      <span class="text-ink-faint">·</span>
      <a href="<?= esc_url($site_root) ?>interactive.php#defect" class="text-ink underline hover:text-accent transition-colors">База дефектов</a>
    </div>
  </div>

  <div class="pt-4">
    <button type="button" onclick="window.openSearchModal &amp;&amp; window.openSearchModal()" class="inline-flex items-center gap-2 px-3 py-1.5 rounded border border-paper-border bg-paper-subtle text-xs font-mono text-ink-muted hover:text-ink hover:border-ink/40 transition-colors">
      <?= tp_icon('search', 'w-3.5 h-3.5 text-accent') ?>
      <span>Поиск по базе знаний</span>
      <kbd class="px-1.5 py-0.5 text-xs bg-paper border border-paper-border rounded">Ctrl+K</kbd>
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
