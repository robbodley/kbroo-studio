<?php
/**
 * Layout schema and sanitization.
 *
 * A layout is a tree, in Elementor's shape:
 *
 *     {
 *       "version": 1,
 *       "elements": [                       // sections
 *         { "id": "a1b2c3d", "elType": "section", "settings": {...},
 *           "elements": [                   // columns
 *             { "id": "...", "elType": "column", "settings": { "width": 50 },
 *               "elements": [               // widgets
 *                 { "id": "...", "elType": "widget", "widgetType": "heading",
 *                   "settings": { "text": "Hello" } }
 *               ] } ] } ]
 *     }
 *
 * What may hold what:
 *
 *     page       sections, containers
 *     section    columns (at most MAX_COLUMNS)
 *     column     widgets, inner sections (`isInner`, not inside another
 *                inner section), containers
 *     container  widgets, containers (`isInner` when nested)
 *
 * Everything that comes from the editor passes through sanitize() before it
 * is stored, so the renderer can trust the shape of the tree.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Layout {

	/** Background options for sections and containers. */
	const WITH_VIDEO = array(
		'types' => array( 'classic', 'gradient', 'video' ),
		'hover' => '{{WRAPPER}}:hover',
	);

	const VERSION     = 1;
	const MAX_COLUMNS = 6;
	const MAX_DEPTH   = 12; // Element levels, counting columns.

	public static function empty_layout(): array {
		return array(
			'version'  => self::VERSION,
			'elements' => array(),
		);
	}

	/**
	 * Controls for sections. Names follow Elementor's section settings.
	 */
	public static function section_controls(): array {
		$layout = array( 'section' => __( 'Layout', 'kbroo-studio' ) );

		return array_merge(
			array(
				'layout'           => $layout + array(
					'type'    => 'select',
					'label'   => __( 'Content width', 'kbroo-studio' ),
					'default' => 'boxed',
					'options' => array(
						'boxed'      => __( 'Boxed', 'kbroo-studio' ),
						'full_width' => __( 'Full width', 'kbroo-studio' ),
					),
				),
				'content_width'    => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Max width', 'kbroo-studio' ),
					'units'      => array( 'px', 'vw', '%' ),
					'default'    => array(
						'unit' => 'px',
						'size' => '',
					),
					'responsive' => true,
					'condition'  => array( 'layout' => 'boxed' ),
					'selectors'  => array( '{{WRAPPER}} > .kbroo-container' => 'max-width: {{SIZE}}{{UNIT}};' ),
				),
				'gap'              => $layout + array(
					'type'                 => 'select',
					'label'                => __( 'Columns gap', 'kbroo-studio' ),
					'default'              => 'default',
					'options'              => array(
						'default'  => __( 'Default', 'kbroo-studio' ),
						'no'       => __( 'No gap', 'kbroo-studio' ),
						'narrow'   => __( 'Narrow', 'kbroo-studio' ),
						'extended' => __( 'Extended', 'kbroo-studio' ),
						'wide'     => __( 'Wide', 'kbroo-studio' ),
						'wider'    => __( 'Wider', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'default'  => '10px',
						'no'       => '0px',
						'narrow'   => '5px',
						'extended' => '15px',
						'wide'     => '20px',
						'wider'    => '30px',
					),
					'selectors'            => array( '{{WRAPPER}}' => '--kbroo-gap: {{VALUE}};' ),
				),
				'min_height'       => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Minimum height', 'kbroo-studio' ),
					'units'      => array( 'px', 'vh' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} > .kbroo-container > .kbroo-row' => 'min-height: {{SIZE}}{{UNIT}};' ),
				),
				'content_position' => $layout + array(
					'type'                 => 'select',
					'label'                => __( 'Vertical align', 'kbroo-studio' ),
					'default'              => '',
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'top'    => __( 'Top', 'kbroo-studio' ),
						'middle' => __( 'Middle', 'kbroo-studio' ),
						'bottom' => __( 'Bottom', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'top'    => 'flex-start',
						'middle' => 'center',
						'bottom' => 'flex-end',
					),
					'selectors'            => array( '{{WRAPPER}} > .kbroo-container > .kbroo-row' => 'align-items: {{VALUE}};' ),
				),
				'html_tag'         => $layout + array(
					'type'    => 'select',
					'label'   => __( 'HTML tag', 'kbroo-studio' ),
					'default' => 'section',
					'options' => array(
						'section' => 'section',
						'header'  => 'header',
						'footer'  => 'footer',
						'main'    => 'main',
						'article' => 'article',
						'aside'   => 'aside',
						'div'     => 'div',
					),
				),
			),
			ControlGroups::background( '{{WRAPPER}}', array(), 'background', self::WITH_VIDEO ),
			ControlGroups::overlay( '{{WRAPPER}}::before', '{{WRAPPER}}:hover::before' ),
			ControlGroups::border( '{{WRAPPER}}' ),
			ControlGroups::advanced(
				'',
				'{{WRAPPER}}',
				array(
					'unit'     => 'px',
					'top'      => 40,
					'right'    => 0,
					'bottom'   => 40,
					'left'     => 0,
					'isLinked' => false,
				)
			)
		);
	}

	/**
	 * Controls for columns. Names follow Elementor's column settings.
	 */
	public static function column_controls(): array {
		$layout = array( 'section' => __( 'Layout', 'kbroo-studio' ) );
		$inner  = '{{WRAPPER}} > .kbroo-column-inner';

		return array_merge(
			array(
				'_inline_size'          => $layout + array(
					'type'           => 'number',
					'label'          => __( 'Width (%)', 'kbroo-studio' ),
					'default'        => 100,
					'default_mobile' => 100,
					'min'            => 2,
					'max'            => 100,
					'responsive'     => true,
					'selectors'      => array( '{{WRAPPER}}' => 'width: {{VALUE}}%;' ),
				),
				'content_position'      => $layout + array(
					'type'                 => 'select',
					'label'                => __( 'Vertical align', 'kbroo-studio' ),
					'default'              => '',
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'top'    => __( 'Top', 'kbroo-studio' ),
						'center' => __( 'Middle', 'kbroo-studio' ),
						'bottom' => __( 'Bottom', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'top'    => 'flex-start',
						'center' => 'center',
						'bottom' => 'flex-end',
					),
					'selectors'            => array( $inner => 'justify-content: {{VALUE}};' ),
				),
				'space_between_widgets' => $layout + array(
					'type'      => 'slider',
					'label'     => __( 'Widgets space', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( $inner => 'gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::background(
				$inner,
				array(),
				'background',
				array( 'hover' => '{{WRAPPER}}:hover > .kbroo-column-inner' )
			),
			ControlGroups::overlay( $inner . '::before', '{{WRAPPER}}:hover > .kbroo-column-inner::before' ),
			ControlGroups::border( $inner ),
			ControlGroups::advanced( '', $inner )
		);
	}

	/**
	 * Controls for flexbox containers. Names follow Elementor's container
	 * settings.
	 */
	public static function container_controls(): array {
		$layout = array( 'section' => __( 'Container', 'kbroo-studio' ) );
		$items  = array( 'section' => __( 'Items', 'kbroo-studio' ) );
		$inner  = '{{WRAPPER}} > .kbroo-con-inner';

		return array_merge(
			array(
				'content_width'        => $layout + array(
					'type'    => 'select',
					'label'   => __( 'Content width', 'kbroo-studio' ),
					'default' => 'boxed',
					'options' => array(
						'boxed' => __( 'Boxed', 'kbroo-studio' ),
						'full'  => __( 'Full width', 'kbroo-studio' ),
					),
				),
				'boxed_width'          => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Boxed width', 'kbroo-studio' ),
					'units'      => array( 'px', '%', 'vw' ),
					'responsive' => true,
					'condition'  => array( 'content_width' => 'boxed' ),
					'selectors'  => array( $inner => 'max-width: {{SIZE}}{{UNIT}};' ),
				),
				'width'                => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Width', 'kbroo-studio' ),
					'units'      => array( '%', 'px', 'vw' ),
					'responsive' => true,
					// One variable per device, so a nested container still
					// goes full width on phones unless it sets a mobile width.
					'selectors'  => array( '{{WRAPPER}}' => '--kbroo-con-width-{{DEVICE}}: {{SIZE}}{{UNIT}};' ),
				),
				'min_height'           => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Minimum height', 'kbroo-studio' ),
					'units'      => array( 'px', 'vh' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}}' => 'min-height: {{SIZE}}{{UNIT}};' ),
				),
				'html_tag'             => $layout + array(
					'type'    => 'select',
					'label'   => __( 'HTML tag', 'kbroo-studio' ),
					'default' => 'div',
					'options' => array(
						'div'     => 'div',
						'section' => 'section',
						'header'  => 'header',
						'footer'  => 'footer',
						'main'    => 'main',
						'article' => 'article',
						'aside'   => 'aside',
						'nav'     => 'nav',
					),
				),
				'flex_direction'       => $items + array(
					'type'       => 'select',
					'label'      => __( 'Direction', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''               => __( 'Default (column)', 'kbroo-studio' ),
						'row'            => __( 'Row', 'kbroo-studio' ),
						'column'         => __( 'Column', 'kbroo-studio' ),
						'row-reverse'    => __( 'Row, reversed', 'kbroo-studio' ),
						'column-reverse' => __( 'Column, reversed', 'kbroo-studio' ),
					),
					'selectors'  => array( $inner => 'flex-direction: {{VALUE}};' ),
				),
				'flex_justify_content' => $items + array(
					'type'       => 'select',
					'label'      => __( 'Justify content', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''              => __( 'Default', 'kbroo-studio' ),
						'flex-start'    => __( 'Start', 'kbroo-studio' ),
						'center'        => __( 'Center', 'kbroo-studio' ),
						'flex-end'      => __( 'End', 'kbroo-studio' ),
						'space-between' => __( 'Space between', 'kbroo-studio' ),
						'space-around'  => __( 'Space around', 'kbroo-studio' ),
						'space-evenly'  => __( 'Space evenly', 'kbroo-studio' ),
					),
					'selectors'  => array( $inner => 'justify-content: {{VALUE}};' ),
				),
				'flex_align_items'     => $items + array(
					'type'       => 'select',
					'label'      => __( 'Align items', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''           => __( 'Default', 'kbroo-studio' ),
						'flex-start' => __( 'Start', 'kbroo-studio' ),
						'center'     => __( 'Center', 'kbroo-studio' ),
						'flex-end'   => __( 'End', 'kbroo-studio' ),
						'stretch'    => __( 'Stretch', 'kbroo-studio' ),
					),
					'selectors'  => array( $inner => 'align-items: {{VALUE}};' ),
				),
				'flex_gap'             => $items + array(
					'type'       => 'slider',
					'label'      => __( 'Gap', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', '%' ),
					'responsive' => true,
					'selectors'  => array( $inner => 'gap: {{SIZE}}{{UNIT}};' ),
				),
				'flex_wrap'            => $items + array(
					'type'       => 'select',
					'label'      => __( 'Wrap', 'kbroo-studio' ),
					'default'    => '',
					'responsive' => true,
					'options'    => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'nowrap' => __( 'No wrap', 'kbroo-studio' ),
						'wrap'   => __( 'Wrap', 'kbroo-studio' ),
					),
					'selectors'  => array( $inner => 'flex-wrap: {{VALUE}};' ),
				),
			),
			ControlGroups::background( '{{WRAPPER}}', array(), 'background', self::WITH_VIDEO ),
			ControlGroups::overlay( '{{WRAPPER}}::before', '{{WRAPPER}}:hover::before' ),
			ControlGroups::border( '{{WRAPPER}}' ),
			ControlGroups::advanced( '' )
		);
	}

	/**
	 * Controls for any element in the tree.
	 */
	public static function controls_for( array $element, Registry $widgets ): array {
		switch ( $element['elType'] ?? '' ) {
			case 'section':
				return self::section_controls();
			case 'column':
				return self::column_controls();
			case 'container':
				return self::container_controls();
			case 'widget':
				$widget = $widgets->get( (string) ( $element['widgetType'] ?? '' ) );
				return $widget ? $widget->get_controls() : array();
		}
		return array();
	}

	/**
	 * Element types a parent may hold. $parent is 'page' for the top level;
	 * $in_inner is true inside an inner section.
	 *
	 * @return string[]
	 */
	public static function allowed_children( string $parent, bool $in_inner = false ): array {
		switch ( $parent ) {
			case 'page':
				return array( 'section', 'container' );
			case 'section':
				return array( 'column' );
			case 'column':
				return $in_inner ? array( 'widget', 'container' ) : array( 'widget', 'section', 'container' );
			case 'container':
				return array( 'widget', 'container' );
		}
		return array();
	}

	/**
	 * Sanitize a decoded layout tree. Unknown element types, unknown widgets,
	 * elements in places they cannot go, and unknown settings are dropped.
	 *
	 * @param mixed    $layout  Decoded JSON.
	 * @param Registry $widgets Widget registry.
	 */
	public static function sanitize( $layout, Registry $widgets ): array {
		$clean = self::empty_layout();
		if ( ! is_array( $layout ) || ! isset( $layout['elements'] ) || ! is_array( $layout['elements'] ) ) {
			return $clean;
		}
		$clean['elements'] = self::sanitize_children( $layout['elements'], 'page', false, 0, $widgets );
		return $clean;
	}

	/**
	 * @param mixed $elements Raw child list.
	 */
	private static function sanitize_children( $elements, string $parent, bool $in_inner, int $depth, Registry $widgets ): array {
		$out = array();
		if ( ! is_array( $elements ) || $depth >= self::MAX_DEPTH ) {
			return $out;
		}
		$allowed = self::allowed_children( $parent, $in_inner );
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || ! in_array( $element['elType'] ?? '', $allowed, true ) ) {
				continue;
			}
			$clean = self::sanitize_element( $element, $parent, $in_inner, $depth, $widgets );
			if ( null !== $clean ) {
				$out[] = $clean;
			}
			if ( 'section' === $parent && count( $out ) >= self::MAX_COLUMNS ) {
				break;
			}
		}
		return $out;
	}

	private static function sanitize_element( array $element, string $parent, bool $in_inner, int $depth, Registry $widgets ): ?array {
		$type = $element['elType'];
		$id   = self::sanitize_id( $element['id'] ?? '' );

		if ( 'widget' === $type ) {
			$instance = $widgets->get( (string) ( $element['widgetType'] ?? '' ) );
			if ( ! $instance ) {
				return null;
			}
			return array(
				'id'         => $id,
				'elType'     => 'widget',
				'widgetType' => $instance->get_name(),
				'settings'   => Controls::sanitize( $instance->get_controls(), $element['settings'] ?? array() ),
			);
		}

		$clean = array(
			'id'       => $id,
			'elType'   => $type,
			'settings' => Controls::sanitize( self::controls_for( $element, $widgets ), $element['settings'] ?? array() ),
		);
		if ( 'section' === $type || 'container' === $type ) {
			$clean['isInner'] = 'page' !== $parent;
		}
		$clean['elements'] = self::sanitize_children(
			$element['elements'] ?? array(),
			$type,
			$in_inner || ( 'section' === $type && 'page' !== $parent ),
			$depth + 1,
			$widgets
		);
		return $clean;
	}

	/**
	 * Element ids are short lowercase hex strings generated by the editor.
	 * Anything else gets a fresh id.
	 */
	public static function sanitize_id( $id ): string {
		$id = is_string( $id ) ? strtolower( $id ) : '';
		if ( preg_match( '/^[a-f0-9]{4,16}$/', $id ) ) {
			return $id;
		}
		return substr( md5( wp_generate_uuid4() ), 0, 7 );
	}
}
