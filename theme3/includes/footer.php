<?php
/**
 * footer.php — Единый полный подвал ТОЧКА ПЛАВЛЕНИЯ (Editorial Lab)
 * Подключает редакционный подвал, мобильную плашку, поиск Ctrl+K, переключатель темы и закрывает </body></html>
 */

// 1. Editorial Minimal Footer (включает cookie-banner и wp_footer())
require_once __DIR__ . '/footer-editorial.php';

// 2. Mobile Sticky Quick Bar (44px WCAG Touch Targets)
require_once __DIR__ . '/mobile-bar.php';

// 3. Lab Quick Drawer (Teenage Engineering / Emil Kowalski tactile drawer)
require_once __DIR__ . '/lab-drawer.php';

// 4. Global Engineering Search Modal (Ctrl+K)
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

  <!-- Dynamic Side-Logo Script (Matching Headphones Site Behavior) -->
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const sideLogo = document.getElementById('sideLogo');
      const hero = document.querySelector('.hero') || document.getElementById('top');
      const container = document.querySelector('.hero .container') || document.querySelector('.container');
      const topElement = document.getElementById('top') || document.body;

      if (sideLogo) {
        sideLogo.addEventListener('click', function(e) {
          e.preventDefault();
          if (topElement) {
            topElement.scrollIntoView({ behavior: 'smooth' });
          } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
          }
        });

        sideLogo.addEventListener('mousedown', function() {
          this.style.transform = 'scale(0.96)';
        });

        sideLogo.addEventListener('mouseup', function() {
          this.style.transform = 'scale(1)';
        });

        sideLogo.addEventListener('mouseleave', function() {
          this.style.transform = 'scale(1)';
        });
      }

      const GAP = 6;

      function applyFixedLeft() {
        if (!sideLogo || !container) return;
        if (window.innerWidth <= 768) {
          sideLogo.style.left = '';
          return;
        }
        const containerRect = container.getBoundingClientRect();
        const logoWidth = sideLogo.offsetWidth || 36;

        if (containerRect.left > 0 && logoWidth > 0) {
          const newLeft = containerRect.left - logoWidth - GAP;
          if (newLeft > 0) {
            sideLogo.style.left = newLeft + 'px';
          } else {
            sideLogo.style.left = '10px';
          }
        }
      }

      function onScroll() {
        if (!sideLogo || !hero) return;
        const heroTop = hero.getBoundingClientRect().top + window.pageYOffset;

        if (window.innerWidth <= 768) {
          if (window.pageYOffset > 100) {
            sideLogo.classList.add('is-fixed');
          } else {
            sideLogo.classList.remove('is-fixed');
          }
          sideLogo.style.left = '';
          return;
        }

        if (window.pageYOffset >= heroTop + 30) {
          sideLogo.classList.add('is-fixed');
          applyFixedLeft();
        } else {
          sideLogo.classList.remove('is-fixed');
          sideLogo.style.left = '';
        }
      }

      let ticking = false;
      function updateScroll() {
        onScroll();
        ticking = false;
      }

      function requestScrollUpdate() {
        if (!ticking) {
          requestAnimationFrame(updateScroll);
          ticking = true;
        }
      }

      window.addEventListener('scroll', requestScrollUpdate, { passive: true });
      window.addEventListener('touchmove', requestScrollUpdate, { passive: true });
      window.addEventListener('resize', function () {
        if (window.innerWidth <= 768) {
          if (sideLogo) sideLogo.style.left = '';
          return;
        }
        if (sideLogo && sideLogo.classList.contains('is-fixed')) {
          applyFixedLeft();
        }
      });

      function initPosition() {
        if (sideLogo && sideLogo.classList.contains('is-fixed')) {
          applyFixedLeft();
        }
      }
      setTimeout(initPosition, 50);
      window.addEventListener('load', initPosition);
    });
  </script>

</body>
</html>
