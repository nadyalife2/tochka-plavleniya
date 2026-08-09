/**
 * cookie.js — Логика баннера куки
 * Точка Плавления
 */
(function () {
  const banner = document.getElementById('cookie-banner');
  const btn    = document.getElementById('cookie-accept');
  if (!banner || !btn) return;

  btn.addEventListener('click', async () => {
    try {
      await fetch('/set-cookie.php', { method: 'POST' });
    } catch (_) { /* fallback */ }
    // Set via JS too for robustness
    document.cookie = 'cookie_consent=1; path=/; max-age=' + (365 * 24 * 3600) + '; SameSite=Lax';
    banner.style.transform = 'translateX(-50%) translateY(120%)';
    banner.style.opacity   = '0';
    banner.style.transition = 'all 0.4s ease';
    setTimeout(() => banner.remove(), 500);
  });
})();
