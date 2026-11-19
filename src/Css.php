<?php
/**
 * Generates a layout's CSS from its elements' control `selectors`.
 *
 * Desktop values apply everywhere; tablet and mobile values are wrapped in
 * max-width media queries so they override on smaller screens. The editor
 * has a JavaScript twin of this class (assets/js/css.js); keep them in step.
 *
 * Only validated values reach the CSS: colors are hex, numbers are cast,
 * units and select values come from fixed lists. Free text is never output.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

final class Css {

	const BREAKPOINTS = array(
		'tablet' => 1024,
		'mobile' => 767,
	);

	/**
	 * Wrapper selector for an element.
	 */
	public static function wrapper( string $id ): string {
		return '.kbroo-el-' . $id;
	}

	/**
	 * Rules for one element, grouped by device.
	 *
	 * @param array  $controls Control definitions.
	 * @param array  $settings Settings with defaults applied.
	 * @param string $wrapper  Wrapper selector.
	 * @return array<string,string[]> device => list of "selector{declarations}"
	 */
	public static function element_rules( array $controls, array $settings, string $wrapper ): array {
		$rules = array_fill_keys( Controls::DEVICES, array() );

		foreach ( $controls as $key => $control ) {
			if ( empty( $control['selectors'] ) || ! Controls::condition_met( $control, $settings ) ) {
				continue;
			}
			foreach ( Controls::keys( $key, $control ) as $device => $setting_key ) {
				$replacements = self::global_replacements( $control, $settings, $setting_key, $device )
					?? self::replacements( $control, $settings[ $setting_key ] ?? null );
				if ( null === $replacements ) {
					continue;
				}
				foreach ( $control['selectors'] as $selector => $template ) {
					$selector               = str_replace( '{{WRAPPER}}', $wrapper, $selector );
					$declaration            = strtr( $template, $replacements + array( '{{DEVICE}}' => $device ) );
					$rules[ $device ][]     = $selector . '{' . $declaration . '}';
				}
			}
		}

		return $rules;
	}

	/**
	 * Placeholder values pointing at a global color or font, or null.
	 *
	 * A color linked to a global uses its variable. A typography property
	 * uses the linked global font's variable unless the element sets its
	 * own value. Tablet and mobile need no rule: the variables themselves
	 * change in media queries.
	 */
	public static function global_replacements( array $control, array $settings, string $setting_key, string $device ): ?array {
		if ( 'color' === ( $control['type'] ?? '' ) ) {
			$id = Controls::global_id( $settings, $setting_key );
			if ( '' === $id || ! Globals::defines( 'colors', $id ) ) {
				return null; // Falls back to the element's own color, if any.
			}
			return array( '{{VALUE}}' => 'var(' . Globals::variable( 'colors', $id ) . ')' );
		}

		if ( empty( $control['global_group'] ) || empty( $control['global_property'] ) || 'desktop' !== $device ) {
			return null;
		}
		$id = Controls::global_id( $settings, $control['global_group'] );
		if ( '' === $id || null !== self::replacements( $control, $settings[ $setting_key ] ?? null ) ) {
			return null;
		}
		if ( ! Globals::defines( 'typography', $id, $control['global_property'] ) ) {
			return null;
		}
		$var = 'var(' . Globals::variable( 'typography', $id, $control['global_property'] ) . ')';
		return array(
			'{{VALUE}}' => $var,
			'{{SIZE}}'  => $var,
			'{{UNIT}}'  => '',
		);
	}

	/**
	 * Placeholder values for a setting, or null when it is not set.
	 *
	 * @param mixed $value Sanitized setting value.
	 */
	public static function replacements( array $control, $value ): ?array {
		switch ( $control['type'] ?? 'text' ) {
			case 'color':
				$hex = Controls::sanitize_color( $value );
				return '' !== $hex ? array( '{{VALUE}}' => $hex ) : null;

			case 'select':
				if ( ! is_string( $value ) || '' === $value || ! array_key_exists( $value, $control['options'] ?? array() ) ) {
					return null;
				}
				// Dictionary values are written in code (font stacks may hold quotes
				// and commas); option keys are filtered to plain characters.
				if ( isset( $control['selectors_dictionary'][ $value ] ) ) {
					return array( '{{VALUE}}' => (string) $control['selectors_dictionary'][ $value ] );
				}
				return array( '{{VALUE}}' => preg_replace( '/[^A-Za-z0-9_\-\.%# ]/', '', $value ) );

			case 'number':
				return is_numeric( $value ) ? array( '{{VALUE}}' => (string) ( 0 + $value ) ) : null;

			case 'switcher':
				return 'yes' === $value ? array( '{{VALUE}}' => 'yes' ) : null;

			case 'slider':
				if ( ! is_array( $value ) || ! is_numeric( $value['size'] ?? '' ) ) {
					return null;
				}
				return array(
					'{{SIZE}}' => (string) ( 0 + $value['size'] ),
					'{{UNIT}}' => Controls::sanitize_unit( $control, $value['unit'] ?? '' ),
				);

			case 'dimensions':
				if ( ! is_array( $value ) ) {
					return null;
				}
				$sides = array();
				$set   = false;
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					$number = $value[ $side ] ?? '';
					$set    = $set || is_numeric( $number );
					$sides[ '{{' . strtoupper( $side ) . '}}' ] = is_numeric( $number ) ? (string) ( 0 + $number ) : '0';
				}
				if ( ! $set ) {
					return null;
				}
				$sides['{{UNIT}}'] = Controls::sanitize_unit( $control, $value['unit'] ?? '' );
				return $sides;

			default:
				return null; // Free text never goes into CSS.
		}
	}

	/**
	 * Join rules into a stylesheet with media queries.
	 *
	 * @param array<string,string[]> $rules device => rules.
	 */
	public static function stylesheet( array $rules ): string {
		$css = implode( '', $rules['desktop'] ?? array() );
		foreach ( self::BREAKPOINTS as $device => $max ) {
			if ( ! empty( $rules[ $device ] ) ) {
				$css .= '@media (max-width:' . $max . 'px){' . implode( '', $rules[ $device ] ) . '}';
			}
		}
		return $css;
	}
}
