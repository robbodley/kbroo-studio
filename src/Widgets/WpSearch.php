<?php
/**
 * Search: WordPress's Search widget (the theme's search form).
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class WpSearch extends WpWidget {

	public function get_name(): string {
		return 'wp-widget-search';
	}

	public function get_title(): string {
		return __( 'Search', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-search';
	}

	protected function widget_class(): string {
		return 'WP_Widget_Search';
	}

	protected function instance_controls(): array {
		return array();
	}

	protected function instance( array $settings ): array {
		return array();
	}
}
