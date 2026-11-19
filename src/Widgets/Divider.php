<?php
/**
 * Divider widget, a line with optional text in the middle. Settings match
 * Elementor's divider widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class Divider extends Widget {

	const TAGS = array( 'span', 'div', 'p', 'h2', 'h3', 'h4', 'h5', 'h6' );

	public function get_name(): string {
		return 'divider';
	}

	public function get_title(): string {
		return __( 'Divider', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-minus';
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Divider', 'kbroo-studio' ) );
		$style   = array(
			'tab'     => 'style',
			'section' => __( 'Divider', 'kbroo-studio' ),
		);
		$text    = array(
			'tab'       => 'style',
			'section'   => __( 'Text', 'kbroo-studio' ),
			'condition' => array( 'look' => 'line_text' ),
		);
		$wrap    = '{{WRAPPER}} .kbroo-divider';

		return array_merge(
			array(
				'style'      => $content + array(
					'type'      => 'select',
					'label'     => __( 'Style', 'kbroo-studio' ),
					'default'   => 'solid',
					'options'   => array(
						'solid'  => __( 'Solid', 'kbroo-studio' ),
						'double' => __( 'Double', 'kbroo-studio' ),
						'dotted' => __( 'Dotted', 'kbroo-studio' ),
						'dashed' => __( 'Dashed', 'kbroo-studio' ),
					),
					'selectors' => array( $wrap => '--kbroo-divider-style: {{VALUE}};' ),
				),
				'width'      => $content + array(
					'type'       => 'slider',
					'label'      => __( 'Width', 'kbroo-studio' ),
					'units'      => array( '%', 'px' ),
					'responsive' => true,
					'selectors'  => array( $wrap => 'width: {{SIZE}}{{UNIT}};' ),
				),
				'align'      => $content + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => '',
					'responsive'           => true,
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'left'   => '0 auto 0 0',
						'center' => '0 auto',
						'right'  => '0 0 0 auto',
					),
					'selectors'            => array( $wrap => 'margin: {{VALUE}};' ),
				),
				'look'       => $content + array(
					'type'    => 'select',
					'label'   => __( 'Add element', 'kbroo-studio' ),
					'default' => 'line',
					'options' => array(
						'line'      => __( 'None', 'kbroo-studio' ),
						'line_text' => __( 'Text', 'kbroo-studio' ),
					),
				),
				'text'       => $content + array(
					'type'      => 'text',
					'label'     => __( 'Text', 'kbroo-studio' ),
					'default'   => __( 'Divider', 'kbroo-studio' ),
					'condition' => array( 'look' => 'line_text' ),
				),
				'html_tag'   => $content + array(
					'type'      => 'select',
					'label'     => __( 'Text HTML tag', 'kbroo-studio' ),
					'default'   => 'span',
					'options'   => array_combine( self::TAGS, array_map( 'strtoupper', self::TAGS ) ),
					'condition' => array( 'look' => 'line_text' ),
				),
				'text_align' => $content + array(
					'type'      => 'select',
					'label'     => __( 'Text position', 'kbroo-studio' ),
					'default'   => 'center',
					'options'   => array(
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'condition' => array( 'look' => 'line_text' ),
				),
				'color'      => $style + array(
					'type'      => 'color',
					'label'     => __( 'Color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap => '--kbroo-divider-color: {{VALUE}};' ),
				),
				'weight'     => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Weight', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( $wrap => '--kbroo-divider-weight: {{SIZE}}{{UNIT}};' ),
				),
				'gap'        => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Gap above and below', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( $wrap => 'padding-top: {{SIZE}}{{UNIT}}; padding-bottom: {{SIZE}}{{UNIT}};' ),
				),
				'text_color' => $text + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-divider__text' => 'color: {{VALUE}};' ),
				),
				'text_spacing' => $text + array(
					'type'       => 'slider',
					'label'      => __( 'Space beside text', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-divider__text' => 'padding-left: {{SIZE}}{{UNIT}}; padding-right: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'typography', '{{WRAPPER}} .kbroo-divider__text', $text )
		);
	}

	public function render( array $settings ): string {
		if ( 'line_text' !== $settings['look'] || '' === trim( (string) $settings['text'] ) ) {
			return '<div class="kbroo-divider" role="separator"><span class="kbroo-divider__line"></span></div>';
		}
		$tag      = in_array( $settings['html_tag'], self::TAGS, true ) ? $settings['html_tag'] : 'span';
		$position = in_array( $settings['text_align'], array( 'left', 'center', 'right' ), true ) ? $settings['text_align'] : 'center';

		return sprintf(
			'<div class="kbroo-divider kbroo-divider--text kbroo-divider--text-%1$s"><span class="kbroo-divider__line"></span><%2$s class="kbroo-divider__text">%3$s</%2$s><span class="kbroo-divider__line"></span></div>',
			$position,
			$tag,
			esc_html( $settings['text'] )
		);
	}
}
