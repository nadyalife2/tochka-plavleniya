<?php
/**
 * footer.php — подвал сайта «Точка плавления»
 */
$year = date('Y');
?>

<!-- COOKIE BANNER -->
<?php include __DIR__ . '/cookie-banner.php'; ?>

<!-- FOOTER -->
<footer class="footer">
  <div class="container">
    <div class="footer__grid">

      <div class="footer__col">
        <p class="footer__logo">⊕ Точка плавления</p>
        <p class="footer__desc">Образовательный ресурс для мейкеров, студентов и инженеров. Пайка без страха — от основ до BGA.</p>
      </div>

      <div class="footer__col">
        <p class="footer__title">Разделы</p>
        <ul class="footer__links">
          <li><a href="/tag/basics">Основы</a></li>
          <li><a href="/tag/smd">SMD</a></li>
          <li><a href="/tag/bga">BGA</a></li>
          <li><a href="/tag/remont">Ремонт</a></li>
          <li><a href="/tag/instrumenty">Инструменты</a></li>
        </ul>
      </div>

      <div class="footer__col">
        <p class="footer__title">Проект</p>
        <ul class="footer__links">
          <li><a href="/interactive">Инструменты</a></li>
          <li><a href="/#courses">Курсы</a></li>
          <li><a href="/cookies">Политика куки</a></li>
          <li><a href="/privacy">Конфиденциальность</a></li>
        </ul>
      </div>

      <div class="footer__col">
        <p class="footer__title">Сообщество</p>
        <ul class="footer__links">
          <li><a href="#">Telegram</a></li>
          <li><a href="#">YouTube</a></li>
          <li><a href="#">VK</a></li>
        </ul>
      </div>

    </div>

    <div class="footer__bottom">
      <span>© <?= $year ?> Точка плавления</span>
      <span>Engineered for precision.</span>
    </div>
  </div>
</footer>

<!-- Кнопка focus-mode (только для статей) -->
<?php if (!empty($is_article)): ?>
<button class="focus-toggle" aria-label="Режим фокуса">[ режим фокуса ]</button>
<?php endif; ?>

</div><!-- /page-sheet -->

<script src="/assets/js/main.js"></script>
<script src="/assets/js/cookie.js"></script>
<?php if (!empty($extra_js)): ?>
  <script src="/assets/js/<?= $extra_js ?>"></script>
<?php endif; ?>
</body>
</html>
