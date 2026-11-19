<?php
/**
 * Text editor widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Text extends Widget {

	public function get_name(): string {
		return 'text';
	}

	public function get_title(): string {
		return __( 'Text', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-editor-paragraph';
	}

	public function get_controls(): array {
		return array(
			'content' => array(
				'type'    => 'textarea',
				'html'    => true,
				'label'   => __( 'Content', 'kbroo-studio' ),
				'default' => __( 'Write something here. Blank lines start a new paragraph, and basic HTML such as <strong>bold</strong> and links is allowed.', 'kbroo-studio' ),
			),
			'align'   => self::align_control(),
			'color'   => array(
				'type'    => 'color',
				'label'   => __( 'Text color', 'kbroo-studio' ),
				'default' => '',
			),
		);
	}

	public function render( array $settings ): string {
		return sprintf(
			'<div class="kbroo-text"%1$s>%2$s</div>',
			self::style_attr(
				array(
					'text-align' => $settings['align'],
					'color'      => (string) sanitize_hex_color( $settings['color'] ),
				)
			),
			wpautop( do_shortcode( wp_kses_post( $settings['content'] ) ) )
		);
	}
}
