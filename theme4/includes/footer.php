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

  <!-- Floating Accessible "Подняться к меню" Button -->
  <button id="scroll-to-menu-btn" type="button" aria-label="Подняться к главному меню" title="Подняться к главному меню (Home)" class="fixed bottom-20 sm:bottom-6 right-4 sm:right-6 z-40 inline-flex items-center gap-1.5 px-3 py-2 rounded border border-paper-border hover:border-accent bg-paper/95 dark:bg-card/95 text-ink font-mono text-xs shadow-md transition-all active:translate-y-0.5 cursor-pointer opacity-0 pointer-events-none group duration-200">
    <svg class="w-4 h-4 text-accent transition-transform group-hover:-translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 15-6-6-6 6"/></svg>
    <span class="font-bold text-ink">К меню</span>
    <kbd class="hidden sm:inline-block text-[10px] text-ink-muted bg-paper-subtle px-1.5 py-0.2 rounded border border-paper-border select-none">Home</kbd>
  </button>

  <script>
    (function() {
      const scrollBtn = document.getElementById('scroll-to-menu-btn');
      if (!scrollBtn) return;

      function handleScroll() {
        if (window.pageYOffset > 240) {
          scrollBtn.classList.remove('opacity-0', 'pointer-events-none');
          scrollBtn.classList.add('opacity-100');
        } else {
          scrollBtn.classList.add('opacity-0', 'pointer-events-none');
          scrollBtn.classList.remove('opacity-100');
        }
      }

      window.addEventListener('scroll', handleScroll, { passive: true });

      scrollBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        const logo = document.querySelector('header .logo');
        if (logo && logo.focus) {
          logo.focus({ preventScroll: true });
        }
      });
    })();
  </script>

</body>
</html>
