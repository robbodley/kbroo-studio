<?php
/**
 * REST API: save a post's layout.
 *
 *     POST /wp-json/kbroo/v1/documents/<id>
 *     { "layout": {...}, "status": "publish" }   // status is optional
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Rest {

	const NAMESPACE = 'kbroo/v1';

	/** @var Registry */
	private $widgets;

	/** @var Renderer */
	private $renderer;

	public function __construct( Registry $widgets, Renderer $renderer ) {
		$this->widgets  = $widgets;
		$this->renderer = $renderer;
	}

	public function hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/documents/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_document' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'save_document' ),
					'permission_callback' => array( $this, 'can_edit' ),
					'args'                => array(
						'layout' => array(
							'required' => true,
							'type'     => 'object',
						),
						'status' => array(
							'required' => false,
							'type'     => 'string',
							'enum'     => array( 'draft', 'publish' ),
						),
					),
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function can_edit( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! $post || ! Document::supports( $post ) ) {
			return new WP_Error( 'kbroo_not_found', __( 'This post cannot be edited with Kbroo Studio.', 'kbroo-studio' ), array( 'status' => 404 ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'kbroo_forbidden', __( 'You are not allowed to edit this post.', 'kbroo-studio' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	public function get_document( WP_REST_Request $request ): WP_REST_Response {
		return rest_ensure_response( $this->document_response( (int) $request['id'] ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_document( WP_REST_Request $request ) {
		$post   = get_post( (int) $request['id'] );
		$layout = Layout::sanitize( $request->get_param( 'layout' ), $this->widgets );
		$status = $request->get_param( 'status' );

		Document::save_layout( $post->ID, $layout );

		// Keep a plain HTML copy in post_content, so the content still shows
		// if the plugin is deactivated and stays visible to search and feeds.
		$update = array(
			'ID'           => $post->ID,
			'post_content' => $this->renderer->render( $layout, $post->ID ),
		);

		if ( 'publish' === $status && 'publish' !== $post->post_status ) {
			$type = get_post_type_object( $post->post_type );
			if ( ! $type || ! current_user_can( $type->cap->publish_posts ) ) {
				return new WP_Error( 'kbroo_cannot_publish', __( 'You are not allowed to publish this post.', 'kbroo-studio' ), array( 'status' => rest_authorization_required_code() ) );
			}
			$update['post_status'] = 'publish';
		} elseif ( 'auto-draft' === $post->post_status ) {
			// A brand new post opened straight in the builder.
			$update['post_status'] = 'draft';
		}

		if ( 'auto-draft' === $post->post_status && '' === $post->post_title ) {
			/* translators: %d: post ID. */
			$update['post_title'] = sprintf( __( 'Kbroo Studio #%d', 'kbroo-studio' ), $post->ID );
		}

		$result = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $this->document_response( $post->ID ) );
	}

	private function document_response( int $post_id ): array {
		$post = get_post( $post_id );
		return array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'status'    => $post->post_status,
			'permalink' => get_permalink( $post ),
			'layout'    => Document::get_layout( $post ),
		);
	}
}
