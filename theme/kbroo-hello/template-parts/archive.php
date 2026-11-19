<?php
/**
 * Blog, category, tag, date, author and search results lists.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;
?>
<main id="content" class="site-main">
	<?php if ( is_search() ) : ?>
		<header class="page-header">
			<h1 class="page-title">
				<?php
				/* translators: %s: search terms. */
				echo esc_html( sprintf( __( 'Search results for: %s', 'kbroo-hello' ), get_search_query() ) );
				?>
			</h1>
		</header>
	<?php elseif ( is_archive() ) : ?>
		<header class="page-header">
			<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		</header>
	<?php elseif ( is_home() && ! is_front_page() ) : ?>
		<header class="page-header">
			<h1 class="page-title"><?php single_post_title(); ?></h1>
		</header>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-list__item' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="post-list__thumbnail" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true"><?php the_post_thumbnail( 'medium_large' ); ?></a>
					<?php endif; ?>
					<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
					<div class="entry-summary"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'kbroo-hello' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</main>
