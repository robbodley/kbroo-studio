<?php
/**
 * Full-screen editor document. Rendered by Editor::render_editor().
 *
 * @package KbrooStudio
 *
 * @var WP_Post $post Post being edited.
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>
		<?php
		/* translators: %s: post title. */
		echo esc_html( sprintf( __( 'Kbroo Studio | %s', 'kbroo-studio' ), get_the_title( $post ) ) );
		?>
	</title>
	<?php wp_print_styles(); ?>
	<?php wp_print_head_scripts(); ?>
</head>
<body class="kbroo-editor-body">
	<div id="kbroo-app" class="kbroo-app">
		<noscript><?php esc_html_e( 'Kbroo Studio needs JavaScript to run.', 'kbroo-studio' ); ?></noscript>
	</div>
	<?php wp_print_footer_scripts(); ?>
</body>
</html>
