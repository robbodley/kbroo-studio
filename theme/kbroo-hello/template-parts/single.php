<?php
/**
 * A page or post. Pages built with Kbroo Studio get the whole width and no
 * theme title, so the builder layout is all that shows.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

$kbroo_hello_builder = kbroo_hello_is_builder_page();
?>
<main id="content" class="site-main<?php echo $kbroo_hello_builder ? ' site-main--builder' : ''; ?>">
	<?php
	while ( have_posts() ) :
		the_post();

		if ( $kbroo_hello_builder ) :
			the_content();
			continue;
		endif;
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php
			/**
			 * Filters whether the theme prints the page title.
			 *
			 * @param bool $show Whether to show the title.
			 */
			if ( apply_filters( 'kbroo_hello_show_page_title', true ) ) :
				?>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
					<?php if ( 'post' === get_post_type() ) : ?>
						<p class="entry-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<?php if ( has_post_thumbnail() && ! is_page() ) : ?>
				<figure class="entry-thumbnail"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'kbroo-hello' ),
						'after'  => '</nav>',
					)
				);
				?>
			</div>

			<?php if ( 'post' === get_post_type() ) : ?>
				<footer class="entry-footer">
					<?php the_tags( '<p class="entry-tags">', ', ', '</p>' ); ?>
				</footer>
			<?php endif; ?>
		</article>
		<?php
		if ( is_singular( 'post' ) ) {
			the_post_navigation();
		}
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
	endwhile;
	?>
</main>
