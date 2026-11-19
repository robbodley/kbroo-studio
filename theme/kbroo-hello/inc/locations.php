<?php
/**
 * Theme locations: the spots the Kbroo Studio plugin can fill with its own
 * templates (header, footer, single pages and posts, archives).
 *
 * How the plugin takes over a location:
 *
 *     add_filter( 'kbroo_theme_do_location', function ( $done, $location ) {
 *         if ( 'header' === $location && my_header_template_exists() ) {
 *             echo render_my_header();
 *             return true; // The theme now skips its own header.
 *         }
 *         return $done;
 *     }, 10, 2 );
 *
 * The plugin can check for this theme (or any theme that supports the same
 * contract) with current_theme_supports( 'kbroo-theme-locations' ).
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

/**
 * The location names. "single" covers pages, posts and the 404 page;
 * "archive" covers the blog, category, tag, date, author and search pages.
 *
 * @return string[]
 */
function kbroo_hello_locations() {
	return (array) apply_filters( 'kbroo_hello_locations', array( 'header', 'footer', 'single', 'archive' ) );
}

/**
 * Gives the builder the chance to print a location. Returns true when
 * something else printed it, so the theme should skip its default markup.
 *
 * @param string $location One of kbroo_hello_locations().
 * @return bool
 */
function kbroo_hello_do_location( $location ) {
	/**
	 * Fires before a theme location prints.
	 *
	 * @param string $location Location name.
	 */
	do_action( 'kbroo_hello_before_location', $location );

	/**
	 * Lets a builder print its own template for a location.
	 *
	 * @param bool   $done     Whether the location was already printed.
	 * @param string $location Location name.
	 */
	$done = (bool) apply_filters( 'kbroo_theme_do_location', false, $location );

	/**
	 * Fires after a location was printed by a builder.
	 *
	 * @param string $location Location name.
	 */
	if ( $done ) {
		do_action( 'kbroo_hello_location_done', $location );
	}

	return $done;
}

/**
 * Prints a location, falling back to the theme's own template part.
 *
 * @param string $location One of kbroo_hello_locations().
 * @param string $part     Template part to use when nothing else prints it.
 */
function kbroo_hello_location( $location, $part ) {
	if ( kbroo_hello_do_location( $location ) ) {
		return;
	}

	if ( 'header' === $location && ! get_theme_mod( 'kbroo_hello_show_header', true ) ) {
		return;
	}
	if ( 'footer' === $location && ! get_theme_mod( 'kbroo_hello_show_footer', true ) ) {
		return;
	}

	get_template_part( 'template-parts/' . $part );
}
