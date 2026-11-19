<?php
/**
 * Spacer widget. Settings match Elementor's spacer widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Spacer extends Widget {

	public function get_name(): string {
		return 'spacer';
	}

	public function get_title(): string {
		return __( 'Spacer', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-image-flip-vertical';
	}

	protected function widget_controls(): array {
		return array(
			'space' => array(
				'type'       => 'slider',
				'label'      => __( 'Space', 'kbroo-studio' ),
				'section'    => __( 'Spacer', 'kbroo-studio' ),
				'units'      => array( 'px', 'vh', 'em' ),
				'default'    => array(
					'unit' => 'px',
					'size' => 50,
				),
				'responsive' => true,
				'selectors'  => array( '{{WRAPPER}} .kbroo-spacer' => 'height: {{SIZE}}{{UNIT}};' ),
			),
		);
	}

	public function render( array $settings ): string {
		return '<div class="kbroo-spacer" aria-hidden="true"></div>';
	}
}
