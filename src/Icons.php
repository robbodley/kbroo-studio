<?php
/**
 * Icons: Font Awesome Free, bundled as SVG paths (data/icons), so pages
 * make no requests to an icon font or CDN and only the icons used are sent.
 *
 * Values follow Elementor's icons control: { value: "fas fa-star",
 * library: "fa-solid" }. Older names (Font Awesome 4 and 5, as Elementor
 * stores them) are mapped to current ones. An uploaded SVG is
 * { value: { id, url }, library: "svg" } and is shown as an image.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Icons {

	/** Library => data file, and the class prefix Elementor uses. */
	const LIBRARIES = array(
		'fa-solid'   => 'fas',
		'fa-regular' => 'far',
		'fa-brands'  => 'fab',
	);

	/** @var array<string,array> Loaded icon data by library. */
	private static $data = array();

	/** @var array|null */
	private static $aliases = null;

	/**
	 * Icons of a library: name => [ width, height, path ].
	 */
	public static function library( string $library ): array {
		if ( ! isset( self::LIBRARIES[ $library ] ) ) {
			return array();
		}
		if ( ! isset( self::$data[ $library ] ) ) {
			self::$data[ $library ] = (array) require KBROO_STUDIO_DIR . 'data/icons/' . $library . '.php';
		}
		return self::$data[ $library ];
	}

	/**
	 * Library and icon name for a value, or null if it is not a known icon.
	 *
	 * @param mixed $value { value, library } or a class string.
	 * @return array{0:string,1:string}|null
	 */
	public static function resolve( $value ): ?array {
		$class   = is_array( $value ) ? ( $value['value'] ?? '' ) : $value;
		$library = is_array( $value ) ? (string) ( $value['library'] ?? '' ) : '';
		if ( ! is_string( $class ) || ! preg_match( '/^(fas|far|fab|fa)\s+fa-([a-z0-9-]+)$/', trim( $class ), $m ) ) {
			return null;
		}
		$prefixes = array_flip( self::LIBRARIES );
		if ( ! isset( self::LIBRARIES[ $library ] ) ) {
			$library = $prefixes[ $m[1] ] ?? 'fa-solid';
		}
		$name = $m[2];

		// Try the library asked for, then its old name, then the others.
		if ( null === self::$aliases ) {
			self::$aliases = (array) require KBROO_STUDIO_DIR . 'data/icons/fa-aliases.php';
		}
		$names = array_unique( array( $name, self::$aliases[ $name ] ?? $name, preg_replace( '/-o$/', '', $name ) ) );
		foreach ( array_unique( array_merge( array( $library ), array_keys( self::LIBRARIES ) ) ) as $lib ) {
			$icons = self::library( $lib );
			foreach ( $names as $candidate ) {
				if ( isset( $icons[ $candidate ] ) ) {
					return array( $lib, $candidate );
				}
			}
		}
		return null;
	}

	/**
	 * Inline SVG for an icon, or '' when there is none.
	 *
	 * @param mixed  $value Icon setting.
	 * @param string $class Extra class.
	 */
	public static function svg( $value, string $class = '' ): string {
		$classes = trim( 'kbroo-icon ' . $class );
		if ( is_array( $value ) && 'svg' === ( $value['library'] ?? '' ) && is_array( $value['value'] ?? null ) ) {
			$url = Controls::sanitize_url( $value['value']['url'] ?? '', array( 'http', 'https' ) );
			return '' === $url ? '' : '<img class="' . esc_attr( $classes . ' kbroo-icon--image' ) . '" src="' . esc_url( $url ) . '" alt="" />';
		}
		$found = self::resolve( $value );
		if ( ! $found ) {
			return '';
		}
		list( $width, $height, $path ) = self::library( $found[0] )[ $found[1] ];
		return sprintf(
			'<svg class="%1$s" viewBox="0 0 %2$d %3$d" aria-hidden="true" focusable="false" fill="currentColor"><path d="%4$s"/></svg>',
			esc_attr( $classes . ' kbroo-icon--' . $found[1] ),
			$width,
			$height,
			esc_attr( $path )
		);
	}

	/**
	 * A clean icons value: { value: "<prefix> fa-<name>", library } for a
	 * known icon, { value: { id, url }, library: "svg" } for an image, or
	 * the empty value.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize( $value ): array {
		$empty = array(
			'value'   => '',
			'library' => '',
		);
		if ( ! is_array( $value ) ) {
			return $empty;
		}
		if ( 'svg' === ( $value['library'] ?? '' ) && is_array( $value['value'] ?? null ) ) {
			$url = Controls::sanitize_url( $value['value']['url'] ?? '', array( 'http', 'https' ) );
			$id  = absint( $value['value']['id'] ?? 0 );
			return '' === $url ? $empty : array(
				'value'   => array(
					'url' => $url,
					'id'  => $id ? $id : '',
				),
				'library' => 'svg',
			);
		}
		$found = self::resolve( $value );
		if ( ! $found ) {
			return $empty;
		}
		return array(
			'value'   => self::LIBRARIES[ $found[0] ] . ' fa-' . $found[1],
			'library' => $found[0],
		);
	}

	/**
	 * Every icon for the editor's picker: library => name => [ w, h, path ],
	 * plus search words by name.
	 */
	public static function for_editor(): array {
		$libraries = array();
		foreach ( array_keys( self::LIBRARIES ) as $library ) {
			$libraries[ $library ] = self::library( $library );
		}
		$search = json_decode( (string) file_get_contents( KBROO_STUDIO_DIR . 'data/icons/fa-search.json' ), true );
		return array(
			'libraries' => $libraries,
			'search'    => is_array( $search ) ? $search : array(),
		);
	}
}
