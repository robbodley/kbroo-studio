<?php
/**
 * The Canvas page layout: a blank page with only the page content, edge to
 * edge. No theme or Kbroo Studio header and footer, and no title. Used for
 * any theme (see PageSettings::template()).
 *
 * @package KbrooStudio
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="content" class="kbroo-page-layout kbroo-page-layout--canvas">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
		\KbrooStudio\PageSettings::print_comments();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
