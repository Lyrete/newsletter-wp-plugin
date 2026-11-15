<?php
/**
 * Language registry and resolution.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Handles configured languages and the active display language.
 */
class Languages {

	/**
	 * Normalize a language configuration row.
	 *
	 * @param array $language Raw language data.
	 * @return array|null
	 */
	public static function normalize_language( array $language ): ?array {
		$code = self::sanitize_code( (string) ( $language['code'] ?? '' ) );
		if ( '' === $code ) {
			return null;
		}

		$label = trim( (string) ( $language['label'] ?? '' ) );
		if ( '' === $label ) {
			$label = strtoupper( $code );
		}

		$short = trim( (string) ( $language['short'] ?? '' ) );
		if ( '' === $short ) {
			$short = strtoupper( $code );
		}

		$dir = ( 'rtl' === ( $language['dir'] ?? 'ltr' ) ) ? 'rtl' : 'ltr';

		return array(
			'code'        => $code,
			'label'       => sanitize_text_field( $label ),
			'short'       => sanitize_text_field( $short ),
			'locale'      => sanitize_text_field( (string) ( $language['locale'] ?? '' ) ),
			'dir'         => $dir,
			'enabled'     => ! empty( $language['enabled'] ),
			'date_format' => sanitize_text_field( (string) ( $language['date_format'] ?? '' ) ),
		);
	}

	/**
	 * Get configured languages.
	 *
	 * @param bool $enabled_only Limit to enabled languages.
	 * @return array
	 */
	public static function all( bool $enabled_only = false ): array {
		$settings  = Settings::get_settings();
		$languages = isset( $settings['languages'] ) && is_array( $settings['languages'] ) ? $settings['languages'] : array();

		$out = array();
		foreach ( $languages as $language ) {
			if ( ! is_array( $language ) ) {
				continue;
			}
			$normalized = self::normalize_language( $language );
			if ( $normalized ) {
				$out[] = $normalized;
			}
		}

		if ( empty( $out ) ) {
			$out = array_filter( array( self::normalize_language( Settings::fallback_language() ) ) );
		}

		if ( $enabled_only ) {
			$out = array_values(
				array_filter(
					$out,
					static function ( $language ) {
						return ! empty( $language['enabled'] );
					}
				)
			);

			if ( empty( $out ) ) {
				$out = array_slice( array_values( array_filter( array( self::normalize_language( Settings::fallback_language() ) ) ) ), 0, 1 );
			}
		}

		return $out;
	}

	/**
	 * Get a single language by code.
	 *
	 * @param string $code Language code.
	 * @return array|null
	 */
	public static function get( string $code ): ?array {
		$code = self::sanitize_code( $code );
		foreach ( self::all() as $language ) {
			if ( $language['code'] === $code ) {
				return $language;
			}
		}

		return null;
	}

	/**
	 * Get the default language code.
	 *
	 * @return string
	 */
	public static function default_code(): string {
		$settings = Settings::get_settings();
		$default  = self::sanitize_code( (string) ( $settings['default_language'] ?? '' ) );
		$enabled  = self::all( true );

		foreach ( $enabled as $language ) {
			if ( $language['code'] === $default ) {
				return $default;
			}
		}

		if ( ! empty( $enabled ) ) {
			return $enabled[0]['code'];
		}

		return self::sanitize_code( Settings::fallback_language()['code'] );
	}

	/**
	 * Resolve a language code or locale to a configured language code.
	 *
	 * @param string $value Code or locale.
	 * @return string
	 */
	public static function resolve_code( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = str_replace( '-', '_', $value );

		if ( '' === $value ) {
			return self::default_code();
		}

		foreach ( self::all() as $language ) {
			$code   = strtolower( str_replace( '-', '_', $language['code'] ) );
			$locale = strtolower( str_replace( '-', '_', $language['locale'] ) );
			$code_base   = strtok( $code, '_' );
			$value_base  = strtok( $value, '_' );
			$locale_base = $locale ? strtok( $locale, '_' ) : '';

			if ( $value === $code || ( $locale && $value === $locale ) ) {
				return $language['code'];
			}
			if ( $code_base && $value_base && $code_base === $value_base ) {
				return $language['code'];
			}
			if ( $locale_base && $value_base && $locale_base === $value_base ) {
				return $language['code'];
			}
		}

		return self::default_code();
	}

	/**
	 * Get the current display language.
	 *
	 * @return string
	 */
	public static function current_code(): string {
		$filtered = apply_filters( 'nlwp_current_language', null );
		if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
			return self::resolve_code( $filtered );
		}

		if ( function_exists( 'pll_current_language' ) ) {
			$polylang = pll_current_language( 'slug' );
			if ( is_string( $polylang ) && '' !== $polylang ) {
				return self::resolve_code( $polylang );
			}
		}

		$wpml = apply_filters( 'wpml_current_language', null );
		if ( is_string( $wpml ) && '' !== $wpml ) {
			return self::resolve_code( $wpml );
		}

		return self::resolve_code( (string) get_locale() );
	}

	/**
	 * Get a language label.
	 *
	 * @param string $code   Language code.
	 * @param string $format Label format: short, name or code.
	 * @return string
	 */
	public static function label( string $code, string $format = 'short' ): string {
		$language = self::get( $code );
		if ( ! $language ) {
			return strtoupper( $code );
		}

		if ( 'name' === $format ) {
			return $language['label'];
		}
		if ( 'code' === $format ) {
			return $language['code'];
		}

		return $language['short'] ? $language['short'] : strtoupper( $language['code'] );
	}

	/**
	 * Get a language direction.
	 *
	 * @param string $code Language code.
	 * @return string
	 */
	public static function dir( string $code ): string {
		$language = self::get( $code );
		return $language && 'rtl' === $language['dir'] ? 'rtl' : 'ltr';
	}

	/**
	 * Sanitize a language code.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public static function sanitize_code( string $code ): string {
		$code = strtolower( trim( $code ) );
		if ( preg_match( '/^[a-z]{2,3}([_-][a-z0-9]{2,8})*$/', $code ) ) {
			return $code;
		}

		return '';
	}
}
