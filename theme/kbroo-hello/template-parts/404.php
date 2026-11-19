<?php
/**
 * Page not found.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;
?>
<main id="content" class="site-main">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'kbroo-hello' ); ?></h1>
	</header>
	<p><?php esc_html_e( 'The page you were looking for could not be found. Try a search instead.', 'kbroo-hello' ); ?></p>
	<?php get_search_form(); ?>
</main>
