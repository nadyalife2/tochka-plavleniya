<?php
/**
 * Hello Elementor Child Theme functions and definitions.
 * Точка Плавления — Экспертный журнал и инженерный верстак
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * 1. Enqueue Styles & Scripts
 */
function hello_elementor_child_enqueue_scripts() {
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_directory_uri() . '/style.css',
		[ 'hello-elementor-theme-style' ],
		'1.2.0'
	);

	if ( is_single() ) {
		wp_enqueue_style(
			'tchp-article-pro',
			get_stylesheet_directory_uri() . '/assets/css/article-pro.css',
			[],
			'1.2.0'
		);
		wp_enqueue_script(
			'tchp-article-ux',
			get_stylesheet_directory_uri() . '/assets/js/article-ux.js',
			[],
			'1.2.0',
			true
		);
		wp_enqueue_script(
			'tchp-thermal-slider',
			get_stylesheet_directory_uri() . '/assets/js/thermal-slider.js',
			[],
			'1.2.0',
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );

/**
 * 2. ПОЛНОЕ ОТКЛЮЧЕНИЕ КОММЕНТАРИЕВ И СБОРА ПЕРСОНАЛЬНЫХ ДАННЫХ (152-ФЗ / Zero-PII)
 * Сайт не является оператором ПДн и не передает данные в РКН.
 */

// Закрываем комментарии и пингбэки на всех типах записей
add_filter( 'comments_open', '__return_false', 20, 2 );
add_filter( 'pings_open', '__return_false', 20, 2 );
add_filter( 'comments_array', '__return_empty_array', 10, 2 );

// Убираем поддержку комментариев из типов постов
function tchp_disable_comments_post_types_support() {
	$post_types = get_post_types();
	foreach ( $post_types as $post_type ) {
		if ( post_type_supports( $post_type, 'comments' ) ) {
			remove_post_type_support( $post_type, 'comments' );
			remove_post_type_support( $post_type, 'trackbacks' );
		}
	}
}
add_action( 'admin_init', 'tchp_disable_comments_post_types_support' );

// Убираем меню комментариев из панели админки
function tchp_disable_comments_admin_menu() {
	remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'tchp_disable_comments_admin_menu' );

// Убираем ссылки на комментарии из Admin Bar
function tchp_disable_comments_admin_bar() {
	if ( is_admin_bar_showing() ) {
		remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
	}
}
add_action( 'init', 'tchp_disable_comments_admin_bar' );

// Блокируем доступ к странице edit-comments.php
function tchp_disable_comments_admin_redirect() {
	global $pagenow;
	if ( 'edit-comments.php' === $pagenow ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'tchp_disable_comments_admin_redirect' );

// Скрываем перечисление пользователей через REST API (/wp-json/wp/v2/users) для гостей
add_filter( 'rest_endpoints', function( $endpoints ) {
	if ( ! is_user_logged_in() && isset( $endpoints['/wp/v2/users'] ) ) {
		unset( $endpoints['/wp/v2/users'] );
	}
	if ( ! is_user_logged_in() && isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
});

// Отключаем сбор IP-адресов
add_filter( 'pre_comment_user_ip', '__return_empty_string' );

/**
 * 3. РЕГИСТРАЦИЯ КАСТОМНЫХ МЕТА-ПОЛЕЙ ДЛЯ ИИ-АВТОМАТИЗАЦИИ (REST API / Gemini)
 */
function tchp_register_ai_post_meta() {
	$meta_fields = [
		'tchp_reading_time' => [
			'type'         => 'integer',
			'description'  => 'Время чтения статьи в минутах',
			'single'       => true,
			'show_in_rest' => true,
		],
		'tchp_difficulty' => [
			'type'         => 'string',
			'description'  => 'Уровень сложности (Junior / Middle / Senior Lab)',
			'single'       => true,
			'show_in_rest' => true,
		],
		'tchp_solder_alloy' => [
			'type'         => 'string',
			'description'  => 'Основной сплав припоя (ПОС-61, SAC305 и др.)',
			'single'       => true,
			'show_in_rest' => true,
		],
		'tchp_temp_range' => [
			'type'         => 'string',
			'description'  => 'Температурный диапазон пайки (°C)',
			'single'       => true,
			'show_in_rest' => true,
		],
		'tchp_tools_needed' => [
			'type'         => 'string',
			'description'  => 'Список инструментов и расходников (JSON/String)',
			'single'       => true,
			'show_in_rest' => true,
		],
		'tchp_faq_items' => [
			'type'         => 'string',
			'description'  => 'FAQ вопросы и ответы в формате JSON для Schema.org',
			'single'       => true,
			'show_in_rest' => true,
		],
	];

	foreach ( $meta_fields as $meta_key => $args ) {
		register_post_meta( 'post', $meta_key, $args );
	}
}
add_action( 'init', 'tchp_register_ai_post_meta' );

/**
 * 4. ВЫВОД GEO & SCHEMA.ORG (TechArticle + FAQPage) В WP_HEAD
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

	$graph = [
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
	];

	// Добавляем FAQ Schema при наличии данных
	$faq_json = get_post_meta( $post->ID, 'tchp_faq_items', true );
	if ( ! empty( $faq_json ) ) {
		$faq_data = json_decode( $faq_json, true );
		if ( is_array( $faq_data ) && ! empty( $faq_data ) ) {
			$faq_entities = [];
			foreach ( $faq_data as $item ) {
				if ( ! empty( $item['q'] ) && ! empty( $item['a'] ) ) {
					$faq_entities[] = [
						'@type' => 'Question',
						'name' => $item['q'],
						'acceptedAnswer' => [
							'@type' => 'Answer',
							'text' => $item['a']
						]
					];
				}
			}
			if ( ! empty( $faq_entities ) ) {
				$graph[] = [
					'@type' => 'FAQPage',
					'mainEntity' => $faq_entities
				];
			}
		}
	}

	$schema = [
		'@context' => 'https://schema.org',
		'@graph' => $graph
	];

	echo "\n<script type=\"application/ld+json\">\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
}
add_action( 'wp_head', 'tchp_output_article_schema', 10 );

/**
 * 5. ВЫВОД КУКИ-БАННЕРА В WP_FOOTER
 */
function tchp_render_cookie_consent_banner() {
	?>
	<div id="cookie-consent-banner" style="display:none; position:fixed; bottom:16px; right:16px; max-width:420px; z-index:9999; background:var(--color-paper, #faf8f5); border:2px solid var(--color-paper-border-dark, #d3cdc2); border-radius:12px; padding:16px; box-shadow:0 10px 25px rgba(0,0,0,0.15); font-family:'Space Grotesk', sans-serif; color:var(--color-ink, #141414);">
		<div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
			<div style="font-weight:700; font-size:13px; text-transform:uppercase; font-family:monospace;">🍪 Файлы Cookie и аналитика</div>
			<button id="cookie-close-btn" style="background:none; border:none; cursor:pointer; font-size:14px; color:#888;">✕</button>
		</div>
		<p style="font-size:12.5px; line-height:1.45; margin:0 0 12px 0; color:var(--color-ink-muted, #5c5850);">
			Мы используем технические cookies для сохранения темы и анонимной аналитики Яндекса. Персональные данные <strong>не собираются</strong> (152-ФЗ).
		</p>
		<div style="display:flex; align-items:center; justify-content:space-between;">
			<a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>" style="font-size:11.5px; font-family:monospace; color:var(--color-ink-muted, #5c5850); text-decoration:underline;">Политика 152-ФЗ →</a>
			<button id="cookie-accept-btn" style="padding:6px 14px; background:var(--color-ink, #141414); color:#ffffff; border:none; border-radius:6px; font-size:12px; font-family:monospace; cursor:pointer; font-weight:600;">Понятно</button>
		</div>
	</div>
	<script>
		(function() {
			try {
				var consent = localStorage.getItem('tp_cookie_consent');
				var banner = document.getElementById('cookie-consent-banner');
				if (!consent && banner) {
					banner.style.display = 'block';
				}
				function accept() {
					localStorage.setItem('tp_cookie_consent', 'accepted');
					if (banner) banner.style.display = 'none';
				}
				var btn = document.getElementById('cookie-accept-btn');
				var close = document.getElementById('cookie-close-btn');
				if (btn) btn.onclick = accept;
				if (close) close.onclick = accept;
			} catch(e) {}
		})();
	</script>
	<?php
}
add_action( 'wp_footer', 'tchp_render_cookie_consent_banner', 100 );
