<?php
/**
 * Renders a layout tree to front-end HTML and CSS.
 *
 * The editor canvas (assets/js/editor.js) produces the same markup and class
 * names so that what you see while editing matches the published page.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	/** @var Registry */
	private $widgets;

	/** @var array<string,string[]> CSS rules collected while rendering, by device. */
	private $rules = array();

	/** @var int Post being rendered. */
	private $post_id = 0;

	/** @var bool Whether any element has an entrance animation. */
	private $animated = false;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	public function render( array $layout, int $post_id = 0, bool $with_css = true ): string {
		// A widget can render another layout while this one renders (Post
		// content, a loop item grid): keep this one's state to come back to.
		$outer = array( $this->rules, $this->animated, $this->post_id );

		$this->rules    = array_fill_keys( Controls::DEVICES, array() );
		$this->animated = false;
		$this->post_id  = $post_id;

		$html = $this->render_children( (array) ( $layout['elements'] ?? array() ) );

		if ( ! defined( 'DONOTCACHEPAGE' ) && Visibility::layout_has_rules( (array) ( $layout['elements'] ?? array() ) ) ) {
			// Shown or hidden by date and time: ask page caches (WP Super
			// Cache, W3 Total Cache, WP Rocket...) not to keep a copy.
			define( 'DONOTCACHEPAGE', true );
		}

		if ( $this->animated ) {
			// Animated elements start hidden only once this has run, so the
			// page still shows everything if scripts never load.
			$html = '<script>document.documentElement.classList.add("kbroo-js");</script>' . $html;
			Frontend::enqueue_script();
		}

		$css = $with_css ? Css::stylesheet( $this->rules ) : '';
		list( $this->rules, $this->animated, $this->post_id ) = $outer;
		if ( '' !== $css ) {
			// Values are validated before they reach the CSS; this only
			// guarantees the style element cannot be closed early.
			$html = '<style class="kbroo-css">' . str_replace( '</', '<\/', $css ) . '</style>' . $html;
		}

		$html = sprintf(
			'<div class="kbroo kbroo-page alignfull" data-kbroo-post="%d">%s</div>',
			$post_id,
			$html
		);

		/**
		 * Filters the rendered HTML of a Kbroo Studio layout.
		 *
		 * @param string $html    Rendered HTML.
		 * @param array  $layout  Layout tree.
		 * @param int    $post_id Post ID.
		 */
		return (string) apply_filters( 'kbroo_studio_render', $html, $layout, $post_id );
	}

	/**
	 * Settings with defaults, after collecting the element's CSS.
	 */
	private function prepare( array $element, array $controls ): array {
		$settings = self::featured_backgrounds( $controls, Controls::with_defaults( $controls, $element['settings'] ?? array() ) );
		$rules    = Css::element_rules( $controls, $settings, Css::wrapper( $this->element_id( $element ) ) );
		foreach ( $rules as $device => $list ) {
			$this->rules[ $device ] = array_merge( $this->rules[ $device ], $list );
		}
		return $settings;
	}

	/**
	 * Backgrounds whose image source is the featured image: the post being
	 * shown (the viewed post, or the one a loop item is for) lends its
	 * featured image on every device. With none, the chosen image stays.
	 */
	private static function featured_backgrounds( array $controls, array $settings ): array {
		foreach ( $controls as $key => $control ) {
			if ( '_image_source' !== substr( (string) $key, -13 ) || 'featured' !== ( $settings[ $key ] ?? '' ) ) {
				continue;
			}
			$image = substr( (string) $key, 0, -7 ); // e.g. background_image.
			$post  = Widgets\DynamicPost::get();
			$id    = $post ? (int) get_post_thumbnail_id( $post ) : 0;
			$url   = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
			if ( '' === $url ) {
				continue;
			}
			$settings[ $image ] = array(
				'id'  => $id,
				'url' => $url,
			);
			foreach ( Controls::keys( $image, $controls[ $image ] ?? array() ) as $device => $device_key ) {
				if ( 'desktop' !== $device ) {
					$settings[ $device_key ] = array();
				}
			}
		}
		return $settings;
	}

	private function element_id( array $element ): string {
		return preg_replace( '/[^a-f0-9]/', '', (string) ( $element['id'] ?? '' ) );
	}

	/**
	 * Shared wrapper attributes: classes, CSS ID and data-id.
	 *
	 * @param string[] $classes Base classes.
	 */
	private function attributes( array $element, array $settings, array $classes, string $prefix ): string {
		$id        = $this->element_id( $element );
		$classes[] = 'kbroo-el';
		$classes[] = 'kbroo-el-' . $id;
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( 'yes' === ( $settings[ 'hide_' . $device ] ?? '' ) ) {
				$classes[] = 'kbroo-hidden-' . $device;
			}
		}
		$custom = trim( (string) ( $settings[ $prefix . 'css_classes' ] ?? '' ) );
		if ( '' !== $custom ) {
			$classes = array_merge( $classes, preg_split( '/\s+/', $custom ) );
		}

		$attributes = ' class="' . esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ) . '"';
		$css_id     = (string) ( $settings[ $prefix . 'element_id' ] ?? '' );
		if ( '' !== $css_id ) {
			$attributes .= ' id="' . esc_attr( $css_id ) . '"';
		}
		$attributes .= ' data-id="' . esc_attr( $id ) . '"';

		$animation = (string) ( $settings[ $prefix . 'animation' ] ?? '' );
		if ( in_array( $animation, ControlGroups::ENTRANCE_ANIMATIONS, true ) ) {
			$this->animated = true;
			$attributes    .= ' data-kbroo-animation="' . esc_attr( $animation ) . '"';
			$duration       = (string) ( $settings['animation_duration'] ?? '' );
			if ( in_array( $duration, array( 'slow', 'fast' ), true ) ) {
				$attributes .= ' data-kbroo-duration="' . esc_attr( $duration ) . '"';
			}
			$delay = (int) ( $settings[ $prefix . 'animation_delay' ] ?? 0 );
			if ( $delay > 0 ) {
				$attributes .= ' data-kbroo-delay="' . min( 10000, $delay ) . '"';
			}
		}
		return $attributes;
	}

	/**
	 * Background video markup for an element whose background type is
	 * "video", or ''. It sits under the overlay and the content.
	 */
	public static function background_video( array $settings, string $prefix = 'background' ): string {
		if ( 'video' !== ( $settings[ $prefix . '_background' ] ?? '' ) ) {
			return '';
		}
		$video = self::video_source( (string) ( $settings[ $prefix . '_video_link' ] ?? '' ) );
		if ( ! $video ) {
			return '';
		}
		$start = absint( $settings[ $prefix . '_video_start' ] ?? 0 );
		$end   = absint( $settings[ $prefix . '_video_end' ] ?? 0 );
		$once  = 'yes' === ( $settings[ $prefix . '_play_once' ] ?? '' );
		$class = 'kbroo-bg-video' . ( 'yes' === ( $settings[ $prefix . '_play_on_mobile' ] ?? '' ) ? ' kbroo-bg-video--mobile' : '' );

		if ( 'file' === $video['type'] ) {
			$src = $video['url'];
			if ( $start || $end ) {
				$src .= '#t=' . $start . ( $end ? ',' . $end : '' );
			}
			return sprintf(
				'<div class="%1$s" aria-hidden="true"><video src="%2$s" autoplay muted playsinline%3$s></video></div>',
				esc_attr( $class ),
				esc_url( $src ),
				$once ? '' : ' loop'
			);
		}

		if ( 'youtube' === $video['type'] ) {
			$query = array(
				'autoplay'       => 1,
				'mute'           => 1,
				'controls'       => 0,
				'playsinline'    => 1,
				'rel'            => 0,
				'modestbranding' => 1,
				'loop'           => $once ? 0 : 1,
				'playlist'       => $once ? null : $video['id'],
				'start'          => $start ? $start : null,
				'end'            => $end ? $end : null,
			);
			$src   = 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video['id'] ) . '?' . http_build_query( array_filter( $query, 'is_scalar' ) );
		} else {
			$src = 'https://player.vimeo.com/video/' . rawurlencode( $video['id'] ) . '?' . http_build_query(
				array(
					'background' => 1,
					'loop'       => $once ? 0 : 1,
				)
			) . ( $start ? '#t=' . $start . 's' : '' );
		}
		return sprintf(
			'<div class="%1$s" aria-hidden="true"><iframe src="%2$s" title="" tabindex="-1" allow="autoplay; fullscreen" loading="lazy"></iframe></div>',
			esc_attr( $class ),
			esc_url( $src )
		);
	}

	/**
	 * What a video link points at: { type: youtube|vimeo|file, id|url }.
	 */
	public static function video_source( string $link ): ?array {
		$link = trim( $link );
		if ( preg_match( '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})#', $link, $m ) ) {
			return array(
				'type' => 'youtube',
				'id'   => $m[1],
			);
		}
		if ( preg_match( '#vimeo\.com/(?:video/)?(\d{3,12})#', $link, $m ) ) {
			return array(
				'type' => 'vimeo',
				'id'   => $m[1],
			);
		}
		$url = Controls::sanitize_url( $link, array( 'http', 'https' ) );
		if ( '' !== $url && preg_match( '#\.(mp4|webm|ogv|mov)(\?|$)#i', $url ) ) {
			return array(
				'type' => 'file',
				'url'  => $url,
			);
		}
		return null;
	}

	/**
	 * Render a list of elements of any type. The layout was sanitized when
	 * it was saved, so each element is where it may be.
	 */
	private function render_children( array $elements ): string {
		$html = '';
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || ! Visibility::visible( (array) ( $element['settings'] ?? array() ) ) ) {
				continue;
			}
			switch ( $element['elType'] ?? '' ) {
				case 'section':
					$html .= $this->render_section( $element );
					break;
				case 'column':
					$html .= $this->render_column( $element );
					break;
				case 'container':
					$html .= $this->render_container( $element );
					break;
				case 'widget':
					$html .= $this->render_widget( $element );
					break;
			}
		}
		return $html;
	}

	private function render_section( array $section ): string {
		$settings = $this->prepare( $section, Layout::section_controls() );

		$tags    = array_keys( Layout::section_controls()['html_tag']['options'] );
		$tag     = in_array( $settings['html_tag'], $tags, true ) ? $settings['html_tag'] : 'section';
		$classes = array( 'kbroo-section', 'kbroo-section--' . ( 'full_width' === $settings['layout'] ? 'full' : 'boxed' ) );
		if ( ! empty( $section['isInner'] ) ) {
			$classes[] = 'kbroo-section--inner';
		}

		return sprintf(
			'<%1$s%2$s>%4$s<div class="kbroo-container"><div class="kbroo-row">%3$s</div></div></%1$s>',
			$tag,
			$this->attributes( $section, $settings, $classes, '' ),
			$this->render_children( (array) ( $section['elements'] ?? array() ) ),
			self::background_video( $settings ) . ShapeDividers::render( $settings )
		);
	}

	private function render_column( array $column ): string {
		$settings = $this->prepare( $column, Layout::column_controls() );

		return sprintf(
			'<div%1$s><div class="kbroo-column-inner">%2$s</div></div>',
			$this->attributes( $column, $settings, array( 'kbroo-column' ), '' ),
			$this->render_children( (array) ( $column['elements'] ?? array() ) )
		);
	}

	private function render_container( array $container ): string {
		$controls = Layout::container_controls();
		$settings = $this->prepare( $container, $controls );

		$tag     = array_key_exists( $settings['html_tag'], $controls['html_tag']['options'] ) ? $settings['html_tag'] : 'div';
		$classes = array( 'kbroo-con', 'kbroo-con--' . ( 'full' === $settings['content_width'] ? 'full' : 'boxed' ) );
		if ( ! empty( $container['isInner'] ) ) {
			$classes[] = 'kbroo-con--child';
		}

		return sprintf(
			'<%1$s%2$s>%4$s<div class="kbroo-con-inner">%3$s</div></%1$s>',
			$tag,
			$this->attributes( $container, $settings, $classes, '' ),
			$this->render_children( (array) ( $container['elements'] ?? array() ) ),
			self::background_video( $settings ) . ShapeDividers::render( $settings )
		);
	}

	private function render_widget( array $widget ): string {
		$instance = $this->widgets->get( (string) ( $widget['widgetType'] ?? '' ) );
		if ( ! $instance ) {
			return '';
		}
		$settings = $this->prepare( $widget, $instance->get_controls() );
		$instance->set_context( $this->element_id( $widget ), $this->post_id );

		return sprintf(
			'<div%1$s>%2$s</div>',
			$this->attributes( $widget, $settings, array( 'kbroo-widget', 'kbroo-widget--' . $instance->get_name() ), '_' ),
			$instance->render( $settings )
		);
	}
}
