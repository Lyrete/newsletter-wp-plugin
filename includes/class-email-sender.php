<?php
/**
 * Newsletter email sender.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Sends newsletter issues through wp_mail().
 */
class Email_Sender {

	const LOCK_PREFIX  = 'nlwp_email_lock_';
	const LOCK_SECONDS = 60;

	/**
	 * Parse a recipient list.
	 *
	 * @param string $raw Raw recipient text.
	 * @return string[]
	 */
	public static function parse_recipients( string $raw ): array {
		$parts = preg_split( '/[\s,;]+/', $raw );
		$parts = is_array( $parts ) ? $parts : array();
		$out   = array();

		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email && is_email( $email ) ) {
				$out[] = $email;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Get the saved recipients for an issue.
	 *
	 * @param int $issue_id Issue ID.
	 * @return string[]
	 */
	public static function recipients( int $issue_id ): array {
		return self::parse_recipients( (string) get_post_meta( $issue_id, '_nlwp_email_recipients', true ) );
	}

	/**
	 * Send an issue to its saved recipients.
	 *
	 * @param int   $issue_id Issue ID.
	 * @param array $args     Optional overrides.
	 * @return array
	 */
	public static function send( int $issue_id, array $args = array() ): array {
		$settings = Settings::get_settings();

		if ( Post_Types::ISSUE !== get_post_type( $issue_id ) ) {
			return self::result( false, __( 'Invalid newsletter issue.', 'newsletter-wp-plugin' ) );
		}

		$recipients = isset( $args['recipients'] )
			? self::parse_recipients( (string) $args['recipients'] )
			: self::recipients( $issue_id );

		if ( empty( $recipients ) ) {
			return self::result( false, __( 'Add at least one valid recipient before sending.', 'newsletter-wp-plugin' ) );
		}

		$lock_key = self::LOCK_PREFIX . $issue_id;
		if ( false !== get_transient( $lock_key ) ) {
			return self::result( false, __( 'This issue was just sent. Wait a minute before sending it again.', 'newsletter-wp-plugin' ) );
		}

		$message = Email_Renderer::message( $issue_id, Email_Renderer::format( $settings ), $settings );
		if ( '' === trim( $message['body'] ) ) {
			return self::result( false, __( 'The email body is empty.', 'newsletter-wp-plugin' ) );
		}

		set_transient( $lock_key, time(), self::LOCK_SECONDS );

		$result = self::dispatch(
			$message['subject'],
			$message['body'],
			self::neutral_to( $settings ),
			$recipients,
			$message['format'],
			$settings,
			count( $recipients )
		);

		if ( $result['success'] ) {
			update_post_meta( $issue_id, '_nlwp_email_last_send', (string) current_time( 'mysql' ) );
		} else {
			delete_transient( $lock_key );
		}

		return $result;
	}

	/**
	 * Send a test copy to one address.
	 *
	 * @param int    $issue_id Issue ID.
	 * @param string $email    Test recipient.
	 * @return array
	 */
	public static function send_test( int $issue_id, string $email ): array {
		$email = sanitize_email( $email );
		if ( '' === $email || ! is_email( $email ) ) {
			return self::result( false, __( 'Enter a valid test email address.', 'newsletter-wp-plugin' ) );
		}

		if ( Post_Types::ISSUE !== get_post_type( $issue_id ) ) {
			return self::result( false, __( 'Invalid newsletter issue.', 'newsletter-wp-plugin' ) );
		}

		$settings = Settings::get_settings();
		$message  = Email_Renderer::message( $issue_id, Email_Renderer::format( $settings ), $settings );
		$subject  = sprintf(
			/* translators: %s: newsletter subject. */
			__( '[Test] %s', 'newsletter-wp-plugin' ),
			$message['subject']
		);

		$result = self::dispatch( $subject, $message['body'], $email, array(), $message['format'], $settings, 1 );

		if ( $result['success'] ) {
			update_post_meta( $issue_id, '_nlwp_email_last_test', (string) current_time( 'mysql' ) );
		}

		return $result;
	}

	/**
	 * Clear the duplicate-send lock for an issue.
	 *
	 * @param int $issue_id Issue ID.
	 * @return void
	 */
	public static function clear_lock( int $issue_id ): void {
		delete_transient( self::LOCK_PREFIX . $issue_id );
	}

	/**
	 * From email address.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function from_email( array $settings ): string {
		$email = sanitize_email( (string) ( $settings['email']['from_email'] ?? '' ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}

	/**
	 * From name.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function from_name( array $settings ): string {
		return sanitize_text_field( (string) ( $settings['email']['from_name'] ?? '' ) );
	}

	/**
	 * Reply-To address.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function reply_to( array $settings ): string {
		$email = sanitize_email( (string) ( $settings['email']['reply_to'] ?? '' ) );
		return '' !== $email && is_email( $email ) ? $email : '';
	}

	/**
	 * Neutral To address used while all real recipients are Bcc.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function neutral_to( array $settings ): string {
		$from = self::from_email( $settings );
		if ( '' !== $from ) {
			return $from;
		}

		$admin = sanitize_email( (string) get_option( 'admin_email', '' ) );
		if ( '' !== $admin && is_email( $admin ) ) {
			return $admin;
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = preg_replace( '/[^a-z0-9.-]/i', '', $host );

		return 'wordpress@' . ( '' !== $host ? $host : 'localhost' );
	}

	/**
	 * Dispatch a message through wp_mail().
	 *
	 * @param string   $subject    Subject.
	 * @param string   $body       Body.
	 * @param string   $to         To address.
	 * @param string[] $bcc        Bcc addresses.
	 * @param string   $format     plain or html.
	 * @param array    $settings   Settings.
	 * @param int      $recipient_count Number of recipients for the message.
	 * @return array
	 */
	private static function dispatch( string $subject, string $body, string $to, array $bcc, string $format, array $settings, int $recipient_count = 0 ): array {
		$from_email = self::from_email( $settings );
		$from_name  = self::from_name( $settings );
		$reply_to   = self::reply_to( $settings );
		$failed     = null;

		$capture = static function ( $error ) use ( &$failed ) {
			$failed = $error;
		};

		$from_filter = static function () use ( $from_email ) {
			return $from_email;
		};
		$name_filter = static function () use ( $from_name ) {
			return $from_name;
		};

		add_action( 'wp_mail_failed', $capture );
		if ( '' !== $from_email ) {
			add_filter( 'wp_mail_from', $from_filter, 99 );
		}
		if ( '' !== $from_name ) {
			add_filter( 'wp_mail_from_name', $name_filter, 99 );
		}

		$headers = array(
			'Content-Type: ' . ( 'html' === $format ? 'text/html' : 'text/plain' ) . '; charset=UTF-8',
		);

		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		if ( ! empty( $bcc ) ) {
			$headers[] = 'Bcc: ' . implode( ', ', $bcc );
		}

		$sent = wp_mail( $to, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', $capture );
		if ( '' !== $from_email ) {
			remove_filter( 'wp_mail_from', $from_filter, 99 );
		}
		if ( '' !== $from_name ) {
			remove_filter( 'wp_mail_from_name', $name_filter, 99 );
		}

		if ( ! $sent ) {
			$message = __( 'WordPress could not send the email.', 'newsletter-wp-plugin' );
			if ( is_wp_error( $failed ) && '' !== $failed->get_error_message() ) {
				$message .= ' ' . $failed->get_error_message();
			}
			return self::result( false, $message );
		}

		return self::result(
			true,
			sprintf(
				/* translators: %d: number of recipients. */
				_n( 'Email sent to %d recipient.', 'Email sent to %d recipients.', max( 1, $recipient_count ), 'newsletter-wp-plugin' ),
				max( 1, $recipient_count )
			)
		);
	}

	/**
	 * Build a result array.
	 *
	 * @param bool   $success Whether the action succeeded.
	 * @param string $message Message.
	 * @return array
	 */
	private static function result( bool $success, string $message ): array {
		return array(
			'success' => $success,
			'message' => $message,
		);
	}
}
