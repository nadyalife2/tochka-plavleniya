<!-- Footer -->
<footer class="w-full border-t border-paper-border bg-paper-subtle/50 py-8 text-xs font-mono text-ink-muted mt-auto">
  <div class="max-w-[1140px] mx-auto px-5 sm:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
    <p>© <?php echo date('Y'); ?> Точка Плавления. Anti-perfect Handcraft.</p>
    
    <div class="flex items-center gap-5">
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
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/')); ?>">Статьи</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/interactive/')); ?>">Инструменты</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/cookies/')); ?>">Cookies</a>
        <a class="hover:text-ink transition-colors" href="<?php echo esc_url(home_url('/privacy-policy/')); ?>">Privacy</a>
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
