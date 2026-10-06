<?php
/**
 * The template for displaying all single posts
 *
 * @package Site2
 * @version 2.1.0
 */

// If viewed in WordPress, sync query vars
if (have_posts()) {
    the_post();
    $post_slug = get_post_field('post_name', get_post());
    if ($post_slug) {
        $_GET['slug'] = $post_slug;
    }
}

require __DIR__ . '/article.php';
