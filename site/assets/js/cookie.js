/* Точка плавления — cookie.js */
(function () {
  const banner = document.querySelector('.cookie-banner');
  if (!banner) return;

  /* Проверяем localStorage (PHP setcookie работает только через set-cookie.php) */
  if (localStorage.getItem('cookie_consent') === 'yes') {
    banner.classList.add('hidden');
    return;
  }

  const acceptBtn = banner.querySelector('[data-cookie-accept]');
  const declineBtn = banner.querySelector('[data-cookie-decline]');

  if (acceptBtn) {
    acceptBtn.addEventListener('click', () => {
      localStorage.setItem('cookie_consent', 'yes');
      fetch('/set-cookie.php', { method: 'POST' })
        .catch(() => {});
      banner.classList.add('hidden');
    });
  }
  if (declineBtn) {
    declineBtn.addEventListener('click', () => {
      localStorage.setItem('cookie_consent', 'no');
      banner.classList.add('hidden');
    });
  }
})();