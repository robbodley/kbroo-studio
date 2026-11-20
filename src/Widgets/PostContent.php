<?php
/**
 * Post content: the content of the post or page being viewed, as the
 * theme would show it (a page built with Kbroo Studio shows its layout).
 * For single post and page templates. Settings match Elementor Pro's Post
 * Content widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class PostContent extends Widget {

	/** @var int How deep post content is being rendered, so it cannot loop. */
	private static $depth = 0;

	public function get_name(): string {
		return 'post-content';
	}

	public function get_title(): string {
		return __( 'Post content', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-text-page';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$style = array(
			'tab'     => 'style',
			'section' => __( 'Content', 'kbroo-studio' ),
		);
		$wrap  = '{{WRAPPER}} .kbroo-post-content';
		return array_merge(
			array(
				'align'      => ControlGroups::align( $wrap, $style ),
				'text_color' => $style + array(
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
		if ( self::$depth > 0 ) {
			return ''; // Post content inside post content.
		}
		return DynamicPost::with(
			function ( $post ) {
				self::$depth++;
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
				$content = apply_filters( 'the_content', get_the_content( null, false, $post ) );
				$content = str_replace( ']]>', ']]&gt;', $content );
				self::$depth--;
				return '<div class="kbroo-post-content">' . $content . '</div>';
			}
		);
	}
}
