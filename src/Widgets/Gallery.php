<?php
/**
 * Image gallery widget: a grid of images, optionally opening in a
 * lightbox. Settings match Elementor's image-gallery widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Frontend;

defined( 'ABSPATH' ) || exit;

class Gallery extends Widget {

	public function get_name(): string {
		return 'image-gallery';
	}

	public function get_title(): string {
		return __( 'Image gallery', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-format-gallery';
	}

	public function server_preview(): bool {
		return true;
	}

	/** Image size select, shared with the image carousel. */
	public static function size_control( array $base, string $default ): array {
		return $base + array(
			'type'    => 'select',
			'label'   => __( 'Image size', 'kbroo-studio' ),
			'default' => $default,
			'options' => array(
				'thumbnail'    => __( 'Thumbnail', 'kbroo-studio' ),
				'medium'       => __( 'Medium', 'kbroo-studio' ),
				'medium_large' => __( 'Medium large', 'kbroo-studio' ),
				'large'        => __( 'Large', 'kbroo-studio' ),
				'full'         => __( 'Full', 'kbroo-studio' ),
			),
		);
	}

	/** Lightbox select (Elementor's open_lightbox), shared with the carousel. */
	public static function lightbox_control( array $base ): array {
		return $base + array(
			'type'    => 'select',
			'label'   => __( 'Lightbox', 'kbroo-studio' ),
			'default' => 'default',
			'options' => array(
				'default' => __( 'Default (on)', 'kbroo-studio' ),
				'yes'     => __( 'On', 'kbroo-studio' ),
				'no'      => __( 'Off', 'kbroo-studio' ),
			),
		);
	}

	/**
	 * One image, linked as asked.
	 *
	 * @param array  $image    { id, url }.
	 * @param string $link     'file', 'attachment', 'custom' or 'none'.
	 * @param string $lightbox Lightbox group name, or '' for none.
	 * @param string $caption  Caption text ('' for none).
	 * @param string $custom   Link attributes for 'custom'.
	 */
	public static function image( array $image, string $size, string $link, string $lightbox, string $caption, string $custom = '' ): string {
		$id   = absint( $image['id'] ?? 0 );
		$url  = (string) ( $image['url'] ?? '' );
		$size = in_array( $size, Image::SIZES, true ) ? $size : 'medium';
		$img  = $id ? wp_get_attachment_image( $id, $size ) : '';
		if ( '' === $img ) {
			if ( '' === $url ) {
				return '';
			}
			$img = '<img src="' . esc_url( $url ) . '" alt="" loading="lazy" decoding="async" />';
		}

		$href = '';
		if ( 'file' === $link ) {
			$href = ' href="' . esc_url( $id ? (string) wp_get_attachment_url( $id ) : $url ) . '"';
			if ( '' !== $lightbox ) {
				$href .= ' data-kbroo-lightbox="' . esc_attr( $lightbox ) . '"';
				if ( '' !== $caption ) {
					$href .= ' data-kbroo-caption="' . esc_attr( $caption ) . '"';
				}
			}
		} elseif ( 'attachment' === $link && $id ) {
			$href = ' href="' . esc_url( (string) get_attachment_link( $id ) ) . '"';
		} elseif ( 'custom' === $link ) {
			$href = $custom;
		}
		if ( '' !== $href ) {
			$img = '<a class="kbroo-gallery__link"' . $href . '>' . $img . '</a>';
		}
		if ( '' !== $caption ) {
			$img .= '<figcaption class="kbroo-gallery__caption">' . esc_html( $caption ) . '</figcaption>';
		}
		return '<figure class="kbroo-gallery__item">' . $img . '</figure>';
	}

	/** Caption for an image: 'caption', 'title', 'description' or ''. */
	public static function caption( array $image, string $type ): string {
		$id = absint( $image['id'] ?? 0 );
		if ( ! $id || '' === $type ) {
			return '';
		}
		if ( 'caption' === $type ) {
			return (string) wp_get_attachment_caption( $id );
		}
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}
		return 'title' === $type ? (string) $post->post_title : wp_strip_all_tags( (string) $post->post_content );
	}

	protected function widget_controls(): array {
		$content = array( 'section' => __( 'Gallery', 'kbroo-studio' ) );
		$images  = array(
			'tab'     => 'style',
			'section' => __( 'Images', 'kbroo-studio' ),
		);
		$caption = array(
			'tab'       => 'style',
			'section'   => __( 'Caption', 'kbroo-studio' ),
			'condition' => array( 'gallery_display_caption' => '' ),
		);
		$columns = array();
		foreach ( range( 1, 10 ) as $n ) {
			$columns[ (string) $n ] = (string) $n;
		}

		return array_merge(
			array(
				'wp_gallery'      => $content + array(
					'type'  => 'gallery',
					'label' => __( 'Images', 'kbroo-studio' ),
				),
				'thumbnail_size'  => self::size_control( $content, 'medium' ),
				'gallery_columns' => $content + array(
					'type'           => 'select',
					'label'          => __( 'Columns', 'kbroo-studio' ),
					'default'        => '4',
					'default_mobile' => '2',
					'responsive'     => true,
					'options'        => $columns,
					'selectors'      => array( '{{WRAPPER}} .kbroo-gallery' => '--kbroo-gallery-columns: {{VALUE}};' ),
				),
				'gallery_link'    => $content + array(
					'type'    => 'select',
					'label'   => __( 'Link', 'kbroo-studio' ),
					'default' => 'file',
					'options' => array(
						'file'       => __( 'Image file', 'kbroo-studio' ),
						'attachment' => __( 'Attachment page', 'kbroo-studio' ),
						'none'       => __( 'None', 'kbroo-studio' ),
					),
				),
				'open_lightbox'   => self::lightbox_control( $content + array( 'condition' => array( 'gallery_link' => 'file' ) ) ),
				'gallery_rand'    => $content + array(
					'type'    => 'select',
					'label'   => __( 'Order', 'kbroo-studio' ),
					'default' => '',
					'options' => array(
						''     => __( 'As chosen', 'kbroo-studio' ),
						'rand' => __( 'Random', 'kbroo-studio' ),
					),
				),

				'image_spacing_custom' => $images + array(
					'type'       => 'slider',
					'label'      => __( 'Spacing', 'kbroo-studio' ),
					'units'      => array( 'px' ),
					'responsive' => true,
					'selectors'  => array( '{{WRAPPER}} .kbroo-gallery' => '--kbroo-gallery-gap: {{SIZE}}{{UNIT}};' ),
				),
				'image_ratio'          => $images + array(
					'type'                 => 'select',
					'label'                => __( 'Crop to', 'kbroo-studio' ),
					'default'              => '',
					'options'              => array(
						''     => __( 'Original shape', 'kbroo-studio' ),
						'1-1'  => __( 'Square', 'kbroo-studio' ),
						'4-3'  => '4:3',
						'3-2'  => '3:2',
						'16-9' => '16:9',
						'3-4'  => __( '3:4 (portrait)', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'1-1'  => '1 / 1',
						'4-3'  => '4 / 3',
						'3-2'  => '3 / 2',
						'16-9' => '16 / 9',
						'3-4'  => '3 / 4',
					),
					'selectors'            => array( '{{WRAPPER}} .kbroo-gallery img' => 'aspect-ratio: {{VALUE}}; object-fit: cover;' ),
				),
			),
			ControlGroups::border( '{{WRAPPER}} .kbroo-gallery img', $images, 'image_border' ),
			array(
				'gallery_display_caption' => array(
					'tab'     => 'style',
					'section' => __( 'Caption', 'kbroo-studio' ),
					'type'    => 'select',
					'label'   => __( 'Show captions', 'kbroo-studio' ),
					'default' => 'none',
					'options' => array(
						'none' => __( 'No', 'kbroo-studio' ),
						''     => __( 'Yes, from the Media Library', 'kbroo-studio' ),
					),
				),
				'align'                   => ControlGroups::align( '{{WRAPPER}} .kbroo-gallery__caption', $caption ),
				'image_caption_color'     => $caption + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-gallery__caption' => 'color: {{VALUE}};' ),
				),
				'caption_space'           => $caption + array(
					'type'      => 'slider',
					'label'     => __( 'Space above', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-gallery__caption' => 'margin-top: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'typography', '{{WRAPPER}} .kbroo-gallery__caption', $caption )
		);
	}

	public function render( array $settings ): string {
		$images = is_array( $settings['wp_gallery'] ) ? $settings['wp_gallery'] : array();
		if ( 'rand' === $settings['gallery_rand'] ) {
			shuffle( $images );
		}
		$lightbox = 'file' === $settings['gallery_link'] && 'no' !== $settings['open_lightbox']
			? 'gallery-' . ( '' !== $this->element_id ? $this->element_id : wp_rand() )
			: '';
		$captions = '' === $settings['gallery_display_caption'];
		$html     = '';
		foreach ( $images as $image ) {
			$html .= self::image( (array) $image, (string) $settings['thumbnail_size'], (string) $settings['gallery_link'], $lightbox, $captions ? self::caption( (array) $image, 'caption' ) : '' );
		}
		if ( '' === $html ) {
			return '';
		}
		if ( '' !== $lightbox ) {
			Frontend::enqueue_script();
		}
		return '<div class="kbroo-gallery">' . $html . '</div>';
	}
}
