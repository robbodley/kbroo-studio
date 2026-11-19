<?php
/**
 * Shape dividers: a shape along the top or bottom edge of a section or
 * container, in the colour of the next section, so the two meet in a wave,
 * slope or zigzag instead of a straight line. Names match Elementor's
 * (shape_divider_top = "waves"); the drawings are Kbroo Studio's own.
 *
 * Each shape is one or more curves across a 1000 x 100 box, from the left
 * edge to the right. The filled area is above the curve (the edge side),
 * or below it when "negative" is on. Layers with an opacity below 1 are
 * drawn first and reach further, for the layered shapes.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class ShapeDividers {

	/**
	 * Shape names and labels.
	 *
	 * @return array<string,string>
	 */
	public static function options(): array {
		return array(
			''                      => __( 'None', 'kbroo-studio' ),
			'mountains'             => __( 'Mountains', 'kbroo-studio' ),
			'drops'                 => __( 'Drops', 'kbroo-studio' ),
			'clouds'                => __( 'Clouds', 'kbroo-studio' ),
			'zigzag'                => __( 'Zigzag', 'kbroo-studio' ),
			'pyramids'              => __( 'Pyramids', 'kbroo-studio' ),
			'triangle'              => __( 'Triangle', 'kbroo-studio' ),
			'triangle-asymmetrical' => __( 'Triangle, off centre', 'kbroo-studio' ),
			'tilt'                  => __( 'Tilt', 'kbroo-studio' ),
			'opacity-tilt'          => __( 'Tilt, layered', 'kbroo-studio' ),
			'opacity-fan'           => __( 'Fan, layered', 'kbroo-studio' ),
			'curve'                 => __( 'Curve', 'kbroo-studio' ),
			'curve-asymmetrical'    => __( 'Curve, off centre', 'kbroo-studio' ),
			'waves'                 => __( 'Waves', 'kbroo-studio' ),
			'wave-brush'            => __( 'Brush wave', 'kbroo-studio' ),
			'waves-pattern'         => __( 'Waves, layered', 'kbroo-studio' ),
			'arrow'                 => __( 'Arrow', 'kbroo-studio' ),
			'split'                 => __( 'Split', 'kbroo-studio' ),
			'book'                  => __( 'Book', 'kbroo-studio' ),
		);
	}

	/**
	 * Curves for a shape, each "M0,y ... 1000,y" with its opacity.
	 *
	 * @return array<int,array{0:string,1:float}>
	 */
	private static function curves( string $name ): array {
		switch ( $name ) {
			case 'mountains':
				return array(
					// The paler range behind reaches further, so it shows between the peaks.
					array( 'M0,95 L110,60 L230,90 L330,45 L470,85 L580,50 L700,92 L820,55 L930,85 L1000,65', 0.35 ),
					array( 'M0,70 L150,35 L260,65 L380,20 L520,60 L640,30 L760,70 L880,35 L1000,55', 1 ),
				);
			case 'drops':
				return array( array( self::bumps( 7, 18, 95, 0.55 ), 1 ) );
			case 'clouds':
				return array( array( self::bumps( 10, 30, 75, 1 ), 1 ) );
			case 'zigzag':
				return array( array( self::zigzag( 50, 0, 100 ), 1 ) );
			case 'pyramids':
				return array( array( 'M0,0 L120,100 L240,20 L420,100 L520,0 L680,100 L800,30 L920,100 L1000,40', 1 ) );
			case 'triangle':
				return array( array( 'M0,0 L500,100 L1000,0', 1 ) );
			case 'triangle-asymmetrical':
				return array( array( 'M0,0 L720,100 L1000,0', 1 ) );
			case 'tilt':
				return array( array( 'M0,100 L1000,0', 1 ) );
			case 'opacity-tilt':
				return array(
					array( 'M0,100 L1000,0', 0.3 ),
					array( 'M0,70 L1000,0', 0.5 ),
					array( 'M0,40 L1000,0', 1 ),
				);
			case 'opacity-fan':
				return array(
					array( 'M0,0 L300,100 L1000,0', 0.3 ),
					array( 'M0,0 L500,100 L1000,0', 0.5 ),
					array( 'M0,0 L700,100 L1000,0', 1 ),
				);
			case 'curve':
				return array( array( 'M0,0 C250,135 750,135 1000,0', 1 ) );
			case 'curve-asymmetrical':
				return array( array( 'M0,0 C400,150 850,120 1000,0', 1 ) );
			case 'waves':
				return array( array( self::waves( 4, 50, 40 ), 1 ) );
			case 'wave-brush':
				return array(
					array( self::waves( 3, 62, 30, 0.6 ), 0.4 ),
					array( self::waves( 5, 48, 22 ), 1 ),
				);
			case 'waves-pattern':
				return array(
					array( self::waves( 3, 70, 25, 0.5 ), 0.25 ),
					array( self::waves( 4, 55, 25, 0.25 ), 0.5 ),
					array( self::waves( 5, 40, 22 ), 1 ),
				);
			case 'arrow':
				return array( array( 'M0,0 L460,0 L500,100 L540,0 L1000,0', 1 ) );
			case 'split':
				return array( array( 'M0,55 L440,55 L500,100 L560,55 L1000,55', 1 ) );
			case 'book':
				return array( array( 'M0,0 C200,0 420,40 500,100 C580,40 800,0 1000,0', 1 ) );
		}
		return array();
	}

	/** Sine-like waves: $count waves around $middle, $amplitude high, shifted by $phase (0-1 of a wave). */
	private static function waves( int $count, float $middle, float $amplitude, float $phase = 0 ): string {
		$width = 1000 / $count;
		$shift = $phase * $width;
		$x     = -$shift;
		$path  = sprintf( 'M%s,%s', self::n( $x ), self::n( $middle ) );
		while ( $x < 1000 ) {
			$half = $width / 2;
			$path .= sprintf(
				' C%s,%s %s,%s %s,%s C%s,%s %s,%s %s,%s',
				self::n( $x + $half * 0.36 ),
				self::n( $middle - $amplitude ),
				self::n( $x + $half * 0.64 ),
				self::n( $middle - $amplitude ),
				self::n( $x + $half ),
				self::n( $middle ),
				self::n( $x + $half * 1.36 ),
				self::n( $middle + $amplitude ),
				self::n( $x + $half * 1.64 ),
				self::n( $middle + $amplitude ),
				self::n( $x + $width ),
				self::n( $middle )
			);
			$x += $width;
		}
		return $path;
	}

	/** Rounded bumps hanging down from $top to $bottom; $narrow < 1 makes drops. */
	private static function bumps( int $count, float $top, float $bottom, float $narrow ): string {
		$width = 1000 / $count;
		$path  = sprintf( 'M0,%s', self::n( $top ) );
		for ( $i = 0; $i < $count; $i++ ) {
			$x0   = $i * $width;
			$gap  = $width * ( 1 - $narrow ) / 2;
			$path .= sprintf(
				' L%s,%s C%s,%s %s,%s %s,%s L%s,%s',
				self::n( $x0 + $gap ),
				self::n( $top ),
				self::n( $x0 + $gap ),
				self::n( $bottom * 1.3 - $top * 0.3 ),
				self::n( $x0 + $width - $gap ),
				self::n( $bottom * 1.3 - $top * 0.3 ),
				self::n( $x0 + $width - $gap ),
				self::n( $top ),
				self::n( $x0 + $width ),
				self::n( $top )
			);
		}
		return $path;
	}

	private static function zigzag( float $width, float $top, float $bottom ): string {
		$path = sprintf( 'M0,%s', self::n( $top ) );
		for ( $x = 0, $i = 0; $x < 1000; $i++ ) {
			$x    += $width / 2;
			$path .= sprintf( ' L%s,%s', self::n( min( $x, 1000 ) ), self::n( $i % 2 ? $top : $bottom ) );
		}
		return $path;
	}

	private static function n( float $value ): string {
		return rtrim( rtrim( number_format( $value, 1, '.', '' ), '0' ), '.' );
	}

	/**
	 * The SVG for a shape, or '' for an unknown one.
	 */
	public static function svg( string $name, bool $negative = false ): string {
		$curves = self::curves( $name );
		if ( ! $curves ) {
			return '';
		}
		// Close each curve along the edge it fills from. Curves may run past
		// 0 and 1000 (waves start early), so close well outside the box.
		$close = $negative ? ' L1100,100 L1100,110 L-100,110 L-100,100 Z' : ' L1100,0 L1100,-10 L-100,-10 L-100,0 Z';
		$paths = '';
		foreach ( $curves as $curve ) {
			list( $d, $opacity ) = $curve;
			// Run in level from the left and out level to the right.
			$d      = preg_replace( '/^M(-?[\d.]+),(-?[\d.]+)/', 'M-100,$2 L$1,$2', $d, 1 );
			$paths .= '<path d="' . esc_attr( $d . ' L1100,' . self::last_y( $d ) . $close ) . '"' . ( $opacity < 1 ? ' opacity="' . $opacity . '"' : '' ) . '/>';
		}
		return '<svg viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">' . $paths . '</svg>';
	}

	private static function last_y( string $d ): string {
		return preg_match( '/,(-?[\d.]+)\s*$/', $d, $m ) ? $m[1] : '0';
	}

	/**
	 * Every shape, normal and negative, for the editor.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function for_editor(): array {
		$shapes = array();
		foreach ( array_keys( self::options() ) as $name ) {
			if ( '' !== $name ) {
				$shapes[ $name ] = array( self::svg( $name ), self::svg( $name, true ) );
			}
		}
		return $shapes;
	}

	/**
	 * Controls for one edge ("top" or "bottom"), named as Elementor does.
	 */
	public static function controls( string $side ): array {
		$base  = array(
			'tab'     => 'style',
			'section' => 'top' === $side ? __( 'Shape divider, top', 'kbroo-studio' ) : __( 'Shape divider, bottom', 'kbroo-studio' ),
		);
		$key   = 'shape_divider_' . $side;
		$shape = '{{WRAPPER}} > .kbroo-shape--' . $side;
		$when  = array( 'condition' => array( $key . '!' => '' ) );

		return array(
			$key                    => $base + array(
				'type'    => 'select',
				'label'   => __( 'Shape', 'kbroo-studio' ),
				'default' => '',
				'options' => self::options(),
			),
			$key . '_color'         => $base + $when + array(
				'type'      => 'color',
				'label'     => __( 'Colour', 'kbroo-studio' ),
				'default'   => '',
				'selectors' => array( $shape . ' svg' => 'fill: {{VALUE}};' ),
			),
			$key . '_width'         => $base + $when + array(
				'type'       => 'slider',
				'label'      => __( 'Width (%)', 'kbroo-studio' ),
				'units'      => array( '%' ),
				'responsive' => true,
				'selectors'  => array( $shape . ' svg' => 'width: calc({{SIZE}}% + 1.3px);' ),
			),
			$key . '_height'        => $base + $when + array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'kbroo-studio' ),
				'units'      => array( 'px' ),
				'responsive' => true,
				'selectors'  => array( $shape . ' svg' => 'height: {{SIZE}}{{UNIT}};' ),
			),
			$key . '_flip'          => $base + $when + array(
				'type'    => 'switcher',
				'label'   => __( 'Flip', 'kbroo-studio' ),
				'default' => '',
			),
			$key . '_negative'      => $base + $when + array(
				'type'    => 'switcher',
				'label'   => __( 'Invert', 'kbroo-studio' ),
				'default' => '',
			),
			$key . '_above_content' => $base + $when + array(
				'type'    => 'switcher',
				'label'   => __( 'In front of the content', 'kbroo-studio' ),
				'default' => '',
			),
		);
	}

	/** Both edges. */
	public static function all_controls(): array {
		return array_merge( self::controls( 'top' ), self::controls( 'bottom' ) );
	}

	/**
	 * Markup for the dividers an element's settings ask for.
	 */
	public static function render( array $settings ): string {
		$html = '';
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$key = 'shape_divider_' . $side;
			$svg = self::svg( (string) ( $settings[ $key ] ?? '' ), 'yes' === ( $settings[ $key . '_negative' ] ?? '' ) );
			if ( '' === $svg ) {
				continue;
			}
			$classes = 'kbroo-shape kbroo-shape--' . $side;
			if ( 'yes' === ( $settings[ $key . '_flip' ] ?? '' ) ) {
				$classes .= ' kbroo-shape--flip';
			}
			if ( 'yes' === ( $settings[ $key . '_above_content' ] ?? '' ) ) {
				$classes .= ' kbroo-shape--above';
			}
			$html .= '<div class="' . $classes . '" aria-hidden="true">' . $svg . '</div>';
		}
		return $html;
	}
}
