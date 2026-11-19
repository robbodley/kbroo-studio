<?php
/**
 * The theme's own plain header: logo or site name, tagline, header menu.
 * Hidden when Kbroo Studio prints its own header, or switched off in the
 * Customizer.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;
?>
<header id="site-header" class="site-header">
	<div class="site-header__inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
			<?php endif; ?>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<p class="site-description"><?php bloginfo( 'description' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="site-navigation" aria-label="<?php esc_attr_e( 'Header menu', 'kbroo-hello' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'depth'          => 2,
					)
				);
				?>
			</nav>
		<?php endif; ?>
	</div>
</header>
