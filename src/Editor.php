<?php
/**
 * The full-screen editor and the "Edit with Kbroo Studio" launchers.
 *
 * The editor lives at wp-admin/post.php?post=<id>&action=kbroo_edit, the same
 * pattern core uses for its own post actions.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Editor {

	const ACTION = 'kbroo_edit';

	/** @var Registry */
	private $widgets;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	public function hooks(): void {
		add_action( 'post_action_' . self::ACTION, array( $this, 'render_editor' ) );

		// Launchers.
		add_action( 'edit_form_after_title', array( $this, 'classic_editor_button' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'block_editor_launcher' ) );
		add_filter( 'page_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_filter( 'display_post_states', array( $this, 'post_state' ), 10, 2 );
	}

	/**
	 * Output the full-screen editor and stop. Runs inside wp-admin/post.php,
	 * which has already checked the post exists and that the user is logged in.
	 *
	 * @param int $post_id Post ID.
	 */
	public function render_editor( $post_id ): void {
		$post = get_post( (int) $post_id );

		if ( ! $post || ! Document::supports( $post ) ) {
			wp_die( esc_html__( 'This post cannot be edited with Kbroo Studio.', 'kbroo-studio' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this item.', 'kbroo-studio' ), 403 );
		}

		$this->enqueue_editor_assets( $post );

		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );

		require KBROO_STUDIO_DIR . 'templates/editor.php';
		exit;
	}

	private function enqueue_editor_assets( WP_Post $post ): void {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_style( 'kbroo-frontend', KBROO_STUDIO_URL . 'assets/css/frontend.css', array(), KBROO_STUDIO_VERSION );
		wp_enqueue_style( 'kbroo-editor', KBROO_STUDIO_URL . 'assets/css/editor.css', array( 'dashicons', 'kbroo-frontend' ), KBROO_STUDIO_VERSION );

		wp_enqueue_script( 'kbroo-widgets', KBROO_STUDIO_URL . 'assets/js/widgets.js', array(), KBROO_STUDIO_VERSION, true );
		wp_enqueue_script( 'kbroo-css', KBROO_STUDIO_URL . 'assets/js/css.js', array(), KBROO_STUDIO_VERSION, true );
		wp_enqueue_script( 'kbroo-editor', KBROO_STUDIO_URL . 'assets/js/editor.js', array( 'kbroo-widgets', 'kbroo-css' ), KBROO_STUDIO_VERSION, true );

		$type = get_post_type_object( $post->post_type );

		wp_add_inline_script(
			'kbroo-widgets',
			'window.kbrooConfig = ' . wp_json_encode( $this->editor_config( $post, $type ) ) . ';',
			'before'
		);
	}

	private function editor_config( WP_Post $post, $type ): array {
		return array(
			'postId'         => $post->ID,
			'title'          => get_the_title( $post ),
			'status'         => $post->post_status,
			'postTypeLabel'  => $type ? $type->labels->singular_name : '',
			'canPublish'     => $type ? current_user_can( $type->cap->publish_posts ) : false,
			'permalink'      => get_permalink( $post ),
			'previewUrl'     => get_preview_post_link( $post ),
			'wpEditUrl'      => get_edit_post_link( $post, 'raw' ),
			'restUrl'        => esc_url_raw( rest_url( Rest::NAMESPACE . '/documents/' . $post->ID ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'layout'         => Document::get_layout( $post ),
			'widgets'        => $this->widgets->to_editor_config(),
			'sectionControls' => Controls::for_editor( Layout::section_controls() ),
			'containerControls' => Controls::for_editor( Layout::container_controls() ),
			'columnControls' => Controls::for_editor( Layout::column_controls() ),
			'maxColumns'     => Layout::MAX_COLUMNS,
			'breakpoints'    => Css::BREAKPOINTS,
			'globals'        => Globals::get(),
			'globalsUrl'     => esc_url_raw( rest_url( Rest::NAMESPACE . '/globals' ) ),
			'canEditGlobals' => current_user_can( Globals::CAPABILITY ),
			'globalTypographyControls' => Controls::for_editor( Globals::typography_controls() ),
			'systemGlobalIds' => Globals::SYSTEM_IDS,
			'fonts'          => Fonts::all(),
			'widgetPreviewUrl' => esc_url_raw( rest_url( Rest::NAMESPACE . '/documents/' . $post->ID . '/widget-preview' ) ),
			'canUnfilteredHtml' => current_user_can( 'unfiltered_html' ),
			'revisionsUrl'   => esc_url_raw( rest_url( Rest::NAMESPACE . '/documents/' . $post->ID . '/revisions' ) ),
		);
	}

	/**
	 * Button under the title in the classic editor.
	 *
	 * @param WP_Post $post Post being edited.
	 */
	public function classic_editor_button( $post ): void {
		if ( ! Document::supports( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		printf(
			'<p class="kbroo-launch"><a class="button button-primary button-hero" href="%s">%s</a></p>',
			esc_url( Document::editor_url( $post ) ),
			esc_html__( 'Edit with Kbroo Studio', 'kbroo-studio' )
		);
	}

	/**
	 * Button in the block editor's document sidebar.
	 */
	public function block_editor_launcher(): void {
		$post = get_post();
		if ( ! $post || ! Document::supports( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_enqueue_script(
			'kbroo-launcher',
			KBROO_STUDIO_URL . 'assets/js/launcher.js',
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-editor', 'wp-edit-post' ),
			KBROO_STUDIO_VERSION,
			true
		);
		wp_localize_script(
			'kbroo-launcher',
			'kbrooLauncher',
			array(
				'editorUrl' => Document::editor_url( $post ),
				'isBuilder' => Document::is_built_with_kbroo( $post ),
				'label'     => __( 'Edit with Kbroo Studio', 'kbroo-studio' ),
				'notice'    => __( 'This page is built with Kbroo Studio. Changes made here are replaced the next time it is saved in Kbroo Studio.', 'kbroo-studio' ),
			)
		);
	}

	/**
	 * "Edit with Kbroo Studio" link in the Pages and Posts lists.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 */
	public function row_action( $actions, $post ): array {
		if ( Document::supports( $post ) && 'trash' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) ) {
			$actions['kbroo_edit'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( Document::editor_url( $post ) ),
				esc_html__( 'Edit with Kbroo Studio', 'kbroo-studio' )
			);
		}
		return $actions;
	}

	/**
	 * Mark builder pages in the Pages and Posts lists.
	 *
	 * @param array   $states Post states.
	 * @param WP_Post $post   Post.
	 */
	public function post_state( $states, $post ): array {
		if ( Document::is_built_with_kbroo( $post ) ) {
			$states['kbroo'] = __( 'Kbroo Studio', 'kbroo-studio' );
		}
		return $states;
	}
}
