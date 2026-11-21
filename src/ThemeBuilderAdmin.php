<?php
/**
 * Kbroo Studio > Theme builder: the parts of the site in use, like
 * Elementor Pro's Theme Builder. Headers, footers, single post and page,
 * archive, search results, 404 and loop item templates are listed by type,
 * each with a live preview, where it shows ("Instances") and whether it is
 * switched on, with links to edit it or its conditions.
 *
 * The Templates list stays the library of sections and pages to build
 * pages from.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Post;

defined( 'ABSPATH' ) || exit;

final class ThemeBuilderAdmin {

	const PAGE = 'kbroo-theme-builder';

	/** Front-end query var that shows one template alone, for the previews. */
	const PREVIEW_VAR = 'kbroo-template-preview';

	/** admin-post action that switches a template on or off. */
	const TOGGLE = 'kbroo_toggle_template';

	/** Types listed here, in the order of the side menu. */
	const TYPES = array( 'header', 'footer', 'single-post', 'single-page', 'archive', 'search-results', 'loop-item', 'error-404' );

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ), 11 );
		add_action( 'admin_post_' . self::TOGGLE, array( $this, 'toggle' ) );
		add_action( 'template_redirect', array( $this, 'preview' ), 0 );
	}

	public static function url( string $type = '' ): string {
		return admin_url( 'admin.php?page=' . self::PAGE . ( '' !== $type ? '&type=' . $type : '' ) );
	}

	/** Whether a template type is listed here rather than in the library. */
	public static function is_theme_type( string $type ): bool {
		return in_array( $type, self::TYPES, true );
	}

	public function menu(): void {
		add_submenu_page(
			Admin::MENU,
			__( 'Theme builder', 'kbroo-studio' ),
			__( 'Theme builder', 'kbroo-studio' ),
			'edit_pages',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/** Type names for the side menu and cards. */
	private static function labels(): array {
		return array(
			'header'         => __( 'Header', 'kbroo-studio' ),
			'footer'         => __( 'Footer', 'kbroo-studio' ),
			'single-post'    => __( 'Single post', 'kbroo-studio' ),
			'single-page'    => __( 'Single page', 'kbroo-studio' ),
			'archive'        => __( 'Archive', 'kbroo-studio' ),
			'search-results' => __( 'Search results', 'kbroo-studio' ),
			'loop-item'      => __( 'Loop item', 'kbroo-studio' ),
			'error-404'      => __( 'Error 404', 'kbroo-studio' ),
		);
	}

	/** Dashicons for the side menu. */
	const ICONS = array(
		'header'         => 'dashicons-table-row-before',
		'footer'         => 'dashicons-table-row-after',
		'single-post'    => 'dashicons-media-text',
		'single-page'    => 'dashicons-media-document',
		'archive'        => 'dashicons-grid-view',
		'search-results' => 'dashicons-search',
		'loop-item'      => 'dashicons-screenoptions',
		'error-404'      => 'dashicons-warning',
	);

	/**
	 * Theme templates of the given types, newest change first.
	 *
	 * @param string[] $types Template types.
	 * @return WP_Post[]
	 */
	private static function templates( array $types ): array {
		return get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_key'       => Templates::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $types, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => 'IN',
			)
		);
	}

	/** Whether a template shows on the site. */
	private static function is_live( WP_Post $post ): bool {
		if ( 'publish' !== $post->post_status ) {
			return false;
		}
		$display = ThemeBuilder::get_display( $post );
		return $display['active'] && (bool) $display['include'];
	}

	public function render(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter.
		$current = sanitize_key( wp_unslash( $_GET['type'] ?? '' ) );
		$current = self::is_theme_type( $current ) ? $current : '';
		$labels  = self::labels();
		$all     = self::templates( self::TYPES );
		$counts  = array_fill_keys( self::TYPES, 0 );
		foreach ( $all as $post ) {
			$counts[ Templates::type_of( $post ) ] = ( $counts[ Templates::type_of( $post ) ] ?? 0 ) + 1;
		}
		$shown = '' === $current ? $all : array_values(
			array_filter(
				$all,
				function ( $post ) use ( $current ) {
					return Templates::type_of( $post ) === $current;
				}
			)
		);
		if ( '' === $current ) {
			// Parts in use first, as Elementor's "Your Site's Global Parts".
			usort(
				$shown,
				function ( $a, $b ) {
					return (int) self::is_live( $b ) - (int) self::is_live( $a );
				}
			);
		}
		$can_add = current_user_can( 'publish_pages' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A notice after a redirect.
		$toggled = sanitize_key( wp_unslash( $_GET['kbroo-toggled'] ?? '' ) );
		?>
		<div class="wrap kbroo-tb">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Theme builder', 'kbroo-studio' ); ?></h1>
			<?php if ( $can_add && '' !== $current ) : ?>
				<a class="page-title-action" href="<?php echo esc_url( self::new_url( $current ) ); ?>">
					<?php
					/* translators: %s: template type, e.g. Header. */
					echo esc_html( sprintf( __( 'Add new %s', 'kbroo-studio' ), strtolower( $labels[ $current ] ) ) );
					?>
				</a>
			<?php endif; ?>
			<hr class="wp-header-end" />
			<?php if ( 'on' === $toggled || 'off' === $toggled ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'on' === $toggled ? __( 'Switched on: it now shows on the site where its conditions say.', 'kbroo-studio' ) : __( 'Switched off: the theme\'s own (or another template) shows instead.', 'kbroo-studio' ) ); ?></p></div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'The parts of your site built with Kbroo Studio: headers, footers and the templates for posts, pages, archives, search results and the 404 page. Sections and pages to build pages from stay under Templates.', 'kbroo-studio' ); ?></p>

			<div class="kbroo-tb__layout">
				<nav class="kbroo-tb__nav" aria-label="<?php esc_attr_e( 'Site parts', 'kbroo-studio' ); ?>">
					<a class="kbroo-tb__nav-item<?php echo '' === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( self::url() ); ?>"<?php echo '' === $current ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons dashicons-filter"></span><?php esc_html_e( 'All parts', 'kbroo-studio' ); ?>
					</a>
					<p class="kbroo-tb__nav-heading"><?php esc_html_e( 'Site parts', 'kbroo-studio' ); ?></p>
					<?php foreach ( self::TYPES as $type ) : ?>
						<a class="kbroo-tb__nav-item<?php echo $type === $current ? ' is-current' : ''; ?>" href="<?php echo esc_url( self::url( $type ) ); ?>"<?php echo $type === $current ? ' aria-current="page"' : ''; ?>>
							<span class="dashicons <?php echo esc_attr( self::ICONS[ $type ] ); ?>"></span><?php echo esc_html( $labels[ $type ] ); ?>
							<?php if ( $counts[ $type ] ) : ?>
								<span class="kbroo-tb__count"><?php echo (int) $counts[ $type ]; ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<div class="kbroo-tb__main">
					<h2 class="kbroo-tb__title"><?php echo esc_html( '' === $current ? __( 'Your site\'s parts', 'kbroo-studio' ) : $labels[ $current ] ); ?></h2>
					<?php if ( ! $shown ) : ?>
						<div class="kbroo-tb__empty">
							<p><?php echo esc_html( '' === $current ? __( 'No theme templates yet. Pick a part on the left to add one, or convert Elementor\'s under Move from Elementor.', 'kbroo-studio' ) : __( 'None yet: the theme\'s own is used.', 'kbroo-studio' ) ); ?></p>
							<?php if ( $can_add && '' !== $current ) : ?>
								<p><a class="button button-primary" href="<?php echo esc_url( self::new_url( $current ) ); ?>">
									<?php
									/* translators: %s: template type, e.g. Header. */
									echo esc_html( sprintf( __( 'Add new %s', 'kbroo-studio' ), strtolower( $labels[ $current ] ) ) );
									?>
								</a></p>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<div class="kbroo-tb__grid">
							<?php foreach ( $shown as $post ) : ?>
								<?php $this->card( $post, '' === $current ); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		self::styles();
	}

	private static function new_url( string $type ): string {
		return admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE . '&template_type=' . $type );
	}

	/**
	 * One template: status, name, live preview, where it shows and links.
	 */
	private function card( WP_Post $post, bool $show_type ): void {
		$type    = Templates::type_of( $post );
		$is_loop = 'loop-item' === $type;
		$live    = ! $is_loop && self::is_live( $post );
		$can     = current_user_can( 'edit_post', $post->ID );
		$editor  = Document::editor_url( $post );
		$title   = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'kbroo-studio' );
		$preview = add_query_arg(
			array(
				self::PREVIEW_VAR => $post->ID,
				'_wpnonce'        => wp_create_nonce( self::PREVIEW_VAR . $post->ID ),
			),
			home_url( '/' )
		);
		if ( $is_loop ) {
			$status = __( 'Used by Posts widgets set to this loop item', 'kbroo-studio' );
		} elseif ( 'publish' !== $post->post_status ) {
			$status = __( 'Draft: not shown on the site', 'kbroo-studio' );
		} else {
			$status = ThemeBuilder::summary( $post );
		}
		?>
		<div class="kbroo-tb__card<?php echo $live ? ' is-live' : ''; ?>" data-kbroo-template="<?php echo (int) $post->ID; ?>">
			<div class="kbroo-tb__card-head">
				<?php if ( ! $is_loop ) : ?>
					<span class="kbroo-tb__dot" title="<?php echo esc_attr( $live ? __( 'Showing on the site', 'kbroo-studio' ) : __( 'Not showing', 'kbroo-studio' ) ); ?>"></span>
					<span class="screen-reader-text"><?php echo esc_html( $live ? __( 'Showing on the site:', 'kbroo-studio' ) : __( 'Not showing:', 'kbroo-studio' ) ); ?></span>
				<?php endif; ?>
				<strong class="kbroo-tb__name"><?php echo esc_html( $title ); ?></strong>
				<?php if ( $show_type ) : ?>
					<span class="kbroo-tb__type"><?php echo esc_html( self::labels()[ $type ] ?? $type ); ?></span>
				<?php endif; ?>
				<?php if ( $can ) : ?>
					<a class="kbroo-tb__edit" href="<?php echo esc_url( $editor ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit', 'kbroo-studio' ); ?></a>
				<?php endif; ?>
			</div>
			<a class="kbroo-tb__preview kbroo-tb__preview--<?php echo esc_attr( $type ); ?>" href="<?php echo esc_url( $can ? $editor : '#' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: template name. */ __( 'Edit %s', 'kbroo-studio' ), $title ) ); ?>">
				<iframe src="<?php echo esc_url( $preview ); ?>" loading="lazy" tabindex="-1" aria-hidden="true" title="<?php echo esc_attr( $title ); ?>"></iframe>
			</a>
			<div class="kbroo-tb__card-foot">
				<span class="dashicons dashicons-networking" aria-hidden="true"></span>
				<span class="kbroo-tb__instances"><strong><?php echo esc_html( $is_loop ? __( 'Use:', 'kbroo-studio' ) : __( 'Instances:', 'kbroo-studio' ) ); ?></strong> <?php echo esc_html( $status ); ?></span>
				<?php if ( $can && ! $is_loop ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'kb-panel', 'display', $editor ) ); ?>"><?php esc_html_e( 'Edit conditions', 'kbroo-studio' ); ?></a>
					<?php if ( 'publish' === $post->post_status ) : ?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::TOGGLE . '&post=' . $post->ID ), self::TOGGLE . $post->ID ) ); ?>"><?php echo esc_html( ThemeBuilder::get_display( $post )['active'] ? __( 'Switch off', 'kbroo-studio' ) : __( 'Switch on', 'kbroo-studio' ) ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Switch a template on or off, from its card.
	 */
	public function toggle(): void {
		$id = absint( $_GET['post'] ?? 0 );
		check_admin_referer( self::TOGGLE . $id );
		$post = get_post( $id );
		if ( ! $post || ! Templates::is_template( $post ) || ! in_array( Templates::type_of( $post ), ThemeBuilder::TYPES, true ) ) {
			wp_die( esc_html__( 'Template not found.', 'kbroo-studio' ), 404 );
		}
		if ( ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change this.', 'kbroo-studio' ), 403 );
		}
		$display           = ThemeBuilder::get_display( $post );
		$display['active'] = ! $display['active'];
		ThemeBuilder::save_display( $id, $display );
		wp_safe_redirect( add_query_arg( 'kbroo-toggled', $display['active'] ? 'on' : 'off', self::url( Templates::type_of( $post ) ) ) );
		exit;
	}

	/**
	 * A template drawn alone on the front end, for its card's preview:
	 * styles and fonts as on the site, a recent post standing in for the
	 * post a single template or loop item shows.
	 */
	public function preview(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below.
		$id = absint( $_GET[ self::PREVIEW_VAR ] ?? 0 );
		if ( ! $id ) {
			return;
		}
		$post = get_post( $id );
		$ok   = $post && Templates::is_template( $post ) && current_user_can( 'edit_post', $id )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), self::PREVIEW_VAR . $id );
		if ( ! $ok ) {
			wp_die( esc_html__( 'This preview link has expired. Reload the Theme builder page.', 'kbroo-studio' ), 403 );
		}
		show_admin_bar( false );
		nocache_headers();
		// The post that Post title, Featured image... show.
		$type = Templates::type_of( $post );
		if ( 'single' === ThemeBuilder::area_of( $type ) || 'loop-item' === $type ) {
			$sample = Widgets\DynamicPost::sample( $post );
			if ( $sample ) {
				$GLOBALS['post'] = $sample; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				setup_postdata( $sample );
			}
		}
		$html = Plugin::instance()->render_template( $post );
		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex" />
	<?php wp_head(); ?>
	<style>html, body { margin: 0 !important; padding: 0 !important; overflow: hidden; pointer-events: none; } #wpadminbar { display: none !important; }</style>
</head>
<body <?php body_class( 'kbroo-template-preview' ); ?>>
	<?php echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- Rendered layout. ?>
	<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}

	private static function styles(): void {
		?>
		<style>
			.kbroo-tb__layout { display: flex; gap: 24px; margin-top: 16px; align-items: flex-start; }
			.kbroo-tb__nav { flex: 0 0 220px; background: #fff; border: 1px solid #dcdcde; padding: 8px 0; }
			.kbroo-tb__nav-heading { margin: 16px 16px 6px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #646970; }
			.kbroo-tb__nav-item { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: #1d2327; text-decoration: none; border-left: 3px solid transparent; }
			.kbroo-tb__nav-item:hover, .kbroo-tb__nav-item:focus { background: #f6f7f7; color: #1d2327; }
			.kbroo-tb__nav-item.is-current { background: #f0f0f1; border-left-color: #1d2327; font-weight: 600; }
			.kbroo-tb__nav-item .dashicons { color: #50575e; }
			.kbroo-tb__count { margin-left: auto; min-width: 18px; padding: 0 6px; border-radius: 9px; background: #dcdcde; font-size: 11px; line-height: 18px; text-align: center; }
			.kbroo-tb__main { flex: 1 1 auto; min-width: 0; }
			.kbroo-tb__title { margin: 4px 0 16px; font-size: 20px; font-weight: 400; }
			.kbroo-tb__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
			.kbroo-tb__card { display: flex; flex-direction: column; background: #fff; border: 1px solid #dcdcde; }
			.kbroo-tb__card-head, .kbroo-tb__card-foot { display: flex; align-items: center; gap: 8px; padding: 10px 12px; flex-wrap: wrap; }
			.kbroo-tb__card-head { border-bottom: 1px solid #f0f0f1; }
			.kbroo-tb__card-foot { border-top: 1px solid #f0f0f1; font-size: 12px; color: #50575e; }
			.kbroo-tb__card-foot .dashicons { font-size: 16px; width: 16px; height: 16px; }
			.kbroo-tb__instances { flex: 1 1 auto; min-width: 0; }
			.kbroo-tb__dot { flex: 0 0 auto; width: 8px; height: 8px; border-radius: 50%; background: #c3c4c7; }
			.kbroo-tb__card.is-live .kbroo-tb__dot { background: #00a32a; }
			.kbroo-tb__name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.kbroo-tb__type { font-size: 11px; color: #646970; background: #f0f0f1; padding: 1px 6px; border-radius: 3px; }
			.kbroo-tb__edit { display: inline-flex; align-items: center; gap: 2px; text-decoration: none; }
			.kbroo-tb__edit .dashicons { font-size: 16px; width: 16px; height: 16px; }
			.kbroo-tb__preview { position: relative; display: block; height: 220px; overflow: hidden; background: #f6f7f7; }
			.kbroo-tb__preview--header, .kbroo-tb__preview--footer { height: 120px; }
			.kbroo-tb__preview iframe { position: absolute; top: 0; left: 0; width: 400%; height: 400%; border: 0; transform: scale(0.25); transform-origin: 0 0; pointer-events: none; background: #fff; }
			.kbroo-tb__preview--loop-item iframe { width: 200%; height: 200%; transform: scale(0.5); }
			.kbroo-tb__preview:hover::after, .kbroo-tb__preview:focus::after { content: ""; position: absolute; inset: 0; box-shadow: inset 0 0 0 2px #2271b1; }
			.kbroo-tb__empty { background: #fff; border: 1px dashed #c3c4c7; padding: 24px; }
			@media (max-width: 782px) { .kbroo-tb__layout { flex-direction: column; } .kbroo-tb__nav { flex: none; width: 100%; } }
		</style>
		<?php
	}
}
