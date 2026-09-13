<?php
/**
 * mobile-bar.php — Нижняя закрепленная панель быстрого доступа (Mobile Quick Bar)
 * Предоставляет инженеру мгновенный доступ к поиску, калькуляторам и сплавам со смартфона.
 */
?>
<aside class="mobile-quick-bar backdrop-blur-md select-none" aria-label="Быстрый мобильный доступ">
  
  <!-- Home / Articles -->
  <a href="index.php#articles" class="flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[10px] font-mono">
    <span class="material-symbols-outlined text-[20px]">menu_book</span>
    <span>Журнал</span>
  </a>

  <!-- Global Search Trigger -->
  <button type="button" class="open-search-trigger flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[10px] font-mono cursor-pointer" aria-label="Открыть поиск">
    <span class="material-symbols-outlined text-[20px] text-accent">search</span>
    <span class="text-accent font-semibold">Поиск</span>
  </button>

  <!-- Workbench / Tools -->
  <a href="interactive.php" class="flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[10px] font-mono">
    <span class="material-symbols-outlined text-[20px]">build</span>
    <span>Верстак</span>
  </a>

  <!-- Solder Catalog -->
  <a href="category.php?slug=materialy" class="flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[10px] font-mono">
    <span class="material-symbols-outlined text-[20px]">thermostat</span>
    <span>Сплавы</span>
  </a>

  <!-- Dark Mode Quick Toggle -->
  <button type="button" id="mobile-theme-toggle" class="flex flex-col items-center justify-center min-w-[54px] min-h-[44px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[10px] font-mono cursor-pointer" aria-label="Сменить тему">
    <span class="material-symbols-outlined text-[20px]">contrast</span>
    <span>Тема</span>
  </button>

</aside>

<script>
  (function() {
    document.addEventListener('click', function(e) {
      if (e.target.closest('#mobile-theme-toggle')) {
        e.preventDefault();
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
      }
    });
  })();
</script>
