<?php
/**
 * mobile-bar.php — Нижняя закрепленная панель быстрого доступа (Mobile Quick Bar)
 * Предоставляет инженеру мгновенный доступ к поиску, калькуляторам и сплавам со смартфона.
 */
$site_root = function_exists('home_url') ? home_url('/') : '/';
?>
<aside class="mobile-quick-bar select-none" aria-label="Быстрый мобильный доступ">
  
  <!-- 1. Global Search Trigger -->
  <button type="button" class="open-search-trigger flex flex-col items-center justify-center min-w-[72px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-xs font-mono cursor-pointer" aria-label="Открыть поиск">
    <?= tp_icon('search', 'w-5 h-5 text-ink') ?>
    <span class="text-ink font-bold mt-0.5">Поиск</span>
  </button>

  <!-- 2. Задача (прямой переход к выбору сценария) -->
  <a href="<?= esc_url($site_root) ?>#hero" class="flex flex-col items-center justify-center min-w-[72px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-xs font-mono">
    <?= tp_icon('sliders', 'w-5 h-5') ?>
    <span class="mt-0.5 font-medium">Задача</span>
  </a>

  <!-- 3. Статьи -->
  <a href="<?= esc_url($site_root) ?>#articles" class="flex flex-col items-center justify-center min-w-[72px] min-h-[48px] text-ink-muted hover:text-ink active:scale-95 transition-all text-xs font-mono">
    <?= tp_icon('book-open', 'w-5 h-5') ?>
    <span class="mt-0.5 font-medium">Статьи</span>
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
