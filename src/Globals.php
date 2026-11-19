<?php
/**
 * Site-wide colors and fonts.
 *
 * Stored in one option, in the shape of Elementor's kit settings, so a site's
 * Elementor kit converts directly:
 *
 *     { "colors":     [ { "_id": "primary", "title": "Primary", "color": "#6EC1E4" } ],
 *       "typography": [ { "_id": "primary", "title": "Primary",
 *                         "typography_font_family": "Roboto", "typography_font_weight": "600", ... } ] }
 *
 * Each becomes a CSS custom property (--kbroo-global-color-<id>,
 * --kbroo-global-typography-<id>-font-size, ...). Elements link to them
 * through their `__globals__` setting, so changing a global restyles every
 * page that uses it.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Globals {

	const OPTION     = 'kbroo_global_styles';
	const CAPABILITY = 'edit_theme_options';
	const MAX_ITEMS  = 50;

	/** The four presets every site has; they can be edited but not deleted. */
	const SYSTEM_IDS = array( 'primary', 'secondary', 'text', 'accent' );

	public static function defaults(): array {
		return array(
			'colors'     => array(
				array( '_id' => 'primary', 'title' => __( 'Primary', 'kbroo-studio' ), 'color' => '#6EC1E4' ),
				array( '_id' => 'secondary', 'title' => __( 'Secondary', 'kbroo-studio' ), 'color' => '#54595F' ),
				array( '_id' => 'text', 'title' => __( 'Text', 'kbroo-studio' ), 'color' => '#7A7A7A' ),
				array( '_id' => 'accent', 'title' => __( 'Accent', 'kbroo-studio' ), 'color' => '#61CE70' ),
			),
			'typography' => array(
				array( '_id' => 'primary', 'title' => __( 'Primary', 'kbroo-studio' ), 'typography_font_family' => 'Roboto', 'typography_font_weight' => '600' ),
				array( '_id' => 'secondary', 'title' => __( 'Secondary', 'kbroo-studio' ), 'typography_font_family' => 'Roboto Slab', 'typography_font_weight' => '400' ),
				array( '_id' => 'text', 'title' => __( 'Text', 'kbroo-studio' ), 'typography_font_family' => 'Roboto', 'typography_font_weight' => '400' ),
				array( '_id' => 'accent', 'title' => __( 'Accent', 'kbroo-studio' ), 'typography_font_family' => 'Roboto', 'typography_font_weight' => '500' ),
			),
		);
	}

	/**
	 * Controls a global font preset is made of (the typography group).
	 */
	public static function typography_controls(): array {
		return ControlGroups::typography( 'typography', ':root' );
	}

	/** @var array|null Per-request cache of get(). */
	private static $cache = null;

	public static function get(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION );
			self::$cache = self::sanitize( is_array( $stored ) ? $stored : self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Whether a global exists and, for a font, sets the given CSS property
	 * on any device. Elements only point at variables that are defined:
	 * an undefined variable would reset the property instead of leaving
	 * the theme's value alone.
	 */
	public static function defines( string $kind, string $id, string $property = '' ): bool {
		foreach ( self::get()[ $kind ] as $item ) {
			if ( $item['_id'] !== $id ) {
				continue;
			}
			if ( 'colors' === $kind ) {
				return '' !== $item['color'];
			}
			foreach ( self::typography_controls() as $key => $control ) {
				if ( ( $control['global_property'] ?? '' ) !== $property ) {
					continue;
				}
				foreach ( Controls::keys( $key, $control ) as $setting_key ) {
					if ( null !== Css::replacements( $control, $item[ $setting_key ] ?? null ) ) {
						return true;
					}
				}
			}
			return false;
		}
		return false;
	}

	/**
	 * @param mixed $globals Raw globals.
	 */
	public static function save( $globals ): array {
		$clean = self::sanitize( $globals );
		update_option( self::OPTION, $clean, true );
		self::$cache = null;
		return $clean;
	}

	/**
	 * @param mixed $globals Raw globals.
	 */
	public static function sanitize( $globals ): array {
		$globals = is_array( $globals ) ? $globals : array();
		$clean   = array(
			'colors'     => array(),
			'typography' => array(),
		);
		$controls = self::typography_controls();

		foreach ( array( 'colors', 'typography' ) as $kind ) {
			$seen = array();
			foreach ( (array) ( $globals[ $kind ] ?? array() ) as $item ) {
				if ( ! is_array( $item ) || count( $clean[ $kind ] ) >= self::MAX_ITEMS ) {
					continue;
				}
				$id = strtolower( (string) ( $item['_id'] ?? '' ) );
				if ( ! preg_match( '/^[a-z0-9_-]{1,40}$/', $id ) || isset( $seen[ $id ] ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$entry       = array(
					'_id'   => $id,
					'title' => mb_substr( sanitize_text_field( (string) ( $item['title'] ?? '' ) ), 0, 60 ),
				);
				if ( 'colors' === $kind ) {
					$entry['color'] = Controls::sanitize_color( $item['color'] ?? '' );
				} else {
					$entry += Controls::sanitize( $controls, $item );
				}
				$clean[ $kind ][] = $entry;
			}
		}

		// The system presets always exist.
		foreach ( self::defaults() as $kind => $items ) {
			$ids = wp_list_pluck( $clean[ $kind ], '_id' );
			foreach ( array_reverse( $items ) as $item ) {
				if ( ! in_array( $item['_id'], $ids, true ) ) {
					array_unshift( $clean[ $kind ], 'colors' === $kind ? $item : array_merge( $item, Controls::sanitize( $controls, $item ) ) );
				}
			}
		}

		return $clean;
	}

	/**
	 * The CSS custom properties, with tablet and mobile font values in media queries.
	 */
	public static function css( ?array $globals = null ): string {
		$globals = $globals ?? self::get();
		$rules   = array_fill_keys( Controls::DEVICES, array() );

		foreach ( $globals['colors'] as $color ) {
			if ( '' !== $color['color'] ) {
				$rules['desktop'][] = ':root{--kbroo-global-color-' . $color['_id'] . ':' . $color['color'] . ';}';
			}
		}

		foreach ( $globals['typography'] as $preset ) {
			$controls = self::variable_controls( $preset['_id'] );
			foreach ( Css::element_rules( $controls, $preset, ':root' ) as $device => $list ) {
				$rules[ $device ] = array_merge( $rules[ $device ], $list );
			}
		}

		return Css::stylesheet( $rules );
	}

	/**
	 * Typography controls rewritten to set custom properties on :root.
	 */
	public static function variable_controls( string $id ): array {
		$controls = self::typography_controls();
		foreach ( $controls as $key => $control ) {
			$value = 'slider' === $control['type'] ? '{{SIZE}}{{UNIT}}' : '{{VALUE}}';
			$controls[ $key ]['selectors'] = array(
				':root' => self::variable( 'typography', $id, $control['global_property'] ) . ': ' . $value . ';',
			);
		}
		return $controls;
	}

	/**
	 * Custom property name for a global.
	 */
	public static function variable( string $kind, string $id, string $property = '' ): string {
		return 'colors' === $kind
			? '--kbroo-global-color-' . $id
			: '--kbroo-global-typography-' . $id . '-' . $property;
	}

	/**
	 * Font families a layout uses, directly or through global fonts.
	 */
	public static function fonts_in_layout( array $layout, ?array $globals = null ): array {
		$globals = $globals ?? self::get();
		$presets = array();
		foreach ( $globals['typography'] as $preset ) {
			$presets[ $preset['_id'] ] = (string) ( $preset['typography_font_family'] ?? '' );
		}

		$fonts = array();
		$walk  = function ( array $elements ) use ( &$walk, &$fonts, $presets ) {
			foreach ( $elements as $element ) {
				$settings = (array) ( $element['settings'] ?? array() );
				foreach ( $settings as $key => $value ) {
					if ( is_string( $value ) && '' !== $value && '_font_family' === substr( $key, -12 ) ) {
						$fonts[] = $value;
					}
				}
				foreach ( (array) ( $settings['__globals__'] ?? array() ) as $ref ) {
					if ( is_string( $ref ) && preg_match( '#^globals/typography\?id=([a-z0-9_-]+)$#', $ref, $m ) && ! empty( $presets[ $m[1] ] ) ) {
						$fonts[] = $presets[ $m[1] ];
					}
				}
				$walk( (array) ( $element['elements'] ?? array() ) );
			}
		};
		$walk( (array) ( $layout['elements'] ?? array() ) );

		return array_values( array_unique( $fonts ) );
	}
}
