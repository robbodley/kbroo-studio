<?php
/**
 * Author box: the picture, name and biography of the author of the post
 * being viewed, with a link to their posts. Settings match Elementor Pro's
 * Author Box widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;

defined( 'ABSPATH' ) || exit;

class AuthorBox extends Widget {

	public function get_name(): string {
		return 'author-box';
	}

	public function get_title(): string {
		return __( 'Author box', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-admin-users';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Author', 'kbroo-studio' ) );
		$image   = array(
			'tab'       => 'style',
			'section'   => __( 'Picture', 'kbroo-studio' ),
			'condition' => array( 'show_avatar' => 'yes' ),
		);
		$name    = array(
			'tab'       => 'style',
			'section'   => __( 'Name', 'kbroo-studio' ),
			'condition' => array( 'show_name' => 'yes' ),
		);
		$bio     = array(
			'tab'       => 'style',
			'section'   => __( 'Biography', 'kbroo-studio' ),
			'condition' => array( 'show_biography' => 'yes' ),
		);
		$button  = array(
			'tab'       => 'style',
			'section'   => __( 'Button', 'kbroo-studio' ),
			'condition' => array( 'show_link' => 'yes' ),
		);
		$box     = '{{WRAPPER}} .kbroo-author-box';
		return array_merge(
			array(
				'show_avatar'     => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Picture', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'show_name'       => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Name', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'author_name_tag' => $content + array(
					'type'      => 'select',
					'label'     => __( 'Name HTML tag', 'kbroo-studio' ),
					'default'   => 'h4',
					'options'   => array_combine( Heading::TAGS, array_map( 'strtoupper', Heading::TAGS ) ),
					'condition' => array( 'show_name' => 'yes' ),
				),
				'link_to'         => $content + array(
					'type'    => 'select',
					'label'   => __( 'Picture and name link to', 'kbroo-studio' ),
					'default' => '',
					'options' => array(
						''              => __( 'Nothing', 'kbroo-studio' ),
						'posts_archive' => __( 'The author\'s posts', 'kbroo-studio' ),
						'website'       => __( 'The author\'s website', 'kbroo-studio' ),
					),
				),
				'show_biography'  => $content + array(
					'type'        => 'switcher',
					'label'       => __( 'Biography', 'kbroo-studio' ),
					'default'     => 'yes',
					'description' => __( 'From the author\'s profile (Users > Profile > Biographical info).', 'kbroo-studio' ),
				),
				'show_link'       => $content + array(
					'type'    => 'switcher',
					'label'   => __( 'Button to their posts', 'kbroo-studio' ),
					'default' => '',
				),
				'link_text'       => $content + array(
					'type'      => 'text',
					'label'     => __( 'Button text', 'kbroo-studio' ),
					'default'   => __( 'All posts', 'kbroo-studio' ),
					'condition' => array( 'show_link' => 'yes' ),
				),
				'layout'          => $content + array(
					'type'    => 'select',
					'label'   => __( 'Picture position', 'kbroo-studio' ),
					'default' => 'left',
					'options' => array(
						'left'  => __( 'Left', 'kbroo-studio' ),
						'above' => __( 'Above', 'kbroo-studio' ),
						'right' => __( 'Right', 'kbroo-studio' ),
					),
				),
				'alignment'       => ControlGroups::align( $box, $content ),

				'image_size'          => $image + array(
					'type'       => 'slider',
					'label'      => __( 'Size', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( $box => '--kbroo-author-avatar: {{SIZE}}{{UNIT}};' ),
				),
				'image_gap'           => $image + array(
					'type'       => 'slider',
					'label'      => __( 'Gap', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( $box => '--kbroo-author-gap: {{SIZE}}{{UNIT}};' ),
				),
				'image_border_radius' => $image + array(
					'type'      => 'slider',
					'label'     => __( 'Corner radius', 'kbroo-studio' ),
					'units'     => array( '%', 'px' ),
					'selectors' => array( $box . ' .kbroo-author-box__avatar img' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				),

				'name_color'      => $name + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' .kbroo-author-box__name, ' . $box . ' .kbroo-author-box__name a' => 'color: {{VALUE}};' ),
				),
				'biography_color' => $bio + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' .kbroo-author-box__bio' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'name_typography', $box . ' .kbroo-author-box__name', $name ),
			ControlGroups::typography( 'biography_typography', $box . ' .kbroo-author-box__bio', $bio ),
			array(
				'button_text_color'       => $button + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' .kbroo-author-box__button' => 'color: {{VALUE}};' ),
				),
				'button_background_color' => $button + array(
					'type'      => 'color',
					'label'     => __( 'Background colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( $box . ' .kbroo-author-box__button' => 'background-color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'button_typography', $box . ' .kbroo-author-box__button', $button )
		);
	}

	public function render( array $settings ): string {
		return DynamicPost::with(
			function ( $post ) use ( $settings ) {
				$author = (int) $post->post_author;
				$user   = get_userdata( $author );
				if ( ! $user ) {
					return '';
				}
				$url = '';
				if ( 'posts_archive' === $settings['link_to'] ) {
					$url = get_author_posts_url( $author );
				} elseif ( 'website' === $settings['link_to'] ) {
					$url = (string) $user->user_url;
				}
				$open  = '' !== $url ? '<a href="' . esc_url( $url ) . '">' : '';
				$close = '' !== $url ? '</a>' : '';

				$html = '';
				if ( 'yes' === $settings['show_avatar'] ) {
					$html .= '<div class="kbroo-author-box__avatar">' . $open . get_avatar( $author, 300, '', $user->display_name ) . $close . '</div>';
				}
				$text = '';
				if ( 'yes' === $settings['show_name'] ) {
					$tag   = in_array( $settings['author_name_tag'], Heading::TAGS, true ) ? $settings['author_name_tag'] : 'h4';
					$text .= sprintf( '<%1$s class="kbroo-author-box__name">%2$s%3$s%4$s</%1$s>', $tag, $open, esc_html( $user->display_name ), $close );
				}
				if ( 'yes' === $settings['show_biography'] ) {
					$bio = get_the_author_meta( 'description', $author );
					if ( '' !== trim( $bio ) ) {
						$text .= '<div class="kbroo-author-box__bio">' . wpautop( wp_kses_post( $bio ) ) . '</div>';
					}
				}
				if ( 'yes' === $settings['show_link'] && '' !== $settings['link_text'] ) {
					$text .= '<a class="kbroo-author-box__button" href="' . esc_url( get_author_posts_url( $author ) ) . '">' . esc_html( $settings['link_text'] ) . '</a>';
				}
				$html  .= '' !== $text ? '<div class="kbroo-author-box__text">' . $text . '</div>' : '';
				$layout = in_array( $settings['layout'], array( 'left', 'above', 'right' ), true ) ? $settings['layout'] : 'left';
				return '' !== $html ? '<div class="kbroo-author-box kbroo-author-box--' . $layout . '">' . $html . '</div>' : '';
			}
		);
	}
}
