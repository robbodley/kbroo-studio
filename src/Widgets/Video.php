<?php
/**
 * Video widget: YouTube, Vimeo or a self-hosted file. Settings match
 * Elementor's video widget. With an image overlay, nothing is loaded from
 * YouTube or Vimeo until the visitor presses play (frontend.js swaps the
 * overlay for the player).
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Frontend;
use KbrooStudio\Renderer;

defined( 'ABSPATH' ) || exit;

class Video extends Widget {

	/** Elementor's aspect_ratio values => CSS aspect-ratio. */
	const RATIOS = array(
		'169' => '16 / 9',
		'219' => '21 / 9',
		'43'  => '4 / 3',
		'32'  => '3 / 2',
		'11'  => '1 / 1',
		'916' => '9 / 16',
	);

	public function get_name(): string {
		return 'video';
	}

	public function get_title(): string {
		return __( 'Video', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-video-alt3';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$content  = array( 'section' => __( 'Video', 'kbroo-studio' ) );
		$overlay  = array( 'section' => __( 'Image overlay', 'kbroo-studio' ) );
		$style    = array(
			'tab'     => 'style',
			'section' => __( 'Video', 'kbroo-studio' ),
		);
		$youtube  = array( 'condition' => array( 'video_type' => 'youtube' ) );
		$vimeo    = array( 'condition' => array( 'video_type' => 'vimeo' ) );
		$hosted   = array( 'condition' => array( 'video_type' => 'hosted' ) );
		$embedded = array( 'condition' => array( 'video_type!' => 'hosted' ) );
		$switch   = function ( string $label, string $default = '' ) {
			return array(
				'type'    => 'switcher',
				'label'   => $label,
				'default' => $default,
			);
		};
		$with_overlay = array( 'condition' => array( 'show_image_overlay' => 'yes' ) );

		return array_merge(
			array(
				'video_type'     => $content + array(
					'type'    => 'select',
					'label'   => __( 'Source', 'kbroo-studio' ),
					'default' => 'youtube',
					'options' => array(
						'youtube' => 'YouTube',
						'vimeo'   => 'Vimeo',
						'hosted'  => __( 'Self hosted', 'kbroo-studio' ),
					),
				),
				'youtube_url'    => $content + $youtube + array(
					'type'    => 'text',
					'label'   => __( 'YouTube link', 'kbroo-studio' ),
					'default' => 'https://www.youtube.com/watch?v=XHOmBV4js_E',
				),
				'vimeo_url'      => $content + $vimeo + array(
					'type'    => 'text',
					'label'   => __( 'Vimeo link', 'kbroo-studio' ),
					'default' => 'https://vimeo.com/235215203',
				),
				'insert_url'     => $content + $hosted + $switch( __( 'Use an address instead', 'kbroo-studio' ) ),
				'hosted_url'     => $content + array(
					'type'       => 'media',
					'media_type' => 'video',
					'label'      => __( 'Video file', 'kbroo-studio' ),
					'condition'  => array(
						'video_type'  => 'hosted',
						'insert_url!' => 'yes',
					),
				),
				'external_url'   => $content + array(
					'type'      => 'url',
					'label'     => __( 'Video address', 'kbroo-studio' ),
					'condition' => array(
						'video_type' => 'hosted',
						'insert_url' => 'yes',
					),
				),
				'start'          => $content + array(
					'type'  => 'number',
					'label' => __( 'Start at (seconds)', 'kbroo-studio' ),
					'min'   => 0,
				),
				'end'            => $content + array(
					'type'      => 'number',
					'label'     => __( 'End at (seconds)', 'kbroo-studio' ),
					'min'       => 0,
					'condition' => array( 'video_type!' => 'vimeo' ),
				),
				'autoplay'       => $content + $switch( __( 'Autoplay', 'kbroo-studio' ) ),
				'play_on_mobile' => $content + array( 'condition' => array( 'autoplay' => 'yes' ) ) + $switch( __( 'Play on mobile', 'kbroo-studio' ) ),
				'mute'           => $content + $switch( __( 'Mute', 'kbroo-studio' ) ),
				'loop'           => $content + $switch( __( 'Loop', 'kbroo-studio' ) ),
				'controls'       => $content + array( 'condition' => array( 'video_type!' => 'vimeo' ) ) + $switch( __( 'Player controls', 'kbroo-studio' ), 'yes' ),
				'rel'            => $content + $youtube + array(
					'type'    => 'select',
					'label'   => __( 'Suggested videos at the end', 'kbroo-studio' ),
					'default' => '',
					'options' => array(
						''    => __( 'From the same channel', 'kbroo-studio' ),
						'yes' => __( 'Any video', 'kbroo-studio' ),
					),
				),
				'yt_privacy'     => $content + $youtube + $switch( __( 'Privacy mode (no cookies until play)', 'kbroo-studio' ), 'yes' ),
				'vimeo_title'    => $content + $vimeo + $switch( __( 'Show title', 'kbroo-studio' ), 'yes' ),
				'vimeo_portrait' => $content + $vimeo + $switch( __( 'Show profile picture', 'kbroo-studio' ), 'yes' ),
				'vimeo_byline'   => $content + $vimeo + $switch( __( 'Show byline', 'kbroo-studio' ), 'yes' ),
				'color'          => $content + $vimeo + array(
					'type'    => 'color',
					'label'   => __( 'Controls colour', 'kbroo-studio' ),
					'default' => '',
				),
				'download_button' => $content + $hosted + $switch( __( 'Download button', 'kbroo-studio' ) ),
				'preload'        => $content + $hosted + array(
					'type'    => 'select',
					'label'   => __( 'Preload', 'kbroo-studio' ),
					'default' => 'metadata',
					'options' => array(
						'metadata' => __( 'Details only', 'kbroo-studio' ),
						'auto'     => __( 'Whole video', 'kbroo-studio' ),
						'none'     => __( 'Nothing', 'kbroo-studio' ),
					),
				),
				'poster'         => $content + $hosted + array(
					'type'  => 'media',
					'label' => __( 'Poster image', 'kbroo-studio' ),
				),
				'aspect_ratio'   => $content + array(
					'type'                 => 'select',
					'label'                => __( 'Aspect ratio', 'kbroo-studio' ),
					'default'              => '169',
					'options'              => array(
						'169' => '16:9',
						'219' => '21:9',
						'43'  => '4:3',
						'32'  => '3:2',
						'11'  => '1:1',
						'916' => '9:16',
					),
					'selectors_dictionary' => self::RATIOS,
					'selectors'            => array( '{{WRAPPER}} .kbroo-video' => 'aspect-ratio: {{VALUE}};' ),
				),

				'show_image_overlay' => $overlay + $switch( __( 'Image overlay', 'kbroo-studio' ) ),
				'image_overlay'      => $overlay + $with_overlay + array(
					'type'  => 'media',
					'label' => __( 'Image', 'kbroo-studio' ),
				),
				'show_play_icon'     => $overlay + $with_overlay + $switch( __( 'Play icon', 'kbroo-studio' ), 'yes' ),

				'play_icon_color' => $style + array(
					'type'      => 'color',
					'label'     => __( 'Play icon colour', 'kbroo-studio' ),
					'default'   => '',
					'selectors' => array( '{{WRAPPER}} .kbroo-video__play' => 'color: {{VALUE}};' ),
				),
				'play_icon_size'  => $style + array(
					'type'      => 'slider',
					'label'     => __( 'Play icon size', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-video__play' => 'font-size: {{SIZE}}{{UNIT}};' ),
				),
			),
			ControlGroups::css_filters( '{{WRAPPER}} .kbroo-video', $style )
		);
	}

	/**
	 * The player: an iframe address for YouTube and Vimeo, or a file.
	 *
	 * @return array{ type: string, src: string }|null
	 */
	public static function player( array $settings, bool $autoplay ): ?array {
		$type  = $settings['video_type'];
		$start = max( 0, (int) $settings['start'] );
		$end   = max( 0, (int) $settings['end'] );
		$on    = function ( string $key ) use ( $settings ) {
			return 'yes' === $settings[ $key ];
		};

		if ( 'youtube' === $type || 'vimeo' === $type ) {
			$source = Renderer::video_source( (string) $settings[ $type . '_url' ] );
			if ( ! $source || $source['type'] !== $type ) {
				return null;
			}
			$id = $source['id'];
			if ( 'youtube' === $type ) {
				$args = array(
					'autoplay'    => $autoplay ? 1 : 0,
					'mute'        => $on( 'mute' ) ? 1 : 0,
					'controls'    => $on( 'controls' ) ? 1 : 0,
					'rel'         => $on( 'rel' ) ? 1 : 0,
					'playsinline' => 1,
				);
				if ( $on( 'loop' ) ) {
					$args['loop']     = 1;
					$args['playlist'] = $id;
				}
				if ( $start ) {
					$args['start'] = $start;
				}
				if ( $end ) {
					$args['end'] = $end;
				}
				$host = $on( 'yt_privacy' ) ? 'https://www.youtube-nocookie.com' : 'https://www.youtube.com';
				return array(
					'type' => 'iframe',
					'src'  => add_query_arg( $args, $host . '/embed/' . $id ),
				);
			}
			$args  = array(
				'autoplay'    => $autoplay ? 1 : 0,
				'muted'       => $on( 'mute' ) ? 1 : 0,
				'loop'        => $on( 'loop' ) ? 1 : 0,
				'title'       => $on( 'vimeo_title' ) ? 1 : 0,
				'portrait'    => $on( 'vimeo_portrait' ) ? 1 : 0,
				'byline'      => $on( 'vimeo_byline' ) ? 1 : 0,
				'playsinline' => 1,
				'dnt'         => 1,
			);
			$color = ltrim( (string) $settings['color'], '#' );
			if ( preg_match( '/^[0-9a-f]{6}$/i', $color ) ) {
				$args['color'] = $color;
			}
			return array(
				'type' => 'iframe',
				'src'  => add_query_arg( $args, 'https://player.vimeo.com/video/' . $id ) . ( $start ? '#t=' . $start . 's' : '' ),
			);
		}

		$url = 'yes' === $settings['insert_url']
			? (string) ( $settings['external_url']['url'] ?? '' )
			: (string) ( $settings['hosted_url']['url'] ?? '' );
		if ( '' === $url ) {
			return null;
		}
		if ( $start || $end ) {
			$url .= '#t=' . $start . ( $end ? ',' . $end : '' );
		}
		return array(
			'type' => 'file',
			'src'  => $url,
		);
	}

	/**
	 * Player HTML. $autoplay is true when the visitor pressed play on the
	 * overlay, or the video autoplays.
	 */
	public static function player_html( array $settings, array $player, bool $autoplay, string $title ): string {
		if ( 'iframe' === $player['type'] ) {
			return sprintf(
				'<iframe class="kbroo-video__player" src="%1$s" title="%2$s" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe>',
				esc_url( $player['src'] ),
				esc_attr( $title )
			);
		}
		$attributes = array( 'playsinline' );
		foreach ( array( 'controls', 'loop' ) as $flag ) {
			if ( 'yes' === $settings[ $flag ] ) {
				$attributes[] = $flag;
			}
		}
		if ( 'yes' === $settings['mute'] || ( $autoplay && 'yes' === $settings['autoplay'] ) ) {
			$attributes[] = 'muted'; // Browsers only autoplay muted video.
		}
		if ( $autoplay ) {
			$attributes[] = 'autoplay';
		}
		if ( 'yes' !== $settings['download_button'] ) {
			$attributes[] = 'controlsList="nodownload"';
		}
		$preload      = in_array( $settings['preload'], array( 'metadata', 'auto', 'none' ), true ) ? $settings['preload'] : 'metadata';
		$attributes[] = 'preload="' . $preload . '"';
		$poster       = (string) ( $settings['poster']['url'] ?? '' );
		if ( '' !== $poster ) {
			$attributes[] = 'poster="' . esc_url( $poster ) . '"';
		}
		return '<video class="kbroo-video__player" src="' . esc_url( $player['src'] ) . '" ' . implode( ' ', $attributes ) . '></video>';
	}

	public function render( array $settings ): string {
		// No autoplay in the editor's preview.
		$preview  = '' === $this->element_id;
		$autoplay = 'yes' === $settings['autoplay'] && ! $preview;
		$player   = self::player( $settings, $autoplay );
		if ( ! $player ) {
			return '';
		}
		$title   = __( 'Video', 'kbroo-studio' );
		$overlay = 'yes' === $settings['show_image_overlay'] ? (string) ( $settings['image_overlay']['url'] ?? '' ) : '';
		$classes = 'kbroo-video';
		if ( 'yes' === $settings['autoplay'] && 'yes' !== $settings['play_on_mobile'] ) {
			$classes .= ' kbroo-video--no-mobile-autoplay';
			Frontend::enqueue_script();
		}

		if ( '' === $overlay ) {
			return '<div class="' . $classes . '">' . self::player_html( $settings, $player, $autoplay, $title ) . '</div>';
		}

		// Overlay: the player is built when the visitor presses play.
		Frontend::enqueue_script();
		$playing = self::player( $settings, true );
		$id      = absint( $settings['image_overlay']['id'] ?? 0 );
		$image   = $id ? wp_get_attachment_image( $id, 'full', false, array( 'class' => 'kbroo-video__overlay-image', 'alt' => '' ) ) : '';
		if ( '' === $image ) {
			$image = '<img class="kbroo-video__overlay-image" src="' . esc_url( $overlay ) . '" alt="" loading="lazy" decoding="async" />';
		}
		$icon = 'yes' === $settings['show_play_icon']
			? '<span class="kbroo-video__play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5.5v13l10.5-6.5z" fill="currentColor"/></svg></span>'
			: '';
		return sprintf(
			'<div class="%1$s"><button type="button" class="kbroo-video__overlay" data-kbroo-video="%2$s" aria-label="%3$s">%4$s%5$s</button></div>',
			$classes,
			esc_attr( self::player_html( $settings, $playing, true, $title ) ),
			esc_attr__( 'Play video', 'kbroo-studio' ),
			$image,
			$icon
		);
	}
}
