<?php
/**
 * Sidebar: the widgets in one of the theme's widget areas (Appearance >
 * Widgets). Settings match Elementor's Sidebar widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Sidebar extends Widget {

	public function get_name(): string {
		return 'sidebar';
	}

	public function get_title(): string {
		return __( 'Sidebar', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-align-pull-right';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * @return array<string,string>
	 */
	private static function sidebars(): array {
		global $wp_registered_sidebars;
		$out = array( '' => __( '— Choose a widget area —', 'kbroo-studio' ) );
		foreach ( (array) $wp_registered_sidebars as $id => $sidebar ) {
			$out[ $id ] = $sidebar['name'];
		}
		return $out;
	}

	protected function widget_controls(): array {
		return array(
			'sidebar' => array(
				'type'        => 'select',
				'label'       => __( 'Widget area', 'kbroo-studio' ),
				'section'     => __( 'Sidebar', 'kbroo-studio' ),
				'default'     => '',
				'options'     => self::sidebars(),
				'description' => __( 'The widgets come from Appearance > Widgets. Block themes may have no widget areas.', 'kbroo-studio' ),
			),
		);
	}

	public function render( array $settings ): string {
		$id = (string) $settings['sidebar'];
		if ( '' === $id || ! is_active_sidebar( $id ) ) {
			return '' === $this->element_id ? '<p class="kbroo-nav-empty">' . esc_html__( 'Choose a widget area that has widgets.', 'kbroo-studio' ) . '</p>' : '';
		}
		ob_start();
		dynamic_sidebar( $id );
		return '<div class="kbroo-sidebar">' . ob_get_clean() . '</div>';
	}
}
