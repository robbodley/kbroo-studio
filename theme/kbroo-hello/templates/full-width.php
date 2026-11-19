<?php
/**
 * Template Name: Kbroo Full Width
 * Template Post Type: page, post
 *
 * Keeps the site header and footer, but shows the content edge to edge with
 * no theme title. Kbroo Studio pages already get this automatically; this
 * template does the same for any other page.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="content" class="site-main site-main--builder">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
get_footer();
