<?php
/**
 * Reads and writes a post's builder layout.
 *
 * The layout is stored as JSON in the `_kbroo_data` post meta. A post is
 * "built with Kbroo" when `_kbroo_edit_mode` is `builder`.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Document {

	const META_DATA = '_kbroo_data';
	const META_MODE = '_kbroo_edit_mode';

	/**
	 * Post types the builder can edit.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		/**
		 * Filters the post types that can be edited with Kbroo Studio.
		 *
		 * @param string[] $post_types Default: page and post.
		 */
		return (array) apply_filters( 'kbroo_studio_post_types', array( 'page', 'post' ) );
	}

	/**
	 * Post types the editor opens: the above and saved templates.
	 *
	 * @return string[]
	 */
	public static function editable_types(): array {
		return array_merge( self::post_types(), array( Templates::POST_TYPE ) );
	}

	public static function supports( $post ): bool {
		$post = get_post( $post );
		return $post && in_array( $post->post_type, self::editable_types(), true );
	}

	public static function is_built_with_kbroo( $post ): bool {
		$post = get_post( $post );
		return $post && 'builder' === get_post_meta( $post->ID, self::META_MODE, true );
	}

	/**
	 * The stored layout, or an empty layout.
	 */
	public static function get_layout( $post ): array {
		$post = get_post( $post );
		if ( ! $post ) {
			return Layout::empty_layout();
		}
		$raw     = get_post_meta( $post->ID, self::META_DATA, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $decoded ) ? $decoded : Layout::empty_layout();
	}

	/**
	 * Store an already-sanitized layout.
	 */
	public static function save_layout( int $post_id, array $layout ): void {
		// update_post_meta() unslashes its value, so slash the JSON first or
		// escaped quotes inside it would be lost.
		update_post_meta( $post_id, self::META_DATA, wp_slash( wp_json_encode( $layout ) ) );
		update_post_meta( $post_id, self::META_MODE, 'builder' );
	}

	public static function editor_url( $post ): string {
		$post = get_post( $post );
		return $post ? admin_url( 'post.php?post=' . $post->ID . '&action=' . Editor::ACTION ) : '';
	}
}
