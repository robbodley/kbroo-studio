<?php
/**
 * Plugin Name:       Kbroo Studio
 * Plugin URI:        https://github.com/robbodley/kbroo-studio
 * Description:       A visual, drag-and-drop page builder for WordPress.
 * Version:           0.3.1
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Kbroo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kbroo-studio
 * Update URI:        https://github.com/robbodley/kbroo-studio
 *
 * @package KbrooStudio
 */

defined( 'ABSPATH' ) || exit;

define( 'KBROO_STUDIO_VERSION', '0.3.1' );

/*
 * Oldest Kbroo Hello theme this plugin works with. Raise it when the plugin
 * starts relying on something newer in the theme; admins using an older
 * Kbroo Hello then see a notice asking them to update the theme.
 */
define( 'KBROO_STUDIO_MIN_HELLO', '0.1.0' );
define( 'KBROO_STUDIO_FILE', __FILE__ );
define( 'KBROO_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'KBROO_STUDIO_URL', plugin_dir_url( __FILE__ ) );

/*
 * Minimal PSR-4 autoloader for the KbrooStudio\ namespace, mapped to src/.
 * No Composer needed, so the plugin works when dropped into wp-content/plugins.
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'KbrooStudio\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = KBROO_STUDIO_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		KbrooStudio\Plugin::instance()->boot();
	}
);
