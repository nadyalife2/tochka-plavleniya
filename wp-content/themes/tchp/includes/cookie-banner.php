<!-- Cookie Consent Banner (Editorial Style / 152-ФЗ Zero-PII) -->
<div id="cookie-consent-banner" class="fixed bottom-4 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-md z-50 transform transition-all duration-300 translate-y-0 opacity-100 hidden">
  <div class="border border-paper-border-dark bg-paper dark:bg-[#1c1d21] p-4 sm:p-5 rounded-lg shadow-xl space-y-3 relative">
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px] text-brand-orange">cookie</span>
        <span class="font-mono text-xs font-bold uppercase text-ink tracking-wider">Файлы Cookie и аналитика</span>
      </div>
      <button id="cookie-close-btn" type="button" class="text-ink-faint hover:text-ink font-mono text-xs px-1" aria-label="Закрыть">✕</button>
    </div>

    <p class="text-xs text-ink-muted leading-relaxed font-sans">
      Мы используем cookies исключительно для сохранения выбранной темы и анонимной статистики Яндекса. Мы <span class="font-semibold text-ink">не собираем</span> персональные данные (152-ФЗ).
    </p>

    <div class="flex items-center justify-between gap-3 pt-2 border-t border-paper-border text-xs font-mono">
      <a class="text-ink-muted hover:text-ink underline text-[11.5px]" href="<?php echo function_exists('home_url') ? esc_url(home_url('/privacy/')) : 'privacy.php'; ?>">Политика 152-ФЗ →</a>
      <button id="cookie-accept-btn" type="button" class="px-4 py-1.5 bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity shadow-sm border border-paper-border-dark dark:border-paper-border cursor-pointer">
        Понятно
      </button>
    </div>
  </div>
</div>

<script>
  (function() {
    try {
      const consent = localStorage.getItem('tp_cookie_consent');
      const banner = document.getElementById('cookie-consent-banner');
      if (!consent && banner) {
        banner.classList.remove('hidden');
      }

      function acceptCookie() {
        localStorage.setItem('tp_cookie_consent', 'accepted');
        if (banner) {
          banner.classList.add('opacity-0', 'translate-y-4');
          setTimeout(() => banner.classList.add('hidden'), 300);
        }
      }

      const acceptBtn = document.getElementById('cookie-accept-btn');
      const closeBtn = document.getElementById('cookie-close-btn');
      if (acceptBtn) acceptBtn.addEventListener('click', acceptCookie);
      if (closeBtn) closeBtn.addEventListener('click', acceptCookie);
    } catch (e) {
      console.error(e);
    }
  })();
</script>
