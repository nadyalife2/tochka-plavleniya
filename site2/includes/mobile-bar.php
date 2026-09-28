<?php
/**
 * mobile-bar.php — Нижняя закрепленная панель быстрого доступа (Mobile Quick Bar)
 * Предоставляет инженеру мгновенный доступ к поиску, калькуляторам и сплавам со смартфона.
 */
?>
<aside class="mobile-quick-bar select-none" aria-label="Быстрый мобильный доступ">
  
  <!-- 1. Global Search Trigger -->
  <button type="button" class="open-search-trigger flex flex-col items-center justify-center min-w-[64px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[11px] font-mono cursor-pointer" aria-label="Открыть поиск">
    <span class="material-symbols-outlined text-[20px] text-accent">search</span>
    <span class="text-accent font-bold mt-0.5">Поиск</span>
  </button>

  <!-- 2. Калькуляторы / Верстак -->
  <a href="interactive.php" class="flex flex-col items-center justify-center min-w-[64px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[11px] font-mono">
    <span class="material-symbols-outlined text-[20px]">calculate</span>
    <span class="mt-0.5 font-medium">Верстак</span>
  </a>

  <!-- 3. Практика -->
  <a href="category.php?slug=praktika" class="flex flex-col items-center justify-center min-w-[64px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[11px] font-mono">
    <span class="material-symbols-outlined text-[20px]">construction</span>
    <span class="mt-0.5 font-medium">Практика</span>
  </a>

  <!-- 4. Старт -->
  <a href="category.php?slug=start" class="flex flex-col items-center justify-center min-w-[64px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-[11px] font-mono">
    <span class="material-symbols-outlined text-[20px]">rocket_launch</span>
    <span class="mt-0.5 font-medium">Старт</span>
  </a>

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
