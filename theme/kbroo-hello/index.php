<?php
/**
 * The one main template. WordPress uses it for every page, and it hands off
 * to the "single" or "archive" location, so the builder can take over either.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_singular() ) {
	kbroo_hello_location( 'single', 'single' );
} elseif ( is_404() ) {
	kbroo_hello_location( 'single', '404' );
} else {
	kbroo_hello_location( 'archive', 'archive' );
}

get_footer();
