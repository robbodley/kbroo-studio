<?php
/**
 * Base class for widgets.
 *
 * A widget declares its controls (see Controls) and renders its settings to
 * HTML on the front end. The editor shows a live preview with a matching
 * JavaScript renderer registered under the same name in assets/js/widgets.js.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Controls;

defined( 'ABSPATH' ) || exit;

abstract class Widget {

	/** @var array|null */
	private $controls = null;

	/** Unique machine name, e.g. "heading". */
	abstract public function get_name(): string;

	/** Label shown in the editor's widget panel. */
	abstract public function get_title(): string;

	/** Dashicon slug shown in the editor's widget panel. */
	public function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	/**
	 * The widget's own control definitions, keyed by setting name. See
	 * KbrooStudio\Controls for the schema. The Advanced tab is added for you.
	 */
	abstract protected function widget_controls(): array;

	/**
	 * Forget the built controls, so options that changed (fonts added by
	 * the Elementor converter) are picked up.
	 */
	/** @var string Id of the element being rendered ('' in editor previews). */
	protected $element_id = '';

	/** @var int Post whose layout is being rendered. */
	protected $post_id = 0;

	/**
	 * Which element and post the next render() is for. Set by Renderer;
	 * widgets that post back to the server (forms) need it.
	 */
	public function set_context( string $element_id, int $post_id ): void {
		$this->element_id = $element_id;
		$this->post_id    = $post_id;
	}

	public function flush_controls(): void {
		$this->controls = null;
	}

	/**
	 * All controls: the widget's own plus the shared Advanced tab.
	 */
	final public function get_controls(): array {
		if ( null === $this->controls ) {
			$this->controls = array_merge(
				$this->widget_controls(),
				ControlGroups::advanced( '_' ),
				ControlGroups::element_width(),
				ControlGroups::flex_item(),
				ControlGroups::border( '{{WRAPPER}}', array( 'tab' => 'advanced' ), '_border' ),
				ControlGroups::box_shadow( '{{WRAPPER}}', array( 'tab' => 'advanced' ), '_box_shadow' ),
				ControlGroups::background(
					'{{WRAPPER}}',
					array( 'tab' => 'advanced' ),
					'_background',
					array( 'hover' => '{{WRAPPER}}:hover' )
				)
			);
		}
		return $this->controls;
	}

	/**
	 * Render the widget's inner HTML. Settings are already sanitized and
	 * have defaults filled in; output must still be escaped. Styling comes
	 * from the controls' `selectors`, so markup should not inline styles.
	 */
	abstract public function render( array $settings ): string;

	/**
	 * Attributes for a link from a `url` control, or '' when it has no URL.
	 * The JavaScript twin is KbrooStudio.linkAttributes() in widgets.js.
	 *
	 * @param mixed $link { url, is_external, nofollow }.
	 */
	protected static function link_attributes( $link ): string {
		if ( ! is_array( $link ) || '' === ( $link['url'] ?? '' ) ) {
			return '';
		}
		$attributes = ' href="' . esc_url( $link['url'] ) . '"';
		$rel        = array();
		if ( 'on' === ( $link['is_external'] ?? '' ) ) {
			$attributes .= ' target="_blank"';
			$rel[]       = 'noopener';
		}
		if ( 'on' === ( $link['nofollow'] ?? '' ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$attributes .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}
		return $attributes;
	}

	/**
	 * Whether the editor previews this widget with HTML rendered on the
	 * server (for output only WordPress can produce, such as shortcodes)
	 * instead of a JavaScript renderer.
	 */
	public function server_preview(): bool {
		return false;
	}

	/** Whether the widget is offered in the editor's widgets panel. */
	public function in_panel(): bool {
		return true;
	}

	/** Text shown at the top of the widget's settings panel, or ''. */
	public function panel_notice(): string {
		return '';
	}

	/**
	 * Data sent to the editor.
	 */
	public function to_editor_config(): array {
		return array(
			'name'     => $this->get_name(),
			'title'    => $this->get_title(),
			'icon'     => $this->get_icon(),
			'controls' => Controls::for_editor( $this->get_controls() ),
			'server'   => $this->server_preview(),
			'panel'    => $this->in_panel(),
			'notice'   => $this->panel_notice(),
		);
	}
}
