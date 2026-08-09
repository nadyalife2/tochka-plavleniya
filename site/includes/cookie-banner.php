<?php
/**
 * cookie-banner.php — Баннер куки (всплывает при первом визите)
 * Логика: если нет куки cookie_consent — показываем баннер
 */
if (!isset($_COOKIE['cookie_consent'])):
?>
<div class="cookie-banner" id="cookie-banner" role="alertdialog" aria-label="Использование файлов cookie">
  <div class="cookie-banner__content">
    <div class="cookie-banner__text">
      <p>Мы используем куки для аналитики и улучшения сайта. Подробнее — в <a href="/cookies">Политике куки</a>.</p>
    </div>
    <div class="cookie-banner__actions">
      <button class="btn-pill btn-dark" id="cookie-accept">Принять</button>
      <a href="/cookies" class="cookie-banner__link">Подробнее</a>
    </div>
  </div>
</div>

<script src="/assets/js/cookie.js"></script>
<?php endif; ?>
