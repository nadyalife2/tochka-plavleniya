<?php
/**
 * The template for displaying archive pages
 *
 * @package ТОЧКА ПЛАВЛЕНИЯ
 * @version 4.0.0
 */

if (empty($_GET['slug']) && empty($_GET['tag']) && empty($_GET['cat'])) {
    $_GET['slug'] = 'all';
}
require __DIR__ . '/category.php';
