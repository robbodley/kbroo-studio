<?php
/**
 * Site title widget: the site's name (or tagline) from Settings > General,
 * styled like a heading. Settings match Elementor Pro's Site Title widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class SiteTitle extends Heading {

	public function get_name(): string {
		return 'site-title';
	}

	public function get_title(): string {
		return __( 'Site title', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-admin-home';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$controls = parent::widget_controls();
		unset( $controls['title'] );
		$controls['header_size']['section'] = __( 'Site title', 'kbroo-studio' );
		return array(
			'show'    => array(
				'type'    => 'select',
				'label'   => __( 'Show', 'kbroo-studio' ),
				'section' => __( 'Site title', 'kbroo-studio' ),
				'default' => 'title',
				'options' => array(
					'title'   => __( 'Site title', 'kbroo-studio' ),
					'tagline' => __( 'Tagline', 'kbroo-studio' ),
				),
				'description' => __( 'Set in Settings > General.', 'kbroo-studio' ),
			),
			'link_to' => array(
				'type'    => 'select',
				'label'   => __( 'Link', 'kbroo-studio' ),
				'section' => __( 'Site title', 'kbroo-studio' ),
				'default' => 'home',
				'options' => array(
					'home' => __( 'Home page', 'kbroo-studio' ),
					'none' => __( 'None', 'kbroo-studio' ),
				),
			),
		) + $controls;
	}

	public function render( array $settings ): string {
		$tag  = in_array( $settings['header_size'], self::TAGS, true ) ? $settings['header_size'] : 'h2';
		$text = esc_html( get_bloginfo( 'tagline' === $settings['show'] ? 'description' : 'name' ) );
		if ( 'none' !== $settings['link_to'] ) {
			$text = sprintf( '<a href="%s" rel="home">%s</a>', esc_url( home_url( '/' ) ), $text );
		}
		return sprintf( '<%1$s class="kbroo-heading kbroo-site-title">%2$s</%1$s>', $tag, $text );
	}
}
