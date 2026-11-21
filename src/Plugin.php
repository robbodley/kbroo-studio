<?php
/**
 * Plugin bootstrap: wires the services together.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Registry */
	private $widgets;

	/** @var Renderer */
	private $renderer;

	/** @var Frontend|null */
	private $frontend;

	/** @var ThemeBuilder */
	private $theme_builder;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		$this->widgets  = new Registry();
		$this->renderer = new Renderer( $this->widgets );

		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'init', array( $this->widgets, 'register_core_widgets' ), 5 );

		( new Rest( $this->widgets, $this->renderer ) )->hooks();
		( new Editor( $this->widgets ) )->hooks();
		$frontend       = new Frontend( $this->renderer );
		$this->frontend = $frontend;
		$frontend->hooks();
		$this->theme_builder = new ThemeBuilder( $frontend );
		$this->theme_builder->hooks();
		( new Revisions() )->hooks();
		( new PageSettings() )->hooks();
		if ( is_admin() ) {
			( new Admin() )->hooks();
		}
		( new Forms( $this->widgets ) )->hooks();
		( new Templates( $this->widgets, $this->renderer ) )->hooks();
		( new Elementor\Migration( $this->widgets, $this->renderer ) )->hooks();
		( new Updater() )->register(); // Also runs for background auto-updates.
	}

	/**
	 * Load the stylesheet, global styles and fonts a layout needs (for
	 * layouts drawn inside another, such as loop item templates).
	 */
	public function frontend_enqueue( array $layout ): void {
		if ( $this->frontend ) {
			$this->frontend->enqueue_layout( $layout );
		}
	}

	public function theme_builder(): ThemeBuilder {
		return $this->theme_builder;
	}

	public function widgets(): Registry {
		return $this->widgets;
	}

	public function renderer(): Renderer {
		return $this->renderer;
	}

	/**
	 * Register the post meta keys. They are protected (leading underscore) and
	 * only written through our REST route, so they are not exposed to the
	 * core REST API.
	 */
	public function register_meta(): void {
		foreach ( Document::editable_types() as $post_type ) {
			register_post_meta(
				$post_type,
				Document::META_DATA,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
				)
			);
			register_post_meta(
				$post_type,
				Document::META_MODE,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => false,
				)
			);
		}
	}
}
