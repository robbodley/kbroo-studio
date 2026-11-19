<?php
/**
 * Menu anchor widget: a named point on the page that links such as
 * "#contact" jump to. Settings match Elementor's menu-anchor widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class MenuAnchor extends Widget {

	public function get_name(): string {
		return 'menu-anchor';
	}

	public function get_title(): string {
		return __( 'Menu anchor', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-admin-links';
	}

	protected function widget_controls(): array {
		return array(
			'anchor' => array(
				'type'        => 'text',
				'label'       => __( 'Anchor name', 'kbroo-studio' ),
				'section'     => __( 'Anchor', 'kbroo-studio' ),
				'description' => __( 'Link to it with #name, for example in a menu or a button.', 'kbroo-studio' ),
				'default'     => '',
				'sanitize'    => 'id',
			),
		);
	}

	public function render( array $settings ): string {
		$anchor = (string) $settings['anchor'];
		if ( '' === $anchor ) {
			return '';
		}
		return '<div class="kbroo-menu-anchor" id="' . esc_attr( $anchor ) . '"></div>';
	}
}
