<?php
/**
 * Updates from GitHub releases, so Kbroo Hello updates like a theme from
 * WordPress.org (Appearance > Themes and Dashboard > Updates, with
 * auto-updates).
 *
 * The theme ships in the same GitHub releases as the Kbroo Studio plugin:
 * each release carries kbroo-hello-<version>.zip, built by the release
 * workflow. A newer theme is offered when the latest release's zip has a
 * higher version than the one installed. This works with or without the
 * plugin.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

/**
 * The updater. Static, so child themes and the plugin can read it.
 */
final class Kbroo_Hello_Updater {

	const SLUG      = 'kbroo-hello';
	const CACHE_KEY = 'kbroo_hello_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function init() {
		// style.css's "Update URI" points at github.com (WordPress 6.1+).
		add_filter( 'update_themes_github.com', array( __CLASS__, 'update_data' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'load-update-core.php', array( __CLASS__, 'forget_on_force_check' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'forget' ), 10, 0 );
	}

	/**
	 * Repository updates come from, as "owner/name".
	 */
	public static function repo() {
		return (string) apply_filters( 'kbroo_hello_update_repo', 'robbodley/kbroo-studio' );
	}

	/**
	 * The theme in the latest release, or null.
	 *
	 * @return array|null { version, package, url }
	 */
	public static function latest() {
		$cached = get_site_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return isset( $cached['release'] ) ? $cached['release'] : null;
		}

		$release  = null;
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo() . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Kbroo-Hello-Updater',
				),
			)
		);
		if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
			$release = self::parse( json_decode( wp_remote_retrieve_body( $response ), true ) );
		}
		set_site_transient( self::CACHE_KEY, array( 'release' => $release ), $release || ! is_wp_error( $response ) ? self::CACHE_TTL : HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * The theme zip in a GitHub release, or null.
	 *
	 * @param mixed $data Decoded release JSON.
	 * @return array|null
	 */
	public static function parse( $data ) {
		if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		foreach ( (array) ( isset( $data['assets'] ) ? $data['assets'] : array() ) as $asset ) {
			$name = is_array( $asset ) && isset( $asset['name'] ) ? (string) $asset['name'] : '';
			$url  = is_array( $asset ) && isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
			if ( preg_match( '/^kbroo-hello-(\d+(?:\.\d+){1,3})\.zip$/', $name, $m ) && 0 === strpos( $url, 'https://' ) ) {
				return array(
					'version' => $m[1],
					'package' => $url,
					'url'     => esc_url_raw( isset( $data['html_url'] ) ? (string) $data['html_url'] : 'https://github.com/' . self::repo() . '/releases' ),
				);
			}
		}
		return null;
	}

	/**
	 * Update data for Appearance > Themes and auto-updates.
	 *
	 * @param array|false $update     Update data so far.
	 * @param array       $theme_data Theme headers.
	 * @param string      $stylesheet Theme folder.
	 * @return array|false
	 */
	public static function update_data( $update, $theme_data, $stylesheet ) {
		if ( self::SLUG !== $stylesheet ) {
			return $update;
		}
		$release = self::latest();
		if ( ! $release ) {
			return $update;
		}
		$theme = wp_get_theme( self::SLUG );
		return array(
			'theme'        => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => (string) $theme->get( 'RequiresWP' ),
			'requires_php' => (string) $theme->get( 'RequiresPHP' ),
		);
	}

	/**
	 * Make sure the update lands in wp-content/themes/kbroo-hello.
	 *
	 * @param string|WP_Error $source        Unzipped folder.
	 * @param string          $remote_source Folder it was unzipped into.
	 * @param mixed           $upgrader      Upgrader.
	 * @param array           $hook_extra    Which theme is being updated.
	 * @return string|WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;
		if ( is_wp_error( $source ) || ! isset( $hook_extra['theme'] ) || self::SLUG !== $hook_extra['theme'] ) {
			return $source;
		}
		$want = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $want ) || ! $wp_filesystem ) {
			return $source;
		}
		if ( ! $wp_filesystem->move( $source, $want, true ) ) {
			return new WP_Error( 'kbroo_hello_update_folder', __( 'Could not prepare the Kbroo Hello update folder.', 'kbroo-hello' ) );
		}
		return $want;
	}

	/**
	 * "Check again" on Dashboard > Updates asks GitHub again too.
	 */
	public static function forget_on_force_check() {
		if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_themes' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only refresh, as core does.
			self::forget();
		}
	}

	public static function forget() {
		delete_site_transient( self::CACHE_KEY );
	}
}

Kbroo_Hello_Updater::init();
