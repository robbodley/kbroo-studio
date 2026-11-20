<?php
/**
 * Post title: the title of the post or page being viewed, styled like a
 * heading. For single post and page templates. Settings match Elementor
 * Pro's Post Title widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class PostTitle extends Heading {

	public function get_name(): string {
		return 'post-title';
	}

	public function get_title(): string {
		return __( 'Post title', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-heading';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$controls = parent::widget_controls();
		unset( $controls['title'] );
		$controls['header_size']['section'] = __( 'Post title', 'kbroo-studio' );
		$controls['header_size']['default'] = 'h1';
		return array(
			'link_to_post' => array(
				'type'    => 'switcher',
				'label'   => __( 'Link to the post', 'kbroo-studio' ),
				'section' => __( 'Post title', 'kbroo-studio' ),
				'default' => '',
			),
		) + $controls;
	}

	public function render( array $settings ): string {
		$tag = in_array( $settings['header_size'], self::TAGS, true ) ? $settings['header_size'] : 'h1';
		return DynamicPost::with(
			function ( $post ) use ( $tag, $settings ) {
				$title = wp_kses( get_the_title( $post ), self::INLINE_HTML );
				if ( 'yes' === $settings['link_to_post'] ) {
					$title = '<a href="' . esc_url( get_permalink( $post ) ) . '">' . $title . '</a>';
				}
				return sprintf( '<%1$s class="kbroo-heading kbroo-post-title">%2$s</%1$s>', $tag, $title );
			}
		);
	}
}
