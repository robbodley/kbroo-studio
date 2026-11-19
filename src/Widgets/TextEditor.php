<?php
/**
 * Text editor widget. Settings match Elementor's text-editor widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class TextEditor extends Widget {

	public function get_name(): string {
		return 'text-editor';
	}

	public function get_title(): string {
		return __( 'Text', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-editor-paragraph';
	}

	protected function widget_controls(): array {
		$text = '{{WRAPPER}} .kbroo-text';

		return array_merge(
			array(
				'editor'     => array(
					'type'    => 'textarea',
					'html'    => true,
					'label'   => __( 'Content', 'kbroo-studio' ),
					'section' => __( 'Text', 'kbroo-studio' ),
					'default' => __( 'Write something here. Blank lines start a new paragraph, and basic HTML such as <strong>bold</strong> and links is allowed.', 'kbroo-studio' ),
				),
				'align'      => ControlGroups::align(
					$text,
					array(
						'tab'     => 'style',
						'section' => __( 'Text', 'kbroo-studio' ),
					)
				),
				'text_color' => array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => __( 'Text', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $text => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography', $text )
		);
	}

	public function render( array $settings ): string {
		return '<div class="kbroo-text">' . wpautop( do_shortcode( wp_kses_post( $settings['editor'] ) ) ) . '</div>';
	}
}
