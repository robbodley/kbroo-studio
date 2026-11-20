<?php
/**
 * The document head and the Kbroo Studio header, printed in place of a
 * classic theme's header.php (see ThemeBuilder::classic_header()).
 *
 * @package KbrooStudio
 * @var \KbrooStudio\ThemeBuilder $this
 */

defined( 'ABSPATH' ) || exit;

// Render first, so its styles and fonts are enqueued before wp_head().
$kbroo_header = $this->render_area( 'header' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php echo $kbroo_header; // phpcs:ignore WordPress.Security.EscapeOutput -- Rendered layout. ?>
