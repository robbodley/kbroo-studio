<?php
/**
 * Contact form. Settings follow Elementor Pro's form widget (form_fields,
 * button_text, email_to, success_message, ...). Submissions go to
 * KbrooStudio\Forms, which checks them against the settings saved in the
 * page (never against what the browser sends), emails them and keeps a copy.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio\Widgets;

use KbrooStudio\ControlGroups;
use KbrooStudio\Forms;
use KbrooStudio\Frontend;

defined( 'ABSPATH' ) || exit;

class Form extends Widget {

	const FIELD_TYPES = array( 'text', 'email', 'textarea', 'tel', 'url', 'number', 'date', 'time', 'select', 'radio', 'checkbox', 'acceptance', 'hidden' );

	const WIDTHS = array( '100', '80', '75', '66', '60', '50', '40', '33', '25', '20' );

	public function get_name(): string {
		return 'form';
	}

	public function get_title(): string {
		return __( 'Form', 'kbroo-studio' );
	}

	public function get_icon(): string {
		return 'dashicons-feedback';
	}

	public function server_preview(): bool {
		return true;
	}

	private static function field( string $type, string $label, string $id, bool $required, string $width = '100' ): array {
		return array(
			'_id'         => substr( md5( $id ), 0, 7 ),
			'field_type'  => $type,
			'field_label' => $label,
			'placeholder' => $label,
			'custom_id'   => $id,
			'required'    => $required ? 'yes' : '',
			'width'       => $width,
		);
	}

	protected function widget_controls(): array {
		$fields  = array( 'section' => __( 'Form fields', 'kbroo-studio' ) );
		$button  = array( 'section' => __( 'Button', 'kbroo-studio' ) );
		$email   = array( 'section' => __( 'Email', 'kbroo-studio' ) );
		$email_2 = array( 'section' => __( 'Second email', 'kbroo-studio' ) );
		$second  = array( 'condition' => array( 'email_2' => 'yes' ) ) + $email_2;
		$after   = array( 'section' => __( 'After sending', 'kbroo-studio' ) );
		$texts   = array( 'section' => __( 'Messages', 'kbroo-studio' ) );
		$style   = function ( string $section ) {
			return array(
				'tab'     => 'style',
				'section' => $section,
			);
		};
		$widths  = array();
		foreach ( self::WIDTHS as $w ) {
			$widths[ $w ] = $w . '%';
		}
		$choices = array( 'condition' => array( 'field_type' => array( 'select', 'radio', 'checkbox' ) ) );
		$input   = '{{WRAPPER}} .kbroo-form__input';
		$btn     = '{{WRAPPER}} .kbroo-form__button';
		$label   = '{{WRAPPER}} .kbroo-form__label';

		return array_merge(
			array(
				'form_name'   => $fields + array(
					'type'    => 'text',
					'label'   => __( 'Form name', 'kbroo-studio' ),
					'default' => __( 'Contact form', 'kbroo-studio' ),
				),
				'form_fields' => $fields + array(
					'type'        => 'repeater',
					'label'       => __( 'Fields', 'kbroo-studio' ),
					'item_label'  => __( 'Field', 'kbroo-studio' ),
					'title_field' => 'field_label',
					'default'     => array(
						self::field( 'text', __( 'Name', 'kbroo-studio' ), 'name', false ),
						self::field( 'email', __( 'Email', 'kbroo-studio' ), 'email', true ),
						self::field( 'textarea', __( 'Message', 'kbroo-studio' ), 'message', false ),
					),
					'fields'      => array(
						'field_type'      => array(
							'type'    => 'select',
							'label'   => __( 'Type', 'kbroo-studio' ),
							'default' => 'text',
							'options' => array(
								'text'       => __( 'Text', 'kbroo-studio' ),
								'email'      => __( 'Email', 'kbroo-studio' ),
								'textarea'   => __( 'Long text', 'kbroo-studio' ),
								'tel'        => __( 'Phone', 'kbroo-studio' ),
								'url'        => __( 'Web address', 'kbroo-studio' ),
								'number'     => __( 'Number', 'kbroo-studio' ),
								'date'       => __( 'Date', 'kbroo-studio' ),
								'time'       => __( 'Time', 'kbroo-studio' ),
								'select'     => __( 'Drop-down', 'kbroo-studio' ),
								'radio'      => __( 'Radio buttons', 'kbroo-studio' ),
								'checkbox'   => __( 'Checkboxes', 'kbroo-studio' ),
								'acceptance' => __( 'Consent tick box', 'kbroo-studio' ),
								'hidden'     => __( 'Hidden', 'kbroo-studio' ),
							),
						),
						'field_label'     => array(
							'type'    => 'text',
							'label'   => __( 'Label', 'kbroo-studio' ),
							'default' => '',
						),
						'placeholder'     => array(
							'type'      => 'text',
							'label'     => __( 'Placeholder', 'kbroo-studio' ),
							'default'   => '',
							'condition' => array( 'field_type' => array( 'text', 'email', 'textarea', 'tel', 'url', 'number' ) ),
						),
						'field_options'   => $choices + array(
							'type'        => 'textarea',
							'label'       => __( 'Options', 'kbroo-studio' ),
							'description' => __( 'One per line. To send a different value, write "Label|value".', 'kbroo-studio' ),
							'rows'        => 4,
							'default'     => '',
						),
						'allow_multiple'  => array(
							'type'      => 'switcher',
							'label'     => __( 'Allow more than one', 'kbroo-studio' ),
							'condition' => array( 'field_type' => 'select' ),
						),
						'acceptance_text' => array(
							'type'      => 'textarea',
							'label'     => __( 'Consent text', 'kbroo-studio' ),
							'html'      => true,
							'rows'      => 3,
							'default'   => '',
							'condition' => array( 'field_type' => 'acceptance' ),
						),
						'rows'            => array(
							'type'      => 'number',
							'label'     => __( 'Rows', 'kbroo-studio' ),
							'default'   => 4,
							'min'       => 1,
							'max'       => 30,
							'condition' => array( 'field_type' => 'textarea' ),
						),
						'required'        => array(
							'type'      => 'switcher',
							'label'     => __( 'Required', 'kbroo-studio' ),
							'condition' => array( 'field_type!' => 'hidden' ),
						),
						'width'           => array(
							'type'       => 'select',
							'label'      => __( 'Column width', 'kbroo-studio' ),
							'default'    => '100',
							'default_mobile' => '100', // Full width on phones, as in Elementor.
							'responsive' => true,
							'options'    => $widths,
							'condition'  => array( 'field_type!' => 'hidden' ),
							'selectors'  => array( '{{WRAPPER}} {{CURRENT_ITEM}}' => 'width: {{VALUE}}%;' ),
						),
						'field_value'     => array(
							'type'    => 'text',
							'label'   => __( 'Default value', 'kbroo-studio' ),
							'default' => '',
						),
						'custom_id'       => array(
							'type'        => 'text',
							'label'       => __( 'ID', 'kbroo-studio' ),
							'description' => __( 'Letters, numbers and underscores. Use it in the email as [field id="…"].', 'kbroo-studio' ),
							'default'     => '',
							'sanitize'    => 'id',
						),
					),
				),
				'show_labels' => $fields + array(
					'type'    => 'switcher',
					'label'   => __( 'Show labels', 'kbroo-studio' ),
					'default' => 'yes',
				),
				'mark_required' => $fields + array(
					'type'  => 'switcher',
					'label' => __( 'Mark required fields with *', 'kbroo-studio' ),
				),
				'input_size'  => $fields + array(
					'type'    => 'select',
					'label'   => __( 'Field size', 'kbroo-studio' ),
					'default' => 'sm',
					'options' => array(
						'xs' => __( 'Extra small', 'kbroo-studio' ),
						'sm' => __( 'Small', 'kbroo-studio' ),
						'md' => __( 'Medium', 'kbroo-studio' ),
						'lg' => __( 'Large', 'kbroo-studio' ),
						'xl' => __( 'Extra large', 'kbroo-studio' ),
					),
				),

				'button_text'  => $button + array(
					'type'    => 'text',
					'label'   => __( 'Text', 'kbroo-studio' ),
					'default' => __( 'Send', 'kbroo-studio' ),
				),
				'button_size'  => $button + array(
					'type'    => 'select',
					'label'   => __( 'Size', 'kbroo-studio' ),
					'default' => 'sm',
					'options' => array(
						'xs' => __( 'Extra small', 'kbroo-studio' ),
						'sm' => __( 'Small', 'kbroo-studio' ),
						'md' => __( 'Medium', 'kbroo-studio' ),
						'lg' => __( 'Large', 'kbroo-studio' ),
						'xl' => __( 'Extra large', 'kbroo-studio' ),
					),
				),
				'button_width' => $button + array(
					'type'       => 'select',
					'label'      => __( 'Column width', 'kbroo-studio' ),
					'default'    => '100',
					'default_mobile' => '100',
					'responsive' => true,
					'options'    => $widths,
					'selectors'  => array( '{{WRAPPER}} .kbroo-form__field--submit' => 'width: {{VALUE}}%;' ),
				),
				'button_align' => $button + array(
					'type'                 => 'select',
					'label'                => __( 'Alignment', 'kbroo-studio' ),
					'default'              => 'stretch',
					'responsive'           => true,
					'options'              => array(
						'start'   => __( 'Left', 'kbroo-studio' ),
						'center'  => __( 'Center', 'kbroo-studio' ),
						'end'     => __( 'Right', 'kbroo-studio' ),
						'stretch' => __( 'Full width', 'kbroo-studio' ),
					),
					'selectors_dictionary' => array(
						'start'   => 'flex-start',
						'center'  => 'center',
						'end'     => 'flex-end',
						'stretch' => 'stretch',
					),
					'selectors'            => array( '{{WRAPPER}} .kbroo-form__field--submit' => 'align-items: {{VALUE}};' ),
				),

				'email_to'        => $email + array(
					'type'        => 'text',
					'label'       => __( 'Send to', 'kbroo-studio' ),
					'default'     => '',
					/* translators: %s: the site's admin email address. */
					'description' => sprintf( __( 'Separate addresses with commas. Leave empty to use the site address (%s).', 'kbroo-studio' ), get_option( 'admin_email' ) ),
				),
				'email_subject'   => $email + array(
					'type'    => 'text',
					'label'   => __( 'Subject', 'kbroo-studio' ),
					/* translators: %s: site name. */
					'default' => sprintf( __( 'New message from "%s"', 'kbroo-studio' ), get_bloginfo( 'name' ) ),
				),
				'email_content'   => $email + array(
					'type'        => 'textarea',
					'label'       => __( 'Message', 'kbroo-studio' ),
					'default'     => '[all-fields]',
					'rows'        => 4,
					'description' => __( '[all-fields] lists every field; [field id="email"] inserts one.', 'kbroo-studio' ),
				),
				'email_from_name' => $email + array(
					'type'    => 'text',
					'label'   => __( 'From name', 'kbroo-studio' ),
					'default' => get_bloginfo( 'name' ),
				),
				'email_from'      => $email + array(
					'type'        => 'text',
					'label'       => __( 'From address', 'kbroo-studio' ),
					'default'     => '',
					'description' => __( 'Leave empty for WordPress\'s own. Use an address at this site\'s domain, or mail may be marked as spam.', 'kbroo-studio' ),
				),
				'email_reply_to'  => $email + array(
					'type'        => 'text',
					'label'       => __( 'Reply to (field ID)', 'kbroo-studio' ),
					'default'     => 'email',
					'description' => __( 'Replies go to the address typed in this field.', 'kbroo-studio' ),
				),
				'email_to_cc'     => $email + array(
					'type'    => 'text',
					'label'   => __( 'Cc', 'kbroo-studio' ),
					'default' => '',
				),
				'email_to_bcc'    => $email + array(
					'type'    => 'text',
					'label'   => __( 'Bcc', 'kbroo-studio' ),
					'default' => '',
				),

				'email_2'           => $email_2 + array(
					'type'        => 'switcher',
					'label'       => __( 'Send a second email', 'kbroo-studio' ),
					'default'     => '',
					'description' => __( 'For example a confirmation to the sender: send it to [field id="email"].', 'kbroo-studio' ),
				),
				'email_to_2'        => $second + array(
					'type'        => 'text',
					'label'       => __( 'Send to', 'kbroo-studio' ),
					'default'     => '',
					'description' => __( 'Addresses separated by commas; [field id="email"] uses the address typed in that field.', 'kbroo-studio' ),
				),
				'email_subject_2'   => $second + array(
					'type'    => 'text',
					'label'   => __( 'Subject', 'kbroo-studio' ),
					/* translators: %s: site name. */
					'default' => sprintf( __( 'New message from "%s"', 'kbroo-studio' ), get_bloginfo( 'name' ) ),
				),
				'email_content_2'   => $second + array(
					'type'    => 'textarea',
					'label'   => __( 'Message', 'kbroo-studio' ),
					'default' => '[all-fields]',
					'rows'    => 4,
				),
				'email_from_name_2' => $second + array(
					'type'    => 'text',
					'label'   => __( 'From name', 'kbroo-studio' ),
					'default' => get_bloginfo( 'name' ),
				),
				'email_from_2'      => $second + array(
					'type'    => 'text',
					'label'   => __( 'From address', 'kbroo-studio' ),
					'default' => '',
				),
				'email_reply_to_2'  => $second + array(
					'type'        => 'text',
					'label'       => __( 'Reply to', 'kbroo-studio' ),
					'default'     => '',
					'description' => __( 'An address, or a field ID whose typed address replies go to.', 'kbroo-studio' ),
				),
				'email_to_cc_2'     => $second + array(
					'type'    => 'text',
					'label'   => __( 'Cc', 'kbroo-studio' ),
					'default' => '',
				),
				'email_to_bcc_2'    => $second + array(
					'type'    => 'text',
					'label'   => __( 'Bcc', 'kbroo-studio' ),
					'default' => '',
				),

				'save_submissions' => $after + array(
					'type'        => 'switcher',
					'label'       => __( 'Keep a copy of each message', 'kbroo-studio' ),
					'default'     => 'yes',
					'description' => __( 'Listed under Kbroo Studio > Form messages, so nothing is lost if an email does not arrive.', 'kbroo-studio' ),
				),
				'redirect_to'      => $after + array(
					'type'  => 'url',
					'label' => __( 'Go to this page after sending', 'kbroo-studio' ),
				),

				'success_message'        => $texts + array(
					'type'    => 'text',
					'label'   => __( 'Sent', 'kbroo-studio' ),
					'default' => __( 'Thanks, your message has been sent.', 'kbroo-studio' ),
				),
				'error_message'          => $texts + array(
					'type'    => 'text',
					'label'   => __( 'Could not send', 'kbroo-studio' ),
					'default' => __( 'Sorry, your message could not be sent. Please try again.', 'kbroo-studio' ),
				),
				'server_message'         => $texts + array(
					'type'        => 'text',
					'label'       => __( 'Email failed', 'kbroo-studio' ),
					'default'     => '',
					'description' => __( 'Shown when the message is not saved and the email could not go. Empty uses "Could not send".', 'kbroo-studio' ),
				),
				'required_field_message' => $texts + array(
					'type'    => 'text',
					'label'   => __( 'Required field missing', 'kbroo-studio' ),
					'default' => __( 'This field is required.', 'kbroo-studio' ),
				),
				'invalid_message'        => $texts + array(
					'type'    => 'text',
					'label'   => __( 'Something is wrong', 'kbroo-studio' ),
					'default' => __( 'Please check the highlighted fields.', 'kbroo-studio' ),
				),

				'column_gap'    => $style( __( 'Form', 'kbroo-studio' ) ) + array(
					'type'      => 'slider',
					'label'     => __( 'Columns gap', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-form' => '--kbroo-form-col-gap: {{SIZE}}{{UNIT}};' ),
				),
				'row_gap'       => $style( __( 'Form', 'kbroo-studio' ) ) + array(
					'type'      => 'slider',
					'label'     => __( 'Rows gap', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-form' => '--kbroo-form-row-gap: {{SIZE}}{{UNIT}};' ),
				),
				'label_spacing' => $style( __( 'Labels', 'kbroo-studio' ) ) + array(
					'type'      => 'slider',
					'label'     => __( 'Space below', 'kbroo-studio' ),
					'units'     => array( 'px', 'em' ),
					'selectors' => array( $label => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
				),
				'label_color'   => $style( __( 'Labels', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Colour', 'kbroo-studio' ),
					'selectors' => array( $label . ', {{WRAPPER}} .kbroo-form__choice' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'label_typography', $label, $style( __( 'Labels', 'kbroo-studio' ) ) ),
			array(
				'field_text_color'       => $style( __( 'Fields', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $input => 'color: {{VALUE}};' ),
				),
				'field_background_color' => $style( __( 'Fields', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Background colour', 'kbroo-studio' ),
					'selectors' => array( $input => 'background-color: {{VALUE}};' ),
				),
				'field_border_color'     => $style( __( 'Fields', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Border colour', 'kbroo-studio' ),
					'selectors' => array( $input => 'border-color: {{VALUE}};' ),
				),
				'field_border_width'     => $style( __( 'Fields', 'kbroo-studio' ) ) + array(
					'type'      => 'dimensions',
					'label'     => __( 'Border width', 'kbroo-studio' ),
					'units'     => array( 'px' ),
					'selectors' => array( $input => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'field_border_radius'    => $style( __( 'Fields', 'kbroo-studio' ) ) + array(
					'type'      => 'dimensions',
					'label'     => __( 'Corner radius', 'kbroo-studio' ),
					'units'     => array( 'px', '%' ),
					'selectors' => array( $input => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
			),
			ControlGroups::typography( 'field_typography', $input, $style( __( 'Fields', 'kbroo-studio' ) ) ),
			array(
				'button_background_color'       => $style( __( 'Button', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Background colour', 'kbroo-studio' ),
					'selectors' => array( $btn => 'background-color: {{VALUE}};' ),
				),
				'button_text_color'             => $style( __( 'Button', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $btn => 'color: {{VALUE}};' ),
				),
				'button_border_radius'          => $style( __( 'Button', 'kbroo-studio' ) ) + array(
					'type'      => 'dimensions',
					'label'     => __( 'Corner radius', 'kbroo-studio' ),
					'units'     => array( 'px', '%' ),
					'selectors' => array( $btn => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				),
				'button_background_hover_color' => $style( __( 'Button on hover', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Background colour', 'kbroo-studio' ),
					'selectors' => array( $btn . ':hover, ' . $btn . ':focus-visible' => 'background-color: {{VALUE}};' ),
				),
				'button_hover_color'            => $style( __( 'Button on hover', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Text colour', 'kbroo-studio' ),
					'selectors' => array( $btn . ':hover, ' . $btn . ':focus-visible' => 'color: {{VALUE}};' ),
				),
			),
			ControlGroups::typography( 'button_typography', $btn, $style( __( 'Button', 'kbroo-studio' ) ) ),
			ControlGroups::border( $btn, $style( __( 'Button', 'kbroo-studio' ) ), 'button_border' ),
			array(
				'success_message_color' => $style( __( 'Messages', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Sent', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-form__message.is-success' => 'color: {{VALUE}};' ),
				),
				'error_message_color'   => $style( __( 'Messages', 'kbroo-studio' ) ) + array(
					'type'      => 'color',
					'label'     => __( 'Errors', 'kbroo-studio' ),
					'selectors' => array( '{{WRAPPER}} .kbroo-form__message.is-error, {{WRAPPER}} .kbroo-form__error' => 'color: {{VALUE}};' ),
				),
			)
		);
	}

	/**
	 * The name a field is posted under: its ID, or one made from its _id.
	 */
	public static function field_name( array $field ): string {
		$id = preg_replace( '/[^A-Za-z0-9_]/', '', (string) ( $field['custom_id'] ?? '' ) );
		return '' !== $id ? $id : 'field_' . preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $field['_id'] ?? '' ) );
	}

	/**
	 * Names for all fields, by _id, made unique: a repeated ID (easy to
	 * leave behind when duplicating a field) falls back to field_<_id>.
	 *
	 * @return array<string,string>
	 */
	public static function field_names( array $fields ): array {
		$names = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$key  = (string) ( $field['_id'] ?? '' );
			$name = self::field_name( $field );
			if ( in_array( $name, $names, true ) ) {
				$name = 'field_' . preg_replace( '/[^A-Za-z0-9]/', '', $key );
			}
			$names[ $key ] = $name;
		}
		return $names;
	}

	/**
	 * Options of a select, radio or checkbox field: [ [ label, value ], ... ].
	 */
	public static function field_options( array $field ): array {
		$options = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $field['field_options'] ?? '' ) ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts     = explode( '|', $line, 2 );
			$options[] = array( trim( $parts[0] ), trim( $parts[1] ?? $parts[0] ) );
		}
		return $options;
	}

	public function render( array $settings ): string {
		$fields = array_filter(
			(array) $settings['form_fields'],
			function ( $field ) {
				return is_array( $field ) && in_array( $field['field_type'] ?? '', self::FIELD_TYPES, true );
			}
		);
		if ( ! $fields ) {
			return '';
		}
		Frontend::enqueue_script();

		$form_id = '' !== $this->element_id ? $this->element_id : 'preview';
		$labels  = 'yes' === $settings['show_labels'];
		$size    = in_array( $settings['input_size'], array( 'xs', 'sm', 'md', 'lg', 'xl' ), true ) ? $settings['input_size'] : 'sm';
		$html    = '';
		$names   = self::field_names( $fields );
		foreach ( $fields as $field ) {
			$html .= $this->render_field( $field, $names[ (string) ( $field['_id'] ?? '' ) ] ?? self::field_name( $field ), $form_id, $labels, 'yes' === $settings['mark_required'] );
		}

		$button_size = in_array( $settings['button_size'], array( 'xs', 'sm', 'md', 'lg', 'xl' ), true ) ? $settings['button_size'] : 'sm';
		$html       .= sprintf(
			'<div class="kbroo-form__field kbroo-form__field--submit"><button type="submit" class="kbroo-button kbroo-button--%1$s kbroo-form__button">%2$s</button></div>',
			$button_size,
			esc_html( $settings['button_text'] )
		);

		// A message after a submission without JavaScript.
		$status  = '';
		$classes = 'kbroo-form__message';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which saved message to show.
		$sent = isset( $_GET['kbroo-form'], $_GET['kbroo-form-id'] ) && sanitize_key( wp_unslash( $_GET['kbroo-form-id'] ) ) === $form_id ? sanitize_key( wp_unslash( $_GET['kbroo-form'] ) ) : '';
		if ( 'sent' === $sent ) {
			$status   = $settings['success_message'];
			$classes .= ' is-success';
		} elseif ( 'error' === $sent ) {
			$status   = $settings['error_message'];
			$classes .= ' is-error';
		}

		$hidden = sprintf(
			'<input type="hidden" name="action" value="%1$s" /><input type="hidden" name="kbroo_form_post" value="%2$d" /><input type="hidden" name="kbroo_form_id" value="%3$s" /><input type="hidden" name="kbroo_form_token" value="%4$s" />',
			esc_attr( Forms::ACTION ),
			(int) $this->post_id,
			esc_attr( $form_id ),
			esc_attr( Forms::token() )
		);
		// Spam trap: people never see or fill this field; bots often do.
		$trap = '<div class="kbroo-form__trap" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'kbroo-studio' ) . ' <input type="text" name="' . esc_attr( Forms::TRAP ) . '" value="" tabindex="-1" autocomplete="off" /></label></div>';

		return sprintf(
			'<form class="kbroo-form kbroo-form--size-%1$s" id="kbroo-form-%2$s" method="post" action="%3$s" data-kbroo-form="%4$s" aria-label="%5$s">%6$s%7$s<div class="kbroo-form__fields">%8$s</div><div class="%9$s" role="status" aria-live="polite">%10$s</div></form>',
			$size,
			esc_attr( $form_id ),
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( wp_json_encode( array( 'invalid' => $settings['invalid_message'], 'error' => $settings['error_message'] ) ) ),
			esc_attr( $settings['form_name'] ),
			$hidden,
			$trap,
			$html,
			esc_attr( $classes ),
			esc_html( $status )
		);
	}

	private function render_field( array $field, string $name, string $form_id, bool $labels, bool $mark ): string {
		$type     = $field['field_type'];
		$id       = 'kbroo-form-' . $form_id . '-' . $name;
		$label    = (string) ( $field['field_label'] ?? '' );
		$required = 'yes' === ( $field['required'] ?? '' ) && 'hidden' !== $type;
		$req_attr = $required ? ' required aria-required="true"' : '';
		$value    = (string) ( $field['field_value'] ?? '' );
		$item     = 'kbroo-repeater-item-' . preg_replace( '/[^A-Za-z0-9]/', '', (string) ( $field['_id'] ?? '' ) );

		if ( 'hidden' === $type ) {
			return '<input type="hidden" name="form_fields[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ) . '" />';
		}

		$label_html = '';
		if ( '' !== $label && 'acceptance' !== $type ) {
			$label_html = sprintf(
				'<%1$s class="kbroo-form__label%2$s"%3$s>%4$s%5$s</%1$s>',
				in_array( $type, array( 'radio', 'checkbox' ), true ) ? 'span' : 'label',
				$labels ? '' : ' kbroo-sr-only',
				in_array( $type, array( 'radio', 'checkbox' ), true ) ? ' id="' . esc_attr( $id ) . '-label"' : ' for="' . esc_attr( $id ) . '"',
				esc_html( $label ),
				$required && $mark ? ' <span class="kbroo-form__required" aria-hidden="true">*</span>' : ''
			);
		}
		$placeholder = '' !== ( $field['placeholder'] ?? '' ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
		$common      = ' id="' . esc_attr( $id ) . '" name="form_fields[' . esc_attr( $name ) . ']"' . $req_attr;

		switch ( $type ) {
			case 'textarea':
				$control = '<textarea class="kbroo-form__input"' . $common . $placeholder . ' rows="' . max( 1, min( 30, (int) ( $field['rows'] ?? 4 ) ) ) . '">' . esc_textarea( $value ) . '</textarea>';
				break;

			case 'select':
				$multiple = 'yes' === ( $field['allow_multiple'] ?? '' );
				$control  = '<select class="kbroo-form__input" id="' . esc_attr( $id ) . '" name="form_fields[' . esc_attr( $name ) . ']' . ( $multiple ? '[]" multiple' : '"' ) . $req_attr . '>';
				foreach ( self::field_options( $field ) as $option ) {
					$control .= '<option value="' . esc_attr( $option[1] ) . '"' . selected( $value, $option[1], false ) . '>' . esc_html( $option[0] ) . '</option>';
				}
				$control .= '</select>';
				break;

			case 'radio':
			case 'checkbox':
				$control = '<div class="kbroo-form__choices" role="' . ( 'radio' === $type ? 'radiogroup' : 'group' ) . '" aria-labelledby="' . esc_attr( $id ) . '-label">';
				foreach ( self::field_options( $field ) as $i => $option ) {
					$control .= sprintf(
						'<label class="kbroo-form__choice"><input type="%1$s" name="form_fields[%2$s]%3$s" value="%4$s"%5$s%6$s /> %7$s</label>',
						$type,
						esc_attr( $name ),
						'checkbox' === $type ? '[]' : '',
						esc_attr( $option[1] ),
						checked( $value, $option[1], false ),
						$required && 'radio' === $type ? ' required' : '',
						esc_html( $option[0] )
					);
				}
				$control .= '</div>';
				break;

			case 'acceptance':
				$text    = '' !== ( $field['acceptance_text'] ?? '' ) ? wp_kses( $field['acceptance_text'], Heading::INLINE_HTML ) : esc_html( $label );
				$control = '<label class="kbroo-form__choice kbroo-form__acceptance"><input type="checkbox" value="on"' . $common . ' /> ' . $text . ( $required && $mark ? ' <span class="kbroo-form__required" aria-hidden="true">*</span>' : '' ) . '</label>';
				break;

			default:
				$control = '<input class="kbroo-form__input" type="' . esc_attr( $type ) . '"' . $common . $placeholder . ' value="' . esc_attr( $value ) . '"' . ( 'email' === $type ? ' autocomplete="email"' : '' ) . ( 'tel' === $type ? ' autocomplete="tel"' : '' ) . ' />';
		}

		return sprintf(
			'<div class="kbroo-form__field kbroo-form__field--%1$s %2$s" data-field="%3$s">%4$s%5$s<span class="kbroo-form__error" hidden></span></div>',
			esc_attr( $type ),
			esc_attr( $item ),
			esc_attr( $name ),
			$label_html,
			$control
		);
	}
}
