<?php
/**
 * Reusable sets of controls (typography, border, background, the Advanced tab).
 *
 * Setting names follow Elementor's group controls, e.g. typography_font_size,
 * border_border, border_width, background_color.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class ControlGroups {

	/**
	 * Typography controls applied to $selector.
	 *
	 * @param string $prefix   Setting name prefix, e.g. "typography".
	 * @param string $selector CSS selector, using {{WRAPPER}}.
	 * @param array  $base     Keys merged into every control (tab, section, condition).
	 */
	public static function typography( string $prefix, string $selector, array $base = array() ): array {
		$base = array_merge(
			array(
				'tab'     => 'style',
				'section' => __( 'Typography', 'kbroo-studio' ),
			),
			$base
		);

		$group    = $prefix . '_typography';
		$controls = array(
			$prefix . '_font_family'    => $base + array(
				'type'                 => 'select',
				'label'                => __( 'Font family', 'kbroo-studio' ),
				'default'              => '',
				'options'              => Fonts::options(),
				'selectors_dictionary' => Fonts::stacks(),
				'selectors'            => array( $selector => 'font-family: {{VALUE}};' ),
				'global_property'      => 'font-family',
			),
			$prefix . '_font_size'      => $base + array(
				'type'       => 'slider',
				'label'      => __( 'Font size', 'kbroo-studio' ),
				'units'      => array( 'px', 'em', 'rem', 'vw' ),
				'responsive' => true,
				'selectors'  => array( $selector => 'font-size: {{SIZE}}{{UNIT}};' ),
				'global_property' => 'font-size',
			),
			$prefix . '_font_weight'    => $base + array(
				'type'      => 'select',
				'label'     => __( 'Weight', 'kbroo-studio' ),
				'default'   => '',
				'options'   => array(
					''       => __( 'Default', 'kbroo-studio' ),
					'300'    => '300',
					'400'    => '400',
					'500'    => '500',
					'600'    => '600',
					'700'    => '700',
					'800'    => '800',
					'normal' => __( 'Normal', 'kbroo-studio' ),
					'bold'   => __( 'Bold', 'kbroo-studio' ),
				),
				'selectors' => array( $selector => 'font-weight: {{VALUE}};' ),
				'global_property' => 'font-weight',
			),
			$prefix . '_text_transform' => $base + array(
				'type'      => 'select',
				'label'     => __( 'Transform', 'kbroo-studio' ),
				'default'   => '',
				'options'   => array(
					''           => __( 'Default', 'kbroo-studio' ),
					'uppercase'  => __( 'Uppercase', 'kbroo-studio' ),
					'lowercase'  => __( 'Lowercase', 'kbroo-studio' ),
					'capitalize' => __( 'Capitalize', 'kbroo-studio' ),
					'none'       => __( 'Normal', 'kbroo-studio' ),
				),
				'selectors' => array( $selector => 'text-transform: {{VALUE}};' ),
				'global_property' => 'text-transform',
			),
			$prefix . '_line_height'    => $base + array(
				'type'       => 'slider',
				'label'      => __( 'Line height', 'kbroo-studio' ),
				'units'      => array( 'em', 'px' ),
				'responsive' => true,
				'selectors'  => array( $selector => 'line-height: {{SIZE}}{{UNIT}};' ),
				'global_property' => 'line-height',
			),
			$prefix . '_letter_spacing' => $base + array(
				'type'       => 'slider',
				'label'      => __( 'Letter spacing', 'kbroo-studio' ),
				'units'      => array( 'px', 'em' ),
				'responsive' => true,
				'selectors'  => array( $selector => 'letter-spacing: {{SIZE}}{{UNIT}};' ),
				'global_property' => 'letter-spacing',
			),
		);

		// Every property can come from a global font linked under
		// __globals__[ "<prefix>_typography" ]; the element's own values win.
		foreach ( $controls as $key => $control ) {
			$controls[ $key ]['global_group'] = $group;
		}
		return $controls;
	}

	/**
	 * Border controls applied to $selector.
	 *
	 * @param string $selector CSS selector, using {{WRAPPER}}.
	 * @param array  $base     Keys merged into every control.
	 * @param string $prefix   Setting name prefix: "border" gives border_border,
	 *                         border_width, border_color and border_radius, as in
	 *                         Elementor; the image widget uses "image_border".
	 */
	public static function border( string $selector, array $base = array(), string $prefix = 'border' ): array {
		$base = array_merge(
			array(
				'tab'     => 'style',
				'section' => __( 'Border', 'kbroo-studio' ),
			),
			$base
		);
		$when = array( 'condition' => array_merge( $base['condition'] ?? array(), array( $prefix . '_border!' => '' ) ) );

		return array(
			$prefix . '_border' => $base + array(
				'type'      => 'select',
				'label'     => __( 'Border type', 'kbroo-studio' ),
				'default'   => '',
				'options'   => array(
					''       => __( 'None', 'kbroo-studio' ),
					'solid'  => __( 'Solid', 'kbroo-studio' ),
					'dashed' => __( 'Dashed', 'kbroo-studio' ),
					'dotted' => __( 'Dotted', 'kbroo-studio' ),
					'double' => __( 'Double', 'kbroo-studio' ),
				),
				'selectors' => array( $selector => 'border-style: {{VALUE}};' ),
			),
			$prefix . '_width'  => $when + $base + array(
				'type'       => 'dimensions',
				'label'      => __( 'Border width', 'kbroo-studio' ),
				'units'      => array( 'px' ),
				'responsive' => true,
				'selectors'  => array( $selector => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			),
			$prefix . '_color'  => $when + $base + array(
				'type'      => 'color',
				'label'     => __( 'Border color', 'kbroo-studio' ),
				'default'   => '',
				'selectors' => array( $selector => 'border-color: {{VALUE}};' ),
			),
			$prefix . '_radius' => $base + array(
				'type'       => 'dimensions',
				'label'      => __( 'Border radius', 'kbroo-studio' ),
				'units'      => array( 'px', '%' ),
				'responsive' => true,
				'selectors'  => array( $selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			),
		);
	}

	/**
	 * Background color. Images, gradients and video come with the
	 * Backgrounds feature.
	 */
	public static function background( string $selector, array $base = array() ): array {
		$base = array_merge(
			array(
				'tab'     => 'style',
				'section' => __( 'Background', 'kbroo-studio' ),
			),
			$base
		);
		return array(
			'background_color' => $base + array(
				'type'      => 'color',
				'label'     => __( 'Background color', 'kbroo-studio' ),
				'default'   => '',
				'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
			),
		);
	}

	/**
	 * The Advanced tab every element has.
	 *
	 * @param string $prefix         '_' for widgets (Elementor's naming), '' for sections and columns.
	 * @param string $spacing_target Selector that margin and padding apply to.
	 * @param array  $padding_default Default padding value, or null for none.
	 */
	public static function advanced( string $prefix, string $spacing_target = '{{WRAPPER}}', $padding_default = null ): array {
		$layout  = array(
			'tab'     => 'advanced',
			'section' => __( 'Layout', 'kbroo-studio' ),
		);
		$padding = $layout + array(
			'type'       => 'dimensions',
			'label'      => __( 'Padding', 'kbroo-studio' ),
			'units'      => array( 'px', '%', 'em', 'rem' ),
			'responsive' => true,
			'selectors'  => array( $spacing_target => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
		);
		if ( null !== $padding_default ) {
			$padding['default'] = $padding_default;
		}

		$visibility = array(
			'tab'     => 'advanced',
			'section' => __( 'Responsive', 'kbroo-studio' ),
			'type'    => 'switcher',
			'default' => '',
		);

		return array(
			$prefix . 'margin'      => $layout + array(
				'type'       => 'dimensions',
				'label'      => __( 'Margin', 'kbroo-studio' ),
				'units'      => array( 'px', '%', 'em', 'rem' ),
				'responsive' => true,
				'selectors'  => array( '{{WRAPPER}}' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
			),
			$prefix . 'padding'     => $padding,
			$prefix . 'element_id'  => $layout + array(
				'type'     => 'text',
				'label'    => __( 'CSS ID', 'kbroo-studio' ),
				'default'  => '',
				'sanitize' => 'id',
			),
			$prefix . 'css_classes' => $layout + array(
				'type'     => 'text',
				'label'    => __( 'CSS classes', 'kbroo-studio' ),
				'default'  => '',
				'sanitize' => 'classes',
			),
			'hide_desktop'          => $visibility + array( 'label' => __( 'Hide on desktop', 'kbroo-studio' ) ),
			'hide_tablet'           => $visibility + array( 'label' => __( 'Hide on tablet', 'kbroo-studio' ) ),
			'hide_mobile'           => $visibility + array( 'label' => __( 'Hide on mobile', 'kbroo-studio' ) ),
		);
	}

	/**
	 * Responsive text alignment.
	 */
	public static function align( string $selector, array $base = array() ): array {
		return $base + array(
			'type'       => 'select',
			'label'      => __( 'Alignment', 'kbroo-studio' ),
			'default'    => '',
			'responsive' => true,
			'options'    => array(
				''        => __( 'Default', 'kbroo-studio' ),
				'left'    => __( 'Left', 'kbroo-studio' ),
				'center'  => __( 'Center', 'kbroo-studio' ),
				'right'   => __( 'Right', 'kbroo-studio' ),
				'justify' => __( 'Justified', 'kbroo-studio' ),
			),
			'selectors'  => array( $selector => 'text-align: {{VALUE}};' ),
		);
	}
}
