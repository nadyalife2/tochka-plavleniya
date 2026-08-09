    <!-- FOOTER -->
    <footer class="footer">
      <div class="footer-grid">
        <div class="footer-col-1">
          <a href="<?php echo esc_url(home_url('/')); ?>" class="logo">
            <span class="logo-symbol">⊕</span> <?php bloginfo('name'); ?>
          </a>
          <p class="footer-desc"><?php bloginfo('description'); ?></p>
        </div>
        <div>
          <div class="footer-col-title">Разделы</div>
          <ul class="footer-links">
            <li><a href="#articles">Статьи</a></li>
            <li><a href="#sections">Каталог знаний</a></li>
            <li><a href="#interactive">Интерактив</a></li>
            <li><a href="#courses">Курсы</a></li>
          </ul>
        </div>
        <div>
          <div class="footer-col-title">Проект</div>
          <ul class="footer-links">
            <li><a href="#">О проекте</a></li>
            <li><a href="#">Авторы</a></li>
            <li><a href="#">Контакты</a></li>
          </ul>
        </div>
        <div>
          <div class="footer-col-title">Сообщество</div>
          <ul class="footer-links">
            <li><a href="#">Telegram канал</a></li>
            <li><a href="#">YouTube гайды</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. Все права защищены.</div>
        <div>Engineered for precision.</div>
      </div>
    </footer>

  </div><!-- /.paper-sheet -->

<?php wp_footer(); ?>
</body>
</html>
