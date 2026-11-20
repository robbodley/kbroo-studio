<?php
/**
 * Nav menu widget: a WordPress menu (Appearance > Menus), across or down,
 * with drop-down submenus and a menu button on smaller screens. Settings
 * match Elementor Pro's Nav Menu widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class NavMenu extends Widget {

	public function get_name(): string {
		return 'nav-menu';
	}

	public function get_title(): string {
		return __( 'Nav menu', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-menu-alt';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * Menus on the site, by slug (as Elementor stores them).
	 *
	 * @return array<string,string>
	 */
	private static function menus(): array {
		$out = array( '' => __( '— Choose a menu —', 'kbroo-studio' ) );
		foreach ( wp_get_nav_menus() as $menu ) {
			$out[ $menu->slug ] = $menu->name;
		}
		return $out;
	}

	protected function widget_controls(): array {
		$nav      = '{{WRAPPER}} .kbroo-nav';
		$content  = __( 'Menu', 'kbroo-studio' );
		$items    = __( 'Menu items', 'kbroo-studio' );
		$dropdown = __( 'Submenus and phone menu', 'kbroo-studio' );
		$toggle   = __( 'Menu button', 'kbroo-studio' );

		return array_merge(
			array(
				'menu'         => array(
					'type'        => 'select',
					'label'       => __( 'Menu', 'kbroo-studio' ),
					'section'     => $content,
					'default'     => '',
					'options'     => self::menus(),
					'description' => __( 'Menus are made in Appearance > Menus.', 'kbroo-studio' ),
				),
				'layout'       => array(
					'type'    => 'select',
					'label'   => __( 'Layout', 'kbroo-studio' ),
					'section' => $content,
					'default' => 'horizontal',
					'options' => array(
						'horizontal' => __( 'Across', 'kbroo-studio' ),
						'vertical'   => __( 'Down', 'kbroo-studio' ),
						'dropdown'   => __( 'Menu button only', 'kbroo-studio' ),
					),
				),
				'align_items'  => array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'section'              => $content,
					'default'              => '',
					'options'              => array(
						''        => __( 'Default', 'kbroo-studio' ),
						'left'    => __( 'Left', 'kbroo-studio' ),
						'center'  => __( 'Center', 'kbroo-studio' ),
						'right'   => __( 'Right', 'kbroo-studio' ),
						'justify' => __( 'Stretch', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'left'    => 'flex-start',
						'center'  => 'center',
						'right'   => 'flex-end',
						'justify' => 'space-between',
					),
					'selectors'            => array( $nav => '--kbroo-nav-justify: {{VALUE}};' ),
				),
				'dropdown'     => array(
					'type'        => 'select',
					'label'       => __( 'Menu button from', 'kbroo-studio' ),
					'section'     => $content,
					'default'     => 'tablet',
					'options'     => array(
						'tablet' => __( 'Tablet and smaller', 'kbroo-studio' ),
						'mobile' => __( 'Phones', 'kbroo-studio' ),
						'none'   => __( 'Never', 'kbroo-studio' ),
					),
					'description' => __( 'Below this size the menu folds into a button that opens it.', 'kbroo-studio' ),
				),
				'toggle_align' => array(
					'type'                 => 'select',
					'label'                => __( 'Menu button position', 'kbroo-studio' ),
					'section'              => $content,
					'default'              => 'center',
					'options'              => array(
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					// The opened menu lines up with the button.
					'selectors_dictionary' => array(
						'left'   => 'flex-start; --kbroo-nav-drop-left: 0; --kbroo-nav-drop-right: auto; --kbroo-nav-drop-shift: 0',
						'center' => 'center',
						'right'  => 'flex-end; --kbroo-nav-drop-left: auto; --kbroo-nav-drop-right: 0; --kbroo-nav-drop-shift: 0',
					),
					'selectors'            => array( $nav => '--kbroo-nav-toggle-align: {{VALUE}};' ),
				),

				// Menu items.
				'color_menu_item'             => array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-color: {{VALUE}};' ),
				),
				'color_menu_item_hover'       => array(
					'type'      => 'color',
					'label'     => __( 'Text color on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-hover: {{VALUE}};' ),
				),
				'color_menu_item_active'      => array(
					'type'      => 'color',
					'label'     => __( 'Current page color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-active: {{VALUE}};' ),
				),
				'padding_horizontal_menu_item' => array(
					'type'       => 'slider',
					'label'      => __( 'Padding across', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $items,
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-pad-x: {{SIZE}}{{UNIT}};' ),
				),
				'padding_vertical_menu_item'  => array(
					'type'       => 'slider',
					'label'      => __( 'Padding up and down', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $items,
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-pad-y: {{SIZE}}{{UNIT}};' ),
				),
				'menu_space_between'          => array(
					'type'       => 'slider',
					'label'      => __( 'Space between', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $items,
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-gap: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography(
				'menu_typography',
				'{{WRAPPER}} .kbroo-nav__menu > li > a',
				array(
					'tab'     => 'style',
					'section' => $items,
				)
			),
			array(
				// Submenus and the opened phone menu.
				'color_dropdown_item'                  => array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-color: {{VALUE}};' ),
				),
				'background_color_dropdown_item'       => array(
					'type'      => 'color',
					'label'     => __( 'Background', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-bg: {{VALUE}};' ),
				),
				'color_dropdown_item_hover'            => array(
					'type'      => 'color',
					'label'     => __( 'Text color on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-hover: {{VALUE}};' ),
				),
				'background_color_dropdown_item_hover' => array(
					'type'      => 'color',
					'label'     => __( 'Background on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-hover-bg: {{VALUE}};' ),
				),

				// Menu button.
				'toggle_color'            => array(
					'type'      => 'color',
					'label'     => __( 'Color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-color: {{VALUE}};' ),
				),
				'toggle_background_color' => array(
					'type'      => 'color',
					'label'     => __( 'Background', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-bg: {{VALUE}};' ),
				),
				'toggle_size'             => array(
					'type'      => 'slider',
					'label'     => __( 'Size', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'units'     => array( 'px' ),
					'selectors' => array( $nav => '--kbroo-nav-toggle-size: {{SIZE}}{{UNIT}};' ),
				),
			)
		);
	}

	public function render( array $settings ): string {
		$slug = (string) $settings['menu'];
		$menu = '' !== $slug ? wp_get_nav_menu_object( $slug ) : false;
		if ( ! $menu ) {
			// Fall back to the first menu, as Elementor does.
			$menus = wp_get_nav_menus();
			$menu  = $menus ? $menus[0] : false;
		}
		if ( ! $menu ) {
			return '' === $this->element_id
				? '<p class="kbroo-nav-empty">' . esc_html__( 'There are no menus yet. Make one in Appearance > Menus.', 'kbroo-studio' ) . '</p>'
				: '';
		}

		$layout   = in_array( $settings['layout'], array( 'horizontal', 'vertical', 'dropdown' ), true ) ? $settings['layout'] : 'horizontal';
		$breaks   = 'dropdown' === $layout ? 'always' : ( in_array( $settings['dropdown'], array( 'tablet', 'mobile', 'none' ), true ) ? $settings['dropdown'] : 'tablet' );
		$list_id  = 'kbroo-nav-' . ( '' !== $this->element_id ? $this->element_id : wp_rand( 1000, 9999 ) );
		$items    = wp_nav_menu(
			array(
				'menu'        => $menu,
				'container'   => false,
				'menu_id'     => $list_id,
				'menu_class'  => 'kbroo-nav__menu',
				'fallback_cb' => '__return_empty_string',
				'echo'        => false,
				'depth'       => 4,
			)
		);
		if ( ! $items ) {
			return '';
		}
		\KbrooStudio\Frontend::enqueue_script(); // Opens and closes the menu button.
		$classes = array(
			'kbroo-nav',
			'kbroo-nav--' . ( 'vertical' === $layout ? 'vertical' : 'horizontal' ),
			'kbroo-nav--break-' . $breaks,
		);

		return sprintf(
			'<nav class="%1$s" aria-label="%2$s"><button type="button" class="kbroo-nav__toggle" aria-expanded="false" aria-controls="%3$s"><span class="kbroo-nav__toggle-bars" aria-hidden="true"></span><span class="kbroo-sr-only">%4$s</span></button>%5$s</nav>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $menu->name ),
			esc_attr( $list_id ),
			esc_html__( 'Menu', 'kbroo-studio' ),
			$items
		);
	}
}
