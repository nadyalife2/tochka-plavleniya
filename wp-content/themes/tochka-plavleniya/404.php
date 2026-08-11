<?php
/**
 * 404.php — Страница ошибки 404
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';

$page_title   = '404 — Плата не найдена | Точка Плавления';
$page_desc    = 'Запрошенная страница не существует или была перемещена.';
$current_page = '404';

require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="text-align: center; padding: 60px 24px;">
  <div style="max-width: 480px; margin: 0 auto 32px;">
    <!-- Broken PCB SVG Sketch -->
    <svg viewBox="0 0 320 200" fill="none" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
      <rect x="30" y="20" width="260" height="160" rx="8" fill="rgba(18,24,36,0.8)" stroke="#ef4444" stroke-dasharray="6 4"/>
      <!-- Burnt chip -->
      <rect x="110" y="60" width="100" height="80" rx="4" fill="rgba(239,68,68,0.15)" stroke="#ef4444" stroke-width="2"/>
      <text x="132" y="105" font-family="IBM Plex Mono" font-size="14" fill="#ef4444" stroke="none" font-weight="700">404-ERR</text>
      <!-- Smoke waves -->
      <path d="M140 50 Q130 30 145 15 T135 0" stroke="#94a3b8" opacity="0.6" stroke-width="1.4"/>
      <path d="M180 50 Q190 30 175 15 T185 0" stroke="#94a3b8" opacity="0.6" stroke-width="1.4"/>
      <!-- Burnt trace crack -->
      <polyline points="50,100 80,100 95,85 110,85" stroke="#ef4444"/>
      <circle cx="80" cy="100" r="3" fill="#ef4444"/>
      <!-- Skull / Warning icon -->
      <text x="235" y="65" font-size="28" stroke="none">💀</text>
    </svg>
  </div>

  <div class="hero-badge" style="background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.4); color:#ef4444;">
    ⚡ Ошибка 404: Короткое замыкание
  </div>

  <h1 class="hero-h1" style="margin-bottom: 16px;">Плата <span class="wavy" style="background:linear-gradient(135deg, #ef4444, #ff6b2b); -webkit-background-clip:text;">не найдена</span></h1>
  <p class="hero-sub" style="margin: 0 auto 32px; max-width: 520px;">
    Запрошенный URL не существует, был перенесён или сгорел при неудачном реболлинге. Перейдите на главную страницу или воспользуйтесь инструментами.
  </p>

  <div class="hero-btns" style="justify-content: center;">
    <a href="/" class="btn-pill btn-glow">Вернуться на главную →</a>
    <a href="/interactive" class="btn-pill btn-outline">К инструментам</a>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
