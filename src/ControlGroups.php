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
			$prefix . '_font_style'     => $base + array(
				'type'            => 'select',
				'label'           => __( 'Style', 'kbroo-studio' ),
				'default'         => '',
				'options'         => array(
					''        => __( 'Default', 'kbroo-studio' ),
					'normal'  => __( 'Normal', 'kbroo-studio' ),
					'italic'  => __( 'Italic', 'kbroo-studio' ),
					'oblique' => __( 'Oblique', 'kbroo-studio' ),
				),
				'selectors'       => array( $selector => 'font-style: {{VALUE}};' ),
				'global_property' => 'font-style',
			),
			$prefix . '_text_decoration' => $base + array(
				'type'            => 'select',
				'label'           => __( 'Decoration', 'kbroo-studio' ),
				'default'         => '',
				'options'         => array(
					''             => __( 'Default', 'kbroo-studio' ),
					'underline'    => __( 'Underline', 'kbroo-studio' ),
					'overline'     => __( 'Overline', 'kbroo-studio' ),
					'line-through' => __( 'Line through', 'kbroo-studio' ),
					'none'         => __( 'None', 'kbroo-studio' ),
				),
				'selectors'       => array( $selector => 'text-decoration: {{VALUE}};' ),
				'global_property' => 'text-decoration',
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
	 * Background controls in Elementor's shape: a type (classic colour and
	 * image, gradient or video) and its settings, e.g. background_background,
	 * background_color, background_image, background_gradient_angle.
	 *
	 * @param string $selector CSS selector, using {{WRAPPER}}.
	 * @param array  $base     Keys merged into every control.
	 * @param string $prefix   Setting name prefix ("background", "_background"
	 *                         for widgets, "background_overlay").
	 * @param array  $args     types: background types offered (classic,
	 *                         gradient, video); hover: selector for a second
	 *                         set of controls applied on hover ("<prefix>_hover").
	 */
	public static function background( string $selector, array $base = array(), string $prefix = 'background', array $args = array() ): array {
		$args = array_merge(
			array(
				'types' => array( 'classic', 'gradient' ),
				'hover' => null,
			),
			$args
		);
		$base = array_merge(
			array(
				'tab'     => 'style',
				'section' => __( 'Background', 'kbroo-studio' ),
			),
			$base
		);

		$controls = self::background_set( $selector, $base, $prefix, $args['types'] );
		if ( $args['hover'] ) {
			$hover    = array( 'section' => $base['section'] . ' ' . __( 'on hover', 'kbroo-studio' ) ) + $base;
			$controls = array_merge(
				$controls,
				self::background_set( $args['hover'], $hover, $prefix . '_hover', array_diff( $args['types'], array( 'video' ) ) ),
				array(
					$prefix . '_hover_transition' => $hover + array(
						'type'      => 'slider',
						'label'     => __( 'Transition duration (s)', 'kbroo-studio' ),
						'units'     => array( 's' ),
						'condition' => array( $prefix . '_hover_background' => array( 'classic', 'gradient' ) ),
						'selectors' => array( $selector => 'transition: background {{SIZE}}s, border {{SIZE}}s, border-radius {{SIZE}}s, box-shadow {{SIZE}}s;' ),
					),
				)
			);
		}
		return $controls;
	}

	/**
	 * One background group (normal or hover).
	 *
	 * @param string[] $types Background types offered.
	 */
	private static function background_set( string $selector, array $base, string $p, array $types ): array {
		$type_labels = array(
			'classic'  => __( 'Colour or image', 'kbroo-studio' ),
			'gradient' => __( 'Gradient', 'kbroo-studio' ),
			'video'    => __( 'Video', 'kbroo-studio' ),
		);
		$options     = array( '' => __( 'None', 'kbroo-studio' ) );
		foreach ( $types as $type ) {
			$options[ $type ] = $type_labels[ $type ];
		}

		$type     = $p . '_background';
		$colored  = array( 'condition' => array_merge( $base['condition'] ?? array(), array( $type => array( 'classic', 'gradient' ) ) ) ) + $base;
		$classic  = array( 'condition' => array_merge( $base['condition'] ?? array(), array( $type => 'classic' ) ) ) + $base;
		$gradient = array( 'condition' => array_merge( $base['condition'] ?? array(), array( $type => 'gradient' ) ) ) + $base;
		$stops    = "{{{$p}_color.VALUE}} {{{$p}_color_stop.SIZE}}{{{$p}_color_stop.UNIT}}, {{{$p}_color_b.VALUE}} {{{$p}_color_b_stop.SIZE}}{{{$p}_color_b_stop.UNIT}}";

		$positions = array(
			''              => __( 'Default', 'kbroo-studio' ),
			'center center' => __( 'Center center', 'kbroo-studio' ),
			'center left'   => __( 'Center left', 'kbroo-studio' ),
			'center right'  => __( 'Center right', 'kbroo-studio' ),
			'top center'    => __( 'Top center', 'kbroo-studio' ),
			'top left'      => __( 'Top left', 'kbroo-studio' ),
			'top right'     => __( 'Top right', 'kbroo-studio' ),
			'bottom center' => __( 'Bottom center', 'kbroo-studio' ),
			'bottom left'   => __( 'Bottom left', 'kbroo-studio' ),
			'bottom right'  => __( 'Bottom right', 'kbroo-studio' ),
		);

		$controls = array(
			$type                    => $base + array(
				'type'    => 'select',
				'label'   => __( 'Background type', 'kbroo-studio' ),
				'default' => '',
				'options' => $options,
			),
			$p . '_color'            => $colored + array(
				'type'      => 'color',
				'label'     => __( 'Colour', 'kbroo-studio' ),
				'default'   => '',
				'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
			),
		);

		if ( in_array( 'gradient', $types, true ) ) {
			$linear   = array( 'condition' => $gradient['condition'] + array( $p . '_gradient_type' => 'linear' ) ) + $gradient;
			$radial   = array( 'condition' => $gradient['condition'] + array( $p . '_gradient_type' => 'radial' ) ) + $gradient;
			$controls = array_merge(
				$controls,
				array(
					$p . '_color_stop'        => $gradient + array(
						'type'    => 'slider',
						'label'   => __( 'Location', 'kbroo-studio' ),
						'units'   => array( '%' ),
						'default' => array(
							'unit' => '%',
							'size' => 0,
						),
					),
					$p . '_color_b'           => $gradient + array(
						'type'    => 'color',
						'label'   => __( 'Second colour', 'kbroo-studio' ),
						'default' => '#f2295b',
					),
					$p . '_color_b_stop'      => $gradient + array(
						'type'    => 'slider',
						'label'   => __( 'Location', 'kbroo-studio' ),
						'units'   => array( '%' ),
						'default' => array(
							'unit' => '%',
							'size' => 100,
						),
					),
					$p . '_gradient_type'     => $gradient + array(
						'type'    => 'select',
						'label'   => __( 'Gradient type', 'kbroo-studio' ),
						'default' => 'linear',
						'options' => array(
							'linear' => __( 'Linear', 'kbroo-studio' ),
							'radial' => __( 'Radial', 'kbroo-studio' ),
						),
					),
					$p . '_gradient_angle'    => $linear + array(
						'type'      => 'slider',
						'label'     => __( 'Angle', 'kbroo-studio' ),
						'units'     => array( 'deg' ),
						'default'   => array(
							'unit' => 'deg',
							'size' => 180,
						),
						'selectors' => array( $selector => "background-color: transparent; background-image: linear-gradient({{SIZE}}{{UNIT}}, {$stops});" ),
					),
					$p . '_gradient_position' => $radial + array(
						'type'      => 'select',
						'label'     => __( 'Position', 'kbroo-studio' ),
						'default'   => 'center center',
						'options'   => array_slice( $positions, 1 ),
						'selectors' => array( $selector => "background-color: transparent; background-image: radial-gradient(at {{VALUE}}, {$stops});" ),
					),
				)
			);
		}

		$custom_position = array( 'condition' => $classic['condition'] + array( $p . '_position' => 'initial' ) ) + $classic;
		$custom_size     = array( 'condition' => $classic['condition'] + array( $p . '_size' => 'initial' ) ) + $classic;

		$controls = array_merge(
			$controls,
			array(
				$p . '_image'      => $classic + array(
					'type'       => 'media',
					'label'      => __( 'Image', 'kbroo-studio' ),
					'responsive' => true,
					'selectors'  => array( $selector => 'background-image: {{URL}};' ),
				),
				$p . '_position'   => $classic + array(
					'type'       => 'select',
					'label'      => __( 'Position', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => $positions + array( 'initial' => __( 'Custom', 'kbroo-studio' ) ),
					'selectors'  => array( $selector => 'background-position: {{VALUE}};' ),
				),
				$p . '_xpos'       => $custom_position + array(
					'type'       => 'slider',
					'label'      => __( 'X position', 'kbroo-studio' ),
					'units'      => array( 'px', '%', 'em', 'vw' ),
					'responsive' => true,
					'default'    => array(
						'unit' => 'px',
						'size' => 0,
					),
					'selectors'  => array( $selector => "background-position: {{SIZE}}{{UNIT}} {{{$p}_ypos.SIZE}}{{{$p}_ypos.UNIT}};" ),
				),
				$p . '_ypos'       => $custom_position + array(
					'type'       => 'slider',
					'label'      => __( 'Y position', 'kbroo-studio' ),
					'units'      => array( 'px', '%', 'em', 'vh' ),
					'responsive' => true,
					'default'    => array(
						'unit' => 'px',
						'size' => 0,
					),
					'selectors'  => array( $selector => "background-position: {{{$p}_xpos.SIZE}}{{{$p}_xpos.UNIT}} {{SIZE}}{{UNIT}};" ),
				),
				$p . '_attachment' => $classic + array(
					'type'      => 'select',
					'label'     => __( 'Attachment', 'kbroo-studio' ),
					'default'   => '',
					'options'   => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'scroll' => __( 'Scroll', 'kbroo-studio' ),
						'fixed'  => __( 'Fixed', 'kbroo-studio' ),
					),
					'selectors' => array( $selector => 'background-attachment: {{VALUE}};' ),
				),
				$p . '_repeat'     => $classic + array(
					'type'       => 'select',
					'label'      => __( 'Repeat', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''          => __( 'Default', 'kbroo-studio' ),
						'no-repeat' => __( 'No repeat', 'kbroo-studio' ),
						'repeat'    => __( 'Repeat', 'kbroo-studio' ),
						'repeat-x'  => __( 'Repeat across', 'kbroo-studio' ),
						'repeat-y'  => __( 'Repeat down', 'kbroo-studio' ),
					),
					'selectors'  => array( $selector => 'background-repeat: {{VALUE}};' ),
				),
				$p . '_size'       => $classic + array(
					'type'       => 'select',
					'label'      => __( 'Display size', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''        => __( 'Default', 'kbroo-studio' ),
						'auto'    => __( 'Auto', 'kbroo-studio' ),
						'cover'   => __( 'Cover', 'kbroo-studio' ),
						'contain' => __( 'Contain', 'kbroo-studio' ),
						'initial' => __( 'Custom', 'kbroo-studio' ),
					),
					'selectors'  => array( $selector => 'background-size: {{VALUE}};' ),
				),
				$p . '_bg_width'   => $custom_size + array(
					'type'       => 'slider',
					'label'      => __( 'Width', 'kbroo-studio' ),
					'units'      => array( '%', 'px', 'vw' ),
					'responsive' => true,
					'default'    => array(
						'unit' => '%',
						'size' => 100,
					),
					'selectors'  => array( $selector => 'background-size: {{SIZE}}{{UNIT}} auto;' ),
				),
			)
		);

		if ( in_array( 'video', $types, true ) ) {
			$video    = array( 'condition' => array_merge( $base['condition'] ?? array(), array( $type => 'video' ) ) ) + $base;
			$controls = array_merge(
				$controls,
				array(
					$p . '_video_link'     => $video + array(
						'type'        => 'text',
						'label'       => __( 'Video link', 'kbroo-studio' ),
						'default'     => '',
						'description' => __( 'A YouTube or Vimeo link, or the address of an MP4 file.', 'kbroo-studio' ),
					),
					$p . '_video_start'    => $video + array(
						'type'  => 'number',
						'label' => __( 'Start time (seconds)', 'kbroo-studio' ),
						'min'   => 0,
					),
					$p . '_video_end'      => $video + array(
						'type'  => 'number',
						'label' => __( 'End time (seconds)', 'kbroo-studio' ),
						'min'   => 0,
					),
					$p . '_play_once'      => $video + array(
						'type'  => 'switcher',
						'label' => __( 'Play once', 'kbroo-studio' ),
					),
					$p . '_play_on_mobile' => $video + array(
						'type'  => 'switcher',
						'label' => __( 'Play on mobile', 'kbroo-studio' ),
					),
					$p . '_video_fallback' => $video + array(
						'type'        => 'media',
						'label'       => __( 'Fallback image', 'kbroo-studio' ),
						'description' => __( 'Shown while the video loads, and on phones unless it plays there.', 'kbroo-studio' ),
						'selectors'   => array( $selector => 'background: {{URL}} 50% 50%; background-size: cover;' ),
					),
				)
			);
		}

		return $controls;
	}

	/**
	 * Background overlay: a layer over the element's background and under
	 * its content, drawn by $selector (a ::before pseudo-element).
	 *
	 * @param string $selector       Overlay selector, e.g. "{{WRAPPER}}::before".
	 * @param string $hover_selector The same on hover.
	 */
	public static function overlay( string $selector, string $hover_selector ): array {
		$base    = array(
			'tab'     => 'style',
			'section' => __( 'Background overlay', 'kbroo-studio' ),
		);
		$hover   = array( 'section' => __( 'Background overlay on hover', 'kbroo-studio' ) ) + $base;
		$has     = array( 'condition' => array( 'background_overlay_background' => array( 'classic', 'gradient' ) ) );
		$has_hov = array( 'condition' => array( 'background_overlay_hover_background' => array( 'classic', 'gradient' ) ) );

		$opacity = array(
			'type'    => 'slider',
			'label'   => __( 'Opacity', 'kbroo-studio' ),
			'units'   => array( 'px' ),
			'min'     => 0,
			'max'     => 1,
			'step'    => 0.01,
			'default' => array(
				'unit' => 'px',
				'size' => 0.5,
			),
		);

		return array_merge(
			self::background( $selector, $base, 'background_overlay' ),
			array(
				'background_overlay_opacity' => $has + $base + $opacity + array(
					'selectors' => array( $selector => 'opacity: {{SIZE}};' ),
				),
				'overlay_blend_mode'         => $has + $base + array(
					'type'      => 'select',
					'label'     => __( 'Blend mode', 'kbroo-studio' ),
					'default'   => '',
					'options'   => array(
						''            => __( 'Normal', 'kbroo-studio' ),
						'multiply'    => 'Multiply',
						'screen'      => 'Screen',
						'overlay'     => 'Overlay',
						'darken'      => 'Darken',
						'lighten'     => 'Lighten',
						'color-dodge' => 'Color dodge',
						'saturation'  => 'Saturation',
						'color'       => 'Color',
						'luminosity'  => 'Luminosity',
					),
					'selectors' => array( $selector => 'mix-blend-mode: {{VALUE}};' ),
				),
			),
			self::background( $hover_selector, $hover, 'background_overlay_hover' ),
			array(
				'background_overlay_hover_opacity'    => $has_hov + $hover + $opacity + array(
					'selectors' => array( $hover_selector => 'opacity: {{SIZE}};' ),
				),
				'background_overlay_hover_transition' => $hover + array(
					'type'      => 'slider',
					'label'     => __( 'Transition duration (s)', 'kbroo-studio' ),
					'units'     => array( 's' ),
					'selectors' => array( $selector => 'transition: background {{SIZE}}s, border-radius {{SIZE}}s, opacity {{SIZE}}s;' ),
				),
			)
		);
	}

	/**
	 * CSS filters (Elementor's css_filters group): blur, brightness,
	 * contrast, saturation and hue, applied when the type is "custom".
	 *
	 * @param string $prefix "css_filters" or "css_filters_hover".
	 */
	public static function css_filters( string $selector, array $base, string $prefix = 'css_filters' ): array {
		$on     = array( 'condition' => array( $prefix . '_css_filter' => 'custom' ) ) + $base;
		$slider = function ( string $label, $default, int $max, string $unit = 'px' ) use ( $on ) {
			return $on + array(
				'type'    => 'slider',
				'label'   => $label,
				'units'   => array( $unit ),
				'min'     => 0,
				'max'     => $max,
				'default' => array(
					'unit' => $unit,
					'size' => $default,
				),
			);
		};
		$p      = $prefix;
		return array(
			$p . '_css_filter' => $base + array(
				'type'      => 'select',
				'label'     => __( 'CSS filters', 'kbroo-studio' ),
				'default'   => '',
				'options'   => array(
					''       => __( 'None', 'kbroo-studio' ),
					'custom' => __( 'Custom', 'kbroo-studio' ),
				),
				'selectors' => array(
					$selector => "filter: brightness({{{$p}_brightness.SIZE}}%) contrast({{{$p}_contrast.SIZE}}%) saturate({{{$p}_saturate.SIZE}}%) blur({{{$p}_blur.SIZE}}px) hue-rotate({{{$p}_hue.SIZE}}deg);",
				),
			),
			$p . '_blur'       => $slider( __( 'Blur', 'kbroo-studio' ), 0, 10 ),
			$p . '_brightness' => $slider( __( 'Brightness (%)', 'kbroo-studio' ), 100, 200 ),
			$p . '_contrast'   => $slider( __( 'Contrast (%)', 'kbroo-studio' ), 100, 200 ),
			$p . '_saturate'   => $slider( __( 'Saturation (%)', 'kbroo-studio' ), 100, 200 ),
			$p . '_hue'        => $slider( __( 'Hue (deg)', 'kbroo-studio' ), 0, 360 ),
		);
	}

	/** Elementor's hover animations that are a single transform. */
	const HOVER_ANIMATIONS = array(
		'grow'          => 'scale(1.1)',
		'shrink'        => 'scale(0.9)',
		'float'         => 'translateY(-8px)',
		'sink'          => 'translateY(8px)',
		'rotate'        => 'rotate(4deg)',
		'grow-rotate'   => 'scale(1.1) rotate(4deg)',
		'skew'          => 'skew(-10deg)',
		'skew-forward'  => 'skew(-10deg)',
		'skew-backward' => 'skew(10deg)',
	);

	/**
	 * Hover animation (Elementor's hover_animation): a transform on hover,
	 * set through --kbroo-hover-transform on $selector.
	 */
	public static function hover_animation( string $selector, array $base ): array {
		$options = array( '' => __( 'None', 'kbroo-studio' ) );
		foreach ( array_keys( self::HOVER_ANIMATIONS ) as $name ) {
			$options[ $name ] = ucfirst( str_replace( '-', ' ', $name ) );
		}
		return array(
			'hover_animation' => $base + array(
				'type'                 => 'select',
				'label'                => __( 'Hover animation', 'kbroo-studio' ),
				'default'              => '',
				'options'              => $options,
				'selectors_dictionary' => self::HOVER_ANIMATIONS,
				'selectors'            => array( $selector => '--kbroo-hover-transform: {{VALUE}};' ),
			),
		);
	}

	/** Entrance animations, by Elementor name. */
	const ENTRANCE_ANIMATIONS = array( 'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'slideInUp', 'slideInDown', 'slideInLeft', 'slideInRight' );

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

		$motion  = array(
			'tab'     => 'advanced',
			'section' => __( 'Motion effects', 'kbroo-studio' ),
		);
		$animate = array( 'condition' => array( $prefix . 'animation!' => '' ) ) + $motion;
		$names   = array( '' => __( 'None', 'kbroo-studio' ) );
		foreach ( self::ENTRANCE_ANIMATIONS as $name ) {
			$names[ $name ] = ucfirst( strtolower( preg_replace( '/([a-z])([A-Z])/', '$1 $2', $name ) ) );
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
				// Columns take margin inside, as Elementor's do, so it never changes their width.
				'selectors'  => array( $spacing_target => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
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
			$prefix . 'animation'       => $motion + array(
				'type'        => 'select',
				'label'       => __( 'Entrance animation', 'kbroo-studio' ),
				'default'     => '',
				'options'     => $names,
				'description' => __( 'Plays once when the element scrolls into view.', 'kbroo-studio' ),
			),
			'animation_duration'        => $animate + array(
				'type'    => 'select',
				'label'   => __( 'Animation duration', 'kbroo-studio' ),
				'default' => '',
				'options' => array(
					'slow' => __( 'Slow', 'kbroo-studio' ),
					''     => __( 'Normal', 'kbroo-studio' ),
					'fast' => __( 'Fast', 'kbroo-studio' ),
				),
			),
			$prefix . 'animation_delay' => $animate + array(
				'type'  => 'number',
				'label' => __( 'Animation delay (ms)', 'kbroo-studio' ),
				'min'   => 0,
				'max'   => 10000,
			),
			'hide_desktop'          => $visibility + array( 'label' => __( 'Hide on desktop', 'kbroo-studio' ) ),
			'hide_tablet'           => $visibility + array( 'label' => __( 'Hide on tablet', 'kbroo-studio' ) ),
			'hide_mobile'           => $visibility + array( 'label' => __( 'Hide on mobile', 'kbroo-studio' ) ),
			'custom_css'            => array(
				'tab'         => 'advanced',
				'section'     => __( 'Custom CSS', 'kbroo-studio' ),
				'type'        => 'css',
				'label'       => __( 'CSS for this element', 'kbroo-studio' ),
				'description' => __( 'Write "selector" for this element, e.g. selector a { color: red; }', 'kbroo-studio' ),
				'default'     => '',
			),
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
