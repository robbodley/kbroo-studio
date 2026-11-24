<?php
/**
 * Nav menu widget: a WordPress menu (Appearance > Menus), across or down,
 * with drop-down submenus and a burger menu on smaller screens (a drop-down,
 * side panel or full-screen menu). Settings match Elementor Pro's Nav Menu
 * widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

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
		$nav       = '{{WRAPPER}} .kbroo-nav';
		$content   = __( 'Menu', 'kbroo-studio' );
		$burger    = __( 'Burger menu', 'kbroo-studio' );
		$items     = __( 'Menu items', 'kbroo-studio' );
		$dropdown  = __( 'Burger menu and drop-downs', 'kbroo-studio' );
		$toggle    = __( 'Burger button', 'kbroo-studio' );
		$text_only = array( 'layout!' => 'dropdown' );
		// The opened burger menu, and the drop-downs of the menu across.
		$drop_links = '{{WRAPPER}} .kbroo-nav__menu .sub-menu a, {{WRAPPER}} .kbroo-nav.is-open .kbroo-nav__menu a';
		$drop_box   = '{{WRAPPER}} .kbroo-nav--horizontal:not(.is-open) .kbroo-nav__menu .sub-menu, {{WRAPPER}} .kbroo-nav.is-open > .kbroo-nav__menu';
		$panel      = array( 'menu_panel' => array( 'side', 'fullscreen' ) );

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
				'menu_name'    => array(
					'type'        => 'text',
					'label'       => __( 'Menu name', 'kbroo-studio' ),
					'section'     => $content,
					'default'     => '',
					'description' => __( 'Read out by screen readers. Empty uses the menu\'s own name.', 'kbroo-studio' ),
				),
				'layout'       => array(
					'type'    => 'select',
					'label'   => __( 'Layout', 'kbroo-studio' ),
					'section' => $content,
					'default' => 'horizontal',
					'options' => array(
						'horizontal' => __( 'Across', 'kbroo-studio' ),
						'vertical'   => __( 'Down', 'kbroo-studio' ),
						'dropdown'   => __( 'Burger menu only', 'kbroo-studio' ),
					),
				),
				'align_items'  => array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'section'              => $content,
					'default'              => '',
					'condition'            => $text_only,
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

				// The burger menu.
				'dropdown'           => array(
					'type'        => 'select',
					'label'       => __( 'Burger menu from', 'kbroo-studio' ),
					'section'     => $burger,
					'default'     => 'tablet',
					'condition'   => $text_only,
					'options'     => array(
						'tablet' => __( 'Tablet and smaller', 'kbroo-studio' ),
						'mobile' => __( 'Phones', 'kbroo-studio' ),
						'none'   => __( 'Never', 'kbroo-studio' ),
					),
					'description' => __( 'Below this size the menu folds into a burger button that opens it.', 'kbroo-studio' ),
				),
				'toggle_align'       => array(
					'type'                 => 'select',
					'label'                => __( 'Button position', 'kbroo-studio' ),
					'section'              => $burger,
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
				'toggle_icon_normal' => array(
					'type'        => 'icons',
					'label'       => __( 'Button icon', 'kbroo-studio' ),
					'section'     => $burger,
					'description' => __( 'Leave empty for the three-line burger.', 'kbroo-studio' ),
				),
				'toggle_icon_active' => array(
					'type'        => 'icons',
					'label'       => __( 'Close icon', 'kbroo-studio' ),
					'section'     => $burger,
					'description' => __( 'Shown while the menu is open. Leave empty for a cross.', 'kbroo-studio' ),
				),
				'menu_panel'         => array(
					'type'    => 'select',
					'label'   => __( 'Menu opens as', 'kbroo-studio' ),
					'section' => $burger,
					'default' => 'dropdown',
					'options' => array(
						'dropdown'   => __( 'Drop-down under the button', 'kbroo-studio' ),
						'side'       => __( 'Side panel', 'kbroo-studio' ),
						'fullscreen' => __( 'Full screen', 'kbroo-studio' ),
					),
				),
				'full_width'         => array(
					'type'        => 'select',
					'label'       => __( 'Drop-down width', 'kbroo-studio' ),
					'section'     => $burger,
					'default'     => '',
					'condition'   => array( 'menu_panel' => 'dropdown' ),
					'options'     => array(
						''        => __( 'Fit the menu', 'kbroo-studio' ),
						'stretch' => __( 'Full width of the page', 'kbroo-studio' ),
					),
				),
				'side_panel_position' => array(
					'type'      => 'select',
					'label'     => __( 'Slides in from', 'kbroo-studio' ),
					'section'   => $burger,
					'default'   => 'right',
					'condition' => array( 'menu_panel' => 'side' ),
					'options'   => array(
						'left'  => __( 'Left', 'kbroo-studio' ),
						'right' => __( 'Right', 'kbroo-studio' ),
					),
				),
				'text_align'         => array(
					'type'                 => 'select',
					'label'                => __( 'Text alignment', 'kbroo-studio' ),
					'section'              => $burger,
					'default'              => '',
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'aside'  => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					// Submenus are indented only when the text is on the left.
					'selectors_dictionary' => array(
						'aside'  => 'left; --kbroo-nav-sub-step: var(--kbroo-nav-drop-pad-x, var(--kbroo-nav-pad-x, 15px))',
						'center' => 'center; --kbroo-nav-sub-step: 0px',
						'right'  => 'right; --kbroo-nav-sub-step: 0px',
					),
					'selectors'            => array( $nav => '--kbroo-nav-drop-align: {{VALUE}};' ),
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
					'condition' => $text_only,
					'selectors' => array( $nav => '--kbroo-nav-hover: {{VALUE}};' ),
				),
				'pointer'                     => array(
					'type'      => 'select',
					'label'     => __( 'Pointer', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'condition' => $text_only,
					'description' => __( 'Marks the item under the mouse and the current page, in the menu across the page.', 'kbroo-studio' ),
					'options'   => array(
						''            => __( 'None', 'kbroo-studio' ),
						'underline'   => __( 'Underline', 'kbroo-studio' ),
						'overline'    => __( 'Overline', 'kbroo-studio' ),
						'double-line' => __( 'Double line', 'kbroo-studio' ),
						'framed'      => __( 'Framed', 'kbroo-studio' ),
						'background'  => __( 'Background', 'kbroo-studio' ),
					),
				),
				'pointer_color_menu_item_hover' => array(
					'type'      => 'color',
					'label'     => __( 'Pointer color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'condition' => array( 'pointer!' => '' ) + $text_only,
					'selectors' => array( $nav => '--kbroo-nav-pointer: {{VALUE}};' ),
				),
				'pointer_width'               => array(
					'type'      => 'slider',
					'label'     => __( 'Pointer width', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'units'     => array( 'px' ),
					'condition' => array( 'pointer' => array( 'underline', 'overline', 'double-line', 'framed' ) ) + $text_only,
					'selectors' => array( $nav => '--kbroo-nav-pointer-width: {{SIZE}}{{UNIT}};' ),
				),
				'color_menu_item_active'      => array(
					'type'      => 'color',
					'label'     => __( 'Current page color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $items,
					'default'   => '',
					'condition' => $text_only,
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
					'condition'  => $text_only,
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
				// Burger button.
				'toggle_color'                  => array(
					'type'      => 'color',
					'label'     => __( 'Color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-color: {{VALUE}};' ),
				),
				'toggle_background_color'       => array(
					'type'      => 'color',
					'label'     => __( 'Background', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-bg: {{VALUE}};' ),
				),
				'toggle_color_hover'            => array(
					'type'      => 'color',
					'label'     => __( 'Color on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-hover: {{VALUE}};' ),
				),
				'toggle_background_color_hover' => array(
					'type'      => 'color',
					'label'     => __( 'Background on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-hover-bg: {{VALUE}};' ),
				),
				'toggle_size'                   => array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $toggle,
					'units'      => array( 'px' ),
					'min'        => 10,
					'max'        => 80,
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-toggle-size: {{SIZE}}{{UNIT}};' ),
				),
				'toggle_padding'                => array(
					'type'       => 'slider',
					'label'      => __( 'Padding', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $toggle,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 40,
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-toggle-pad: {{SIZE}}{{UNIT}};' ),
				),
				'toggle_border_width'           => array(
					'type'      => 'slider',
					'label'     => __( 'Border width', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'units'     => array( 'px' ),
					'min'       => 0,
					'max'       => 10,
					'selectors' => array( $nav => '--kbroo-nav-toggle-border-width: {{SIZE}}{{UNIT}};' ),
				),
				'toggle_border_color'           => array(
					'type'      => 'color',
					'label'     => __( 'Border color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-toggle-border-color: {{VALUE}};' ),
				),
				'toggle_border_radius'          => array(
					'type'      => 'slider',
					'label'     => __( 'Rounded corners', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $toggle,
					'units'     => array( 'px', '%' ),
					'min'       => 0,
					'selectors' => array( $nav => '--kbroo-nav-toggle-radius: {{SIZE}}{{UNIT}};' ),
				),

				// The opened burger menu (and drop-downs of the menu across).
				'background_color_dropdown_item'       => array(
					'type'      => 'color',
					'label'     => __( 'Background', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-bg: {{VALUE}};' ),
				),
				'color_dropdown_item'                  => array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-color: {{VALUE}};' ),
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
					'label'     => __( 'Item background on hover', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-hover-bg: {{VALUE}};' ),
				),
				'color_dropdown_item_active'           => array(
					'type'      => 'color',
					'label'     => __( 'Current page color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-active: {{VALUE}};' ),
				),
				'background_color_dropdown_item_active' => array(
					'type'      => 'color',
					'label'     => __( 'Current page background', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-sub-active-bg: {{VALUE}};' ),
				),
			),
			ControlGroups::typography(
				'dropdown_typography',
				$drop_links,
				array(
					'tab'     => 'style',
					'section' => $dropdown,
				)
			),
			array(
				'padding_horizontal_dropdown_item' => array(
					'type'       => 'slider',
					'label'      => __( 'Item padding across', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $dropdown,
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-drop-pad-x: {{SIZE}}{{UNIT}};' ),
				),
				'padding_vertical_dropdown_item'   => array(
					'type'       => 'slider',
					'label'      => __( 'Item padding up and down', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $dropdown,
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $nav => '--kbroo-nav-drop-pad-y: {{SIZE}}{{UNIT}};' ),
				),
				'dropdown_divider_color'           => array(
					'type'      => 'color',
					'label'     => __( 'Line between items', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-nav-divider-color: {{VALUE}}; --kbroo-nav-divider-on: 1px;' ),
				),
				'dropdown_divider_size'            => array(
					'type'      => 'slider',
					'label'     => __( 'Line thickness', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'units'     => array( 'px' ),
					'min'       => 0,
					'max'       => 10,
					'condition' => array( 'dropdown_divider_color!' => '' ),
					'selectors' => array( $nav => '--kbroo-nav-divider-width: {{SIZE}}{{UNIT}};' ),
				),
				'dropdown_width'                   => array(
					'type'        => 'slider',
					'label'       => __( 'Menu width', 'kbroo-studio' ),
					'tab'         => 'style',
					'section'     => $dropdown,
					'units'       => array( 'px', 'vw', '%' ),
					'responsive'  => true,
					'description' => __( 'For the drop-down and the side panel.', 'kbroo-studio' ),
					'selectors'   => array( $nav => '--kbroo-nav-drop-width: {{SIZE}}{{UNIT}};' ),
				),
				'dropdown_top_distance'            => array(
					'type'       => 'slider',
					'label'      => __( 'Gap below the button', 'kbroo-studio' ),
					'tab'        => 'style',
					'section'    => $dropdown,
					'units'      => array( 'px' ),
					'responsive' => true,
					'condition'  => array( 'menu_panel' => 'dropdown' ),
					'selectors'  => array( $nav => '--kbroo-nav-drop-distance: {{SIZE}}{{UNIT}};' ),
				),
				'dropdown_shadow'                  => array(
					'type'                 => 'select',
					'label'                => __( 'Shadow', 'kbroo-studio' ),
					'tab'                  => 'style',
					'section'              => $dropdown,
					'default'              => '',
					'options'              => array(
						''       => __( 'Soft', 'kbroo-studio' ),
						'strong' => __( 'Strong', 'kbroo-studio' ),
						'none'   => __( 'None', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'strong' => '0 16px 48px rgba(15, 23, 42, 0.35)',
					),
					'selectors'            => array( $nav => '--kbroo-nav-drop-shadow: {{VALUE}};' ),
				),
				'overlay_color'                    => array(
					'type'      => 'color',
					'label'     => __( 'Page shade behind the panel', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'condition' => array( 'menu_panel' => 'side' ),
					'selectors' => array( $nav => '--kbroo-nav-overlay: {{VALUE}};' ),
				),
				'close_color'                      => array(
					'type'      => 'color',
					'label'     => __( 'Close button color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'default'   => '',
					'condition' => $panel,
					'selectors' => array( $nav => '--kbroo-nav-close-color: {{VALUE}};' ),
				),
				'close_size'                       => array(
					'type'      => 'slider',
					'label'     => __( 'Close button size', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => $dropdown,
					'units'     => array( 'px' ),
					'min'       => 10,
					'max'       => 80,
					'condition' => $panel,
					'selectors' => array( $nav => '--kbroo-nav-close-size: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::border(
				$drop_box,
				array(
					'tab'     => 'style',
					'section' => $dropdown,
				),
				'dropdown_border'
			)
		);
	}

	/**
	 * The burger button's insides: the icon, and the close icon while open.
	 */
	private static function toggle_inner( array $settings ): string {
		$normal = Icons::svg( $settings['toggle_icon_normal'] ?? null, 'kbroo-nav__icon' );
		$active = Icons::svg( $settings['toggle_icon_active'] ?? null, 'kbroo-nav__icon' );
		$bars   = '<span class="kbroo-nav__toggle-bars" aria-hidden="true"></span>';
		if ( '' === $normal && '' === $active ) {
			return $bars; // The three lines turn into a cross.
		}
		return sprintf(
			'<span class="kbroo-nav__toggle-open" aria-hidden="true">%1$s</span><span class="kbroo-nav__toggle-close" aria-hidden="true">%2$s</span>',
			'' !== $normal ? $normal : $bars,
			'' !== $active ? $active : '<span class="kbroo-nav__cross"></span>'
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
		$panel   = in_array( $settings['menu_panel'] ?? '', array( 'dropdown', 'side', 'fullscreen' ), true ) ? $settings['menu_panel'] : 'dropdown';
		$classes = array(
			'kbroo-nav',
			'kbroo-nav--' . ( 'vertical' === $layout ? 'vertical' : 'horizontal' ),
			'kbroo-nav--break-' . $breaks,
			'kbroo-nav--panel-' . $panel,
		);
		if ( 'side' === $panel ) {
			$classes[] = 'kbroo-nav--from-' . ( 'left' === ( $settings['side_panel_position'] ?? '' ) ? 'left' : 'right' );
		}
		$pointers = array( 'underline', 'overline', 'double-line', 'framed', 'background' );
		if ( in_array( $settings['pointer'] ?? '', $pointers, true ) ) {
			$classes[] = 'kbroo-nav--pointer-' . $settings['pointer'];
		}
		if ( 'dropdown' === $panel && 'stretch' === ( $settings['full_width'] ?? '' ) ) {
			$classes[] = 'kbroo-nav--stretch';
		}
		$cross = Icons::svg( $settings['toggle_icon_active'] ?? null, 'kbroo-nav__icon' );
		$close = 'dropdown' === $panel ? '' : sprintf(
			'<button type="button" class="kbroo-nav__close" aria-controls="%1$s"><span aria-hidden="true">%2$s</span><span class="kbroo-sr-only">%3$s</span></button>',
			esc_attr( $list_id ),
			'' !== $cross ? $cross : '<span class="kbroo-nav__cross"></span>',
			esc_html__( 'Close menu', 'kbroo-studio' )
		);

		return sprintf(
			'<nav class="%1$s" aria-label="%2$s"><button type="button" class="kbroo-nav__toggle" aria-expanded="false" aria-controls="%3$s">%4$s<span class="kbroo-sr-only">%5$s</span></button>%6$s%7$s</nav>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( '' !== trim( (string) ( $settings['menu_name'] ?? '' ) ) ? $settings['menu_name'] : $menu->name ),
			esc_attr( $list_id ),
			self::toggle_inner( $settings ),
			esc_html__( 'Menu', 'kbroo-studio' ),
			$close,
			$items
		);
	}
}
