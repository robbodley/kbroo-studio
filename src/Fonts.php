<?php
/**
 * Font families the builder offers, and loading Google Fonts for them.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Fonts {

	/** Fonts added for this site (by the Elementor converter): name => [ google, fallback ]. */
	const SITE_OPTION = 'kbroo_site_fonts';

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
		$site = get_option( self::SITE_OPTION, array() );
		foreach ( is_array( $site ) ? $site : array() as $name => $font ) {
			if ( is_string( $name ) && is_array( $font ) && ! isset( $fonts[ $name ] ) ) {
				$fonts[ $name ] = array(
					'stack'  => '"' . $name . '", ' . ( 'serif' === ( $font['fallback'] ?? '' ) ? 'serif' : 'sans-serif' ),
					'google' => (string) ( $font['google'] ?? '' ),
				);
			}
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
	 * Make a font available on this site. Google Fonts are checked against
	 * the Google Fonts API, weight by weight, so the stylesheet URL only
	 * asks for weights that exist (one wrong weight fails the whole URL).
	 * Other names are added as local fonts (for example a font the theme
	 * or Elementor Pro's custom fonts loads).
	 *
	 * @return string 'exists', 'google', 'local' or 'invalid'.
	 */
	public static function add( string $name ): string {
		$name = trim( $name );
		if ( ! preg_match( '/^[A-Za-z0-9 \-]{1,60}$/', $name ) ) {
			return 'invalid';
		}
		if ( isset( self::all()[ $name ] ) ) {
			return 'exists';
		}

		$base    = 'https://fonts.googleapis.com/css2?family=' . str_replace( ' ', '+', $name );
		$weights = array();
		foreach ( array( 300, 400, 500, 600, 700 ) as $weight ) {
			$response = wp_remote_get( $base . ':wght@' . $weight, array( 'timeout' => 8 ) );
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$weights[] = $weight;
			}
		}
		if ( ! $weights && 200 === wp_remote_retrieve_response_code( wp_remote_get( $base, array( 'timeout' => 8 ) ) ) ) {
			$weights[] = 400;
		}

		$site          = get_option( self::SITE_OPTION, array() );
		$site          = is_array( $site ) ? $site : array();
		$site[ $name ] = array(
			'google'   => implode( ';', $weights ),
			'fallback' => 'sans-serif',
		);
		update_option( self::SITE_OPTION, $site );
		return $weights ? 'google' : 'local';
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
