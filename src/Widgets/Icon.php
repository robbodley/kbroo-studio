<?php
/**
 * Icon widget. Settings match Elementor's icon widget (selected_icon, view,
 * shape, primary_color, ...). Previewed with HTML from the server, which
 * holds the icon set.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class Icon extends Widget {

	public function get_name(): string {
		return 'icon';
	}

	public function get_title(): string {
		return __( 'Icon', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-star-filled';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * Shared by Icon and other icon widgets: view, shape and colours for an
	 * icon at $el inside a .kbroo-icon-view--<view> wrapper.
	 */
	public static function view_controls( string $el, array $content, array $style, array $hover ): array {
		$framed_or_stacked = array( 'condition' => array( 'view!' => 'default' ) );
		$stacked           = '{{WRAPPER}} .kbroo-icon-view--stacked ' . $el;
		$outlined          = '{{WRAPPER}} .kbroo-icon-view--framed ' . $el . ', {{WRAPPER}} .kbroo-icon-view--default ' . $el;
		$h                 = ':hover';

		return array(
			'content' => array(
				'view'  => $content + array(
					'type'    => 'select',
					'label'   => __( 'View', 'kbroo-studio' ),
					'default' => 'default',
					'options' => array(
						'default' => __( 'Default', 'kbroo-studio' ),
						'stacked' => __( 'Stacked', 'kbroo-studio' ),
						'framed'  => __( 'Framed', 'kbroo-studio' ),
					),
				),
				'shape' => $content + $framed_or_stacked + array(
					'type'    => 'select',
					'label'   => __( 'Shape', 'kbroo-studio' ),
					'default' => 'circle',
					'options' => array(
						'circle' => __( 'Circle', 'kbroo-studio' ),
						'square' => __( 'Square', 'kbroo-studio' ),
					),
				),
			),
			'style'   => array(
				'primary_color'         => $style + array(
					'type'      => 'color',
					'label'     => __( 'Primary colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array(
						$stacked  => 'background-color: {{VALUE}};',
						$outlined => 'color: {{VALUE}}; border-color: {{VALUE}};',
					),
				),
				'secondary_color'       => $style + $framed_or_stacked + array(
					'type'      => 'color',
					'label'     => __( 'Secondary colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array(
						$stacked                                             => 'color: {{VALUE}};',
						'{{WRAPPER}} .kbroo-icon-view--framed ' . $el => 'background-color: {{VALUE}};',
					),
				),
				'hover_primary_color'   => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Primary colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array(
						$stacked . $h => 'background-color: {{VALUE}};',
						'{{WRAPPER}} .kbroo-icon-view--framed ' . $el . $h . ', {{WRAPPER}} .kbroo-icon-view--default ' . $el . $h => 'color: {{VALUE}}; border-color: {{VALUE}};',
					),
				),
				'hover_secondary_color' => $hover + $framed_or_stacked + array(
					'type'      => 'color',
					'label'     => __( 'Secondary colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array(
						$stacked . $h => 'color: {{VALUE}};',
						'{{WRAPPER}} .kbroo-icon-view--framed ' . $el . $h => 'background-color: {{VALUE}};',
					),
				),
			),
		);
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Icon', 'kbroo-studio' ) );
		$style   = array(
			'tab'     => 'style',
			'section' => __( 'Icon', 'kbroo-studio' ),
		);
		$hover   = array(
			'tab'     => 'style',
			'section' => __( 'Icon on hover', 'kbroo-studio' ),
		);
		$el      = '{{WRAPPER}} .kbroo-icon-el';
		$view    = self::view_controls( '.kbroo-icon-el', $content, $style, $hover );

		return array_merge(
			array(
				'selected_icon' => $content + array(
					'type'    => 'icons',
					'label'   => __( 'Icon', 'kbroo-studio' ),
					'default' => array(
						'value'   => 'fas fa-star',
						'library' => 'fa-solid',
					),
				),
			),
			$view['content'],
			array(
				'link'  => $content + array(
					'type'  => 'url',
					'label' => __( 'Link', 'kbroo-studio' ),
				),
				'align' => ControlGroups::align( '{{WRAPPER}} .kbroo-icon-wrapper', $content ),
			),
			$view['style'],
			array(
				'size'          => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', 'rem', 'vw' ),
					'responsive' => true,
					'selectors'  => array( $el => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
				'icon_padding'  => $style + array(
					'type'      => 'slider',
					'label'     => __( 'Padding', 'kbroo-studio' ),
					'units'     => array( 'px', 'em', 'rem' ),
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
			ControlGroups::hover_animation( $el, $hover )
		);
	}

	public function render( array $settings ): string {
		$svg = Icons::svg( $settings['selected_icon'] );
		if ( '' === $svg ) {
			return '';
		}
		$view  = in_array( $settings['view'], array( 'stacked', 'framed' ), true ) ? $settings['view'] : 'default';
		$shape = 'square' === $settings['shape'] ? 'square' : 'circle';
		$link  = self::link_attributes( $settings['link'] );
		$tag   = '' !== $link ? 'a' : 'span';
		return sprintf(
			'<div class="kbroo-icon-wrapper kbroo-icon-view--%1$s kbroo-icon-shape--%2$s"><%3$s class="kbroo-icon-el"%4$s>%5$s</%3$s></div>',
			$view,
			$shape,
			$tag,
			$link,
			$svg
		);
	}
}
