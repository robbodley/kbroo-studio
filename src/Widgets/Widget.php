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
	 * All controls: the widget's own plus the shared Advanced tab.
	 */
	final public function get_controls(): array {
		if ( null === $this->controls ) {
			$this->controls = array_merge( $this->widget_controls(), ControlGroups::advanced( '_' ) );
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
	 * Data sent to the editor.
	 */
	public function to_editor_config(): array {
		return array(
			'name'     => $this->get_name(),
			'title'    => $this->get_title(),
			'icon'     => $this->get_icon(),
			'controls' => Controls::for_editor( $this->get_controls() ),
		);
	}
}
