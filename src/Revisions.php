<?php
/**
 * Keeps the builder layout with WordPress revisions.
 *
 * WordPress revisions store the post's content but not its meta. Each time a
 * revision is made, the layout is copied onto it; restoring a revision from
 * the core Revisions screen copies it back. The editor lists and previews
 * these revisions through the REST routes in Rest.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Revisions {

	const LIMIT = 50;

	public function hooks(): void {
		add_action( '_wp_put_post_revision', array( $this, 'copy_to_revision' ) );
		add_action( 'wp_restore_post_revision', array( $this, 'restore_from_revision' ), 10, 2 );
	}

	/**
	 * @param int $revision_id New revision.
	 */
	public function copy_to_revision( $revision_id ): void {
		$revision = get_post( $revision_id );
		if ( ! $revision || ! $revision->post_parent || ! Document::supports( $revision->post_parent ) ) {
			return;
		}
		$parent = (int) $revision->post_parent;
		foreach ( array( Document::META_DATA, Document::META_MODE ) as $key ) {
			$value = get_post_meta( $parent, $key, true );
			if ( '' !== $value ) {
				// update_metadata(), not update_post_meta(), which would write to the parent.
				update_metadata( 'post', $revision_id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * @param int $post_id     Post being restored.
	 * @param int $revision_id Revision restored from.
	 */
	public function restore_from_revision( $post_id, $revision_id ): void {
		$data = get_metadata( 'post', $revision_id, Document::META_DATA, true );
		if ( '' === $data ) {
			return;
		}
		update_post_meta( $post_id, Document::META_DATA, wp_slash( $data ) );
		update_post_meta( $post_id, Document::META_MODE, 'builder' );
	}

	/**
	 * Revisions of a post that hold a layout, newest first.
	 */
	public static function list_for( int $post_id ): array {
		$items = array();
		foreach ( wp_get_post_revisions( $post_id, array( 'posts_per_page' => self::LIMIT ) ) as $revision ) {
			if ( wp_is_post_autosave( $revision ) || '' === get_metadata( 'post', $revision->ID, Document::META_DATA, true ) ) {
				continue;
			}
			$author  = get_userdata( (int) $revision->post_author );
			$items[] = array(
				'id'     => $revision->ID,
				'date'   => mysql_to_rfc3339( $revision->post_date_gmt ),
				/* translators: %s: time difference, e.g. "5 mins". */
				'ago'    => sprintf( __( '%s ago', 'kbroo-studio' ), human_time_diff( strtotime( $revision->post_date_gmt . ' UTC' ) ) ),
				'author' => $author ? $author->display_name : '',
			);
		}
		return $items;
	}

	/**
	 * A revision's layout, or null if it is not a revision of $post_id.
	 */
	public static function layout( int $post_id, int $revision_id ): ?array {
		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision || (int) $revision->post_parent !== $post_id ) {
			return null;
		}
		$raw     = get_metadata( 'post', $revision->ID, Document::META_DATA, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
		return is_array( $decoded ) ? $decoded : null;
	}
}
