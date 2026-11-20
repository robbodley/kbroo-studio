<?php
/**
 * Post info: the author, date, time, comments, categories or tags of the
 * post being viewed, as an icon list. Settings match Elementor Pro's Post
 * Info widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\Icons;

defined( 'ABSPATH' ) || exit;

class PostInfo extends IconList {

	/** Icons each kind of item shows unless another is chosen. */
	const DEFAULT_ICONS = array(
		'author'   => 'far fa-circle-user',
		'date'     => 'fas fa-calendar',
		'time'     => 'far fa-clock',
		'comments' => 'far fa-comment-dots',
		'terms'    => 'fas fa-tags',
		'custom'   => 'fas fa-info',
	);

	public function get_name(): string {
		return 'post-info';
	}

	public function get_title(): string {
		return __( 'Post info', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-info-outline';
	}

	protected function widget_controls(): array {
		$controls = parent::widget_controls();
		$item     = function ( string $id, string $type ) {
			return array(
				'_id'  => $id,
				'type' => $type,
			);
		};
		$controls['view']['default']          = 'inline';
		$controls['view']['section']          = __( 'Post info', 'kbroo-studio' );
		$controls['link_click']['section']    = __( 'Post info', 'kbroo-studio' );
		$controls['icon_list']['section']     = __( 'Post info', 'kbroo-studio' );
		$controls['icon_list']['title_field'] = 'type';
		$controls['icon_list']['default']     = array(
			$item( 'b2d4f01', 'author' ),
			$item( 'b2d4f02', 'date' ),
			$item( 'b2d4f03', 'time' ),
			$item( 'b2d4f04', 'comments' ),
		);
		$custom_date = array( 'date_format' => 'custom' );
		$controls['icon_list']['fields'] = array(
			'type'               => array(
				'type'    => 'select',
				'label'   => __( 'Show', 'kbroo-studio' ),
				'default' => 'date',
				'options' => array(
					'author'   => __( 'Author', 'kbroo-studio' ),
					'date'     => __( 'Date', 'kbroo-studio' ),
					'time'     => __( 'Time', 'kbroo-studio' ),
					'comments' => __( 'Comments', 'kbroo-studio' ),
					'terms'    => __( 'Categories or tags', 'kbroo-studio' ),
					'custom'   => __( 'Custom text', 'kbroo-studio' ),
				),
			),
			'date_format'        => array(
				'type'      => 'select',
				'label'     => __( 'Date format', 'kbroo-studio' ),
				'default'   => 'default',
				'options'   => array(
					'default' => __( 'Site default', 'kbroo-studio' ),
					'0'       => 'March 6, 2018 (F j, Y)',
					'1'       => '2018-03-06 (Y-m-d)',
					'2'       => '03/06/2018 (m/d/Y)',
					'3'       => '06/03/2018 (d/m/Y)',
					'custom'  => __( 'Custom', 'kbroo-studio' ),
				),
				'condition' => array( 'type' => 'date' ),
			),
			'custom_date_format' => array(
				'type'        => 'text',
				'label'       => __( 'Custom date format', 'kbroo-studio' ),
				'default'     => 'F j, Y',
				'description' => __( 'PHP date format, as in Settings > General.', 'kbroo-studio' ),
				'condition'   => array( 'type' => 'date' ) + $custom_date,
			),
			'time_format'        => array(
				'type'      => 'select',
				'label'     => __( 'Time format', 'kbroo-studio' ),
				'default'   => 'default',
				'options'   => array(
					'default' => __( 'Site default', 'kbroo-studio' ),
					'0'       => '3:31 pm (g:i a)',
					'1'       => '3:31 PM (g:i A)',
					'2'       => '15:31 (H:i)',
					'custom'  => __( 'Custom', 'kbroo-studio' ),
				),
				'condition' => array( 'type' => 'time' ),
			),
			'custom_time_format' => array(
				'type'      => 'text',
				'label'     => __( 'Custom time format', 'kbroo-studio' ),
				'default'   => 'g:i a',
				'condition' => array(
					'type'        => 'time',
					'time_format' => 'custom',
				),
			),
			'taxonomy'           => array(
				'type'      => 'select',
				'label'     => __( 'Taxonomy', 'kbroo-studio' ),
				'default'   => 'category',
				'options'   => array(
					'category' => __( 'Categories', 'kbroo-studio' ),
					'post_tag' => __( 'Tags', 'kbroo-studio' ),
				),
				'condition' => array( 'type' => 'terms' ),
			),
			'text_prefix'        => array(
				'type'    => 'text',
				'label'   => __( 'Before', 'kbroo-studio' ),
				'default' => '',
			),
			'string_no_comments' => array(
				'type'      => 'text',
				'label'     => __( 'No comments', 'kbroo-studio' ),
				'default'   => __( 'No comments', 'kbroo-studio' ),
				'condition' => array( 'type' => 'comments' ),
			),
			'string_one_comment' => array(
				'type'      => 'text',
				'label'     => __( 'One comment', 'kbroo-studio' ),
				'default'   => __( 'One comment', 'kbroo-studio' ),
				'condition' => array( 'type' => 'comments' ),
			),
			'string_comments'    => array(
				'type'        => 'text',
				'label'       => __( 'Comments', 'kbroo-studio' ),
				'default'     => __( '%s comments', 'kbroo-studio' ),
				'description' => __( '%s is the number.', 'kbroo-studio' ),
				'condition'   => array( 'type' => 'comments' ),
			),
			'custom_text'        => array(
				'type'      => 'text',
				'label'     => __( 'Text', 'kbroo-studio' ),
				'default'   => '',
				'condition' => array( 'type' => 'custom' ),
			),
			'custom_url'         => array(
				'type'      => 'url',
				'label'     => __( 'Link', 'kbroo-studio' ),
				'condition' => array( 'type' => 'custom' ),
			),
			'link'               => array(
				'type'      => 'switcher',
				'label'     => __( 'Link', 'kbroo-studio' ),
				'default'   => 'yes',
				'condition' => array( 'type!' => array( 'time', 'custom' ) ),
			),
			'show_avatar'        => array(
				'type'      => 'switcher',
				'label'     => __( 'Show the author\'s picture instead of the icon', 'kbroo-studio' ),
				'default'   => '',
				'condition' => array( 'type' => 'author' ),
			),
			'show_icon'          => array(
				'type'    => 'select',
				'label'   => __( 'Icon', 'kbroo-studio' ),
				'default' => 'default',
				'options' => array(
					'default' => __( 'Default', 'kbroo-studio' ),
					'custom'  => __( 'Choose', 'kbroo-studio' ),
					'none'    => __( 'None', 'kbroo-studio' ),
				),
			),
			'selected_icon'      => array(
				'type'      => 'icons',
				'label'     => __( 'Choose icon', 'kbroo-studio' ),
				'default'   => array(
					'value'   => 'fas fa-circle',
					'library' => 'fa-solid',
				),
				'condition' => array( 'show_icon' => 'custom' ),
			),
		);
		return $controls;
	}

	/** Date and time formats Elementor numbers. */
	const DATE_FORMATS = array( 'F j, Y', 'Y-m-d', 'm/d/Y', 'd/m/Y' );
	const TIME_FORMATS = array( 'g:i a', 'g:i A', 'H:i' );

	public function render( array $settings ): string {
		$items = is_array( $settings['icon_list'] ) ? $settings['icon_list'] : array();
		return DynamicPost::with(
			function ( $post ) use ( $items, $settings ) {
				$html = '';
				foreach ( $items as $item ) {
					$html .= $this->item( $item, $post );
				}
				if ( '' === $html ) {
					return '';
				}
				$inline  = 'inline' === $settings['view'];
				$classes = array( 'kbroo-icon-list', 'kbroo-post-info', 'kbroo-icon-list--' . ( $inline ? 'inline' : 'traditional' ) );
				if ( 'yes' === $settings['divider'] ) {
					$classes[] = 'kbroo-icon-list--divider';
				}
				if ( ! $inline && 'inline' === $settings['link_click'] ) {
					$classes[] = 'kbroo-icon-list--link-inline';
				}
				return '<ul class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $html . '</ul>';
			}
		);
	}

	/**
	 * One item, or '' when it has nothing to show (no tags, say).
	 *
	 * @param array    $item Repeater item.
	 * @param \WP_Post $post Post shown.
	 */
	private function item( array $item, $post ): string {
		$type = (string) ( $item['type'] ?? 'date' );
		$link = 'yes' === ( $item['link'] ?? 'yes' );
		$url  = '';
		$text = '';
		$icon = '';
		switch ( $type ) {
			case 'author':
				$author = (int) $post->post_author;
				$text   = esc_html( get_the_author_meta( 'display_name', $author ) );
				$url    = $link ? get_author_posts_url( $author ) : '';
				if ( 'yes' === ( $item['show_avatar'] ?? '' ) ) {
					$icon = '<span class="kbroo-icon-list__icon kbroo-post-info__avatar">' . get_avatar( $author, 48 ) . '</span>';
				}
				break;
			case 'date':
				$format = self::format( (string) ( $item['date_format'] ?? 'default' ), (string) ( $item['custom_date_format'] ?? '' ), self::DATE_FORMATS );
				$text   = esc_html( get_the_date( $format, $post ) );
				$url    = $link ? get_day_link( (int) get_the_date( 'Y', $post ), (int) get_the_date( 'm', $post ), (int) get_the_date( 'j', $post ) ) : '';
				break;
			case 'time':
				$format = self::format( (string) ( $item['time_format'] ?? 'default' ), (string) ( $item['custom_time_format'] ?? '' ), self::TIME_FORMATS );
				$text   = esc_html( get_the_time( '' !== $format ? $format : get_option( 'time_format' ), $post ) );
				break;
			case 'comments':
				if ( ! comments_open( $post ) && ! get_comments_number( $post ) ) {
					return '';
				}
				$count = (int) get_comments_number( $post );
				$text  = 0 === $count ? (string) ( $item['string_no_comments'] ?? '' ) : ( 1 === $count ? (string) ( $item['string_one_comment'] ?? '' ) : str_replace( '%s', number_format_i18n( $count ), (string) ( $item['string_comments'] ?? '' ) ) );
				$text  = esc_html( $text );
				$url   = $link ? get_comments_link( $post ) : '';
				break;
			case 'terms':
				$taxonomy = in_array( $item['taxonomy'] ?? '', array( 'category', 'post_tag' ), true ) ? $item['taxonomy'] : 'category';
				$terms    = get_the_terms( $post, $taxonomy );
				if ( ! is_array( $terms ) || ! $terms ) {
					return '';
				}
				$names = array();
				foreach ( $terms as $term ) {
					$names[] = $link
						? '<a class="kbroo-post-info__term" href="' . esc_url( get_term_link( $term ) ) . '">' . esc_html( $term->name ) . '</a>'
						: '<span class="kbroo-post-info__term">' . esc_html( $term->name ) . '</span>';
				}
				$text = implode( ', ', $names );
				$link = false; // Each term links on its own.
				break;
			case 'custom':
				$text = esc_html( (string) ( $item['custom_text'] ?? '' ) );
				break;
			default:
				return '';
		}
		if ( '' === $text ) {
			return '';
		}
		if ( '' === $icon ) {
			$show = (string) ( $item['show_icon'] ?? 'default' );
			if ( 'custom' === $show ) {
				$svg = Icons::svg( $item['selected_icon'] ?? array() );
			} elseif ( 'none' === $show ) {
				$svg = '';
			} else {
				$svg = Icons::svg(
					array(
						'value'   => self::DEFAULT_ICONS[ $type ] ?? '',
						'library' => 0 === strpos( self::DEFAULT_ICONS[ $type ] ?? '', 'far' ) ? 'fa-regular' : 'fa-solid',
					)
				);
			}
			$icon = '' !== $svg ? '<span class="kbroo-icon-list__icon">' . $svg . '</span>' : '';
		}
		$prefix = (string) ( $item['text_prefix'] ?? '' );
		$inner  = $icon . '<span class="kbroo-icon-list__text kbroo-post-info__' . esc_attr( $type ) . '">'
			. ( '' !== $prefix ? '<span class="kbroo-post-info__prefix">' . esc_html( $prefix ) . '</span>' : '' )
			. $text . '</span>';
		if ( 'custom' === $type ) {
			$attributes = self::link_attributes( $item['custom_url'] ?? array() );
		} else {
			$attributes = $link && '' !== $url ? ' href="' . esc_url( $url ) . '"' : '';
		}
		$tag = '' !== $attributes ? 'a' : 'span';
		return sprintf(
			'<li class="kbroo-icon-list__item kbroo-repeater-item-%1$s"><%2$s class="kbroo-icon-list__link"%3$s>%4$s</%2$s></li>',
			esc_attr( (string) ( $item['_id'] ?? '' ) ),
			$tag,
			$attributes,
			$inner
		);
	}

	/**
	 * A date or time format from Elementor's numbered choices, a custom one,
	 * or '' for the site's own.
	 */
	private static function format( string $choice, string $custom, array $numbered ): string {
		if ( 'custom' === $choice ) {
			return $custom;
		}
		if ( is_numeric( $choice ) && isset( $numbered[ (int) $choice ] ) ) {
			return $numbered[ (int) $choice ];
		}
		return '';
	}
}
