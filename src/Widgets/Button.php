<?php
/**
 * Button widget. Settings match Elementor's button widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class Button extends Widget {

	const SIZES = array( 'xs', 'sm', 'md', 'lg', 'xl' );

	public function get_name(): string {
		return 'button';
	}

	public function get_title(): string {
		return __( 'Button', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-button';
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Button', 'kbroo-studio' ) );
		$normal  = array(
			'tab'     => 'style',
			'section' => __( 'Button', 'kbroo-studio' ),
		);
		$hover   = array(
			'tab'     => 'style',
			'section' => __( 'Button on hover', 'kbroo-studio' ),
		);
		$button  = '{{WRAPPER}} .kbroo-button';
		$hovered = '{{WRAPPER}} .kbroo-button:hover, {{WRAPPER}} .kbroo-button:focus-visible';

		return array_merge(
			array(
				'text'  => $content + array(
					'type'    => 'text',
					'label'   => __( 'Text', 'kbroo-studio' ),
					'default' => __( 'Click here', 'kbroo-studio' ),
				),
				'link'  => $content + array(
					'type'  => 'url',
					'label' => __( 'Link', 'kbroo-studio' ),
				),
				'align' => $content + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => '',
					'responsive'           => true,
					'options'              => array(
						''        => __( 'Default', 'kbroo-studio' ),
						'left'    => __( 'Left', 'kbroo-studio' ),
						'center'  => __( 'Center', 'kbroo-studio' ),
						'right'   => __( 'Right', 'kbroo-studio' ),
						'justify' => __( 'Full width', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'left'    => 'flex-start',
						'center'  => 'center',
						'right'   => 'flex-end',
						'justify' => 'stretch',
					),
					'selectors'            => array( '{{WRAPPER}} .kbroo-button-wrapper' => 'align-items: {{VALUE}};' ),
				),
				'size'  => $content + array(
					'type'    => 'select',
					'label'   => __( 'Size', 'kbroo-studio' ),
					'default' => 'sm',
					'options' => array(
						'xs' => __( 'Extra small', 'kbroo-studio' ),
						'sm' => __( 'Small', 'kbroo-studio' ),
						'md' => __( 'Medium', 'kbroo-studio' ),
						'lg' => __( 'Large', 'kbroo-studio' ),
						'xl' => __( 'Extra large', 'kbroo-studio' ),
					),
				),
			),
			ControlGroups::typography( 'typography', $button ),
			array(
				'button_text_color'             => $normal + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $button => 'color: {{VALUE}};' ),
				),
				'background_color'              => $normal + array(
					'type'      => 'color',
					'label'     => __( 'Background color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $button => 'background-color: {{VALUE}};' ),
				),
				'text_padding'                  => $normal + array(
					'type'       => 'dimensions',
					'label'      => __( 'Padding', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', '%' ),
					'responsive' => true,
					'selectors'  => array( $button => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'hover_color'                   => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $hovered => 'color: {{VALUE}};' ),
				),
				'button_background_hover_color' => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Background color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $hovered => 'background-color: {{VALUE}};' ),
				),
				'button_hover_border_color'     => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Border color', 'kbroo-studio' ),
					'default'   => '',
					'condition' => array( 'border_border!' => '' ),
					'selectors' => array( $hovered => 'border-color: {{VALUE}};' ),
				),
			),
			ControlGroups::border( $button )
		);
	}

	public function render( array $settings ): string {
		$size = in_array( $settings['size'], self::SIZES, true ) ? $settings['size'] : 'sm';
		$link = self::link_attributes( $settings['link'] );
		$tag  = '' === $link ? 'span' : 'a';

		return sprintf(
			'<div class="kbroo-button-wrapper"><%1$s class="kbroo-button kbroo-button--%2$s"%3$s><span class="kbroo-button__text">%4$s</span></%1$s></div>',
			$tag,
			$size,
			$link,
			esc_html( $settings['text'] )
		);
	}
}
