<?php
/**
 * The Kbroo Studio footer and the closing tags, printed in place of a
 * classic theme's footer.php (see ThemeBuilder::classic_footer()).
 *
 * @package KbrooStudio
 * @var \KbrooStudio\ThemeBuilder $this
 */

defined( 'ABSPATH' ) || exit;

echo $this->render_area( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendered layout.
wp_footer();
?>
</body>
</html>
