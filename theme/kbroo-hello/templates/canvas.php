<?php
/**
 * Template Name: Kbroo Canvas
 * Template Post Type: page, post
 *
 * A blank page: no theme header, footer or title. Only the page content,
 * edge to edge. Use it for landing pages built entirely in Kbroo Studio.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<main id="content" class="site-main site-main--builder">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
