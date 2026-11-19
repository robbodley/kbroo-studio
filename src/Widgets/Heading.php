<?php
/**
 * Heading widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class Heading extends Widget {

	public function get_name(): string {
		return 'heading';
	}

	public function get_title(): string {
		return __( 'Heading', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-heading';
	}

	public function get_controls(): array {
		return array(
			'text'  => array(
				'type'    => 'text',
				'label'   => __( 'Title', 'kbroo-studio' ),
				'default' => __( 'Add your heading here', 'kbroo-studio' ),
			),
			'tag'   => array(
				'type'    => 'select',
				'label'   => __( 'HTML tag', 'kbroo-studio' ),
				'default' => 'h2',
				'options' => array(
					'h1'  => 'H1',
					'h2'  => 'H2',
					'h3'  => 'H3',
					'h4'  => 'H4',
					'h5'  => 'H5',
					'h6'  => 'H6',
					'div' => 'div',
					'p'   => 'p',
				),
			),
			'align' => self::align_control(),
			'color' => array(
				'type'    => 'color',
				'label'   => __( 'Text color', 'kbroo-studio' ),
				'default' => '',
			),
		);
	}

	public function render( array $settings ): string {
		$tags = array_keys( $this->get_controls()['tag']['options'] );
		$tag  = in_array( $settings['tag'], $tags, true ) ? $settings['tag'] : 'h2';

		return sprintf(
			'<%1$s class="kbroo-heading"%2$s>%3$s</%1$s>',
			$tag,
			self::style_attr(
				array(
					'text-align' => $settings['align'],
					'color'      => (string) sanitize_hex_color( $settings['color'] ),
				)
			),
			esc_html( $settings['text'] )
		);
	}
}
