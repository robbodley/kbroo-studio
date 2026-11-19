<?php
/**
 * Renders a layout tree to front-end HTML.
 *
 * The editor canvas (assets/js/editor.js) produces the same markup and class
 * names so that what you see while editing matches the published page.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/** @var Registry */
	private $widgets;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	public function render( array $layout, int $post_id = 0 ): string {
		$html = '';
		foreach ( (array) ( $layout['elements'] ?? array() ) as $section ) {
			if ( is_array( $section ) && 'section' === ( $section['elType'] ?? '' ) ) {
				$html .= $this->render_section( $section );
			}
		}

		$html = sprintf(
			'<div class="kbroo kbroo-page alignfull" data-kbroo-post="%d">%s</div>',
			$post_id,
			$html
		);

		/**
		 * Filters the rendered HTML of a Kbroo Studio layout.
		 *
		 * @param string $html    Rendered HTML.
		 * @param array  $layout  Layout tree.
		 * @param int    $post_id Post ID.
		 */
		return (string) apply_filters( 'kbroo_studio_render', $html, $layout, $post_id );
	}

	private function render_section( array $section ): string {
		$settings = Controls::with_defaults( Layout::section_controls(), $section['settings'] ?? array() );
		$padding  = absint( $settings['padding'] ) . 'px';

		$style = $this->style(
			array(
				'background-color' => (string) sanitize_hex_color( $settings['background_color'] ),
				'padding-top'      => $padding,
				'padding-bottom'   => $padding,
				'--kbroo-gap'      => absint( $settings['gap'] ) . 'px',
			)
		);

		$columns = '';
		foreach ( (array) ( $section['elements'] ?? array() ) as $column ) {
			if ( is_array( $column ) && 'column' === ( $column['elType'] ?? '' ) ) {
				$columns .= $this->render_column( $column );
			}
		}

		return sprintf(
			'<section class="kbroo-section kbroo-section--%1$s" data-id="%2$s"%3$s><div class="kbroo-container"><div class="kbroo-row">%4$s</div></div></section>',
			'full' === $settings['content_width'] ? 'full' : 'boxed',
			esc_attr( (string) ( $section['id'] ?? '' ) ),
			$style,
			$columns
		);
	}

	private function render_column( array $column ): string {
		$settings = Controls::with_defaults( Layout::column_controls(), $column['settings'] ?? array() );
		$width    = max( 5, min( 100, (int) $settings['width'] ) );

		$widgets = '';
		foreach ( (array) ( $column['elements'] ?? array() ) as $widget ) {
			if ( is_array( $widget ) && 'widget' === ( $widget['elType'] ?? '' ) ) {
				$widgets .= $this->render_widget( $widget );
			}
		}

		return sprintf(
			'<div class="kbroo-column" data-id="%1$s"%2$s><div class="kbroo-column-inner">%3$s</div></div>',
			esc_attr( (string) ( $column['id'] ?? '' ) ),
			$this->style( array( '--kbroo-col-width' => $width ) ),
			$widgets
		);
	}

	private function render_widget( array $widget ): string {
		$instance = $this->widgets->get( (string) ( $widget['widgetType'] ?? '' ) );
		if ( ! $instance ) {
			return '';
		}
		$settings = Controls::with_defaults( $instance->get_controls(), $widget['settings'] ?? array() );

		return sprintf(
			'<div class="kbroo-widget kbroo-widget--%1$s" data-id="%2$s">%3$s</div>',
			esc_attr( $instance->get_name() ),
			esc_attr( (string) ( $widget['id'] ?? '' ) ),
			$instance->render( $settings )
		);
	}

	/**
	 * @param array<string,string|int> $rules Already-validated CSS values.
	 */
	private function style( array $rules ): string {
		$css = '';
		foreach ( $rules as $property => $value ) {
			if ( '' !== (string) $value ) {
				$css .= $property . ':' . $value . ';';
			}
		}
		return '' === $css ? '' : ' style="' . esc_attr( $css ) . '"';
	}
}
