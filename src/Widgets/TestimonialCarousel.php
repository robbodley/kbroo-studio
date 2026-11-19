<?php
/**
 * Testimonial carousel. Settings match Elementor Pro's testimonial-carousel
 * (slides, layout, slides_per_view, autoplay, ...). The carousel is a
 * scroll-snap strip that also works without JavaScript; frontend.js adds
 * arrows, dots, autoplay and looping.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Frontend;

defined( 'ABSPATH' ) || exit;

class TestimonialCarousel extends Widget {

	public function get_name(): string {
		return 'testimonial-carousel';
	}

	public function get_title(): string {
		return __( 'Testimonial carousel', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-quote';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * Slide-show settings shared by carousels: slides per view, arrows,
	 * pagination, autoplay and looping (Elementor's names).
	 */
	public static function carousel_controls( array $content ): array {
		$numbers = array( '' => __( 'Default', 'kbroo-studio' ) );
		foreach ( range( 1, 6 ) as $n ) {
			$numbers[ (string) $n ] = (string) $n;
		}
		$autoplay = array( 'condition' => array( 'autoplay' => 'yes' ) );
		return array(
			'slides_per_view'  => $content + array(
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
			'show_arrows'      => $content + array(
				'type'    => 'switcher',
				'label'   => __( 'Arrows', 'kbroo-studio' ),
				'default' => 'yes',
			),
			'pagination'       => $content + array(
				'type'    => 'select',
				'label'   => __( 'Pagination', 'kbroo-studio' ),
				'default' => 'bullets',
				'options' => array(
					''         => __( 'None', 'kbroo-studio' ),
					'bullets'  => __( 'Dots', 'kbroo-studio' ),
					'fraction' => __( 'Fraction (1 / 3)', 'kbroo-studio' ),
				),
			),
			'autoplay'         => $content + array(
				'type'    => 'switcher',
				'label'   => __( 'Autoplay', 'kbroo-studio' ),
				'default' => 'yes',
			),
			'autoplay_speed'   => $content + $autoplay + array(
				'type'    => 'number',
				'label'   => __( 'Autoplay delay (ms)', 'kbroo-studio' ),
				'default' => 5000,
				'min'     => 1000,
				'max'     => 60000,
			),
			'pause_on_hover'   => $content + $autoplay + array(
				'type'    => 'switcher',
				'label'   => __( 'Pause on hover', 'kbroo-studio' ),
				'default' => 'yes',
			),
			'loop'             => $content + array(
				'type'    => 'switcher',
				'label'   => __( 'Back to the start after the last slide', 'kbroo-studio' ),
				'default' => 'yes',
			),
			'space_between'    => $content + array(
				'type'       => 'slider',
				'label'      => __( 'Space between', 'kbroo-studio' ),
				'units'      => array( 'px' ),
				'responsive' => true,
				'selectors'  => array( '{{WRAPPER}} .kbroo-carousel' => '--kbroo-slide-gap: {{SIZE}}{{UNIT}};' ),
			),
		);
	}

	/**
	 * Carousel markup around rendered slides.
	 *
	 * @param string[] $slides Slide HTML.
	 */
	public static function carousel( array $slides, array $settings, string $label ): string {
		Frontend::enqueue_script();
		$options = array(
			'scroll'   => max( 1, (int) $settings['slides_to_scroll'] ),
			'autoplay' => 'yes' === $settings['autoplay'] ? max( 1000, (int) $settings['autoplay_speed'] ) : 0,
			'pause'    => 'yes' === $settings['pause_on_hover'],
			'loop'     => 'yes' === $settings['loop'],
		);
		$items   = '';
		$count   = count( $slides );
		foreach ( $slides as $i => $slide ) {
			/* translators: 1: slide number, 2: number of slides. */
			$items .= '<div class="kbroo-carousel__slide" role="group" aria-roledescription="slide" aria-label="' . esc_attr( sprintf( __( '%1$d of %2$d', 'kbroo-studio' ), $i + 1, $count ) ) . '">' . $slide . '</div>';
		}
		$arrows = '';
		if ( 'yes' === $settings['show_arrows'] ) {
			$arrows = '<button type="button" class="kbroo-carousel__arrow kbroo-carousel__arrow--prev" aria-label="' . esc_attr__( 'Previous slide', 'kbroo-studio' ) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>'
				. '<button type="button" class="kbroo-carousel__arrow kbroo-carousel__arrow--next" aria-label="' . esc_attr__( 'Next slide', 'kbroo-studio' ) . '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2"/></svg></button>';
		}
		$pagination = in_array( $settings['pagination'], array( 'bullets', 'fraction' ), true )
			? '<div class="kbroo-carousel__pagination kbroo-carousel__pagination--' . $settings['pagination'] . '"></div>'
			: '';
		return sprintf(
			'<div class="kbroo-carousel" role="region" aria-roledescription="carousel" aria-label="%1$s" data-kbroo-carousel="%2$s"><div class="kbroo-carousel__viewport"><div class="kbroo-carousel__track" tabindex="0">%3$s</div>%4$s</div>%5$s</div>',
			esc_attr( $label ),
			esc_attr( wp_json_encode( $options ) ),
			$items,
			$arrows,
			$pagination
		);
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Slides', 'kbroo-studio' ) );
		$options = array( 'section' => __( 'Carousel', 'kbroo-studio' ) );
		$slide   = function ( string $name, string $title ) {
			return array(
				'_id'     => substr( md5( $name ), 0, 7 ),
				'content' => __( 'Working with them was a joy from start to finish. Everything arrived on time and looked better than we imagined.', 'kbroo-studio' ),
				'name'    => $name,
				'title'   => $title,
			);
		};
		$style   = function ( string $section ) {
			return array(
				'tab'     => 'style',
				'section' => $section,
			);
		};
		$text    = '{{WRAPPER}} .kbroo-testimonial__content';
		$name    = '{{WRAPPER}} .kbroo-testimonial__name';
		$title   = '{{WRAPPER}} .kbroo-testimonial__title';
		$image   = '{{WRAPPER}} .kbroo-testimonial__image img';

		return array_merge(
			array(
				'slides'    => $content + array(
					'type'        => 'repeater',
					'label'       => __( 'Testimonials', 'kbroo-studio' ),
					'item_label'  => __( 'Testimonial', 'kbroo-studio' ),
					'title_field' => 'name',
					'default'     => array(
						$slide( 'Jane Doe', 'Bride' ),
						$slide( 'John Smith', 'Business owner' ),
						$slide( 'Sam Lee', 'Customer' ),
					),
					'fields'      => array(
						'content' => array(
							'type'    => 'textarea',
							'label'   => __( 'Testimonial', 'kbroo-studio' ),
							'default' => '',
							'rows'    => 5,
						),
						'image'   => array(
							'type'  => 'media',
							'label' => __( 'Photo', 'kbroo-studio' ),
						),
						'name'    => array(
							'type'    => 'text',
							'label'   => __( 'Name', 'kbroo-studio' ),
							'default' => '',
						),
						'title'   => array(
							'type'    => 'text',
							'label'   => __( 'Title', 'kbroo-studio' ),
							'default' => '',
						),
					),
				),
				'layout'    => $content + array(
					'type'    => 'select',
					'label'   => __( 'Layout', 'kbroo-studio' ),
					'default' => 'image_inline',
					'options' => array(
						'image_inline'  => __( 'Photo beside the name', 'kbroo-studio' ),
						'image_stacked' => __( 'Photo above the name', 'kbroo-studio' ),
						'image_above'   => __( 'Photo at the top', 'kbroo-studio' ),
						'image_left'    => __( 'Photo on the left', 'kbroo-studio' ),
						'image_right'   => __( 'Photo on the right', 'kbroo-studio' ),
					),
				),
				'alignment' => $content + array(
					'type'      => 'select',
					'label'     => __( 'Alignment', 'kbroo-studio' ),
					'default'   => 'center',
					'options'   => array(
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					// start/end work for text-align, justify-content and align-items alike.
					'selectors_dictionary' => array(
						'left'   => 'start',
						'center' => 'center',
						'right'  => 'end',
					),
					'selectors' => array( '{{WRAPPER}} .kbroo-testimonial' => 'text-align: {{VALUE}}; --kbroo-align: {{VALUE}};' ),
				),
			),
			self::carousel_controls( $options ),
			array(
				'content_color' => $style( __( 'Testimonial', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $text => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'content_typography', $text, $style( __( 'Testimonial', 'kbroo-studio' ) ) ),
			array(
				'name_color' => $style( __( 'Name', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $name => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'name_typography', $name, $style( __( 'Name', 'kbroo-studio' ) ) ),
			array(
				'title_color' => $style( __( 'Title', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $title => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'title_typography', $title, $style( __( 'Title', 'kbroo-studio' ) ) ),
			array(
				'image_size'     => $style( __( 'Photo', 'kbroo-studio' ) ) + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( $image => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ),
				),
				'image_gap'      => $style( __( 'Photo', 'kbroo-studio' ) ) + array(
					'type'      => 'slider',
					'label'     => __( 'Gap', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-testimonial' => '--kbroo-image-gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::border( $image, $style( __( 'Photo', 'kbroo-studio' ) ), 'image_border' ),
			array(
				'arrows_size'      => $style( __( 'Arrows and dots', 'kbroo-studio' ) ) + array(
					'type'      => 'slider',
					'label'     => __( 'Arrow size', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__arrow' => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
				'arrows_color'     => $style( __( 'Arrows and dots', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Arrow colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__arrow' => 'color: {{VALUE}};' ),
				),
				'pagination_color' => $style( __( 'Arrows and dots', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Dot colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-carousel__pagination' => '--kbroo-dot: {{VALUE}};' ),
				),
			)
		);
	}

	public function render( array $settings ): string {
		$layout = in_array( $settings['layout'], array( 'image_inline', 'image_stacked', 'image_above', 'image_left', 'image_right' ), true ) ? $settings['layout'] : 'image_inline';
		$slides = array();
		foreach ( (array) $settings['slides'] as $item ) {
			$photo = '';
			$url   = (string) ( $item['image']['url'] ?? '' );
			if ( '' !== $url ) {
				$id    = (int) ( $item['image']['id'] ?? 0 );
				$img   = $id ? wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => (string) ( $item['name'] ?? '' ) ) ) : '';
				$photo = '<div class="kbroo-testimonial__image">' . ( $img ? $img : '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( (string) ( $item['name'] ?? '' ) ) . '" loading="lazy" />' ) . '</div>';
			}
			$cite = '';
			if ( '' !== ( $item['name'] ?? '' ) ) {
				$cite .= '<span class="kbroo-testimonial__name">' . esc_html( $item['name'] ) . '</span>';
			}
			if ( '' !== ( $item['title'] ?? '' ) ) {
				$cite .= '<span class="kbroo-testimonial__title">' . esc_html( $item['title'] ) . '</span>';
			}
			$text     = '' !== ( $item['content'] ?? '' ) ? '<div class="kbroo-testimonial__content">' . nl2br( esc_html( $item['content'] ) ) . '</div>' : '';
			$footer   = '' !== $photo . $cite ? '<div class="kbroo-testimonial__footer">' . ( in_array( $layout, array( 'image_inline', 'image_stacked' ), true ) ? $photo : '' ) . ( '' !== $cite ? '<cite class="kbroo-testimonial__cite">' . $cite . '</cite>' : '' ) . '</div>' : '';
			$top      = in_array( $layout, array( 'image_above', 'image_left', 'image_right' ), true ) ? $photo : '';
			$slides[] = '<figure class="kbroo-testimonial kbroo-testimonial--' . $layout . '">' . $top . '<div class="kbroo-testimonial__body">' . $text . $footer . '</div></figure>';
		}
		if ( ! $slides ) {
			return '';
		}
		return self::carousel( $slides, $settings, __( 'Testimonials', 'kbroo-studio' ) );
	}
}
