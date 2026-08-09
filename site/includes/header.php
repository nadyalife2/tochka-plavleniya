<?php
/**
 * header.php — <head> + sticky glassmorphism NAV
 * Точка Плавления
 */
$base_url = '/';
$page_title   = $page_title   ?? 'Точка Плавления — Паяй. Твори. Понимай.';
$page_desc    = $page_desc    ?? 'Современный портал по пайке и микроэлектронике. Гайды, калькуляторы и курсы для мейкеров.';
$page_og_img  = $page_og_img  ?? '/assets/img/og-default.jpg';
$current_page = $current_page ?? 'home';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($page_desc) ?>">

  <!-- Open Graph -->
  <meta property="og:title"       content="<?= e($page_title) ?>">
  <meta property="og:description" content="<?= e($page_desc) ?>">
  <meta property="og:image"       content="<?= e($page_og_img) ?>">
  <meta property="og:type"        content="website">
  <meta property="og:locale"      content="ru_RU">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;700;800;900&family=IBM+Plex+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- CSS -->
  <link rel="stylesheet" href="/assets/css/style.css">
  <?php if (!empty($extra_css)): ?>
    <link rel="stylesheet" href="<?= e($extra_css) ?>">
  <?php endif; ?>

  <!-- Favicon (inline SVG favicon) -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⊕</text></svg>">
</head>
<body>

<!-- Grain overlay -->
<svg class="grain-overlay" xmlns="http://www.w3.org/2000/svg">
  <filter id="grain">
    <feTurbulence type="fractalNoise" baseFrequency="0.72" numOctaves="4" stitchTiles="stitch"/>
    <feColorMatrix type="saturate" values="0"/>
  </filter>
  <rect width="100%" height="100%" filter="url(#grain)"/>
</svg>

<!-- Scribble hero -->
<svg class="scribble-hero" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <filter id="sf">
      <feTurbulence type="turbulence" baseFrequency="0.02" numOctaves="3" result="noise"/>
      <feDisplacementMap in="SourceGraphic" in2="noise" scale="8" xChannelSelector="R" yChannelSelector="G"/>
    </filter>
  </defs>
  <g filter="url(#sf)" stroke="#fc6c2b" stroke-width="2" fill="none" opacity="0.6">
    <path d="M -20 60 Q 200 20 420 80 T 860 60 T 1300 70"/>
    <path d="M -20 100 Q 300 60 550 110 T 980 90 T 1400 100"/>
    <path d="M -20 140 Q 150 110 380 150 T 780 130 T 1200 140"/>
  </g>
</svg>

<!-- Scribble bottom -->
<svg class="scribble-bottom" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <filter id="sb">
      <feTurbulence type="turbulence" baseFrequency="0.025" numOctaves="3" result="noise"/>
      <feDisplacementMap in="SourceGraphic" in2="noise" scale="6" xChannelSelector="R" yChannelSelector="G"/>
    </filter>
  </defs>
  <g filter="url(#sb)" stroke="#fc6c2b" stroke-width="1.5" fill="none" opacity="0.4">
    <path d="M -20 80 Q 250 40 520 80 T 1040 60 T 1500 75"/>
    <path d="M -20 130 Q 180 100 440 130 T 900 110 T 1380 125"/>
  </g>
</svg>

<!-- NAV -->
<nav class="nav" id="main-nav">
  <div class="nav-inner">
    <a href="/" class="nav-logo">
      <span class="nav-logo-symbol">⊕</span>
      Точка плавления
    </a>
    <ul class="nav-links">
      <li><a href="/#articles"    class="<?= $current_page === 'home'        ? 'active' : '' ?>">Статьи</a></li>
      <li><a href="/#sections"    class="<?= $current_page === 'sections'    ? 'active' : '' ?>">Разделы</a></li>
      <li><a href="/interactive"  class="<?= $current_page === 'interactive' ? 'active' : '' ?>">Интерактив</a></li>
      <li><a href="/#courses"     class="<?= $current_page === 'courses'     ? 'active' : '' ?>">Курсы</a></li>
    </ul>
    <a href="/#articles" class="btn-pill btn-dark">Начать →</a>
  </div>
</nav>

<div class="page-wrap">
