<?php
/**
 * Imports the colours, fonts and custom CSS of the active Elementor kit
 * (Site Settings) into Kbroo Studio's global styles.
 *
 * Both use the same shape and ids, so links in converted pages
 * (`globals/colors?id=...`) keep pointing at the right colour.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Elementor;

use KbrooStudio\Controls;
use KbrooStudio\Fonts;
use KbrooStudio\Globals;

defined( 'ABSPATH' ) || exit;

final class Kit {

	/**
	 * The active kit's settings, or null when there is no kit.
	 */
	public static function settings(): ?array {
		$kit_id = (int) get_option( 'elementor_active_kit' );
		if ( ! $kit_id ) {
			return null;
		}
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		return is_array( $settings ) ? $settings : null;
	}

	public static function exists(): bool {
		return null !== self::settings();
	}

	/**
	 * Copy the kit's colours and fonts into the globals. Items with the
	 * same id are replaced; Kbroo Studio's own extra items are kept.
	 *
	 * @return array{colors: int, typography: int, fonts: string[], css: bool}|null
	 */
	public static function import(): ?array {
		$kit = self::settings();
		if ( null === $kit ) {
			return null;
		}

		$colors = array();
		foreach ( array( 'system_colors', 'custom_colors' ) as $key ) {
			foreach ( (array) ( $kit[ $key ] ?? array() ) as $item ) {
				if ( is_array( $item ) && isset( $item['_id'] ) ) {
					$colors[] = array(
						'_id'   => $item['_id'],
						'title' => $item['title'] ?? '',
						'color' => Controls::sanitize_color( $item['color'] ?? '' ),
					);
				}
			}
		}

		$typography = array();
		$fonts      = array();
		foreach ( array( 'system_typography', 'custom_typography' ) as $key ) {
			foreach ( (array) ( $kit[ $key ] ?? array() ) as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['_id'] ) ) {
					continue;
				}
				$family = (string) ( $item['typography_font_family'] ?? '' );
				if ( '' !== $family && in_array( Fonts::add( $family ), array( 'google', 'local' ), true ) ) {
					$fonts[] = $family;
				}
				$typography[] = $item;
			}
		}

		$current = Globals::get();
		$merged  = array();
		foreach ( array( 'colors' => $colors, 'typography' => $typography ) as $kind => $items ) {
			$ids             = wp_list_pluck( $items, '_id' );
			$merged[ $kind ] = $items;
			foreach ( $current[ $kind ] as $existing ) {
				if ( ! in_array( $existing['_id'], $ids, true ) ) {
					$merged[ $kind ][] = $existing;
				}
			}
		}
		// Elementor Pro's site-wide custom CSS joins any Kbroo Studio already has.
		$css                  = Controls::sanitize_css( $kit['custom_css'] ?? '' );
		$merged['custom_css'] = $current['custom_css'];
		if ( '' !== $css && false === strpos( $current['custom_css'], $css ) ) {
			$merged['custom_css'] = trim( $current['custom_css'] . "\n\n/* From Elementor */\n" . $css );
		}
		Globals::save( $merged );

		return array(
			'colors'     => count( $colors ),
			'typography' => count( $typography ),
			'fonts'      => array_values( array_unique( $fonts ) ),
			'css'        => '' !== $css,
		);
	}
}
