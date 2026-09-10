<?php
/**
 * Hello Elementor Child Theme functions and definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[ 'hello-elementor-theme-style' ],
		'1.1.0'
	);

	if ( is_single() ) {
		wp_enqueue_style(
			'tchp-article-pro',
			get_stylesheet_directory_uri() . '/assets/css/article-pro.css',
			[],
			'1.1.0'
		);
		wp_enqueue_script(
			'tchp-article-ux',
			get_stylesheet_directory_uri() . '/assets/js/article-ux.js',
			[],
			'1.1.0',
			true
		);
		wp_enqueue_script(
			'tchp-thermal-slider',
			get_stylesheet_directory_uri() . '/assets/js/thermal-slider.js',
			[],
			'1.1.0',
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

/**
 * Output GEO & Schema.org TechArticle JSON-LD in wp_head
 */
function tchp_output_article_schema() {
	if ( ! is_single() ) {
		return;
	}

	global $post;
	$author_id = $post->post_author;
	$author_name = get_the_author_meta( 'display_name', $author_id ) ?: 'Инженер Лаборатории ТЧП';
	$permalink = get_permalink( $post );
	$categories = get_the_category( $post->ID );
	$cat_name = ! empty( $categories ) ? $categories[0]->name : 'Пайка и Монтаж';

	$schema = [
		'@context' => 'https://schema.org',
		'@graph' => [
			[
				'@type' => 'TechArticle',
				'@id' => $permalink . '#article',
				'headline' => get_the_title( $post ),
				'description' => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'inLanguage' => 'ru',
				'datePublished' => get_the_date( 'Y-m-d', $post ),
				'dateModified' => get_the_modified_date( 'Y-m-d', $post ),
				'author' => [
					'@type' => 'Person',
					'name' => $author_name,
					'jobTitle' => 'Ведущий инженер-технолог пайки и монтажа РЭА',
					'worksFor' => [
						'@type' => 'Organization',
						'name' => 'Точка Плавления',
						'url' => home_url( '/' )
					]
				],
				'publisher' => [
					'@type' => 'Organization',
					'name' => get_bloginfo( 'name' ),
					'url' => home_url( '/' )
				],
				'mainEntityOfPage' => $permalink
			],
			[
				'@type' => 'BreadcrumbList',
				'itemListElement' => [
					[
						'@type' => 'ListItem',
						'position' => 1,
						'name' => 'Главная',
						'item' => home_url( '/' )
					],
					[
						'@type' => 'ListItem',
						'position' => 2,
						'name' => $cat_name,
						'item' => ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : home_url( '/' )
					],
					[
						'@type' => 'ListItem',
						'position' => 3,
						'name' => get_the_title( $post ),
						'item' => $permalink
					]
				]
			]
		]
	];

	echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
}
add_action( 'wp_head', 'tchp_output_article_schema', 10 );
