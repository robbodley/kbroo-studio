<?php
/**
 * Font families the builder offers, and loading Google Fonts for them.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Fonts {

	/**
	 * System fonts: name => CSS stack.
	 */
	const SYSTEM = array(
		'System UI'       => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
		'Arial'           => 'Arial, Helvetica, sans-serif',
		'Helvetica'       => 'Helvetica, Arial, sans-serif',
		'Georgia'         => 'Georgia, serif',
		'Times New Roman' => '"Times New Roman", Times, serif',
		'Verdana'         => 'Verdana, Geneva, sans-serif',
		'Courier New'     => '"Courier New", Courier, monospace',
	);

	/**
	 * Google Fonts: name => [ fallback, weights for the css2 API ].
	 * Weights are a range for variable fonts, or the exact list otherwise.
	 */
	const GOOGLE = array(
		'Roboto'            => array( 'sans-serif', '100..900' ),
		'Roboto Slab'       => array( 'serif', '100..900' ),
		'Open Sans'         => array( 'sans-serif', '300..800' ),
		'Inter'             => array( 'sans-serif', '100..900' ),
		'Lato'              => array( 'sans-serif', '100;300;400;700;900' ),
		'Montserrat'        => array( 'sans-serif', '100..900' ),
		'Poppins'           => array( 'sans-serif', '100;200;300;400;500;600;700;800;900' ),
		'Raleway'           => array( 'sans-serif', '100..900' ),
		'Oswald'            => array( 'sans-serif', '200..700' ),
		'Nunito'            => array( 'sans-serif', '200..1000' ),
		'Work Sans'         => array( 'sans-serif', '100..900' ),
		'Rubik'             => array( 'sans-serif', '300..900' ),
		'DM Sans'           => array( 'sans-serif', '100..1000' ),
		'Source Sans 3'     => array( 'sans-serif', '200..900' ),
		'Josefin Sans'      => array( 'sans-serif', '100..700' ),
		'Quicksand'         => array( 'sans-serif', '300..700' ),
		'Barlow'            => array( 'sans-serif', '100;200;300;400;500;600;700;800;900' ),
		'PT Sans'           => array( 'sans-serif', '400;700' ),
		'Playfair Display'  => array( 'serif', '400..900' ),
		'Lora'              => array( 'serif', '400..700' ),
		'Merriweather'      => array( 'serif', '300..900' ),
		'Libre Baskerville' => array( 'serif', '400;700' ),
		'Cormorant Garamond' => array( 'serif', '300..700' ),
	);

	/**
	 * All fonts: name => [ 'stack' => CSS, 'google' => weights or '' ].
	 */
	public static function all(): array {
		$fonts = array();
		foreach ( self::SYSTEM as $name => $stack ) {
			$fonts[ $name ] = array(
				'stack'  => $stack,
				'google' => '',
			);
		}
		foreach ( self::GOOGLE as $name => $font ) {
			$fonts[ $name ] = array(
				'stack'  => '"' . $name . '", ' . $font[0],
				'google' => $font[1],
			);
		}
		/**
		 * Filters the fonts the builder offers. Each entry is
		 * name => [ 'stack' => CSS font-family value, 'google' => css2 weights or '' ].
		 *
		 * @param array $fonts Fonts.
		 */
		$fonts = (array) apply_filters( 'kbroo_studio_fonts', $fonts );

		// Names and stacks reach CSS: keep only safe characters.
		$clean = array();
		foreach ( $fonts as $name => $font ) {
			if ( ! is_string( $name ) || ! preg_match( '/^[A-Za-z0-9 \-]{1,60}$/', $name ) || ! is_array( $font ) ) {
				continue;
			}
			$stack = (string) ( $font['stack'] ?? '' );
			if ( ! preg_match( '/^[A-Za-z0-9 ,"\-]{1,200}$/', $stack ) ) {
				continue;
			}
			$clean[ $name ] = array(
				'stack'  => $stack,
				'google' => preg_match( '/^[0-9.;]{1,60}$/', (string) ( $font['google'] ?? '' ) ) ? (string) $font['google'] : '',
			);
		}
		return $clean;
	}

	/**
	 * Options for a select control.
	 */
	public static function options(): array {
		$names = array_keys( self::all() );
		return array( '' => __( 'Default', 'kbroo-studio' ) ) + array_combine( $names, $names );
	}

	/**
	 * name => CSS stack, for a select control's selectors_dictionary.
	 */
	public static function stacks(): array {
		return wp_list_pluck( self::all(), 'stack' );
	}

	/**
	 * Google Fonts stylesheet URL for the given family names, or ''.
	 *
	 * @param string[] $names Font names.
	 */
	public static function google_url( array $names ): string {
		$all      = self::all();
		$families = array();
		foreach ( array_unique( $names ) as $name ) {
			if ( isset( $all[ $name ] ) && '' !== $all[ $name ]['google'] ) {
				$families[] = 'family=' . str_replace( ' ', '+', $name ) . ':wght@' . $all[ $name ]['google'];
			}
		}
		if ( ! $families ) {
			return '';
		}
		sort( $families );
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}
}
