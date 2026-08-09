<?php
/**
 * footer.php — Footer 4 колонки + закрывающие теги + скрипты
 * Точка Плавления
 */
?>
</div><!-- /page-wrap -->

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-grid">

      <div class="footer-col footer-col--brand">
        <div class="footer-logo">
          <span class="footer-logo-symbol">⊕</span> Точка плавления
        </div>
        <p class="footer-desc">
          Независимая обучающая платформа по пайке и микроэлектронике для инженеров и мейкеров.
        </p>
      </div>

      <div class="footer-col">
        <div class="footer-col-title">Разделы</div>
        <ul class="footer-links">
          <li><a href="/#articles">Статьи</a></li>
          <li><a href="/#sections">Все материалы</a></li>
          <li><a href="/interactive">Интерактив</a></li>
          <li><a href="/#courses">Курсы</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <div class="footer-col-title">Проект</div>
        <ul class="footer-links">
          <li><a href="#">О нас</a></li>
          <li><a href="#">Авторы</a></li>
          <li><a href="#">Поддержка</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <div class="footer-col-title">Сообщество</div>
        <ul class="footer-links">
          <li><a href="#">Telegram</a></li>
          <li><a href="#">YouTube</a></li>
          <li><a href="#">GitHub</a></li>
        </ul>
      </div>

    </div><!-- /footer-grid -->

    <div class="footer-bottom">
      <div>
        &copy; 2026 Точка Плавления. Все права защищены.
        &nbsp;·&nbsp;
        <a href="/privacy">Конфиденциальность</a>
        &nbsp;·&nbsp;
        <a href="/cookies">Политика куки</a>
      </div>
      <div>Engineered for precision.</div>
    </div>

  </div><!-- /footer-inner -->
</footer>

<!-- Scripts -->
<script src="/assets/js/main.js"></script>
<?php if (!empty($extra_js)): ?>
  <script src="<?= e($extra_js) ?>"></script>
<?php endif; ?>
<?php if (!empty($extra_js2)): ?>
  <script src="<?= e($extra_js2) ?>"></script>
<?php endif; ?>

</body>
</html>
