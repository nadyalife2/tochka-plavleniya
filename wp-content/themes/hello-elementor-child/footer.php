<?php
/**
 * Footer template — ТОЧКА ПЛАВЛЕНИЯ (Hello Elementor Child)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

  <!-- Editorial Minimal Footer -->
  <footer class="w-full border-t border-paper-border bg-paper py-10 mt-auto text-ink-muted text-xs font-mono">
    <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
      <div class="space-y-1.5">
        <div class="flex items-center gap-2">
          <a class="logo text-sm hover:opacity-85 transition-opacity" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
          </a>
          <span class="text-ink-faint">·</span>
          <span class="text-[11px] font-normal text-ink-faint font-mono">Инженерный регламент v2.4</span>
        </div>
        <p class="text-[12px] text-ink-muted max-w-md">
          Инженерный справочник, регламенты поверхностного монтажа и открытая документация по пайке и теплофизике компонентов.
        </p>
        <div class="text-ink-faint text-[11px] pt-1">
          © <?php echo date( 'Y' ); ?> ТОЧКА ПЛАВЛЕНИЯ. Все права защищены.
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/' ) ); ?>">Статьи</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/interactive#table' ) ); ?>">Реестр сплавов</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/privacy' ) ); ?>">Конфиденциальность (152-ФЗ)</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/terms' ) ); ?>">Соглашение</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url( home_url( '/interactive' ) ); ?>">Верстак</a>
      </div>
    </div>
  </footer>

  <!-- Theme Toggle JS -->
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

<?php wp_footer(); ?>
</body>
</html>
