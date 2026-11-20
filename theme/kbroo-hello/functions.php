<?php
/**
 * Kbroo Hello: a near-blank starter theme for the Kbroo Studio page builder.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

define( 'KBROO_HELLO_VERSION', '0.2.0' );

/*
 * Oldest Kbroo Studio plugin this theme works with. Raise it when the theme
 * starts relying on something newer in the plugin; admins then see a
 * notice asking them to update the plugin.
 */
define( 'KBROO_HELLO_MIN_STUDIO', '0.1.0' );
define( 'KBROO_HELLO_DIR', trailingslashit( get_template_directory() ) );
define( 'KBROO_HELLO_URL', trailingslashit( get_template_directory_uri() ) );

require KBROO_HELLO_DIR . 'inc/locations.php';
require KBROO_HELLO_DIR . 'inc/customizer.php';
require KBROO_HELLO_DIR . 'inc/admin-notice.php';
require KBROO_HELLO_DIR . 'inc/updater.php';

if ( ! isset( $content_width ) ) {
	$content_width = 1140; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Core expects this global.
}

/**
 * Theme supports and menus.
 */
function kbroo_hello_setup() {
	load_theme_textdomain( 'kbroo-hello', KBROO_HELLO_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'script', 'style', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 100,
			'width'       => 350,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Tells the Kbroo Studio plugin which spots it can fill with its own templates.
	add_theme_support( 'kbroo-theme-locations', kbroo_hello_locations() );

	register_nav_menus(
		array(
			'primary' => __( 'Header menu', 'kbroo-hello' ),
			'footer'  => __( 'Footer menu', 'kbroo-hello' ),
		)
	);
}
add_action( 'after_setup_theme', 'kbroo_hello_setup' );

/**
 * The theme's small stylesheet. Switch it off in Appearance > Customize >
 * Kbroo Hello, or with the kbroo_hello_load_theme_css filter.
 */
function kbroo_hello_enqueue() {
	$load = (bool) apply_filters( 'kbroo_hello_load_theme_css', (bool) get_theme_mod( 'kbroo_hello_theme_css', true ) );
	if ( $load ) {
		wp_enqueue_style( 'kbroo-hello', KBROO_HELLO_URL . 'assets/css/theme.css', array(), KBROO_HELLO_VERSION );
	}

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'kbroo_hello_enqueue' );

/**
 * Whether the current page was built with Kbroo Studio. Reads the plugin's
 * post meta directly so the theme works with or without the plugin loaded.
 *
 * @param int|WP_Post|null $post Post to check. Defaults to the queried page.
 */
function kbroo_hello_is_builder_page( $post = null ) {
	if ( null === $post ) {
		$post = is_singular() ? get_queried_object() : null;
	}
	$post   = $post ? get_post( $post ) : null;
	$is_kbr = $post && 'builder' === get_post_meta( $post->ID, '_kbroo_edit_mode', true );

	/**
	 * Filters whether a page is treated as a builder page (full width, no
	 * theme title).
	 *
	 * @param bool         $is_kbr Whether the page was built with Kbroo Studio.
	 * @param WP_Post|null $post   The post.
	 */
	return (bool) apply_filters( 'kbroo_hello_is_builder_page', $is_kbr, $post );
}

/**
 * Body classes the stylesheet and the builder can key off.
 *
 * @param string[] $classes Body classes.
 */
function kbroo_hello_body_class( $classes ) {
	$classes[] = 'kbroo-hello';
	if ( kbroo_hello_is_builder_page() ) {
		$classes[] = 'kbroo-hello--builder';
	}
	if ( is_page_template( 'templates/canvas.php' ) ) {
		$classes[] = 'kbroo-hello--canvas';
	}
	if ( is_page_template( 'templates/full-width.php' ) ) {
		$classes[] = 'kbroo-hello--full-width';
	}
	return $classes;
}
add_filter( 'body_class', 'kbroo_hello_body_class' );
