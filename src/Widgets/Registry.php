<?php
/**
 * Widget registry.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

final class Registry {

	/** @var array<string,Widget> */
	private $widgets = array();

	public function register( Widget $widget ): void {
		$this->widgets[ $widget->get_name() ] = $widget;
	}

	public function get( string $name ): ?Widget {
		return $this->widgets[ $name ] ?? null;
	}

	/**
	 * @return array<string,Widget>
	 */
	public function all(): array {
		return $this->widgets;
	}

	public function flush_controls(): void {
		foreach ( $this->widgets as $widget ) {
			$widget->flush_controls();
		}
	}

	public function register_core_widgets(): void {
		$this->register( new Heading() );
		$this->register( new TextEditor() );
		$this->register( new Image() );
		$this->register( new Button() );
		$this->register( new Spacer() );
		$this->register( new Divider() );
		$this->register( new Html() );
		$this->register( new Shortcode() );
		$this->register( new MenuAnchor() );
		$this->register( new Icon() );
		$this->register( new SocialIcons() );
		$this->register( new TestimonialCarousel() );
		$this->register( new ElementorFallback() );

		/**
		 * Register additional widgets.
		 *
		 * @param Registry $registry Call $registry->register( new My_Widget() ).
		 */
		do_action( 'kbroo_studio_register_widgets', $this );
	}

	public function to_editor_config(): array {
		$config = array();
		foreach ( $this->widgets as $name => $widget ) {
			$config[ $name ] = $widget->to_editor_config();
		}
		return $config;
	}
}
