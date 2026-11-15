<?php
/**
 * Plugin settings data.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Stores, sanitizes and retrieves plugin settings.
 */
class Settings {

	const OPTION = 'nlwp_settings';
	const GROUP  = 'nlwp_settings_group';
	const PAGE   = 'nlwp-settings';

	/**
	 * Register settings hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the settings option.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'languages'                  => array(),
			'default_language'           => 'en',
			'display_mode'               => 'site',
			'field_modes'                => array(
				'category_labels'    => 'inherit',
				'event_titles'       => 'inherit',
				'event_descriptions' => 'inherit',
				'issue_title'        => 'inherit',
				'issue_intro'        => 'inherit',
				'ui_strings'         => 'inherit',
			),
			'stacked'                    => array(
				'show_labels'           => false,
				'label_format'          => 'short',
				'title_layout'          => 'inline',
				'separator'             => ' / ',
				'description_separator' => 'none',
			),
			'hide_untranslated_events'   => false,
			'title_source'               => 'issue',
			'title_tag'                  => 'h2',
			'show_intro'                 => true,
			'show_issue_date'            => false,
			'default_period_days'        => 28,
			'date_format'                => 'j.n.Y',
			'end_date_separator'         => '–',
			'show_numbers'               => true,
			'numbering'                  => 'continuous',
			'accordion'                  => true,
			'open_first'                 => false,
			'open_all'                   => false,
			'exclusive_accordion'        => false,
			'show_categories'            => true,
			'show_empty_categories'      => false,
			'category_heading_tag'       => 'h3',
			'show_dates'                 => true,
			'show_end_dates'             => true,
			'show_attachments'           => true,
			'attachment_style'           => 'link',
			'attachment_new_tab'         => true,
			'autolink'                   => true,
			'appearance'                 => array(
				'preset'       => 'light',
				'colors'       => array(
					'background' => '#f5f5f5',
					'surface'    => '#f1f1f1',
					'text'       => '#1f1f1f',
					'muted'      => '#666666',
					'accent'     => '#000f89',
					'border'     => '#dcdcdc',
					'heading'    => '#111111',
				),
				'font_family'  => 'inherit',
				'font_size'    => '16px',
				'max_width'    => '860px',
				'border_radius'=> '10px',
				'gap'          => '1rem',
				'logo_id'      => 0,
				'logo_size'    => '80px',
				'logo_align'   => 'center',
				'custom_css'   => '',
			),
			'strings'                    => array(
				'attachment_label'    => array(),
				'empty_text'          => array(),
				'fixed_title'         => array(),
				'issue_date_label'    => array(),
				'uncategorized_label' => array(),
			),
			'delete_data_on_uninstall'   => false,
		);
	}

	/**
	 * Build a fallback language from the site locale.
	 *
	 * @return array
	 */
	public static function fallback_language(): array {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		$locale = str_replace( '-', '_', (string) $locale );
		$parts  = explode( '_', $locale );
		$code   = Languages::sanitize_code( strtolower( (string) ( $parts[0] ?? 'en' ) ) );

		if ( '' === $code ) {
			$code = 'en';
		}

		$label = $locale;
		if ( class_exists( '\\Locale' ) ) {
			$display = \Locale::getDisplayName( $locale, $locale );
			if ( is_string( $display ) && '' !== $display ) {
				$label = $display;
			}
		}

		return array(
			'code'        => $code,
			'label'       => $label,
			'short'       => strtoupper( $code ),
			'locale'      => $locale,
			'dir'         => 'ltr',
			'enabled'     => true,
			'date_format' => '',
		);
	}

	/**
	 * Install initial settings.
	 *
	 * @return void
	 */
	public static function install_defaults(): void {
		$existing = get_option( self::OPTION, null );

		if ( ! is_array( $existing ) || empty( $existing ) ) {
			$defaults                     = self::defaults();
			$defaults['languages']        = array( self::fallback_language() );
			$defaults['default_language'] = $defaults['languages'][0]['code'];
			update_option( self::OPTION, $defaults );
			return;
		}

		if ( empty( $existing['languages'] ) || ! is_array( $existing['languages'] ) ) {
			$existing['languages']        = array( self::fallback_language() );
			$existing['default_language'] = $existing['languages'][0]['code'];
			update_option( self::OPTION, $existing );
		}
	}

	/**
	 * Get merged settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = wp_parse_args( $saved, $defaults );

		foreach ( array( 'field_modes', 'stacked', 'appearance' ) as $key ) {
			$current          = is_array( $settings[ $key ] ?? null ) ? $settings[ $key ] : array();
			$settings[ $key ] = wp_parse_args( $current, $defaults[ $key ] );
		}

		$colors = is_array( $settings['appearance']['colors'] ?? null ) ? $settings['appearance']['colors'] : array();
		$settings['appearance']['colors'] = wp_parse_args( $colors, $defaults['appearance']['colors'] );

		$settings['strings'] = is_array( $settings['strings'] ?? null ) ? $settings['strings'] : array();

		$rows = is_array( $settings['languages'] ?? null ) ? $settings['languages'] : array();
		$settings['languages'] = self::normalize_languages( $rows );

		if ( empty( $settings['languages'] ) ) {
			$settings['languages'] = array( self::fallback_language() );
		}

		$codes   = array_column( $settings['languages'], 'code' );
		$default = Languages::sanitize_code( (string) ( $settings['default_language'] ?? '' ) );

		if ( ! in_array( $default, $codes, true ) ) {
			$enabled = array_values(
				array_filter(
					$settings['languages'],
					static function ( $language ) {
						return ! empty( $language['enabled'] );
					}
				)
			);
			$default = ! empty( $enabled ) ? $enabled[0]['code'] : $settings['languages'][0]['code'];
		}

		foreach ( $settings['languages'] as &$language ) {
			if ( $language['code'] === $default ) {
				$language['enabled'] = true;
				break;
			}
		}
		unset( $language );

		$settings['default_language'] = $default;

		return apply_filters( 'nlwp_settings', $settings );
	}

	/**
	 * Normalize language rows.
	 *
	 * @param array $rows Raw rows.
	 * @return array
	 */
	public static function normalize_languages( array $rows ): array {
		$out  = array();
		$used = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$raw_code  = trim( (string) ( $row['code'] ?? '' ) );
			$raw_label = trim( (string) ( $row['label'] ?? '' ) );
			$raw_loc   = trim( (string) ( $row['locale'] ?? '' ) );

			if ( '' === $raw_code && '' === $raw_label && '' === $raw_loc ) {
				continue;
			}

			$code = Languages::sanitize_code( $raw_code );

			if ( '' === $code ) {
				$locale = strtolower( str_replace( '-', '_', (string) ( $row['locale'] ?? '' ) ) );
				$base   = preg_replace( '/[^a-z]/', '', (string) strtok( $locale, '_' ) );
				if ( '' === $base ) {
					$base = preg_replace( '/[^a-z]/', '', sanitize_title( (string) ( $row['label'] ?? '' ) ) );
				}
				if ( '' === $base ) {
					$base = 'lang';
				}
				$base = substr( $base, 0, 3 );
				$code = $base;
				$i    = 2;
				while ( isset( $used[ $code ] ) ) {
					$code = $base . $i;
					++$i;
				}
			}

			if ( isset( $used[ $code ] ) ) {
				$i = 2;
				while ( isset( $used[ $code . $i ] ) ) {
					++$i;
				}
				$code = $code . $i;
			}

			$row['code'] = $code;
			$normalized  = Languages::normalize_language( $row );
			if ( ! $normalized ) {
				continue;
			}

			$normalized['enabled'] = ! empty( $row['enabled'] );
			$used[ $code ]         = true;
			$out[]                 = $normalized;
		}

		return array_values( $out );
	}

	/**
	 * Settings string keys.
	 *
	 * @return string[]
	 */
	public static function string_keys(): array {
		return array( 'attachment_label', 'empty_text', 'fixed_title', 'issue_date_label', 'uncategorized_label' );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$settings = self::get_settings();
		$defaults = self::defaults();
		$tab      = isset( $input['nlwp_tab'] ) ? sanitize_key( (string) $input['nlwp_tab'] ) : '';

		if ( '' === $tab || 'languages' === $tab ) {
			$rows = isset( $input['languages'] ) && is_array( $input['languages'] ) ? $input['languages'] : $settings['languages'];
			$settings['languages'] = self::normalize_languages( $rows );
			if ( empty( $settings['languages'] ) ) {
				$settings['languages'] = array( self::fallback_language() );
			}

			$codes   = array_column( $settings['languages'], 'code' );
			$default = Languages::sanitize_code( (string) ( $input['default_language'] ?? $settings['default_language'] ) );
			if ( ! in_array( $default, $codes, true ) ) {
				$default = $settings['languages'][0]['code'];
			}

			foreach ( $settings['languages'] as &$language ) {
				if ( $language['code'] === $default ) {
					$language['enabled'] = true;
				}
			}
			unset( $language );

			$settings['default_language'] = $default;

			$mode = isset( $input['display_mode'] ) ? sanitize_key( (string) $input['display_mode'] ) : $settings['display_mode'];
			$settings['display_mode'] = in_array( $mode, array( 'site', 'stacked' ), true ) ? $mode : $defaults['display_mode'];

			$field_modes = array();
			$raw_modes   = isset( $input['field_modes'] ) && is_array( $input['field_modes'] ) ? $input['field_modes'] : array();
			foreach ( array_keys( $defaults['field_modes'] ) as $key ) {
				$value = isset( $raw_modes[ $key ] ) ? sanitize_key( (string) $raw_modes[ $key ] ) : 'inherit';
				$field_modes[ $key ] = in_array( $value, array( 'inherit', 'site', 'stacked' ), true ) ? $value : 'inherit';
			}
			$settings['field_modes'] = $field_modes;

			$stacked = isset( $input['stacked'] ) && is_array( $input['stacked'] ) ? $input['stacked'] : array();
			$settings['stacked']['show_labels'] = ! empty( $stacked['show_labels'] );
			$label_format = isset( $stacked['label_format'] ) ? sanitize_key( (string) $stacked['label_format'] ) : 'short';
			$settings['stacked']['label_format'] = in_array( $label_format, array( 'short', 'name', 'code' ), true ) ? $label_format : 'short';
			$title_layout = isset( $stacked['title_layout'] ) ? sanitize_key( (string) $stacked['title_layout'] ) : 'inline';
			$settings['stacked']['title_layout'] = in_array( $title_layout, array( 'inline', 'block' ), true ) ? $title_layout : 'inline';
			$settings['stacked']['separator'] = isset( $stacked['separator'] ) ? nlwp_sanitize_separator( $stacked['separator'] ) : $defaults['stacked']['separator'];
			$desc_sep = isset( $stacked['description_separator'] ) ? sanitize_key( (string) $stacked['description_separator'] ) : 'none';
			$settings['stacked']['description_separator'] = in_array( $desc_sep, array( 'none', 'line' ), true ) ? $desc_sep : 'none';

			$settings['hide_untranslated_events'] = ! empty( $input['hide_untranslated_events'] );
		}

		if ( '' === $tab || 'general' === $tab ) {
			$title_source = isset( $input['title_source'] ) ? sanitize_key( (string) $input['title_source'] ) : $settings['title_source'];
			$settings['title_source'] = in_array( $title_source, array( 'issue', 'fixed', 'none' ), true ) ? $title_source : 'issue';

			$title_tag = isset( $input['title_tag'] ) ? sanitize_key( (string) $input['title_tag'] ) : $settings['title_tag'];
			$settings['title_tag'] = in_array( $title_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $title_tag : 'h2';

			$settings['show_intro']      = ! empty( $input['show_intro'] );
			$settings['show_issue_date'] = ! empty( $input['show_issue_date'] );
			$settings['default_period_days'] = isset( $input['default_period_days'] ) ? max( 1, min( 365, absint( $input['default_period_days'] ) ) ) : $settings['default_period_days'];
			$settings['date_format'] = isset( $input['date_format'] ) && '' !== trim( (string) $input['date_format'] ) ? sanitize_text_field( (string) $input['date_format'] ) : $defaults['date_format'];
			$settings['end_date_separator'] = isset( $input['end_date_separator'] ) ? sanitize_text_field( (string) $input['end_date_separator'] ) : $settings['end_date_separator'];

			$raw_strings = isset( $input['strings'] ) && is_array( $input['strings'] ) ? $input['strings'] : array();
			$strings     = array();
			foreach ( self::string_keys() as $key ) {
				$strings[ $key ] = array();
				$raw_key = isset( $raw_strings[ $key ] ) && is_array( $raw_strings[ $key ] ) ? $raw_strings[ $key ] : array();
				foreach ( $settings['languages'] as $language ) {
					$code = $language['code'];
					if ( ! isset( $raw_key[ $code ] ) ) {
						continue;
					}
					$value = 'empty_text' === $key ? wp_kses_post( (string) $raw_key[ $code ] ) : sanitize_text_field( (string) $raw_key[ $code ] );
					if ( '' !== trim( $value ) ) {
						$strings[ $key ][ $code ] = $value;
					}
				}
			}
			$settings['strings'] = $strings;
		}

		if ( '' === $tab || 'layout' === $tab ) {
			foreach ( array( 'show_numbers', 'accordion', 'open_first', 'open_all', 'exclusive_accordion', 'show_categories', 'show_empty_categories', 'show_dates', 'show_end_dates', 'show_attachments', 'attachment_new_tab', 'autolink' ) as $key ) {
				$settings[ $key ] = ! empty( $input[ $key ] );
			}

			$numbering = isset( $input['numbering'] ) ? sanitize_key( (string) $input['numbering'] ) : $settings['numbering'];
			$settings['numbering'] = in_array( $numbering, array( 'continuous', 'per_category' ), true ) ? $numbering : 'continuous';

			$heading_tag = isset( $input['category_heading_tag'] ) ? sanitize_key( (string) $input['category_heading_tag'] ) : $settings['category_heading_tag'];
			$settings['category_heading_tag'] = in_array( $heading_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $heading_tag : 'h3';

			$attachment_style = isset( $input['attachment_style'] ) ? sanitize_key( (string) $input['attachment_style'] ) : $settings['attachment_style'];
			$settings['attachment_style'] = in_array( $attachment_style, array( 'link', 'button' ), true ) ? $attachment_style : 'link';
		}

		if ( '' === $tab || 'appearance' === $tab ) {
			$appearance = isset( $input['appearance'] ) && is_array( $input['appearance'] ) ? $input['appearance'] : array();

			$preset = isset( $appearance['preset'] ) ? sanitize_key( (string) $appearance['preset'] ) : $settings['appearance']['preset'];
			$settings['appearance']['preset'] = in_array( $preset, array( 'light', 'dark', 'custom' ), true ) ? $preset : 'light';

			$raw_colors = isset( $appearance['colors'] ) && is_array( $appearance['colors'] ) ? $appearance['colors'] : array();
			foreach ( array_keys( $defaults['appearance']['colors'] ) as $key ) {
				$color = isset( $raw_colors[ $key ] ) ? sanitize_hex_color( (string) $raw_colors[ $key ] ) : '';
				$settings['appearance']['colors'][ $key ] = $color ? $color : $settings['appearance']['colors'][ $key ];
			}

			foreach ( array( 'font_family', 'font_size', 'max_width', 'border_radius', 'gap', 'logo_size' ) as $key ) {
				if ( isset( $appearance[ $key ] ) ) {
					$settings['appearance'][ $key ] = sanitize_text_field( (string) $appearance[ $key ] );
				}
			}

			$settings['appearance']['logo_id'] = isset( $appearance['logo_id'] ) ? absint( $appearance['logo_id'] ) : $settings['appearance']['logo_id'];
			$align = isset( $appearance['logo_align'] ) ? sanitize_key( (string) $appearance['logo_align'] ) : $settings['appearance']['logo_align'];
			$settings['appearance']['logo_align'] = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';
			$settings['appearance']['custom_css'] = isset( $appearance['custom_css'] ) ? nlwp_sanitize_css( $appearance['custom_css'] ) : $settings['appearance']['custom_css'];
		}

		if ( '' === $tab || 'advanced' === $tab ) {
			$settings['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
		}

		return $settings;
	}
}
