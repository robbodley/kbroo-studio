<?php
/**
 * Shortcode widget: runs another plugin's shortcode. Settings match
 * Elementor's shortcode widget. The editor previews it with HTML rendered on
 * the server, since only WordPress can run shortcodes.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Shortcode extends Widget {

	public function get_name(): string {
		return 'shortcode';
	}

	public function get_title(): string {
		return __( 'Shortcode', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-shortcode';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		return array(
			'shortcode' => array(
				'type'    => 'textarea',
				'label'   => __( 'Shortcode', 'kbroo-studio' ),
				'section' => __( 'Shortcode', 'kbroo-studio' ),
				'default' => '',
			),
		);
	}

	public function render( array $settings ): string {
		$shortcode = trim( (string) $settings['shortcode'] );
		if ( '' === $shortcode ) {
			return '';
		}
		return '<div class="kbroo-shortcode">' . do_shortcode( shortcode_unautop( $shortcode ) ) . '</div>';
	}
}
