<?php
/**
 * Icon box widget: an icon with a title and description beside or above
 * it. Settings match Elementor's icon-box widget. The title, description
 * and layout controls are shared with the image box (see box_controls()).
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class IconBox extends Widget {

	const TITLE_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	public function get_name(): string {
		return 'icon-box';
	}

	public function get_title(): string {
		return __( 'Icon box', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-index-card';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * Title, description, link and layout controls shared by the icon box
	 * and image box. $media names the icon or image in labels.
	 *
	 * @return array{ content: array, layout: array, style: array }
	 */
	public static function box_controls( string $media_label ): array {
		$content = array( 'section' => $media_label );
		$box     = array(
			'tab'     => 'style',
			'section' => __( 'Box', 'kbroo-studio' ),
		);
		$text    = array(
			'tab'     => 'style',
			'section' => __( 'Title', 'kbroo-studio' ),
		);
		$about   = array(
			'tab'     => 'style',
			'section' => __( 'Description', 'kbroo-studio' ),
		);
		$title   = '{{WRAPPER}} .kbroo-box__title';
		$desc    = '{{WRAPPER}} .kbroo-box__description';

		return array(
			'content' => array(
				'title_text'       => $content + array(
					'type'    => 'textarea',
					'html'    => true,
					'rows'    => 2,
					'label'   => __( 'Title', 'kbroo-studio' ),
					'default' => __( 'This is the heading', 'kbroo-studio' ),
				),
				'description_text' => $content + array(
					'type'    => 'textarea',
					'html'    => true,
					'rows'    => 4,
					'label'   => __( 'Description', 'kbroo-studio' ),
					'default' => __( 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Ut elit tellus, luctus nec ullamcorper mattis, pulvinar dapibus leo.', 'kbroo-studio' ),
				),
				'link'             => $content + array(
					'type'  => 'url',
					'label' => __( 'Link', 'kbroo-studio' ),
				),
				'title_size'       => $content + array(
					'type'    => 'select',
					'label'   => __( 'Title HTML tag', 'kbroo-studio' ),
					'default' => 'h3',
					'options' => array_combine( self::TITLE_TAGS, array_map( 'strtoupper', self::TITLE_TAGS ) ),
				),
			),
			'layout'  => array(
				'position'                   => $box + array(
					/* translators: %s: "Icon" or "Image". */
					'label'      => sprintf( __( '%s position', 'kbroo-studio' ), $media_label ),
					'type'       => 'select',
					'default'    => 'top',
					'responsive' => true,
					'options'    => array(
						'top'   => __( 'Top', 'kbroo-studio' ),
						'left'  => __( 'Left', 'kbroo-studio' ),
						'right' => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						// Stacked, the media spans the box (an image box's image takes
						// the width); side by side, the media column takes it.
						'top'   => 'column; --kbroo-box-stack: stretch; --kbroo-box-media-width: auto; --kbroo-box-img-width: var(--kbroo-box-image-width, auto)',
						'left'  => 'row; --kbroo-box-stack: auto; --kbroo-box-media-width: var(--kbroo-box-image-width, 30%); --kbroo-box-img-width: 100%',
						'right' => 'row-reverse; --kbroo-box-stack: auto; --kbroo-box-media-width: var(--kbroo-box-image-width, 30%); --kbroo-box-img-width: 100%',
					),
					'selectors'  => array( '{{WRAPPER}} .kbroo-box' => '--kbroo-box-direction: {{VALUE}};' ),
				),
				'content_vertical_alignment' => $box + array(
					'type'      => 'select',
					'label'     => __( 'Vertical alignment', 'kbroo-studio' ),
					'default'   => 'top',
					'options'   => array(
						'top'    => __( 'Top', 'kbroo-studio' ),
						'middle' => __( 'Middle', 'kbroo-studio' ),
						'bottom' => __( 'Bottom', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'top'    => 'flex-start',
						'middle' => 'center',
						'bottom' => 'flex-end',
					),
					'selectors' => array( '{{WRAPPER}} .kbroo-box' => '--kbroo-box-align: {{VALUE}};' ),
				),
				'text_align'                 => ControlGroups::align( '{{WRAPPER}} .kbroo-box', $box ),
			),
			'style'   => array_merge(
				array(
					'title_bottom_space' => $text + array(
						'type'       => 'slider',
						'label'      => __( 'Space below title', 'kbroo-studio' ),
						'units'      => array( 'px', 'em' ),
						'responsive' => true,
						'selectors'  => array( $title => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
					),
					'title_color'        => $text + array(
						'type'      => 'color',
						'label'     => __( 'Colour', 'kbroo-studio' ),
						'default'   => '',
						'selectors' => array( $title => 'color: {{VALUE}};' ),
					),
					'hover_title_color'  => $text + array(
						'type'      => 'color',
						'label'     => __( 'Colour on hover', 'kbroo-studio' ),
						'default'   => '',
						'selectors' => array( '{{WRAPPER}} .kbroo-box:hover .kbroo-box__title' => 'color: {{VALUE}};' ),
					),
				),
				ControlGroups::typography( 'title_typography', $title, $text ),
				array(
					'description_color' => $about + array(
						'type'      => 'color',
						'label'     => __( 'Colour', 'kbroo-studio' ),
						'default'   => '',
						'selectors' => array( $desc => 'color: {{VALUE}};' ),
					),
				),
				ControlGroups::typography( 'description_typography', $desc, $about )
			),
		);
	}

	/**
	 * Box markup around $media (icon or image HTML).
	 */
	public static function box( string $media, array $settings, string $link_attributes ): string {
		$tag   = in_array( $settings['title_size'], self::TITLE_TAGS, true ) ? $settings['title_size'] : 'h3';
		$title = wp_kses( (string) $settings['title_text'], Heading::INLINE_HTML );
		$desc  = wp_kses_post( (string) $settings['description_text'] );
		if ( '' !== $link_attributes && '' !== $title ) {
			$title = '<a' . $link_attributes . '>' . $title . '</a>';
		}
		$body = '';
		if ( '' !== $title ) {
			$body .= sprintf( '<%1$s class="kbroo-box__title">%2$s</%1$s>', $tag, $title );
		}
		if ( '' !== trim( $desc ) ) {
			$body .= '<p class="kbroo-box__description">' . $desc . '</p>';
		}
		return '<div class="kbroo-box">'
			. ( '' !== $media ? '<div class="kbroo-box__media">' . $media . '</div>' : '' )
			. ( '' !== $body ? '<div class="kbroo-box__content">' . $body . '</div>' : '' )
			. '</div>';
	}

	protected function widget_controls(): array {
		$label   = __( 'Icon', 'kbroo-studio' );
		$content = array( 'section' => $label );
		$style   = array(
			'tab'     => 'style',
			'section' => $label,
		);
		$hover   = array(
			'tab'     => 'style',
			'section' => __( 'Icon on hover', 'kbroo-studio' ),
		);
		$el      = '{{WRAPPER}} .kbroo-icon-el';
		$view    = Icon::view_controls( '.kbroo-icon-el', $content, $style, $hover );
		$box     = self::box_controls( $label );

		return array_merge(
			array(
				'selected_icon' => $content + array(
					'type'    => 'icons',
					'label'   => $label,
					'default' => array(
						'value'   => 'fas fa-star',
						'library' => 'fa-solid',
					),
				),
			),
			$view['content'],
			$box['content'],
			$box['layout'],
			array(
				'icon_space' => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Spacing', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-box' => '--kbroo-box-gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			$view['style'],
			array(
				'icon_size'     => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', 'rem' ),
					'responsive' => true,
					'selectors'  => array( $el => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
				'icon_padding'  => $style + array(
					'type'      => 'slider',
					'label'     => __( 'Padding', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'condition' => array( 'view!' => 'default' ),
					'selectors' => array( $el => 'padding: {{SIZE}}{{UNIT}};' ),
				),
				'rotate'        => $style + array(
					'type'      => 'slider',
					'label'     => __( 'Rotate', 'kbroo-studio' ),
					'units'     => array( 'deg' ),
					'selectors' => array( $el . ' .kbroo-icon' => 'transform: rotate({{SIZE}}{{UNIT}});' ),
				),
				'border_width'  => $style + array(
					'type'      => 'dimensions',
					'label'     => __( 'Border width', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'condition' => array( 'view' => 'framed' ),
					'selectors' => array( $el => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'border_radius' => $style + array(
					'type'      => 'dimensions',
					'label'     => __( 'Border radius', 'kbroo-studio' ),
					'units'     => array( 'px', '%', 'em' ),
					'condition' => array( 'view!' => 'default' ),
					'selectors' => array( $el => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
			),
			ControlGroups::hover_animation( $el, $hover ),
			$box['style']
		);
	}

	public function render( array $settings ): string {
		$svg  = Icons::svg( $settings['selected_icon'] );
		$link = self::link_attributes( $settings['link'] );
		$icon = '';
		if ( '' !== $svg ) {
			$view  = in_array( $settings['view'], array( 'stacked', 'framed' ), true ) ? $settings['view'] : 'default';
			$shape = 'square' === $settings['shape'] ? 'square' : 'circle';
			// The title carries the link for screen readers; the icon repeats it for the mouse.
			$tag  = '' !== $link ? 'a' : 'span';
			$icon = sprintf(
				'<div class="kbroo-icon-wrapper kbroo-icon-view--%1$s kbroo-icon-shape--%2$s"><%3$s class="kbroo-icon-el"%4$s>%5$s</%3$s></div>',
				$view,
				$shape,
				$tag,
				'' !== $link ? $link . ' tabindex="-1" aria-hidden="true"' : '',
				$svg
			);
		}
		return self::box( $icon, $settings, $link );
	}
}
