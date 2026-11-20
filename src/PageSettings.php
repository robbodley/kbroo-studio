<?php
/**
 * Page settings: the settings that belong to a whole page or post rather
 * than to one element. Title, status, excerpt, featured image and comments
 * are WordPress's own; hide title and the page layout (default, full width
 * or canvas) are Kbroo Studio's, and apply while the page is built with
 * Kbroo Studio.
 *
 * Kbroo Studio's own settings live in their own post meta, so a page
 * converted from Elementor keeps Elementor's page template untouched.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class PageSettings {

	/** "yes" or "no". Unset: whatever the theme does for builder pages. */
	const META_HIDE_TITLE = '_kbroo_hide_title';

	/** One of LAYOUTS. Unset or "": the theme's own template. */
	const META_LAYOUT = '_kbroo_page_layout';

	const LAYOUTS = array( '', 'full_width', 'canvas' );

	/** Statuses the editor can set. */
	const STATUSES = array( 'draft', 'pending', 'private', 'publish' );

	public function hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'template_include', array( $this, 'template' ), 99 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_action( 'wp_head', array( $this, 'hide_title_css' ), 20 );
		add_action( 'init', array( $this, 'register_content_block' ) );
		add_filter( 'theme_templates', array( $this, 'keep_elementor_template' ), 10, 4 );
	}

	public function register_meta(): void {
		foreach ( Document::post_types() as $post_type ) {
			foreach ( array( self::META_HIDE_TITLE, self::META_LAYOUT ) as $key ) {
				register_post_meta(
					$post_type,
					$key,
					array(
						'type'         => 'string',
						'single'       => true,
						'show_in_rest' => false,
					)
				);
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * Whether a post type has page settings (saved templates do not).
	 *
	 * @param WP_Post|int|null $post Post.
	 */
	public static function applies_to( $post ): bool {
		$post = get_post( $post );
		return $post && in_array( $post->post_type, Document::post_types(), true );
	}

	/**
	 * Whether the active theme hides the title of builder pages by itself.
	 * Kbroo Hello (and any theme with the same contract) does.
	 */
	public static function theme_hides_title(): bool {
		return current_theme_supports( 'kbroo-theme-locations' );
	}

	/**
	 * Whether the page's title is hidden.
	 *
	 * @param WP_Post|int $post Post.
	 */
	public static function hides_title( $post ): bool {
		$post  = get_post( $post );
		$value = $post ? get_post_meta( $post->ID, self::META_HIDE_TITLE, true ) : '';
		if ( 'yes' === $value ) {
			return true;
		}
		if ( 'no' === $value ) {
			return false;
		}
		return self::theme_hides_title();
	}

	/**
	 * The page layout: "", "full_width" or "canvas".
	 *
	 * @param WP_Post|int $post Post.
	 */
	public static function layout( $post ): string {
		$post  = get_post( $post );
		$value = $post ? (string) get_post_meta( $post->ID, self::META_LAYOUT, true ) : '';
		return in_array( $value, self::LAYOUTS, true ) ? $value : '';
	}

	/**
	 * The layout in effect for this request: only for a singular page built
	 * with Kbroo Studio (switching a page back to Elementor turns it off).
	 */
	public static function current_layout(): string {
		if ( ! is_singular() ) {
			return '';
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || ! self::applies_to( $post ) || ! Document::is_built_with_kbroo( $post ) ) {
			return '';
		}
		return self::layout( $post );
	}

	/**
	 * Whether this request shows a blank canvas page, with no site header
	 * or footer: our Canvas layout, or Kbroo Hello's own Canvas template.
	 */
	public static function is_canvas_request(): bool {
		return 'canvas' === self::current_layout() || ( is_singular() && is_page_template( 'templates/canvas.php' ) );
	}

	/**
	 * Everything the editor's Page settings panel shows.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function for_editor( WP_Post $post ): array {
		$thumb_id = (int) get_post_thumbnail_id( $post );
		$thumb    = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'medium' ) : false;
		$type     = get_post_type_object( $post->post_type );

		return array(
			'status'       => $post->post_status,
			'excerpt'      => $post->post_excerpt,
			'featured'     => array(
				'id'  => $thumb ? $thumb_id : '',
				'url' => $thumb ? $thumb[0] : '',
			),
			'comments'     => 'open' === $post->comment_status,
			'hide_title'   => self::hides_title( $post ),
			'layout'       => self::layout( $post ),
			'supports'     => array(
				'excerpt'  => post_type_supports( $post->post_type, 'excerpt' ),
				'featured' => post_type_supports( $post->post_type, 'thumbnail' ) && current_theme_supports( 'post-thumbnails', $post->post_type ),
				'comments' => post_type_supports( $post->post_type, 'comments' ),
			),
			'canPublish'   => $type ? current_user_can( $type->cap->publish_posts ) : false,
			'themeHidesTitle' => self::theme_hides_title(),
		);
	}

	/* ------------------------------------------------------------------ *
	 * Saving
	 * ------------------------------------------------------------------ */

	/**
	 * Store Kbroo Studio's own settings (hide title, layout).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $values  hide_title (bool) and/or layout (string).
	 */
	public static function save_meta( int $post_id, array $values ): void {
		if ( array_key_exists( 'hide_title', $values ) ) {
			update_post_meta( $post_id, self::META_HIDE_TITLE, $values['hide_title'] ? 'yes' : 'no' );
		}
		if ( array_key_exists( 'layout', $values ) ) {
			$layout = in_array( $values['layout'], self::LAYOUTS, true ) ? $values['layout'] : '';
			if ( '' === $layout ) {
				delete_post_meta( $post_id, self::META_LAYOUT );
			} else {
				update_post_meta( $post_id, self::META_LAYOUT, $layout );
			}
		}
	}

	/**
	 * Save the settings sent by the editor. WordPress's own post fields are
	 * returned for the caller's wp_update_post(); the rest is stored here.
	 *
	 * @param WP_Post $post Post.
	 * @param mixed   $raw  The editor's `page` object.
	 * @return array Post fields to update (post_excerpt, comment_status).
	 */
	public static function save( WP_Post $post, $raw ): array {
		if ( ! is_array( $raw ) || ! self::applies_to( $post ) ) {
			return array();
		}

		$meta = array();
		if ( array_key_exists( 'hide_title', $raw ) ) {
			$meta['hide_title'] = (bool) $raw['hide_title'];
		}
		if ( array_key_exists( 'layout', $raw ) ) {
			$meta['layout'] = is_string( $raw['layout'] ) ? $raw['layout'] : '';
		}
		self::save_meta( $post->ID, $meta );

		$fields = array();
		if ( array_key_exists( 'excerpt', $raw ) && is_string( $raw['excerpt'] ) && post_type_supports( $post->post_type, 'excerpt' ) ) {
			$fields['post_excerpt'] = sanitize_textarea_field( $raw['excerpt'] );
		}
		if ( array_key_exists( 'comments', $raw ) && post_type_supports( $post->post_type, 'comments' ) ) {
			$fields['comment_status'] = $raw['comments'] ? 'open' : 'closed';
		}
		if ( array_key_exists( 'featured', $raw ) && post_type_supports( $post->post_type, 'thumbnail' ) ) {
			$image = absint( $raw['featured'] );
			if ( $image && wp_attachment_is_image( $image ) ) {
				set_post_thumbnail( $post->ID, $image );
			} elseif ( ! $image ) {
				delete_post_thumbnail( $post->ID );
			}
		}
		return $fields;
	}

	/* ------------------------------------------------------------------ *
	 * Front end
	 * ------------------------------------------------------------------ */

	/**
	 * Use the Full width or Canvas template for pages that chose one.
	 *
	 * @param string $template Template file chosen so far.
	 */
	public function template( $template ) {
		$layout = self::current_layout();
		if ( 'canvas' === $layout ) {
			return KBROO_STUDIO_DIR . 'templates/page-canvas.php';
		}
		if ( 'full_width' !== $layout ) {
			return $template;
		}
		if ( ! wp_is_block_theme() ) {
			return KBROO_STUDIO_DIR . 'templates/page-full-width.php';
		}
		// A block theme: its header and footer parts (which a Kbroo Studio
		// header or footer then replaces) around the page content.
		global $_wp_current_template_content;
		$theme = wp_json_encode( get_stylesheet() );
		$_wp_current_template_content = '<!-- wp:template-part {"slug":"header","theme":' . $theme . ',"tagName":"header","area":"header"} /-->'
			. '<!-- wp:kbroo/page-content /-->'
			. '<!-- wp:template-part {"slug":"footer","theme":' . $theme . ',"tagName":"footer","area":"footer"} /-->';
		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * The page content and comments, for the Full width layout in block
	 * themes.
	 */
	public function register_content_block(): void {
		register_block_type(
			'kbroo/page-content',
			array(
				'render_callback' => function () {
					// Core already runs the loop around singular block
					// templates; otherwise run it here.
					if ( in_the_loop() ) {
						$html = self::block_post_html();
					} else {
						$html = '';
						while ( have_posts() ) {
							the_post();
							$html .= self::block_post_html();
						}
					}
					return '<main id="content" class="kbroo-page-layout kbroo-page-layout--full-width">' . $html . '</main>';
				},
			)
		);
	}

	/** The current post's content and comments, in a block theme. */
	private static function block_post_html(): string {
		$html = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter.
		if ( comments_open() || get_comments_number() ) {
			$comments = new \WP_Block(
				array(
					'blockName' => 'core/comments',
					'attrs'     => array( 'legacy' => true ),
				),
				array(
					'postId'   => get_the_ID(),
					'postType' => get_post_type(),
				)
			);
			$html .= '<div class="kbroo-page-comments">' . $comments->render() . '</div>';
		}
		return $html;
	}

	/**
	 * Comments under the content, when the page allows them (or has some).
	 * Used by the Full width and Canvas templates.
	 */
	public static function print_comments(): void {
		if ( ! comments_open() && ! get_comments_number() ) {
			return;
		}
		echo '<div class="kbroo-page-comments">';
		comments_template();
		echo '</div>';
	}

	/**
	 * @param string[] $classes Body classes.
	 */
	public function body_class( $classes ): array {
		$classes = (array) $classes;
		$layout  = self::current_layout();
		if ( '' !== $layout ) {
			$classes[] = 'kbroo-template-' . str_replace( '_', '-', $layout );
		}
		if ( self::hides_current_title() ) {
			$classes[] = 'kbroo-hide-title';
		}
		return $classes;
	}

	/** Whether the queried page is a builder page with its title hidden. */
	private static function hides_current_title(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && self::applies_to( $post ) && Document::is_built_with_kbroo( $post ) && self::hides_title( $post );
	}

	/**
	 * Hide the theme's title. Kbroo Hello leaves it out by itself; other
	 * themes print it, so it is hidden with CSS, as Elementor does.
	 */
	public function hide_title_css(): void {
		if ( ! self::hides_current_title() || '' !== self::current_layout() ) {
			return;
		}
		/**
		 * Filters the CSS selector of the theme's page title, hidden when a
		 * page turns on Hide title.
		 *
		 * @param string $selector CSS selector.
		 */
		$selector = (string) apply_filters( 'kbroo_studio_hide_title_selector', 'h1.entry-title, h1.wp-block-post-title' );
		if ( '' === trim( $selector ) ) {
			return;
		}
		echo '<style id="kbroo-hide-title">' . wp_strip_all_tags( $selector ) . '{display:none !important}</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- Tags stripped.
	}

	/* ------------------------------------------------------------------ *
	 * Elementor
	 * ------------------------------------------------------------------ */

	/**
	 * While Elementor is switched off, its page templates are unknown to
	 * WordPress, and any update of a page that uses one (such as saving it
	 * here) would reset it to the default. List them for those pages, so
	 * Elementor's setting survives for switching back or converting again.
	 *
	 * @param string[]     $templates Page templates.
	 * @param mixed        $theme     Theme.
	 * @param WP_Post|null $post      Post being edited or saved.
	 */
	public function keep_elementor_template( $templates, $theme = null, $post = null ) {
		if ( ! $post instanceof WP_Post || class_exists( '\\Elementor\\Plugin' ) ) {
			return $templates;
		}
		$current = (string) get_post_meta( $post->ID, '_wp_page_template', true );
		$names   = array(
			'elementor_canvas'        => __( 'Elementor Canvas', 'kbroo-studio' ),
			'elementor_header_footer' => __( 'Elementor Full Width', 'kbroo-studio' ),
		);
		if ( isset( $names[ $current ] ) ) {
			$templates                = (array) $templates;
			$templates[ $current ] = $names[ $current ];
		}
		return $templates;
	}

	/**
	 * An Elementor page's Hide title and page template, as ours. Elementor
	 * Canvas becomes Canvas, Elementor Full Width becomes Full width.
	 *
	 * @param int $post_id Post ID.
	 * @return array{hide_title: bool, layout: string}
	 */
	public static function from_elementor( int $post_id ): array {
		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		$template = (string) get_post_meta( $post_id, '_wp_page_template', true );
		$layouts  = array(
			'elementor_canvas'        => 'canvas',
			'elementor_header_footer' => 'full_width',
		);
		return array(
			'hide_title' => is_array( $settings ) && 'yes' === ( $settings['hide_title'] ?? '' ),
			'layout'     => $layouts[ $template ] ?? '',
		);
	}
}
