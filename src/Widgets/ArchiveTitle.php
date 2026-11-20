<?php
/**
 * Archive title: the title of the archive being viewed ("Category: News",
 * "Search results for ...", or the blog page's title), styled like a
 * heading. For blog archive templates. Settings match Elementor Pro's
 * Archive Title widget.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class ArchiveTitle extends Heading {

	public function get_name(): string {
		return 'archive-title';
	}

	public function get_title(): string {
		return __( 'Archive title', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-heading';
	}

	public function server_preview(): bool {
		return true;
	}

	protected function widget_controls(): array {
		$controls = parent::widget_controls();
		unset( $controls['title'] );
		$controls['header_size']['section'] = __( 'Archive title', 'kbroo-studio' );
		$controls['header_size']['default'] = 'h1';
		return array(
			'include_context' => array(
				'type'        => 'switcher',
				'label'       => __( 'Say what kind of archive', 'kbroo-studio' ),
				'section'     => __( 'Archive title', 'kbroo-studio' ),
				'default'     => 'yes',
				'description' => __( 'e.g. "Category: News" rather than "News".', 'kbroo-studio' ),
			),
		) + $controls;
	}

	/**
	 * The title for the page being viewed.
	 */
	public static function current_title( bool $context ): string {
		if ( is_search() ) {
			/* translators: %s: search terms. */
			return $context ? sprintf( __( 'Search results for "%s"', 'kbroo-studio' ), get_search_query( false ) ) : get_search_query( false );
		}
		if ( is_home() ) {
			$blog = (int) get_option( 'page_for_posts' );
			return $blog ? get_the_title( $blog ) : __( 'Blog', 'kbroo-studio' );
		}
		if ( is_archive() ) {
			if ( ! $context && ( is_category() || is_tag() || is_tax() ) ) {
				return single_term_title( '', false );
			}
			return wp_strip_all_tags( get_the_archive_title() );
		}
		/* translators: Example archive title shown while editing a template. */
		return $context ? __( 'Category: News', 'kbroo-studio' ) : __( 'News', 'kbroo-studio' );
	}

	public function render( array $settings ): string {
		$tag = in_array( $settings['header_size'], self::TAGS, true ) ? $settings['header_size'] : 'h1';
		return sprintf( '<%1$s class="kbroo-heading kbroo-archive-title">%2$s</%1$s>', $tag, esc_html( self::current_title( 'yes' === $settings['include_context'] ) ) );
	}
}
