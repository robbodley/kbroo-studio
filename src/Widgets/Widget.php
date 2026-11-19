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

defined( 'ABSPATH' ) || exit;

abstract class Widget {

	/** Unique machine name, e.g. "heading". */
	abstract public function get_name(): string;

	/** Label shown in the editor's widget panel. */
	abstract public function get_title(): string;

	/** Dashicon slug shown in the editor's widget panel. */
	public function get_icon(): string {
		return 'dashicons-admin-generic';
	}

	/**
	 * Control definitions keyed by setting name.
	 */
	abstract public function get_controls(): array;

	/**
	 * Render the widget's inner HTML. Settings are already sanitized and
	 * have defaults filled in; output must still be escaped.
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
			'controls' => $this->get_controls(),
		);
	}

	/**
	 * Shared alignment control.
	 */
	protected static function align_control( string $default = 'left' ): array {
		return array(
			'type'    => 'select',
			'label'   => __( 'Alignment', 'kbroo-studio' ),
			'default' => $default,
			'options' => array(
				'left'    => __( 'Left', 'kbroo-studio' ),
				'center'  => __( 'Center', 'kbroo-studio' ),
				'right'   => __( 'Right', 'kbroo-studio' ),
				'justify' => __( 'Justified', 'kbroo-studio' ),
			),
		);
	}

	/**
	 * Build a style attribute from already-validated values.
	 *
	 * @param array<string,string> $rules CSS property => value. Empty values are skipped.
	 */
	protected static function style_attr( array $rules ): string {
		$css = '';
		foreach ( $rules as $property => $value ) {
			if ( '' !== (string) $value ) {
				$css .= $property . ':' . $value . ';';
			}
		}
		return '' === $css ? '' : ' style="' . esc_attr( $css ) . '"';
	}
}
