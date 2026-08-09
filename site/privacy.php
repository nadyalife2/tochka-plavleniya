<?php
/**
 * privacy.php — Политика конфиденциальности
 */
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Политика конфиденциальности — Точка Плавления';
$page_desc    = 'Политика конфиденциальности сайта Точка Плавления.';
$current_page = '';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <nav class="breadcrumb" aria-label="Хлебные крошки">
    <a href="/">Главная</a>
    <span>›</span>
    <span>Политика конфиденциальности</span>
  </nav>

  <div class="section-title-row">
    <div class="section-dash"></div>
    <h1 class="section-h2">Политика конфиденциальности</h1>
  </div>

  <div style="max-width: 720px;">
    <div class="article-body">
      <p>Последнее обновление: <strong>01 августа 2026 г.</strong></p>

      <h2 id="operator">Оператор данных</h2>
      <p>
        ИП «Точка Плавления», ОГРН XXXXXXXX. Email: <a href="mailto:info@tochka-plavleniya.ru">info@tochka-plavleniya.ru</a>
      </p>

      <h2 id="what-collect">Какие данные мы собираем</h2>
      <p>Мы можем собирать следующие данные:</p>
      <ul>
        <li><strong>Технические данные:</strong> IP-адрес, тип браузера, ОС, время визита (через Google Analytics)</li>
        <li><strong>Контактные данные:</strong> email (только при добровольном оставлении через форму обратной связи)</li>
        <li><strong>Файлы cookie:</strong> см. <a href="/cookies">Политику cookie</a></li>
      </ul>

      <h2 id="purpose">Для чего используем данные</h2>
      <ul>
        <li>Анализ трафика и улучшение сайта</li>
        <li>Ответы на обращения пользователей</li>
        <li>Обеспечение безопасности</li>
      </ul>

      <div class="callout callout--tip">
        <div class="callout-label">✓ Гарантия</div>
        Мы не продаём и не передаём ваши данные третьим лицам без вашего согласия.
      </div>

      <h2 id="rights">Права пользователя</h2>
      <p>Вы имеете право:</p>
      <ul>
        <li>Запросить копию ваших данных</li>
        <li>Потребовать удаления данных</li>
        <li>Отозвать согласие в любой момент</li>
      </ul>

      <h2 id="contacts">Контакты</h2>
      <p>
        По всем вопросам о ваших данных: <a href="mailto:info@tochka-plavleniya.ru">info@tochka-plavleniya.ru</a>.<br>
        Ответим в течение 3 рабочих дней.
      </p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
