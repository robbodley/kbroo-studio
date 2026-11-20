<?php
/**
 * The template library: saved sections and pages that can be inserted into
 * any page.
 *
 * A template is a `kbroo_template` post holding a layout like any page,
 * edited in the same editor. Its type (section or page) is a label used to
 * sort the library. Templates are listed under Kbroo Studio > Templates
 * and are never shown on the site by themselves.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Templates {

	const POST_TYPE = 'kbroo_template';
	const META_TYPE = '_kbroo_template_type';

	/** Elementor library post a template was converted from. */
	const META_SOURCE = '_kbroo_elementor_template';

	/** @var Registry */
	private $widgets;

	/** @var Renderer */
	private $renderer;

	public function __construct( Registry $widgets, Renderer $renderer ) {
		$this->widgets  = $widgets;
		$this->renderer = $renderer;
	}

	/**
	 * Template types and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function types(): array {
		return array(
			'section' => __( 'Section', 'kbroo-studio' ),
			'page'    => __( 'Page', 'kbroo-studio' ),
			'header'  => __( 'Header', 'kbroo-studio' ),
			'footer'  => __( 'Footer', 'kbroo-studio' ),
			'archive' => __( 'Blog archive', 'kbroo-studio' ),
		);
	}

	public static function type_of( $post ): string {
		$type = (string) get_post_meta( is_object( $post ) ? $post->ID : (int) $post, self::META_TYPE, true );
		return array_key_exists( $type, self::types() ) ? $type : 'page';
	}

	public static function is_template( $post ): bool {
		$post = get_post( $post );
		return $post && self::POST_TYPE === $post->post_type;
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'load-post-new.php', array( $this, 'new_from_admin' ) );
		add_filter( 'get_edit_post_link', array( $this, 'edit_link' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'type_filter' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_by_type' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'new_buttons' ) );
	}

	/**
	 * "New header" and "New footer" next to "New template" in the list.
	 */
	public function new_buttons(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id || ! current_user_can( 'publish_pages' ) ) {
			return;
		}
		$links = '';
		foreach ( ThemeBuilder::TYPES as $area ) {
			$links .= sprintf(
				' <a href="%s" class="page-title-action" data-kbroo-new="%s">%s</a>',
				esc_url( admin_url( 'post-new.php?post_type=' . self::POST_TYPE . '&template_type=' . $area ) ),
				esc_attr( $area ),
				/* translators: %s: Header or Footer. */
				esc_html( sprintf( __( 'New %s', 'kbroo-studio' ), strtolower( self::types()[ $area ] ) ) )
			);
		}
		?>
		<script>
			( function () {
				var add = document.querySelector( '.wrap .page-title-action' );
				if ( add ) {
					add.insertAdjacentHTML( 'afterend', <?php echo wp_json_encode( $links ); ?> );
				}
			} )();
		</script>
		<?php
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Templates', 'kbroo-studio' ),
					'singular_name' => __( 'Template', 'kbroo-studio' ),
					'menu_name'     => __( 'Templates', 'kbroo-studio' ),
					'all_items'     => __( 'Templates', 'kbroo-studio' ),
					'add_new'       => __( 'New template', 'kbroo-studio' ),
					'add_new_item'  => __( 'New template', 'kbroo-studio' ),
					'edit_item'     => __( 'Edit template', 'kbroo-studio' ),
					'search_items'  => __( 'Search templates', 'kbroo-studio' ),
					'not_found'     => __( 'No templates yet. Right-click a section in the editor and choose Save as template.', 'kbroo-studio' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Admin::MENU,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'revisions', 'author' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Admin list
	 * ------------------------------------------------------------------ */

	/**
	 * "New template" in the admin opens a new template straight in the
	 * editor instead of WordPress's own edit screen.
	 */
	public function new_from_admin(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Same as core's post-new.php, which this replaces.
		if ( self::POST_TYPE !== ( $_GET['post_type'] ?? '' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			return; // Core shows its own error.
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = sanitize_key( wp_unslash( $_GET['template_type'] ?? 'section' ) );
		// An auto-draft, like core's own "Add New": it joins the library
		// when first saved, and WordPress clears it away if it never is.
		$id = self::create( __( 'New template', 'kbroo-studio' ), $type, Layout::empty_layout(), 'auto-draft' );
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		if ( in_array( $type, ThemeBuilder::TYPES, true ) ) {
			// A new header or footer is for the whole site unless changed, and
			// an archive template for every archive.
			ThemeBuilder::save_display( $id, array( 'include' => array( 'archive' === $type ? 'archives' : 'entire_site' ) ) );
			wp_update_post(
				array(
					'ID'         => $id,
					/* translators: %s: Header or Footer. */
					'post_title' => sprintf( __( 'New %s', 'kbroo-studio' ), strtolower( self::types()[ $type ] ) ),
				)
			);
		}
		wp_safe_redirect( Document::editor_url( $id ) );
		exit;
	}

	/**
	 * Template titles in admin lists open the editor.
	 *
	 * @param string $link    Edit link.
	 * @param int    $post_id Post ID.
	 */
	public function edit_link( $link, $post_id ) {
		return self::is_template( $post_id ) ? Document::editor_url( $post_id ) : $link;
	}

	public function columns( $columns ): array {
		$out = array();
		foreach ( (array) $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'title' === $key ) {
				$out['kbroo_type'] = __( 'Type', 'kbroo-studio' );
			}
		}
		return $out;
	}

	public function column( $column, $post_id ): void {
		if ( 'kbroo_type' === $column ) {
			$type = self::type_of( $post_id );
			echo esc_html( self::types()[ $type ] );
			if ( in_array( $type, ThemeBuilder::TYPES, true ) ) {
				echo '<br /><span class="description">' . esc_html( ThemeBuilder::summary( $post_id ) ) . '</span>';
			}
			$source = (int) get_post_meta( $post_id, self::META_SOURCE, true );
			if ( $source ) {
				echo '<br /><span class="description">' . esc_html__( 'From Elementor', 'kbroo-studio' ) . '</span>';
			}
		}
	}

	public function type_filter( $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter.
		$current = sanitize_key( wp_unslash( $_GET['kbroo_template_type'] ?? '' ) );
		echo '<select name="kbroo_template_type"><option value="">' . esc_html__( 'All types', 'kbroo-studio' ) . '</option>';
		foreach ( self::types() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public function filter_by_type( $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter.
		$type = sanitize_key( wp_unslash( $_GET['kbroo_template_type'] ?? '' ) );
		if ( array_key_exists( $type, self::types() ) ) {
			$query->set( 'meta_key', self::META_TYPE );
			$query->set( 'meta_value', $type );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Storage
	 * ------------------------------------------------------------------ */

	/**
	 * Create a template from an already-sanitized layout.
	 *
	 * @return int|WP_Error Post ID.
	 */
	public static function create( string $title, string $type, array $layout, string $status = 'publish' ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => $status,
				'post_title'  => '' !== trim( $title ) ? $title : __( 'Untitled template', 'kbroo-studio' ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META_TYPE, array_key_exists( $type, self::types() ) ? $type : 'page' );
		Document::save_layout( $id, $layout );
		return $id;
	}

	/**
	 * Templates for the editor's library, newest change first.
	 */
	public static function all(): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		return array_map( array( self::class, 'summary' ), $posts );
	}

	private static function summary( WP_Post $post ): array {
		return array(
			'id'       => $post->ID,
			'title'    => get_the_title( $post ),
			'type'     => self::type_of( $post ),
			'modified' => get_post_modified_time( 'c', true, $post ),
			'author'   => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'editUrl'  => current_user_can( 'edit_post', $post->ID ) ? Document::editor_url( $post ) : '',
		);
	}

	/* ------------------------------------------------------------------ *
	 * REST
	 * ------------------------------------------------------------------ */

	public function register_routes(): void {
		register_rest_route(
			Rest::NAMESPACE,
			'/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () {
						return rest_ensure_response( self::all() );
					},
					'permission_callback' => array( $this, 'can_use' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_create' ),
					'permission_callback' => array( $this, 'can_create' ),
					'args'                => array(
						'title'  => array(
							'required' => true,
							'type'     => 'string',
						),
						'type'   => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array_keys( self::types() ),
						),
						'layout' => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			Rest::NAMESPACE,
			'/templates/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get' ),
				'permission_callback' => array( $this, 'can_use' ),
			)
		);
	}

	/**
	 * Anyone who can build pages can use the library.
	 *
	 * @return true|WP_Error
	 */
	public function can_use() {
		if ( ! current_user_can( 'edit_pages' ) && ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'kbroo_forbidden', __( 'You are not allowed to use templates.', 'kbroo-studio' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * Saving a template needs the rights to edit and publish pages.
	 *
	 * @return true|WP_Error
	 */
	public function can_create() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			return new WP_Error( 'kbroo_forbidden', __( 'You are not allowed to save templates.', 'kbroo-studio' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rest_get( WP_REST_Request $request ) {
		$post = get_post( (int) $request['id'] );
		if ( ! self::is_template( $post ) || ! in_array( $post->post_status, array( 'publish', 'draft', 'private' ), true ) ) {
			return new WP_Error( 'kbroo_not_found', __( 'Template not found.', 'kbroo-studio' ), array( 'status' => 404 ) );
		}
		if ( 'private' === $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			return new WP_Error( 'kbroo_not_found', __( 'Template not found.', 'kbroo-studio' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response(
			self::summary( $post ) + array( 'layout' => Layout::sanitize( Document::get_layout( $post ), $this->widgets ) )
		);
	}

	/**
	 * @return \WP_REST_Response|WP_Error
	 */
	public function rest_create( WP_REST_Request $request ) {
		$layout = Layout::sanitize( $request->get_param( 'layout' ), $this->widgets );
		if ( ! $layout['elements'] ) {
			return new WP_Error( 'kbroo_empty_template', __( 'There is nothing to save in this template.', 'kbroo-studio' ), array( 'status' => 400 ) );
		}
		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		$id    = self::create( $title, (string) $request->get_param( 'type' ), $layout );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_content' => $this->renderer->render( $layout, $id ),
				)
			)
		);
		return rest_ensure_response( self::summary( get_post( $id ) ) );
	}
}
