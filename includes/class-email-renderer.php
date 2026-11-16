<?php
/**
 * Newsletter email renderer.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Builds plain-text and minimal HTML email messages from newsletter issues.
 */
class Email_Renderer {

	const META_FORMATTING = '_nlwp_email_formatting';

	/**
	 * Default per-issue email formatting.
	 *
	 * @return array
	 */
	public static function default_formatting(): array {
		$defaults = Settings::defaults();
		return $defaults['email']['formatting'];
	}

	/**
	 * Global email formatting from settings.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function settings_formatting( array $settings ): array {
		$formatting = isset( $settings['email']['formatting'] ) && is_array( $settings['email']['formatting'] )
			? $settings['email']['formatting']
			: array();

		return self::sanitize_formatting( $formatting, $settings );
	}

	/**
	 * Resolve the formatting used for an issue.
	 *
	 * @param int        $issue_id Issue ID.
	 * @param array|null $settings Settings.
	 * @return array
	 */
	public static function formatting( int $issue_id, ?array $settings = null ): array {
		$settings = null === $settings ? Settings::get_settings() : $settings;
		$base     = self::settings_formatting( $settings );
		$stored   = get_post_meta( $issue_id, self::META_FORMATTING, true );

		if ( is_array( $stored ) ) {
			$base = array_merge( $base, $stored );
		}

		return self::sanitize_formatting( $base, $settings );
	}

	/**
	 * Sanitize email formatting.
	 *
	 * @param mixed      $value    Raw formatting.
	 * @param array|null $settings Settings.
	 * @return array
	 */
	public static function sanitize_formatting( $value, ?array $settings = null ): array {
		$settings   = null === $settings ? Settings::get_settings() : $settings;
		$defaults   = self::default_formatting();
		$value      = is_array( $value ) ? $value : array();
		$codes      = self::configured_codes( $settings );
		$default    = self::default_code( $settings );
		$primary    = Languages::sanitize_code( (string) ( $value['primary_language'] ?? '' ) );
		$languages  = array();
		$raw_langs  = $value['languages'] ?? array();

		if ( is_string( $raw_langs ) ) {
			$raw_langs = preg_split( '/[\s,;]+/', $raw_langs );
		}
		$raw_langs = is_array( $raw_langs ) ? $raw_langs : array();

		foreach ( $raw_langs as $code ) {
			$code = Languages::sanitize_code( (string) $code );
			if ( '' !== $code && in_array( $code, $codes, true ) ) {
				$languages[] = $code;
			}
		}
		$languages = array_values( array_unique( $languages ) );

		if ( '' === $primary || ! in_array( $primary, $codes, true ) ) {
			$primary = $default;
		}
		if ( empty( $languages ) ) {
			$languages = array( $primary );
		}

		$title_layout = sanitize_key( (string) ( $value['title_layout'] ?? $defaults['title_layout'] ) );
		$label_format = sanitize_key( (string) ( $value['label_format'] ?? $defaults['label_format'] ) );
		$desc_sep     = sanitize_key( (string) ( $value['description_separator'] ?? $defaults['description_separator'] ) );
		$title_sep    = isset( $value['title_separator'] ) ? nlwp_sanitize_separator( $value['title_separator'] ) : (string) $defaults['title_separator'];
		$custom_sep   = isset( $value['description_custom_separator'] ) ? sanitize_textarea_field( (string) $value['description_custom_separator'] ) : '';

		return array(
			'languages'                    => $languages,
			'primary_language'             => $primary,
			'title_layout'                 => in_array( $title_layout, array( 'inline', 'block' ), true ) ? $title_layout : 'inline',
			'title_separator'              => '' !== trim( $title_sep ) ? $title_sep : (string) $defaults['title_separator'],
			'title_labels'                 => ! empty( $value['title_labels'] ),
			'label_format'                 => in_array( $label_format, array( 'short', 'name', 'code' ), true ) ? $label_format : 'short',
			'description_separator'        => in_array( $desc_sep, array( 'none', 'line', 'custom' ), true ) ? $desc_sep : 'none',
			'description_custom_separator' => $custom_sep,
			'description_labels'           => ! empty( $value['description_labels'] ),
		);
	}

	/**
	 * Build a rendered email message.
	 *
	 * @param int        $issue_id Issue ID.
	 * @param string     $format   plain or html.
	 * @param array|null $settings Settings.
	 * @return array
	 */
	public static function message( int $issue_id, string $format = '', ?array $settings = null ): array {
		$settings = null === $settings ? Settings::get_settings() : $settings;
		$format   = in_array( $format, array( 'plain', 'html' ), true ) ? $format : self::format( $settings );
		$data     = self::build_data( $issue_id, $format, $settings );
		$body     = self::template( 'email-' . $format, $data );

		if ( 'html' === $format ) {
			$body = (string) apply_filters( 'nlwp_email_html', $body, $issue_id, $data, $settings );
		} else {
			$body = (string) apply_filters( 'nlwp_email_plain_text', $body, $issue_id, $data, $settings );
		}

		return array(
			'subject' => $data['subject'],
			'body'    => $body,
			'format'  => $format,
		);
	}

	/**
	 * Export the rendered body in a format.
	 *
	 * @param int        $issue_id Issue ID.
	 * @param string     $format   plain or html.
	 * @param array|null $settings Settings.
	 * @return string
	 */
	public static function export( int $issue_id, string $format, ?array $settings = null ): string {
		$message = self::message( $issue_id, $format, $settings );
		return $message['body'];
	}

	/**
	 * Build the email subject.
	 *
	 * @param int        $issue_id Issue ID.
	 * @param array|null $settings Settings.
	 * @return string
	 */
	public static function subject( int $issue_id, ?array $settings = null ): string {
		$settings   = null === $settings ? Settings::get_settings() : $settings;
		$formatting = self::formatting( $issue_id, $settings );
		$subject    = trim( (string) get_post_meta( $issue_id, '_nlwp_email_subject', true ) );

		if ( '' === $subject ) {
			$subject = Content::to_plain( Translations::get_post_text( $issue_id, 'title', $formatting['primary_language'], false ) );
		}
		if ( '' === $subject ) {
			$subject = (string) get_bloginfo( 'name' );
		}
		if ( '' === $subject ) {
			$subject = __( 'Newsletter', 'newsletter-wp-plugin' );
		}

		return (string) apply_filters( 'nlwp_email_subject', $subject, $issue_id, $settings );
	}

	/**
	 * Build the data passed to the email templates.
	 *
	 * @param int    $issue_id Issue ID.
	 * @param string $format   plain or html.
	 * @param array  $settings Settings.
	 * @return array
	 */
	private static function build_data( int $issue_id, string $format, array $settings ): array {
		$formatting = self::formatting( $issue_id, $settings );
		$codes      = $formatting['languages'];
		$primary    = $formatting['primary_language'];
		$context    = Content::email_context( $codes, $primary );

		$title_items = Content::post_field_items( $issue_id, 'title', $codes, $primary, false, $settings );
		$intro_items = Content::post_field_items( $issue_id, 'intro', $codes, $primary, true, $settings );

		$groups = Content::build_groups( $issue_id, $context, $settings );

		$data_groups = array();
		foreach ( $groups as $group ) {
			$events = array();
			foreach ( $group['events'] as $event ) {
				$attachment = null;
				if ( ! empty( $event['attachment'] ) ) {
					$attachment = array(
						'label' => self::compose_title_items( $event['attachment']['label_items'], $formatting, $format ),
						'name'  => self::clean_text( (string) ( $event['attachment']['name'] ?? '' ), $format ),
						'url'   => (string) ( $event['attachment']['url'] ?? '' ),
					);
				}

				$events[] = array(
					'number'      => (int) $event['number'],
					'title'       => self::compose_title_items( $event['title_items'], $formatting, $format ),
					'description' => self::compose_description_items( $event['description_items'], $formatting, $format ),
					'date'        => self::clean_text( self::date_text( $event['date'], $primary, $settings ), $format ),
					'attachment'  => $attachment,
				);
			}

			$data_groups[] = array(
				'label'  => self::compose_title_items( $group['label_items'], $formatting, $format ),
				'events' => $events,
			);
		}

		$header = self::clean_text( self::setting_text( 'email_header', $primary, $settings ), $format );
		$footer = self::clean_text( self::setting_text( 'email_footer', $primary, $settings ), $format );
		$title  = self::compose_title_items( $title_items, $formatting, $format );
		$intro  = self::compose_description_items( $intro_items, $formatting, $format );
		$date   = '';

		$issue_date = nlwp_sanitize_date( (string) get_post_meta( $issue_id, '_nlwp_issue_date', true ) );
		if ( '' !== $issue_date ) {
			$label = self::setting_text( 'issue_date_label', $primary, $settings );
			$value = Content::format_date( $issue_date, $primary, $settings );
			$date  = '' !== $label ? $label . ': ' . $value : $value;
			$date  = self::clean_text( $date, $format );
		}

		return array(
			'subject'    => self::subject( $issue_id, $settings ),
			'header'     => $header,
			'title'      => $title,
			'date'       => $date,
			'intro'      => $intro,
			'groups'     => $data_groups,
			'footer'     => $footer,
			'formatting' => $formatting,
			'site_name'  => (string) get_bloginfo( 'name' ),
		);
	}

	/**
	 * Compose title-like items.
	 *
	 * @param array  $items      Content items.
	 * @param array  $formatting Formatting.
	 * @param string $format     plain or html.
	 * @return string
	 */
	private static function compose_title_items( array $items, array $formatting, string $format ): string {
		$items = self::dedupe_items( $items );
		if ( empty( $items ) ) {
			return '';
		}

		$parts = array();
		foreach ( $items as $item ) {
			$text = self::clean_text( (string) ( $item['text'] ?? '' ), $format );
			if ( '' === $text ) {
				continue;
			}

			if ( ! empty( $formatting['title_labels'] ) ) {
				$label = Languages::label( (string) ( $item['code'] ?? '' ), (string) $formatting['label_format'] );
				if ( '' !== $label ) {
					$text = ( 'html' === $format ? esc_html( $label ) : $label ) . ': ' . $text;
				}
			}

			$parts[] = $text;
		}

		if ( empty( $parts ) ) {
			return '';
		}

		if ( 'block' === $formatting['title_layout'] ) {
			return implode( 'html' === $format ? '<br>' : "\n", $parts );
		}

		$separator = (string) $formatting['title_separator'];
		return implode( 'html' === $format ? esc_html( $separator ) : $separator, $parts );
	}

	/**
	 * Compose description-like items.
	 *
	 * @param array  $items      Content items.
	 * @param array  $formatting Formatting.
	 * @param string $format     plain or html.
	 * @return string
	 */
	private static function compose_description_items( array $items, array $formatting, string $format ): string {
		$items = self::dedupe_items( $items );
		if ( empty( $items ) ) {
			return '';
		}

		$parts = array();
		foreach ( $items as $item ) {
			$text = self::clean_text( (string) ( $item['text'] ?? '' ), $format );
			if ( '' === $text ) {
				continue;
			}

			if ( ! empty( $formatting['description_labels'] ) ) {
				$label = Languages::label( (string) ( $item['code'] ?? '' ), (string) $formatting['label_format'] );
				if ( '' !== $label ) {
					$text = ( 'html' === $format ? esc_html( $label ) : $label ) . ': ' . $text;
				}
			}

			$parts[] = $text;
		}

		if ( empty( $parts ) ) {
			return '';
		}

		$separator = (string) $formatting['description_separator'];
		if ( 'custom' === $separator ) {
			$custom = (string) $formatting['description_custom_separator'];
			if ( '' === trim( $custom ) ) {
				$separator = 'none';
			} else {
				$glue = 'html' === $format
					? '<br><br>' . nl2br( esc_html( $custom ) ) . '<br><br>'
					: "\n\n" . $custom . "\n\n";
				return implode( $glue, $parts );
			}
		}

		if ( 'line' === $separator ) {
			$glue = 'html' === $format ? '<br><br><hr><br>' : "\n\n" . str_repeat( '-', 40 ) . "\n\n";
			return implode( $glue, $parts );
		}

		return implode( 'html' === $format ? '<br><br>' : "\n\n", $parts );
	}

	/**
	 * Convert raw text for a format.
	 *
	 * @param string $text   Raw text.
	 * @param string $format plain or html.
	 * @return string
	 */
	private static function clean_text( string $text, string $format ): string {
		$plain = Content::to_plain( $text );

		if ( 'html' === $format ) {
			$safe = esc_html( $plain );
			$safe = make_clickable( $safe );
			return nl2br( $safe );
		}

		return $plain;
	}

	/**
	 * Remove duplicate items by plain text.
	 *
	 * @param array $items Content items.
	 * @return array
	 */
	private static function dedupe_items( array $items ): array {
		$out  = array();
		$seen = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$key = Content::to_plain( (string) ( $item['text'] ?? '' ) );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $item;
		}

		return $out;
	}

	/**
	 * Format an event date range.
	 *
	 * @param array|null $date     Date array.
	 * @param string     $lang     Language code.
	 * @param array      $settings Settings.
	 * @return string
	 */
	private static function date_text( ?array $date, string $lang, array $settings ): string {
		if ( empty( $date['start'] ) ) {
			return '';
		}

		$start = (string) $date['start'];
		$end   = isset( $date['end'] ) ? (string) $date['end'] : '';
		$text  = Content::format_date( $start, $lang, $settings );

		if ( ! empty( $settings['show_end_dates'] ) && '' !== $end && $end !== $start ) {
			$separator = isset( $settings['end_date_separator'] ) ? (string) $settings['end_date_separator'] : '–';
			$text     .= ' ' . $separator . ' ' . Content::format_date( $end, $lang, $settings );
		}

		return $text;
	}

	/**
	 * Read a translated settings string in the primary language.
	 *
	 * @param string $key      String key.
	 * @param string $lang     Language code.
	 * @param array  $settings Settings.
	 * @return string
	 */
	private static function setting_text( string $key, string $lang, array $settings ): string {
		unset( $settings );
		return (string) Translations::get_setting_string( $key, $lang, true );
	}

	/**
	 * Email body format.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function format( array $settings ): string {
		$format = (string) ( $settings['email']['format'] ?? 'plain' );
		return in_array( $format, array( 'plain', 'html' ), true ) ? $format : 'plain';
	}

	/**
	 * Load an email template, allowing theme overrides.
	 *
	 * @param string $name Template name without extension.
	 * @param array  $data Template data.
	 * @return string
	 */
	public static function template( string $name, array $data ): string {
		$name = sanitize_file_name( $name );
		$file = locate_template( array( 'nlwp/' . $name . '.php' ) );

		if ( ! $file ) {
			$file = NLWP_DIR . 'templates/' . $name . '.php';
		}
		if ( ! is_readable( $file ) ) {
			return '';
		}

		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Configured language codes.
	 *
	 * @param array $settings Settings.
	 * @return string[]
	 */
	private static function configured_codes( array $settings ): array {
		$codes = array();
		foreach ( (array) ( $settings['languages'] ?? array() ) as $language ) {
			if ( is_array( $language ) && ! empty( $language['code'] ) ) {
				$codes[] = (string) $language['code'];
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Default language code from settings.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function default_code( array $settings ): string {
		$codes   = self::configured_codes( $settings );
		$default = Languages::sanitize_code( (string) ( $settings['default_language'] ?? '' ) );

		if ( '' !== $default && in_array( $default, $codes, true ) ) {
			return $default;
		}

		return ! empty( $codes ) ? $codes[0] : 'en';
	}
}
