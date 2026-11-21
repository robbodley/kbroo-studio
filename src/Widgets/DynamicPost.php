<?php
/**
 * The post that dynamic widgets (Post title, Post content, Featured image,
 * Post info...) show: the post being viewed, or the one a posts grid is
 * looping over. While a single template is edited, a recent post stands in
 * for it so the widgets show something real.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\Templates;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class DynamicPost {

	/**
	 * The post to show, or null when there is none.
	 */
	public static function get(): ?WP_Post {
		$post = get_post();
		if ( $post && Templates::POST_TYPE !== $post->post_type ) {
			return $post;
		}
		return self::sample( $post );
	}

	/**
	 * A post to preview a template with: the newest page for a single page
	 * template, otherwise the newest post.
	 *
	 * @param WP_Post|int|null $template Template being edited.
	 */
	public static function sample( $template = null ): ?WP_Post {
		$template = $template ? get_post( $template ) : null;
		$type     = $template && Templates::is_template( $template ) && 'single-page' === Templates::type_of( $template ) ? 'page' : 'post';
		$found    = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * Run a render with the post to show as the global post (template tags
	 * such as get_the_excerpt() and get_previous_post() read it).
	 *
	 * @param callable $render Called with the post; returns HTML.
	 */
	public static function with( callable $render ): string {
		$post = self::get();
		if ( ! $post ) {
			return '';
		}
		$previous = $GLOBALS['post'] ?? null;
		if ( $previous !== $post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			setup_postdata( $post );
		}
		$html = (string) $render( $post );
		if ( $previous !== $post ) {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			if ( $previous instanceof WP_Post ) {
				setup_postdata( $previous );
			}
		}
		return $html;
	}
}
