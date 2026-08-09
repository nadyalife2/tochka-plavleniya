<?php
/**
 * cookies.php — Политика использования файлов cookie
 */
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Политика cookie — Точка Плавления';
$page_desc    = 'Политика использования файлов cookie на сайте Точка Плавления.';
$current_page = '';

require_once __DIR__ . '/includes/header.php';
?>

<section class="section">
  <nav class="breadcrumb" aria-label="Хлебные крошки">
    <a href="/">Главная</a>
    <span>›</span>
    <span>Политика куки</span>
  </nav>

  <div class="section-title-row">
    <div class="section-dash"></div>
    <h1 class="section-h2">Политика cookie</h1>
  </div>

  <div style="max-width: 720px;">
    <div class="article-body">
      <p>Последнее обновление: <strong>01 августа 2026 г.</strong></p>

      <h2 id="what">Что такое файлы cookie?</h2>
      <p>
        Cookie — небольшие текстовые файлы, которые сохраняются в вашем браузере при посещении сайтов. Они позволяют сайту «запомнить» ваши предпочтения и обеспечить корректную работу функций.
      </p>

      <h2 id="which">Какие cookie мы используем</h2>
      <table>
        <thead>
          <tr><th>Название</th><th>Цель</th><th>Срок хранения</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><code>cookie_consent</code></td>
            <td>Сохранение факта принятия политики куки</td>
            <td>1 год</td>
          </tr>
          <tr>
            <td><code>_ga</code></td>
            <td>Google Analytics — анализ трафика (анонимно)</td>
            <td>2 года</td>
          </tr>
          <tr>
            <td><code>_gid</code></td>
            <td>Google Analytics — сессия</td>
            <td>24 часа</td>
          </tr>
        </tbody>
      </table>

      <h2 id="disable">Как отключить cookie</h2>
      <p>
        Вы можете отключить использование cookie в настройках браузера. Обратите внимание: отключение cookie может повлиять на работу некоторых функций сайта.
      </p>
      <ul>
        <li><strong>Chrome:</strong> Настройки → Конфиденциальность → Файлы cookie</li>
        <li><strong>Firefox:</strong> Настройки → Приватность и защита</li>
        <li><strong>Safari:</strong> Настройки → Конфиденциальность</li>
      </ul>

      <h2 id="contacts">Контакты</h2>
      <p>По вопросам использования данных: <a href="mailto:info@tochka-plavleniya.ru">info@tochka-plavleniya.ru</a></p>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
