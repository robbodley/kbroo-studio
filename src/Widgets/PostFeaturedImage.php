<?php
/**
 * Featured image: the featured image of the post being viewed, with the
 * Image widget's settings. Settings match Elementor Pro's Featured Image
 * widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class PostFeaturedImage extends Image {

	public function get_name(): string {
		return 'post-featured-image';
	}

	public function get_title(): string {
		return __( 'Featured image', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-image';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$controls = parent::widget_controls();
		unset( $controls['image'] );
		$controls['link_to']['options'] = array( 'post' => __( 'The post', 'kbroo-studio' ) ) + $controls['link_to']['options'];
		return array(
			'fallback' => array(
				'type'        => 'media',
				'label'       => __( 'Image when the post has none', 'kbroo-studio' ),
				'section'     => __( 'Image', 'kbroo-studio' ),
				'description' => __( 'Leave empty to show nothing.', 'kbroo-studio' ),
			),
		) + $controls;
	}

	public function render( array $settings ): string {
		return DynamicPost::with(
			function ( $post ) use ( $settings ) {
				$id = (int) get_post_thumbnail_id( $post );
				if ( $id ) {
					$settings['image'] = array(
						'id'  => $id,
						'url' => (string) wp_get_attachment_url( $id ),
					);
				} else {
					$settings['image'] = is_array( $settings['fallback'] ) ? $settings['fallback'] : array();
				}
				if ( 'post' === $settings['link_to'] ) {
					$settings['link_to'] = 'custom';
					$settings['link']    = array( 'url' => get_permalink( $post ) );
				}
				$html = parent::render( $settings );
				return '' !== $html ? str_replace( 'class="kbroo-image"', 'class="kbroo-image kbroo-post-featured-image"', $html ) : '';
			}
		);
	}
}
