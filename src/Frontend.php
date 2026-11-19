<?php
/**
 * Front-end rendering of builder pages.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Admin_Bar;

defined( 'ABSPATH' ) || exit;

final class Frontend {

	/** @var Renderer */
	private $renderer;

	public function __construct( Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	public function hooks(): void {
		// Late priority: the builder output replaces the stored content after
		// core's formatting filters (wpautop, do_blocks, ...) have run, so they
		// cannot mangle the layout markup.
		add_filter( 'the_content', array( $this, 'filter_content' ), 999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_link' ), 80 );
	}

	/**
	 * @param string $content Post content.
	 */
	public function filter_content( $content ) {
		$post = get_post();
		if ( ! $post || ! Document::supports( $post ) || ! Document::is_built_with_kbroo( $post ) ) {
			return $content;
		}
		if ( post_password_required( $post ) ) {
			return $content;
		}
		// Excerpts are built from the_content; keep them as plain stored content.
		if ( doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}

		wp_enqueue_style( 'kbroo-frontend' );
		return $this->renderer->render( Document::get_layout( $post ), $post->ID );
	}

	/**
	 * Register the stylesheet everywhere, and enqueue it in the head when the
	 * main post is a builder page. filter_content() also enqueues it, which
	 * covers builder content shown elsewhere (it then prints in the footer).
	 */
	public function enqueue(): void {
		wp_register_style( 'kbroo-frontend', KBROO_STUDIO_URL . 'assets/css/frontend.css', array(), KBROO_STUDIO_VERSION );
		if ( is_singular() && Document::is_built_with_kbroo( get_queried_object() ) ) {
			wp_enqueue_style( 'kbroo-frontend' );
		}
	}

	/**
	 * "Edit with Kbroo Studio" in the admin bar on the front end.
	 *
	 * @param WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar_link( $bar ): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post || ! Document::supports( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'kbroo-edit',
				'title' => __( 'Edit with Kbroo Studio', 'kbroo-studio' ),
				'href'  => Document::editor_url( $post ),
			)
		);
	}
}
