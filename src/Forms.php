<?php
/**
 * Form submissions: checks, spam protection, email and a saved copy.
 *
 * A submission names the page and the form element. The form's settings are
 * read from that page's saved layout, so the recipients, fields and
 * messages cannot be changed by the visitor. Spam protection needs no
 * outside service: a hidden trap field, a signed time stamp (forms sent
 * within a few seconds of loading are refused) and a limit per visitor.
 *
 * Saved messages are a private post type listed under Kbroo Studio > Form messages
 * for administrators.
 *
 * @package KbrooStudio
 */

namespace KbrooStudio;

use KbrooStudio\Widgets\Form;
use KbrooStudio\Widgets\Registry;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class Forms {

	const ACTION    = 'kbroo_form';
	const TRAP      = 'kbroo_website';
	const POST_TYPE = 'kbroo_submission';

	/** Fewest seconds between loading a form and sending it. */
	const MIN_SECONDS = 3;

	/** Most submissions per visitor per 10 minutes. */
	const RATE_LIMIT = 5;

	/** Most characters kept per field. */
	const MAX_LENGTH = 5000;

	/** @var Registry */
	private $widgets;

	public function __construct( Registry $widgets ) {
		$this->widgets = $widgets;
	}

	public function hooks(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'meta_boxes' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	public function register_post_type(): void {
		$admin = 'manage_options';
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Form messages', 'kbroo-studio' ),
					'singular_name' => __( 'Form message', 'kbroo-studio' ),
					'menu_name'     => __( 'Form messages', 'kbroo-studio' ),
					'all_items'     => __( 'Form messages', 'kbroo-studio' ),
					'edit_item'     => __( 'Form message', 'kbroo-studio' ),
					'search_items'  => __( 'Search messages', 'kbroo-studio' ),
					'not_found'     => __( 'No messages yet.', 'kbroo-studio' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => Admin::MENU,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'map_meta_cap'    => false,
				'capabilities'    => array(
					'edit_post'          => $admin,
					'read_post'          => $admin,
					'delete_post'        => $admin,
					'edit_posts'         => $admin,
					'edit_others_posts'  => $admin,
					'delete_posts'       => $admin,
					'publish_posts'      => 'do_not_allow',
					'read_private_posts' => $admin,
					'create_posts'       => 'do_not_allow',
				),
				'rewrite'         => false,
				'query_var'       => false,
			)
		);
	}

	/**
	 * A signed time stamp for a form being shown.
	 */
	public static function token(): string {
		$time = (string) time();
		return $time . '.' . substr( wp_hash( 'kbroo-form|' . $time, 'nonce' ), 0, 20 );
	}

	/**
	 * Seconds since a token was made, or -1 if it is not genuine.
	 */
	public static function token_age( string $token ): int {
		if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{20})$/', $token, $m ) ) {
			return -1;
		}
		if ( ! hash_equals( substr( wp_hash( 'kbroo-form|' . $m[1], 'nonce' ), 0, 20 ), $m[2] ) ) {
			return -1;
		}
		return time() - (int) $m[1];
	}

	/**
	 * The form element with this id in a layout, or null.
	 */
	public static function find_element( array $elements, string $id ): ?array {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ( $element['id'] ?? '' ) === $id && 'form' === ( $element['widgetType'] ?? '' ) ) {
				return $element;
			}
			$found = self::find_element( (array) ( $element['elements'] ?? array() ), $id );
			if ( $found ) {
				return $found;
			}
		}
		return null;
	}

	/**
	 * Handle a submission: JSON for the front-end script, a redirect
	 * otherwise.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public form; protected by the signed token, trap and rate limit below.
		$ajax    = ! empty( $_POST['kbroo_ajax'] );
		$post_id = absint( $_POST['kbroo_form_post'] ?? 0 );
		$form_id = sanitize_key( wp_unslash( $_POST['kbroo_form_id'] ?? '' ) );
		$token   = sanitize_text_field( wp_unslash( $_POST['kbroo_form_token'] ?? '' ) );
		$trap    = (string) wp_unslash( $_POST[ self::TRAP ] ?? '' );
		$raw     = isset( $_POST['form_fields'] ) && is_array( $_POST['form_fields'] ) ? wp_unslash( $_POST['form_fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized per field type below.
		// phpcs:enable

		$post     = get_post( $post_id );
		$element  = null;
		$settings = null;
		if ( $post && ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) ) && ! post_password_required( $post ) ) {
			$element = self::find_element( (array) ( Document::get_layout( $post )['elements'] ?? array() ), $form_id );
		}
		$widget = $this->widgets->get( 'form' );
		if ( ! $element || ! $widget ) {
			$this->respond( $ajax, $post, $form_id, false, __( 'This form is no longer on the page.', 'kbroo-studio' ) );
		}
		$settings = Controls::with_defaults( $widget->get_controls(), $element['settings'] ?? array() );

		// Spam checks. A trapped or too-quick submission gets the normal
		// "sent" reply so that bots learn nothing, but nothing is sent.
		$age = self::token_age( $token );
		if ( '' !== $trap || $age < self::MIN_SECONDS ) {
			$this->respond( $ajax, $post, $form_id, true, $settings['success_message'] );
		}
		list( $values, $errors ) = self::validate( $settings, $raw );
		if ( $errors ) {
			$this->respond( $ajax, $post, $form_id, false, $settings['invalid_message'], $errors );
		}
		// Only valid messages count towards the limit, so fixing a typo is never blocked.
		if ( ! $this->within_rate_limit() ) {
			$this->respond( $ajax, $post, $form_id, false, __( 'Too many messages in a short time. Please try again in a few minutes.', 'kbroo-studio' ) );
		}

		$sent = $this->send_email( $settings, $values, $post );
		if ( 'yes' === $settings['save_submissions'] ) {
			$this->save( $settings, $values, $post, $form_id, $sent );
		}

		/**
		 * Fires after a Kbroo Studio form is submitted and checked.
		 *
		 * @param array   $values   Field values by ID: [ label, value ].
		 * @param array   $settings The form's settings.
		 * @param WP_Post $post     The page with the form.
		 * @param bool    $sent     Whether the email was sent.
		 */
		do_action( 'kbroo_studio_form_submitted', $values, $settings, $post, $sent );

		if ( ! $sent && 'yes' !== $settings['save_submissions'] ) {
			$this->respond( $ajax, $post, $form_id, false, '' !== trim( (string) $settings['server_message'] ) ? $settings['server_message'] : $settings['error_message'] );
		}
		$redirect = Controls::sanitize_url( $settings['redirect_to']['url'] ?? '', array( 'http', 'https' ) );
		$this->respond( $ajax, $post, $form_id, true, $settings['success_message'], array(), $redirect );
	}

	/**
	 * Check and clean the posted values against the form's fields.
	 *
	 * @param mixed $raw Posted form_fields.
	 * @return array{0:array,1:array} values (id => [ label, value ]) and errors (id => message).
	 */
	public static function validate( array $settings, $raw ): array {
		$raw    = is_array( $raw ) ? $raw : array();
		$values = array();
		$errors = array();
		$names  = Form::field_names( (array) $settings['form_fields'] );
		foreach ( (array) $settings['form_fields'] as $field ) {
			if ( ! is_array( $field ) || ! in_array( $field['field_type'] ?? '', Form::FIELD_TYPES, true ) ) {
				continue;
			}
			$type     = $field['field_type'];
			$name     = $names[ (string) ( $field['_id'] ?? '' ) ] ?? Form::field_name( $field );
			$label    = '' !== ( $field['field_label'] ?? '' ) ? $field['field_label'] : ( '' !== ( $field['placeholder'] ?? '' ) ? $field['placeholder'] : $name );
			$required = 'yes' === ( $field['required'] ?? '' ) && 'hidden' !== $type;
			$input    = $raw[ $name ] ?? '';

			if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
				$allowed = array_column( Form::field_options( $field ), 1 );
				$picked  = array_values( array_intersect( array_map( 'strval', array_filter( (array) $input, 'is_scalar' ) ), $allowed ) );
				$multi   = 'checkbox' === $type || ( 'select' === $type && 'yes' === ( $field['allow_multiple'] ?? '' ) );
				$value   = $multi ? implode( ', ', $picked ) : ( $picked[0] ?? '' );
			} elseif ( is_array( $input ) ) {
				$value = '';
			} else {
				$input = mb_substr( (string) $input, 0, self::MAX_LENGTH );
				$value = 'textarea' === $type ? sanitize_textarea_field( $input ) : sanitize_text_field( $input );
			}

			if ( 'acceptance' === $type ) {
				$value = 'on' === $input ? __( 'Yes', 'kbroo-studio' ) : '';
			}

			if ( '' === $value ) {
				if ( $required ) {
					$errors[ $name ] = $settings['required_field_message'];
				}
			} elseif ( 'email' === $type && ! is_email( $value ) ) {
				$errors[ $name ] = __( 'Please enter a valid email address.', 'kbroo-studio' );
			} elseif ( 'url' === $type && '' === Controls::sanitize_url( $value, array( 'http', 'https' ) ) ) {
				$errors[ $name ] = __( 'Please enter a valid web address.', 'kbroo-studio' );
			} elseif ( 'number' === $type && ! is_numeric( $value ) ) {
				$errors[ $name ] = __( 'Please enter a number.', 'kbroo-studio' );
			} elseif ( 'date' === $type && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$errors[ $name ] = __( 'Please enter a date.', 'kbroo-studio' );
			} elseif ( 'time' === $type && ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $value ) ) {
				$errors[ $name ] = __( 'Please enter a time.', 'kbroo-studio' );
			}
			$values[ $name ] = array( $label, $value );
		}
		return array( $values, $errors );
	}

	/**
	 * Fill [all-fields] and [field id="x"] in a template.
	 *
	 * @param bool $html Whether the result is HTML.
	 */
	public static function fill( string $template, array $values, bool $html = false ): string {
		$lines = array();
		foreach ( $values as $pair ) {
			if ( '' !== $pair[1] ) {
				$lines[] = $html
					? '<p><strong>' . esc_html( $pair[0] ) . ':</strong><br>' . nl2br( esc_html( $pair[1] ) ) . '</p>'
					: $pair[0] . ': ' . $pair[1];
			}
		}
		$out = str_replace( '[all-fields]', implode( $html ? '' : "\n", $lines ), $template );
		return (string) preg_replace_callback(
			'/\[field id=["\']?([A-Za-z0-9_]+)["\']?\]/',
			function ( $m ) use ( $values, $html ) {
				$value = $values[ $m[1] ][1] ?? '';
				return $html ? nl2br( esc_html( $value ) ) : $value;
			},
			$out
		);
	}

	/**
	 * Comma-separated addresses, keeping only valid ones.
	 *
	 * @return string[]
	 */
	public static function addresses( string $list ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $list ) ), 'is_email' ) );
	}

	private function send_email( array $settings, array $values, WP_Post $post ): bool {
		$sent = $this->send_one( $settings, $values, $post, '' );
		if ( 'yes' === ( $settings['email_2'] ?? '' ) ) {
			$this->send_one( $settings, $values, $post, '_2' );
		}
		return $sent;
	}

	/**
	 * One of the form's emails: the main one ($suffix '') or the second
	 * ('_2'). Addresses may use [field id="..."]. The main email goes to
	 * the site address when it names none; the second is then not sent.
	 */
	private function send_one( array $settings, array $values, WP_Post $post, string $suffix ): bool {
		// Header values: no line breaks, so nothing can be added to the mail headers.
		$clean = function ( $value ) {
			return trim( str_replace( array( "\r", "\n" ), ' ', (string) $value ) );
		};
		$setting = function ( string $key ) use ( $settings, $suffix, $values, $clean ) {
			return $clean( self::fill( (string) ( $settings[ $key . $suffix ] ?? '' ), $values ) );
		};

		$to = self::addresses( $setting( 'email_to' ) );
		if ( ! $to ) {
			if ( '' !== $suffix ) {
				return false;
			}
			$to = array( get_option( 'admin_email' ) );
		}
		$subject = $setting( 'email_subject' );
		$body    = self::fill( (string) ( $settings[ 'email_content' . $suffix ] ?? '' ), $values );
		if ( '' === $suffix ) {
			/* translators: 1: page title, 2: page address. */
			$body .= "\n\n---\n" . sprintf( __( 'Sent from "%1$s" (%2$s)', 'kbroo-studio' ), $clean( get_the_title( $post ) ), get_permalink( $post ) );
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$from    = is_email( $setting( 'email_from' ) );
		if ( $from ) {
			$name      = $setting( 'email_from_name' );
			$headers[] = 'From: ' . ( '' !== $name ? '"' . str_replace( '"', '', $name ) . '" ' : '' ) . '<' . $from . '>';
		}
		// A field ID (the address typed there), or an address.
		$reply_key = (string) ( $settings[ 'email_reply_to' . $suffix ] ?? '' );
		$reply     = $values[ preg_replace( '/[^A-Za-z0-9_]/', '', $reply_key ) ][1] ?? $setting( 'email_reply_to' );
		if ( is_email( $reply ) ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		foreach ( array( 'Cc' => 'email_to_cc', 'Bcc' => 'email_to_bcc' ) as $header => $key ) {
			foreach ( self::addresses( $setting( $key ) ) as $address ) {
				$headers[] = $header . ': ' . $address;
			}
		}
		return (bool) wp_mail( $to, '' !== $subject ? $subject : get_bloginfo( 'name' ), $body, $headers );
	}

	private function save( array $settings, array $values, WP_Post $post, string $form_id, bool $sent ): void {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				/* translators: 1: form name, 2: page title. */
				'post_title'  => sprintf( __( '%1$s on "%2$s"', 'kbroo-studio' ), $settings['form_name'], get_the_title( $post ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return;
		}
		update_post_meta( $id, '_kbroo_fields', wp_slash( wp_json_encode( $values ) ) );
		update_post_meta( $id, '_kbroo_source', array( 'post' => $post->ID, 'form' => $form_id ) );
		update_post_meta( $id, '_kbroo_email_sent', $sent ? 'yes' : 'no' );
	}

	/**
	 * At most RATE_LIMIT submissions per visitor (by hashed IP address) per
	 * 10 minutes.
	 */
	private function within_rate_limit(): bool {
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key   = 'kbroo_form_' . substr( wp_hash( $ip ), 0, 16 );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Reply and stop.
	 *
	 * @param WP_Post|null $post Page with the form.
	 */
	private function respond( bool $ajax, $post, string $form_id, bool $success, string $message, array $errors = array(), string $redirect = '' ): void {
		if ( $ajax ) {
			wp_send_json(
				array(
					'success'  => $success,
					'message'  => $message,
					'errors'   => (object) $errors,
					'redirect' => $redirect,
				),
				$success ? 200 : 400
			);
		}
		if ( $success && '' !== $redirect ) {
			wp_safe_redirect( $redirect ); // Only to this site or allowed hosts.
			exit;
		}
		$back = $post ? get_permalink( $post ) : home_url( '/' );
		$back = add_query_arg(
			array(
				'kbroo-form'    => $success ? 'sent' : 'error',
				'kbroo-form-id' => $form_id,
			),
			$back
		) . '#kbroo-form-' . $form_id;
		wp_safe_redirect( $back, 303 );
		exit;
	}

	/* Admin screens ------------------------------------------------------- */

	public function meta_boxes(): void {
		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
		add_meta_box( 'kbroo-submission', __( 'Message', 'kbroo-studio' ), array( $this, 'render_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function render_meta_box( WP_Post $post ): void {
		$values = json_decode( (string) get_post_meta( $post->ID, '_kbroo_fields', true ), true );
		$source = (array) get_post_meta( $post->ID, '_kbroo_source', true );
		echo '<table class="widefat striped"><tbody>';
		foreach ( (array) $values as $pair ) {
			echo '<tr><th style="width:25%">' . esc_html( $pair[0] ?? '' ) . '</th><td>' . nl2br( esc_html( $pair[1] ?? '' ) ) . '</td></tr>';
		}
		echo '</tbody></table><p>';
		/* translators: %s: date and time. */
		echo esc_html( sprintf( __( 'Received %s.', 'kbroo-studio' ), get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ) ) . ' ';
		if ( ! empty( $source['post'] ) && get_post( $source['post'] ) ) {
			/* translators: %s: page title. */
			echo wp_kses_post( sprintf( __( 'From %s.', 'kbroo-studio' ), '<a href="' . esc_url( get_permalink( $source['post'] ) ) . '">' . esc_html( get_the_title( $source['post'] ) ) . '</a>' ) ) . ' ';
		}
		echo esc_html( 'yes' === get_post_meta( $post->ID, '_kbroo_email_sent', true ) ? __( 'The email was sent.', 'kbroo-studio' ) : __( 'The email could not be sent.', 'kbroo-studio' ) );
		echo '</p>';
	}

	/**
	 * @param array $columns List table columns.
	 */
	public function columns( $columns ): array {
		return array(
			'cb'           => $columns['cb'] ?? '',
			'title'        => __( 'Form', 'kbroo-studio' ),
			'kbroo_from'   => __( 'From', 'kbroo-studio' ),
			'kbroo_email'  => __( 'Email sent', 'kbroo-studio' ),
			'date'         => __( 'Date', 'kbroo-studio' ),
		);
	}

	/**
	 * @param string $column  Column name.
	 * @param int    $post_id Submission.
	 */
	public function column( $column, $post_id ): void {
		if ( 'kbroo_email' === $column ) {
			echo esc_html( 'yes' === get_post_meta( $post_id, '_kbroo_email_sent', true ) ? __( 'Yes', 'kbroo-studio' ) : __( 'No', 'kbroo-studio' ) );
		}
		if ( 'kbroo_from' === $column ) {
			$values = (array) json_decode( (string) get_post_meta( $post_id, '_kbroo_fields', true ), true );
			$shown  = array();
			foreach ( $values as $pair ) {
				if ( '' !== ( $pair[1] ?? '' ) && count( $shown ) < 2 ) {
					$shown[] = mb_substr( (string) $pair[1], 0, 60 );
				}
			}
			echo esc_html( implode( ' · ', $shown ) );
		}
	}
}
