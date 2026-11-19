<?php
/**
 * Icon list widget: items with an icon and optional link, stacked or in a
 * row, with optional dividers. Settings match Elementor's icon-list widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class IconList extends Widget {

	public function get_name(): string {
		return 'icon-list';
	}

	public function get_title(): string {
		return __( 'Icon list', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-editor-ul';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'List', 'kbroo-studio' ) );
		$list    = array(
			'tab'     => 'style',
			'section' => __( 'List', 'kbroo-studio' ),
		);
		$divider = $list + array( 'condition' => array( 'divider' => 'yes' ) );
		$icon    = array(
			'tab'     => 'style',
			'section' => __( 'Icon', 'kbroo-studio' ),
		);
		$text    = array(
			'tab'     => 'style',
			'section' => __( 'Text', 'kbroo-studio' ),
		);
		$wrap    = '{{WRAPPER}} .kbroo-icon-list';
		$item    = function ( string $id, string $label, string $icon ) {
			return array(
				'_id'           => $id,
				'text'          => $label,
				'selected_icon' => array(
					'value'   => $icon,
					'library' => 'fa-solid',
				),
			);
		};
		$align   = array(
			'left'   => 'flex-start',
			'center' => 'center',
			'right'  => 'flex-end',
		);

		return array_merge(
			array(
				'view'       => $content + array(
					'type'    => 'select',
					'label'   => __( 'Layout', 'kbroo-studio' ),
					'default' => 'traditional',
					'options' => array(
						'traditional' => __( 'Stacked', 'kbroo-studio' ),
						'inline'      => __( 'In a row', 'kbroo-studio' ),
					),
				),
				'icon_list'  => $content + array(
					'type'        => 'repeater',
					'label'       => __( 'Items', 'kbroo-studio' ),
					'item_label'  => __( 'Item', 'kbroo-studio' ),
					'title_field' => 'text',
					'default'     => array(
						$item( 'a1c3e01', __( 'List item #1', 'kbroo-studio' ), 'fas fa-check' ),
						$item( 'a1c3e02', __( 'List item #2', 'kbroo-studio' ), 'fas fa-xmark' ),
						$item( 'a1c3e03', __( 'List item #3', 'kbroo-studio' ), 'fas fa-circle-dot' ),
					),
					'fields'      => array(
						'text'          => array(
							'type'    => 'text',
							'label'   => __( 'Text', 'kbroo-studio' ),
							'default' => __( 'List item', 'kbroo-studio' ),
						),
						'selected_icon' => array(
							'type'    => 'icons',
							'label'   => __( 'Icon', 'kbroo-studio' ),
							'default' => array(
								'value'   => 'fas fa-check',
								'library' => 'fa-solid',
							),
						),
						'link'          => array(
							'type'  => 'url',
							'label' => __( 'Link', 'kbroo-studio' ),
						),
					),
				),
				'link_click' => $content + array(
					'type'    => 'select',
					'label'   => __( 'Link covers', 'kbroo-studio' ),
					'default' => 'full_width',
					'options' => array(
						'full_width' => __( 'The whole line', 'kbroo-studio' ),
						'inline'     => __( 'The text only', 'kbroo-studio' ),
					),
					'condition' => array( 'view!' => 'inline' ),
				),

				'space_between'  => $list + array(
					'type'       => 'slider',
					'label'      => __( 'Space between', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $wrap => '--kbroo-list-gap: {{SIZE}}{{UNIT}};' ),
				),
				'icon_align'     => $list + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => '',
					'responsive'           => true,
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => $align,
					'selectors'            => array( $wrap => '--kbroo-list-align: {{VALUE}};' ),
				),
				'divider'        => $list + array(
					'type'    => 'switcher',
					'label'   => __( 'Divider', 'kbroo-studio' ),
					'default' => '',
				),
				'divider_style'  => $divider + array(
					'type'      => 'select',
					'label'     => __( 'Divider style', 'kbroo-studio' ),
					'default'   => 'solid',
					'options'   => array(
						'solid'  => __( 'Solid', 'kbroo-studio' ),
						'double' => __( 'Double', 'kbroo-studio' ),
						'dotted' => __( 'Dotted', 'kbroo-studio' ),
						'dashed' => __( 'Dashed', 'kbroo-studio' ),
					),
					'selectors' => array( $wrap => '--kbroo-divider-style: {{VALUE}};' ),
				),
				'divider_weight' => $divider + array(
					'type'      => 'slider',
					'label'     => __( 'Divider weight', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( $wrap => '--kbroo-divider-weight: {{SIZE}}{{UNIT}};' ),
				),
				'divider_width'  => $divider + array(
					'type'      => 'slider',
					'label'     => __( 'Divider width', 'kbroo-studio' ),
					'units'     => array( '%', 'px' ),
					'condition' => array(
						'divider' => 'yes',
						'view!'   => 'inline',
					),
					'selectors' => array( $wrap => '--kbroo-divider-width: {{SIZE}}{{UNIT}};' ),
				),
				'divider_height' => $divider + array(
					'type'      => 'slider',
					'label'     => __( 'Divider height', 'kbroo-studio' ),
					'units'     => array( '%', 'px' ),
					'condition' => array(
						'divider' => 'yes',
						'view'    => 'inline',
					),
					'selectors' => array( $wrap => '--kbroo-divider-height: {{SIZE}}{{UNIT}};' ),
				),
				'divider_color'  => $divider + array(
					'type'      => 'color',
					'label'     => __( 'Divider colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap => '--kbroo-divider-color: {{VALUE}};' ),
				),

				'icon_color'               => $icon + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap . ' .kbroo-icon-list__icon' => 'color: {{VALUE}};' ),
				),
				'icon_color_hover'         => $icon + array(
					'type'      => 'color',
					'label'     => __( 'Colour on hover', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap . ' .kbroo-icon-list__item:hover .kbroo-icon-list__icon' => 'color: {{VALUE}};' ),
				),
				'icon_size'                => $icon + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $wrap => '--kbroo-list-icon-size: {{SIZE}}{{UNIT}};' ),
				),
				'icon_self_align'          => $icon + array(
					'type'                 => 'select',
					'label'                => __( 'Horizontal alignment', 'kbroo-studio' ),
					'default'              => '',
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => $align,
					'selectors'            => array( $wrap . ' .kbroo-icon-list__icon' => 'justify-content: {{VALUE}};' ),
				),
				'icon_self_vertical_align' => $icon + array(
					'type'      => 'select',
					'label'     => __( 'Vertical alignment', 'kbroo-studio' ),
					'default'   => '',
					'options'   => array(
						''           => __( 'Default', 'kbroo-studio' ),
						'flex-start' => __( 'Top', 'kbroo-studio' ),
						'center'     => __( 'Middle', 'kbroo-studio' ),
						'flex-end'   => __( 'Bottom', 'kbroo-studio' ),
					),
					'selectors' => array( $wrap . ' .kbroo-icon-list__link' => 'align-items: {{VALUE}};' ),
				),

				'text_color'       => $text + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap . ' .kbroo-icon-list__text' => 'color: {{VALUE}};' ),
				),
				'text_color_hover' => $text + array(
					'type'      => 'color',
					'label'     => __( 'Colour on hover', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap . ' .kbroo-icon-list__item:hover .kbroo-icon-list__text' => 'color: {{VALUE}};' ),
				),
				'text_indent'      => $text + array(
					'type'      => 'slider',
					'label'     => __( 'Gap after icon', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( $wrap => '--kbroo-list-icon-gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'icon_typography', $wrap . ' .kbroo-icon-list__item', $text )
		);
	}

	public function render( array $settings ): string {
		$items  = is_array( $settings['icon_list'] ) ? $settings['icon_list'] : array();
		$inline = 'inline' === $settings['view'];
		$html   = '';
		foreach ( $items as $item ) {
			$svg   = Icons::svg( $item['selected_icon'] ?? array() );
			$inner = ( '' !== $svg ? '<span class="kbroo-icon-list__icon">' . $svg . '</span>' : '' )
				. '<span class="kbroo-icon-list__text">' . esc_html( (string) ( $item['text'] ?? '' ) ) . '</span>';
			$link  = self::link_attributes( $item['link'] ?? array() );
			$tag   = '' !== $link ? 'a' : 'span';
			$html .= sprintf(
				'<li class="kbroo-icon-list__item kbroo-repeater-item-%1$s"><%2$s class="kbroo-icon-list__link"%3$s>%4$s</%2$s></li>',
				esc_attr( (string) ( $item['_id'] ?? '' ) ),
				$tag,
				$link,
				$inner
			);
		}
		if ( '' === $html ) {
			return '';
		}
		$classes = array( 'kbroo-icon-list', 'kbroo-icon-list--' . ( $inline ? 'inline' : 'traditional' ) );
		if ( 'yes' === $settings['divider'] ) {
			$classes[] = 'kbroo-icon-list--divider';
		}
		if ( ! $inline && 'inline' === $settings['link_click'] ) {
			$classes[] = 'kbroo-icon-list--link-inline';
		}
		return '<ul class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $html . '</ul>';
	}
}
