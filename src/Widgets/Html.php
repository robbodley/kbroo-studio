<?php
/**
 * HTML widget: custom code, output as written. Settings match Elementor's
 * html widget. Only users allowed to post unfiltered HTML keep scripts and
 * other tags that wp_kses_post() removes (see Controls, type "code").
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Html extends Widget {

	public function get_name(): string {
		return 'html';
	}

	public function get_title(): string {
		return __( 'HTML', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-editor-code';
	}

	protected function widget_controls(): array {
		return array(
			'html' => array(
				'type'    => 'code',
				'label'   => __( 'HTML code', 'kbroo-studio' ),
				'section' => __( 'HTML code', 'kbroo-studio' ),
				'default' => '',
			),
		);
	}

	public function render( array $settings ): string {
		return (string) $settings['html'];
	}
}
