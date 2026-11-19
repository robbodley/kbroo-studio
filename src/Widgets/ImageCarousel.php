<?php
/**
 * Image carousel widget. Settings match Elementor's image-carousel widget
 * (slides_to_show, navigation, infinite...); the carousel itself is shared
 * with the testimonial carousel.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Frontend;

defined( 'ABSPATH' ) || exit;

class ImageCarousel extends Widget {

	public function get_name(): string {
		return 'image-carousel';
	}

	public function get_title(): string {
		return __( 'Image carousel', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-images-alt2';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content  = array( 'section' => __( 'Images', 'kbroo-studio' ) );
		$options  = array( 'section' => __( 'Carousel', 'kbroo-studio' ) );
		$autoplay = array( 'condition' => array( 'autoplay' => 'yes' ) );
		$images   = array(
			'tab'     => 'style',
			'section' => __( 'Images', 'kbroo-studio' ),
		);
		$nav      = array(
			'tab'     => 'style',
			'section' => __( 'Arrows and dots', 'kbroo-studio' ),
		);
		$caption  = array(
			'tab'       => 'style',
			'section'   => __( 'Caption', 'kbroo-studio' ),
			'condition' => array( 'caption_type!' => '' ),
		);
		$numbers  = array( '' => __( 'Default', 'kbroo-studio' ) );
		foreach ( range( 1, 10 ) as $n ) {
			$numbers[ (string) $n ] = (string) $n;
		}

		return array_merge(
			array(
				'carousel'        => $content + array(
					'type'  => 'gallery',
					'label' => __( 'Images', 'kbroo-studio' ),
				),
				'thumbnail_size'  => Gallery::size_control( $content, 'medium_large' ),
				'slides_to_show'  => $content + array(
					'type'       => 'select',
					'label'      => __( 'Slides on view', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => $numbers,
					'selectors'  => array( '{{WRAPPER}} .kbroo-carousel' => '--kbroo-per-view: {{VALUE}};' ),
				),
				'slides_to_scroll' => $content + array(
					'type'    => 'select',
					'label'   => __( 'Slides to scroll', 'kbroo-studio' ),
					'default' => '',
					'options' => $numbers,
				),
				'image_stretch'   => $content + array(
					'type'                 => 'select',
					'label'                => __( 'Stretch images to fill', 'kbroo-studio' ),
					'default'              => 'no',
					'options'              => array(
						'no'  => __( 'No', 'kbroo-studio' ),
						'yes' => __( 'Yes', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'no'  => 'auto',
						'yes' => '100%',
					),
					'selectors'            => array( '{{WRAPPER}} .kbroo-gallery__item img' => 'width: {{VALUE}};' ),
				),
				'navigation'      => $content + array(
					'type'    => 'select',
					'label'   => __( 'Navigation', 'kbroo-studio' ),
					'default' => 'both',
					'options' => array(
						'both'   => __( 'Arrows and dots', 'kbroo-studio' ),
						'arrows' => __( 'Arrows', 'kbroo-studio' ),
						'dots'   => __( 'Dots', 'kbroo-studio' ),
						'none'   => __( 'None', 'kbroo-studio' ),
					),
				),
				'link_to'         => $content + array(
					'type'    => 'select',
					'label'   => __( 'Link', 'kbroo-studio' ),
					'default' => 'none',
					'options' => array(
						'none'   => __( 'None', 'kbroo-studio' ),
						'file'   => __( 'Image file', 'kbroo-studio' ),
						'custom' => __( 'Custom address', 'kbroo-studio' ),
					),
				),
				'link'            => $content + array(
					'type'      => 'url',
					'label'     => __( 'Address', 'kbroo-studio' ),
					'condition' => array( 'link_to' => 'custom' ),
				),
				'open_lightbox'   => Gallery::lightbox_control( $content + array( 'condition' => array( 'link_to' => 'file' ) ) ),
				'caption_type'    => $content + array(
					'type'    => 'select',
					'label'   => __( 'Caption', 'kbroo-studio' ),
					'default' => '',
					'options' => array(
						''            => __( 'None', 'kbroo-studio' ),
						'title'       => __( 'Title', 'kbroo-studio' ),
						'caption'     => __( 'Caption', 'kbroo-studio' ),
						'description' => __( 'Description', 'kbroo-studio' ),
					),
				),

				'autoplay'        => $options + array(
					'type'    => 'switcher',
					'label'   => __( 'Autoplay', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'autoplay_speed'  => $options + $autoplay + array(
					'type'    => 'number',
					'label'   => __( 'Autoplay delay (ms)', 'kbroo-studio' ),
					'default' => 5000,
					'min'     => 1000,
					'max'     => 60000,
				),
				'pause_on_hover'  => $options + $autoplay + array(
					'type'    => 'switcher',
					'label'   => __( 'Pause on hover', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'infinite'        => $options + array(
					'type'    => 'switcher',
					'label'   => __( 'Back to the start after the last slide', 'kbroo-studio' ),
					'default' => 'yes',
				),

				'image_spacing_custom' => $images + array(
					'type'       => 'slider',
					'label'      => __( 'Space between', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-carousel' => '--kbroo-slide-gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::border( '{{WRAPPER}} .kbroo-gallery__item img', $images, 'image_border' ),
			array(
				'arrows_size'  => $nav + array(
					'type'      => 'slider',
					'label'     => __( 'Arrow size', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__arrow' => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
				'arrows_color' => $nav + array(
					'type'      => 'color',
					'label'     => __( 'Arrow colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__arrow' => 'color: {{VALUE}};' ),
				),
				'dots_size'    => $nav + array(
					'type'      => 'slider',
					'label'     => __( 'Dot size', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__dot' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
				),
				'dots_color'   => $nav + array(
					'type'      => 'color',
					'label'     => __( 'Dot colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__pagination' => '--kbroo-dot: {{VALUE}};' ),
				),

				'caption_align'      => ControlGroups::align( '{{WRAPPER}} .kbroo-gallery__caption', $caption ),
				'caption_text_color' => $caption + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-gallery__caption' => 'color: {{VALUE}};' ),
				),
				'caption_space'      => $caption + array(
					'type'      => 'slider',
					'label'     => __( 'Space above', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-gallery__caption' => 'margin-top: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'caption_typography', '{{WRAPPER}} .kbroo-gallery__caption', $caption )
		);
	}

	public function render( array $settings ): string {
		$images = is_array( $settings['carousel'] ) ? $settings['carousel'] : array();
		$link   = in_array( $settings['link_to'], array( 'file', 'custom' ), true ) ? $settings['link_to'] : 'none';
		$custom = 'custom' === $link ? self::link_attributes( $settings['link'] ) : '';
		$group  = 'file' === $link && 'no' !== $settings['open_lightbox']
			? 'carousel-' . ( '' !== $this->element_id ? $this->element_id : wp_rand() )
			: '';
		$slides = array();
		foreach ( $images as $image ) {
			$html = Gallery::image( (array) $image, (string) $settings['thumbnail_size'], $link, $group, Gallery::caption( (array) $image, (string) $settings['caption_type'] ), $custom );
			if ( '' !== $html ) {
				$slides[] = $html;
			}
		}
		if ( ! $slides ) {
			return '';
		}
		Frontend::enqueue_script();
		$nav = (string) $settings['navigation'];
		return TestimonialCarousel::carousel(
			$slides,
			array(
				'slides_to_scroll' => $settings['slides_to_scroll'],
				'autoplay'         => $settings['autoplay'],
				'autoplay_speed'   => $settings['autoplay_speed'],
				'pause_on_hover'   => $settings['pause_on_hover'],
				'loop'             => $settings['infinite'],
				'show_arrows'      => in_array( $nav, array( 'both', 'arrows' ), true ) ? 'yes' : '',
				'pagination'       => in_array( $nav, array( 'both', 'dots' ), true ) ? 'bullets' : '',
			),
			__( 'Image carousel', 'kbroo-studio' )
		);
	}
}
