<?php
/**
 * Updates from GitHub releases. WordPress offers an update when the latest
 * release on github.com/robbodley/kbroo-studio has a higher version than
 * the one installed, and installs it like any other plugin update (so the
 * "Enable auto-updates" link on the Plugins screen works too).
 *
 * A release is offered only once its kbroo-studio.zip is attached; the
 * GitHub workflow in .github/workflows/release.yml builds and attaches it
 * when a release is published.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

defined( 'ABSPATH' ) || exit;

class Updater {

	const SLUG      = 'kbroo-studio';
	const ASSET     = 'kbroo-studio.zip';
	const CACHE_KEY = 'kbroo_studio_release';

	/** The Kbroo Hello theme's folder, and where its updater caches the release. */
	const THEME           = 'kbroo-hello';
	const THEME_CACHE_KEY = 'kbroo_hello_release';

	/** How long a check is kept, so GitHub is asked a few times a day at most. */
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public function register(): void {
		// The plugin header's "Update URI" points at github.com, so WordPress
		// asks this filter instead of WordPress.org (WordPress 5.8+).
		add_filter( 'update_plugins_github.com', array( $this, 'update_data' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder' ), 10, 4 );
		// Priority 1: core checks for updates on this same hook at priority
		// 10, so the cached release has to be gone before then.
		add_action( 'load-update-core.php', array( $this, 'forget_on_force_check' ), 1 );
		add_action( 'upgrader_process_complete', array( $this, 'forget' ), 10, 0 );
	}

	public static function repo(): string {
		/**
		 * Filters the GitHub repository updates come from, as "owner/name".
		 *
		 * @param string $repo Repository.
		 */
		return (string) apply_filters( 'kbroo_studio_update_repo', 'robbodley/kbroo-studio' );
	}

	public static function basename(): string {
		return plugin_basename( KBROO_STUDIO_FILE );
	}

	/**
	 * The latest release, or null when there is none or GitHub can't be
	 * reached. Kept for a few hours either way.
	 *
	 * @return array{version: string, tag: string, package: string, url: string, notes: string, published: string}|null
	 */
	public static function latest(): ?array {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached['release'] ?? null;
		}

		$release  = null;
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Kbroo-Studio-Updater',
				),
			)
		);
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$release = self::parse( json_decode( wp_remote_retrieve_body( $response ), true ) );
		}

		// A failed check is kept for an hour, so a GitHub outage doesn't slow
		// every admin page. A release whose zip isn't attached yet (the
		// workflow takes a few minutes) is asked about again soon.
		set_site_transient( self::CACHE_KEY, array( 'release' => $release ), $release ? self::CACHE_TTL : ( is_wp_error( $response ) ? HOUR_IN_SECONDS : 15 * MINUTE_IN_SECONDS ) );
		return $release;
	}

	/**
	 * A GitHub release, as far as updating needs it.
	 *
	 * @param mixed $data Decoded release JSON.
	 */
	public static function parse( $data ): ?array {
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$tag     = (string) ( $data['tag_name'] ?? '' );
		$version = ltrim( $tag, 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+){1,3}([-.][0-9A-Za-z.]+)?$/', $version ) ) {
			return null;
		}
		$package = '';
		foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
			if ( is_array( $asset ) && self::ASSET === ( $asset['name'] ?? '' ) && 0 === strpos( (string) ( $asset['browser_download_url'] ?? '' ), 'https://' ) ) {
				$package = (string) $asset['browser_download_url'];
			}
		}
		if ( '' === $package ) {
			return null; // Not built yet.
		}
		return array(
			'version'   => $version,
			'tag'       => $tag,
			'package'   => $package,
			'url'       => esc_url_raw( (string) ( $data['html_url'] ?? 'https://github.com/' . self::repo() . '/releases' ) ),
			'notes'     => (string) ( $data['body'] ?? '' ),
			'published' => (string) ( $data['published_at'] ?? '' ),
		);
	}

	/**
	 * Update data for the Plugins screen and auto-updates.
	 *
	 * @param array|false $update      Update data so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function update_data( $update, $plugin_data, $plugin_file ) {
		if ( self::basename() !== $plugin_file ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}
		// Returned whatever the version, so WordPress can also show "up to
		// date"; it offers the update only when this version is higher.
		return array(
			'id'           => 'github.com/' . self::repo(),
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => (string) ( $plugin_data['RequiresWP'] ?? '' ),
			'requires_php' => (string) ( $plugin_data['RequiresPHP'] ?? '' ),
		);
	}

	/**
	 * The "View details" popup.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action plugins_api action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $result;
		}
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$headers = get_plugin_data( KBROO_STUDIO_FILE, false, false );

		return (object) array(
			'name'          => 'Kbroo Studio',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => esc_html( $headers['Author'] ?? 'Kbroo' ),
			'homepage'      => 'https://github.com/' . self::repo(),
			'requires'      => $headers['RequiresWP'] ?? '',
			'requires_php'  => $headers['RequiresPHP'] ?? '',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => esc_html( $headers['Description'] ?? '' ),
				'changelog'   => self::notes_html( $release ),
			),
		);
	}

	/**
	 * Release notes as simple HTML: headings, bullets, bold and links.
	 */
	public static function notes_html( array $release ): string {
		$html = '<h4>' . esc_html( $release['tag'] ) . '</h4>';
		$list = false;
		foreach ( preg_split( '/\r?\n/', $release['notes'] ) as $line ) {
			$line = trim( $line );
			$text = esc_html( preg_replace( '/^(#+|[-*])\s+/', '', $line ) );
			$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
			$text = preg_replace( '/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/', '<a href="$2">$1</a>', $text );
			if ( preg_match( '/^[-*]\s+/', $line ) ) {
				$html .= ( $list ? '' : '<ul>' ) . '<li>' . $text . '</li>';
				$list  = true;
				continue;
			}
			if ( $list ) {
				$html .= '</ul>';
				$list  = false;
			}
			if ( '' === $line ) {
				continue;
			}
			$html .= preg_match( '/^#+\s/', $line ) ? '<h4>' . $text . '</h4>' : '<p>' . $text . '</p>';
		}
		$html .= $list ? '</ul>' : '';
		$html .= '<p><a href="' . esc_url( $release['url'] ) . '">' . esc_html__( 'Release on GitHub', 'kbroo-studio' ) . '</a></p>';
		return wp_kses_post( $html );
	}

	/**
	 * Make sure the update lands in wp-content/plugins/kbroo-studio, whatever
	 * the folder in the zip is called.
	 *
	 * @param string|\WP_Error $source        Unzipped folder.
	 * @param string           $remote_source Folder it was unzipped into.
	 * @param mixed            $upgrader      Upgrader.
	 * @param array            $hook_extra    Which plugin is being updated.
	 * @return string|\WP_Error
	 */
	public function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( is_wp_error( $source ) || self::basename() !== ( $hook_extra['plugin'] ?? '' ) ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $want ) || ! $wp_filesystem ) {
			return $source;
		}
		if ( ! $wp_filesystem->move( $source, $want, true ) ) {
			return new \WP_Error( 'kbroo_update_folder', __( 'Could not prepare the Kbroo Studio update folder.', 'kbroo-studio' ) );
		}
		return $want;
	}

	/**
	 * "Check again" on Dashboard > Updates asks GitHub again too.
	 */
	public function forget_on_force_check(): void {
		if ( ! empty( $_GET['force-check'] ) && ( current_user_can( 'update_plugins' ) || current_user_can( 'update_themes' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only refresh, as core does.
			self::forget_all();
		}
	}

	public function forget(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Forget the cached GitHub release for the plugin and the Kbroo Hello
	 * theme (its updater keeps its own copy).
	 */
	public static function forget_all(): void {
		delete_site_transient( self::CACHE_KEY );
		delete_site_transient( self::THEME_CACHE_KEY );
	}

	/**
	 * Ask GitHub again now, and have WordPress rebuild its list of plugin
	 * and theme updates, so a new Kbroo Studio or Kbroo Hello release shows
	 * up straight away. WordPress core itself is not checked or updated.
	 */
	public static function check_now(): void {
		self::forget_all();
		$checks = array(
			'update_plugins' => 'wp_update_plugins',
			'update_themes'  => 'wp_update_themes',
		);
		foreach ( $checks as $cap => $check ) {
			if ( ! current_user_can( $cap ) || ! function_exists( $check ) ) {
				continue;
			}
			// Mark WordPress's last check as old rather than deleting it, so
			// the other updates it knows about stay if WordPress.org can't be
			// reached.
			$current = get_site_transient( $cap );
			if ( is_object( $current ) ) {
				$current->last_checked = 0;
				set_site_transient( $cap, $current );
			}
			$check();
		}
	}

	/**
	 * The version WordPress will install for the plugin, or '' when it knows
	 * of no update.
	 */
	public static function pending_plugin_version(): string {
		$updates = get_site_transient( 'update_plugins' );
		$update  = is_object( $updates ) && isset( $updates->response[ self::basename() ] ) ? (array) $updates->response[ self::basename() ] : array();
		return (string) ( $update['new_version'] ?? '' );
	}

	/**
	 * The version WordPress will install for Kbroo Hello, or ''.
	 */
	public static function pending_theme_version(): string {
		$updates = get_site_transient( 'update_themes' );
		$update  = is_object( $updates ) && isset( $updates->response[ self::THEME ] ) ? (array) $updates->response[ self::THEME ] : array();
		return (string) ( $update['new_version'] ?? '' );
	}

	/**
	 * Link that installs the waiting plugin update, as "update now" on the
	 * Plugins screen does.
	 */
	public static function update_plugin_url(): string {
		return wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( self::basename() ) ), 'upgrade-plugin_' . self::basename() );
	}

	/**
	 * Link that installs the waiting Kbroo Hello update.
	 */
	public static function update_theme_url(): string {
		return wp_nonce_url( self_admin_url( 'update.php?action=upgrade-theme&theme=' . rawurlencode( self::THEME ) ), 'upgrade-theme_' . self::THEME );
	}
}
