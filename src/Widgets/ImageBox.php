<?php
/**
 * Image box widget: an image with a title and description beside or above
 * it. Settings match Elementor's image-box widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class ImageBox extends Widget {

	public function get_name(): string {
		return 'image-box';
	}

	public function get_title(): string {
		return __( 'Image box', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-gallery';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$label   = __( 'Image', 'kbroo-studio' );
		$content = array( 'section' => $label );
		$style   = array(
			'tab'     => 'style',
			'section' => $label,
		);
		$hover   = array(
			'tab'     => 'style',
			'section' => __( 'Image on hover', 'kbroo-studio' ),
		);
		$img     = '{{WRAPPER}} .kbroo-box__media img';
		$box     = IconBox::box_controls( $label );

		return array_merge(
			array(
				'image'          => $content + array(
					'type'  => 'media',
					'label' => __( 'Choose image', 'kbroo-studio' ),
				),
				'thumbnail_size' => $content + array(
					'type'    => 'select',
					'label'   => __( 'Image size', 'kbroo-studio' ),
					'default' => 'full',
					'options' => array(
						'thumbnail'    => __( 'Thumbnail', 'kbroo-studio' ),
						'medium'       => __( 'Medium', 'kbroo-studio' ),
						'medium_large' => __( 'Medium large', 'kbroo-studio' ),
						'large'        => __( 'Large', 'kbroo-studio' ),
						'full'         => __( 'Full', 'kbroo-studio' ),
					),
				),
			),
			$box['content'],
			$box['layout'],
			array(
				'image_space'   => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Spacing', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-box' => '--kbroo-box-gap: {{SIZE}}{{UNIT}};' ),
				),
				'image_size'    => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Width (%)', 'kbroo-studio' ),
					'units'      => array( '%' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-box' => '--kbroo-box-image-width: {{SIZE}}{{UNIT}};' ),
				),
				'image_opacity' => $style + array(
					'type'      => 'slider',
					'label'     => __( 'Opacity (0 to 1)', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'min'       => 0,
					'max'       => 1,
					'selectors' => array( $img => 'opacity: {{SIZE}};' ),
				),
			),
			ControlGroups::css_filters( $img, $style ),
			ControlGroups::border( $img, $style, 'image_border' ),
			array(
				'image_opacity_hover'         => $hover + array(
					'type'      => 'slider',
					'label'     => __( 'Opacity (0 to 1)', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'min'       => 0,
					'max'       => 1,
					'selectors' => array( '{{WRAPPER}} .kbroo-box:hover .kbroo-box__media img' => 'opacity: {{SIZE}};' ),
				),
				'background_hover_transition' => $hover + array(
					'type'      => 'slider',
					'label'     => __( 'Transition duration (s)', 'kbroo-studio' ),
					'units'     => array( 's' ),
					'selectors' => array( $img => 'transition-duration: {{SIZE}}s;' ),
				),
			),
			ControlGroups::css_filters( '{{WRAPPER}} .kbroo-box:hover .kbroo-box__media img', $hover, 'css_filters_hover' ),
			ControlGroups::hover_animation( '{{WRAPPER}} .kbroo-box__media', $hover ),
			$box['style']
		);
	}

	public function render( array $settings ): string {
		$image = is_array( $settings['image'] ) ? $settings['image'] : array();
		$id    = absint( $image['id'] ?? 0 );
		$url   = (string) ( $image['url'] ?? '' );
		$size  = in_array( $settings['thumbnail_size'], Image::SIZES, true ) ? $settings['thumbnail_size'] : 'full';
		$link  = self::link_attributes( $settings['link'] );

		$img = $id ? wp_get_attachment_image( $id, $size ) : '';
		if ( '' === $img && '' !== $url ) {
			$img = '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" />';
		}
		if ( '' !== $img && '' !== $link ) {
			$img = '<a' . $link . ' tabindex="-1" aria-hidden="true">' . $img . '</a>';
		}
		return IconBox::box( $img, $settings, $link );
	}
}
