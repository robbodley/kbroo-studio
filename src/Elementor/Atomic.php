<?php
/**
 * Elementor's atomic (version 4) elements in the shape the converter
 * reads: Div block and Flexbox become containers; Heading, Paragraph,
 * Button, Image, Divider and YouTube become their Kbroo Studio widgets.
 *
 * Atomic settings wrap every value as { $$type, value }, and their look
 * lives in a list of style variants (per breakpoint and state) rather
 * than in settings. The values are unwrapped, and the styles become the
 * element's custom CSS, which keeps them as written.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Elementor;

defined( 'ABSPATH' ) || exit;

final class Atomic {

	/** Atomic element => [ elType, widgetType ]. */
	const ELEMENTS = array(
		'e-div-block' => array( 'container', '' ),
		'e-flexbox'   => array( 'container', '' ),
		'e-heading'   => array( 'widget', 'heading' ),
		'e-paragraph' => array( 'widget', 'text-editor' ),
		'e-button'    => array( 'widget', 'button' ),
		'e-image'     => array( 'widget', 'image' ),
		'e-divider'   => array( 'widget', 'divider' ),
		'e-youtube'   => array( 'widget', 'video' ),
	);

	/** Media queries for Elementor's breakpoints. */
	const BREAKPOINTS = array(
		'desktop' => '',
		'tablet'  => '@media (max-width:1024px)',
		'mobile'  => '@media (max-width:767px)',
	);

	/** Properties that lay out a container's children (on its inner box). */
	const LAYOUT_PROPS = array( 'display', 'flex-direction', 'flex-wrap', 'gap', 'row-gap', 'column-gap', 'justify-content', 'align-items', 'align-content' );

	/** Text properties, which also go to the widget's own text element. */
	const TEXT_PROPS = array( 'color', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'word-spacing', 'text-align', 'text-decoration', 'text-transform' );

	/**
	 * The atomic name of an element, or '' if it is not atomic.
	 */
	public static function name( array $element ): string {
		$name = 'widget' === ( $element['elType'] ?? '' ) ? (string) ( $element['widgetType'] ?? '' ) : (string) ( $element['elType'] ?? '' );
		return 0 === strpos( $name, 'e-' ) ? $name : '';
	}

	/**
	 * An atomic element as a classic one, and notes for the report.
	 *
	 * @return array{0: array, 1: string[]}
	 */
	public static function to_classic( array $element ): array {
		$name     = self::name( $element );
		$notes    = array();
		$settings = self::unwrap( (array) ( $element['settings'] ?? array() ) );
		if ( ! isset( self::ELEMENTS[ $name ] ) ) {
			// Kept as is: an unknown widget becomes a fallback block.
			return array( $element, $notes );
		}
		list( $type, $widget ) = self::ELEMENTS[ $name ];

		$out = array(
			'id'       => (string) ( $element['id'] ?? '' ),
			'elType'   => $type,
			'isInner'  => ! empty( $element['isInner'] ),
			'settings' => self::settings( $name, $settings ),
			'elements' => (array) ( $element['elements'] ?? array() ),
		);
		if ( 'widget' === $type ) {
			$out['widgetType'] = $widget;
			unset( $out['elements'], $out['isInner'] );
		}

		list( $css, $style_notes ) = self::styles( $name, $element, (array) ( $settings['classes'] ?? array() ) );
		if ( '' !== $css ) {
			$out['settings']['custom_css'] = $css;
		}
		return array( $out, array_merge( $notes, $style_notes ) );
	}

	/**
	 * Unwrap { $$type, value } everywhere.
	 *
	 * @param mixed $value Atomic value.
	 * @return mixed
	 */
	public static function unwrap( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) ) {
			return self::unwrap( $value['value'] );
		}
		return array_map( array( __CLASS__, 'unwrap' ), $value );
	}

	/**
	 * Classic settings from unwrapped atomic ones.
	 */
	private static function settings( string $name, array $s ): array {
		$link = function ( $link ) {
			$url = is_array( $link ) ? ( $link['destination'] ?? $link['url'] ?? '' ) : '';
			$url = is_array( $url ) ? ( $url['url'] ?? '' ) : $url;
			return array(
				'url'         => is_string( $url ) ? $url : '',
				'is_external' => ! empty( $link['isTargetBlank'] ) || '_blank' === ( $link['target'] ?? '' ) ? 'on' : '',
				'nofollow'    => '',
			);
		};
		switch ( $name ) {
			case 'e-flexbox':
				return array(
					'content_width'  => 'full',
					'flex_direction' => 'row',
				);
			case 'e-div-block':
				return array( 'content_width' => 'full' );
			case 'e-heading':
				$tag = (string) ( $s['tag'] ?? 'h2' );
				return array(
					'title'       => (string) ( $s['title'] ?? '' ),
					'header_size' => preg_match( '/^(h[1-6]|div|span|p)$/', $tag ) ? $tag : 'h2',
				);
			case 'e-paragraph':
				return array( 'editor' => (string) ( $s['paragraph'] ?? '' ) );
			case 'e-button':
				return array(
					'text' => (string) ( $s['text'] ?? '' ),
					'link' => $link( $s['link'] ?? null ),
				);
			case 'e-image':
				$image = (array) ( $s['image'] ?? array() );
				$src   = (array) ( $image['src'] ?? array() );
				$out   = array(
					'image'      => array(
						'id'  => (int) ( $src['id'] ?? 0 ) ? (int) $src['id'] : '',
						'url' => is_string( $src['url'] ?? null ) ? $src['url'] : '',
					),
					'image_size' => is_string( $image['size'] ?? null ) ? $image['size'] : 'full',
				);
				$url   = $link( $s['link'] ?? null );
				if ( '' !== $url['url'] ) {
					$out['link_to'] = 'custom';
					$out['link']    = $url;
				}
				return $out;
			case 'e-youtube':
				return array(
					'video_type'  => 'youtube',
					'youtube_url' => (string) ( $s['source'] ?? '' ),
				);
		}
		return array();
	}

	/**
	 * The element's style variants as custom CSS ("selector" stands for the
	 * element), with notes on what could not be written.
	 *
	 * @param array $classes Class ids the element uses.
	 * @return array{0: string, 1: string[]}
	 */
	private static function styles( string $name, array $element, array $classes ): array {
		$styles = (array) ( $element['styles'] ?? array() );
		$notes  = array();
		$rules  = array();
		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) ) {
				continue;
			}
			if ( ! isset( $styles[ $class ] ) ) {
				$notes[] = 'atomic global class ' . $class . ' (style it again)';
				continue;
			}
			foreach ( (array) ( $styles[ $class ]['variants'] ?? array() ) as $variant ) {
				$meta       = (array) ( $variant['meta'] ?? array() );
				$breakpoint = (string) ( $meta['breakpoint'] ?? 'desktop' );
				$state      = (string) ( $meta['state'] ?? '' );
				if ( ! array_key_exists( $breakpoint, self::BREAKPOINTS ) ) {
					$notes[] = 'atomic styles for the ' . $breakpoint . ' breakpoint';
					continue;
				}
				if ( '' !== $state && ! in_array( $state, array( 'hover', 'focus', 'active' ), true ) ) {
					$notes[] = 'atomic styles for the ' . $state . ' state';
					continue;
				}
				foreach ( (array) ( $variant['props'] ?? array() ) as $prop => $value ) {
					$css = self::value( (string) $prop, self::unwrap( $value ) );
					if ( null === $css ) {
						$notes[] = 'atomic style ' . $prop;
						continue;
					}
					foreach ( self::targets( $name, (string) $prop ) as $target ) {
						$selector = $target . ( '' !== $state ? ':' . $state : '' );
						$rules[ self::BREAKPOINTS[ $breakpoint ] ][ $selector ][] = $prop . ': ' . $css . ';';
					}
				}
			}
		}
		$out = '';
		foreach ( self::BREAKPOINTS as $media ) {
			if ( empty( $rules[ $media ] ) ) {
				continue;
			}
			$block = '';
			foreach ( $rules[ $media ] as $selector => $declarations ) {
				$block .= $selector . ' { ' . implode( ' ', $declarations ) . " }\n";
			}
			$out .= '' === $media ? $block : $media . " {\n" . $block . "}\n";
		}
		return array( trim( $out ), array_values( array_unique( $notes ) ) );
	}

	/**
	 * Where a property goes: the element, its inner box (container layout),
	 * or its own text, button or image element too.
	 *
	 * @return string[]
	 */
	private static function targets( string $name, string $prop ): array {
		if ( in_array( $name, array( 'e-div-block', 'e-flexbox' ), true ) ) {
			return in_array( $prop, self::LAYOUT_PROPS, true ) ? array( 'selector > .kbroo-con-inner' ) : array( 'selector' );
		}
		if ( 'e-button' === $name ) {
			return array( 'selector .kbroo-button' );
		}
		if ( 'e-image' === $name ) {
			return in_array( $prop, array( 'width', 'height', 'max-width', 'min-width', 'max-height', 'min-height', 'object-fit', 'object-position', 'border-radius', 'opacity', 'aspect-ratio' ), true )
				? array( 'selector img' )
				: array( 'selector' );
		}
		if ( in_array( $prop, self::TEXT_PROPS, true ) ) {
			return array( 'selector', 'selector .kbroo-heading', 'selector .kbroo-text' );
		}
		return array( 'selector' );
	}

	/**
	 * A property's unwrapped value as CSS, or null if its shape is not
	 * known. Only plain characters get through.
	 *
	 * @param mixed $value Unwrapped value.
	 */
	public static function value( string $prop, $value ): ?string {
		if ( ! preg_match( '/^[a-z-]+$/', $prop ) ) {
			return null;
		}
		$css = self::to_css( $prop, $value );
		if ( null === $css || '' === trim( $css ) || preg_match( '/[;{}<>\\\\]/', $css ) ) {
			return null;
		}
		return trim( $css );
	}

	/**
	 * @param mixed $value Unwrapped value.
	 */
	private static function to_css( string $prop, $value ): ?string {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		if ( is_string( $value ) ) {
			return $value;
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		// A size: { size, unit }.
		if ( array_key_exists( 'size', $value ) && array_key_exists( 'unit', $value ) ) {
			if ( 'auto' === $value['unit'] ) {
				return 'auto';
			}
			if ( ! is_numeric( $value['size'] ) && 'custom' !== $value['unit'] ) {
				return null;
			}
			return 'custom' === $value['unit'] ? (string) $value['size'] : ( 0 + $value['size'] ) . $value['unit'];
		}
		// Sides: logical or physical.
		$sides = array(
			array( 'block-start', 'inline-end', 'block-end', 'inline-start' ),
			array( 'top', 'right', 'bottom', 'left' ),
			array( 'start-start', 'start-end', 'end-end', 'end-start' ),
		);
		foreach ( $sides as $names ) {
			if ( array_intersect( $names, array_keys( $value ) ) ) {
				$parts = array();
				foreach ( $names as $side ) {
					$parts[] = isset( $value[ $side ] ) ? ( self::to_css( $prop, $value[ $side ] ) ?? '0' ) : '0';
				}
				return implode( ' ', $parts );
			}
		}
		// A gap: { row, column }.
		if ( isset( $value['row'] ) || isset( $value['column'] ) ) {
			return ( self::to_css( $prop, $value['row'] ?? '0' ) ?? '0' ) . ' ' . ( self::to_css( $prop, $value['column'] ?? '0' ) ?? '0' );
		}
		if ( 'background' === $prop ) {
			return self::background( $value );
		}
		if ( 'transition' === $prop ) {
			return self::transition( $value );
		}
		return null;
	}

	/**
	 * A background: its layers (images, gradients, colour overlays), top
	 * first, over its colour.
	 */
	private static function background( array $value ): ?string {
		$layers = array();
		foreach ( (array) ( $value['background-overlay'] ?? array() ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$css = self::background_layer( $layer );
			if ( null === $css ) {
				return null; // A layer that cannot be written: leave it to the report.
			}
			$layers[] = $css;
		}
		$color = isset( $value['color'] ) && is_string( $value['color'] ) ? $value['color'] : '';
		if ( '' !== $color ) {
			$layers[] = $color;
		}
		return $layers ? implode( ', ', $layers ) : null;
	}

	private static function background_layer( array $layer ): ?string {
		if ( isset( $layer['image'] ) ) {
			$image = (array) $layer['image'];
			$src   = (array) ( $image['src'] ?? $image );
			$id    = (int) ( $src['id'] ?? 0 );
			$url   = $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
			if ( '' === $url ) {
				$url = is_string( $src['url'] ?? null ) ? $src['url'] : '';
			}
			$url = esc_url_raw( $url );
			if ( '' === $url ) {
				return null;
			}
			$words = function ( $part, string $default ) {
				return is_string( $part ) && preg_match( '/^[a-z0-9 %.-]+$/i', $part ) ? $part : $default;
			};
			$size = $layer['size'] ?? 'auto';
			$size = is_array( $size ) ? ( self::to_css( 'size', $size ) ?? 'auto' ) : $words( $size, 'auto' );
			$position = $layer['position'] ?? '0% 0%';
			$position = is_array( $position ) ? ( self::to_css( 'position', array_values( $position ) ) ?? '0% 0%' ) : $words( $position, '0% 0%' );
			return sprintf(
				'url("%1$s") %2$s / %3$s %4$s %5$s',
				str_replace( array( '"', '\\', ';', '{', '}' ), '', $url ),
				$position,
				$size,
				$words( $layer['repeat'] ?? 'repeat', 'repeat' ),
				$words( $layer['attachment'] ?? 'scroll', 'scroll' )
			);
		}
		if ( isset( $layer['stops'] ) && is_array( $layer['stops'] ) ) {
			$stops = array();
			foreach ( $layer['stops'] as $stop ) {
				if ( is_array( $stop ) && is_string( $stop['color'] ?? null ) ) {
					$stops[] = $stop['color'] . ( is_numeric( $stop['offset'] ?? null ) ? ' ' . ( 0 + $stop['offset'] ) . '%' : '' );
				}
			}
			if ( count( $stops ) < 2 ) {
				return null;
			}
			if ( 'radial' === ( $layer['type'] ?? '' ) ) {
				$at = is_string( $layer['positions'] ?? null ) ? $layer['positions'] : 'center center';
				return 'radial-gradient(circle at ' . $at . ', ' . implode( ', ', $stops ) . ')';
			}
			$angle = is_numeric( $layer['angle'] ?? null ) ? 0 + $layer['angle'] : 180;
			return 'linear-gradient(' . $angle . 'deg, ' . implode( ', ', $stops ) . ')';
		}
		if ( isset( $layer['color'] ) && is_string( $layer['color'] ) ) {
			return 'linear-gradient(' . $layer['color'] . ', ' . $layer['color'] . ')';
		}
		return null;
	}

	/**
	 * A transition: a list of { selection: { value: property }, size }.
	 */
	private static function transition( array $value ): ?string {
		$parts = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$selection = $item['selection'] ?? 'all';
			$property  = is_array( $selection ) ? ( $selection['value'] ?? 'all' ) : $selection;
			$duration  = is_array( $item['size'] ?? null ) ? self::to_css( 'transition', $item['size'] ) : null;
			if ( ! is_string( $property ) || ! preg_match( '/^[a-z-]+$/', $property ) || null === $duration ) {
				continue;
			}
			$parts[] = $property . ' ' . $duration;
		}
		return $parts ? implode( ', ', $parts ) : null;
	}
}
