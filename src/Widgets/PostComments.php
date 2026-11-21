<?php
/**
 * Post comments: the comments and comment form of the post being viewed,
 * as the theme shows them (its comments.php). Matches Elementor Pro's Post
 * Comments widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class PostComments extends Widget {

	public function get_name(): string {
		return 'post-comments';
	}

	public function get_title(): string {
		return __( 'Post comments', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-admin-comments';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		return array(
			'heading_text' => array(
				'type'        => 'text',
				'label'       => __( 'Heading above', 'kbroo-studio' ),
				'section'     => __( 'Comments', 'kbroo-studio' ),
				'default'     => '',
				'description' => __( 'Optional. Comments show only where the post allows them (Discussion settings).', 'kbroo-studio' ),
			),
		);
	}

	public function render( array $settings ): string {
		return DynamicPost::with(
			function ( $post ) use ( $settings ) {
				if ( ! comments_open( $post ) && ! get_comments_number( $post ) ) {
					return '';
				}
				$html = wp_is_block_theme() ? self::block_comments( $post ) : self::theme_comments();
				if ( '' === trim( $html ) ) {
					return '';
				}
				$heading = '' !== $settings['heading_text'] ? '<h3 class="kbroo-post-comments__heading">' . esc_html( $settings['heading_text'] ) . '</h3>' : '';
				return '<div class="kbroo-post-comments">' . $heading . $html . '</div>';
			}
		);
	}

	/** Classic themes: the theme's comments.php. */
	private static function theme_comments(): string {
		global $withcomments;
		$previous     = $withcomments;
		$withcomments = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Lets comments_template() run outside single.php.
		ob_start();
		comments_template();
		$html         = (string) ob_get_clean();
		$withcomments = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return $html;
	}

	/**
	 * Block themes have no comments.php: WordPress's Comments block, as
	 * block themes show it.
	 *
	 * @param \WP_Post $post Post shown.
	 */
	private static function block_comments( $post ): string {
		$markup = '<!-- wp:comments --><div class="wp-block-comments">'
			. '<!-- wp:comments-title /-->'
			. '<!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-date /--><!-- wp:comment-content /--><!-- wp:comment-reply-link /--><!-- /wp:comment-template -->'
			. '<!-- wp:comments-pagination --><!-- wp:comments-pagination-previous /--><!-- wp:comments-pagination-numbers /--><!-- wp:comments-pagination-next /--><!-- /wp:comments-pagination -->'
			. '<!-- wp:post-comments-form /-->'
			. '</div><!-- /wp:comments -->';
		$blocks = parse_blocks( $markup );
		$block  = new \WP_Block(
			$blocks[0],
			array(
				'postId'   => $post->ID,
				'postType' => $post->post_type,
			)
		);
		return (string) $block->render();
	}
}
