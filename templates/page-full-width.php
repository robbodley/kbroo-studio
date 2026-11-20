<?php
/**
 * The Full width page layout, for classic themes: the site header and
 * footer (the theme's, or Kbroo Studio's), and the page content edge to
 * edge with no theme title (see PageSettings::template()).
 *
 * @package KbrooStudio
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="content" class="kbroo-page-layout kbroo-page-layout--full-width">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
		\KbrooStudio\PageSettings::print_comments();
	endwhile;
	?>
</main>
<?php
get_footer();
