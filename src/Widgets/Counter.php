<?php
/**
 * Counter widget: a number that counts up from a start to an end number
 * when it scrolls into view, with an optional prefix, suffix and title.
 * Settings match Elementor's counter widget.
 *
 * The page shows the end number, so it reads right without scripts;
 * frontend.js counts up to it.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Frontend;

defined( 'ABSPATH' ) || exit;

class Counter extends Widget {

	const TITLE_TAGS = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );

	/** Thousands separators by Elementor's setting value ('' is a comma). */
	const SEPARATORS = array(
		''  => ',',
		'.' => '.',
		' ' => ' ',
		'_' => '_',
		"'" => "'",
	);

	const TITLE_POSITIONS = array( 'before', 'after', 'start', 'end' );

	public function get_name(): string {
		return 'counter';
	}

	public function get_title(): string {
		return __( 'Counter', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-performance';
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Counter', 'kbroo-studio' ) );
		$number  = array(
			'tab'     => 'style',
			'section' => __( 'Number', 'kbroo-studio' ),
		);
		$title   = array(
			'tab'     => 'style',
			'section' => __( 'Title', 'kbroo-studio' ),
		);

		return array_merge(
			array(
				'starting_number'         => $content + array(
					'type'    => 'number',
					'label'   => __( 'Starting number', 'kbroo-studio' ),
					'default' => 0,
				),
				'ending_number'           => $content + array(
					'type'    => 'number',
					'label'   => __( 'Ending number', 'kbroo-studio' ),
					'default' => 100,
				),
				'prefix'                  => $content + array(
					'type'    => 'text',
					'label'   => __( 'Number prefix', 'kbroo-studio' ),
					'default' => '',
				),
				'suffix'                  => $content + array(
					'type'    => 'text',
					'label'   => __( 'Number suffix', 'kbroo-studio' ),
					'default' => '',
				),
				'duration'                => $content + array(
					'type'    => 'number',
					'label'   => __( 'Animation duration (ms)', 'kbroo-studio' ),
					'default' => 2000,
					'min'     => 0,
					'max'     => 60000,
				),
				'thousand_separator'      => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Thousands separator', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'thousand_separator_char' => $content + array(
					'type'      => 'select',
					'label'     => __( 'Separator', 'kbroo-studio' ),
					'default'   => '',
					'condition' => array( 'thousand_separator' => 'yes' ),
					'options'   => array(
						''  => __( 'Comma', 'kbroo-studio' ),
						'.' => __( 'Dot', 'kbroo-studio' ),
						' ' => __( 'Space', 'kbroo-studio' ),
						'_' => __( 'Underline', 'kbroo-studio' ),
						"'" => __( 'Apostrophe', 'kbroo-studio' ),
					),
				),
				'title'                   => $content + array(
					'type'    => 'text',
					'label'   => __( 'Title', 'kbroo-studio' ),
					'default' => __( 'Cool Number', 'kbroo-studio' ),
				),
				'title_tag'               => $content + array(
					'type'    => 'select',
					'label'   => __( 'Title HTML tag', 'kbroo-studio' ),
					'default' => 'div',
					'options' => array_combine( self::TITLE_TAGS, array_map( 'strtoupper', self::TITLE_TAGS ) ),
				),
				'title_position'          => $content + array(
					'type'    => 'select',
					'label'   => __( 'Title position', 'kbroo-studio' ),
					'default' => 'after',
					'options' => array(
						'before' => __( 'Above the number', 'kbroo-studio' ),
						'after'  => __( 'Below the number', 'kbroo-studio' ),
						'start'  => __( 'Before the number', 'kbroo-studio' ),
						'end'    => __( 'After the number', 'kbroo-studio' ),
					),
				),
				'number_color'            => $number + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-counter__number-wrapper' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography_number', '{{WRAPPER}} .kbroo-counter__number-wrapper', $number ),
			array(
				'title_color' => $title + array(
					'type'      => 'color',
					'label'     => __( 'Text color', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-counter__title' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'typography_title', '{{WRAPPER}} .kbroo-counter__title', $title )
		);
	}

	/** Decimal places a number is written with. */
	private static function decimals( $value ): int {
		$text = (string) $value;
		$dot  = strpos( $text, '.' );
		return false === $dot ? 0 : min( 6, strlen( rtrim( substr( $text, $dot + 1 ), '0' ) ) );
	}

	public function render( array $settings ): string {
		$from      = is_numeric( $settings['starting_number'] ) ? 0 + $settings['starting_number'] : 0;
		$to        = is_numeric( $settings['ending_number'] ) ? 0 + $settings['ending_number'] : 100;
		$decimals  = max( self::decimals( $from ), self::decimals( $to ) );
		$separator = 'yes' === $settings['thousand_separator'] ? ( self::SEPARATORS[ (string) $settings['thousand_separator_char'] ] ?? ',' ) : '';
		$options   = array(
			'from'      => $from,
			'to'        => $to,
			'duration'  => max( 0, (int) $settings['duration'] ),
			'separator' => $separator,
			'decimals'  => $decimals,
		);
		Frontend::enqueue_script();

		$tag      = in_array( $settings['title_tag'], self::TITLE_TAGS, true ) ? $settings['title_tag'] : 'div';
		$position = in_array( $settings['title_position'], self::TITLE_POSITIONS, true ) ? $settings['title_position'] : 'after';
		$title    = '' !== trim( (string) $settings['title'] )
			? sprintf( '<%1$s class="kbroo-counter__title">%2$s</%1$s>', $tag, esc_html( $settings['title'] ) )
			: '';

		return sprintf(
			'<div class="kbroo-counter kbroo-counter--title-%1$s"><div class="kbroo-counter__number-wrapper"><span class="kbroo-counter__prefix">%2$s</span><span class="kbroo-counter__number" data-kbroo-counter="%3$s">%4$s</span><span class="kbroo-counter__suffix">%5$s</span></div>%6$s</div>',
			esc_attr( $position ),
			esc_html( $settings['prefix'] ),
			esc_attr( (string) wp_json_encode( $options ) ),
			esc_html( number_format( $to, $decimals, '.' === $separator ? ',' : '.', $separator ) ),
			esc_html( $settings['suffix'] ),
			$title
		);
	}
}
