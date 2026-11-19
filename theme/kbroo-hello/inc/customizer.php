<?php
/**
 * Appearance > Customize > Kbroo Hello: three on/off switches.
 *
 * @package KbrooHello
 */

defined( 'ABSPATH' ) || exit;

/**
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function kbroo_hello_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'kbroo_hello',
		array(
			'title'       => __( 'Kbroo Hello', 'kbroo-hello' ),
			'description' => __( 'Turn off the parts you build yourself in Kbroo Studio.', 'kbroo-hello' ),
			'priority'    => 30,
		)
	);

	$switches = array(
		'kbroo_hello_show_header' => __( 'Show the theme header', 'kbroo-hello' ),
		'kbroo_hello_show_footer' => __( 'Show the theme footer', 'kbroo-hello' ),
		'kbroo_hello_theme_css'   => __( 'Load the theme stylesheet', 'kbroo-hello' ),
	);

	foreach ( $switches as $id => $label ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => true,
				'sanitize_callback' => 'kbroo_hello_sanitize_checkbox',
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'type'    => 'checkbox',
				'section' => 'kbroo_hello',
				'label'   => $label,
			)
		);
	}
}
add_action( 'customize_register', 'kbroo_hello_customize_register' );

/**
 * @param mixed $value Checkbox value.
 */
function kbroo_hello_sanitize_checkbox( $value ) {
	return (bool) $value;
}
