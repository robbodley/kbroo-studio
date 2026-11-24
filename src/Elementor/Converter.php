<?php
/**
 * Converts Elementor page data to a Kbroo Studio layout.
 *
 * Kbroo Studio uses Elementor's tree shape and setting names, so most of the
 * work is copying: sections, columns, containers and the widgets Kbroo
 * Studio has keep their settings, and Layout::sanitize() drops whatever
 * Kbroo Studio does not support. This class fixes the places where the
 * two differ, swaps widgets Kbroo Studio does not have yet for fallback
 * blocks, and writes a report of everything that did not carry over.
 *
 * Elementor's own data is only read, never changed.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Elementor;

use KbrooStudio\ControlGroups;
use KbrooStudio\Controls;
use KbrooStudio\Fonts;
use KbrooStudio\Layout;
use KbrooStudio\Visibility;
use KbrooStudio\Widgets\ElementorFallback;
use KbrooStudio\Widgets\Registry;

defined( 'ABSPATH' ) || exit;

final class Converter {

	/** Friendly names for Elementor widgets that become fallback blocks. */
	const WIDGET_LABELS = array(
		'form'                   => 'Form',
		'posts'                  => 'Posts',
		'testimonial-carousel'   => 'Testimonial carousel',
		'icon'                   => 'Icon',
		'icon-list'              => 'Icon list',
		'icon-box'               => 'Icon box',
		'image-box'              => 'Image box',
		'social-icons'           => 'Social icons',
		'image-gallery'          => 'Image gallery',
		'image-carousel'         => 'Image carousel',
		'video'                  => 'Video',
		'loop-carousel'          => 'Loop carousel',
		'wp-widget-categories'   => 'Categories (WordPress widget)',
		'wp-widget-recent-posts' => 'Recent posts (WordPress widget)',
	);

	/** Elementor Pro widgets that Kbroo Studio has under another name. */
	const WIDGET_ALIASES = array(
		'theme-site-logo'     => 'site-logo',
		'theme-site-title'    => 'site-title',
		'theme-archive-title' => 'archive-title',
		'archive-posts'       => 'posts',
		'theme-post-title'    => 'post-title',
		'theme-page-title'    => 'post-title',
		'theme-post-content'  => 'post-content',
		'theme-post-excerpt'  => 'post-excerpt',
		'theme-post-featured-image' => 'post-featured-image',
	);

	/**
	 * Elementor widgets whose main setting is filled by a dynamic tag that a
	 * Kbroo Studio widget shows by itself: widget => setting => tag => widget.
	 */
	const DYNAMIC_WIDGETS = array(
		'heading'     => array(
			'title' => array(
				'post-title'    => 'theme-post-title',
				'archive-title' => 'theme-archive-title',
				'site-title'    => 'theme-site-title',
			),
		),
		'image'       => array(
			'image' => array(
				'post-featured-image' => 'theme-post-featured-image',
				'site-logo'           => 'theme-site-logo',
			),
		),
		'text-editor' => array(
			'editor' => array(
				'post-excerpt' => 'theme-post-excerpt',
				'post-content' => 'theme-post-content',
			),
		),
	);

	/** Elementor settings with no visible effect, left out of the report. */
	const QUIET_SETTINGS = array( '_column_size', 'structure', 'typography_typography', 'background_background', 'pa_condition_repeater', '_skin', 'slides_name', 'motion_fx_devices', 'background_slideshow_lazyload', 'classes' );

	/** Elementor's flex item settings, saved without the "_" by some versions. */
	const FLEX_ITEM = array( 'align_self', 'order', 'order_custom', 'size', 'grow', 'shrink' );

	/** @var Registry */
	private $widgets;

	/** @var array */
	private $report;

	/** @var int */
	private $source_post = 0;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	/**
	 * The `_elementor_data` of a post, decoded, or null if it has none.
	 */
	public static function post_data( int $post_id ): ?array {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
		return is_array( $data ) && $data ? $data : null;
	}

	/**
	 * Convert a list of Elementor elements.
	 *
	 * @param array $elements    Elementor elements (the page's top level).
	 * @param int   $source_post Post the data came from, for live fallbacks.
	 * @return array{layout: array, report: array}
	 */
	public function convert( array $elements, int $source_post = 0 ): array {
		$this->source_post = $source_post;
		$this->report      = array(
			'converted_at' => gmdate( 'c' ),
			'source_post'  => $source_post,
			'elements'     => 0,
			'widgets'      => array(),
			'fallbacks'    => array(),
			'dropped'      => array(),
			'fonts_added'  => array(),
			'fonts_local'  => array(),
		);

		$this->add_fonts( $elements );

		$converted = array_values( array_filter( array_map( array( $this, 'element' ), $elements ) ) );
		$layout    = Layout::sanitize( array( 'elements' => $converted ), $this->widgets );

		arsort( $this->report['dropped'] );
		ksort( $this->report['widgets'] );
		return array(
			'layout' => $layout,
			'report' => $this->report,
		);
	}

	/**
	 * Make the fonts the page uses available before any setting is checked
	 * against the font list.
	 */
	private function add_fonts( array $elements ): void {
		$names = array();
		array_walk_recursive(
			$elements,
			function ( $value, $key ) use ( &$names ) {
				if ( is_string( $key ) && preg_match( '/_font_family(_tablet|_mobile)?$/', $key ) && is_string( $value ) && '' !== $value ) {
					$names[ $value ] = true;
				}
			}
		);
		foreach ( array_keys( $names ) as $name ) {
			$result = Fonts::add( $name );
			if ( 'google' === $result ) {
				$this->report['fonts_added'][] = $name;
			} elseif ( 'local' === $result ) {
				$this->report['fonts_local'][] = $name;
			}
		}
		if ( $this->report['fonts_added'] || $this->report['fonts_local'] ) {
			$this->widgets->flush_controls();
		}
	}

	/**
	 * @param mixed $element Elementor element.
	 */
	private function element( $element ): ?array {
		if ( ! is_array( $element ) ) {
			return null;
		}
		if ( '' !== Atomic::name( $element ) ) {
			list( $element, $notes ) = Atomic::to_classic( $element );
			foreach ( $notes as $note ) {
				$this->drop( $note );
			}
		}
		$type     = (string) ( $element['elType'] ?? '' );
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

		if ( 'widget' === $type ) {
			return $this->widget( $element, $settings );
		}
		if ( ! in_array( $type, array( 'section', 'column', 'container' ), true ) ) {
			return null;
		}

		$settings = $this->dynamic_tags( $this->featured_backgrounds( $settings ) );
		$settings = $this->common( $settings );
		if ( 'section' === $type ) {
			$settings = $this->section( $settings );
		} elseif ( 'column' === $type ) {
			$settings = $this->column( $settings );
			if ( 'slideshow' === ( $settings['background_background'] ?? '' ) ) {
				$this->drop( 'background slideshow on a column (move it to the section)' );
				foreach ( array_keys( $settings ) as $key ) {
					if ( 0 === strpos( (string) $key, 'background_slideshow_' ) ) {
						unset( $settings[ $key ] );
					}
				}
			}
		} else {
			$settings = $this->container( $settings, ! empty( $element['isInner'] ) );
		}

		$this->note_dropped( $settings, Layout::controls_for( array( 'elType' => $type ), $this->widgets ), ucfirst( $type ) );
		$this->report['elements']++;

		$out = array(
			'id'       => (string) ( $element['id'] ?? '' ),
			'elType'   => $type,
			'isInner'  => ! empty( $element['isInner'] ),
			'settings' => $settings,
			'elements' => array_values( array_filter( array_map( array( $this, 'element' ), (array) ( $element['elements'] ?? array() ) ) ) ),
		);
		return $out;
	}

	/**
	 * Settings every element type shares.
	 */
	private function common( array $settings ): array {
		// Elementor stores "hidden-desktop" etc.; Kbroo Studio's switchers use "yes".
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( ! empty( $settings[ 'hide_' . $device ] ) ) {
				$settings[ 'hide_' . $device ] = 'yes';
			}
		}
		// Premium Addons' Display Conditions become show and hide rules.
		list( $rules, $dropped ) = Visibility::from_premium_addons( $settings );
		foreach ( $dropped as $name ) {
			$this->drop( $name );
		}
		foreach ( array_keys( $settings ) as $key ) {
			if ( 0 === strpos( (string) $key, 'pa_display_' ) || 0 === strpos( (string) $key, 'pa_condition' ) ) {
				unset( $settings[ $key ] );
			}
		}
		$settings = $rules + $settings;
		// Elementor Pro's own display conditions are not read yet.
		if ( ! empty( $settings['e_display_conditions'] ) && '[]' !== $settings['e_display_conditions'] ) {
			$this->drop( 'Elementor display conditions' );
		}
		unset( $settings['e_display_conditions'] );
		// Colours in rgba() become #rrggbbaa (see Controls::sanitize_color()).
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) && 0 === strpos( $value, 'rgb' ) ) {
				$color = Controls::sanitize_color( $value );
				if ( '' !== $color ) {
					$settings[ $key ] = $color;
				}
			}
			// Elementor's placeholder image left in a background: its file goes
			// when Elementor does, and it was never meant to show.
			if ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) && false !== strpos( $value['url'], '/elementor/assets/images/placeholder' ) ) {
				$settings[ $key ] = array(
					'url' => '',
					'id'  => '',
				);
				continue;
			}
			// Sliders saved empty come through as { size: null }.
			if ( is_array( $value ) && array_key_exists( 'size', $value ) && null === $value['size'] ) {
				$settings[ $key ]['size'] = '';
			}
		}
		foreach ( array( '_animation', 'animation' ) as $key ) {
			if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) && '' !== $settings[ $key ] && 'none' !== $settings[ $key ] ) {
				$settings[ $key ] = $this->animation( $settings[ $key ] );
			}
		}
		$settings = $this->box_shadows( $this->flex_item( $settings ) );
		// Background slideshows only fade, as Elementor's default does.
		$transition = $settings['background_slideshow_slide_transition'] ?? '';
		if ( is_string( $transition ) && '' !== $transition && 'fade' !== $transition && 'slideshow' === ( $settings['background_background'] ?? '' ) ) {
			$this->drop( 'slideshow ' . str_replace( '_', ' ', $transition ) . ' transition (shown as a fade)' );
		}
		unset( $settings['background_slideshow_slide_transition'] );
		if ( isset( $settings['hover_animation'] ) && is_string( $settings['hover_animation'] ) && '' !== $settings['hover_animation']
			&& ! ControlGroups::has_hover_animation( $settings['hover_animation'] ) ) {
			$this->drop( 'hover animation ' . $settings['hover_animation'] );
			$settings['hover_animation'] = '';
		}
		return $settings;
	}

	/**
	 * Flex item settings: some Elementor versions saved them without the
	 * leading "_" (flex_grow); an inline widget's vertical align
	 * (_element_vertical_align) is its align self.
	 */
	private function flex_item( array $settings ): array {
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			foreach ( self::FLEX_ITEM as $part ) {
				$old = 'flex_' . $part . $device;
				$new = '_flex_' . $part . $device;
				if ( isset( $settings[ $old ] ) && ! self::is_empty( $settings[ $old ] ) && self::is_empty( $settings[ $new ] ?? '' ) ) {
					$settings[ $new ] = $settings[ $old ];
				}
				unset( $settings[ $old ] );
			}
			// A grow or shrink number without a size means a custom size.
			$custom = ! self::is_empty( $settings[ '_flex_grow' . $device ] ?? '' ) || ! self::is_empty( $settings[ '_flex_shrink' . $device ] ?? '' );
			if ( $custom && self::is_empty( $settings[ '_flex_size' . $device ] ?? '' ) ) {
				$settings[ '_flex_size' . $device ] = 'custom';
			}
			$valign = $settings[ '_element_vertical_align' . $device ] ?? '';
			if ( is_string( $valign ) && '' !== $valign && self::is_empty( $settings[ '_flex_align_self' . $device ] ?? '' ) ) {
				$settings[ '_flex_align_self' . $device ] = $valign;
			}
			unset( $settings[ '_element_vertical_align' . $device ] );
		}
		return $settings;
	}

	/**
	 * Elementor keeps a box shadow in one value, { horizontal, vertical,
	 * blur, spread, color }; Kbroo Studio has a control for each part.
	 */
	private function box_shadows( array $settings ): array {
		foreach ( array( 'box_shadow', '_box_shadow' ) as $prefix ) {
			$key   = $prefix . '_box_shadow';
			$value = $settings[ $key ] ?? null;
			unset( $settings[ $key ] );
			if ( ! is_array( $value ) || 'yes' !== ( $settings[ $key . '_type' ] ?? '' ) ) {
				continue;
			}
			foreach ( array( 'horizontal', 'vertical', 'blur', 'spread' ) as $part ) {
				if ( isset( $value[ $part ] ) && is_numeric( $value[ $part ] ) ) {
					$settings[ $key . '_' . $part ] = array(
						'unit' => 'px',
						'size' => 0 + $value[ $part ],
					);
				}
			}
			$color = Controls::sanitize_color( (string) ( $value['color'] ?? '' ) );
			if ( '' !== $color ) {
				$settings[ $key . '_color' ] = $color;
			}
			if ( 'outline' === ( $settings[ $key . '_position' ] ?? '' ) ) {
				$settings[ $key . '_position' ] = '';
			}
		}
		return $settings;
	}

	/**
	 * The nearest entrance animation Kbroo Studio has to an Elementor one.
	 * Ones that are not the same are noted in the report.
	 */
	private function animation( string $name ): string {
		if ( in_array( $name, ControlGroups::ENTRANCE_ANIMATIONS, true ) ) {
			return $name;
		}
		$near = 'fadeIn';
		foreach ( ControlGroups::ENTRANCE_ANIMATIONS as $known ) {
			if ( 0 === strpos( $name, $known ) && strlen( $known ) > strlen( $near ) ) {
				$near = $known; // fadeInUpBig becomes fadeInUp.
			}
		}
		if ( 0 === strpos( $name, 'zoomIn' ) ) {
			$near = 'zoomIn';
		}
		$this->drop( 'animation ' . $name . ' (shown as ' . $near . ')' );
		return $near;
	}

	private function section( array $settings ): array {
		// Elementor sections have no padding unless set; Kbroo's default is 40px.
		if ( ! isset( $settings['padding'] ) ) {
			$settings['padding'] = self::empty_dimensions();
		}
		// Height: "full" screen or a minimum height.
		$height = $settings['height'] ?? '';
		if ( 'full' === $height ) {
			$settings['min_height'] = array(
				'unit' => 'vh',
				'size' => 100,
			);
		} elseif ( 'min-height' === $height ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
				if ( isset( $settings[ 'custom_height' . $suffix ] ) ) {
					$settings[ 'min_height' . $suffix ] = $settings[ 'custom_height' . $suffix ];
				}
			}
		}
		unset( $settings['height'], $settings['custom_height'], $settings['custom_height_tablet'], $settings['custom_height_mobile'] );
		return $settings;
	}

	private function column( array $settings ): array {
		// _inline_size is only stored once a column was resized.
		if ( ! isset( $settings['_inline_size'] ) || '' === $settings['_inline_size'] || null === $settings['_inline_size'] ) {
			$settings['_inline_size'] = isset( $settings['_column_size'] ) ? (float) $settings['_column_size'] : 100;
		}
		foreach ( array( '_inline_size_tablet', '_inline_size_mobile' ) as $key ) {
			if ( array_key_exists( $key, $settings ) && null === $settings[ $key ] ) {
				unset( $settings[ $key ] );
			}
		}
		return $settings;
	}

	private function container( array $settings, bool $inner ): array {
		// Elementor's default: boxed at the top level, full width when nested.
		if ( empty( $settings['content_width'] ) ) {
			$settings['content_width'] = $inner ? 'full' : 'boxed';
		}
		// The gap control stores { column, row, size?, unit }; use the column gap.
		foreach ( array( '', '_tablet', '_mobile' ) as $suffix ) {
			$gap = $settings[ 'flex_gap' . $suffix ] ?? null;
			if ( is_array( $gap ) ) {
				$size = $gap['size'] ?? '';
				if ( '' === $size || null === $size ) {
					$size = $gap['column'] ?? '';
				}
				$settings[ 'flex_gap' . $suffix ] = array(
					'unit' => $gap['unit'] ?? 'px',
					'size' => $size,
				);
			}
		}
		return $settings;
	}

	/**
	 * Site logo and title: Elementor fills them from dynamic tags (the site
	 * logo, name and home URL), which these widgets do themselves.
	 */
	private function site_widget( string $type, array $settings ): array {
		if ( 'posts' === $type ) {
			// Archive posts: the archive's own posts, with "archive_" skins.
			$clean = array( 'posts_post_type' => \KbrooStudio\Widgets\Posts::CURRENT_QUERY );
			foreach ( $settings as $key => $value ) {
				$clean[ preg_replace( '/^archive_/', '', (string) $key ) ] = '_skin' === $key ? preg_replace( '/^archive_/', '', (string) $value ) : $value;
			}
			return $clean;
		}
		// A link to the post (the post-url tag) on a title or featured image.
		if ( 'post-url' === ( self::tags_in( $settings )['link'] ?? '' ) ) {
			if ( 'post-title' === $type ) {
				$settings['link_to_post'] = 'yes';
			} elseif ( 'post-featured-image' === $type ) {
				$settings['link_to'] = 'post';
			}
		}
		unset( $settings['__dynamic__'], $settings['title'] );
		if ( 'post-featured-image' !== $type ) {
			unset( $settings['link'] ); // The featured image keeps a custom link.
		} else {
			unset( $settings['image'] ); // The post's own.
		}
		if ( 'site-logo' === $type ) {
			$settings['link_to'] = 'none' === ( $settings['link_to'] ?? '' ) ? 'none' : 'home';
			if ( isset( $settings['space'] ) ) {
				$settings['max_width'] = $settings['space'];
				unset( $settings['space'] );
			}
			foreach ( array( 'image', 'caption_source', 'caption' ) as $key ) {
				if ( 'image' === $key && empty( $settings['image']['id'] ) ) {
					unset( $settings['image'] ); // The theme's logo.
				} elseif ( 'image' !== $key ) {
					unset( $settings[ $key ] );
				}
			}
		}
		return $settings;
	}

	private function widget( array $element, array $settings ): ?array {
		$type = (string) ( $element['widgetType'] ?? '' );
		if ( '' === $type ) {
			return null;
		}
		$this->report['elements']++;
		$this->report['widgets'][ $type ] = ( $this->report['widgets'][ $type ] ?? 0 ) + 1;
		$type = $this->dynamic_widget( $type, $settings );
		if ( 'loop-grid' === $type ) {
			$type     = 'posts';
			$settings = $this->loop_grid( $settings );
		}
		if ( isset( self::WIDGET_ALIASES[ $type ] ) ) {
			$type     = self::WIDGET_ALIASES[ $type ];
			$settings = $this->site_widget( $type, $settings );
		}

		$widget = $this->widgets->get( $type );
		if ( 'video' === $type && ! in_array( $settings['video_type'] ?? 'youtube', array( 'youtube', 'vimeo', 'hosted' ), true ) ) {
			$widget = null; // Dailymotion and VideoPress stay as Elementor showed them.
		}
		if ( $widget && 'elementor-fallback' !== $type ) {
			$settings = $this->dynamic_tags( $this->featured_backgrounds( $settings ) );
			$settings = $this->common( $settings );
			$settings = $this->legacy_icons( $type, $settings );
			if ( 'posts' === $type && 'loop' !== ( $settings['_skin'] ?? '' ) ) {
				$settings = $this->posts( $settings );
			}
			if ( 0 === strpos( $type, 'wp-widget-' ) && isset( $settings['wp'] ) && is_array( $settings['wp'] ) ) {
				// Elementor keeps a WordPress widget's own settings under "wp".
				foreach ( $settings['wp'] as $key => $value ) {
					$settings[ $key ] = in_array( $key, array( 'count', 'hierarchical', 'dropdown', 'show_date' ), true ) ? ( ! empty( $value ) ? 'yes' : '' ) : $value;
				}
				unset( $settings['wp'] );
			}
			if ( 'form' === $type ) {
				$settings = $this->form( $settings );
			}
			if ( 'image' === $type ) {
				$settings = $this->image_size( $settings );
			}
			if ( 'post-info' === $type ) {
				$settings = self::post_info( $settings );
			}
			if ( 'nav-menu' === $type && ! isset( $settings['pointer'] ) ) {
				$settings['pointer'] = 'underline'; // Elementor's default.
			}
			if ( 'nav-menu' === $type && 'text' === ( $settings['pointer'] ?? '' ) ) {
				$this->drop( 'menu text pointer (shows no pointer)' );
				$settings['pointer'] = '';
			}
			if ( 'nav-menu' === $type && 'none' === ( $settings['pointer'] ?? '' ) ) {
				$settings['pointer'] = '';
			}
			if ( in_array( $type, array( 'icon-box', 'image-box' ), true ) ) {
				$settings = $this->box( $settings );
			}
			if ( 'image-gallery' === $type ) {
				// Elementor's defaults, which differ from Kbroo Studio's.
				$settings += array(
					'thumbnail_size'          => 'thumbnail',
					'gallery_display_caption' => '',
				);
			}
			if ( 'video' === $type ) {
				if ( 'yes' === ( $settings['lightbox'] ?? '' ) ) {
					$this->drop( 'video lightbox (plays in the page)' );
				}
				unset( $settings['lightbox'] );
			}
			if ( 'post-navigation' === $type ) {
				// Elementor picks taxonomies; Kbroo Studio keeps to categories.
				$settings['in_same_term'] = ! empty( $settings['in_same_term'] ) ? 'yes' : '';
			}
			if ( 'divider' === $type && 'line_icon' === ( $settings['look'] ?? '' ) ) {
				$settings['look'] = 'line';
				$this->drop( 'divider icon' );
			}
			$this->note_dropped( $settings, $widget->get_controls(), $widget->get_title() );
			return array(
				'id'         => (string) ( $element['id'] ?? '' ),
				'elType'     => 'widget',
				'widgetType' => $type,
				'settings'   => $settings,
			);
		}

		// Not supported yet: keep the original and show it as it was.
		$label = self::WIDGET_LABELS[ $type ] ?? ucwords( str_replace( array( '-', '_' ), ' ', $type ) );
		$json  = wp_json_encode( $element );
		$this->report['fallbacks'][] = array(
			'id'    => (string) ( $element['id'] ?? '' ),
			'type'  => $type,
			'label' => $label,
		);

		$keep = array();
		foreach ( array( '_margin', '_margin_tablet', '_margin_mobile', '_padding', '_padding_tablet', '_padding_mobile', '_element_id', '_css_classes', 'hide_desktop', 'hide_tablet', 'hide_mobile', 'pa_display_conditions_switcher', 'pa_display_action', 'pa_display_when', 'pa_condition_repeater', 'e_display_conditions' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$keep[ $key ] = $settings[ $key ];
			}
		}
		return array(
			'id'         => (string) ( $element['id'] ?? '' ),
			'elType'     => 'widget',
			'widgetType' => 'elementor-fallback',
			'settings'   => $this->common( $keep ) + array(
				/* translators: %s: Elementor widget name. */
				'_title'         => sprintf( __( '%s (not converted)', 'kbroo-studio' ), $label ),
				'element_type'   => $type,
				'element_label'  => $label,
				'source_post'    => $this->source_post,
				'elementor_data' => $json,
				'snapshot'       => ElementorFallback::render_with_elementor( $json, $this->source_post ),
			),
		);
	}

	/**
	 * The dynamic tags in an element's settings, as setting => tag name.
	 * Elementor stores them as shortcodes: [elementor-tag id=".." name="post-title" settings=".."].
	 */
	private static function tags_in( array $settings ): array {
		$tags = array();
		foreach ( (array) ( $settings['__dynamic__'] ?? array() ) as $key => $shortcode ) {
			if ( is_string( $shortcode ) && preg_match( '/name="([a-z0-9_-]+)"/i', $shortcode, $m ) ) {
				$tags[ (string) $key ] = $m[1];
			}
		}
		return $tags;
	}

	/**
	 * A heading showing the post title becomes a Post title widget, an image
	 * showing the featured image a Featured image widget, and so on.
	 */
	private function dynamic_widget( string $type, array &$settings ): string {
		$tags = self::tags_in( $settings );
		foreach ( self::DYNAMIC_WIDGETS[ $type ] ?? array() as $key => $map ) {
			if ( isset( $tags[ $key ], $map[ $tags[ $key ] ] ) ) {
				unset( $settings['__dynamic__'][ $key ] );
				if ( 'heading' === $type && empty( $settings['header_size'] ) ) {
					$settings['header_size'] = 'h2'; // Elementor's heading default.
				}
				return $map[ $tags[ $key ] ];
			}
		}
		return $type;
	}

	/**
	 * A background image filled by the featured image tag becomes a
	 * background whose image source is the featured image.
	 */
	private function featured_backgrounds( array $settings ): array {
		foreach ( self::tags_in( $settings ) as $key => $tag ) {
			if ( 'post-featured-image' === $tag && preg_match( '/^(_?background(?:_hover|_overlay(?:_hover)?)?)_image(?:_tablet|_mobile)?$/', $key, $m ) ) {
				$settings[ $m[1] . '_image_source' ] = 'featured';
				unset( $settings['__dynamic__'][ $key ] );
			}
		}
		return $settings;
	}

	/**
	 * Dynamic tags Kbroo Studio cannot fill yet: the setting keeps the value
	 * saved with it, and the report says so.
	 */
	private function dynamic_tags( array $settings ): array {
		foreach ( self::tags_in( $settings ) as $key => $tag ) {
			$this->drop( 'dynamic tag ' . $tag . ' (shows the saved ' . $key . ' instead)' );
		}
		unset( $settings['__dynamic__'] );
		return $settings;
	}

	/**
	 * Elementor Pro's Loop grid: a Posts widget with the Loop item skin. Its
	 * query settings are named post_query_* where the Posts widget's are
	 * posts_*. The loop item is found by the Elementor template it was
	 * converted from, now or once it is converted.
	 */
	private function loop_grid( array $settings ): array {
		$clean = array( '_skin' => 'loop' );
		foreach ( $settings as $key => $value ) {
			$key = (string) $key;
			if ( 0 === strpos( $key, 'post_query_' ) ) {
				$key = 'posts_' . substr( $key, 11 );
			}
			$clean[ $key ] = $value;
		}
		$elementor = (int) ( $settings['template_id'] ?? 0 );
		$converted = $elementor ? Migration::converted_template( $elementor ) : null;
		$clean['template_id']           = $converted ? (string) $converted->ID : '';
		$clean['elementor_template_id'] = $elementor;
		if ( $elementor && ! $converted ) {
			$this->drop( 'loop item template (convert it under Saved templates and the grid uses it)' );
		}
		$clean = self::load_more( $clean );
		// Elementor's defaults where they differ from the Posts widget's.
		$clean += array(
			'posts_exclude_current' => '',
			'posts_post_type'       => 'post',
		);
		return $clean;
	}

	/**
	 * Load more pagination: Elementor names the button's text "text".
	 * Its spinner and end-of-posts message have no equivalent (the button
	 * goes once the last posts are in).
	 */
	private static function load_more( array $settings ): array {
		if ( 0 === strpos( (string) ( $settings['pagination_type'] ?? '' ), 'load_more' ) && isset( $settings['text'] ) && is_string( $settings['text'] ) ) {
			$settings['load_more_text'] = html_entity_decode( $settings['text'], ENT_QUOTES, 'UTF-8' );
		}
		unset( $settings['text'], $settings['load_more_spinner'], $settings['load_more_no_posts_custom_message'], $settings['load_more_no_posts_message_switcher'] );
		return $settings;
	}

	/**
	 * Post info: some versions list what to show in meta_data (["author",
	 * "date"]) rather than as icon_list items.
	 */
	private static function post_info( array $settings ): array {
		$meta = $settings['meta_data'] ?? null;
		unset( $settings['meta_data'] );
		if ( ! is_array( $meta ) || ! $meta || ! empty( $settings['icon_list'] ) ) {
			return $settings;
		}
		$items = array();
		foreach ( $meta as $i => $item ) {
			if ( is_array( $item ) ) {
				$items[] = $item + array( '_id' => substr( md5( 'meta' . $i ), 0, 7 ) ); // Already items.
			} elseif ( is_string( $item ) && in_array( $item, array( 'author', 'date', 'time', 'comments', 'terms' ), true ) ) {
				$items[] = array(
					'_id'  => substr( md5( 'meta' . $item ), 0, 7 ),
					'type' => $item,
				);
			} elseif ( in_array( $item, array( 'categories', 'tags' ), true ) ) {
				$items[] = array(
					'_id'      => substr( md5( 'meta' . $item ), 0, 7 ),
					'type'     => 'terms',
					'taxonomy' => 'categories' === $item ? 'category' : 'post_tag',
				);
			}
		}
		if ( $items ) {
			$settings['icon_list'] = $items;
		}
		return $settings;
	}

	/**
	 * Elementor's posts widget prefixes most settings with its skin
	 * (classic_columns, cards_meta_data); Kbroo Studio's does not.
	 */
	private function posts( array $settings ): array {
		// Older Elementor Pro versions picked categories by id in
		// posts_selected_categories rather than include by term.
		$picked = $settings['posts_selected_categories'] ?? null;
		unset( $settings['posts_selected_categories'] );
		if ( is_array( $picked ) && $picked && empty( $settings['posts_include_term_ids'] ) ) {
			$ids = array();
			foreach ( $picked as $term ) {
				$found = is_numeric( $term ) ? get_term( (int) $term, 'category' ) : get_term_by( 'slug', (string) $term, 'category' );
				if ( $found && ! is_wp_error( $found ) ) {
					$ids[] = (string) $found->term_id;
				}
			}
			if ( $ids ) {
				$settings['posts_include']          = array( 'terms' );
				$settings['posts_include_term_ids'] = $ids;
			} else {
				$this->drop( 'posts categories (none found on this site)' );
			}
		}
		$skin  = in_array( $settings['_skin'] ?? '', array( 'classic', 'cards' ), true ) ? $settings['_skin'] : 'classic';
		$clean = array( '_skin' => $skin );
		foreach ( $settings as $key => $value ) {
			if ( preg_match( '/^(classic|cards|full_content)_(.+)$/', $key, $m ) ) {
				if ( $m[1] === $skin ) {
					$clean[ $m[2] ] = $value;
				}
				continue; // Another skin's settings were never shown.
			}
			$clean[ $key ] = $value;
		}
		// Elementor's defaults where they differ from Kbroo Studio's.
		$clean += array(
			'meta_data'      => array( 'date', 'comments' ),
			'meta_separator' => 'cards' === $skin ? '•' : '///',
		);
		if ( 'cards' === $skin && isset( $clean['show_image'] ) ) {
			$clean['thumbnail'] = 'yes' === $clean['show_image'] ? 'top' : 'none';
		}
		unset( $clean['show_image'] );
		if ( ! in_array( 'terms', (array) ( $clean['posts_include'] ?? array() ), true ) ) {
			unset( $clean['posts_include_term_ids'] );
		}
		if ( ! in_array( 'terms', (array) ( $clean['posts_exclude'] ?? array() ), true ) ) {
			unset( $clean['posts_exclude_term_ids'] );
		}
		$clean = self::load_more( $clean );
		unset( $clean['posts_include'], $clean['posts_exclude'] );
		foreach ( array( 'pagination_prev_label', 'pagination_next_label', 'read_more_text' ) as $key ) {
			if ( isset( $clean[ $key ] ) && is_string( $clean[ $key ] ) ) {
				$clean[ $key ] = html_entity_decode( $clean[ $key ], ENT_QUOTES, 'UTF-8' );
			}
		}
		return $clean;
	}

	/**
	 * An image at a custom size (image_size "custom" with
	 * image_custom_dimension { width, height }): Elementor makes a copy of
	 * the file at that size. Here the large or full file shows at that
	 * width, and at that height too (cropped to fill) when both are given.
	 */
	private function image_size( array $settings ): array {
		$size = $settings['image_custom_dimension'] ?? null;
		unset( $settings['image_custom_dimension'] );
		if ( 'custom' !== ( $settings['image_size'] ?? '' ) ) {
			return $settings;
		}
		$width  = is_array( $size ) && is_numeric( $size['width'] ?? '' ) ? (int) $size['width'] : 0;
		$height = is_array( $size ) && is_numeric( $size['height'] ?? '' ) ? (int) $size['height'] : 0;
		$settings['image_size'] = $width > 1024 || $height > 1024 ? 'full' : 'large';
		if ( $width && self::is_empty( $settings['width'] ?? '' ) ) {
			$settings['width'] = array(
				'unit' => 'px',
				'size' => $width,
			);
		}
		if ( $height && self::is_empty( $settings['height'] ?? '' ) ) {
			$settings['height'] = array(
				'unit' => 'px',
				'size' => $height,
			);
			if ( $width && self::is_empty( $settings['object-fit'] ?? '' ) ) {
				$settings['object-fit'] = 'cover';
			}
		}
		return $settings;
	}

	/**
	 * Icon box and image box: newer Elementor versions name positions and
	 * alignments by writing direction (inline-start), older ones by side.
	 */
	private function box( array $settings ): array {
		$sides = array(
			'block-start'  => 'top',
			'block-end'    => 'top',
			'inline-start' => 'left',
			'inline-end'   => 'right',
			'start'        => 'left',
			'end'          => 'right',
		);
		if ( 'block-end' === ( $settings['position'] ?? '' ) ) {
			$this->drop( 'image or icon below the text (moved above)' );
		}
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			foreach ( array( 'position', 'text_align' ) as $key ) {
				$value = $settings[ $key . $device ] ?? null;
				if ( is_string( $value ) && isset( $sides[ $value ] ) ) {
					$settings[ $key . $device ] = $sides[ $value ];
				}
			}
		}
		// The image box's border radius is a single size in Elementor.
		$radius = $settings['image_border_radius'] ?? null;
		if ( is_array( $radius ) && isset( $radius['size'] ) && ! isset( $radius['top'] ) ) {
			$size                            = (string) $radius['size'];
			$settings['image_border_radius'] = array(
				'unit'     => (string) ( $radius['unit'] ?? 'px' ),
				'top'      => $size,
				'right'    => $size,
				'bottom'   => $size,
				'left'     => $size,
				'isLinked' => true,
			);
		}
		return $settings;
	}

	/**
	 * Elementor's form stores ticked boxes in fields as "true".
	 */
	private function form( array $settings ): array {
		foreach ( (array) ( $settings['form_fields'] ?? array() ) as $i => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			foreach ( array( 'required', 'allow_multiple', 'inline_list' ) as $key ) {
				if ( isset( $field[ $key ] ) ) {
					$field[ $key ] = in_array( $field[ $key ], array( 'true', 'yes', true ), true ) ? 'yes' : '';
				}
			}
			unset( $field['inline_list'] );
			$settings['form_fields'][ $i ] = $field + array( 'field_type' => 'text' );
		}
		$actions = $settings['submit_actions'] ?? array( 'email' );
		if ( is_array( $actions ) && ! in_array( 'email', $actions, true ) ) {
			$this->drop( 'form without an email action (Kbroo Studio emails every form)' );
		}
		if ( is_array( $actions ) && in_array( 'email2', $actions, true ) ) {
			$settings['email_2'] = 'yes';
			// Elementor's second email sends to the address typed in the form unless told otherwise.
			$settings += array( 'email_to_2' => '[field id="email"]' );
		} else {
			foreach ( array_keys( $settings ) as $key ) {
				if ( preg_match( '/^email_.+_2$/', (string) $key ) ) {
					unset( $settings[ $key ] ); // Filled in once, never sent.
				}
			}
		}
		unset( $settings['submit_actions'], $settings['step_next_label'], $settings['step_previous_label'], $settings['email_content_type'], $settings['email_content_type_2'], $settings['form_metadata'], $settings['form_metadata_2'] );
		return $settings;
	}

	/**
	 * Icons saved by older Elementor versions as a class string ("fa fa-star")
	 * become the current { value, library } shape.
	 */
	private function legacy_icons( string $type, array $settings ): array {
		$pairs = array(
			'icon'         => array( 'icon', 'selected_icon' ),
			'icon-box'     => array( 'icon', 'selected_icon' ),
			'social-icons' => array( 'social', 'social_icon' ),
			'icon-list'    => array( 'icon', 'selected_icon' ),
		);
		if ( ! isset( $pairs[ $type ] ) ) {
			return $settings;
		}
		list( $old, $new ) = $pairs[ $type ];
		$upgrade = function ( array $values ) use ( $old, $new ) {
			if ( empty( $values[ $new ]['value'] ) && ! empty( $values[ $old ] ) && is_string( $values[ $old ] ) ) {
				$values[ $new ] = array(
					'value'   => $values[ $old ],
					'library' => '',
				);
			}
			unset( $values[ $old ] );
			return $values;
		};
		$lists = array(
			'social-icons' => 'social_icon_list',
			'icon-list'    => 'icon_list',
		);
		if ( isset( $lists[ $type ] ) ) {
			foreach ( (array) ( $settings[ $lists[ $type ] ] ?? array() ) as $i => $item ) {
				if ( is_array( $item ) ) {
					$settings[ $lists[ $type ] ][ $i ] = $upgrade( $item );
				}
			}
			return $settings;
		}
		return $upgrade( $settings );
	}

	/**
	 * Count settings that have a value but no Kbroo Studio control.
	 */
	private function note_dropped( array $settings, array $controls, string $where = '' ): void {
		$known = array( '__globals__' => true, '_title' => true );
		foreach ( $controls as $key => $control ) {
			foreach ( Controls::keys( $key, $control ) as $setting_key ) {
				$known[ $setting_key ] = true;
			}
			if ( ! empty( $control['global_group'] ) ) {
				$known[ $control['global_group'] ] = true;
			}
		}
		foreach ( $settings as $key => $value ) {
			if ( isset( $known[ $key ] ) || in_array( $key, self::QUIET_SETTINGS, true ) || self::is_empty( $value ) ) {
				continue;
			}
			$name = (string) preg_replace( '/_(tablet|mobile)$/', '', (string) $key );
			// Say where it was, so a setting that could mean several things can be traced.
			$this->drop( '' !== $where ? $name . ' (' . $where . ')' : $name );
		}
	}

	private function drop( string $name ): void {
		$this->report['dropped'][ $name ] = ( $this->report['dropped'][ $name ] ?? 0 ) + 1;
	}

	/**
	 * @param mixed $value Setting value.
	 */
	private static function is_empty( $value ): bool {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $part ) {
				if ( in_array( $key, array( 'unit', 'isLinked', 'sizes' ), true ) ) {
					continue;
				}
				if ( ! self::is_empty( $part ) ) {
					return false;
				}
			}
			return true;
		}
		return null === $value || '' === $value || false === $value;
	}

	private static function empty_dimensions(): array {
		return array(
			'unit'     => 'px',
			'top'      => '',
			'right'    => '',
			'bottom'   => '',
			'left'     => '',
			'isLinked' => true,
		);
	}
}
