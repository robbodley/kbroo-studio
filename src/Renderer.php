<?php
/**
 * Renders a layout tree to front-end HTML and CSS.
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

	/** @var array<string,string[]> CSS rules collected while rendering, by device. */
	private $rules = array();

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	public function render( array $layout, int $post_id = 0 ): string {
		$this->rules = array_fill_keys( Controls::DEVICES, array() );

		$html = '';
		foreach ( (array) ( $layout['elements'] ?? array() ) as $section ) {
			if ( is_array( $section ) && 'section' === ( $section['elType'] ?? '' ) ) {
				$html .= $this->render_section( $section );
			}
		}

		$css = Css::stylesheet( $this->rules );
		if ( '' !== $css ) {
			// Values are validated before they reach the CSS; this only
			// guarantees the style element cannot be closed early.
			$html = '<style class="kbroo-css">' . str_replace( '</', '<\/', $css ) . '</style>' . $html;
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

	/**
	 * Settings with defaults, after collecting the element's CSS.
	 */
	private function prepare( array $element, array $controls ): array {
		$settings = Controls::with_defaults( $controls, $element['settings'] ?? array() );
		$rules    = Css::element_rules( $controls, $settings, Css::wrapper( $this->element_id( $element ) ) );
		foreach ( $rules as $device => $list ) {
			$this->rules[ $device ] = array_merge( $this->rules[ $device ], $list );
		}
		return $settings;
	}

	private function element_id( array $element ): string {
		return preg_replace( '/[^a-f0-9]/', '', (string) ( $element['id'] ?? '' ) );
	}

	/**
	 * Shared wrapper attributes: classes, CSS ID and data-id.
	 *
	 * @param string[] $classes Base classes.
	 */
	private function attributes( array $element, array $settings, array $classes, string $prefix ): string {
		$id        = $this->element_id( $element );
		$classes[] = 'kbroo-el';
		$classes[] = 'kbroo-el-' . $id;
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( 'yes' === ( $settings[ 'hide_' . $device ] ?? '' ) ) {
				$classes[] = 'kbroo-hidden-' . $device;
			}
		}
		$custom = trim( (string) ( $settings[ $prefix . 'css_classes' ] ?? '' ) );
		if ( '' !== $custom ) {
			$classes = array_merge( $classes, preg_split( '/\s+/', $custom ) );
		}

		$attributes = ' class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '"';
		$css_id     = (string) ( $settings[ $prefix . 'element_id' ] ?? '' );
		if ( '' !== $css_id ) {
			$attributes .= ' id="' . esc_attr( $css_id ) . '"';
		}
		return $attributes . ' data-id="' . esc_attr( $id ) . '"';
	}

	private function render_section( array $section ): string {
		$settings = $this->prepare( $section, Layout::section_controls() );

		$columns = '';
		foreach ( (array) ( $section['elements'] ?? array() ) as $column ) {
			if ( is_array( $column ) && 'column' === ( $column['elType'] ?? '' ) ) {
				$columns .= $this->render_column( $column );
			}
		}

		$tags = array_keys( Layout::section_controls()['html_tag']['options'] );
		$tag  = in_array( $settings['html_tag'], $tags, true ) ? $settings['html_tag'] : 'section';

		return sprintf(
			'<%1$s%2$s><div class="kbroo-container"><div class="kbroo-row">%3$s</div></div></%1$s>',
			$tag,
			$this->attributes(
				$section,
				$settings,
				array( 'kbroo-section', 'kbroo-section--' . ( 'full_width' === $settings['layout'] ? 'full' : 'boxed' ) ),
				''
			),
			$columns
		);
	}

	private function render_column( array $column ): string {
		$settings = $this->prepare( $column, Layout::column_controls() );

		$widgets = '';
		foreach ( (array) ( $column['elements'] ?? array() ) as $widget ) {
			if ( is_array( $widget ) && 'widget' === ( $widget['elType'] ?? '' ) ) {
				$widgets .= $this->render_widget( $widget );
			}
		}

		return sprintf(
			'<div%1$s><div class="kbroo-column-inner">%2$s</div></div>',
			$this->attributes( $column, $settings, array( 'kbroo-column' ), '' ),
			$widgets
		);
	}

	private function render_widget( array $widget ): string {
		$instance = $this->widgets->get( (string) ( $widget['widgetType'] ?? '' ) );
		if ( ! $instance ) {
			return '';
		}
		$settings = $this->prepare( $widget, $instance->get_controls() );

		return sprintf(
			'<div%1$s>%2$s</div>',
			$this->attributes( $widget, $settings, array( 'kbroo-widget', 'kbroo-widget--' . $instance->get_name() ), '_' ),
			$instance->render( $settings )
		);
	}
}
