<?php
/**
 * Heading widget. Settings match Elementor's heading widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class Heading extends Widget {

	const TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/** Inline HTML a title may hold (Elementor titles often have <br> or <span>). */
	const INLINE_HTML = array(
		'br'     => array(),
		'span'   => array( 'class' => array() ),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'u'      => array(),
		'small'  => array(),
		'sup'    => array(),
		'sub'    => array(),
		'a'      => array(
			'href'   => array(),
			'target' => array(),
			'rel'    => array(),
		),
	);

	public function get_name(): string {
		return 'heading';
	}

	public function get_title(): string {
		return __( 'Heading', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-heading';
	}

	protected function widget_controls(): array {
		$title = '{{WRAPPER}} .kbroo-heading';

		return array_merge(
			array(
				'title'       => array(
					'type'    => 'textarea',
					'html'    => true,
					'rows'    => 2,
					'label'   => __( 'Title', 'kbroo-studio' ),
					'section' => __( 'Title', 'kbroo-studio' ),
					'default' => __( 'Add your heading here', 'kbroo-studio' ),
				),
				'header_size' => array(
					'type'    => 'select',
					'label'   => __( 'HTML tag', 'kbroo-studio' ),
					'section' => __( 'Title', 'kbroo-studio' ),
					'default' => 'h2',
					'options' => array_combine( self::TAGS, array_map( 'strtoupper', self::TAGS ) ),
				),
				'align'       => ControlGroups::align(
					$title,
					array(
						'tab'     => 'style',
						'section' => __( 'Title', 'kbroo-studio' ),
					)
				),
				'title_color' => array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'tab'       => 'style',
					'section'   => __( 'Title', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $title => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography', $title )
		);
	}

	public function render( array $settings ): string {
		$tag = in_array( $settings['header_size'], self::TAGS, true ) ? $settings['header_size'] : 'h2';

		return sprintf( '<%1$s class="kbroo-heading">%2$s</%1$s>', $tag, wp_kses( $settings['title'], self::INLINE_HTML ) );
	}
}
