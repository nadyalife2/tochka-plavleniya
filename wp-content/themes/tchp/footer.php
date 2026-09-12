<!-- Editorial Minimal Footer (Unified across all pages) -->
<footer class="w-full border-t border-paper-border bg-paper py-10 mt-12 text-ink-muted text-xs font-mono">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div class="space-y-1.5">
      <div class="flex items-center gap-2 text-ink font-semibold">
        <a class="logo text-sm hover:opacity-85 transition-opacity" href="<?php echo esc_url(home_url('/')); ?>">
          ТОЧКА<span>.</span>ПЛАВЛЕНИЯ
        </a>
        <span class="text-ink-faint">·</span>
        <span class="text-[11px] font-normal text-ink-faint">Инженерный регламент v2.4</span>
      </div>
      <p class="text-[12px] text-ink-muted max-w-md">
        Инженерный справочник, регламенты поверхностного монтажа и открытая документация по пайке и теплофизике компонентов.
      </p>
      <div class="text-ink-faint text-[11px] pt-1">
        © <?php echo date('Y'); ?> ТОЧКА ПЛАВЛЕНИЯ. Все права защищены.
      </div>
    </div>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px]">
      <?php if (has_nav_menu('footer')) : ?>
        <?php
        wp_nav_menu([
          'theme_location' => 'footer',
          'container'      => false,
          'items_wrap'     => '%3$s',
          'fallback_cb'    => false,
        ]);
        ?>
      <?php else : ?>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/#articles')); ?>">Статьи</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/#calculator')); ?>">Калькулятор флюсов</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/#table')); ?>">Таблица припоев</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/')); ?>">Верстак</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/privacy/')); ?>">Конфиденциальность</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/terms/')); ?>">Соглашение</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<script>
  // Theme toggle functionality
  const themeToggle = document.getElementById('theme-toggle');
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const isDark = document.documentElement.classList.toggle('dark');
      localStorage.setItem('tp_theme', isDark ? 'dark' : 'light');
    });
  }
</script>

<?php
// Optional cookie banner
if (file_exists(get_template_directory() . '/includes/cookie-banner.php')) {
    include get_template_directory() . '/includes/cookie-banner.php';
}
?>

<?php wp_footer(); ?>
</body>
</html>
