<?php
/**
 * Translation storage and retrieval.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes multilingual content.
 */
class Translations {

	/**
	 * Get all stored translations for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_post_translations( int $post_id ): array {
		$value = get_post_meta( $post_id, '_nlwp_translations', true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Get a translated post field.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $field    title, description or intro.
	 * @param string $lang     Language code.
	 * @param bool   $fallback Fall back to the default language.
	 * @return string
	 */
	public static function get_post_text( int $post_id, string $field, string $lang, bool $fallback = true ): string {
		$lang    = Languages::resolve_code( $lang );
		$default = Languages::default_code();
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		if ( $lang === $default ) {
			return 'title' === $field ? (string) $post->post_title : (string) $post->post_content;
		}

		$translations = self::get_post_translations( $post_id );
		$value        = isset( $translations[ $lang ][ $field ] ) ? (string) $translations[ $lang ][ $field ] : '';

		if ( '' !== $value ) {
			return $value;
		}

		if ( $fallback ) {
			return 'title' === $field ? (string) $post->post_title : (string) $post->post_content;
		}

		return '';
	}

	/**
	 * Determine whether an event or issue has any translation for a language.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $lang    Language code.
	 * @return bool
	 */
	public static function has_translation( int $post_id, string $lang ): bool {
		$lang = Languages::resolve_code( $lang );
		if ( $lang === Languages::default_code() ) {
			return true;
		}

		$translations = self::get_post_translations( $post_id );
		if ( empty( $translations[ $lang ] ) || ! is_array( $translations[ $lang ] ) ) {
			return false;
		}

		foreach ( $translations[ $lang ] as $value ) {
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get translation codes present on a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	public static function translation_codes( int $post_id ): array {
		$codes = array();
		foreach ( self::get_post_translations( $post_id ) as $code => $fields ) {
			if ( is_array( $fields ) ) {
				foreach ( $fields as $value ) {
					if ( is_string( $value ) && '' !== trim( $value ) ) {
						$codes[] = (string) $code;
						break;
					}
				}
			}
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Get a translated category label.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $lang     Language code.
	 * @param bool   $fallback Fall back to the default label.
	 * @return string
	 */
	public static function get_term_label( int $term_id, string $lang, bool $fallback = true ): string {
		$term = get_term( $term_id, Taxonomy::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		$lang = Languages::resolve_code( $lang );
		if ( $lang === Languages::default_code() ) {
			return (string) $term->name;
		}

		$labels = get_term_meta( $term_id, '_nlwp_label_translations', true );
		$value  = is_array( $labels ) && isset( $labels[ $lang ] ) ? (string) $labels[ $lang ] : '';

		if ( '' !== $value ) {
			return $value;
		}

		return $fallback ? (string) $term->name : '';
	}

	/**
	 * Get a translated settings string.
	 *
	 * @param string $key      String key.
	 * @param string $lang     Language code.
	 * @param bool   $fallback Fall back to default/built-in text.
	 * @return string
	 */
	public static function get_setting_string( string $key, string $lang, bool $fallback = true ): string {
		$settings = Settings::get_settings();
		$strings  = isset( $settings['strings'] ) && is_array( $settings['strings'] ) ? $settings['strings'] : array();
		$lang     = Languages::resolve_code( $lang );
		$value    = isset( $strings[ $key ][ $lang ] ) ? (string) $strings[ $key ][ $lang ] : '';

		if ( '' !== $value ) {
			return $value;
		}

		if ( $fallback ) {
			$default = Languages::default_code();
			$value   = isset( $strings[ $key ][ $default ] ) ? (string) $strings[ $key ][ $default ] : '';
			if ( '' !== $value ) {
				return $value;
			}
			return self::builtin_string( $key );
		}

		return '';
	}

	/**
	 * Built-in fallback strings.
	 *
	 * @param string $key String key.
	 * @return string
	 */
	public static function builtin_string( string $key ): string {
		switch ( $key ) {
			case 'attachment_label':
				return __( 'Attachment', 'newsletter-wp-plugin' );
			case 'empty_text':
				return __( 'No events for this period.', 'newsletter-wp-plugin' );
			case 'fixed_title':
				return __( 'Newsletter', 'newsletter-wp-plugin' );
			case 'issue_date_label':
				return __( 'Published', 'newsletter-wp-plugin' );
			case 'uncategorized_label':
				return __( 'Other', 'newsletter-wp-plugin' );
		}

		return '';
	}

	/**
	 * Sanitize event translations.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_event_translations( $value ): array {
		return self::sanitize_translations(
			$value,
			array(
				'title'       => 'text',
				'description' => 'html',
			)
		);
	}

	/**
	 * Sanitize issue translations.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_issue_translations( $value ): array {
		return self::sanitize_translations(
			$value,
			array(
				'title' => 'text',
				'intro' => 'html',
			)
		);
	}

	/**
	 * Sanitize a translations array.
	 *
	 * @param mixed $value  Raw value.
	 * @param array $fields Field map.
	 * @return array
	 */
	private static function sanitize_translations( $value, array $fields ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$configured = array();
		foreach ( Languages::all() as $language ) {
			$configured[ $language['code'] ] = true;
		}

		$default = Languages::default_code();
		$out     = array();

		foreach ( $value as $lang => $data ) {
			$lang = Languages::sanitize_code( (string) $lang );
			if ( '' === $lang || $lang === $default || ! isset( $configured[ $lang ] ) || ! is_array( $data ) ) {
				continue;
			}

			$clean = array();
			foreach ( $fields as $field => $type ) {
				$raw = isset( $data[ $field ] ) ? $data[ $field ] : '';
				if ( 'html' === $type ) {
					$clean[ $field ] = wp_kses_post( (string) $raw );
				} else {
					$clean[ $field ] = sanitize_text_field( (string) $raw );
				}
			}

			$clean = array_filter(
				$clean,
				static function ( $item ) {
					return is_string( $item ) && '' !== trim( $item );
				}
			);

			if ( ! empty( $clean ) ) {
				$out[ $lang ] = $clean;
			}
		}

		return $out;
	}

	/**
	 * Sanitize category label translations.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	public static function sanitize_term_label_translations( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$configured = array();
		foreach ( Languages::all() as $language ) {
			$configured[ $language['code'] ] = true;
		}

		$default = Languages::default_code();
		$out     = array();

		foreach ( $value as $lang => $label ) {
			$lang = Languages::sanitize_code( (string) $lang );
			if ( '' === $lang || $lang === $default || ! isset( $configured[ $lang ] ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) $label );
			if ( '' !== $label ) {
				$out[ $lang ] = $label;
			}
		}

		return $out;
	}
}
