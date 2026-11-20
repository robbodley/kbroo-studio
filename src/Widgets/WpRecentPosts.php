<?php
/**
 * Recent posts: WordPress's Recent Posts widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class WpRecentPosts extends WpWidget {

	public function get_name(): string {
		return 'wp-widget-recent-posts';
	}

	public function get_title(): string {
		return __( 'Recent posts', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-list-view';
	}

	protected function widget_class(): string {
		return 'WP_Widget_Recent_Posts';
	}

	protected function instance_controls(): array {
		return array(
			'number'    => array(
				'type'    => 'number',
				'label'   => __( 'Number of posts', 'kbroo-studio' ),
				'default' => 5,
				'min'     => 1,
				'max'     => 30,
			),
			'show_date' => array(
				'type'  => 'switcher',
				'label' => __( 'Show the date', 'kbroo-studio' ),
			),
		);
	}

	protected function instance( array $settings ): array {
		return array(
			'number'    => max( 1, min( 30, (int) $settings['number'] ) ),
			'show_date' => 'yes' === $settings['show_date'],
		);
	}
}
