<?php
/**
 * Control schema helpers.
 *
 * A control describes one editable setting. The same definition drives the
 * editor's settings panel (it is sent to the browser as JSON), server-side
 * sanitization, and CSS generation (see Css).
 *
 * Definition keys:
 *   type        text | textarea | select | color | number | slider | dimensions | switcher
 *   label       Shown in the panel.
 *   tab         content (default) | style | advanced
 *   section     Group heading inside the tab.
 *   default     Default value (shape depends on type, see empty_value()).
 *   responsive  true: also stores <key>_tablet and <key>_mobile, which
 *               override the desktop value on smaller screens.
 *   default_tablet / default_mobile  Defaults for those variants.
 *   options     select: value => label.
 *   units       slider / dimensions: allowed units, first is the default.
 *   min / max   number: clamp range.
 *   html        textarea: allow HTML (filtered with wp_kses_post).
 *   sanitize    text: 'classes' (CSS class list) or 'id' (HTML id).
 *   selectors   CSS selector => declaration template. Placeholders:
 *               {{WRAPPER}} the element, {{VALUE}}, {{SIZE}} {{UNIT}} (slider),
 *               {{TOP}} {{RIGHT}} {{BOTTOM}} {{LEFT}} {{UNIT}} (dimensions).
 *   selectors_dictionary  select: option value => CSS value used for {{VALUE}}.
 *   condition   Show and apply only when other settings match:
 *               [ 'key' => value|[values], 'key!' => value|[values] ].
 *
 * Setting names and value shapes follow Elementor's where there is an
 * equivalent, so imported Elementor data maps across with little work.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Controls {

	const DEVICES = array( 'desktop', 'tablet', 'mobile' );

	const ALL_UNITS = array( 'px', '%', 'em', 'rem', 'vw', 'vh' );

	/**
	 * Setting key for a control on a device.
	 */
	public static function device_key( string $key, string $device ): string {
		return 'desktop' === $device ? $key : $key . '_' . $device;
	}

	/**
	 * The "not set" value for a control type.
	 *
	 * @return mixed
	 */
	public static function empty_value( array $control ) {
		$unit = self::units( $control )[0];
		switch ( $control['type'] ?? 'text' ) {
			case 'slider':
				return array(
					'unit' => $unit,
					'size' => '',
				);
			case 'dimensions':
				return array(
					'unit'     => $unit,
					'top'      => '',
					'right'    => '',
					'bottom'   => '',
					'left'     => '',
					'isLinked' => true,
				);
			default:
				return '';
		}
	}

	/**
	 * Default for a control on a device.
	 *
	 * @return mixed
	 */
	public static function default_for( array $control, string $device = 'desktop' ) {
		if ( 'desktop' === $device ) {
			return array_key_exists( 'default', $control ) ? $control['default'] : self::empty_value( $control );
		}
		$key = 'default_' . $device;
		return array_key_exists( $key, $control ) ? $control[ $key ] : self::empty_value( $control );
	}

	/**
	 * Control definitions as sent to the editor: every default is filled in
	 * so the browser never has to work out a type's empty value.
	 */
	public static function for_editor( array $controls ): array {
		foreach ( $controls as $key => $control ) {
			$controls[ $key ]['type']    = $control['type'] ?? 'text';
			$controls[ $key ]['tab']     = $control['tab'] ?? 'content';
			$controls[ $key ]['default'] = self::default_for( $control, 'desktop' );
			if ( ! empty( $control['responsive'] ) ) {
				$controls[ $key ]['default_tablet'] = self::default_for( $control, 'tablet' );
				$controls[ $key ]['default_mobile'] = self::default_for( $control, 'mobile' );
			}
			if ( in_array( $controls[ $key ]['type'], array( 'slider', 'dimensions' ), true ) ) {
				$controls[ $key ]['units'] = self::units( $control );
			}
		}
		return $controls;
	}

	/**
	 * Setting keys a control stores, keyed by device.
	 *
	 * @return array<string,string>
	 */
	public static function keys( string $key, array $control ): array {
		if ( empty( $control['responsive'] ) ) {
			return array( 'desktop' => $key );
		}
		$keys = array();
		foreach ( self::DEVICES as $device ) {
			$keys[ $device ] = self::device_key( $key, $device );
		}
		return $keys;
	}

	/**
	 * Return settings containing exactly the defined controls, each sanitized
	 * or reset to its default.
	 *
	 * @param array $controls Control definitions keyed by setting name.
	 * @param mixed $settings Raw settings.
	 */
	public static function sanitize( array $controls, $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		$clean    = array();
		foreach ( $controls as $key => $control ) {
			foreach ( self::keys( $key, $control ) as $device => $setting_key ) {
				$clean[ $setting_key ] = array_key_exists( $setting_key, $settings )
					? self::sanitize_value( $control, $settings[ $setting_key ], $device )
					: self::default_for( $control, $device );
			}
		}
		return $clean;
	}

	/**
	 * Fill in defaults for any missing settings (used at render time, so old
	 * layouts keep working when new controls are added).
	 */
	public static function with_defaults( array $controls, $settings ): array {
		$settings = is_array( $settings ) ? $settings : array();
		foreach ( $controls as $key => $control ) {
			foreach ( self::keys( $key, $control ) as $device => $setting_key ) {
				if ( ! array_key_exists( $setting_key, $settings ) ) {
					$settings[ $setting_key ] = self::default_for( $control, $device );
				}
			}
		}
		return $settings;
	}

	/**
	 * @param array  $control Control definition.
	 * @param mixed  $value   Raw value.
	 * @param string $device  Device the value is for.
	 * @return mixed
	 */
	public static function sanitize_value( array $control, $value, string $device = 'desktop' ) {
		$default = self::default_for( $control, $device );
		$type    = $control['type'] ?? 'text';

		if ( 'slider' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			return array(
				'unit' => self::sanitize_unit( $control, $value['unit'] ?? '' ),
				'size' => self::sanitize_number( $value['size'] ?? '' ),
			);
		}

		if ( 'dimensions' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			$clean = array( 'unit' => self::sanitize_unit( $control, $value['unit'] ?? '' ) );
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$clean[ $side ] = self::sanitize_number( $value[ $side ] ?? '' );
			}
			$clean['isLinked'] = ! empty( $value['isLinked'] );
			return $clean;
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return $default;
		}

		switch ( $type ) {
			case 'textarea':
				return empty( $control['html'] )
					? sanitize_textarea_field( (string) $value )
					: wp_kses_post( (string) $value );

			case 'select':
				$value = (string) $value;
				if ( '' === $value && 'desktop' !== $device ) {
					return ''; // Inherit from the larger screen.
				}
				return array_key_exists( $value, $control['options'] ?? array() ) ? $value : $default;

			case 'color':
				$value = (string) $value;
				if ( '' === $value ) {
					return '';
				}
				$hex = sanitize_hex_color( $value );
				return $hex ? $hex : $default;

			case 'number':
				if ( '' === $value && 'desktop' !== $device ) {
					return '';
				}
				if ( ! is_numeric( $value ) ) {
					return $default;
				}
				$value = 0 + $value;
				if ( isset( $control['min'] ) ) {
					$value = max( $control['min'], $value );
				}
				if ( isset( $control['max'] ) ) {
					$value = min( $control['max'], $value );
				}
				return $value;

			case 'switcher':
				return 'yes' === $value ? 'yes' : '';

			case 'text':
			default:
				$value = sanitize_text_field( (string) $value );
				if ( 'classes' === ( $control['sanitize'] ?? '' ) ) {
					return trim( preg_replace( '/[^A-Za-z0-9_\- ]/', '', $value ) );
				}
				if ( 'id' === ( $control['sanitize'] ?? '' ) ) {
					return preg_replace( '/[^A-Za-z0-9_\-]/', '', $value );
				}
				return $value;
		}
	}

	/**
	 * A number or '' (not set). Numbers are cast so they are safe in CSS.
	 *
	 * @param mixed $value Raw value.
	 * @return int|float|string
	 */
	public static function sanitize_number( $value ) {
		if ( is_bool( $value ) || ! is_numeric( $value ) ) {
			return '';
		}
		$number = 0 + $value;
		return is_finite( (float) $number ) ? $number : '';
	}

	/**
	 * @return string[]
	 */
	public static function units( array $control ): array {
		$units = array_values( array_intersect( (array) ( $control['units'] ?? array( 'px' ) ), self::ALL_UNITS ) );
		return $units ? $units : array( 'px' );
	}

	public static function sanitize_unit( array $control, $unit ): string {
		$units = self::units( $control );
		return in_array( $unit, $units, true ) ? $unit : $units[0];
	}

	/**
	 * Whether a control's condition holds for the given settings.
	 */
	public static function condition_met( array $control, array $settings ): bool {
		foreach ( (array) ( $control['condition'] ?? array() ) as $key => $expected ) {
			$negate = '!' === substr( $key, -1 );
			$key    = $negate ? substr( $key, 0, -1 ) : $key;
			$value  = $settings[ $key ] ?? '';
			$match  = is_array( $expected ) ? in_array( $value, $expected, true ) : $value === $expected;
			if ( $match === $negate ) {
				return false;
			}
		}
		return true;
	}
}
