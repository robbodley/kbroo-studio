<?php
/**
 * Front-end stylesheets for server-rendered widget previews.
 *
 * The editor shows shortcodes (and the other widgets only WordPress can
 * render) with HTML from the server. Plugins usually style that output with
 * stylesheets they enqueue on the front end only: on `wp_enqueue_scripts`,
 * or from inside the shortcode itself. Neither happens on the editor screen,
 * so the preview showed unstyled output (full-size images and so on) while
 * the live page looked right.
 *
 * While a preview renders, this runs the plugins' `wp_enqueue_scripts`
 * callbacks inside the post's own query, renders the widget, and returns the
 * <link> and <style> tags for every stylesheet that was enqueued, so the
 * editor can add them to its page. The theme's and WordPress's own front-end
 * styles are left out: they would restyle the editor itself.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class PreviewStyles {

	/**
	 * Render a preview as the front end would for $post.
	 *
	 * @param callable $render Returns the preview HTML.
	 * @param WP_Post  $post   Post the preview belongs to.
	 * @return array{html: string, styles: string}
	 */
	public static function render( callable $render, WP_Post $post ): array {
		global $wp_query, $wp_the_query;

		$previous = array(
			'wp_query'     => $wp_query,
			'wp_the_query' => $wp_the_query,
			'post'         => $GLOBALS['post'] ?? null,
		);

		// Conditional tags (is_singular(), get_queried_object()...) answer as
		// they would on the post's own page, as plugins often check them
		// before enqueuing.
		$args = array(
			'post_type'           => $post->post_type,
			'post_status'         => 'any',
			'posts_per_page'      => 1,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		$args[ 'page' === $post->post_type ? 'page_id' : 'p' ] = $post->ID;
		$query = new WP_Query( $args );

		$wp_query        = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_the_query    = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['post'] = $query->post ?: $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $GLOBALS['post'] );

		$styles = wp_styles();
		$before = $styles->queue;

		self::run_plugin_enqueues();
		$html = (string) call_user_func( $render );

		$handles = array_values( array_diff( $styles->queue, $before ) );
		$handles = array_values(
			array_filter(
				$handles,
				function ( $handle ) {
					return 0 !== strpos( $handle, 'kbroo-' );
				}
			)
		);

		/**
		 * Stylesheet handles added to the editor for a server-rendered widget
		 * preview. Their dependencies are added too.
		 *
		 * @param string[] $handles Enqueued style handles.
		 * @param WP_Post  $post    Post being edited.
		 */
		$handles = (array) apply_filters( 'kbroo_studio_preview_style_handles', $handles, $post );

		$markup = '';
		if ( $handles ) {
			ob_start();
			$styles->do_items( $handles );
			$markup = (string) ob_get_clean();
		}

		$wp_query        = $previous['wp_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_the_query    = $previous['wp_the_query']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['post'] = $previous['post']; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $previous['post'] ) {
			setup_postdata( $previous['post'] );
		}

		return array(
			'html'   => $html,
			'styles' => $markup,
		);
	}

	/**
	 * Run the `wp_enqueue_scripts` callbacks that plugins added, in priority
	 * order. Core's and the theme's are skipped, and so are Kbroo Studio's,
	 * whose styles the editor already has.
	 */
	private static function run_plugin_enqueues(): void {
		global $wp_filter;
		if ( empty( $wp_filter['wp_enqueue_scripts'] ) ) {
			return;
		}
		foreach ( $wp_filter['wp_enqueue_scripts']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( self::is_plugin_callback( $callback['function'] ) ) {
					call_user_func( $callback['function'] );
				}
			}
		}
	}

	/**
	 * Whether a hooked callback is defined in a plugin (other than this one).
	 *
	 * @param callable $callback Hooked callback.
	 */
	private static function is_plugin_callback( $callback ): bool {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}
			if ( is_array( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( is_object( $callback ) && ! $callback instanceof \Closure ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				$reflection = new \ReflectionFunction( $callback );
			}
		} catch ( \ReflectionException $e ) {
			return false;
		}

		$file = $reflection->getFileName();
		if ( ! $file ) {
			return false; // Built into PHP.
		}
		$file = wp_normalize_path( (string) realpath( $file ) );

		if ( 0 === strpos( $file, self::dir( KBROO_STUDIO_DIR ) ) ) {
			return false;
		}
		foreach ( array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ) as $dir ) {
			if ( 0 === strpos( $file, self::dir( $dir ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** A directory's real path, normalised, with a trailing slash. */
	private static function dir( string $dir ): string {
		$real = realpath( $dir );
		return trailingslashit( wp_normalize_path( $real ? $real : $dir ) );
	}
}
