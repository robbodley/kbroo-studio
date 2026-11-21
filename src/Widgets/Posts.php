<?php
/**
 * Posts grid. Settings follow Elementor Pro's posts widget, without its
 * per-skin prefixes (Elementor's classic_columns is columns here; the
 * converter strips the prefix): _skin (classic or cards), columns,
 * posts_per_page, the posts_* query settings, pagination_* and so on.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Document;
use KbrooStudio\Plugin;
use KbrooStudio\Templates;
use WP_Query;

defined( 'ABSPATH' ) || exit;

class Posts extends Widget {

	/** Query variable for the page number, so it never clashes with the page's own. */
	const PAGE_VAR = 'kbroo-page';

	/** Source that shows the posts of the archive being viewed. */
	const CURRENT_QUERY = 'current_query';

	public function get_name(): string {
		return 'posts';
	}

	public function get_title(): string {
		return __( 'Posts', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-grid-view';
	}

	public function server_preview(): bool {
		return true;
	}

	/**
	 * Post types visitors can see, for the query.
	 */
	private static function post_types(): array {
		$options = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$options[ $type->name ] = $type->labels->name;
			}
		}
		return $options;
	}

	/**
	 * Categories and tags (and other public taxonomies), by term id.
	 */
	private static function terms(): array {
		$options = array();
		$taxes   = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		$terms   = get_terms(
			array(
				'taxonomy'   => array_keys( $taxes ),
				'hide_empty' => false,
				'number'     => 300,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $options;
		}
		foreach ( $terms as $term ) {
			$tax                                 = $taxes[ $term->taxonomy ] ?? null;
			$options[ (string) $term->term_id ] = ( $tax ? $tax->labels->singular_name . ': ' : '' ) . $term->name;
		}
		return $options;
	}

	/** Settings the Loop item skin keeps: the rest style the built-in skins. */
	const LOOP_KEEPS = array( '_skin', 'template_id', 'elementor_template_id', 'columns', 'posts_per_page', 'column_gap', 'row_gap' );

	/**
	 * Loop item templates to pick from, for the Loop item skin.
	 *
	 * @return array<string,string>
	 */
	private static function loop_templates(): array {
		$options = array( '' => __( 'Choose a loop item template', 'kbroo-studio' ) );
		$posts   = get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => Templates::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'loop-item', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $posts as $post ) {
			$options[ (string) $post->ID ] = '' !== $post->post_title ? $post->post_title : '#' . $post->ID;
		}
		return $options;
	}

	protected function widget_controls(): array {
		$controls = $this->base_controls();
		// The Loop item skin draws each post with a template: the built-in
		// skins' settings are hidden for it.
		foreach ( $controls as $key => $control ) {
			$tab       = $control['tab'] ?? 'content';
			$selectors = implode( ' ', array_keys( (array) ( $control['selectors'] ?? array() ) ) );
			$classic   = ( 'content' === $tab && ( $control['section'] ?? '' ) === __( 'Layout', 'kbroo-studio' ) ) || preg_match( '/\.kbroo-post(?!s)/', $selectors );
			if ( $classic && ! in_array( $key, self::LOOP_KEEPS, true ) ) {
				$controls[ $key ]['condition'] = (array) ( $control['condition'] ?? array() ) + array( '_skin!' => 'loop' );
			}
		}
		$controls['_skin']['options']['loop'] = __( 'Loop item template', 'kbroo-studio' );
		$position = array_search( '_skin', array_keys( $controls ), true ) + 1;
		return array_slice( $controls, 0, $position, true ) + array(
			'template_id'           => array(
				'type'        => 'select',
				'label'       => __( 'Loop item template', 'kbroo-studio' ),
				'section'     => __( 'Layout', 'kbroo-studio' ),
				'default'     => '',
				'options'     => self::loop_templates(),
				'condition'   => array( '_skin' => 'loop' ),
				'description' => __( 'Make one under Kbroo Studio > Templates > New theme template: Loop item. Its Post title, Featured image, Post info... widgets show each post in turn.', 'kbroo-studio' ),
			),
			// The Elementor loop item a converted grid used, until its
			// template is converted too.
			'elementor_template_id' => array(
				'type'    => 'number',
				'label'   => 'Elementor template',
				'default' => 0,
				'min'     => 0,
				'hidden'  => true,
			),
		) + array_slice( $controls, $position, null, true );
	}

	private function base_controls(): array {
		$layout = array( 'section' => __( 'Layout', 'kbroo-studio' ) );
		$query  = array( 'section' => __( 'Query', 'kbroo-studio' ) );
		// Hidden when the posts come from the archive being viewed.
		$custom = array( 'condition' => array( 'posts_post_type!' => self::CURRENT_QUERY ) );
		$paging = array( 'section' => __( 'Pagination', 'kbroo-studio' ) );
		$cards  = array( 'condition' => array( '_skin' => 'cards' ) );
		$style  = function ( string $section ) {
			return array(
				'tab'     => 'style',
				'section' => $section,
			);
		};
		$box    = $style( __( 'Box', 'kbroo-studio' ) );
		$image  = $style( __( 'Image', 'kbroo-studio' ) );
		$text   = $style( __( 'Text', 'kbroo-studio' ) );
		$post   = '{{WRAPPER}} .kbroo-post';
		$cols   = array();
		foreach ( range( 1, 6 ) as $n ) {
			$cols[ (string) $n ] = (string) $n;
		}
		$sizes = array(
			'thumbnail'    => __( 'Thumbnail', 'kbroo-studio' ),
			'medium'       => __( 'Medium', 'kbroo-studio' ),
			'medium_large' => __( 'Medium large', 'kbroo-studio' ),
			'large'        => __( 'Large', 'kbroo-studio' ),
			'full'         => __( 'Full size', 'kbroo-studio' ),
		);

		return array_merge(
			array(
				'_skin'               => $layout + array(
					'type'    => 'select',
					'label'   => __( 'Skin', 'kbroo-studio' ),
					'default' => 'classic',
					'options' => array(
						'classic' => __( 'Classic', 'kbroo-studio' ),
						'cards'   => __( 'Cards', 'kbroo-studio' ),
					),
				),
				'columns'             => $layout + array(
					'type'           => 'select',
					'label'          => __( 'Columns', 'kbroo-studio' ),
					'default'        => '3',
					'default_tablet' => '2',
					'default_mobile' => '1',
					'responsive'     => true,
					'options'        => $cols,
					'selectors'      => array( '{{WRAPPER}} .kbroo-posts' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));' ),
				),
				'posts_per_page'      => $layout + array(
					'type'    => 'number',
					'label'   => __( 'Posts per page', 'kbroo-studio' ),
					'default' => 6,
					'min'     => 1,
					'max'     => 100,
				),
				'thumbnail'           => $layout + array(
					'type'    => 'select',
					'label'   => __( 'Image', 'kbroo-studio' ),
					'default' => 'top',
					'options' => array(
						'top'   => __( 'Top', 'kbroo-studio' ),
						'left'  => __( 'Left', 'kbroo-studio' ),
						'right' => __( 'Right', 'kbroo-studio' ),
						'none'  => __( 'None', 'kbroo-studio' ),
					),
				),
				'thumbnail_size_size' => $layout + array(
					'type'      => 'select',
					'label'     => __( 'Image size', 'kbroo-studio' ),
					'default'   => 'medium',
					'options'   => $sizes,
					'condition' => array( 'thumbnail!' => 'none' ),
				),
				'item_ratio'          => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Image ratio (height ÷ width)', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'min'        => 0.1,
					'max'        => 2,
					'step'       => 0.01,
					'responsive' => true,
					'default'    => array(
						'unit' => 'px',
						'size' => 0.66,
					),
					'condition'  => array( 'thumbnail!' => 'none' ),
					'selectors'  => array( '{{WRAPPER}} .kbroo-post__thumbnail' => 'aspect-ratio: 1 / {{SIZE}};' ),
				),
				'image_width'         => $layout + array(
					'type'       => 'slider',
					'label'      => __( 'Image width', 'kbroo-studio' ),
					'units'      => array( '%', 'px' ),
					'responsive' => true,
					'condition'  => array( 'thumbnail' => array( 'left', 'right' ) ),
					'selectors'  => array( '{{WRAPPER}} .kbroo-post__thumbnail' => 'flex-basis: {{SIZE}}{{UNIT}};' ),
				),
				'show_title'          => $layout + array(
					'type'    => 'switcher',
					'label'   => __( 'Title', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'title_tag'           => $layout + array(
					'type'      => 'select',
					'label'     => __( 'Title HTML tag', 'kbroo-studio' ),
					'default'   => 'h3',
					'options'   => array_combine( Heading::TAGS, Heading::TAGS ),
					'condition' => array( 'show_title' => 'yes' ),
				),
				'show_excerpt'        => $layout + array(
					'type'    => 'switcher',
					'label'   => __( 'Excerpt', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'excerpt_length'      => $layout + array(
					'type'      => 'number',
					'label'     => __( 'Excerpt length (words)', 'kbroo-studio' ),
					'default'   => 25,
					'min'       => 1,
					'max'       => 200,
					'condition' => array( 'show_excerpt' => 'yes' ),
				),
				'meta_data'           => $layout + array(
					'type'    => 'multiselect',
					'label'   => __( 'Details', 'kbroo-studio' ),
					'default' => array( 'date' ),
					'options' => array(
						'author'   => __( 'Author', 'kbroo-studio' ),
						'date'     => __( 'Date', 'kbroo-studio' ),
						'time'     => __( 'Time', 'kbroo-studio' ),
						'comments' => __( 'Comments', 'kbroo-studio' ),
					),
				),
				'meta_separator'      => $layout + array(
					'type'    => 'text',
					'label'   => __( 'Separator between details', 'kbroo-studio' ),
					'default' => '///',
				),
				'show_read_more'      => $layout + array(
					'type'    => 'switcher',
					'label'   => __( 'Read more link', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'read_more_text'      => $layout + array(
					'type'      => 'text',
					'label'     => __( 'Read more text', 'kbroo-studio' ),
					'default'   => __( 'Read More »', 'kbroo-studio' ),
					'condition' => array( 'show_read_more' => 'yes' ),
				),
				'show_badge'          => $layout + $cards + array(
					'type'    => 'switcher',
					'label'   => __( 'Category badge', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'open_new_tab'        => $layout + array(
					'type'  => 'switcher',
					'label' => __( 'Open in a new tab', 'kbroo-studio' ),
				),

				'posts_post_type'        => $query + array(
					'type'    => 'select',
					'label'   => __( 'Source', 'kbroo-studio' ),
					'default' => 'post',
					'options' => array( self::CURRENT_QUERY => __( 'Current archive (for blog archive templates)', 'kbroo-studio' ) ) + self::post_types(),
				),
				'posts_include_term_ids' => $query + $custom + array(
					'type'        => 'multiselect',
					'label'       => __( 'Only these categories or tags', 'kbroo-studio' ),
					'description' => __( 'Leave all unticked to show every post.', 'kbroo-studio' ),
					'options'     => self::terms(),
				),
				'posts_exclude_term_ids' => $query + $custom + array(
					'type'    => 'multiselect',
					'label'   => __( 'Leave out these categories or tags', 'kbroo-studio' ),
					'options' => self::terms(),
				),
				'posts_orderby'          => $query + $custom + array(
					'type'    => 'select',
					'label'   => __( 'Order by', 'kbroo-studio' ),
					'default' => 'post_date',
					'options' => array(
						'post_date'     => __( 'Date', 'kbroo-studio' ),
						'post_title'    => __( 'Title', 'kbroo-studio' ),
						'menu_order'    => __( 'Menu order', 'kbroo-studio' ),
						'modified'      => __( 'Last modified', 'kbroo-studio' ),
						'comment_count' => __( 'Comment count', 'kbroo-studio' ),
						'rand'          => __( 'Random', 'kbroo-studio' ),
					),
				),
				'posts_order'            => $query + $custom + array(
					'type'    => 'select',
					'label'   => __( 'Order', 'kbroo-studio' ),
					'default' => 'desc',
					'options' => array(
						'desc' => __( 'Newest or Z first', 'kbroo-studio' ),
						'asc'  => __( 'Oldest or A first', 'kbroo-studio' ),
					),
				),
				'posts_offset'           => $query + $custom + array(
					'type'    => 'number',
					'label'   => __( 'Skip the first', 'kbroo-studio' ),
					'default' => 0,
					'min'     => 0,
					'max'     => 100,
				),
				'posts_exclude_current'  => $query + $custom + array(
					'type'    => 'switcher',
					'label'   => __( 'Leave out the page being viewed', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'nothing_found_message'  => $query + array(
					'type'    => 'text',
					'label'   => __( 'Message when there are no posts', 'kbroo-studio' ),
					'default' => '',
				),

				'pagination_type'       => $paging + array(
					'type'    => 'select',
					'label'   => __( 'Pagination', 'kbroo-studio' ),
					'default' => '',
					'options' => array(
						''                      => __( 'None', 'kbroo-studio' ),
						'numbers'               => __( 'Numbers', 'kbroo-studio' ),
						'prev_next'             => __( 'Previous and next', 'kbroo-studio' ),
						'numbers_and_prev_next' => __( 'Numbers, previous and next', 'kbroo-studio' ),
					),
				),
				'pagination_page_limit' => $paging + array(
					'type'      => 'number',
					'label'     => __( 'Page limit', 'kbroo-studio' ),
					'default'   => 5,
					'min'       => 1,
					'max'       => 100,
					'condition' => array( 'pagination_type!' => '' ),
				),
				'pagination_prev_label' => $paging + array(
					'type'      => 'text',
					'label'     => __( 'Previous label', 'kbroo-studio' ),
					'default'   => __( '« Previous', 'kbroo-studio' ),
					'condition' => array( 'pagination_type' => array( 'prev_next', 'numbers_and_prev_next' ) ),
				),
				'pagination_next_label' => $paging + array(
					'type'      => 'text',
					'label'     => __( 'Next label', 'kbroo-studio' ),
					'default'   => __( 'Next »', 'kbroo-studio' ),
					'condition' => array( 'pagination_type' => array( 'prev_next', 'numbers_and_prev_next' ) ),
				),
				'pagination_align'      => $paging + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => '',
					'options'              => array(
						''       => __( 'Default', 'kbroo-studio' ),
						'left'   => __( 'Left', 'kbroo-studio' ),
						'center' => __( 'Center', 'kbroo-studio' ),
						'right'  => __( 'Right', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'left'   => 'flex-start',
						'center' => 'center',
						'right'  => 'flex-end',
					),
					'condition'            => array( 'pagination_type!' => '' ),
					'selectors'            => array( '{{WRAPPER}} .kbroo-pagination' => 'justify-content: {{VALUE}};' ),
				),

				'column_gap'         => $box + array(
					'type'       => 'slider',
					'label'      => __( 'Columns gap', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-posts' => 'column-gap: {{SIZE}}{{UNIT}};' ),
				),
				'row_gap'            => $box + array(
					'type'       => 'slider',
					'label'      => __( 'Rows gap', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-posts' => 'row-gap: {{SIZE}}{{UNIT}};' ),
				),
				'alignment'          => ControlGroups::align( '{{WRAPPER}} .kbroo-post__text', $box ),
				'card_bg_color'      => $box + array(
					'type'      => 'color',
					'label'     => __( 'Background colour', 'kbroo-studio' ),
					'selectors' => array( $post => 'background-color: {{VALUE}};' ),
				),
				'card_border_color'  => $box + array(
					'type'      => 'color',
					'label'     => __( 'Border colour', 'kbroo-studio' ),
					'selectors' => array( $post => 'border-color: {{VALUE}};' ),
				),
				'card_border_width'  => $box + array(
					'type'      => 'slider',
					'label'     => __( 'Border width', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( $post => 'border-style: solid; border-width: {{SIZE}}{{UNIT}};' ),
				),
				'card_border_radius' => $box + array(
					'type'      => 'slider',
					'label'     => __( 'Corner radius', 'kbroo-studio' ),
					'units'     => array( 'px', '%' ),
					'selectors' => array( $post => 'border-radius: {{SIZE}}{{UNIT}};' ),
				),
				'card_padding'       => $box + array(
					'type'       => 'dimensions',
					'label'      => __( 'Text padding', 'kbroo-studio' ),
					'units'      => array( 'px', 'em', '%' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-post__text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'image_border_radius' => $image + array(
					'type'      => 'slider',
					'label'     => __( 'Corner radius', 'kbroo-studio' ),
					'units'     => array( 'px', '%' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__thumbnail' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				),
				'image_spacing'      => $image + array(
					'type'       => 'slider',
					'label'      => __( 'Space after the image', 'kbroo-studio' ),
					'units'      => array( 'px', 'em' ),
					'responsive' => true,
					'selectors'  => array( $post => '--kbroo-post-image-gap: {{SIZE}}{{UNIT}};' ),
				),
				'badge_bg_color'     => $image + $cards + array(
					'type'      => 'color',
					'label'     => __( 'Badge colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__badge' => 'background-color: {{VALUE}};' ),
				),
				'badge_color'        => $image + $cards + array(
					'type'      => 'color',
					'label'     => __( 'Badge text colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__badge' => 'color: {{VALUE}};' ),
				),
				'badge_radius'       => $image + $cards + array(
					'type'      => 'slider',
					'label'     => __( 'Badge corner radius', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__badge' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				),
				'badge_margin'       => $image + $cards + array(
					'type'      => 'slider',
					'label'     => __( 'Badge distance from the corner', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__badge' => 'margin: {{SIZE}}{{UNIT}};' ),
				),
				'title_color'        => $text + array(
					'type'      => 'color',
					'label'     => __( 'Title colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__title, {{WRAPPER}} .kbroo-post__title a' => 'color: {{VALUE}};' ),
				),
				'title_spacing'      => $text + array(
					'type'      => 'slider',
					'label'     => __( 'Space after the title', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'title_typography', '{{WRAPPER}} .kbroo-post__title, {{WRAPPER}} .kbroo-post__title a', $text + array( 'section' => __( 'Title', 'kbroo-studio' ) ) ),
			array(
				'meta_color' => $style( __( 'Details', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__meta' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'meta_typography', '{{WRAPPER}} .kbroo-post__meta', $style( __( 'Details', 'kbroo-studio' ) ) ),
			array(
				'excerpt_color' => $style( __( 'Excerpt', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__excerpt' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'excerpt_typography', '{{WRAPPER}} .kbroo-post__excerpt', $style( __( 'Excerpt', 'kbroo-studio' ) ) ),
			array(
				'read_more_color' => $style( __( 'Read more', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-post__read-more' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'read_more_typography', '{{WRAPPER}} .kbroo-post__read-more', $style( __( 'Read more', 'kbroo-studio' ) ) ),
			array(
				'pagination_color'        => $style( __( 'Pagination', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-pagination a' => 'color: {{VALUE}};' ),
				),
				'pagination_active_color' => $style( __( 'Pagination', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Current page colour', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-pagination .current' => 'color: {{VALUE}};' ),
				),
			)
		);
	}

	/**
	 * The current page number for this widget's pagination.
	 */
	private static function current_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A page number in a public link.
		return isset( $_GET[ self::PAGE_VAR ] ) ? max( 1, absint( $_GET[ self::PAGE_VAR ] ) ) : 1;
	}

	/**
	 * Query arguments from the settings.
	 */
	public static function query_args( array $settings, int $page = 1 ): array {
		$types = self::post_types();
		$per   = max( 1, min( 100, (int) $settings['posts_per_page'] ) );
		$args  = array(
			// "Current archive" outside an archive (the editor) shows the latest posts.
			'post_type'           => isset( $types[ $settings['posts_post_type'] ] ) ? $settings['posts_post_type'] : 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per,
			'orderby'             => in_array( $settings['posts_orderby'], array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' ), true ) ? $settings['posts_orderby'] : 'post_date',
			'order'               => 'asc' === $settings['posts_order'] ? 'ASC' : 'DESC',
			'offset'              => max( 0, (int) $settings['posts_offset'] ) + ( $page - 1 ) * $per,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => '' === $settings['pagination_type'],
		);
		$tax   = array();
		foreach ( array( 'IN' => 'posts_include_term_ids', 'NOT IN' => 'posts_exclude_term_ids' ) as $operator => $key ) {
			$by_taxonomy = array();
			foreach ( (array) $settings[ $key ] as $id ) {
				$term = get_term( (int) $id );
				if ( $term && ! is_wp_error( $term ) ) {
					$by_taxonomy[ $term->taxonomy ][] = $term->term_id;
				}
			}
			foreach ( $by_taxonomy as $taxonomy => $ids ) {
				$tax[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $ids,
					'operator' => $operator,
				);
			}
		}
		if ( $tax ) {
			$args['tax_query'] = array( 'relation' => 'AND' ) + $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}
		$current = get_the_ID();
		if ( 'yes' === $settings['posts_exclude_current'] && $current ) {
			$args['post__not_in'] = array( $current ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}
		return $args;
	}

	/**
	 * Whether to show the posts of the archive being viewed: set to
	 * "current archive", and rendering the page, not an editor preview.
	 */
	private function uses_main_query( array $settings ): bool {
		global $wp_query;
		return self::CURRENT_QUERY === $settings['posts_post_type'] && '' !== $this->element_id
			&& $wp_query instanceof WP_Query && ( $wp_query->is_archive() || $wp_query->is_home() || $wp_query->is_search() );
	}

	public function render( array $settings ): string {
		global $wp_query;
		$main = $this->uses_main_query( $settings );
		if ( $main ) {
			$page  = max( 1, (int) get_query_var( 'paged' ) );
			$query = clone $wp_query;
			$query->rewind_posts();
		} else {
			$page  = self::current_page();
			$query = new WP_Query( self::query_args( $settings, $page ) );
		}
		if ( ! $query->have_posts() ) {
			$message = (string) $settings['nothing_found_message'];
			return '' !== $message ? '<div class="kbroo-posts-nothing">' . esc_html( $message ) . '</div>' : '';
		}

		if ( 'loop' === $settings['_skin'] ) {
			return $this->render_loop( $settings, $query, $page, $main );
		}
		$skin  = 'cards' === $settings['_skin'] ? 'cards' : 'classic';
		$thumb = in_array( $settings['thumbnail'], array( 'top', 'left', 'right', 'none' ), true ) ? $settings['thumbnail'] : 'top';
		$html  = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html .= $this->render_post( $settings, $skin, $thumb );
		}
		wp_reset_postdata();

		$html = sprintf( '<div class="kbroo-posts kbroo-posts--%1$s kbroo-posts--thumb-%2$s">%3$s</div>', $skin, $thumb, $html );
		return $html . $this->pagination( $settings, $query, $page, $main );
	}

	/**
	 * The loop item template a grid uses: the one chosen, or the converted
	 * copy of the Elementor loop item it was converted with.
	 */
	public static function loop_template( array $settings ): ?\WP_Post {
		$id   = (int) $settings['template_id'];
		$post = $id > 0 ? get_post( $id ) : null; // get_post( 0 ) is the current post.
		if ( ! $post && (int) $settings['elementor_template_id'] > 0 && class_exists( '\\KbrooStudio\\Elementor\\Migration' ) ) {
			$post = \KbrooStudio\Elementor\Migration::converted_template( (int) $settings['elementor_template_id'] );
		}
		if ( ! $post || ! Templates::is_template( $post ) || 'trash' === $post->post_status ) {
			return null;
		}
		return $post;
	}

	/** @var int[] Loop item templates being drawn, so one cannot hold itself. */
	private static $drawing = array();

	/**
	 * Each post drawn with the loop item template, in the grid.
	 */
	private function render_loop( array $settings, WP_Query $query, int $page, bool $main ): string {
		$template = self::loop_template( $settings );
		if ( ! $template || in_array( $template->ID, self::$drawing, true ) ) {
			wp_reset_postdata();
			return current_user_can( 'edit_posts' ) && '' === $this->element_id
				? '<div class="kbroo-posts-nothing">' . esc_html__( 'Choose a loop item template for this grid.', 'kbroo-studio' ) . '</div>'
				: '';
		}
		self::$drawing[] = $template->ID;
		$layout   = Document::get_layout( $template );
		$renderer = Plugin::instance()->renderer();
		Plugin::instance()->frontend_enqueue( $layout );
		$html     = '';
		$first    = true;
		while ( $query->have_posts() ) {
			$query->the_post();
			// The template's CSS is the same for every post: print it once.
			$item  = $renderer->render( $layout, $template->ID, $first );
			$html .= '<article class="' . esc_attr( implode( ' ', get_post_class( 'kbroo-loop-item' ) ) ) . '">' . $item . '</article>';
			$first = false;
		}
		wp_reset_postdata();
		array_pop( self::$drawing );
		$html = '<div class="kbroo-posts kbroo-posts--loop" data-kbroo-loop-template="' . (int) $template->ID . '">' . $html . '</div>';
		return $html . $this->pagination( $settings, $query, $page, $main );
	}

	private function render_post( array $settings, string $skin, string $thumb ): string {
		$link   = get_permalink();
		$target = 'yes' === $settings['open_new_tab'] ? ' target="_blank" rel="noopener"' : '';
		$out    = '';

		if ( 'none' !== $thumb && has_post_thumbnail() ) {
			$size  = array_key_exists( $settings['thumbnail_size_size'], $this->get_controls()['thumbnail_size_size']['options'] ) ? $settings['thumbnail_size_size'] : 'medium';
			$badge = '';
			if ( 'cards' === $skin && 'yes' === $settings['show_badge'] ) {
				$terms = get_the_terms( get_the_ID(), 'category' );
				if ( $terms && ! is_wp_error( $terms ) ) {
					$badge = '<span class="kbroo-post__badge">' . esc_html( $terms[0]->name ) . '</span>';
				}
			}
			$out .= '<a class="kbroo-post__thumbnail" href="' . esc_url( $link ) . '"' . $target . ' tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( null, $size, array( 'alt' => '' ) ) . $badge . '</a>';
		}

		$text = '';
		if ( 'yes' === $settings['show_title'] ) {
			$tag   = in_array( $settings['title_tag'], Heading::TAGS, true ) ? $settings['title_tag'] : 'h3';
			$text .= sprintf( '<%1$s class="kbroo-post__title"><a href="%2$s"%3$s>%4$s</a></%1$s>', $tag, esc_url( $link ), $target, esc_html( get_the_title() ) );
		}
		$meta = $this->meta( $settings );
		if ( 'classic' === $skin ) {
			$text .= $meta;
		}
		if ( 'yes' === $settings['show_excerpt'] ) {
			$excerpt = wp_trim_words( get_the_excerpt(), max( 1, (int) $settings['excerpt_length'] ), '…' );
			if ( '' !== $excerpt ) {
				$text .= '<div class="kbroo-post__excerpt"><p>' . esc_html( $excerpt ) . '</p></div>';
			}
		}
		if ( 'yes' === $settings['show_read_more'] && '' !== $settings['read_more_text'] ) {
			$text .= '<a class="kbroo-post__read-more" href="' . esc_url( $link ) . '"' . $target . '>' . esc_html( $settings['read_more_text'] ) . '<span class="kbroo-sr-only"> ' . esc_html( get_the_title() ) . '</span></a>';
		}
		$out .= '<div class="kbroo-post__text">' . $text . '</div>';
		if ( 'cards' === $skin && '' !== $meta ) {
			$out .= '<div class="kbroo-post__footer">' . $meta . '</div>';
		}
		return '<article class="' . esc_attr( implode( ' ', get_post_class( 'kbroo-post' ) ) ) . '">' . $out . '</article>';
	}

	private function meta( array $settings ): string {
		$parts = array();
		foreach ( (array) $settings['meta_data'] as $item ) {
			switch ( $item ) {
				case 'author':
					$parts[] = '<span class="kbroo-post__author">' . esc_html( get_the_author() ) . '</span>';
					break;
				case 'date':
					$parts[] = '<time class="kbroo-post__date" datetime="' . esc_attr( get_the_date( 'c' ) ) . '">' . esc_html( get_the_date() ) . '</time>';
					break;
				case 'time':
					$parts[] = '<span class="kbroo-post__time">' . esc_html( get_the_time() ) . '</span>';
					break;
				case 'comments':
					$count   = (int) get_comments_number();
					$parts[] = '<span class="kbroo-post__comments">' . esc_html(
						/* translators: %s: number of comments. */
						sprintf( _n( '%s comment', '%s comments', $count, 'kbroo-studio' ), number_format_i18n( $count ) )
					) . '</span>';
					break;
			}
		}
		if ( ! $parts ) {
			return '';
		}
		$separator = '<span class="kbroo-post__separator" aria-hidden="true">' . esc_html( $settings['meta_separator'] ) . '</span>';
		return '<div class="kbroo-post__meta">' . implode( $separator, $parts ) . '</div>';
	}

	/**
	 * @param bool $main Whether these are the archive's own posts, which page
	 *                   with WordPress's own links (/page/2/).
	 */
	private function pagination( array $settings, WP_Query $query, int $page, bool $main = false ): string {
		$type = $settings['pagination_type'];
		if ( '' === $type ) {
			return '';
		}
		$offset = $main ? 0 : max( 0, (int) $settings['posts_offset'] );
		$total  = $main ? (int) $query->max_num_pages : (int) ceil( max( 0, $query->found_posts - $offset ) / max( 1, (int) $query->get( 'posts_per_page' ) ) );
		$total  = min( $total, max( 1, (int) $settings['pagination_page_limit'] ) );
		if ( $total < 2 ) {
			return '';
		}
		$url   = function ( int $n ) use ( $main ) {
			if ( $main ) {
				return esc_url( get_pagenum_link( $n ) );
			}
			return esc_url( 1 === $n ? remove_query_arg( self::PAGE_VAR ) : add_query_arg( self::PAGE_VAR, $n ) );
		};
		$links = array();
		$prev  = in_array( $type, array( 'prev_next', 'numbers_and_prev_next' ), true );
		if ( $prev ) {
			$links[] = $page > 1
				? '<a class="prev" href="' . $url( $page - 1 ) . '">' . esc_html( $settings['pagination_prev_label'] ) . '</a>'
				: '<span class="prev disabled">' . esc_html( $settings['pagination_prev_label'] ) . '</span>';
		}
		if ( 'prev_next' !== $type ) {
			for ( $n = 1; $n <= $total; $n++ ) {
				$links[] = $n === $page
					? '<span class="page-numbers current" aria-current="page">' . $n . '</span>'
					: '<a class="page-numbers" href="' . $url( $n ) . '">' . $n . '</a>';
			}
		}
		if ( $prev ) {
			$links[] = $page < $total
				? '<a class="next" href="' . $url( $page + 1 ) . '">' . esc_html( $settings['pagination_next_label'] ) . '</a>'
				: '<span class="next disabled">' . esc_html( $settings['pagination_next_label'] ) . '</span>';
		}
		return '<nav class="kbroo-pagination" aria-label="' . esc_attr__( 'Posts pages', 'kbroo-studio' ) . '">' . implode( '', $links ) . '</nav>';
	}
}
