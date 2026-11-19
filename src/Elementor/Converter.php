<?php
/**
 * Converts Elementor page data to a Kbroo Studio layout.
 *
 * Kbroo Studio uses Elementor's tree shape and setting names, so most of the
 * work is copying: sections, columns, containers and the widgets Kbroo
 * Studio has keep their settings, and Layout::sanitize() drops whatever
 * Kbroo Studio does not support. This class fixes the places where the
 * two differ, swaps widgets Kbroo Studio does not have yet for fallback
 * blocks, and writes a report of everything that did not carry over.
 *
 * Elementor's own data is only read, never changed.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Elementor;

use KbrooStudio\ControlGroups;
use KbrooStudio\Controls;
use KbrooStudio\Fonts;
use KbrooStudio\Layout;
use KbrooStudio\Widgets\ElementorFallback;
use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Converter {

	/** Friendly names for Elementor widgets that become fallback blocks. */
	const WIDGET_LABELS = array(
		'form'                   => 'Form',
		'posts'                  => 'Posts',
		'testimonial-carousel'   => 'Testimonial carousel',
		'icon'                   => 'Icon',
		'icon-list'              => 'Icon list',
		'icon-box'               => 'Icon box',
		'image-box'              => 'Image box',
		'social-icons'           => 'Social icons',
		'image-gallery'          => 'Image gallery',
		'image-carousel'         => 'Image carousel',
		'video'                  => 'Video',
		'wp-widget-categories'   => 'Categories (WordPress widget)',
		'wp-widget-recent-posts' => 'Recent posts (WordPress widget)',
	);

	/** Elementor settings with no visible effect, left out of the report. */
	const QUIET_SETTINGS = array( '_column_size', 'structure', 'typography_typography', 'background_background', 'pa_condition_repeater', '_skin', 'slides_name' );

	/** @var Registry */
	private $widgets;

	/** @var array */
	private $report;

	/** @var int */
	private $source_post = 0;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	/**
	 * The `_elementor_data` of a post, decoded, or null if it has none.
	 */
	public static function post_data( int $post_id ): ?array {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) && $data ? $data : null;
	}

	/**
	 * Convert a list of Elementor elements.
	 *
	 * @param array $elements    Elementor elements (the page's top level).
	 * @param int   $source_post Post the data came from, for live fallbacks.
	 * @return array{layout: array, report: array}
	 */
	public function convert( array $elements, int $source_post = 0 ): array {
		$this->source_post = $source_post;
		$this->report      = array(
			'converted_at' => gmdate( 'c' ),
			'source_post'  => $source_post,
			'elements'     => 0,
			'widgets'      => array(),
			'fallbacks'    => array(),
			'dropped'      => array(),
			'fonts_added'  => array(),
			'fonts_local'  => array(),
		);

		$this->add_fonts( $elements );

		$converted = array_values( array_filter( array_map( array( $this, 'element' ), $elements ) ) );
		$layout    = Layout::sanitize( array( 'elements' => $converted ), $this->widgets );

		arsort( $this->report['dropped'] );
		ksort( $this->report['widgets'] );
		return array(
			'layout' => $layout,
			'report' => $this->report,
		);
	}

	/**
	 * Make the fonts the page uses available before any setting is checked
	 * against the font list.
	 */
	private function add_fonts( array $elements ): void {
		$names = array();
		array_walk_recursive(
			$elements,
			function ( $value, $key ) use ( &$names ) {
				if ( is_string( $key ) && preg_match( '/_font_family(_tablet|_mobile)?$/', $key ) && is_string( $value ) && '' !== $value ) {
					$names[ $value ] = true;
				}
			}
		);
		foreach ( array_keys( $names ) as $name ) {
			$result = Fonts::add( $name );
			if ( 'google' === $result ) {
				$this->report['fonts_added'][] = $name;
			} elseif ( 'local' === $result ) {
				$this->report['fonts_local'][] = $name;
			}
		}
		if ( $this->report['fonts_added'] || $this->report['fonts_local'] ) {
			$this->widgets->flush_controls();
		}
	}

	/**
	 * @param mixed $element Elementor element.
	 */
	private function element( $element ): ?array {
		if ( ! is_array( $element ) ) {
			return null;
		}
		$type     = (string) ( $element['elType'] ?? '' );
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

		if ( 'widget' === $type ) {
			return $this->widget( $element, $settings );
		}
		if ( ! in_array( $type, array( 'section', 'column', 'container' ), true ) ) {
			return null;
		}

		$settings = $this->common( $settings );
		if ( 'section' === $type ) {
			$settings = $this->section( $settings );
		} elseif ( 'column' === $type ) {
			$settings = $this->column( $settings );
		} else {
			$settings = $this->container( $settings, ! empty( $element['isInner'] ) );
		}

		$this->note_dropped( $settings, Layout::controls_for( array( 'elType' => $type ), $this->widgets ) );
		$this->report['elements']++;

		$out = array(
			'id'       => (string) ( $element['id'] ?? '' ),
			'elType'   => $type,
			'isInner'  => ! empty( $element['isInner'] ),
			'settings' => $settings,
			'elements' => array_values( array_filter( array_map( array( $this, 'element' ), (array) ( $element['elements'] ?? array() ) ) ) ),
		);
		return $out;
	}

	/**
	 * Settings every element type shares.
	 */
	private function common( array $settings ): array {
		// Elementor stores "hidden-desktop" etc.; Kbroo Studio's switchers use "yes".
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( ! empty( $settings[ 'hide_' . $device ] ) ) {
				$settings[ 'hide_' . $device ] = 'yes';
			}
		}
		// Colours in rgba() become #rrggbbaa (see Controls::sanitize_color()).
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'rgb' ) ) {
				$color = Controls::sanitize_color( $value );
				if ( '' !== $color ) {
					$settings[ $key ] = $color;
				}
			}
			// Elementor's placeholder image left in a background: its file goes
			// when Elementor does, and it was never meant to show.
			if ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) && false !== strpos( $value['url'], '/elementor/assets/images/placeholder' ) ) {
				$settings[ $key ] = array(
					'url' => '',
					'id'  => '',
				);
				continue;
			}
			// Sliders saved empty come through as { size: null }.
			if ( is_array( $value ) && array_key_exists( 'size', $value ) && null === $value['size'] ) {
				$settings[ $key ]['size'] = '';
			}
		}
		foreach ( array( '_animation', 'animation' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] && 'none' !== $settings[ $key ] ) {
				$settings[ $key ] = $this->animation( $settings[ $key ] );
			}
		}
		if ( isset( $settings['hover_animation'] ) && is_string( $settings['hover_animation'] ) && '' !== $settings['hover_animation']
			&& ! isset( ControlGroups::HOVER_ANIMATIONS[ $settings['hover_animation'] ] ) ) {
			$this->drop( 'hover animation ' . $settings['hover_animation'] );
			$settings['hover_animation'] = '';
		}
		return $settings;
	}

	/**
	 * The nearest entrance animation Kbroo Studio has to an Elementor one.
	 * Ones that are not the same are noted in the report.
	 */
	private function animation( string $name ): string {
		if ( in_array( $name, ControlGroups::ENTRANCE_ANIMATIONS, true ) ) {
			return $name;
		}
		$near = 'fadeIn';
		foreach ( ControlGroups::ENTRANCE_ANIMATIONS as $known ) {
			if ( 0 === strpos( $name, $known ) && strlen( $known ) > strlen( $near ) ) {
				$near = $known; // fadeInUpBig becomes fadeInUp.
			}
		}
		if ( 0 === strpos( $name, 'zoomIn' ) ) {
			$near = 'zoomIn';
		}
		$this->drop( 'animation ' . $name . ' (shown as ' . $near . ')' );
		return $near;
	}

	private function section( array $settings ): array {
		// Elementor sections have no padding unless set; Kbroo's default is 40px.
		if ( ! isset( $settings['padding'] ) ) {
			$settings['padding'] = self::empty_dimensions();
		}
		// Height: "full" screen or a minimum height.
		$height = $settings['height'] ?? '';
		if ( 'full' === $height ) {
			$settings['min_height'] = array(
				'unit' => 'vh',
				'size' => 100,
			);
		} elseif ( 'min-height' === $height ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
				if ( isset( $settings[ 'custom_height' . $suffix ] ) ) {
					$settings[ 'min_height' . $suffix ] = $settings[ 'custom_height' . $suffix ];
				}
			}
		}
		unset( $settings['height'], $settings['custom_height'], $settings['custom_height_tablet'], $settings['custom_height_mobile'] );
		return $settings;
	}

	private function column( array $settings ): array {
		// _inline_size is only stored once a column was resized.
		if ( ! isset( $settings['_inline_size'] ) || '' === $settings['_inline_size'] || null === $settings['_inline_size'] ) {
			$settings['_inline_size'] = isset( $settings['_column_size'] ) ? (float) $settings['_column_size'] : 100;
		}
		foreach ( array( '_inline_size_tablet', '_inline_size_mobile' ) as $key ) {
			if ( array_key_exists( $key, $settings ) && null === $settings[ $key ] ) {
				unset( $settings[ $key ] );
			}
		}
		return $settings;
	}

	private function container( array $settings, bool $inner ): array {
		// Elementor's default: boxed at the top level, full width when nested.
		if ( empty( $settings['content_width'] ) ) {
			$settings['content_width'] = $inner ? 'full' : 'boxed';
		}
		// The gap control stores { column, row, size?, unit }; use the column gap.
		foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
			$gap = $settings[ 'flex_gap' . $suffix ] ?? null;
			if ( is_array( $gap ) ) {
				$size = $gap['size'] ?? '';
				if ( '' === $size || null === $size ) {
					$size = $gap['column'] ?? '';
				}
				$settings[ 'flex_gap' . $suffix ] = array(
					'unit' => $gap['unit'] ?? 'px',
					'size' => $size,
				);
			}
		}
		return $settings;
	}

	private function widget( array $element, array $settings ): ?array {
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( '' === $type ) {
			return null;
		}
		$this->report['elements']++;
		$this->report['widgets'][ $type ] = ( $this->report['widgets'][ $type ] ?? 0 ) + 1;

		$widget = $this->widgets->get( $type );
		if ( $widget && 'elementor-fallback' !== $type ) {
			$settings = $this->common( $settings );
			$settings = $this->legacy_icons( $type, $settings );
			if ( 'posts' === $type ) {
				$settings = $this->posts( $settings );
			}
			if ( 'form' === $type ) {
				$settings = $this->form( $settings );
			}
			if ( 'divider' === $type && 'line_icon' === ( $settings['look'] ?? '' ) ) {
				$settings['look'] = 'line';
				$this->drop( 'divider icon' );
			}
			$this->note_dropped( $settings, $widget->get_controls() );
			return array(
				'id'         => (string) ( $element['id'] ?? '' ),
				'elType'     => 'widget',
				'widgetType' => $type,
				'settings'   => $settings,
			);
		}

		// Not supported yet: keep the original and show it as it was.
		$label = self::WIDGET_LABELS[ $type ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $type ) );
		$json  = wp_json_encode( $element );
		$this->report['fallbacks'][] = array(
			'id'    => (string) ( $element['id'] ?? '' ),
			'type'  => $type,
			'label' => $label,
		);

		$keep = array();
		foreach ( array( '_margin', '_margin_tablet', '_margin_mobile', '_padding', '_padding_tablet', '_padding_mobile', '_element_id', '_css_classes', 'hide_desktop', 'hide_tablet', 'hide_mobile' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$keep[ $key ] = $settings[ $key ];
			}
		}
		return array(
			'id'         => (string) ( $element['id'] ?? '' ),
			'elType'     => 'widget',
			'widgetType' => 'elementor-fallback',
			'settings'   => $this->common( $keep ) + array(
				/* translators: %s: Elementor widget name. */
				'_title'         => sprintf( __( '%s (not converted)', 'kbroo-studio' ), $label ),
				'element_type'   => $type,
				'element_label'  => $label,
				'source_post'    => $this->source_post,
				'elementor_data' => $json,
				'snapshot'       => ElementorFallback::render_with_elementor( $json, $this->source_post ),
			),
		);
	}

	/**
	 * Elementor's posts widget prefixes most settings with its skin
	 * (classic_columns, cards_meta_data); Kbroo Studio's does not.
	 */
	private function posts( array $settings ): array {
		$skin  = in_array( $settings['_skin'] ?? '', array( 'classic', 'cards' ), true ) ? $settings['_skin'] : 'classic';
		$clean = array( '_skin' => $skin );
		foreach ( $settings as $key => $value ) {
			if ( preg_match( '/^(classic|cards|full_content)_(.+)$/', $key, $m ) ) {
				if ( $m[1] === $skin ) {
					$clean[ $m[2] ] = $value;
				}
				continue; // Another skin's settings were never shown.
			}
			$clean[ $key ] = $value;
		}
		// Elementor's defaults where they differ from Kbroo Studio's.
		$clean += array(
			'meta_data'      => array( 'date', 'comments' ),
			'meta_separator' => 'cards' === $skin ? '•' : '///',
		);
		if ( 'cards' === $skin && isset( $clean['show_image'] ) ) {
			$clean['thumbnail'] = 'yes' === $clean['show_image'] ? 'top' : 'none';
		}
		unset( $clean['show_image'] );
		if ( ! in_array( 'terms', (array) ( $clean['posts_include'] ?? array() ), true ) ) {
			unset( $clean['posts_include_term_ids'] );
		}
		if ( ! in_array( 'terms', (array) ( $clean['posts_exclude'] ?? array() ), true ) ) {
			unset( $clean['posts_exclude_term_ids'] );
		}
		unset( $clean['posts_include'], $clean['posts_exclude'], $clean['text'], $clean['load_more_no_posts_custom_message'] );
		$type = (string) ( $clean['pagination_type'] ?? '' );
		if ( 0 === strpos( $type, 'load_more' ) ) {
			$clean['pagination_type'] = 'numbers';
			$this->drop( 'posts "load more" button (shown as page numbers)' );
		}
		foreach ( array( 'pagination_prev_label', 'pagination_next_label', 'read_more_text' ) as $key ) {
			if ( isset( $clean[ $key ] ) && is_string( $clean[ $key ] ) ) {
				$clean[ $key ] = html_entity_decode( $clean[ $key ], ENT_QUOTES, 'UTF-8' );
			}
		}
		return $clean;
	}

	/**
	 * Elementor's form stores ticked boxes in fields as "true".
	 */
	private function form( array $settings ): array {
		foreach ( (array) ( $settings['form_fields'] ?? array() ) as $i => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			foreach ( array( 'required', 'allow_multiple', 'inline_list' ) as $key ) {
				if ( isset( $field[ $key ] ) ) {
					$field[ $key ] = in_array( $field[ $key ], array( 'true', 'yes', true ), true ) ? 'yes' : '';
				}
			}
			unset( $field['inline_list'] );
			$settings['form_fields'][ $i ] = $field + array( 'field_type' => 'text' );
		}
		$actions = $settings['submit_actions'] ?? array( 'email' );
		if ( is_array( $actions ) && ! in_array( 'email', $actions, true ) ) {
			$this->drop( 'form without an email action (Kbroo Studio emails every form)' );
		}
		unset( $settings['submit_actions'], $settings['email_content_2'], $settings['step_next_label'], $settings['step_previous_label'] );
		return $settings;
	}

	/**
	 * Icons saved by older Elementor versions as a class string ("fa fa-star")
	 * become the current { value, library } shape.
	 */
	private function legacy_icons( string $type, array $settings ): array {
		$pairs = array(
			'icon'         => array( 'icon', 'selected_icon' ),
			'social-icons' => array( 'social', 'social_icon' ),
		);
		if ( ! isset( $pairs[ $type ] ) ) {
			return $settings;
		}
		list( $old, $new ) = $pairs[ $type ];
		$upgrade = function ( array $values ) use ( $old, $new ) {
			if ( empty( $values[ $new ]['value'] ) && ! empty( $values[ $old ] ) && is_string( $values[ $old ] ) ) {
				$values[ $new ] = array(
					'value'   => $values[ $old ],
					'library' => '',
				);
			}
			unset( $values[ $old ] );
			return $values;
		};
		if ( 'social-icons' === $type ) {
			foreach ( (array) ( $settings['social_icon_list'] ?? array() ) as $i => $item ) {
				if ( is_array( $item ) ) {
					$settings['social_icon_list'][ $i ] = $upgrade( $item );
				}
			}
			return $settings;
		}
		return $upgrade( $settings );
	}

	/**
	 * Count settings that have a value but no Kbroo Studio control.
	 */
	private function note_dropped( array $settings, array $controls ): void {
		$known = array( '__globals__' => true, '_title' => true );
		foreach ( $controls as $key => $control ) {
			foreach ( Controls::keys( $key, $control ) as $setting_key ) {
				$known[ $setting_key ] = true;
			}
			if ( ! empty( $control['global_group'] ) ) {
				$known[ $control['global_group'] ] = true;
			}
		}
		foreach ( $settings as $key => $value ) {
			if ( isset( $known[ $key ] ) || in_array( $key, self::QUIET_SETTINGS, true ) || self::is_empty( $value ) ) {
				continue;
			}
			$this->drop( (string) preg_replace( '/_(tablet|mobile)$/', '', (string) $key ) );
		}
	}

	private function drop( string $name ): void {
		$this->report['dropped'][ $name ] = ( $this->report['dropped'][ $name ] ?? 0 ) + 1;
	}

	/**
	 * @param mixed $value Setting value.
	 */
	private static function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $part ) {
				if ( in_array( $key, array( 'unit', 'isLinked', 'sizes' ), true ) ) {
					continue;
				}
				if ( ! self::is_empty( $part ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || '' === $value || false === $value;
	}

	private static function empty_dimensions(): array {
		return array(
			'unit'     => 'px',
			'top'      => '',
			'right'    => '',
			'bottom'   => '',
			'left'     => '',
			'isLinked' => true,
		);
	}
}
