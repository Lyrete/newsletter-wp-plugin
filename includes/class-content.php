<?php
/**
 * Shared newsletter content builder.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Builds language-aware content items used by the website and email renderers.
 */
class Content {

	/**
	 * Build the language context for the website renderer.
	 *
	 * @param string   $current_lang Active language.
	 * @param callable $field_mode   Field mode resolver.
	 * @return array
	 */
	public static function web_context( string $current_lang, callable $field_mode ): array {
		$codes = static function ( string $field ) use ( $field_mode, $current_lang ): array {
			return 'stacked' === $field_mode( $field )
				? array_column( Languages::all( true ), 'code' )
				: array( $current_lang );
		};

		$post_fallback = static function ( string $field ) use ( $field_mode, $current_lang ): string {
			return 'stacked' === $field_mode( $field ) ? '' : $current_lang;
		};

		$string_fallback = static function ( string $field ) use ( $field_mode, $current_lang ): string {
			return 'stacked' === $field_mode( $field ) ? Languages::default_code() : $current_lang;
		};

		$dedupe = static function ( string $field ) use ( $field_mode ): bool {
			return 'stacked' === $field_mode( $field );
		};

		return array(
			'lang'               => $current_lang,
			'issue_title'        => array(
				'codes'    => $codes( 'issue_title' ),
				'fallback' => $post_fallback( 'issue_title' ),
				'dedupe'   => false,
			),
			'issue_intro'        => array(
				'codes'    => $codes( 'issue_intro' ),
				'fallback' => $post_fallback( 'issue_intro' ),
				'dedupe'   => false,
			),
			'category_labels'    => array(
				'codes'    => $codes( 'category_labels' ),
				'fallback' => $string_fallback( 'category_labels' ),
				'dedupe'   => $dedupe( 'category_labels' ),
			),
			'event_titles'       => array(
				'codes'    => $codes( 'event_titles' ),
				'fallback' => $post_fallback( 'event_titles' ),
				'dedupe'   => false,
			),
			'event_descriptions' => array(
				'codes'    => $codes( 'event_descriptions' ),
				'fallback' => $post_fallback( 'event_descriptions' ),
				'dedupe'   => false,
			),
			'ui_strings'         => array(
				'codes'    => $codes( 'ui_strings' ),
				'fallback' => $string_fallback( 'ui_strings' ),
				'dedupe'   => $dedupe( 'ui_strings' ),
			),
			'hide_untranslated_lang' => '',
		);
	}

	/**
	 * Build the language context for email rendering.
	 *
	 * @param string[] $codes   Content language codes.
	 * @param string   $primary Primary language code.
	 * @return array
	 */
	public static function email_context( array $codes, string $primary ): array {
		return array(
			'lang'               => $primary,
			'issue_title'        => array(
				'codes'    => $codes,
				'fallback' => $primary,
				'dedupe'   => true,
			),
			'issue_intro'        => array(
				'codes'    => $codes,
				'fallback' => $primary,
				'dedupe'   => false,
			),
			'category_labels'    => array(
				'codes'    => $codes,
				'fallback' => $primary,
				'dedupe'   => true,
			),
			'event_titles'       => array(
				'codes'    => $codes,
				'fallback' => $primary,
				'dedupe'   => false,
			),
			'event_descriptions' => array(
				'codes'    => $codes,
				'fallback' => $primary,
				'dedupe'   => false,
			),
			'ui_strings'         => array(
				'codes'    => array( $primary ),
				'fallback' => $primary,
				'dedupe'   => false,
			),
			'hide_untranslated_lang' => '',
		);
	}

	/**
	 * Build text items for a translated post field.
	 *
	 * @param int      $post_id       Post ID.
	 * @param string   $field         Field key.
	 * @param string[] $codes         Language codes to include.
	 * @param string   $fallback_lang Language that may fall back to the default.
	 * @param bool     $allow_html    Whether HTML is allowed.
	 * @param array    $settings      Settings.
	 * @param bool     $autolink      Whether to link URLs in HTML content.
	 * @return array
	 */
	public static function post_field_items( int $post_id, string $field, array $codes, string $fallback_lang, bool $allow_html, array $settings, bool $autolink = false ): array {
		$items         = array();
		$fallback_lang = Languages::sanitize_code( $fallback_lang );

		foreach ( $codes as $code ) {
			$code = Languages::sanitize_code( (string) $code );
			if ( '' === $code ) {
				continue;
			}

			$text      = Translations::get_post_text( $post_id, $field, $code, false );
			$text_lang = $code;

			if ( '' === trim( wp_strip_all_tags( $text ) ) && $code === $fallback_lang ) {
				$text_lang = Languages::default_code();
				$text      = Translations::get_post_text( $post_id, $field, $text_lang, false );
			}

			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			$items[] = self::make_item( $code, $text_lang, $text, $allow_html, $settings, $autolink );
		}

		return $items;
	}

	/**
	 * Build text items for a translated settings string.
	 *
	 * @param string   $key           String key.
	 * @param string[] $codes         Language codes to include.
	 * @param string   $fallback_lang Language that may fall back to the default or built-in text.
	 * @param array    $settings      Settings.
	 * @param bool     $allow_html    Whether HTML is allowed.
	 * @param bool     $dedupe        Whether to remove duplicate texts.
	 * @return array
	 */
	public static function setting_string_items( string $key, array $codes, string $fallback_lang, array $settings, bool $allow_html = false, bool $dedupe = false ): array {
		$items         = array();
		$seen          = array();
		$fallback_lang = Languages::sanitize_code( $fallback_lang );

		foreach ( $codes as $code ) {
			$code = Languages::sanitize_code( (string) $code );
			if ( '' === $code ) {
				continue;
			}

			$text      = Translations::get_setting_string( $key, $code, false );
			$text_lang = $code;

			if ( '' === trim( wp_strip_all_tags( $text ) ) && $code === $fallback_lang ) {
				$text_lang = Languages::default_code();
				$text      = Translations::get_setting_string( $key, $text_lang, false );
				if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
					$text = Translations::builtin_string( $key );
				}
			}

			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			$dedupe_key = trim( wp_strip_all_tags( $text ) );
			if ( $dedupe && isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$items[] = self::make_item( $code, $text_lang, $text, $allow_html, $settings, false );
		}

		return $items;
	}

	/**
	 * Build category label items.
	 *
	 * @param int   $term_id Term ID, 0 for uncategorized.
	 * @param array $context Language context.
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function category_label_items( int $term_id, array $context, array $settings ): array {
		if ( ! $term_id ) {
			$ui = isset( $context['ui_strings'] ) && is_array( $context['ui_strings'] ) ? $context['ui_strings'] : array();
			return self::setting_string_items(
				'uncategorized_label',
				isset( $ui['codes'] ) && is_array( $ui['codes'] ) ? $ui['codes'] : array(),
				(string) ( $ui['fallback'] ?? '' ),
				$settings,
				false,
				! empty( $ui['dedupe'] )
			);
		}

		$category = isset( $context['category_labels'] ) && is_array( $context['category_labels'] ) ? $context['category_labels'] : array();
		$codes    = isset( $category['codes'] ) && is_array( $category['codes'] ) ? $category['codes'] : array();
		$fallback = Languages::sanitize_code( (string) ( $category['fallback'] ?? '' ) );
		$dedupe   = ! empty( $category['dedupe'] );
		$items    = array();
		$seen     = array();

		foreach ( $codes as $code ) {
			$code = Languages::sanitize_code( (string) $code );
			if ( '' === $code ) {
				continue;
			}

			$label = Translations::get_term_label( $term_id, $code, false );

			if ( '' === trim( $label ) && $code === $fallback ) {
				$label = Translations::get_term_label( $term_id, Languages::default_code(), true );
			}

			if ( '' === trim( $label ) ) {
				continue;
			}

			$dedupe_key = trim( $label );
			if ( $dedupe && isset( $seen[ $dedupe_key ] ) ) {
				continue;
			}
			$seen[ $dedupe_key ] = true;

			$items[] = array(
				'code' => $code,
				'lang' => $code,
				'dir'  => Languages::dir( $code ),
				'text' => $label,
				'html' => esc_html( $label ),
			);
		}

		return $items;
	}

	/**
	 * Build grouped events for an issue.
	 *
	 * @param int   $issue_id Issue ID.
	 * @param array $context  Language context.
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function build_groups( int $issue_id, array $context, array $settings ): array {
		$ids            = nlwp_sanitize_id_array( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
		$selection_mode = (string) get_post_meta( $issue_id, '_nlwp_selection_mode', true );

		if ( empty( $ids ) && 'manual' !== $selection_mode ) {
			$ids = Issue_Selection::get_auto_event_ids(
				(string) get_post_meta( $issue_id, '_nlwp_period_start', true ),
				(string) get_post_meta( $issue_id, '_nlwp_period_end', true )
			);
		}

		$ids            = Issue_Selection::sort_event_ids( $ids );
		$grouped        = array();
		$continuous     = 0;
		$hide_lang      = Languages::sanitize_code( (string) ( $context['hide_untranslated_lang'] ?? '' ) );

		foreach ( $ids as $event_id ) {
			if ( Post_Types::EVENT !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
				continue;
			}

			if ( '' !== $hide_lang && ! Translations::has_translation( $event_id, $hide_lang ) ) {
				continue;
			}

			$term    = Issue_Selection::get_event_category_term( $event_id );
			$term_id = $term ? (int) $term->term_id : 0;

			if ( $term && '0' === (string) get_term_meta( $term_id, '_nlwp_enabled', true ) ) {
				continue;
			}

			if ( ! isset( $grouped[ $term_id ] ) ) {
				$grouped[ $term_id ] = array(
					'term_id'     => $term_id,
					'label_items' => self::category_label_items( $term_id, $context, $settings ),
					'events'      => array(),
				);
			}

			++$continuous;
			$number = 'per_category' === $settings['numbering'] ? count( $grouped[ $term_id ]['events'] ) + 1 : $continuous;

			$grouped[ $term_id ]['events'][] = self::build_event( $event_id, $context, $settings, $number, 1 === $continuous );
		}

		if ( ! empty( $settings['show_empty_categories'] ) && ! empty( $settings['show_categories'] ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => Taxonomy::TAXONOMY,
					'hide_empty' => false,
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				foreach ( Issue_Selection::sort_terms( $terms ) as $term ) {
					if ( isset( $grouped[ $term->term_id ] ) || '0' === (string) get_term_meta( $term->term_id, '_nlwp_enabled', true ) ) {
						continue;
					}
					$grouped[ $term->term_id ] = array(
						'term_id'     => (int) $term->term_id,
						'label_items' => self::category_label_items( (int) $term->term_id, $context, $settings ),
						'events'      => array(),
					);
				}
			}
		}

		$order_map = Issue_Selection::get_category_order_map();
		$groups    = array_values( $grouped );

		usort(
			$groups,
			static function ( $a, $b ) use ( $order_map ) {
				if ( 0 === $a['term_id'] && 0 !== $b['term_id'] ) {
					return 1;
				}
				if ( 0 !== $a['term_id'] && 0 === $b['term_id'] ) {
					return -1;
				}
				$order_a = isset( $order_map[ $a['term_id'] ] ) ? (int) $order_map[ $a['term_id'] ] : 0;
				$order_b = isset( $order_map[ $b['term_id'] ] ) ? (int) $order_map[ $b['term_id'] ] : 0;
				if ( $order_a !== $order_b ) {
					return $order_a <=> $order_b;
				}
				return strcmp( self::plain_label( $a['label_items'] ), self::plain_label( $b['label_items'] ) );
			}
		);

		return $groups;
	}

	/**
	 * Build one event's raw display data.
	 *
	 * @param int   $event_id Event ID.
	 * @param array $context  Language context.
	 * @param array $settings Settings.
	 * @param int   $number   Display number.
	 * @param bool  $is_first Whether this is the first event.
	 * @return array
	 */
	public static function build_event( int $event_id, array $context, array $settings, int $number, bool $is_first ): array {
		$title_context = isset( $context['event_titles'] ) && is_array( $context['event_titles'] ) ? $context['event_titles'] : array();
		$desc_context  = isset( $context['event_descriptions'] ) && is_array( $context['event_descriptions'] ) ? $context['event_descriptions'] : array();
		$ui_context    = isset( $context['ui_strings'] ) && is_array( $context['ui_strings'] ) ? $context['ui_strings'] : array();

		$title_items = self::post_field_items(
			$event_id,
			'title',
			isset( $title_context['codes'] ) && is_array( $title_context['codes'] ) ? $title_context['codes'] : array(),
			(string) ( $title_context['fallback'] ?? '' ),
			false,
			$settings
		);

		$description_items = self::post_field_items(
			$event_id,
			'description',
			isset( $desc_context['codes'] ) && is_array( $desc_context['codes'] ) ? $desc_context['codes'] : array(),
			(string) ( $desc_context['fallback'] ?? '' ),
			true,
			$settings,
			true
		);

		$show_date = get_post_meta( $event_id, '_nlwp_show_date', true );
		$show_date = '' === $show_date ? true : '1' === (string) $show_date;

		$date = null;
		if ( ! empty( $settings['show_dates'] ) && $show_date ) {
			$start = nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_start_date', true ) );
			if ( '' !== $start ) {
				$date = array(
					'start' => $start,
					'end'   => nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_end_date', true ) ),
				);
			}
		}

		$attachment = null;
		if ( ! empty( $settings['show_attachments'] ) ) {
			$attachment_id = absint( get_post_meta( $event_id, '_nlwp_attachment_id', true ) );
			if ( $attachment_id ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$name = get_the_title( $attachment_id );
					if ( '' === $name ) {
						$name = wp_basename( $url );
					}
					$attachment = array(
						'url'         => $url,
						'name'        => $name,
						'label_items' => self::setting_string_items(
							'attachment_label',
							isset( $ui_context['codes'] ) && is_array( $ui_context['codes'] ) ? $ui_context['codes'] : array(),
							(string) ( $ui_context['fallback'] ?? '' ),
							$settings,
							false,
							! empty( $ui_context['dedupe'] )
						),
					);
				}
			}
		}

		$open = ! empty( $settings['open_all'] ) || ( ! empty( $settings['open_first'] ) && $is_first );

		return array(
			'id'                => $event_id,
			'number'            => ! empty( $settings['show_numbers'] ) ? $number : 0,
			'title_items'       => $title_items,
			'description_items' => $description_items,
			'date'              => $date,
			'attachment'        => $attachment,
			'open'              => $open,
			'anchor'            => 'nlwp-event-' . $event_id,
		);
	}

	/**
	 * Format a date for a language.
	 *
	 * @param string $date     Y-m-d date.
	 * @param string $lang     Language code.
	 * @param array  $settings Settings.
	 * @return string
	 */
	public static function format_date( string $date, string $lang, array $settings ): string {
		$language = Languages::get( $lang );
		$format   = $language && '' !== $language['date_format'] ? $language['date_format'] : $settings['date_format'];

		try {
			$datetime = new \DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		} catch ( \Exception $e ) {
			return $date;
		}

		return (string) wp_date( $format, $datetime->getTimestamp(), wp_timezone() );
	}

	/**
	 * Convert item text to plain text.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	public static function to_plain( string $text ): string {
		$text = preg_replace( '/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $text );
		$text = wp_strip_all_tags( (string) $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[ \t]+/u', ' ', $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", (string) $text );

		return trim( (string) $text );
	}

	/**
	 * Make a content item.
	 *
	 * @param string $code      Requested language code.
	 * @param string $text_lang Actual text language.
	 * @param string $text      Raw text.
	 * @param bool   $allow_html Whether HTML is allowed.
	 * @param array  $settings  Settings.
	 * @param bool   $autolink  Whether to link URLs in HTML content.
	 * @return array
	 */
	private static function make_item( string $code, string $text_lang, string $text, bool $allow_html, array $settings, bool $autolink ): array {
		if ( $allow_html ) {
			$content = wp_kses_post( $text );
			if ( $autolink && ! empty( $settings['autolink'] ) ) {
				$content = make_clickable( $content );
			}
		} else {
			$content = esc_html( $text );
		}

		return array(
			'code' => $code,
			'lang' => $text_lang,
			'dir'  => Languages::dir( $text_lang ),
			'text' => $text,
			'html' => $content,
		);
	}

	/**
	 * Build a plain label for sorting.
	 *
	 * @param array $items Label items.
	 * @return string
	 */
	private static function plain_label( array $items ): string {
		$parts = array();
		foreach ( $items as $item ) {
			$parts[] = wp_strip_all_tags( (string) ( $item['html'] ?? '' ) );
		}

		return implode( ' ', $parts );
	}
}
