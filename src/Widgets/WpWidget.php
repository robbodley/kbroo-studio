<?php
/**
 * Base for widgets that show one of WordPress's own widgets (the ones under
 * Appearance > Widgets), as Elementor's "WordPress" widgets do. Elementor
 * keeps their settings under a "wp" key; the converter lifts them out.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

abstract class WpWidget extends Widget {

	/** The WP_Widget class to show. */
	abstract protected function widget_class(): string;

	/** This widget's own settings, as WP_Widget instance values. */
	abstract protected function instance( array $settings ): array;

	/** Controls for the instance values. */
	abstract protected function instance_controls(): array;

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$section = $this->get_title();
		$text    = array(
			'tab'     => 'style',
			'section' => __( 'Text', 'kbroo-studio' ),
		);
		$box     = '{{WRAPPER}} .kbroo-wp-widget';
		$title   = '{{WRAPPER}} .kbroo-wp-widget__title';

		$controls = array(
			'title' => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'kbroo-studio' ),
				'section' => $section,
				'default' => '',
			),
		);
		foreach ( $this->instance_controls() as $key => $control ) {
			$controls[ $key ] = $control + array( 'section' => $section );
		}
		return array_merge(
			$controls,
			array(
				'title_color' => $text + array(
					'type'      => 'color',
					'label'     => __( 'Title color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $title => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'title_typography', $title, $text + array( 'section' => __( 'Title', 'kbroo-studio' ) ) ),
			array(
				'text_color'       => $text + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box => 'color: {{VALUE}};' ),
				),
				'link_color'       => $text + array(
					'type'      => 'color',
					'label'     => __( 'Link color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' a' => 'color: {{VALUE}};' ),
				),
				'link_hover_color' => $text + array(
					'type'      => 'color',
					'label'     => __( 'Link color on hover', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' a:hover' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography', $box, $text )
		);
	}

	public function render( array $settings ): string {
		$class = $this->widget_class();
		if ( ! class_exists( $class ) ) {
			return '';
		}
		$slug = sanitize_html_class( str_replace( 'wp-widget-', '', $this->get_name() ) );
		ob_start();
		the_widget(
			$class,
			array( 'title' => sanitize_text_field( (string) $settings['title'] ) ) + $this->instance( $settings ),
			array(
				'before_widget' => '<div class="kbroo-wp-widget kbroo-wp-widget--' . $slug . '">',
				'after_widget'  => '</div>',
				'before_title'  => '<h5 class="kbroo-wp-widget__title">',
				'after_title'   => '</h5>',
			)
		);
		return (string) ob_get_clean();
	}
}
