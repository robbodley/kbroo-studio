<?php
/**
 * The theme's own plain footer: footer menu and copyright line.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;
?>
<footer id="site-footer" class="site-footer">
	<div class="site-footer__inner">
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="site-navigation" aria-label="<?php esc_attr_e( 'Footer menu', 'kbroo-hello' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'depth'          => 1,
					)
				);
				?>
			</nav>
		<?php endif; ?>
		<p class="site-info">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
