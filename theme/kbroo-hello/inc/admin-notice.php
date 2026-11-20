<?php
/**
 * A dismissible reminder on the Themes screen when Kbroo Studio is not active.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

/**
 * Prints the notice.
 */
function kbroo_hello_plugin_notice() {
	if ( defined( 'KBROO_STUDIO_VERSION' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'themes', 'dashboard' ), true ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), 'kbroo_hello_notice_dismissed', true ) ) {
		return;
	}

	$dismiss = wp_nonce_url( add_query_arg( 'kbroo-hello-dismiss', '1' ), 'kbroo_hello_dismiss' );
	?>
	<div class="notice notice-info">
		<p>
			<?php esc_html_e( 'Kbroo Hello is built to pair with the Kbroo Studio page builder. Install and activate the Kbroo Studio plugin to design your pages visually.', 'kbroo-hello' ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php esc_html_e( 'Go to Plugins', 'kbroo-hello' ); ?></a>
			<a class="button" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Dismiss', 'kbroo-hello' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'kbroo_hello_plugin_notice' );

/**
 * Remembers the dismissal for the current user.
 */
function kbroo_hello_dismiss_notice() {
	if ( empty( $_GET['kbroo-hello-dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked below.
		return;
	}
	check_admin_referer( 'kbroo_hello_dismiss' );
	update_user_meta( get_current_user_id(), 'kbroo_hello_notice_dismissed', 1 );
	wp_safe_redirect( remove_query_arg( array( 'kbroo-hello-dismiss', '_wpnonce' ) ) );
	exit;
}
add_action( 'admin_init', 'kbroo_hello_dismiss_notice' );

/**
 * When Kbroo Studio is active but older than this theme needs, say so on the
 * Dashboard, Themes, Plugins and Updates screens.
 */
function kbroo_hello_old_plugin_notice() {
	if ( ! defined( 'KBROO_STUDIO_VERSION' ) || version_compare( KBROO_STUDIO_VERSION, KBROO_HELLO_MIN_STUDIO, '>=' ) || ! current_user_can( 'update_plugins' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->id, array( 'themes', 'dashboard', 'plugins', 'update-core' ), true ) ) {
		return;
	}
	?>
	<div class="notice notice-warning kbroo-hello-old-plugin">
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: theme version, 2: plugin version it needs, 3: installed plugin version. */
					__( 'Kbroo Hello %1$s needs Kbroo Studio %2$s or newer, and this site has %3$s. Update the Kbroo Studio plugin so the two work together.', 'kbroo-hello' ),
					KBROO_HELLO_VERSION,
					KBROO_HELLO_MIN_STUDIO,
					KBROO_STUDIO_VERSION
				)
			);
			?>
		</p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'update-core.php?force-check=1' ) ); ?>"><?php esc_html_e( 'Check for updates', 'kbroo-hello' ); ?></a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'kbroo_hello_old_plugin_notice' );
