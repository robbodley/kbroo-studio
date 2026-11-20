<?php
/**
 * Post navigation: links to the previous and next posts, with labels,
 * titles and arrows. Settings match Elementor Pro's Post Navigation widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class PostNavigation extends Widget {

	/** Arrow choices: Elementor's names => Font Awesome left and right icons. */
	const ARROWS = array(
		'fa fa-angle-left'          => array( 'fas fa-angle-left', 'fas fa-angle-right' ),
		'fa fa-angle-double-left'   => array( 'fas fa-angles-left', 'fas fa-angles-right' ),
		'fa fa-chevron-left'        => array( 'fas fa-chevron-left', 'fas fa-chevron-right' ),
		'fa fa-chevron-circle-left' => array( 'fas fa-circle-chevron-left', 'fas fa-circle-chevron-right' ),
		'fa fa-caret-left'          => array( 'fas fa-caret-left', 'fas fa-caret-right' ),
		'fa fa-arrow-left'          => array( 'fas fa-arrow-left', 'fas fa-arrow-right' ),
		'fa fa-long-arrow-left'     => array( 'fas fa-arrow-left-long', 'fas fa-arrow-right-long' ),
		'fa fa-arrow-circle-left'   => array( 'fas fa-circle-arrow-left', 'fas fa-circle-arrow-right' ),
	);

	public function get_name(): string {
		return 'post-navigation';
	}

	public function get_title(): string {
		return __( 'Post navigation', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-leftright';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Post navigation', 'kbroo-studio' ) );
		$label   = array(
			'tab'       => 'style',
			'section'   => __( 'Label', 'kbroo-studio' ),
			'condition' => array( 'show_label' => 'yes' ),
		);
		$title   = array(
			'tab'       => 'style',
			'section'   => __( 'Title', 'kbroo-studio' ),
			'condition' => array( 'show_title' => 'yes' ),
		);
		$arrow   = array(
			'tab'       => 'style',
			'section'   => __( 'Arrow', 'kbroo-studio' ),
			'condition' => array( 'show_arrow' => 'yes' ),
		);
		$line    = array(
			'tab'       => 'style',
			'section'   => __( 'Dividing line', 'kbroo-studio' ),
			'condition' => array( 'show_borderline' => 'yes' ),
		);
		$nav     = '{{WRAPPER}} .kbroo-post-navigation';
		$arrows  = array();
		foreach ( array_keys( self::ARROWS ) as $key ) {
			$arrows[ $key ] = ucwords( str_replace( array( 'fa fa-', '-left', '-' ), array( '', '', ' ' ), $key ) );
		}
		return array_merge(
			array(
				'show_label'      => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Label', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'prev_label'      => $content + array(
					'type'      => 'text',
					'label'     => __( 'Previous label', 'kbroo-studio' ),
					'default'   => __( 'Previous', 'kbroo-studio' ),
					'condition' => array( 'show_label' => 'yes' ),
				),
				'next_label'      => $content + array(
					'type'      => 'text',
					'label'     => __( 'Next label', 'kbroo-studio' ),
					'default'   => __( 'Next', 'kbroo-studio' ),
					'condition' => array( 'show_label' => 'yes' ),
				),
				'show_arrow'      => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Arrows', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'arrow'           => $content + array(
					'type'      => 'select',
					'label'     => __( 'Arrow', 'kbroo-studio' ),
					'default'   => 'fa fa-angle-left',
					'options'   => $arrows,
					'condition' => array( 'show_arrow' => 'yes' ),
				),
				'show_title'      => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Post title', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'show_borderline' => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Dividing line', 'kbroo-studio' ),
					'default' => '',
				),
				'in_same_term'    => $content + array(
					'type'        => 'switcher',
					'label'       => __( 'Only posts in the same category', 'kbroo-studio' ),
					'default'     => '',
				),

				'label_color'       => $label + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav . ' .kbroo-post-navigation__label' => 'color: {{VALUE}};' ),
				),
				'label_hover_color' => $label + array(
					'type'      => 'color',
					'label'     => __( 'Colour on hover', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav . ' a:hover .kbroo-post-navigation__label' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'label_typography', $nav . ' .kbroo-post-navigation__label', $label ),
			array(
				'title_color'       => $title + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav . ' .kbroo-post-navigation__title' => 'color: {{VALUE}};' ),
				),
				'title_hover_color' => $title + array(
					'type'      => 'color',
					'label'     => __( 'Colour on hover', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav . ' a:hover .kbroo-post-navigation__title' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'title_typography', $nav . ' .kbroo-post-navigation__title', $title ),
			array(
				'arrow_color'   => $arrow + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav . ' .kbroo-post-navigation__arrow' => 'color: {{VALUE}};' ),
				),
				'arrow_size'    => $arrow + array(
					'type'      => 'slider',
					'label'     => __( 'Size', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( $nav . ' .kbroo-post-navigation__arrow' => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
				'arrow_padding' => $arrow + array(
					'type'      => 'slider',
					'label'     => __( 'Gap', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( $nav => '--kbroo-post-nav-arrow-gap: {{SIZE}}{{UNIT}};' ),
				),
				'sep_color'     => $line + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $nav => '--kbroo-post-nav-line-color: {{VALUE}};' ),
				),
				'borders_width' => $line + array(
					'type'      => 'slider',
					'label'     => __( 'Weight', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( $nav => '--kbroo-post-nav-line-width: {{SIZE}}{{UNIT}};' ),
				),
			)
		);
	}

	public function render( array $settings ): string {
		return DynamicPost::with(
			function ( $post ) use ( $settings ) {
				$same   = 'yes' === $settings['in_same_term'];
				$prev   = get_adjacent_post( $same, '', true, 'category' );
				$next   = get_adjacent_post( $same, '', false, 'category' );
				$arrows = self::ARROWS[ $settings['arrow'] ] ?? self::ARROWS['fa fa-angle-left'];
				$html   = '<div class="kbroo-post-navigation__prev">' . ( $prev ? $this->link( $prev, 'prev', $settings, $arrows[0] ) : '' ) . '</div>';
				if ( 'yes' === $settings['show_borderline'] ) {
					$html .= '<div class="kbroo-post-navigation__separator" aria-hidden="true"></div>';
				}
				$html .= '<div class="kbroo-post-navigation__next">' . ( $next ? $this->link( $next, 'next', $settings, $arrows[1] ) : '' ) . '</div>';
				$class = 'kbroo-post-navigation' . ( 'yes' === $settings['show_borderline'] ? ' kbroo-post-navigation--line' : '' );
				return '<nav class="' . $class . '" aria-label="' . esc_attr__( 'Posts', 'kbroo-studio' ) . '">' . $html . '</nav>';
			}
		);
	}

	/**
	 * @param \WP_Post $post Post linked to.
	 */
	private function link( $post, string $rel, array $settings, string $arrow ): string {
		$text = '';
		if ( 'yes' === $settings['show_label'] ) {
			$text .= '<span class="kbroo-post-navigation__label">' . esc_html( 'prev' === $rel ? $settings['prev_label'] : $settings['next_label'] ) . '</span>';
		}
		if ( 'yes' === $settings['show_title'] ) {
			$text .= '<span class="kbroo-post-navigation__title">' . esc_html( get_the_title( $post ) ) . '</span>';
		}
		$icon = 'yes' === $settings['show_arrow'] ? '<span class="kbroo-post-navigation__arrow" aria-hidden="true">' . Icons::svg(
			array(
				'value'   => $arrow,
				'library' => 'fa-solid',
			)
		) . '</span>' : '';
		$text = '<span class="kbroo-post-navigation__text">' . $text . '</span>';
		return sprintf(
			'<a href="%s" rel="%s">%s</a>',
			esc_url( get_permalink( $post ) ),
			$rel,
			'prev' === $rel ? $icon . $text : $text . $icon
		);
	}
}
