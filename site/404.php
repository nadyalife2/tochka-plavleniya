<?php
/**
 * 404.php — Страница ошибки «Не найдено»
 */
require_once __DIR__ . '/includes/functions.php';

http_response_code(404);

$page_title   = '404 — Страница не найдена — Точка Плавления';
$page_desc    = 'Страница не найдена.';
$current_page = '';

require_once __DIR__ . '/includes/header.php';
?>

<section class="not-found">
  <!-- Burnt board SVG -->
  <svg width="180" height="120" viewBox="0 0 180 120" fill="none" stroke="#1c242b" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 28px; opacity: 0.5;">
    <rect x="20" y="20" width="140" height="80" rx="6"/>
    <!-- Burnt traces -->
    <polyline points="40,45 80,45 80,75" stroke="#fc6c2b" stroke-width="2"/>
    <line x1="80" y1="75" x2="120" y2="75" stroke="#fc6c2b" stroke-width="2" stroke-dasharray="4 3"/>
    <polyline points="50,60 70,60" stroke-dasharray="3 3"/>
    <!-- Damaged IC -->
    <rect x="90" y="40" width="44" height="36" rx="3" fill="rgba(252,108,43,0.1)" stroke="#fc6c2b" stroke-width="1.8"/>
    <text x="95" y="62" font-family="IBM Plex Mono" font-size="9" fill="#fc6c2b" stroke="none" font-weight="700">💀 RIP</text>
    <!-- Smoke lines -->
    <path d="M112 40 Q108 28 114 22" stroke="#aaa" stroke-width="1.2" stroke-dasharray="3 2"/>
    <path d="M118 40 Q122 26 116 18" stroke="#aaa" stroke-width="1.2" stroke-dasharray="3 2"/>
    <!-- Crack -->
    <path d="M35 55 L50 70 L42 85" stroke="#fc6c2b" stroke-width="1.5" stroke-dasharray="2 2"/>
  </svg>

  <div class="not-found-code">404</div>
  <h1 class="not-found-title">Плата не найдена</h1>
  <p class="not-found-sub">Кажется, эта страница сгорела. Проверьте URL или вернитесь на главную.</p>
  <a href="/" class="btn-pill btn-dark">← На главную</a>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
