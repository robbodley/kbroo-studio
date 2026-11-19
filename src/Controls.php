<?php
/**
 * Control schema helpers.
 *
 * A control describes one editable setting. The same definition drives the
 * editor's settings panel (it is sent to the browser as JSON) and the
 * server-side sanitization of the saved value.
 *
 * Supported types:
 *   text     single line, plain text
 *   textarea multi line; HTML allowed when 'html' => true (filtered by wp_kses_post)
 *   select   one of 'options'
 *   color    hex color or empty
 *   number   integer clamped to 'min'..'max'
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Controls {

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
			$clean[ $key ] = array_key_exists( $key, $settings )
				? self::sanitize_value( $control, $settings[ $key ] )
				: ( $control['default'] ?? '' );
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
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = $control['default'] ?? '';
			}
		}
		return $settings;
	}

	/**
	 * @param array $control Control definition.
	 * @param mixed $value   Raw value.
	 * @return string|int
	 */
	public static function sanitize_value( array $control, $value ) {
		$default = $control['default'] ?? '';
		if ( is_array( $value ) || is_object( $value ) ) {
			return $default;
		}

		switch ( $control['type'] ?? 'text' ) {
			case 'textarea':
				return empty( $control['html'] )
					? sanitize_textarea_field( (string) $value )
					: wp_kses_post( (string) $value );

			case 'select':
				$value = (string) $value;
				return array_key_exists( $value, $control['options'] ?? array() ) ? $value : $default;

			case 'color':
				$value = (string) $value;
				if ( '' === $value ) {
					return '';
				}
				$hex = sanitize_hex_color( $value );
				return $hex ? $hex : $default;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return $default;
				}
				$value = (int) round( (float) $value );
				if ( isset( $control['min'] ) ) {
					$value = max( (int) $control['min'], $value );
				}
				if ( isset( $control['max'] ) ) {
					$value = min( (int) $control['max'], $value );
				}
				return $value;

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}
