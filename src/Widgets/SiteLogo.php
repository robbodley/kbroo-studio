<?php
/**
 * Site logo widget: the logo set in the theme's Site Identity (or an image
 * chosen here), linking to the home page. Settings match Elementor Pro's
 * Site Logo widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class SiteLogo extends Widget {

	public function get_name(): string {
		return 'site-logo';
	}

	public function get_title(): string {
		return __( 'Site logo', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-image';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$img     = '{{WRAPPER}} .kbroo-site-logo img';
		$section = __( 'Site logo', 'kbroo-studio' );
		return array(
			'image'      => array(
				'type'        => 'media',
				'label'       => __( 'Logo', 'kbroo-studio' ),
				'section'     => $section,
				'description' => __( 'Leave empty to use the logo from Appearance > Customize > Site Identity.', 'kbroo-studio' ),
			),
			'image_size' => array(
				'type'    => 'select',
				'label'   => __( 'Image size', 'kbroo-studio' ),
				'section' => $section,
				'default' => 'full',
				'options' => array(
					'thumbnail' => __( 'Thumbnail', 'kbroo-studio' ),
					'medium'    => __( 'Medium', 'kbroo-studio' ),
					'large'     => __( 'Large', 'kbroo-studio' ),
					'full'      => __( 'Full', 'kbroo-studio' ),
				),
			),
			'link_to'    => array(
				'type'    => 'select',
				'label'   => __( 'Link', 'kbroo-studio' ),
				'section' => $section,
				'default' => 'home',
				'options' => array(
					'home' => __( 'Home page', 'kbroo-studio' ),
					'none' => __( 'None', 'kbroo-studio' ),
				),
			),
			'align'      => ControlGroups::align(
				'{{WRAPPER}} .kbroo-site-logo',
				array(
					'tab'     => 'style',
					'section' => $section,
				)
			),
			'width'      => array(
				'type'       => 'slider',
				'label'      => __( 'Width', 'kbroo-studio' ),
				'tab'        => 'style',
				'section'    => $section,
				'units'      => array( 'px', '%', 'vw' ),
				'responsive' => true,
				'selectors'  => array( $img => 'width: {{SIZE}}{{UNIT}};' ),
			),
			'max_width'  => array(
				'type'       => 'slider',
				'label'      => __( 'Max width', 'kbroo-studio' ),
				'tab'        => 'style',
				'section'    => $section,
				'units'      => array( 'px', '%', 'vw' ),
				'responsive' => true,
				'selectors'  => array( $img => 'max-width: {{SIZE}}{{UNIT}};' ),
			),
			'height'     => array(
				'type'       => 'slider',
				'label'      => __( 'Height', 'kbroo-studio' ),
				'tab'        => 'style',
				'section'    => $section,
				'units'      => array( 'px' ),
				'responsive' => true,
				'selectors'  => array( $img => 'height: {{SIZE}}{{UNIT}}; width: auto;' ),
			),
		);
	}

	public function render( array $settings ): string {
		$size  = in_array( $settings['image_size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $settings['image_size'] : 'full';
		$id    = (int) ( $settings['image']['id'] ?? 0 );
		$url   = (string) ( $settings['image']['url'] ?? '' );
		$alt   = get_bloginfo( 'name' );
		$image = '';
		if ( ! $id && '' === $url ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $id ) {
			$image = wp_get_attachment_image( $id, $size, false, array( 'alt' => $alt ) );
		}
		if ( '' === $image && '' !== $url ) {
			$image = sprintf( '<img src="%s" alt="%s" />', esc_url( $url ), esc_attr( $alt ) );
		}
		if ( '' === $image ) {
			// No logo anywhere: show the site name, so the header still works.
			$image = '<span class="kbroo-site-logo__name">' . esc_html( $alt ) . '</span>';
		}
		if ( 'none' !== $settings['link_to'] ) {
			$image = sprintf( '<a href="%s" rel="home">%s</a>', esc_url( home_url( '/' ) ), $image );
		}
		return '<div class="kbroo-site-logo">' . $image . '</div>';
	}
}
