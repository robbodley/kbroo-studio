<?php
/**
 * Show or hide any element by date, day, time of day or whether the visitor
 * is logged in. The rules sit on every element's Advanced tab; an element
 * whose rules say "hidden" is left out of the page altogether.
 *
 * Dates and times are in the site's time zone (Settings > General).
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

class Visibility {

	const DAYS = array( '1', '2', '3', '4', '5', '6', '7' ); // ISO-8601: Monday is 1.

	/**
	 * Controls for the Advanced tab.
	 */
	public static function controls(): array {
		$base = array(
			'tab'       => 'advanced',
			'section'   => __( 'Show and hide', 'kbroo-studio' ),
			'condition' => array( 'display_mode!' => '' ),
		);
		$tz   = wp_timezone_string();

		return array(
			'display_mode'       => array(
				'tab'         => 'advanced',
				'section'     => __( 'Show and hide', 'kbroo-studio' ),
				'type'        => 'select',
				'label'       => __( 'Show this element', 'kbroo-studio' ),
				'default'     => '',
				'options'     => array(
					''     => __( 'Always', 'kbroo-studio' ),
					'show' => __( 'Only when…', 'kbroo-studio' ),
					'hide' => __( 'Except when…', 'kbroo-studio' ),
				),
				'description' => __( 'Fill in any of the rules below; all the ones you fill in must be true. Empty rules are ignored.', 'kbroo-studio' ),
			),
			'display_from'       => $base + array(
				'type'        => 'datetime',
				'label'       => __( 'From', 'kbroo-studio' ),
				'default'     => '',
				/* translators: %s: time zone name. */
				'description' => sprintf( __( 'Dates and times are in the site\'s time zone (%s).', 'kbroo-studio' ), $tz ),
			),
			'display_until'      => $base + array(
				'type'    => 'datetime',
				'label'   => __( 'Until', 'kbroo-studio' ),
				'default' => '',
			),
			'display_days'       => $base + array(
				'type'    => 'multiselect',
				'label'   => __( 'On these days', 'kbroo-studio' ),
				'default' => array(),
				'options' => array(
					'1' => __( 'Monday', 'kbroo-studio' ),
					'2' => __( 'Tuesday', 'kbroo-studio' ),
					'3' => __( 'Wednesday', 'kbroo-studio' ),
					'4' => __( 'Thursday', 'kbroo-studio' ),
					'5' => __( 'Friday', 'kbroo-studio' ),
					'6' => __( 'Saturday', 'kbroo-studio' ),
					'7' => __( 'Sunday', 'kbroo-studio' ),
				),
			),
			'display_time_from'  => $base + array(
				'type'    => 'time',
				'label'   => __( 'Each day from', 'kbroo-studio' ),
				'default' => '',
			),
			'display_time_until' => $base + array(
				'type'        => 'time',
				'label'       => __( 'Each day until', 'kbroo-studio' ),
				'default'     => '',
				'description' => __( 'A time window can run past midnight, e.g. 22:00 until 02:00.', 'kbroo-studio' ),
			),
			'display_login'      => $base + array(
				'type'    => 'select',
				'label'   => __( 'Visitors', 'kbroo-studio' ),
				'default' => '',
				'options' => array(
					''    => __( 'Anyone', 'kbroo-studio' ),
					'in'  => __( 'Logged in', 'kbroo-studio' ),
					'out' => __( 'Logged out', 'kbroo-studio' ),
				),
			),
		);
	}

	/**
	 * Whether any rule is filled in.
	 */
	public static function has_rules( array $settings ): bool {
		if ( ! in_array( $settings['display_mode'] ?? '', array( 'show', 'hide' ), true ) ) {
			return false;
		}
		foreach ( array( 'display_from', 'display_until', 'display_time_from', 'display_time_until', 'display_login' ) as $key ) {
			if ( '' !== (string) ( $settings[ $key ] ?? '' ) ) {
				return true;
			}
		}
		return ! empty( $settings['display_days'] ) && is_array( $settings['display_days'] );
	}

	/**
	 * Whether the element shows now.
	 *
	 * @param array                   $settings  Element settings.
	 * @param \DateTimeImmutable|null $now       Defaults to now, in the site's time zone.
	 * @param bool|null               $logged_in Defaults to is_user_logged_in().
	 */
	public static function visible( array $settings, ?\DateTimeImmutable $now = null, ?bool $logged_in = null ): bool {
		if ( ! self::has_rules( $settings ) ) {
			return true;
		}
		$now       = $now ? $now->setTimezone( wp_timezone() ) : new \DateTimeImmutable( 'now', wp_timezone() );
		$logged_in = null === $logged_in ? is_user_logged_in() : $logged_in;
		$match     = self::rules_match( $settings, $now, $logged_in );
		return 'show' === $settings['display_mode'] ? $match : ! $match;
	}

	private static function rules_match( array $settings, \DateTimeImmutable $now, bool $logged_in ): bool {
		$stamp = $now->format( 'Y-m-d\TH:i' );
		$from  = (string) ( $settings['display_from'] ?? '' );
		$until = (string) ( $settings['display_until'] ?? '' );
		if ( '' !== $from && $stamp < $from ) {
			return false;
		}
		if ( '' !== $until && $stamp >= $until ) {
			return false;
		}

		$days = isset( $settings['display_days'] ) && is_array( $settings['display_days'] ) ? array_map( 'strval', $settings['display_days'] ) : array();
		if ( $days && ! in_array( $now->format( 'N' ), $days, true ) ) {
			return false;
		}

		$time  = $now->format( 'H:i' );
		$start = (string) ( $settings['display_time_from'] ?? '' );
		$end   = (string) ( $settings['display_time_until'] ?? '' );
		if ( '' !== $start || '' !== $end ) {
			$start = '' !== $start ? $start : '00:00';
			$end   = '' !== $end ? $end : '24:00';
			$in    = $start <= $end
				? ( $time >= $start && $time < $end )
				: ( $time >= $start || $time < $end ); // Past midnight.
			if ( ! $in ) {
				return false;
			}
		}

		$login = (string) ( $settings['display_login'] ?? '' );
		if ( ( 'in' === $login && ! $logged_in ) || ( 'out' === $login && $logged_in ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Whether a layout has any element with date or time rules, so the page
	 * changes by itself and should not be kept in a page cache for long.
	 */
	public static function layout_has_rules( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( self::has_rules( (array) ( $element['settings'] ?? array() ) ) ) {
				return true;
			}
			if ( self::layout_has_rules( (array) ( $element['elements'] ?? array() ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * "2026-11-20T09:30" or '' for anything else.
	 */
	public static function sanitize_datetime( $value ): string {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) && (int) $m[4] < 24 && (int) $m[5] < 60 ) {
			return sprintf( '%s-%s-%sT%s:%s', $m[1], $m[2], $m[3], $m[4], $m[5] );
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return $value . 'T00:00';
		}
		return '';
	}

	/**
	 * "09:30" or '' for anything else.
	 */
	public static function sanitize_time( $value ): string {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{1,2}):(\d{2})/', $value, $m ) && (int) $m[1] < 24 && (int) $m[2] < 60 ) {
			return sprintf( '%02d:%s', (int) $m[1], $m[2] );
		}
		return '';
	}

	/**
	 * Premium Addons' Display Conditions, as far as Kbroo Studio's rules can
	 * hold them. Returns [ settings, list of what could not be carried over ].
	 * Anything that cannot be matched leaves the element always shown.
	 *
	 * @return array{0: array, 1: string[]}
	 */
	public static function from_premium_addons( array $settings ): array {
		$out     = array();
		$dropped = array();
		if ( 'yes' !== ( $settings['pa_display_conditions_switcher'] ?? '' ) ) {
			return array( $out, $dropped );
		}
		$items = isset( $settings['pa_condition_repeater'] ) && is_array( $settings['pa_condition_repeater'] ) ? $settings['pa_condition_repeater'] : array();
		$items = array_values(
			array_filter(
				$items,
				function ( $item ) {
					return is_array( $item ) && '' !== (string) ( $item['pa_condition_key'] ?? '' );
				}
			)
		);
		if ( ! $items ) {
			return array( $out, $dropped );
		}
		if ( count( $items ) > 1 && 'any' === ( $settings['pa_display_when'] ?? 'any' ) ) {
			return array( $out, array( 'display conditions matching "any" of several rules' ) );
		}

		foreach ( $items as $item ) {
			$key = (string) $item['pa_condition_key'];
			if ( 'not' === ( $item['pa_condition_operator'] ?? 'is' ) ) {
				$dropped[] = 'display condition "' . $key . ' is not"';
				continue;
			}
			$value = $item[ 'pa_condition_' . $key ] ?? '';
			switch ( $key ) {
				case 'date_range':
					$parts = is_string( $value ) ? preg_split( '/\s+(to|-)\s+/', trim( $value ) ) : array();
					$from  = self::sanitize_datetime( $parts[0] ?? '' );
					$until = self::sanitize_datetime( $parts[1] ?? '' );
					if ( '' === $from && '' === $until ) {
						$dropped[] = 'display condition "date range" (unreadable dates)';
						break;
					}
					$out['display_from'] = $from;
					// A date range runs to the end of its last day.
					$out['display_until'] = '' !== $until && preg_match( '/^\d{4}-\d{2}-\d{2}$/', trim( (string) ( $parts[1] ?? '' ) ) )
						? gmdate( 'Y-m-d\TH:i', strtotime( substr( $until, 0, 10 ) . ' +1 day' ) )
						: $until;
					break;
				case 'date':
					$day = self::sanitize_datetime( is_string( $value ) ? substr( trim( $value ), 0, 10 ) : '' );
					if ( '' === $day ) {
						$dropped[] = 'display condition "date" (unreadable date)';
						break;
					}
					$out['display_from']  = $day;
					$out['display_until'] = gmdate( 'Y-m-d\TH:i', strtotime( substr( $day, 0, 10 ) . ' +1 day' ) );
					break;
				case 'day':
					$names = array( 'monday' => '1', 'tuesday' => '2', 'wednesday' => '3', 'thursday' => '4', 'friday' => '5', 'saturday' => '6', 'sunday' => '7' );
					$days  = array();
					foreach ( (array) $value as $name ) {
						$name = strtolower( (string) $name );
						if ( isset( $names[ $name ] ) ) {
							$days[] = $names[ $name ];
						} elseif ( in_array( $name, self::DAYS, true ) ) {
							$days[] = $name;
						}
					}
					if ( ! $days ) {
						$dropped[] = 'display condition "day" (unreadable days)';
						break;
					}
					$out['display_days'] = array_values( array_unique( $days ) );
					break;
				case 'time_range':
					$parts = is_string( $value ) ? preg_split( '/\s+(to|-)\s+/', trim( $value ) ) : array();
					$from  = self::sanitize_time( $item['pa_condition_time_range_from'] ?? ( $parts[0] ?? '' ) );
					$until = self::sanitize_time( $item['pa_condition_time_range_to'] ?? ( $parts[1] ?? '' ) );
					if ( '' === $from && '' === $until ) {
						$dropped[] = 'display condition "time range" (unreadable times)';
						break;
					}
					$out['display_time_from']  = $from;
					$out['display_time_until'] = $until;
					break;
				case 'login_status':
					$out['display_login'] = false !== strpos( (string) $value, 'not' ) || false !== strpos( (string) $value, 'out' ) ? 'out' : 'in';
					break;
				default:
					$dropped[] = 'display condition "' . $key . '"';
			}
		}

		if ( $dropped ) {
			// Half a rule set could show something meant to be hidden, or the
			// other way round: keep the element always shown and report it.
			return array( array(), $dropped );
		}
		$out['display_mode'] = 'hide' === ( $settings['pa_display_action'] ?? 'show' ) ? 'hide' : 'show';
		return array( $out, $dropped );
	}
}
