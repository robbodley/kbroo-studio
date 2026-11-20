<?php
/**
 * Moving pages from Elementor to Kbroo Studio, and back.
 *
 * - Kbroo Studio > Move from Elementor lists Elementor pages with their state and
 *   conversion report, imports the kit's colours and fonts, converts the
 *   site's Elementor saved templates into the template library, and imports
 *   exported Elementor template files there too.
 * - The Pages and Posts lists get "Convert to Kbroo Studio" (row and bulk
 *   action) and "Switch back to Elementor".
 *
 * Elementor's data (`_elementor_data` and the rest) is never changed or
 * deleted. A converted page shows the Kbroo Studio version while
 * `_kbroo_edit_mode` is set; switching back removes only that flag (and
 * restores the original post_content), so Elementor takes over again and
 * the Kbroo Studio version is kept for switching forward.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Elementor;

use KbrooStudio\Admin;
use KbrooStudio\Document;
use KbrooStudio\PageSettings;
use KbrooStudio\Renderer;
use KbrooStudio\Templates;
use KbrooStudio\ThemeBuilder;
use KbrooStudio\Widgets\Registry;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Migration {

	const PAGE          = 'kbroo-elementor';
	const META_REPORT   = '_kbroo_conversion_report';
	const META_BACKUP   = '_kbroo_elementor_content';
	const ACTION        = 'kbroo_elementor';

	/** @var Registry */
	private $widgets;

	/** @var Renderer */
	private $renderer;

	public function __construct( Registry $widgets, Renderer $renderer ) {
		$this->widgets  = $widgets;
		$this->renderer = $renderer;
	}

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 11, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 11, 2 );
		foreach ( Document::post_types() as $type ) {
			add_filter( 'bulk_actions-edit-' . $type, array( $this, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-edit-' . $type, array( $this, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/* ------------------------------------------------------------------ *
	 * State
	 * ------------------------------------------------------------------ */

	public static function is_elementor( $post ): bool {
		$post = get_post( $post );
		return $post && 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) && null !== Converter::post_data( $post->ID );
	}

	public static function report( int $post_id ): ?array {
		$report = get_post_meta( $post_id, self::META_REPORT, true );
		return is_array( $report ) ? $report : null;
	}

	/**
	 * Keep the post_content Elementor wrote, once, so switching back can
	 * restore it. Also called when an Elementor page is first saved from
	 * the Kbroo Studio editor.
	 */
	public static function backup_content( WP_Post $post ): void {
		if ( self::is_elementor( $post ) && '' === (string) get_post_meta( $post->ID, self::META_BACKUP, true ) ) {
			update_post_meta( $post->ID, self::META_BACKUP, wp_slash( $post->post_content ) );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Actions
	 * ------------------------------------------------------------------ */

	/**
	 * Convert a post's Elementor data and switch the post to Kbroo Studio.
	 * Replaces any earlier Kbroo Studio version of it.
	 */
	public function convert_post( int $post_id ): ?array {
		$post = get_post( $post_id );
		$data = $post ? Converter::post_data( $post_id ) : null;
		if ( null === $data ) {
			return null;
		}
		self::backup_content( $post );

		$result = ( new Converter( $this->widgets ) )->convert( $data, $post_id );
		Document::save_layout( $post_id, $result['layout'] );
		PageSettings::save_meta( $post_id, PageSettings::from_elementor( $post_id ) );
		update_post_meta( $post_id, self::META_REPORT, $result['report'] );
		$this->write_content( $post_id, $result['layout'] );
		return $result['report'];
	}

	public function switch_to_elementor( int $post_id ): void {
		delete_post_meta( $post_id, Document::META_MODE );
		$backup = get_post_meta( $post_id, self::META_BACKUP, true );
		if ( is_string( $backup ) && '' !== $backup ) {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $backup,
					)
				)
			);
		}
	}

	public function switch_to_kbroo( int $post_id ): void {
		update_post_meta( $post_id, Document::META_MODE, 'builder' );
		$this->write_content( $post_id, Document::get_layout( $post_id ) );
	}

	/**
	 * Add an exported Elementor template file to the template library.
	 *
	 * @return array{post_id: int, report: array}|\WP_Error
	 */
	public function import_template( string $json ) {
		$file = json_decode( $json, true );
		if ( ! is_array( $file ) || ! isset( $file['content'] ) || ! is_array( $file['content'] ) ) {
			return new \WP_Error( 'kbroo_bad_template', __( 'That file is not an Elementor template export.', 'kbroo-studio' ) );
		}
		$title  = sanitize_text_field( (string) ( $file['title'] ?? '' ) );
		$type   = sanitize_key( (string) ( $file['type'] ?? '' ) );
		$result = ( new Converter( $this->widgets ) )->convert( $file['content'], 0 );
		$post_id = Templates::create( '' !== $title ? $title : __( 'Imported Elementor template', 'kbroo-studio' ), self::template_type( $type ), $result['layout'] );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		$result['report']['template'] = array(
			'title' => $title,
			'type'  => $type,
		);
		update_post_meta( $post_id, self::META_REPORT, $result['report'] );
		$this->write_content( $post_id, $result['layout'] );
		return array(
			'post_id' => $post_id,
			'report'  => $result['report'],
		);
	}

	/** Elementor template types the library takes. */
	const LIBRARY_TYPES = array( 'page', 'section', 'container', 'header', 'footer', 'archive', 'search-results', 'single', 'single-post', 'single-page', 'error-404' );

	/**
	 * The library type for an Elementor template type. Elementor Pro's older
	 * "single" type covers posts, pages and the 404 page; its conditions say
	 * which.
	 *
	 * @param mixed $conditions The _elementor_conditions meta, if known.
	 */
	private static function template_type( string $elementor_type, $conditions = null ): string {
		if ( 'single' === $elementor_type ) {
			$conditions = array_map( 'strval', is_array( $conditions ) ? $conditions : array() );
			$includes   = array_values(
				array_filter(
					$conditions,
					function ( $c ) {
						return 0 === strpos( $c, 'include/' );
					}
				)
			);
			if ( $includes && ! array_diff( $includes, array( 'include/singular/not_found404' ) ) ) {
				return 'error-404';
			}
			$pages = array_filter(
				$includes,
				function ( $c ) {
					return 0 === strpos( $c, 'include/singular/page' ) || 0 === strpos( $c, 'include/singular/front_page' );
				}
			);
			return $includes && count( $pages ) === count( $includes ) ? 'single-page' : 'single-post';
		}
		if ( in_array( $elementor_type, ThemeBuilder::TYPES, true ) ) {
			return $elementor_type;
		}
		return in_array( $elementor_type, array( 'section', 'container' ), true ) ? 'section' : 'page';
	}

	/**
	 * Display settings from an Elementor Pro theme template's conditions
	 * ("include/general", "include/singular/page/12", ...). Converted ones
	 * start switched off, so nothing changes on the site until they are
	 * checked and switched on.
	 *
	 * @param mixed  $conditions The _elementor_conditions meta.
	 * @param string $type       Kbroo Studio template type they are for.
	 * @return array{display: array, dropped: string[]}
	 */
	public static function display_from_conditions( $conditions, string $type = 'header' ): array {
		$display = array(
			'active'  => false,
			'include' => array(),
			'exclude' => array(),
		);
		$single  = 'single' === ThemeBuilder::area_of( $type );
		$dropped = array();
		foreach ( is_array( $conditions ) ? $conditions : array() as $condition ) {
			$parts = explode( '/', (string) $condition );
			$list  = 'exclude' === ( $parts[0] ?? '' ) ? 'exclude' : 'include';
			$rest  = implode( '/', array_slice( $parts, 1 ) );
			$map   = array(
				'general'                => array( 'entire_site' ),
				'singular'               => $single ? array( 'singular_all' ) : array( 'pages', 'posts' ),
				'singular/front_page'    => array( 'front_page' ),
				'singular/page'          => array( 'pages' ),
				'singular/post'          => array( 'posts' ),
				'singular/not_found404'  => array( 'not_found' ),
				'archive/posts_page'     => array( 'blog' ),
				'archive/category'       => array( 'category' ),
				'archive/post_tag'       => array( 'tag' ),
				'archive/search'         => array( 'search' ),
			);
			if ( isset( $map[ $rest ] ) ) {
				$targets = $map[ $rest ];
			} elseif ( preg_match( '#^singular/(?:page|post|by_id)/(\d+)$#', $rest, $m ) ) {
				$targets = array( 'singular:' . $m[1] );
			} elseif ( $single && preg_match( '#^singular/in_(category|post_tag)/(\d+)$#', $rest, $m ) ) {
				$targets = array( ( 'category' === $m[1] ? 'in_category:' : 'in_tag:' ) . $m[2] );
			} elseif ( $single && preg_match( '#^singular/(?:post|page)?_?by_author/(\d+)$#', $rest, $m ) ) {
				$targets = array( 'author:' . $m[1] );
			} elseif ( $single && preg_match( '#^singular/([a-z0-9_-]{1,20})$#', $rest, $m ) && ! in_array( $m[1], array( 'attachment' ), true ) ) {
				$targets = array( 'post_type:' . $m[1] ); // A custom post type, such as a product.
			} elseif ( 0 === strpos( $rest, 'archive' ) ) {
				$targets = array( 'archives' );
			} else {
				$dropped[] = (string) $condition;
				continue;
			}
			$display[ $list ] = array_merge( $display[ $list ], $targets );
		}
		// Search results and 404 templates show where their type says, even
		// when Elementor stored no condition.
		if ( ! $display['include'] && in_array( $type, array( 'search-results', 'error-404' ), true ) ) {
			$display['include'] = array( ThemeBuilder::DEFAULT_TARGET[ $type ] );
		}
		return array(
			'display' => ThemeBuilder::sanitize_display( $display ),
			'dropped' => $dropped,
		);
	}

	/**
	 * Elementor saved templates (Templates > Saved Templates) that can go
	 * into the library.
	 *
	 * @return WP_Post[]
	 */
	private function library_posts(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => '_elementor_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => self::LIBRARY_TYPES, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => 'IN',
			)
		);
		return array_values(
			array_filter(
				$posts,
				function ( $post ) {
					return null !== Converter::post_data( $post->ID );
				}
			)
		);
	}

	/** The library template converted from an Elementor template, if any. */
	public static function converted_template( int $elementor_id ): ?WP_Post {
		$found = get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				'meta_key'       => Templates::META_SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => (string) $elementor_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		return $found ? $found[0] : null;
	}

	/**
	 * Convert an Elementor saved template into the library, replacing an
	 * earlier conversion of it. Elementor's template is left as it is.
	 *
	 * @return int|null Library template ID, or null if there was nothing to convert.
	 */
	public function convert_library( int $elementor_id ): ?int {
		$source = get_post( $elementor_id );
		$data   = $source && 'elementor_library' === $source->post_type ? Converter::post_data( $elementor_id ) : null;
		$type   = (string) get_post_meta( $elementor_id, '_elementor_template_type', true );
		if ( null === $data || ! in_array( $type, self::LIBRARY_TYPES, true ) ) {
			return null;
		}
		$result     = ( new Converter( $this->widgets ) )->convert( $data, $elementor_id );
		$existing   = self::converted_template( $elementor_id );
		$conditions = get_post_meta( $elementor_id, '_elementor_conditions', true );
		$kbroo_type = self::template_type( $type, $conditions );
		if ( $existing ) {
			$id = $existing->ID;
			Document::save_layout( $id, $result['layout'] );
			update_post_meta( $id, Templates::META_TYPE, $kbroo_type );
		} else {
			$id = Templates::create( get_the_title( $source ), $kbroo_type, $result['layout'] );
			if ( is_wp_error( $id ) ) {
				return null;
			}
			update_post_meta( $id, Templates::META_SOURCE, (string) $elementor_id );
		}
		if ( in_array( $kbroo_type, ThemeBuilder::TYPES, true ) ) {
			$converted = self::display_from_conditions( $conditions, $kbroo_type );
			ThemeBuilder::save_display( $id, $converted['display'] );
			foreach ( $converted['dropped'] as $condition ) {
				/* translators: %s: Elementor display condition. */
				$result['report']['dropped'][ sprintf( __( 'display condition %s', 'kbroo-studio' ), $condition ) ] = 1;
			}
		}
		update_post_meta( $id, self::META_REPORT, $result['report'] );
		$this->write_content( $id, $result['layout'] );
		return $id;
	}

	/** A plain HTML copy in post_content, as the editor's save does. */
	private function write_content( int $post_id, array $layout ): void {
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $this->renderer->render( $layout, $post_id ),
				)
			)
		);
	}

	/* ------------------------------------------------------------------ *
	 * Requests
	 * ------------------------------------------------------------------ */

	public static function action_url( string $do, int $post_id = 0, string $back = '' ): string {
		$args = array(
			'action' => self::ACTION,
			'do'     => $do,
			'post'   => $post_id,
		);
		if ( '' !== $back ) {
			$args['back'] = $back;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), self::ACTION . '_' . $do . '_' . $post_id );
	}

	public function handle(): void {
		$do      = sanitize_key( wp_unslash( $_REQUEST['do'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- checked below.
		$post_id = absint( $_REQUEST['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		check_admin_referer( self::ACTION . '_' . $do . '_' . $post_id );

		$back   = 'list' === ( $_REQUEST['back'] ?? '' ) ? wp_get_referer() : admin_url( 'admin.php?page=' . self::PAGE ); // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '';

		if ( 'kit' === $do ) {
			if ( ! current_user_can( \KbrooStudio\Globals::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to change site-wide styles.', 'kbroo-studio' ), 403 );
			}
			$notice = null === Kit::import() ? 'no-kit' : 'kit';
		} elseif ( 'library' === $do || 'library-all' === $do ) {
			if ( ! current_user_can( 'publish_pages' ) ) {
				wp_die( esc_html__( 'You are not allowed to save templates.', 'kbroo-studio' ), 403 );
			}
			$ids  = 'library' === $do ? array( $post_id ) : wp_list_pluck( $this->library_posts(), 'ID' );
			$done = 0;
			foreach ( $ids as $id ) {
				$converted = $this->convert_library( (int) $id );
				if ( null !== $converted ) {
					$done++;
					$post_id = $converted;
				}
			}
			if ( 'library-all' === $do ) {
				wp_safe_redirect( add_query_arg( array( 'kbroo_notice' => 'library-all', 'kbroo_count' => $done ), $back ) );
				exit;
			}
			$notice = $done ? 'library' : 'no-data';
		} elseif ( 'template' === $do ) {
			if ( ! current_user_can( 'publish_pages' ) && ! current_user_can( 'edit_pages' ) ) {
				wp_die( esc_html__( 'You are not allowed to create pages.', 'kbroo-studio' ), 403 );
			}
			$file = $_FILES['template'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as a file below.
			if ( ! is_array( $file ) || UPLOAD_ERR_OK !== ( $file['error'] ?? 1 ) || ! is_uploaded_file( $file['tmp_name'] ) || $file['size'] > 10 * MB_IN_BYTES ) {
				$notice = 'bad-file';
			} else {
				$result = $this->import_template( (string) file_get_contents( $file['tmp_name'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( is_wp_error( $result ) ) {
					$notice = 'bad-file';
				} else {
					$notice  = 'library';
					$post_id = $result['post_id'];
				}
			}
		} else {
			if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! Document::supports( $post_id ) ) {
				wp_die( esc_html__( 'You are not allowed to edit this item.', 'kbroo-studio' ), 403 );
			}
			switch ( $do ) {
				case 'convert':
					$notice = null === $this->convert_post( $post_id ) ? 'no-data' : 'converted';
					break;
				case 'elementor':
					$this->switch_to_elementor( $post_id );
					$notice = 'to-elementor';
					break;
				case 'kbroo':
					$this->switch_to_kbroo( $post_id );
					$notice = 'to-kbroo';
					break;
			}
		}

		wp_safe_redirect( add_query_arg( array( 'kbroo_notice' => $notice, 'kbroo_post' => $post_id ), $back ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Pages and Posts lists
	 * ------------------------------------------------------------------ */

	/**
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 */
	public function row_actions( $actions, $post ): array {
		if ( ! Document::supports( $post ) || ! self::is_elementor( $post ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		if ( Document::is_built_with_kbroo( $post ) ) {
			$actions['kbroo_to_elementor'] = sprintf( '<a href="%s">%s</a>', esc_url( self::action_url( 'elementor', $post->ID, 'list' ) ), esc_html__( 'Switch back to Elementor', 'kbroo-studio' ) );
		} else {
			$actions['kbroo_convert'] = sprintf( '<a href="%s">%s</a>', esc_url( self::action_url( 'convert', $post->ID, 'list' ) ), esc_html__( 'Convert to Kbroo Studio', 'kbroo-studio' ) );
			unset( $actions['kbroo_edit'] ); // The empty editor would only confuse.
		}
		return $actions;
	}

	public function bulk_actions( $actions ): array {
		$actions['kbroo_convert'] = __( 'Convert to Kbroo Studio', 'kbroo-studio' );
		return $actions;
	}

	/**
	 * @param string $redirect Redirect URL.
	 * @param string $action   Bulk action.
	 * @param int[]  $ids      Post ids.
	 */
	public function handle_bulk( $redirect, $action, $ids ): string {
		if ( 'kbroo_convert' !== $action ) {
			return $redirect;
		}
		$done = 0;
		foreach ( (array) $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) && self::is_elementor( $id ) && null !== $this->convert_post( (int) $id ) ) {
				$done++;
			}
		}
		return add_query_arg( array( 'kbroo_notice' => 'bulk', 'kbroo_count' => $done ), $redirect );
	}

	public function notices(): void {
		$notice = sanitize_key( wp_unslash( $_GET['kbroo_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification -- display only.
		if ( '' === $notice ) {
			return;
		}
		$post_id = absint( $_GET['kbroo_post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$title   = $post_id ? get_the_title( $post_id ) : '';
		$report  = $post_id ? self::report( $post_id ) : null;
		$missing = $report ? count( $report['fallbacks'] ) : 0;
		$type    = 'success';

		switch ( $notice ) {
			case 'converted':
			case 'imported':
				$text = sprintf(
					/* translators: 1: page title, 2: number of parts. */
					_n( '"%1$s" is now built with Kbroo Studio. %2$d part could not be converted yet and still shows as it was; it is listed below for rebuilding.', '"%1$s" is now built with Kbroo Studio. %2$d parts could not be converted yet and still show as they were; they are listed below for rebuilding.', $missing, 'kbroo-studio' ),
					$title,
					$missing
				);
				if ( ! $missing ) {
					/* translators: %s: page title. */
					$text = sprintf( __( '"%s" is now built with Kbroo Studio. Everything converted.', 'kbroo-studio' ), $title );
				}
				break;
			case 'library':
				$text = sprintf(
					/* translators: 1: template title, 2: number of parts. */
					_n( '"%1$s" is now in Kbroo Studio > Templates. %2$d part could not be converted yet; it is listed below.', '"%1$s" is now in Kbroo Studio > Templates. %2$d parts could not be converted yet; they are listed below.', $missing, 'kbroo-studio' ),
					$title,
					$missing
				);
				if ( ! $missing ) {
					/* translators: %s: template title. */
					$text = sprintf( __( '"%s" is now in Kbroo Studio > Templates. Everything converted.', 'kbroo-studio' ), $title );
				}
				break;
			case 'library-all':
				$count = absint( $_GET['kbroo_count'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
				/* translators: %d: number of templates. */
				$text = sprintf( _n( '%d Elementor template converted into Kbroo Studio > Templates.', '%d Elementor templates converted into Kbroo Studio > Templates.', $count, 'kbroo-studio' ), $count );
				break;
			case 'to-elementor':
				/* translators: %s: page title. */
				$text = sprintf( __( '"%s" is back on Elementor. Its Kbroo Studio version is kept if you want to switch again.', 'kbroo-studio' ), $title );
				break;
			case 'to-kbroo':
				/* translators: %s: page title. */
				$text = sprintf( __( '"%s" now shows its Kbroo Studio version.', 'kbroo-studio' ), $title );
				break;
			case 'kit':
				$text = __( 'Colours and fonts imported from Elementor\'s Site Settings.', 'kbroo-studio' );
				break;
			case 'bulk':
				$count = absint( $_GET['kbroo_count'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
				/* translators: %d: number of pages. */
				$text = sprintf( _n( '%d page converted to Kbroo Studio. See Kbroo Studio > Move from Elementor for the report.', '%d pages converted to Kbroo Studio. See Kbroo Studio > Move from Elementor for the reports.', $count, 'kbroo-studio' ), $count );
				break;
			case 'no-kit':
				$text = __( 'No Elementor Site Settings were found on this site.', 'kbroo-studio' );
				$type = 'warning';
				break;
			case 'no-data':
				$text = __( 'That page has no Elementor data to convert.', 'kbroo-studio' );
				$type = 'warning';
				break;
			case 'bad-file':
				$text = __( 'That file could not be read as an Elementor template export (.json).', 'kbroo-studio' );
				$type = 'error';
				break;
			default:
				return;
		}
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $text ) );
	}

	/* ------------------------------------------------------------------ *
	 * Kbroo Studio > Move from Elementor
	 * ------------------------------------------------------------------ */

	public function menu(): void {
		add_submenu_page(
			Admin::MENU,
			__( 'Move from Elementor', 'kbroo-studio' ),
			__( 'Move from Elementor', 'kbroo-studio' ),
			'edit_pages',
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @return WP_Post[]
	 */
	private function elementor_posts(): array {
		return get_posts(
			array(
				'post_type'      => Document::post_types(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 500,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_key'       => '_elementor_edit_mode', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
	}

	public function render_page(): void {
		$posts    = array_filter( $this->elementor_posts(), array( self::class, 'is_elementor' ) );
		$library  = $this->library_posts();
		$imported = get_posts(
			array(
				'post_type'      => array( 'page', Templates::POST_TYPE ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'meta_key'       => self::META_REPORT, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$imported = array_filter(
			$imported,
			function ( $post ) {
				$report = self::report( $post->ID );
				return $report && isset( $report['template'] ) && ! get_post_meta( $post->ID, Templates::META_SOURCE, true );
			}
		);
		?>
		<div class="wrap kbroo-migration">
			<h1><?php esc_html_e( 'Move from Elementor', 'kbroo-studio' ); ?></h1>
			<p><?php esc_html_e( 'Convert Elementor pages to Kbroo Studio one at a time and check each one. Elementor\'s own data is never changed or deleted, so you can switch any page back at any time.', 'kbroo-studio' ); ?></p>

			<h2><?php esc_html_e( '1. Colours and fonts', 'kbroo-studio' ); ?></h2>
			<?php if ( Kit::exists() ) : ?>
				<p><?php esc_html_e( 'Import the global colours and fonts from Elementor\'s Site Settings first, so converted pages keep their colours and fonts. Items with the same name are replaced.', 'kbroo-studio' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( self::action_url( 'kit' ) ); ?>"><?php esc_html_e( 'Import colours and fonts', 'kbroo-studio' ); ?></a></p>
			<?php else : ?>
				<p><?php esc_html_e( 'No Elementor Site Settings were found on this site.', 'kbroo-studio' ); ?></p>
			<?php endif; ?>

			<h2><?php esc_html_e( '2. Pages', 'kbroo-studio' ); ?></h2>
			<?php if ( ! $posts ) : ?>
				<p><?php esc_html_e( 'No pages built with Elementor were found.', 'kbroo-studio' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Showing', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Conversion report', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'kbroo-studio' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php $this->render_row( $post ); ?>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( '3. Saved templates', 'kbroo-studio' ); ?></h2>
			<?php if ( ! $library ) : ?>
				<p><?php esc_html_e( 'No Elementor saved templates (pages, sections, containers, or theme builder headers, footers, single, archive, search results and 404 templates) were found on this site.', 'kbroo-studio' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Copy Elementor\'s saved templates into Kbroo Studio > Templates, so they can be inserted into any page from the editor.', 'kbroo-studio' ); ?></p>
				<p><?php esc_html_e( 'Theme builder templates (headers, footers, single posts and pages, archives, search results, 404) keep their display conditions but start switched off, so the site does not change yet. Check each one, then switch it on under "Where it shows" in its editor (and turn off Elementor\'s).', 'kbroo-studio' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( self::action_url( 'library-all' ) ); ?>"><?php esc_html_e( 'Convert all templates', 'kbroo-studio' ); ?></a></p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Elementor template', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Conversion report', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'kbroo-studio' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $library as $source ) : ?>
						<?php $converted = self::converted_template( $source->ID ); ?>
						<tr data-elementor-template="<?php echo (int) $source->ID; ?>">
							<td>
								<strong><?php echo esc_html( get_the_title( $source ) ); ?></strong>
								<div><?php echo esc_html( ucfirst( (string) get_post_meta( $source->ID, '_elementor_template_type', true ) ) ); ?></div>
								<?php if ( $converted && in_array( Templates::type_of( $converted ), ThemeBuilder::TYPES, true ) ) : ?>
									<div class="description"><?php echo esc_html( ThemeBuilder::summary( $converted ) ); ?></div>
								<?php endif; ?>
							</td>
							<td><?php $this->render_report( $converted ? self::report( $converted->ID ) : null ); ?></td>
							<td class="row-actions-static">
								<?php if ( $converted ) : ?>
									<a class="button button-small" href="<?php echo esc_url( Document::editor_url( $converted ) ); ?>"><?php esc_html_e( 'Edit with Kbroo Studio', 'kbroo-studio' ); ?></a>
									<a class="button button-small" href="<?php echo esc_url( self::action_url( 'library', $source->ID ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Convert again? This replaces the Kbroo Studio copy of this template, including any changes made to it.', 'kbroo-studio' ) ); ?>');"><?php esc_html_e( 'Convert again', 'kbroo-studio' ); ?></a>
								<?php else : ?>
									<a class="button button-primary button-small" href="<?php echo esc_url( self::action_url( 'library', $source->ID ) ); ?>"><?php esc_html_e( 'Convert', 'kbroo-studio' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h2><?php esc_html_e( '4. Template files', 'kbroo-studio' ); ?></h2>
			<p><?php esc_html_e( 'Import a template exported from Elementor on another site (Templates > Saved Templates > Export). It is added to Kbroo Studio > Templates.', 'kbroo-studio' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="do" value="template" />
				<input type="hidden" name="post" value="0" />
				<?php wp_nonce_field( self::ACTION . '_template_0' ); ?>
				<input type="file" name="template" accept=".json,application/json" required />
				<button type="submit" class="button"><?php esc_html_e( 'Import template', 'kbroo-studio' ); ?></button>
			</form>
			<?php if ( $imported ) : ?>
				<table class="widefat striped" style="margin-top:12px">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Imported', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Conversion report', 'kbroo-studio' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'kbroo-studio' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $imported as $post ) : ?>
						<tr>
							<td><strong><?php echo esc_html( get_the_title( $post ) ); ?></strong></td>
							<td><?php $this->render_report( self::report( $post->ID ) ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( Document::editor_url( $post ) ); ?>"><?php esc_html_e( 'Edit with Kbroo Studio', 'kbroo-studio' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<style>
			.kbroo-migration td { vertical-align: top; }
			.kbroo-migration .kbroo-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; background: #f0f0f1; font-size: 12px; }
			.kbroo-migration .kbroo-badge.is-kbroo { background: #ede9fe; color: #5b21b6; }
			.kbroo-migration details summary { cursor: pointer; }
			.kbroo-migration ul { margin: 4px 0 0 18px; list-style: disc; }
			.kbroo-migration .row-actions-static a { margin-right: 6px; }
		</style>
		<?php
	}

	private function render_row( WP_Post $post ): void {
		$kbroo    = Document::is_built_with_kbroo( $post );
		$report   = self::report( $post->ID );
		$has_copy = '' !== (string) get_post_meta( $post->ID, Document::META_DATA, true );
		$confirm  = esc_js( __( 'Convert again? This replaces the Kbroo Studio version of this page, including any changes made to it in Kbroo Studio.', 'kbroo-studio' ) );
		?>
		<tr>
			<td>
				<strong><?php echo esc_html( get_the_title( $post ) ); ?></strong>
				<div><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name . ', ' . get_post_status( $post ) ); ?></div>
			</td>
			<td>
				<?php if ( $kbroo ) : ?>
					<span class="kbroo-badge is-kbroo"><?php esc_html_e( 'Kbroo Studio', 'kbroo-studio' ); ?></span>
				<?php else : ?>
					<span class="kbroo-badge"><?php esc_html_e( 'Elementor', 'kbroo-studio' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php $this->render_report( $report ); ?></td>
			<td class="row-actions-static">
				<?php if ( ! $has_copy ) : ?>
					<a class="button button-primary button-small" href="<?php echo esc_url( self::action_url( 'convert', $post->ID ) ); ?>"><?php esc_html_e( 'Convert', 'kbroo-studio' ); ?></a>
				<?php else : ?>
					<?php if ( $kbroo ) : ?>
						<a class="button button-small" href="<?php echo esc_url( Document::editor_url( $post ) ); ?>"><?php esc_html_e( 'Edit with Kbroo Studio', 'kbroo-studio' ); ?></a>
						<a class="button button-small" href="<?php echo esc_url( self::action_url( 'elementor', $post->ID ) ); ?>"><?php esc_html_e( 'Switch back to Elementor', 'kbroo-studio' ); ?></a>
					<?php else : ?>
						<a class="button button-primary button-small" href="<?php echo esc_url( self::action_url( 'kbroo', $post->ID ) ); ?>"><?php esc_html_e( 'Switch to Kbroo Studio', 'kbroo-studio' ); ?></a>
					<?php endif; ?>
					<a class="button button-small" href="<?php echo esc_url( self::action_url( 'convert', $post->ID ) ); ?>" onclick="return confirm('<?php echo $confirm; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_js above. ?>');"><?php esc_html_e( 'Convert again', 'kbroo-studio' ); ?></a>
				<?php endif; ?>
				<a class="button button-small" href="<?php echo esc_url( get_permalink( $post ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'kbroo-studio' ); ?></a>
			</td>
		</tr>
		<?php
	}

	private function render_report( ?array $report ): void {
		if ( ! $report ) {
			echo '&mdash;';
			return;
		}
		$fallbacks = $report['fallbacks'] ?? array();
		$dropped   = $report['dropped'] ?? array();
		printf(
			'<div>%s</div>',
			esc_html(
				sprintf(
					/* translators: 1: number of elements, 2: date. */
					__( '%1$d elements converted on %2$s.', 'kbroo-studio' ),
					(int) ( $report['elements'] ?? 0 ),
					wp_date( get_option( 'date_format' ), strtotime( (string) ( $report['converted_at'] ?? 'now' ) ) )
				)
			)
		);
		if ( $fallbacks ) {
			$labels = array_count_values( wp_list_pluck( $fallbacks, 'label' ) );
			echo '<div><strong>' . esc_html(
				sprintf(
					/* translators: %d: number of parts. */
					_n( '%d part to rebuild (still shows as it was):', '%d parts to rebuild (still show as they were):', count( $fallbacks ), 'kbroo-studio' ),
					count( $fallbacks )
				)
			) . '</strong><ul>';
			foreach ( $labels as $label => $count ) {
				echo '<li>' . esc_html( $count > 1 ? $label . ' × ' . $count : $label ) . '</li>';
			}
			echo '</ul></div>';
		} else {
			echo '<div>' . esc_html__( 'Every widget converted.', 'kbroo-studio' ) . '</div>';
		}
		if ( ! empty( $report['fonts_added'] ) ) {
			/* translators: %s: font names. */
			echo '<div>' . esc_html( sprintf( __( 'Google Fonts added: %s.', 'kbroo-studio' ), implode( ', ', $report['fonts_added'] ) ) ) . '</div>';
		}
		if ( ! empty( $report['fonts_local'] ) ) {
			/* translators: %s: font names. */
			echo '<div>' . esc_html( sprintf( __( 'Fonts not found on Google Fonts (they show only if the theme loads them): %s.', 'kbroo-studio' ), implode( ', ', $report['fonts_local'] ) ) ) . '</div>';
		}
		if ( $dropped ) {
			echo '<details><summary>' . esc_html(
				sprintf(
					/* translators: %d: number of setting names. */
					_n( '%d kind of setting not carried over yet', '%d kinds of setting not carried over yet', count( $dropped ), 'kbroo-studio' ),
					count( $dropped )
				)
			) . '</summary><ul>';
			foreach ( $dropped as $name => $count ) {
				echo '<li><code>' . esc_html( (string) $name ) . '</code> × ' . (int) $count . '</li>';
			}
			echo '</ul></details>';
		}
	}
}
