<?php
/**
 * Categories: WordPress's Categories widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class WpCategories extends WpWidget {

	public function get_name(): string {
		return 'wp-widget-categories';
	}

	public function get_title(): string {
		return __( 'Categories', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-category';
	}

	protected function widget_class(): string {
		return 'WP_Widget_Categories';
	}

	protected function instance_controls(): array {
		return array(
			'dropdown'     => array(
				'type'  => 'switcher',
				'label' => __( 'Show as a drop-down', 'kbroo-studio' ),
			),
			'count'        => array(
				'type'  => 'switcher',
				'label' => __( 'Show post counts', 'kbroo-studio' ),
			),
			'hierarchical' => array(
				'type'  => 'switcher',
				'label' => __( 'Show hierarchy', 'kbroo-studio' ),
			),
		);
	}

	protected function instance( array $settings ): array {
		return array(
			'dropdown'     => 'yes' === $settings['dropdown'] ? 1 : 0,
			'count'        => 'yes' === $settings['count'] ? 1 : 0,
			'hierarchical' => 'yes' === $settings['hierarchical'] ? 1 : 0,
		);
	}
}
