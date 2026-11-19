<?php
/**
 * Layout schema and sanitization.
 *
 * A layout is a tree:
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
 * Everything that comes from the editor passes through sanitize() before it
 * is stored, so the renderer can trust the shape of the tree.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Layout {

	const VERSION     = 1;
	const MAX_COLUMNS = 6;

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
			ControlGroups::background( '{{WRAPPER}}' ),
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
			ControlGroups::background( $inner ),
			ControlGroups::border( $inner ),
			ControlGroups::advanced( '', $inner )
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
			case 'widget':
				$widget = $widgets->get( (string) ( $element['widgetType'] ?? '' ) );
				return $widget ? $widget->get_controls() : array();
		}
		return array();
	}

	/**
	 * Sanitize a decoded layout tree. Unknown element types, unknown widgets,
	 * and unknown settings are dropped.
	 *
	 * @param mixed    $layout  Decoded JSON.
	 * @param Registry $widgets Widget registry.
	 */
	public static function sanitize( $layout, Registry $widgets ): array {
		$clean = self::empty_layout();
		if ( ! is_array( $layout ) || ! isset( $layout['elements'] ) || ! is_array( $layout['elements'] ) ) {
			return $clean;
		}

		foreach ( $layout['elements'] as $section ) {
			if ( ! is_array( $section ) || 'section' !== ( $section['elType'] ?? '' ) ) {
				continue;
			}
			$columns = array();
			foreach ( (array) ( $section['elements'] ?? array() ) as $column ) {
				if ( ! is_array( $column ) || 'column' !== ( $column['elType'] ?? '' ) ) {
					continue;
				}
				$items = array();
				foreach ( (array) ( $column['elements'] ?? array() ) as $widget ) {
					if ( ! is_array( $widget ) || 'widget' !== ( $widget['elType'] ?? '' ) ) {
						continue;
					}
					$instance = $widgets->get( (string) ( $widget['widgetType'] ?? '' ) );
					if ( ! $instance ) {
						continue;
					}
					$items[] = array(
						'id'         => self::sanitize_id( $widget['id'] ?? '' ),
						'elType'     => 'widget',
						'widgetType' => $instance->get_name(),
						'settings'   => Controls::sanitize( $instance->get_controls(), $widget['settings'] ?? array() ),
					);
				}
				$columns[] = array(
					'id'       => self::sanitize_id( $column['id'] ?? '' ),
					'elType'   => 'column',
					'settings' => Controls::sanitize( self::column_controls(), $column['settings'] ?? array() ),
					'elements' => $items,
				);
				if ( count( $columns ) >= self::MAX_COLUMNS ) {
					break;
				}
			}
			$clean['elements'][] = array(
				'id'       => self::sanitize_id( $section['id'] ?? '' ),
				'elType'   => 'section',
				'settings' => Controls::sanitize( self::section_controls(), $section['settings'] ?? array() ),
				'elements' => $columns,
			);
		}

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
