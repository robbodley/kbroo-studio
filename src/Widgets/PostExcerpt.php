<?php
/**
 * Post excerpt: the excerpt of the post being viewed (its own, or the
 * start of its content). Settings match Elementor Pro's Post Excerpt
 * widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class PostExcerpt extends Widget {

	public function get_name(): string {
		return 'post-excerpt';
	}

	public function get_title(): string {
		return __( 'Post excerpt', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-editor-paragraph';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Excerpt', 'kbroo-studio' ) );
		$style   = array(
			'tab'     => 'style',
			'section' => __( 'Excerpt', 'kbroo-studio' ),
		);
		$wrap    = '{{WRAPPER}} .kbroo-post-excerpt';
		return array_merge(
			array(
				'excerpt_length' => $content + array(
					'type'        => 'number',
					'label'       => __( 'Length (words)', 'kbroo-studio' ),
					'default'     => '',
					'min'         => 0,
					'max'         => 500,
					'description' => __( 'Leave empty for WordPress\'s own length. A post\'s own excerpt is shortened too.', 'kbroo-studio' ),
				),
				'align'          => ControlGroups::align( $wrap, $style ),
				'text_color'     => $style + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $wrap => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography', $wrap, $style )
		);
	}

	public function render( array $settings ): string {
		$length = (int) $settings['excerpt_length'];
		return DynamicPost::with(
			function ( $post ) use ( $length ) {
				$excerpt = get_the_excerpt( $post );
				if ( $length > 0 ) {
					$excerpt = wp_trim_words( $excerpt, $length );
				}
				if ( '' === trim( $excerpt ) ) {
					return '';
				}
				return '<div class="kbroo-post-excerpt">' . wpautop( esc_html( $excerpt ) ) . '</div>';
			}
		);
	}
}
