<?php
/**
 * Image widget. Settings match Elementor's image widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class Image extends Widget {

	const SIZES = array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' );

	public function get_name(): string {
		return 'image';
	}

	public function get_title(): string {
		return __( 'Image', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-image';
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Image', 'kbroo-studio' ) );
		$style   = array(
			'tab'     => 'style',
			'section' => __( 'Image', 'kbroo-studio' ),
		);
		$caption = array(
			'tab'       => 'style',
			'section'   => __( 'Caption', 'kbroo-studio' ),
			'condition' => array( 'caption_source!' => 'none' ),
		);
		$img     = '{{WRAPPER}} .kbroo-image img';

		return array_merge(
			array(
				'image'          => $content + array(
					'type'  => 'media',
					'label' => __( 'Choose image', 'kbroo-studio' ),
				),
				'image_size'     => $content + array(
					'type'    => 'select',
					'label'   => __( 'Image size', 'kbroo-studio' ),
					'default' => 'large',
					'options' => array(
						'thumbnail'    => __( 'Thumbnail', 'kbroo-studio' ),
						'medium'       => __( 'Medium', 'kbroo-studio' ),
						'medium_large' => __( 'Medium large', 'kbroo-studio' ),
						'large'        => __( 'Large', 'kbroo-studio' ),
						'full'         => __( 'Full', 'kbroo-studio' ),
					),
				),
				'align'          => ControlGroups::align( '{{WRAPPER}} .kbroo-image', $content ),
				'caption_source' => $content + array(
					'type'    => 'select',
					'label'   => __( 'Caption', 'kbroo-studio' ),
					'default' => 'none',
					'options' => array(
						'none'       => __( 'None', 'kbroo-studio' ),
						'attachment' => __( 'From Media Library', 'kbroo-studio' ),
						'custom'     => __( 'Custom', 'kbroo-studio' ),
					),
				),
				'caption'        => $content + array(
					'type'      => 'text',
					'label'     => __( 'Custom caption', 'kbroo-studio' ),
					'default'   => '',
					'condition' => array( 'caption_source' => 'custom' ),
				),
				'link_to'        => $content + array(
					'type'    => 'select',
					'label'   => __( 'Link', 'kbroo-studio' ),
					'default' => 'none',
					'options' => array(
						'none'   => __( 'None', 'kbroo-studio' ),
						'file'   => __( 'Image file', 'kbroo-studio' ),
						'custom' => __( 'Custom URL', 'kbroo-studio' ),
					),
				),
				'link'           => $content + array(
					'type'      => 'url',
					'label'     => __( 'Link URL', 'kbroo-studio' ),
					'condition' => array( 'link_to' => 'custom' ),
				),
				'width'          => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Width', 'kbroo-studio' ),
					'units'      => array( '%', 'px', 'vw' ),
					'responsive' => true,
					'selectors'  => array( $img => 'width: {{SIZE}}{{UNIT}};' ),
				),
				'space'          => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Max width', 'kbroo-studio' ),
					'units'      => array( '%', 'px', 'vw' ),
					'responsive' => true,
					'selectors'  => array( $img => 'max-width: {{SIZE}}{{UNIT}};' ),
				),
				'height'         => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Height', 'kbroo-studio' ),
					'units'      => array( 'px', 'vh' ),
					'responsive' => true,
					'selectors'  => array( $img => 'height: {{SIZE}}{{UNIT}};' ),
				),
				'object-fit'     => $style + array(
					'type'      => 'select',
					'label'     => __( 'Object fit', 'kbroo-studio' ),
					'default'   => '',
					'options'   => array(
						''        => __( 'Default', 'kbroo-studio' ),
						'fill'    => __( 'Fill', 'kbroo-studio' ),
						'cover'   => __( 'Cover', 'kbroo-studio' ),
						'contain' => __( 'Contain', 'kbroo-studio' ),
					),
					'selectors' => array( $img => 'object-fit: {{VALUE}};' ),
				),
				'opacity'        => $style + array(
					'type'      => 'number',
					'label'     => __( 'Opacity (0 to 1)', 'kbroo-studio' ),
					'default'   => '',
					'min'       => 0,
					'max'       => 1,
					'selectors' => array( $img => 'opacity: {{VALUE}};' ),
				),
			),
			ControlGroups::border( $img, array(), 'image_border' ),
			array(
				'caption_align' => ControlGroups::align( '{{WRAPPER}} .kbroo-image__caption', $caption ),
				'text_color'    => $caption + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-image__caption' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'caption_typography', '{{WRAPPER}} .kbroo-image__caption', $caption )
		);
	}

	public function render( array $settings ): string {
		$image = is_array( $settings['image'] ) ? $settings['image'] : array();
		$id    = absint( $image['id'] ?? 0 );
		$url   = (string) ( $image['url'] ?? '' );
		$size  = in_array( $settings['image_size'], self::SIZES, true ) ? $settings['image_size'] : 'large';

		$img = $id ? wp_get_attachment_image( $id, $size ) : '';
		if ( '' === $img ) {
			if ( '' === $url ) {
				return '';
			}
			$img = '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" />';
		}

		$link = '';
		if ( 'file' === $settings['link_to'] ) {
			$file = $id ? wp_get_attachment_url( $id ) : $url;
			$link = $file ? ' href="' . esc_url( $file ) . '"' : '';
		} elseif ( 'custom' === $settings['link_to'] ) {
			$link = self::link_attributes( $settings['link'] );
		}
		if ( '' !== $link ) {
			$img = '<a' . $link . '>' . $img . '</a>';
		}

		$caption = '';
		if ( 'attachment' === $settings['caption_source'] && $id ) {
			$caption = (string) wp_get_attachment_caption( $id );
		} elseif ( 'custom' === $settings['caption_source'] ) {
			$caption = (string) $settings['caption'];
		}
		if ( '' !== $caption ) {
			$img .= '<figcaption class="kbroo-image__caption">' . esc_html( $caption ) . '</figcaption>';
		}

		return '<figure class="kbroo-image">' . $img . '</figure>';
	}
}
