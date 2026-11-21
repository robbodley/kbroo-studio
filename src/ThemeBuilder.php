<?php
/**
 * Site headers, footers and blog archive pages built with Kbroo Studio.
 *
 * A header or footer is a template (see Templates) of type "header" or
 * "footer" with display settings: where it shows (the whole site, the front
 * page, all pages, all posts, archives, the 404 page, or chosen pages and
 * posts, with exceptions), whether it is switched on, and for headers
 * whether it sticks to the top of the window.
 *
 * Where several match, the most specific wins (a chosen page beats "all
 * pages", which beats "entire site"), then the most recently changed.
 *
 * It replaces the theme's own header or footer:
 * - Classic themes: on get_header / get_footer, Kbroo Studio prints the
 *   document head and its header (or its footer and the closing tags) and
 *   loads the theme's header.php / footer.php into a discarded buffer, so
 *   core does not load it again. This is how Elementor Pro does it too.
 * - Block themes: the header and footer template parts are swapped as they
 *   render.
 *
 * The other types replace the theme's whole template (between its header
 * and footer) for the requests they are set for, like Elementor Pro's
 * theme builder:
 * - archive: the blog page, archives and search results;
 * - search-results: search results only (beats an archive template);
 * - single-post and single-page: single posts, pages and other post types
 *   (the Post title, Post content... widgets show the post being viewed);
 * - error-404: the page not found page.
 * Classic themes get a template that calls get_header() and get_footer()
 * around it; block themes get a block template with the theme's header and
 * footer parts around the kbroo/theme-template block.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ThemeBuilder {

	const META_DISPLAY = '_kbroo_display';

	/** Template types that replace part of the theme. */
	const TYPES = array( 'header', 'footer', 'archive', 'search-results', 'single-post', 'single-page', 'error-404' );

	/** The ones that are template parts around every page. */
	const AREAS = array( 'header', 'footer' );

	/** The part of the page each type fills. Types of one area compete. */
	const AREA_OF = array(
		'header'         => 'header',
		'footer'         => 'footer',
		'archive'        => 'archive',
		'search-results' => 'archive',
		'single-post'    => 'single',
		'single-page'    => 'single',
		'error-404'      => 'not_found',
	);

	/** Areas that replace the theme's template between header and footer. */
	const CONTENT_AREAS = array( 'archive', 'single', 'not_found' );

	/** Where a new template of each type shows until changed. */
	const DEFAULT_TARGET = array(
		'header'         => 'entire_site',
		'footer'         => 'entire_site',
		'archive'        => 'archives',
		'search-results' => 'search',
		'single-post'    => 'posts',
		'single-page'    => 'pages',
		'error-404'      => 'not_found',
	);

	/** How specific each target is; higher wins. */
	const SPECIFICITY = array(
		'entire_site'  => 10,
		'singular_all' => 15,
		'archives'     => 20,
		'pages'        => 20,
		'posts'        => 20,
		'post_type'    => 20,
		'blog'         => 25,
		'category'     => 25,
		'tag'          => 25,
		'search'       => 25,
		'author'       => 28,
		'front_page'   => 30,
		'not_found'    => 30,
		'in_category'  => 30,
		'in_tag'       => 30,
		'singular'     => 40,
	);

	/** Most chosen pages and posts one header or footer keeps. */
	const MAX_ITEMS = 200;

	/** @var Frontend */
	private $frontend;

	/** @var array<string,WP_Post|null> Templates found for this request, by area. */
	private $found = array();

	/** @var array<string,bool> Areas already printed. */
	private $printed = array();

	public function __construct( Frontend $frontend ) {
		$this->frontend = $frontend;
	}

	public function hooks(): void {
		add_action( 'get_header', array( $this, 'classic_header' ), 20, 1 );
		add_action( 'get_footer', array( $this, 'classic_footer' ), 20, 1 );
		add_filter( 'render_block_core/template-part', array( $this, 'block_template_part' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'template_include', array( $this, 'archive_template' ), 999 );
		add_action( 'init', array( $this, 'register_archive_block' ) );
	}

	/* ------------------------------------------------------------------ *
	 * Display settings
	 * ------------------------------------------------------------------ */

	/**
	 * Where a header or footer can show, and labels for each.
	 *
	 * @return array<string,string>
	 */
	public static function targets(): array {
		return array(
			'entire_site' => __( 'Entire site', 'kbroo-studio' ),
			'front_page'  => __( 'Front page', 'kbroo-studio' ),
			'pages'       => __( 'All pages', 'kbroo-studio' ),
			'posts'       => __( 'All posts', 'kbroo-studio' ),
			'archives'    => __( 'Blog, archives and search results', 'kbroo-studio' ),
			'not_found'   => __( 'Page not found (404)', 'kbroo-studio' ),
		);
	}

	/**
	 * Where a blog archive template can show.
	 *
	 * @return array<string,string>
	 */
	public static function archive_targets(): array {
		return array(
			'archives' => __( 'All archives (blog, categories, tags, authors, dates, search)', 'kbroo-studio' ),
			'blog'     => __( 'Blog page', 'kbroo-studio' ),
			'category' => __( 'Category archives', 'kbroo-studio' ),
			'tag'      => __( 'Tag archives', 'kbroo-studio' ),
			'search'   => __( 'Search results', 'kbroo-studio' ),
		);
	}

	/**
	 * Where a single post or page template can show: kinds of post (more
	 * are picked one by one, by category, tag or author in the editor).
	 *
	 * @return array<string,string>
	 */
	public static function single_targets(): array {
		$targets = array(
			'singular_all' => __( 'All posts and pages', 'kbroo-studio' ),
			'posts'        => __( 'All posts', 'kbroo-studio' ),
			'pages'        => __( 'All pages', 'kbroo-studio' ),
			'front_page'   => __( 'Front page', 'kbroo-studio' ),
		);
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'post', 'page', 'attachment', Templates::POST_TYPE ), true ) ) {
				continue;
			}
			/* translators: %s: post type name, plural (e.g. "Products"). */
			$targets[ 'post_type:' . $type->name ] = sprintf( __( 'All %s', 'kbroo-studio' ), $type->labels->name );
		}
		return $targets;
	}

	/**
	 * The targets offered for a template type.
	 *
	 * @return array<string,string>
	 */
	public static function targets_for( string $type ): array {
		switch ( self::AREA_OF[ $type ] ?? '' ) {
			case 'archive':
				return 'search-results' === $type ? array( 'search' => __( 'Search results', 'kbroo-studio' ) ) : self::archive_targets();
			case 'single':
				return self::single_targets();
			case 'not_found':
				return array( 'not_found' => __( 'Page not found (404)', 'kbroo-studio' ) );
		}
		return self::targets();
	}

	/** The area a template type fills ('' for library templates). */
	public static function area_of( string $type ): string {
		return self::AREA_OF[ $type ] ?? '';
	}

	/** Every target, with labels. */
	private static function all_targets(): array {
		return self::targets() + self::archive_targets() + self::single_targets();
	}

	/**
	 * A template's display settings.
	 *
	 * @return array{active: bool, sticky: bool, include: string[], exclude: string[]}
	 */
	public static function get_display( $post ): array {
		$post = get_post( $post );
		$raw  = $post ? get_post_meta( $post->ID, self::META_DISPLAY, true ) : null;
		return self::sanitize_display( is_array( $raw ) ? $raw : array( 'include' => array() ) );
	}

	/**
	 * @param mixed $raw Display settings from a request or storage.
	 */
	public static function sanitize_display( $raw ): array {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = function ( $list ) {
			$out = array();
			foreach ( is_array( $list ) ? $list : array() as $target ) {
				$target = is_string( $target ) ? $target : '';
				if ( array_key_exists( $target, self::all_targets() ) ) {
					$out[] = $target;
				} elseif ( preg_match( '/^(singular|in_category|in_tag|author):(\d{1,12})$/', $target, $m ) && (int) $m[2] > 0 ) {
					$out[] = $m[1] . ':' . (int) $m[2];
				} elseif ( preg_match( '/^post_type:([a-z0-9_-]{1,20})$/', $target, $m ) ) {
					$out[] = $target; // Kept even while its plugin is off.
				}
			}
			return array_slice( array_values( array_unique( $out ) ), 0, self::MAX_ITEMS );
		};
		return array(
			'active'  => ! array_key_exists( 'active', $raw ) || ! empty( $raw['active'] ),
			'sticky'  => ! empty( $raw['sticky'] ),
			'include' => $clean( $raw['include'] ?? array() ),
			'exclude' => $clean( $raw['exclude'] ?? array() ),
		);
	}

	public static function save_display( int $post_id, $raw ): void {
		update_post_meta( $post_id, self::META_DISPLAY, self::sanitize_display( $raw ) );
	}

	/**
	 * A short description of where a header or footer shows, for lists.
	 */
	public static function summary( $post ): string {
		$display = self::get_display( $post );
		if ( ! $display['active'] ) {
			return __( 'Switched off', 'kbroo-studio' );
		}
		if ( ! $display['include'] ) {
			return __( 'Not shown anywhere yet', 'kbroo-studio' );
		}
		$names = array_map( array( self::class, 'target_label' ), $display['include'] );
		$text  = implode( ', ', $names );
		if ( $display['exclude'] ) {
			/* translators: 1: where it shows, 2: exceptions. */
			$text = sprintf( __( '%1$s, except %2$s', 'kbroo-studio' ), $text, implode( ', ', array_map( array( self::class, 'target_label' ), $display['exclude'] ) ) );
		}
		return $text;
	}

	public static function target_label( string $target ): string {
		list( $kind, $id ) = array_pad( explode( ':', $target, 2 ), 2, '' );
		if ( 'singular' === $kind ) {
			$title = get_the_title( (int) $id );
			return '' !== $title ? '"' . $title . '"' : '#' . $id;
		}
		if ( 'in_category' === $kind || 'in_tag' === $kind ) {
			$term = get_term( (int) $id );
			$name = $term && ! is_wp_error( $term ) ? $term->name : '#' . $id;
			/* translators: %s: category or tag name. */
			return sprintf( 'in_category' === $kind ? __( 'Posts in category "%s"', 'kbroo-studio' ) : __( 'Posts tagged "%s"', 'kbroo-studio' ), $name );
		}
		if ( 'author' === $kind ) {
			$user = get_userdata( (int) $id );
			/* translators: %s: author name. */
			return sprintf( __( 'Posts by %s', 'kbroo-studio' ), $user ? $user->display_name : '#' . $id );
		}
		if ( 'post_type' === $kind && ! post_type_exists( $id ) ) {
			/* translators: %s: post type key. */
			return sprintf( __( 'All "%s" (not available now)', 'kbroo-studio' ), $id );
		}
		if ( 'archives' === $target ) {
			return __( 'All archives', 'kbroo-studio' );
		}
		return self::all_targets()[ $target ] ?? $target;
	}

	/**
	 * What can be picked one by one in the editor for a template type: pages
	 * and posts for headers, footers and single templates, and categories,
	 * tags and authors for single templates.
	 *
	 * @return array<int,array{value: string, label: string, group: string}>
	 */
	public static function pickables( string $type ): array {
		$area = self::area_of( $type );
		if ( ! in_array( $area, array( 'header', 'footer', 'single' ), true ) ) {
			return array();
		}
		$out = array();
		foreach ( self::pickable_posts() as $post ) {
			$out[] = array(
				'value' => 'singular:' . $post['id'],
				'label' => $post['title'],
				'group' => 'post' === $post['type'] ? __( 'Posts', 'kbroo-studio' ) : __( 'Pages', 'kbroo-studio' ),
			);
		}
		if ( 'single' !== $area ) {
			return $out;
		}
		foreach ( array( 'category' => 'in_category', 'post_tag' => 'in_tag' ) as $taxonomy => $kind ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => self::MAX_ITEMS,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$out[] = array(
					'value' => $kind . ':' . $term->term_id,
					'label' => $term->name,
					'group' => 'category' === $taxonomy ? __( 'Posts in category', 'kbroo-studio' ) : __( 'Posts with tag', 'kbroo-studio' ),
				);
			}
		}
		$authors = get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'number'     => self::MAX_ITEMS,
				'fields'     => array( 'ID', 'display_name' ),
			)
		);
		foreach ( $authors as $user ) {
			$out[] = array(
				'value' => 'author:' . $user->ID,
				'label' => $user->display_name,
				'group' => __( 'Posts by', 'kbroo-studio' ),
			);
		}
		return $out;
	}

	/**
	 * Pages and posts that can be picked one by one.
	 */
	public static function pickable_posts(): array {
		$out = array();
		foreach ( array( 'page', 'post' ) as $type ) {
			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => array( 'publish', 'private', 'draft', 'future' ),
					'posts_per_page' => self::MAX_ITEMS,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			foreach ( $posts as $post ) {
				$out[] = array(
					'id'    => $post->ID,
					'title' => '' !== $post->post_title ? $post->post_title : '#' . $post->ID,
					'type'  => $type,
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ *
	 * Matching
	 * ------------------------------------------------------------------ */

	/**
	 * Whether a target matches the current request.
	 */
	private static function matches( string $target ): bool {
		switch ( $target ) {
			case 'entire_site':
				return true;
			case 'front_page':
				return is_front_page();
			case 'pages':
				return is_page();
			case 'posts':
				return is_singular( 'post' );
			case 'archives':
				return is_archive() || is_home() || is_search();
			case 'not_found':
				return is_404();
			case 'blog':
				return is_home();
			case 'category':
				return is_category();
			case 'tag':
				return is_tag();
			case 'search':
				return is_search();
		}
		list( $kind, $value ) = array_pad( explode( ':', $target, 2 ), 2, '' );
		if ( 'singular_all' === $kind ) {
			return is_singular() && ! is_attachment();
		}
		if ( ! is_singular() ) {
			return false;
		}
		switch ( $kind ) {
			case 'singular':
				return (int) $value === (int) get_queried_object_id();
			case 'post_type':
				return is_singular( $value );
			case 'in_category':
				return has_term( (int) $value, 'category', get_queried_object_id() );
			case 'in_tag':
				return has_term( (int) $value, 'post_tag', get_queried_object_id() );
			case 'author':
				$post = get_queried_object();
				return $post instanceof WP_Post && (int) $value === (int) $post->post_author;
		}
		return false;
	}

	private static function specificity( string $target ): int {
		$key = explode( ':', $target, 2 )[0];
		return self::SPECIFICITY[ $key ] ?? 0;
	}

	/**
	 * The template for an area (header, footer, archive, single or
	 * not_found) for this request, or null for the theme's own.
	 */
	public function find( string $area ): ?WP_Post {
		if ( array_key_exists( $area, $this->found ) ) {
			return $this->found[ $area ];
		}
		$this->found[ $area ] = null;
		if ( is_admin() || is_feed() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return null;
		}
		// A Canvas page has no header or footer, and no single template: it
		// asked for a blank page.
		if ( in_array( $area, array( 'header', 'footer', 'single' ), true ) && PageSettings::is_canvas_request() ) {
			return null;
		}
		$types = array_keys( self::AREA_OF, $area, true );
		if ( ! $types ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_key'       => Templates::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $types, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => 'IN',
			)
		);
		$best = 0;
		foreach ( $posts as $post ) {
			$display = self::get_display( $post );
			if ( ! $display['active'] ) {
				continue;
			}
			$score = 0;
			foreach ( $display['include'] as $target ) {
				if ( self::matches( $target ) ) {
					$score = max( $score, self::specificity( $target ) );
				}
			}
			// A search results template beats an archive template set for search.
			if ( $score && 'search-results' === Templates::type_of( $post ) ) {
				$score++;
			}
			foreach ( $display['exclude'] as $target ) {
				if ( self::matches( $target ) ) {
					$score = 0;
				}
			}
			// Newest first, so on a tie the first one found stays.
			if ( $score > $best ) {
				$best                 = $score;
				$this->found[ $area ] = $post;
			}
		}
		/**
		 * Filters the theme template used for this request.
		 *
		 * @param WP_Post|null $post Template, or null for the theme's own.
		 * @param string       $area "header", "footer", "archive", "single" or "not_found".
		 */
		$this->found[ $area ] = apply_filters( 'kbroo_studio_theme_template', $this->found[ $area ], $area );
		return $this->found[ $area ];
	}

	/* ------------------------------------------------------------------ *
	 * Output
	 * ------------------------------------------------------------------ */

	/**
	 * The header or footer markup, or '' when the theme's own is used.
	 */
	public function render_area( string $area ): string {
		$post = $this->find( $area );
		if ( ! $post ) {
			return '';
		}
		$display = self::get_display( $post );
		if ( in_array( $area, self::CONTENT_AREAS, true ) ) {
			$this->printed[ $area ] = true;
			$class = array(
				'archive'   => 'kbroo-archive-template',
				'single'    => 'kbroo-single-template',
				'not_found' => 'kbroo-404-template',
			)[ $area ];
			return sprintf( '<div class="%s" data-kbroo-template="%d">%s</div>', $class, $post->ID, $this->frontend->render_post( $post ) );
		}
		$tag     = 'header' === $area ? 'header' : 'footer';
		$classes = array( 'kbroo-site-' . $area );
		if ( 'header' === $area && $display['sticky'] ) {
			$classes[] = 'kbroo-site-header--sticky';
		}
		$this->printed[ $area ] = true;
		return sprintf(
			'<%1$s class="%2$s" data-kbroo-template="%3$d">%4$s</%1$s>',
			$tag,
			esc_attr( implode( ' ', $classes ) ),
			$post->ID,
			$this->frontend->render_post( $post )
		);
	}

	/**
	 * Styles for the header and footer, in the head.
	 */
	public function enqueue(): void {
		foreach ( array_unique( self::AREA_OF ) as $area ) {
			$post = $this->find( $area );
			if ( $post ) {
				$this->frontend->enqueue_layout( Document::get_layout( $post ) );
			}
		}
	}

	public function body_class( $classes ): array {
		$classes = (array) $classes;
		foreach ( array_unique( self::AREA_OF ) as $area ) {
			if ( $this->find( $area ) ) {
				$classes[] = 'kbroo-has-' . ( 'not_found' === $area ? '404' : $area );
			}
		}
		return $classes;
	}

	/**
	 * Classic themes: print the head and the header, and keep the theme's
	 * header.php from printing.
	 *
	 * @param string|null $name Header name, as passed to get_header().
	 */
	public function classic_header( $name = null ): void {
		if ( wp_is_block_theme() || ! $this->find( 'header' ) ) {
			return;
		}
		require KBROO_STUDIO_DIR . 'templates/site-header.php';
		$this->swallow_theme_template( 'header', $name, 'wp_head' );
	}

	/**
	 * Classic themes: print the footer and the closing tags, and keep the
	 * theme's footer.php from printing.
	 *
	 * @param string|null $name Footer name, as passed to get_footer().
	 */
	public function classic_footer( $name = null ): void {
		if ( wp_is_block_theme() || ! $this->find( 'footer' ) ) {
			return;
		}
		require KBROO_STUDIO_DIR . 'templates/site-footer.php';
		$this->swallow_theme_template( 'footer', $name, 'wp_footer' );
	}

	/**
	 * Load the theme's header.php or footer.php once, into a buffer that is
	 * thrown away. Core then skips it, since it is required only once. The
	 * hooks it would fire a second time are emptied first.
	 */
	private function swallow_theme_template( string $area, $name, string $hook ): void {
		$templates = array();
		$name      = (string) $name;
		if ( '' !== $name ) {
			$templates[] = "{$area}-{$name}.php";
		}
		$templates[] = "{$area}.php";

		remove_all_actions( $hook );
		if ( 'header' === $area ) {
			remove_all_actions( 'wp_body_open' );
		}
		ob_start();
		locate_template( $templates, true );
		ob_end_clean();
	}

	/**
	 * The content area this request shows: archive (the blog page, archives
	 * and search results), single or not_found, or ''.
	 */
	public static function content_area(): string {
		if ( is_404() ) {
			return 'not_found';
		}
		if ( is_home() || is_archive() || is_search() ) {
			return 'archive';
		}
		if ( is_singular() && ! is_attachment() ) {
			return 'single';
		}
		return '';
	}

	/** @var string Content area the current template file is for. */
	private $current = '';

	/**
	 * Archive, single and 404 templates: use ours instead of the theme's.
	 *
	 * @param string $template Template file chosen so far.
	 */
	public function archive_template( $template ) {
		$area = self::content_area();
		if ( '' === $area || ! $this->find( $area ) ) {
			return $template;
		}
		$this->current = $area;
		if ( ! wp_is_block_theme() ) {
			return KBROO_STUDIO_DIR . 'templates/site-template.php';
		}
		// A block template: the theme's header and footer parts (which a
		// Kbroo Studio header or footer then replaces) around ours.
		global $_wp_current_template_content;
		$theme = wp_json_encode( get_stylesheet() );
		$_wp_current_template_content = '<!-- wp:template-part {"slug":"header","theme":' . $theme . ',"tagName":"header","area":"header"} /-->'
			. '<!-- wp:kbroo/theme-template /-->'
			. '<!-- wp:template-part {"slug":"footer","theme":' . $theme . ',"tagName":"footer","area":"footer"} /-->';
		return ABSPATH . WPINC . '/template-canvas.php';
	}

	/**
	 * The main content for templates/site-template.php and the
	 * kbroo/theme-template block: the template for this request, inside
	 * the loop for single posts so its widgets show that post.
	 */
	public function render_content(): string {
		$area = '' !== $this->current ? $this->current : self::content_area();
		if ( 'single' === $area && ! in_the_loop() && have_posts() ) {
			the_post();
			$html = $this->render_area( $area );
			rewind_posts();
			return $html;
		}
		return $this->render_area( $area );
	}

	public function register_archive_block(): void {
		$callback = function () {
			$area = '' !== $this->current ? $this->current : self::content_area();
			return '<main class="kbroo-' . ( 'not_found' === $area ? '404' : $area ) . '">' . $this->render_content() . '</main>';
		};
		register_block_type( 'kbroo/theme-template', array( 'render_callback' => $callback ) );
		// The name 0.2 used, for block templates saved with it.
		register_block_type( 'kbroo/archive', array( 'render_callback' => $callback ) );
	}

	/**
	 * Block themes: swap the header and footer template parts.
	 *
	 * @param string $content Rendered template part.
	 * @param array  $block   Parsed block.
	 */
	public function block_template_part( $content, $block ) {
		$area = self::template_part_area( (array) ( $block['attrs'] ?? array() ) );
		if ( '' === $area || ! empty( $this->printed[ $area ] ) || ! $this->find( $area ) ) {
			return $content;
		}
		return $this->render_area( $area );
	}

	/**
	 * Whether a template part block is the header or the footer.
	 */
	private static function template_part_area( array $attrs ): string {
		$slug = (string) ( $attrs['slug'] ?? '' );
		$tag  = (string) ( $attrs['tagName'] ?? '' );
		if ( 'header' === $tag ) {
			return 'header';
		}
		if ( 'footer' === $tag ) {
			return 'footer';
		}
		if ( '' !== $slug ) {
			$part = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
			if ( $part && in_array( $part->area, self::AREAS, true ) ) {
				return $part->area;
			}
			foreach ( self::AREAS as $area ) {
				if ( $area === $slug || 0 === strpos( $slug, $area . '-' ) ) {
					return $area;
				}
			}
		}
		return '';
	}
}
