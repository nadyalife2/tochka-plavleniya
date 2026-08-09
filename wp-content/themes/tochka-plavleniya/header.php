<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

  <!-- Grain Overlay Filter -->
  <svg class="grain-overlay">
    <filter id="grain">
      <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="3" stitchTiles="stitch"/>
      <feColorMatrix type="saturate" values="0"/>
    </filter>
    <rect width="100%" height="100%" filter="url(#grain)"/>
  </svg>

  <!-- Paper Sheet Container resting on dark background -->
  <div class="paper-sheet">

    <!-- Sticky Glass Header Nav -->
    <header class="header-nav">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="logo">
        <span class="logo-symbol">⊕</span> <?php bloginfo('name'); ?>
      </a>

      <?php
      if (has_nav_menu('primary')) {
          wp_nav_menu(array(
              'theme_location' => 'primary',
              'container'      => false,
              'menu_class'     => 'nav-links',
          ));
      } else {
          ?>
          <ul class="nav-links">
            <li><a href="#articles">Статьи</a></li>
            <li><a href="#sections">Разделы</a></li>
            <li><a href="#interactive">Интерактив</a></li>
            <li><a href="#courses">Курсы</a></li>
          </ul>
          <?php
      }
      ?>

      <a href="#courses" class="btn-pill btn-dark">Начать →</a>
    </header>
