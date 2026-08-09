<?php
/**
 * header.php — шапка сайта «Точка плавления»
 * Подключает: Google Fonts, style.css, NAV, GRAIN, COOKIE-BANNER
 */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($meta_desc ?? 'Точка плавления — статьи, инструменты и курсы по пайке электроники') ?>">
  <title><?= htmlspecialchars($page_title ?? 'Точка плавления') ?></title>

  <!-- Fonts: Hanken Grotesk (UI) + Lora (serif/prose) + IBM Plex Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&family=Lora:ital,wght@0,400;0,600;1,400&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (!empty($extra_css)): ?>
    <link rel="stylesheet" href="/assets/css/<?= $extra_css ?>">
  <?php endif; ?>
</head>
<body>
<div class="page-sheet">

<!-- Grain texture -->
<div class="grain" aria-hidden="true"></div>

<!-- NAV -->
<header class="nav">
  <div class="container">
    <nav class="nav__inner" aria-label="Главная навигация">
      <a href="/" class="nav__logo">⊕ Точка <span>плавления</span></a>

      <button class="nav__toggle" aria-label="Меню" aria-expanded="false">
        <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
          <rect width="20" height="2" rx="1" fill="currentColor"/>
          <rect y="6" width="20" height="2" rx="1" fill="currentColor"/>
          <rect y="12" width="14" height="2" rx="1" fill="currentColor"/>
        </svg>
      </button>

      <ul class="nav__links" role="list">
        <li><a href="/#articles">Статьи</a></li>
        <li><a href="/#sections">Разделы</a></li>
        <li><a href="/interactive">Инструменты</a></li>
        <li><a href="/#courses">Курсы</a></li>
      </ul>

      <a href="/#courses" class="btn-nav">Начать →</a>
    </nav>
  </div>
</header>
