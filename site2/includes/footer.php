<?php
/**
 * footer.php — Единый полный подвал ТОЧКА ПЛАВЛЕНИЯ (Editorial Lab)
 * Подключает редакционный подвал, мобильную плашку, поиск Ctrl+K, переключатель темы и закрывает </body></html>
 */

// 1. Editorial Minimal Footer (включает cookie-banner и wp_footer())
require_once __DIR__ . '/footer-editorial.php';

// 2. Mobile Sticky Quick Bar (44px WCAG Touch Targets)
require_once __DIR__ . '/mobile-bar.php';

// 3. Global Engineering Search Modal (Ctrl+K)
require_once __DIR__ . '/search-modal.php';
?>

  <!-- Global Theme Toggle Handler -->
  <script>
    (function() {
      const toggle = document.getElementById('theme-toggle');
      if (toggle) {
        toggle.addEventListener('click', function() {
          const isDark = document.documentElement.classList.toggle('dark');
          localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
        });
      }
    })();
  </script>

</body>
</html>
