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

		$layout = Document::get_layout( $post );
		$this->enqueue_for( $layout );
		return $this->renderer->render( $layout, $post->ID );
	}

	/**
	 * Enqueue the styles in the head when the main post is a builder page.
	 * filter_content() also enqueues them, which covers builder content shown
	 * elsewhere (it then prints in the footer).
	 */
	public function enqueue(): void {
		if ( is_singular() && Document::is_built_with_kbroo( get_queried_object() ) ) {
			$this->enqueue_for( Document::get_layout( get_queried_object() ) );
		}
	}

	/**
	 * The script that plays entrance animations, in the footer.
	 */
	public static function enqueue_script(): void {
		if ( ! wp_script_is( 'kbroo-frontend', 'registered' ) ) {
			wp_register_script( 'kbroo-frontend', KBROO_STUDIO_URL . 'assets/js/frontend.js', array(), KBROO_STUDIO_VERSION, true );
		}
		wp_enqueue_script( 'kbroo-frontend' );
	}

	/** @var bool Whether the global styles were added to the page. */
	private $globals_added = false;

	/**
	 * A builder post's layout as HTML, with its styles enqueued. Used for
	 * site headers and footers.
	 */
	public function render_post( \WP_Post $post ): string {
		$layout = Document::get_layout( $post );
		$this->enqueue_for( $layout );
		return $this->renderer->render( $layout, $post->ID );
	}

	public function enqueue_layout( array $layout ): void {
		$this->enqueue_for( $layout );
	}

	/**
	 * Stylesheet, global colors and fonts, and the Google Fonts a layout uses.
	 */
	private function enqueue_for( array $layout ): void {
		// Block themes render content before wp_enqueue_scripts runs, so this
		// can be the first call: register here rather than in enqueue().
		if ( ! wp_style_is( 'kbroo-frontend', 'registered' ) ) {
			wp_register_style( 'kbroo-frontend', KBROO_STUDIO_URL . 'assets/css/frontend.css', array(), KBROO_STUDIO_VERSION );
		}
		wp_enqueue_style( 'kbroo-frontend' );

		$globals = Globals::get();
		if ( ! $this->globals_added ) {
			$this->globals_added = true;
			wp_add_inline_style( 'kbroo-frontend', Globals::css( $globals ) );
		}

		$url = Fonts::google_url( Globals::fonts_in_layout( $layout, $globals ) );
		if ( '' !== $url ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google sets the version.
			wp_enqueue_style( 'kbroo-fonts-' . substr( md5( $url ), 0, 8 ), $url, array(), null );
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
