<?php
/**
 * Social icons widget. Settings match Elementor's social-icons widget
 * (social_icon_list, shape, icon_color, ...). Icons use each network's own
 * colour unless set to custom colours.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class SocialIcons extends Widget {

	/** Network colours, by icon name (as Elementor's). */
	const BRAND_COLORS = array(
		'android'        => '#a4c639',
		'apple'          => '#999999',
		'behance'        => '#1769ff',
		'bitbucket'      => '#205081',
		'codepen'        => '#000000',
		'delicious'      => '#3399ff',
		'deviantart'     => '#05cc47',
		'digg'           => '#005be2',
		'discord'        => '#5865f2',
		'dribbble'       => '#ea4c89',
		'envelope'       => '#ea4335',
		'etsy'           => '#f1641e',
		'facebook'       => '#3b5998',
		'facebook-f'     => '#3b5998',
		'flickr'         => '#0063dc',
		'foursquare'     => '#2d5be3',
		'free-code-camp' => '#006400',
		'github'         => '#333333',
		'gitlab'         => '#e24329',
		'globe'          => '#69727d',
		'houzz'          => '#7ac142',
		'instagram'      => '#262626',
		'jsfiddle'       => '#487aa2',
		'link'           => '#818a91',
		'linkedin'       => '#0077b5',
		'linkedin-in'    => '#0077b5',
		'medium'         => '#00ab6b',
		'meetup'         => '#ec1c40',
		'mixcloud'       => '#273a4b',
		'odnoklassniki'  => '#f4731c',
		'pinterest'      => '#bd081c',
		'pinterest-p'    => '#bd081c',
		'product-hunt'   => '#da552f',
		'reddit'         => '#ff4500',
		'rss'            => '#f26522',
		'shopping-cart'  => '#4caf50',
		'skype'          => '#00aff0',
		'slideshare'     => '#0077b5',
		'snapchat'       => '#fffc00',
		'soundcloud'     => '#ff8800',
		'spotify'        => '#2ebd59',
		'stack-overflow' => '#fe7a15',
		'steam'          => '#00adee',
		'telegram'       => '#2ca5e0',
		'threads'        => '#000000',
		'thumb-tack'     => '#1aa1d8',
		'tiktok'         => '#000000',
		'tripadvisor'    => '#589442',
		'tumblr'         => '#35465c',
		'twitch'         => '#6441a5',
		'twitter'        => '#1da1f2',
		'viber'          => '#665cac',
		'vimeo'          => '#1ab7ea',
		'vimeo-v'        => '#1ab7ea',
		'vk'             => '#45668e',
		'weibo'          => '#dd2430',
		'weixin'         => '#31a918',
		'whatsapp'       => '#25d366',
		'wordpress'      => '#21759b',
		'x-twitter'      => '#000000',
		'xing'           => '#026466',
		'yelp'           => '#af0606',
		'youtube'        => '#cd201f',
		'500px'          => '#0099e5',
	);

	public function get_name(): string {
		return 'social-icons';
	}

	public function get_title(): string {
		return __( 'Social icons', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-share';
	}

	public function server_preview(): bool {
		return true;
	}

	private static function default_item( string $network ): array {
		return array(
			'_id'         => substr( md5( $network ), 0, 7 ),
			'social_icon' => array(
				'value'   => 'fab fa-' . $network,
				'library' => 'fa-brands',
			),
			'link'        => array(
				'url'         => '',
				'is_external' => 'on',
				'nofollow'    => '',
			),
		);
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Social icons', 'kbroo-studio' ) );
		$style   = array(
			'tab'     => 'style',
			'section' => __( 'Icon', 'kbroo-studio' ),
		);
		$hover   = array(
			'tab'     => 'style',
			'section' => __( 'Icon on hover', 'kbroo-studio' ),
		);
		$icon    = '{{WRAPPER}} .kbroo-social-icon';
		$custom  = array( 'condition' => array( 'icon_color' => 'custom' ) );

		return array_merge(
			array(
				'social_icon_list' => $content + array(
					'type'        => 'repeater',
					'label'       => __( 'Icons', 'kbroo-studio' ),
					'item_label'  => __( 'Icon', 'kbroo-studio' ),
					'title_field' => 'social_icon',
					'default'     => array( self::default_item( 'facebook' ), self::default_item( 'x-twitter' ), self::default_item( 'youtube' ) ),
					'fields'      => array(
						'social_icon'               => array(
							'type'    => 'icons',
							'label'   => __( 'Icon', 'kbroo-studio' ),
							'default' => array(
								'value'   => 'fab fa-wordpress',
								'library' => 'fa-brands',
							),
						),
						'link'                      => array(
							'type'    => 'url',
							'label'   => __( 'Link', 'kbroo-studio' ),
							'default' => array(
								'url'         => '',
								'is_external' => 'on',
								'nofollow'    => '',
							),
						),
						'item_icon_color'           => array(
							'type'    => 'select',
							'label'   => __( 'Colour', 'kbroo-studio' ),
							'default' => 'default',
							'options' => array(
								'default' => __( 'Official colour', 'kbroo-studio' ),
								'custom'  => __( 'Custom', 'kbroo-studio' ),
							),
						),
						'item_icon_primary_color'   => array(
							'type'      => 'color',
							'label'     => __( 'Primary colour', 'kbroo-studio' ),
							'condition' => array( 'item_icon_color' => 'custom' ),
							'selectors' => array( '{{WRAPPER}} .kbroo-social-icon{{CURRENT_ITEM}}' => 'background-color: {{VALUE}};' ),
						),
						'item_icon_secondary_color' => array(
							'type'      => 'color',
							'label'     => __( 'Secondary colour', 'kbroo-studio' ),
							'condition' => array( 'item_icon_color' => 'custom' ),
							'selectors' => array( '{{WRAPPER}} .kbroo-social-icon{{CURRENT_ITEM}}' => 'color: {{VALUE}};' ),
						),
					),
				),
				'shape'            => $content + array(
					'type'    => 'select',
					'label'   => __( 'Shape', 'kbroo-studio' ),
					'default' => 'rounded',
					'options' => array(
						'rounded' => __( 'Rounded', 'kbroo-studio' ),
						'square'  => __( 'Square', 'kbroo-studio' ),
						'circle'  => __( 'Circle', 'kbroo-studio' ),
					),
				),
				'align'            => $content + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => 'center',
					'responsive'           => true,
					'options'              => array(
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'left'   => 'flex-start',
						'center' => 'center',
						'right'  => 'flex-end',
					),
					'selectors'            => array( '{{WRAPPER}} .kbroo-social-icons' => 'justify-content: {{VALUE}};' ),
				),
				'icon_color'       => $style + array(
					'type'    => 'select',
					'label'   => __( 'Colour', 'kbroo-studio' ),
					'default' => 'default',
					'options' => array(
						'default' => __( 'Official colour', 'kbroo-studio' ),
						'custom'  => __( 'Custom', 'kbroo-studio' ),
					),
				),
				'icon_primary_color'   => $style + $custom + array(
					'type'      => 'color',
					'label'     => __( 'Primary colour', 'kbroo-studio' ),
					'selectors' => array( $icon => 'background-color: {{VALUE}};' ),
				),
				'icon_secondary_color' => $style + $custom + array(
					'type'      => 'color',
					'label'     => __( 'Secondary colour', 'kbroo-studio' ),
					'selectors' => array( $icon => 'color: {{VALUE}};' ),
				),
				'icon_size'        => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', 'rem' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-social-icons' => '--kbroo-icon-size: {{SIZE}}{{UNIT}};' ),
				),
				'icon_padding'     => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Padding', 'kbroo-studio' ),
					'units'      => array( 'em', 'px' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-social-icons' => '--kbroo-icon-padding: {{SIZE}}{{UNIT}};' ),
				),
				'icon_spacing'     => $style + array(
					'type'       => 'slider',
					'label'      => __( 'Spacing', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-social-icons' => 'gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::border( $icon, $style, 'image_border' ),
			array(
				'border_radius' => $style + array(
					'type'       => 'dimensions',
					'label'      => __( 'Corner radius', 'kbroo-studio' ),
					'units'      => array( 'px', '%', 'em' ),
					'responsive' => true,
					'selectors'  => array( $icon => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'hover_primary_color'   => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Primary colour', 'kbroo-studio' ),
					'selectors' => array( $icon . ':hover' => 'background-color: {{VALUE}};' ),
				),
				'hover_secondary_color' => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Secondary colour', 'kbroo-studio' ),
					'selectors' => array( $icon . ':hover' => 'color: {{VALUE}};' ),
				),
				'hover_border_color'    => $hover + array(
					'type'      => 'color',
					'label'     => __( 'Border colour', 'kbroo-studio' ),
					'condition' => array( 'image_border_border!' => '' ),
					'selectors' => array( $icon . ':hover' => 'border-color: {{VALUE}};' ),
				),
			),
			ControlGroups::hover_animation( $icon, $hover )
		);
	}

	public function render( array $settings ): string {
		$items = '';
		foreach ( (array) $settings['social_icon_list'] as $item ) {
			$svg = Icons::svg( $item['social_icon'] ?? array() );
			if ( '' === $svg ) {
				continue;
			}
			$found   = Icons::resolve( $item['social_icon'] );
			$network = $found ? $found[1] : 'custom';
			$label   = ucwords( str_replace( array( '-', 'x twitter' ), array( ' ', 'X' ), $network ) );
			$link    = self::link_attributes( $item['link'] ?? array() );
			$tag     = '' !== $link ? 'a' : 'span';
			$classes = 'kbroo-social-icon kbroo-repeater-item-' . preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $item['_id'] ?? '' ) );
			$brand   = '';
			if ( 'custom' !== ( $item['item_icon_color'] ?? '' ) && 'custom' !== $settings['icon_color'] ) {
				$color = self::BRAND_COLORS[ $network ] ?? self::BRAND_COLORS[ preg_replace( '/-(square|alt|circle|f|in|p|v)$/', '', $network ) ] ?? '#69727d';
				$brand = ' style="--kbroo-brand:' . esc_attr( $color ) . '"';
			}
			$items .= sprintf(
				'<%1$s class="%2$s"%3$s%4$s aria-label="%5$s">%6$s</%1$s>',
				$tag,
				esc_attr( $classes ),
				$brand,
				$link,
				esc_attr( $label ),
				$svg
			);
		}
		if ( '' === $items ) {
			return '';
		}
		$shape = in_array( $settings['shape'], array( 'square', 'circle' ), true ) ? $settings['shape'] : 'rounded';
		return '<div class="kbroo-social-icons kbroo-social-icons--' . $shape . '">' . $items . '</div>';
	}
}
