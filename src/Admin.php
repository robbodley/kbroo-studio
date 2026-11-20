<?php
/**
 * The Kbroo Studio menu in the WordPress admin sidebar. Its first page is
 * an overview: pages built with Kbroo Studio, a button for a new page, and
 * links to the site styles, Templates, Move from Elementor and Form
 * messages, which sit under the same menu.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class Admin {

	/** Menu slug; other pages use it as their parent. */
	const MENU = 'kbroo-studio';

	/** admin-post action that creates a page and opens it in the editor. */
	const NEW_PAGE = 'kbroo_new_page';

	/** admin-post action that asks GitHub for Kbroo Studio and Kbroo Hello updates. */
	const CHECK_UPDATES = 'kbroo_check_updates';

	public function hooks(): void {
		// Before admin_menu's default priority, so post types and other
		// pages can add themselves under this menu.
		add_action( 'admin_menu', array( $this, 'menu' ), 9 );
		add_action( 'admin_post_' . self::NEW_PAGE, array( $this, 'new_page' ) );
		add_action( 'admin_post_' . self::CHECK_UPDATES, array( $this, 'check_updates' ) );
		add_action( 'admin_notices', array( $this, 'old_theme_notice' ) );
	}

	/**
	 * The Kbroo Hello version in use (as the theme or a child theme's
	 * parent), or '' when the site doesn't use Kbroo Hello.
	 */
	public static function hello_version(): string {
		$theme = wp_get_theme();
		if ( 'kbroo-hello' !== $theme->get_template() ) {
			return '';
		}
		$hello = $theme->parent() ? $theme->parent() : $theme;
		return (string) $hello->get( 'Version' );
	}

	/**
	 * When the site uses a Kbroo Hello older than this plugin needs, say so
	 * on the Dashboard, Themes, Plugins, Updates and Kbroo Studio screens.
	 */
	public function old_theme_notice(): void {
		$version = self::hello_version();
		if ( '' === $version || version_compare( $version, KBROO_STUDIO_MIN_HELLO, '>=' ) || ! current_user_can( 'update_themes' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'themes', 'plugins', 'update-core', 'toplevel_page_' . self::MENU ), true ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning kbroo-old-theme"><p>%s</p><p><a class="button button-primary" href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: 1: plugin version, 2: theme version it needs, 3: installed theme version. */
					__( 'Kbroo Studio %1$s needs the Kbroo Hello theme %2$s or newer, and this site has %3$s. Update the theme so the two work together.', 'kbroo-studio' ),
					KBROO_STUDIO_VERSION,
					KBROO_STUDIO_MIN_HELLO,
					$version
				)
			),
			esc_url( self::check_updates_url() ),
			esc_html__( 'Check for updates', 'kbroo-studio' )
		);
	}

	/**
	 * The "Check for updates" link: asks GitHub for Kbroo updates and comes
	 * back to the overview, without going through Dashboard > Updates.
	 */
	public static function check_updates_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::CHECK_UPDATES ), self::CHECK_UPDATES );
	}

	public function check_updates(): void {
		check_admin_referer( self::CHECK_UPDATES );
		if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'update_themes' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to update plugins or themes.', 'kbroo-studio' ), 403 );
		}
		Updater::check_now();
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU . '&kbroo-checked=1#kbroo-updates' ) );
		exit;
	}

	public function menu(): void {
		add_menu_page(
			__( 'Kbroo Studio', 'kbroo-studio' ),
			__( 'Kbroo Studio', 'kbroo-studio' ),
			'edit_pages',
			self::MENU,
			array( $this, 'render_overview' ),
			self::icon(),
			58
		);
		add_submenu_page(
			self::MENU,
			__( 'Kbroo Studio', 'kbroo-studio' ),
			__( 'Overview', 'kbroo-studio' ),
			'edit_pages',
			self::MENU,
			array( $this, 'render_overview' )
		);
	}

	/**
	 * Menu icon: a data URI SVG, which WordPress tints to match the menu.
	 */
	private static function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M3 2h14a1 1 0 0 1 1 1v4H2V3a1 1 0 0 1 1-1zm-1 7h7v9H3a1 1 0 0 1-1-1V9zm9 0h7v3h-7V9zm0 5h7v3a1 1 0 0 1-1 1h-6v-4z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress menu icons take a data URI.
	}

	/**
	 * Create an empty page and open it in the editor.
	 */
	public function new_page(): void {
		check_admin_referer( self::NEW_PAGE );
		$type = sanitize_key( wp_unslash( $_GET['post_type'] ?? 'page' ) );
		if ( ! in_array( $type, Document::post_types(), true ) ) {
			$type = 'page';
		}
		$object = get_post_type_object( $type );
		if ( ! $object || ! current_user_can( $object->cap->create_posts ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create this.', 'kbroo-studio' ), 403 );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'draft',
				/* translators: %s: post type name, e.g. "Page". */
				'post_title'  => sprintf( __( 'New %s', 'kbroo-studio' ), strtolower( $object->labels->singular_name ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		Document::save_layout( $id, Layout::empty_layout() );
		wp_safe_redirect( Document::editor_url( $id ) );
		exit;
	}

	/**
	 * Pages and posts built with Kbroo Studio, newest change first.
	 */
	private function built_posts( int $limit ): WP_Query {
		return new WP_Query(
			array(
				'post_type'      => Document::post_types(),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_key'       => Document::META_MODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin list.
				'meta_value'     => 'builder', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'  => false,
			)
		);
	}

	/**
	 * The newest switched-on header or footer, for the overview.
	 */
	private function area_templates( string $area ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'meta_key'       => Templates::META_TYPE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $area, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $posts as $post ) {
			$display = ThemeBuilder::get_display( $post );
			if ( $display['active'] && $display['include'] ) {
				return $post;
			}
		}
		return null;
	}

	public function render_overview(): void {
		$query   = $this->built_posts( 20 );
		$latest  = $query->posts ? $query->posts[0] : null;
		$new_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::NEW_PAGE . '&post_type=page' ), self::NEW_PAGE );
		$styles  = $latest ? add_query_arg( 'kb-panel', 'globals', Document::editor_url( $latest ) ) : '';
		$forms   = (int) ( wp_count_posts( Forms::POST_TYPE )->private ?? 0 );
		$counts  = wp_count_posts( Templates::POST_TYPE );
		$library = (int) ( $counts->publish ?? 0 ) + (int) ( $counts->draft ?? 0 ) + (int) ( $counts->private ?? 0 );
		?>
		<div class="wrap kbroo-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Kbroo Studio', 'kbroo-studio' ); ?></h1>
			<?php if ( current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' ) ) : ?>
				<a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action"><?php esc_html_e( 'New page', 'kbroo-studio' ); ?></a>
			<?php endif; ?>
			<hr class="wp-header-end" />

			<div class="kbroo-admin__cards">
				<div class="card">
					<h2 class="title"><?php esc_html_e( 'Site colours, fonts and CSS', 'kbroo-studio' ); ?></h2>
					<p><?php esc_html_e( 'Colours and fonts every page can link to, and CSS added to every page built with Kbroo Studio.', 'kbroo-studio' ); ?></p>
					<?php if ( $styles && current_user_can( Globals::CAPABILITY ) ) : ?>
						<p><a class="button" href="<?php echo esc_url( $styles ); ?>"><?php esc_html_e( 'Edit site styles', 'kbroo-studio' ); ?></a></p>
					<?php elseif ( ! $styles ) : ?>
						<p class="description"><?php esc_html_e( 'Create a page first: site styles are edited from the editor, where you can see them on a page.', 'kbroo-studio' ); ?></p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'Only administrators can change them.', 'kbroo-studio' ); ?></p>
					<?php endif; ?>
				</div>
				<div class="card">
					<h2 class="title"><?php esc_html_e( 'Templates', 'kbroo-studio' ); ?></h2>
					<p>
						<?php
						/* translators: %d: number of saved templates. */
						echo esc_html( sprintf( _n( '%d saved section or page to insert into any page.', '%d saved sections and pages to insert into any page.', $library, 'kbroo-studio' ), $library ) );
						?>
					</p>
					<p>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Templates::POST_TYPE ) ); ?>"><?php esc_html_e( 'View templates', 'kbroo-studio' ); ?></a>
						<?php if ( current_user_can( 'publish_pages' ) ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE ) ); ?>"><?php esc_html_e( 'New template', 'kbroo-studio' ); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<div class="card">
					<h2 class="title"><?php esc_html_e( 'Header, footer and blog archive', 'kbroo-studio' ); ?></h2>
					<?php foreach ( ThemeBuilder::TYPES as $area ) : ?>
						<?php $current = $this->area_templates( $area ); ?>
						<p>
							<strong><?php echo esc_html( Templates::types()[ $area ] ); ?>:</strong>
							<?php if ( $current ) : ?>
								<a href="<?php echo esc_url( Document::editor_url( $current ) ); ?>"><?php echo esc_html( get_the_title( $current ) ); ?></a>
								<span class="description">(<?php echo esc_html( ThemeBuilder::summary( $current ) ); ?>)</span>
							<?php else : ?>
								<?php esc_html_e( 'the theme\'s own', 'kbroo-studio' ); ?>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
					<?php if ( current_user_can( 'publish_pages' ) ) : ?>
						<p>
							<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE . '&template_type=header' ) ); ?>"><?php esc_html_e( 'New header', 'kbroo-studio' ); ?></a>
							<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE . '&template_type=footer' ) ); ?>"><?php esc_html_e( 'New footer', 'kbroo-studio' ); ?></a>
							<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . Templates::POST_TYPE . '&template_type=archive' ) ); ?>"><?php esc_html_e( 'New blog archive', 'kbroo-studio' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
				<div class="card">
					<h2 class="title"><?php esc_html_e( 'Move from Elementor', 'kbroo-studio' ); ?></h2>
					<p><?php esc_html_e( 'Convert Elementor pages and import their colours, fonts and custom CSS. Elementor\'s own data is never changed.', 'kbroo-studio' ); ?></p>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . Elementor\Migration::PAGE ) ); ?>"><?php esc_html_e( 'Open', 'kbroo-studio' ); ?></a></p>
				</div>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<div class="card">
						<h2 class="title"><?php esc_html_e( 'Form messages', 'kbroo-studio' ); ?></h2>
						<p>
							<?php
							/* translators: %d: number of saved form messages. */
							echo esc_html( sprintf( _n( '%d message sent through your forms.', '%d messages sent through your forms.', $forms, 'kbroo-studio' ), $forms ) );
							?>
						</p>
						<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Forms::POST_TYPE ) ); ?>"><?php esc_html_e( 'View messages', 'kbroo-studio' ); ?></a></p>
					</div>
				<?php endif; ?>
				<div class="card" id="kbroo-updates">
					<h2 class="title"><?php esc_html_e( 'Updates', 'kbroo-studio' ); ?></h2>
					<?php
					$can_plugins = current_user_can( 'update_plugins' );
					$can_themes  = current_user_can( 'update_themes' ) && '' !== self::hello_version();
					$release     = $can_plugins ? Updater::latest() : null;
					$plugin_next = $can_plugins ? Updater::pending_plugin_version() : '';
					$plugin_next = '' !== $plugin_next && version_compare( $plugin_next, KBROO_STUDIO_VERSION, '>' ) ? $plugin_next : '';
					$theme_next  = $can_themes ? Updater::pending_theme_version() : '';
					$theme_next  = '' !== $theme_next && version_compare( $theme_next, self::hello_version(), '>' ) ? $theme_next : '';
					if ( ! empty( $_GET['kbroo-checked'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks a message.
						echo '<p><em>' . esc_html__( 'Checked GitHub for updates just now.', 'kbroo-studio' ) . '</em></p>';
					}
					/* translators: %s: version number. */
					echo '<p>' . esc_html( sprintf( __( 'Kbroo Studio %s.', 'kbroo-studio' ), KBROO_STUDIO_VERSION ) ) . ' ';
					if ( '' !== $plugin_next ) {
						/* translators: %s: version number. */
						echo '<strong>' . esc_html( sprintf( __( 'Version %s is out.', 'kbroo-studio' ), $plugin_next ) ) . '</strong>';
					} elseif ( $release && version_compare( $release['version'], KBROO_STUDIO_VERSION, '>' ) ) {
						/* translators: %s: version number. */
						echo '<strong>' . esc_html( sprintf( __( 'Version %s is out. Check for updates to get it.', 'kbroo-studio' ), $release['version'] ) ) . '</strong>';
					} elseif ( $release ) {
						esc_html_e( 'This is the latest release.', 'kbroo-studio' );
					}
					echo '</p>';
					if ( '' !== self::hello_version() ) {
						/* translators: %s: version number. */
						echo '<p>' . esc_html( sprintf( __( 'Kbroo Hello theme %s.', 'kbroo-studio' ), self::hello_version() ) ) . ' ';
						if ( '' !== $theme_next ) {
							/* translators: %s: version number. */
							echo '<strong>' . esc_html( sprintf( __( 'Version %s is out.', 'kbroo-studio' ), $theme_next ) ) . '</strong>';
						}
						echo '</p>';
					}
					?>
					<p class="description"><?php esc_html_e( 'New versions come from GitHub releases and install like any plugin update. Turn on auto-updates for Kbroo Studio on the Plugins screen to have them installed for you.', 'kbroo-studio' ); ?></p>
					<?php if ( $can_plugins || $can_themes ) : ?>
						<p>
							<?php if ( '' !== $plugin_next ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( Updater::update_plugin_url() ); ?>"><?php esc_html_e( 'Update Kbroo Studio', 'kbroo-studio' ); ?></a>
							<?php endif; ?>
							<?php if ( '' !== $theme_next ) : ?>
								<a class="button button-primary" href="<?php echo esc_url( Updater::update_theme_url() ); ?>"><?php esc_html_e( 'Update Kbroo Hello', 'kbroo-studio' ); ?></a>
							<?php endif; ?>
							<a class="button" href="<?php echo esc_url( self::check_updates_url() ); ?>"><?php esc_html_e( 'Check for updates', 'kbroo-studio' ); ?></a>
						</p>
					<?php endif; ?>
				</div>
			</div>

			<h2><?php esc_html_e( 'Built with Kbroo Studio', 'kbroo-studio' ); ?></h2>
			<?php if ( ! $query->posts ) : ?>
				<p><?php esc_html_e( 'Nothing yet. Start a new page, or open any page and choose Edit with Kbroo Studio.', 'kbroo-studio' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Title', 'kbroo-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Type', 'kbroo-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Status', 'kbroo-studio' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last changed', 'kbroo-studio' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $query->posts as $post ) :
							$type   = get_post_type_object( $post->post_type );
							$status = get_post_status_object( $post->post_status );
							$title  = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'kbroo-studio' );
							?>
							<tr>
								<td>
									<strong>
										<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
											<a href="<?php echo esc_url( Document::editor_url( $post ) ); ?>"><?php echo esc_html( $title ); ?></a>
										<?php else : ?>
											<?php echo esc_html( $title ); ?>
										<?php endif; ?>
									</strong>
									<div class="row-actions visible">
										<?php if ( current_user_can( 'edit_post', $post->ID ) ) : ?>
											<a href="<?php echo esc_url( Document::editor_url( $post ) ); ?>"><?php esc_html_e( 'Edit with Kbroo Studio', 'kbroo-studio' ); ?></a> |
										<?php endif; ?>
										<a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php esc_html_e( 'View', 'kbroo-studio' ); ?></a>
									</div>
								</td>
								<td><?php echo esc_html( $type ? $type->labels->singular_name : $post->post_type ); ?></td>
								<td><?php echo esc_html( $status ? $status->label : $post->post_status ); ?></td>
								<td><?php echo esc_html( sprintf( /* translators: %s: time ago. */ __( '%s ago', 'kbroo-studio' ), human_time_diff( get_post_modified_time( 'U', true, $post ), time() ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $query->found_posts > count( $query->posts ) ) : ?>
					<p class="description">
						<?php
						/* translators: 1: number shown, 2: total. */
						echo esc_html( sprintf( __( 'Showing the %1$d most recently changed of %2$d. The Pages and Posts lists mark the rest "Kbroo Studio".', 'kbroo-studio' ), count( $query->posts ), $query->found_posts ) );
						?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<style>
			.kbroo-admin__cards { display: flex; flex-wrap: wrap; gap: 16px; margin: 16px 0 24px; }
			.kbroo-admin__cards .card { flex: 1 1 260px; max-width: 420px; margin: 0; }
		</style>
		<?php
	}
}
