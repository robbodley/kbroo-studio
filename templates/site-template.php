<?php
/**
 * A Kbroo Studio archive, single post or page, or 404 template, for
 * classic themes (see ThemeBuilder::archive_template()). The theme's header
 * and footer, or Kbroo Studio's, go around it.
 *
 * @package KbrooStudio
 */

defined( 'ABSPATH' ) || exit;

$kbroo_area = \KbrooStudio\ThemeBuilder::content_area();

get_header();
?>
<main id="primary" class="kbroo-<?php echo esc_attr( 'not_found' === $kbroo_area ? '404' : $kbroo_area ); ?>">
	<?php echo \KbrooStudio\Plugin::instance()->theme_builder()->render_content(); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendered layout. ?>
</main>
<?php
get_footer();
