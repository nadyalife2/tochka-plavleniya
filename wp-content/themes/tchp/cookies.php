<?php
/**
 * cookies.php — Политика использования файлов cookie
 * Точка Плавления
 */
require_once __DIR__ . '/includes/functions.php';

$page_title   = 'Политика куки — Точка Плавления';
$page_desc    = 'Информация об использовании файлов cookie на портале «Точка Плавления».';
$current_page = 'cookies';
$extra_css    = '/assets/css/article.css';

require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="max-width: 900px;">
  <nav class="breadcrumbs">
    <a href="/">Главная</a>
    <span class="breadcrumb-sep">/</span>
    <span>Политика куки</span>
  </nav>

  <header class="article-hero">
    <h1 class="hero-h1">Политика использования <span class="wavy">Cookie</span></h1>
    <p class="hero-sub">Мы уважительно относимся к вашей приватности. Ниже описано, какие куки мы создаём и зачем.</p>
  </header>

  <article class="article-content">
    <h2>1. Что такое файлы cookie?</h2>
    <p>
      Cookie (куки) — это небольшие текстовые фрагменты данных, которые ваш браузер сохраняет на устройстве при посещении веб-страниц. Они помогают сайту запомнить ваши предпочтения.
    </p>

    <h2>2. какие куки мы используем</h2>
    <p>Наш портал использует минимальный технический набор cookie-файлов:</p>

    <table>
      <thead>
        <tr>
          <th>Имя Cookie</th>
          <th>Назначение</th>
          <th>Срок хранения</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>cookie_consent</code></td>
          <td>Запоминает ваше согласие с показом баннера куки, чтобы не беспокоить вас повторно.</td>
          <td>1 год</td>
        </tr>
        <tr>
          <td><code>PHPSESSID</code></td>
          <td>Техническая сессия для работы встроенной формы комментариев и тестов.</td>
          <td>До закрытия браузера</td>
        </tr>
      </tbody>
    </table>

    <h2>3. Как отключить cookie?</h2>
    <p>
      Вы можете запретить сохранение cookie в настройках любого браузера (Chrome, Firefox, Safari, Edge). Обратите внимание, что в этом случае баннер куки будет выводиться при каждом визите.
    </p>

    <h2>4. Контакты</h2>
    <p>Если у вас есть вопросы по работе портала, напишите нам по адресу: <code>privacy@tochka-plavleniya.ru</code></p>
  </article>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
