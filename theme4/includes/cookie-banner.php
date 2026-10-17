<!-- Cookie Consent Banner (Editorial Style / 152-ФЗ Zero-PII) -->
<div id="cookie-consent-banner" class="fixed bottom-[66px] sm:bottom-4 left-3 right-3 sm:left-auto sm:right-6 sm:max-w-[340px] max-w-[calc(100vw-24px)] box-border z-50 transform transition-all duration-300 translate-y-0 opacity-100 hidden">
  <div class="border border-paper-border-dark bg-paper dark:bg-card p-3 sm:p-4 rounded-lg shadow-xl relative">
    
    <!-- Mobile Compact Single-Row Layout -->
    <div class="sm:hidden flex items-center justify-between gap-2 text-xs font-mono">
      <div class="flex items-center gap-1.5 min-w-0">
        <span class="w-1.5 h-1.5 rounded-full bg-accent shrink-0"></span>
        <span class="truncate text-ink font-medium text-[11px]">Cookies: тема и аналитика (152-ФЗ)</span>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <a class="text-ink-muted hover:text-ink underline text-[11px] px-1.5 py-1 min-h-[36px] flex items-center" href="<?php echo function_exists('home_url') ? esc_url(home_url('/privacy/')) : 'privacy.php'; ?>">Инфо</a>
        <button id="cookie-accept-btn" type="button" class="px-3 py-1.5 min-h-[36px] bg-ink text-paper text-[11px] font-mono font-medium rounded hover:opacity-90 transition-opacity cursor-pointer shadow-xs flex items-center justify-center">
          Принять
        </button>
        <button id="cookie-close-btn" type="button" class="text-ink-muted hover:text-ink min-w-[36px] min-h-[36px] flex items-center justify-center p-1.5 rounded hover:bg-paper-subtle cursor-pointer" aria-label="Закрыть без согласия">
          <?= tp_icon('x', 'w-4 h-4') ?>
        </button>
      </div>
    </div>

    <!-- Tablet / Desktop Editorial Card Layout -->
    <div class="hidden sm:block space-y-2.5">
      <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
          <span class="font-mono text-xs font-bold uppercase text-ink tracking-wider">Файлы Cookie и аналитика</span>
        </div>
        <button id="cookie-close-btn-desk" type="button" class="text-ink-faint hover:text-ink font-mono min-w-[32px] min-h-[32px] flex items-center justify-center p-1 rounded hover:bg-paper-subtle cursor-pointer" aria-label="Закрыть без согласия">
          <?= tp_icon('x', 'w-4 h-4') ?>
        </button>
      </div>

      <p class="text-[12px] text-ink-muted leading-relaxed font-sans">
        Мы используем cookies для темы оформления и анонимной аналитики Яндекса. Персональные данные не собираются (152-ФЗ Zero-PII).
      </p>

      <div class="flex items-center justify-between gap-2 pt-1.5 border-t border-paper-border text-[11px] font-mono">
        <a class="text-ink-muted hover:text-ink underline min-h-[32px] flex items-center" href="<?php echo function_exists('home_url') ? esc_url(home_url('/privacy/')) : 'privacy.php'; ?>">Политика 152-ФЗ →</a>
        <button id="cookie-accept-btn-desk" type="button" class="px-3.5 py-1.5 min-h-[36px] bg-ink text-paper text-xs font-mono font-medium rounded hover:opacity-90 transition-opacity shadow-xs border border-paper-border-dark dark:border-paper-border cursor-pointer flex items-center justify-center">
          Принять
        </button>
      </div>
    </div>

  </div>
</div>

<script>
  (function() {
    try {
      const consent = localStorage.getItem('tp_cookie_consent');
      const dismissed = sessionStorage.getItem('tp_cookie_dismissed');
      const banner = document.getElementById('cookie-consent-banner');
      if (!consent && !dismissed && banner) {
        banner.classList.remove('hidden');
      }

      function hideBanner() {
        if (banner) {
          banner.classList.add('opacity-0', 'translate-y-4');
          setTimeout(() => banner.classList.add('hidden'), 300);
        }
      }

      function acceptCookie() {
        try { localStorage.setItem('tp_cookie_consent', 'accepted'); } catch(e){}
        hideBanner();
      }

      function dismissCookie() {
        try { sessionStorage.setItem('tp_cookie_dismissed', '1'); } catch(e){}
        hideBanner();
      }

      const acceptBtn = document.getElementById('cookie-accept-btn');
      const closeBtn = document.getElementById('cookie-close-btn');
      const acceptBtnDesk = document.getElementById('cookie-accept-btn-desk');
      const closeBtnDesk = document.getElementById('cookie-close-btn-desk');
      if (acceptBtn) acceptBtn.addEventListener('click', acceptCookie);
      if (closeBtn) closeBtn.addEventListener('click', dismissCookie);
      if (acceptBtnDesk) acceptBtnDesk.addEventListener('click', acceptCookie);
      if (closeBtnDesk) closeBtnDesk.addEventListener('click', dismissCookie);
    } catch (e) {
      console.error(e);
    }
  })();
</script>
