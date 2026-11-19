<?php
/**
 * Control schema helpers.
 *
 * A control describes one editable setting. The same definition drives the
 * editor's settings panel (it is sent to the browser as JSON), server-side
 * sanitization, and CSS generation (see Css).
 *
 * Definition keys:
 *   type        text | textarea | code | select | color | number | slider |
 *               dimensions | switcher | media | url
 *               code: raw HTML, kept as typed for users who may post
 *               unfiltered HTML, filtered with wp_kses_post for others.
 *               media: { id, url } of a Media Library image (Elementor's shape).
 *               url: { url, is_external, nofollow } (Elementor's link shape).
 *               icons: { value, library }, e.g. { "fas fa-star", "fa-solid" }
 *               (Elementor's shape, see Icons).
 *               repeater: a list of items, each with its own settings from
 *               `fields` (control definitions) and an `_id`. Field selectors
 *               may use {{CURRENT_ITEM}}, the item's .kbroo-repeater-item-<id>.
 *               multiselect: a list of option keys (Elementor's select2 with
 *               multiple), shown as checkboxes.
 *               gallery: a list of { id, url } images (Elementor's shape).
 *   media_type  media: 'image' (default) or 'video', for the Media Library.
 *   fields      repeater: control definitions for each item.
 *   title_field repeater: field shown as each item's name in the panel.
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
 *               {{TOP}} {{RIGHT}} {{BOTTOM}} {{LEFT}} {{UNIT}} (dimensions),
 *               {{URL}} (media: a quoted url()), {{DEVICE}} (desktop, tablet
 *               or mobile), and {{other_key.VALUE}} / {{other_key.SIZE}} ...
 *               for another control's value on the same element (for
 *               gradients and filters, which combine several settings).
 *   selectors_dictionary  select: option value => CSS value used for {{VALUE}}.
 *   global_group / global_property  Part of a typography group that can
 *               link to a global font (see Globals).
 *   condition   Show and apply only when other settings match:
 *               [ 'key' => value|[values], 'key!' => value|[values] ].
 *               For a responsive control's tablet and mobile rules, a
 *               responsive condition key is read for that device too.
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

	const ALL_UNITS = array( 'px', '%', 'em', 'rem', 'vw', 'vh', 'deg', 's', 'ms' );

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
			case 'media':
				return array(
					'url' => '',
					'id'  => '',
				);
			case 'url':
				return array(
					'url'         => '',
					'is_external' => '',
					'nofollow'    => '',
				);
			case 'icons':
				return array(
					'value'   => '',
					'library' => '',
				);
			case 'repeater':
			case 'multiselect':
			case 'gallery':
				return array();
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
			if ( 'repeater' === $controls[ $key ]['type'] ) {
				$controls[ $key ]['fields'] = self::for_editor( $control['fields'] ?? array() );
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

		// Custom name shown in the Navigator (Elementor's `_title`).
		if ( isset( $settings['_title'] ) && is_string( $settings['_title'] ) ) {
			$title = mb_substr( sanitize_text_field( $settings['_title'] ), 0, 100 );
			if ( '' !== $title ) {
				$clean['_title'] = $title;
			}
		}

		$globals = self::sanitize_globals( $controls, $settings['__globals__'] ?? array() );
		if ( $globals ) {
			$clean['__globals__'] = $globals;
		}

		return $clean;
	}

	/**
	 * Links from settings to global colors and fonts, in Elementor's shape:
	 * `__globals__: { title_color: "globals/colors?id=primary",
	 *                 typography_typography: "globals/typography?id=text" }`.
	 *
	 * @param mixed $globals Raw links.
	 */
	public static function sanitize_globals( array $controls, $globals ): array {
		$clean = array();
		if ( ! is_array( $globals ) ) {
			return $clean;
		}

		$targets = array(); // setting key => 'colors' | 'typography'
		foreach ( $controls as $key => $control ) {
			if ( 'color' === ( $control['type'] ?? '' ) ) {
				foreach ( self::keys( $key, $control ) as $setting_key ) {
					$targets[ $setting_key ] = 'colors';
				}
			}
			if ( ! empty( $control['global_group'] ) ) {
				$targets[ $control['global_group'] ] = 'typography';
			}
		}

		foreach ( $globals as $key => $ref ) {
			if ( ! is_string( $key ) || ! isset( $targets[ $key ] ) || ! is_string( $ref ) ) {
				continue;
			}
			if ( preg_match( '#^globals/' . $targets[ $key ] . '\?id=[a-z0-9_-]{1,40}$#', $ref ) ) {
				$clean[ $key ] = $ref;
			}
		}
		return $clean;
	}

	/**
	 * The global id a setting links to, or ''.
	 */
	public static function global_id( array $settings, string $key ): string {
		$ref = $settings['__globals__'][ $key ] ?? '';
		return is_string( $ref ) && preg_match( '#\?id=([a-z0-9_-]{1,40})$#', $ref, $m ) ? $m[1] : '';
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
			if ( 'repeater' === ( $control['type'] ?? '' ) && is_array( $settings[ $key ] ) ) {
				foreach ( $settings[ $key ] as $i => $item ) {
					$settings[ $key ][ $i ] = self::with_defaults( $control['fields'] ?? array(), $item );
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

		if ( 'media' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			$id = absint( $value['id'] ?? 0 );
			return array(
				'url' => self::sanitize_url( $value['url'] ?? '', array( 'http', 'https' ) ),
				'id'  => $id ? $id : '',
			);
		}

		if ( 'gallery' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			$clean = array();
			foreach ( array_slice( array_values( $value ), 0, self::MAX_GALLERY ) as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id  = absint( $item['id'] ?? 0 );
				$url = self::sanitize_url( $item['url'] ?? '', array( 'http', 'https' ) );
				if ( $id || '' !== $url ) {
					$clean[] = array(
						'id'  => $id ? $id : '',
						'url' => $url,
					);
				}
			}
			return $clean;
		}

		if ( 'url' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			return array(
				'url'         => self::sanitize_url( $value['url'] ?? '', array( 'http', 'https', 'mailto', 'tel' ) ),
				'is_external' => 'on' === ( $value['is_external'] ?? '' ) ? 'on' : '',
				'nofollow'    => 'on' === ( $value['nofollow'] ?? '' ) ? 'on' : '',
			);
		}

		if ( 'icons' === $type ) {
			return Icons::sanitize( $value );
		}

		if ( 'repeater' === $type ) {
			return self::sanitize_repeater( $control, $value );
		}

		if ( 'multiselect' === $type ) {
			if ( ! is_array( $value ) ) {
				return $default;
			}
			$options = array_map( 'strval', array_keys( $control['options'] ?? array() ) );
			$picked  = array_map( 'strval', array_filter( $value, 'is_scalar' ) );
			return array_values( array_unique( array_intersect( $picked, $options ) ) );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return $default;
		}

		switch ( $type ) {
			case 'code':
				return current_user_can( 'unfiltered_html' ) ? (string) $value : wp_kses_post( (string) $value );

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
				$hex = self::sanitize_color( $value );
				return '' !== $hex ? $hex : $default;

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

	/** Most items a repeater keeps. */
	const MAX_ITEMS = 100;

	/** Most images a gallery control keeps. */
	const MAX_GALLERY = 200;

	/**
	 * Repeater items, each sanitized against the control's fields, with a
	 * unique `_id`.
	 *
	 * @param mixed $value Raw items.
	 */
	public static function sanitize_repeater( array $control, $value ): array {
		if ( ! is_array( $value ) ) {
			return self::default_for( $control );
		}
		$fields = $control['fields'] ?? array();
		$items  = array();
		$seen   = array();
		foreach ( array_slice( array_values( $value ), 0, self::MAX_ITEMS ) as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$item = self::sanitize( $fields, $raw );
			unset( $item['_title'] );
			$id = is_string( $raw['_id'] ?? null ) ? preg_replace( '/[^A-Za-z0-9]/', '', $raw['_id'] ) : '';
			$id = substr( $id, 0, 12 );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				$id = substr( md5( wp_json_encode( $raw ) . count( $items ) ), 0, 7 );
			}
			$seen[ $id ] = true;
			$items[]     = array( '_id' => $id ) + $item;
		}
		return $items;
	}

	/**
	 * A colour as hex: #rgb, #rgba, #rrggbb or #rrggbbaa. rgb() and rgba()
	 * values (common in Elementor data) become #rrggbbaa. Anything else is ''.
	 *
	 * @param mixed $value Raw value.
	 */
	public static function sanitize_color( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = strtolower( trim( $value ) );
		if ( preg_match( '/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([0-9.]+%?)\s*)?\)$/', $value, $m ) ) {
			$hex = '#';
			for ( $i = 1; $i <= 3; $i++ ) {
				$hex .= sprintf( '%02x', min( 255, (int) $m[ $i ] ) );
			}
			if ( isset( $m[4] ) && '' !== $m[4] ) {
				$alpha = '%' === substr( $m[4], -1 ) ? (float) $m[4] / 100 : (float) $m[4];
				$alpha = max( 0, min( 1, $alpha ) );
				if ( $alpha < 1 ) {
					$hex .= sprintf( '%02x', (int) round( $alpha * 255 ) );
				}
			}
			return $hex;
		}
		return '';
	}

	/**
	 * A link or image address: full URLs with an allowed scheme, or a
	 * site-relative path, #anchor or ?query. Anything else becomes ''.
	 *
	 * @param mixed    $url       Raw value.
	 * @param string[] $protocols Allowed schemes.
	 */
	public static function sanitize_url( $url, array $protocols ): string {
		if ( ! is_string( $url ) ) {
			return '';
		}
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		if ( ! in_array( $url[0], array( '/', '#', '?' ), true ) && ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
			$url = 'https://' . $url; // "example.com" typed without a scheme.
		}
		return esc_url_raw( $url, $protocols );
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
