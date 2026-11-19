<?php
/**
 * Stand-in for an Elementor widget the converter cannot convert yet.
 *
 * It keeps the original Elementor element, so a later version of the
 * converter can convert it properly, and shows it on the page:
 *
 * - while Elementor is active, rendered live by Elementor, with its styles;
 * - otherwise, the HTML Elementor produced when the page was converted
 *   (a snapshot, so it looks right but forms and sliders may not work);
 * - with neither, nothing for visitors and a note for editors.
 *
 * Each one is listed in the conversion report for rebuilding.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

defined( 'ABSPATH' ) || exit;

class ElementorFallback extends Widget {

	public function get_name(): string {
		return 'elementor-fallback';
	}

	public function get_title(): string {
		return __( 'Not yet converted', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-warning';
	}

	/** Only the converter adds these; they are not in the widgets panel. */
	public function in_panel(): bool {
		return false;
	}

	public function server_preview(): bool {
		return true;
	}

	public function panel_notice(): string {
		return __( 'This part of the page came from an Elementor widget that Kbroo Studio cannot convert yet. It still shows on the page (live while Elementor is active, otherwise as it looked when converted). Rebuild it with Kbroo Studio widgets when you can, then delete this block.', 'kbroo-studio' );
	}

	protected function widget_controls(): array {
		$hidden = array(
			'hidden'  => true,
			'default' => '',
		);
		return array(
			'element_type'   => $hidden + array( 'type' => 'text', 'label' => 'Elementor widget' ),
			'element_label'  => $hidden + array( 'type' => 'text', 'label' => 'Label' ),
			'source_post'    => $hidden + array( 'type' => 'number', 'label' => 'Source post', 'min' => 0 ),
			'elementor_data' => $hidden + array( 'type' => 'code', 'label' => 'Elementor data' ),
			'snapshot'       => $hidden + array( 'type' => 'code', 'label' => 'Snapshot' ),
		);
	}

	public function render( array $settings ): string {
		$live = self::render_with_elementor( (string) $settings['elementor_data'], (int) $settings['source_post'] );
		if ( '' !== $live ) {
			return '<div class="kbroo-fallback">' . $live . '</div>';
		}
		if ( '' !== trim( (string) $settings['snapshot'] ) ) {
			return '<div class="kbroo-fallback">' . $settings['snapshot'] . '</div>';
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return sprintf(
				'<div class="kbroo-fallback kbroo-fallback--missing">%s</div>',
				esc_html(
					sprintf(
						/* translators: %s: Elementor widget name. */
						__( 'Not converted from Elementor: %s. Only editors see this note.', 'kbroo-studio' ),
						'' !== $settings['element_label'] ? $settings['element_label'] : $settings['element_type']
					)
				)
			);
		}
		return '';
	}

	/**
	 * Render an Elementor element with Elementor, or '' when Elementor is
	 * not active or fails. Wrapped in the source post's classes so its
	 * generated CSS (enqueued here) applies.
	 */
	public static function render_with_elementor( string $json, int $source_post ): string {
		if ( '' === $json || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		$element = json_decode( $json, true );
		if ( ! is_array( $element ) ) {
			return '';
		}
		$level = ob_get_level();
		try {
			$plugin   = \Elementor\Plugin::instance();
			$instance = $plugin->elements_manager->create_element_instance( $element );
			if ( ! $instance ) {
				return '';
			}
			if ( isset( $plugin->frontend ) && method_exists( $plugin->frontend, 'enqueue_styles' ) ) {
				$plugin->frontend->enqueue_styles();
			}
			if ( $source_post && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				\Elementor\Core\Files\CSS\Post::create( $source_post )->enqueue();
			}
			ob_start();
			$instance->print_element();
			$html = (string) ob_get_clean();
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			return '';
		}
		if ( '' === trim( $html ) ) {
			return '';
		}
		return sprintf( '<div class="elementor elementor-%d">%s</div>', $source_post, $html );
	}
}
