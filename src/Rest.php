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
						'title'  => array(
							'required' => false,
							'type'     => 'string',
						),
						'display' => array(
							'required' => false,
							'type'     => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/documents/(?P<id>\d+)/revisions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					return rest_ensure_response( Revisions::list_for( (int) $request['id'] ) );
				},
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/documents/(?P<id>\d+)/revisions/(?P<revision>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function ( WP_REST_Request $request ) {
					$layout = Revisions::layout( (int) $request['id'], (int) $request['revision'] );
					if ( null === $layout ) {
						return new WP_Error( 'kbroo_not_found', __( 'Revision not found.', 'kbroo-studio' ), array( 'status' => 404 ) );
					}
					return rest_ensure_response(
						array(
							'id'     => (int) $request['revision'],
							'layout' => Layout::sanitize( $layout, $this->widgets ),
						)
					);
				},
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/icons',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					$response = rest_ensure_response( Icons::for_editor() );
					// The icons only change with the plugin version (?v=).
					$response->header( 'Cache-Control', 'private, max-age=31536000' );
					return $response;
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/documents/(?P<id>\d+)/widget-preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'widget_preview' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'widgetType' => array(
						'required' => true,
						'type'     => 'string',
					),
					'settings'   => array(
						'required' => false,
						'type'     => 'object',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/globals',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () {
						return rest_ensure_response( Globals::get() );
					},
					'permission_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => function ( WP_REST_Request $request ) {
						return rest_ensure_response( Globals::save( $request->get_param( 'globals' ) ) );
					},
					'permission_callback' => array( $this, 'can_edit_globals' ),
					'args'                => array(
						'globals' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);
	}

	/**
	 * @return true|WP_Error
	 */
	public function can_edit_globals() {
		if ( ! current_user_can( Globals::CAPABILITY ) ) {
			return new WP_Error( 'kbroo_forbidden', __( 'You are not allowed to change site-wide styles.', 'kbroo-studio' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
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

	/**
	 * HTML for a widget the editor cannot render itself (see
	 * Widget::server_preview()), rendered as it would be on the page.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function widget_preview( WP_REST_Request $request ) {
		$widget = $this->widgets->get( (string) $request->get_param( 'widgetType' ) );
		if ( ! $widget || ! $widget->server_preview() ) {
			return new WP_Error( 'kbroo_bad_widget', __( 'This widget has no server preview.', 'kbroo-studio' ), array( 'status' => 400 ) );
		}
		$controls = $widget->get_controls();
		$settings = Controls::with_defaults( $controls, Controls::sanitize( $controls, $request->get_param( 'settings' ) ) );

		// Shortcodes often read the current post, so render inside its loop.
		global $post;
		$previous = $post;
		$post     = get_post( (int) $request['id'] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		$html = $widget->render( $settings );
		$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $previous ) {
			setup_postdata( $previous );
		}

		return rest_ensure_response( array( 'html' => $html ) );
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

		Elementor\Migration::backup_content( $post );
		Document::save_layout( $post->ID, $layout );
		if ( Templates::is_template( $post ) && in_array( Templates::type_of( $post ), ThemeBuilder::AREAS, true ) && is_array( $request->get_param( 'display' ) ) ) {
			ThemeBuilder::save_display( $post->ID, $request->get_param( 'display' ) );
		}

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

		$title = $request->get_param( 'title' );
		if ( is_string( $title ) && '' !== trim( $title ) && $title !== $post->post_title ) {
			$update['post_title'] = sanitize_text_field( $title );
		} elseif ( 'auto-draft' === $post->post_status && '' === $post->post_title ) {
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
