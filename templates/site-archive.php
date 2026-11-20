<?php
/**
 * A Kbroo Studio blog archive template, for classic themes (see
 * ThemeBuilder::archive_template()). The theme's header and footer, or
 * Kbroo Studio's, go around it.
 *
 * @package KbrooStudio
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="kbroo-archive">
	<?php echo \KbrooStudio\Plugin::instance()->theme_builder()->render_area( 'archive' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendered layout. ?>
</main>
<?php
get_footer();
