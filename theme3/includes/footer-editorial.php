<?php $site_root = function_exists('home_url') ? home_url('/') : '/'; ?>
<!-- Editorial Minimal Footer (Unified across all pages) -->
<footer class="footer">
  <div class="container">
    <div class="footer__row">
      <div class="footer__copy">
        <a class="footer__brand" href="<?= $site_root ?>">
          ТОЧКА<span class="logo-dot">.</span>ПЛАВЛЕНИЯ
        </a>
        <span class="footer__sep">•</span>
        <span>Инженерный регламент и теплофизика монтажа</span>
        <span class="footer__sep">•</span>
        <span>© <?= date('Y') ?></span>
      </div>
      <nav class="footer-nav">
        <a href="<?= $site_root ?>#articles">Статьи</a>
        <a href="<?= $site_root ?>interactive.php#calculator">Калькулятор флюсов</a>
        <a href="<?= $site_root ?>interactive.php#table">Таблица припоев</a>
        <a href="<?= $site_root ?>interactive.php">Верстак</a>
        <a href="<?= $site_root ?>privacy.php">Конфиденциальность</a>
        <a href="<?= $site_root ?>terms.php">Соглашение</a>
      </nav>
    </div>
  </div>
</footer>

<!-- Cookie Consent Banner -->
<?php require_once __DIR__ . '/cookie-banner.php'; ?>

<?php if (function_exists('wp_footer')) wp_footer(); ?>
