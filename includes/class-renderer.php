<?php
/**
 * Newsletter renderer.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the public newsletter output.
 */
class Renderer {

	/**
	 * Default shortcode and block arguments.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'issue_id'     => 0,
			'language'     => '',
			'display_mode' => '',
			'show_title'   => null,
			'show_intro'   => null,
			'class'        => '',
		);
	}

	/**
	 * Render a newsletter.
	 *
	 * @param array $args Render arguments.
	 * @return string
	 */
	public static function render( array $args = array() ): string {
		$args     = wp_parse_args( $args, self::defaults() );
		$settings = Settings::get_settings();

		$issue_id = self::resolve_issue_id( $args );
		if ( ! $issue_id ) {
			return '';
		}

		$issue = get_post( $issue_id );
		if ( ! $issue || Post_Types::ISSUE !== $issue->post_type ) {
			return '';
		}

		if ( 'publish' !== $issue->post_status && ! current_user_can( 'edit_post', $issue_id ) ) {
			return '';
		}

		$current_lang = '' !== (string) $args['language']
			? Languages::resolve_code( (string) $args['language'] )
			: Languages::current_code();

		$placement_mode = in_array( (string) $args['display_mode'], array( 'site', 'stacked' ), true )
			? (string) $args['display_mode']
			: '';

		$global_mode = '' !== $placement_mode ? $placement_mode : $settings['display_mode'];

		$field_mode = static function ( string $field ) use ( $settings, $global_mode, $placement_mode ): string {
			if ( '' !== $placement_mode ) {
				return $placement_mode;
			}

			$mode = isset( $settings['field_modes'][ $field ] ) ? $settings['field_modes'][ $field ] : 'inherit';
			return in_array( $mode, array( 'site', 'stacked' ), true ) ? $mode : $global_mode;
		};

		self::enqueue_assets( $settings, $current_lang );

		$ui_mode    = $field_mode( 'ui_strings' );
		$show_title = null === $args['show_title'] ? true : (bool) $args['show_title'];
		$show_intro = null === $args['show_intro'] ? ! empty( $settings['show_intro'] ) : (bool) $args['show_intro'];

		$title_html = '';
		if ( $show_title ) {
			if ( 'issue' === $settings['title_source'] ) {
				$title_html = self::field_html( $issue_id, 'title', $field_mode( 'issue_title' ), $current_lang, $settings, true, false );
			} elseif ( 'fixed' === $settings['title_source'] ) {
				$title_html = self::setting_string_html( 'fixed_title', $current_lang, $ui_mode, $settings, true );
			}
		}

		$intro_html = '';
		if ( $show_intro ) {
			$intro_html = self::field_html( $issue_id, 'intro', $field_mode( 'issue_intro' ), $current_lang, $settings, false, true );
		}

		$issue_date_html = '';
		if ( ! empty( $settings['show_issue_date'] ) ) {
			$date = nlwp_sanitize_date( (string) get_post_meta( $issue_id, '_nlwp_issue_date', true ) );
			if ( '' !== $date ) {
				$label           = self::setting_string_html( 'issue_date_label', $current_lang, $ui_mode, $settings );
				$issue_date_html = '<p class="nlwp-issue-date">';
				if ( '' !== $label ) {
					$issue_date_html .= '<span class="nlwp-issue-date-label">' . $label . '</span> ';
				}
				$issue_date_html .= '<time datetime="' . esc_attr( $date ) . '">' . esc_html( self::format_date( $date, $current_lang, $settings ) ) . '</time></p>';
			}
		}

		$groups   = self::build_groups( $issue_id, $current_lang, $field_mode, $settings, $global_mode, $ui_mode );
		$classes  = array( 'nlwp', 'nlwp-mode-' . $global_mode, 'nlwp-lang-' . $current_lang );
		if ( ! empty( $settings['exclusive_accordion'] ) ) {
			$classes[] = 'nlwp-exclusive';
		}
		$extra    = preg_split( '/\s+/', trim( (string) $args['class'] ) );
		$extra    = is_array( $extra ) ? $extra : array();
		foreach ( $extra as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}

		$data = array(
			'settings'        => $settings,
			'args'            => $args,
			'issue'           => $issue,
			'issue_id'        => $issue_id,
			'lang'            => $current_lang,
			'dir'             => Languages::dir( $current_lang ),
			'mode'            => $global_mode,
			'classes'         => implode( ' ', array_unique( $classes ) ),
			'title_tag'       => $settings['title_tag'],
			'title_html'      => $title_html,
			'intro_html'      => $intro_html,
			'issue_date_html' => $issue_date_html,
			'logo_html'       => self::logo_html( $settings ),
			'show_categories' => ! empty( $settings['show_categories'] ),
			'category_tag'    => $settings['category_heading_tag'],
			'groups'          => $groups,
			'accordion'       => ! empty( $settings['accordion'] ),
			'empty_text'      => self::setting_string_html( 'empty_text', $current_lang, $ui_mode, $settings ),
		);

		return self::template( 'newsletter', $data );
	}

	/**
	 * Load a template file.
	 *
	 * @param string $name Template name without extension.
	 * @param array  $data Variables for the template.
	 * @return string
	 */
	public static function template( string $name, array $data = array() ): string {
		$file = NLWP_DIR . 'templates/' . sanitize_file_name( $name ) . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}

		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		ob_start();
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Find the issue to render.
	 *
	 * @param array $args Render arguments.
	 * @return int
	 */
	private static function resolve_issue_id( array $args ): int {
		$issue_id = isset( $args['issue_id'] ) ? absint( $args['issue_id'] ) : 0;
		if ( $issue_id ) {
			return $issue_id;
		}

		$ids = get_posts(
			array(
				'post_type'      => Post_Types::ISSUE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		return ! empty( $ids ) ? (int) $ids[0] : 0;
	}

	/**
	 * Build grouped events for an issue.
	 *
	 * @param int      $issue_id     Issue ID.
	 * @param string   $current_lang Active language.
	 * @param callable $field_mode   Field mode resolver.
	 * @param array    $settings     Settings.
	 * @param string   $global_mode  Global display mode.
	 * @param string   $ui_mode      Interface string display mode.
	 * @return array
	 */
	private static function build_groups( int $issue_id, string $current_lang, callable $field_mode, array $settings, string $global_mode, string $ui_mode ): array {
		$ids = nlwp_sanitize_id_array( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );

		$selection_mode = (string) get_post_meta( $issue_id, '_nlwp_selection_mode', true );
		if ( empty( $ids ) && 'manual' !== $selection_mode ) {
			$ids = Issue_Selection::get_auto_event_ids(
				(string) get_post_meta( $issue_id, '_nlwp_period_start', true ),
				(string) get_post_meta( $issue_id, '_nlwp_period_end', true )
			);
		}

		$ids       = Issue_Selection::sort_event_ids( $ids );
		$grouped   = array();
		$continuous = 0;
		$site_event_fields = 'site' === $field_mode( 'event_titles' ) || 'site' === $field_mode( 'event_descriptions' );

		foreach ( $ids as $event_id ) {
			if ( Post_Types::EVENT !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
				continue;
			}

			if ( ! empty( $settings['hide_untranslated_events'] ) && $site_event_fields && $current_lang !== Languages::default_code() && ! Translations::has_translation( $event_id, $current_lang ) ) {
				continue;
			}

			$term    = Issue_Selection::get_event_category_term( $event_id );
			$term_id = $term ? (int) $term->term_id : 0;

			if ( $term && '0' === (string) get_term_meta( $term_id, '_nlwp_enabled', true ) ) {
				continue;
			}

			if ( ! isset( $grouped[ $term_id ] ) ) {
				$grouped[ $term_id ] = array(
					'term_id' => $term_id,
					'label'   => self::category_label_html( $term_id, $current_lang, $field_mode( 'category_labels' ), $settings, $ui_mode ),
					'events'  => array(),
				);
			}

			++$continuous;
			$number = 'per_category' === $settings['numbering'] ? count( $grouped[ $term_id ]['events'] ) + 1 : $continuous;

			$grouped[ $term_id ]['events'][] = self::build_event( $event_id, $current_lang, $field_mode, $settings, $number, 1 === $continuous, $global_mode, $ui_mode );
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
						'term_id' => (int) $term->term_id,
						'label'   => self::category_label_html( (int) $term->term_id, $current_lang, $field_mode( 'category_labels' ), $settings, $ui_mode ),
						'events'  => array(),
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
				return strcmp( wp_strip_all_tags( $a['label'] ), wp_strip_all_tags( $b['label'] ) );
			}
		);

		return $groups;
	}

	/**
	 * Build one event's display data.
	 *
	 * @param int      $event_id     Event ID.
	 * @param string   $current_lang Active language.
	 * @param callable $field_mode   Field mode resolver.
	 * @param array    $settings     Settings.
	 * @param int      $number       Display number.
	 * @param bool     $is_first     Whether this is the first event.
	 * @param string   $global_mode  Global display mode.
	 * @param string   $ui_mode      Interface string display mode.
	 * @return array
	 */
	private static function build_event( int $event_id, string $current_lang, callable $field_mode, array $settings, int $number, bool $is_first, string $global_mode, string $ui_mode ): array {
		$title = self::field_html( $event_id, 'title', $field_mode( 'event_titles' ), $current_lang, $settings, true, false );
		$body  = self::field_html( $event_id, 'description', $field_mode( 'event_descriptions' ), $current_lang, $settings, false, true );

		$show_date = get_post_meta( $event_id, '_nlwp_show_date', true );
		$show_date = '' === $show_date ? true : '1' === (string) $show_date;

		$date_html = '';
		if ( ! empty( $settings['show_dates'] ) && $show_date ) {
			$date_html = self::event_date_html( $event_id, $current_lang, $settings );
		}

		$open = ! empty( $settings['open_all'] ) || ( ! empty( $settings['open_first'] ) && $is_first );

		return array(
			'id'              => $event_id,
			'number'          => ! empty( $settings['show_numbers'] ) ? $number : 0,
			'title_html'      => $title,
			'description_html'=> $body,
			'date_html'       => $date_html,
			'attachment_html' => self::attachment_html( $event_id, $current_lang, $settings, $ui_mode ),
			'open'            => $open,
			'anchor'          => 'nlwp-event-' . $event_id,
		);
	}

	/**
	 * Render a translated text field.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $field        Field key.
	 * @param string $mode         site or stacked.
	 * @param string $current_lang Active language.
	 * @param array  $settings     Settings.
	 * @param bool   $is_title     Whether this is a title field.
	 * @param bool   $allow_html   Whether HTML is allowed.
	 * @return string
	 */
	private static function field_html( int $post_id, string $field, string $mode, string $current_lang, array $settings, bool $is_title, bool $allow_html ): string {
		$codes = 'stacked' === $mode ? array_column( Languages::all( true ), 'code' ) : array( $current_lang );
		$items = array();

		foreach ( $codes as $code ) {
			$text      = Translations::get_post_text( $post_id, $field, $code, false );
			$text_lang = $code;

			if ( 'stacked' !== $mode && '' === trim( wp_strip_all_tags( $text ) ) ) {
				$text_lang = Languages::default_code();
				$text      = Translations::get_post_text( $post_id, $field, $text_lang, false );
			}

			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			if ( $allow_html ) {
				$content = wp_kses_post( $text );
				if ( ! empty( $settings['autolink'] ) ) {
					$content = make_clickable( $content );
				}
			} else {
				$content = esc_html( $text );
			}

			$items[] = array(
				'code'    => $code,
				'content' => $content,
				'lang'    => $text_lang,
				'dir'     => Languages::dir( $text_lang ),
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		if ( $is_title ) {
			return self::title_stack_html( $items, $settings );
		}

		return self::text_stack_html( $items, $settings );
	}

	/**
	 * Render stacked titles.
	 *
	 * @param array $items    Text items.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function title_stack_html( array $items, array $settings ): string {
		if ( 1 === count( $items ) ) {
			return '<span class="nlwp-lang-text" lang="' . esc_attr( $items[0]['lang'] ) . '" dir="' . esc_attr( $items[0]['dir'] ) . '">' . $items[0]['content'] . '</span>';
		}

		$show_labels = ! empty( $settings['stacked']['show_labels'] );
		$format      = isset( $settings['stacked']['label_format'] ) ? $settings['stacked']['label_format'] : 'short';
		$block       = isset( $settings['stacked']['title_layout'] ) && 'block' === $settings['stacked']['title_layout'];
		$separator   = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';

		$parts = array();
		foreach ( $items as $item ) {
			$part = '';
			if ( $show_labels ) {
				$part .= '<span class="nlwp-lang-label">' . esc_html( Languages::label( $item['code'], $format ) ) . '</span> ';
			}
			$part .= '<span class="nlwp-lang-text" lang="' . esc_attr( $item['lang'] ) . '" dir="' . esc_attr( $item['dir'] ) . '">' . $item['content'] . '</span>';
			$parts[] = $block ? '<span class="nlwp-lang-line">' . $part . '</span>' : $part;
		}

		$glue = $block ? '' : esc_html( $separator );

		return '<span class="nlwp-title-stack">' . implode( $glue, $parts ) . '</span>';
	}

	/**
	 * Render stacked rich text.
	 *
	 * @param array $items    Text items.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function text_stack_html( array $items, array $settings ): string {
		if ( 1 === count( $items ) ) {
			return '<div class="nlwp-lang-text" lang="' . esc_attr( $items[0]['lang'] ) . '" dir="' . esc_attr( $items[0]['dir'] ) . '">' . $items[0]['content'] . '</div>';
		}

		$separator = isset( $settings['stacked']['description_separator'] ) ? $settings['stacked']['description_separator'] : 'none';
		$parts     = array();

		foreach ( $items as $item ) {
			$parts[] = '<div class="nlwp-lang-text" lang="' . esc_attr( $item['lang'] ) . '" dir="' . esc_attr( $item['dir'] ) . '">' . $item['content'] . '</div>';
		}

		$glue = 'line' === $separator ? '<hr class="nlwp-lang-separator">' : '';
		return implode( $glue, $parts );
	}

	/**
	 * Render a translated interface string.
	 *
	 * @param string $key          String key.
	 * @param string $current_lang Active language.
	 * @param string $mode         site or stacked.
	 * @param array  $settings     Settings.
	 * @param bool   $is_title     Whether the string is used as a title.
	 * @return string
	 */
	private static function setting_string_html( string $key, string $current_lang, string $mode, array $settings, bool $is_title = false ): string {
		$codes          = 'stacked' === $mode ? array_column( Languages::all( true ), 'code' ) : array( $current_lang );
		$default        = Languages::default_code();
		$items          = array();
		$seen           = array();

		foreach ( $codes as $code ) {
			$text      = Translations::get_setting_string( $key, $code, false );
			$text_lang = $code;

			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				if ( 'stacked' === $mode ) {
					if ( $code !== $default ) {
						continue;
					}
					$text = Translations::builtin_string( $key );
				} else {
					$text_lang = $default;
					$text      = Translations::get_setting_string( $key, $text_lang, false );
					if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
						$text = Translations::builtin_string( $key );
					}
				}
			}

			if ( '' === trim( wp_strip_all_tags( $text ) ) ) {
				continue;
			}

			$content = 'empty_text' === $key ? wp_kses_post( $text ) : esc_html( $text );

			if ( 'stacked' === $mode ) {
				$dedupe = trim( wp_strip_all_tags( $content ) );
				if ( isset( $seen[ $dedupe ] ) ) {
					continue;
				}
				$seen[ $dedupe ] = true;
			}

			$items[] = array(
				'code'    => $code,
				'content' => $content,
				'lang'    => $text_lang,
				'dir'     => Languages::dir( $text_lang ),
			);
		}

		if ( empty( $items ) ) {
			return '';
		}

		if ( $is_title ) {
			return self::title_stack_html( $items, $settings );
		}

		$separator = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';
		return implode( esc_html( $separator ), array_column( $items, 'content' ) );
	}

	/**
	 * Render a category label.
	 *
	 * @param int    $term_id      Term ID, 0 for uncategorized.
	 * @param string $current_lang Active language.
	 * @param string $mode         Field mode.
	 * @param array  $settings     Settings.
	 * @param string $ui_mode      Interface string display mode.
	 * @return string
	 */
	private static function category_label_html( int $term_id, string $current_lang, string $mode, array $settings, string $ui_mode ): string {
		if ( ! $term_id ) {
			return self::setting_string_html( 'uncategorized_label', $current_lang, $ui_mode, $settings );
		}

		if ( 'stacked' !== $mode ) {
			return esc_html( Translations::get_term_label( $term_id, $current_lang ) );
		}

		$labels = array();
		foreach ( Languages::all( true ) as $language ) {
			$label = Translations::get_term_label( $term_id, $language['code'], $language['code'] === Languages::default_code() );
			if ( '' !== trim( $label ) ) {
				$labels[] = esc_html( $label );
			}
		}

		$separator = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';
		return implode( esc_html( $separator ), array_unique( $labels ) );
	}

	/**
	 * Render an event date range.
	 *
	 * @param int    $event_id     Event ID.
	 * @param string $current_lang Active language.
	 * @param array  $settings     Settings.
	 * @return string
	 */
	private static function event_date_html( int $event_id, string $current_lang, array $settings ): string {
		$start = nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_start_date', true ) );
		if ( '' === $start ) {
			return '';
		}

		$end  = nlwp_sanitize_date( (string) get_post_meta( $event_id, '_nlwp_end_date', true ) );
		$html = '<span class="nlwp-event-date"><time datetime="' . esc_attr( $start ) . '">' . esc_html( self::format_date( $start, $current_lang, $settings ) ) . '</time>';

		if ( ! empty( $settings['show_end_dates'] ) && '' !== $end && $end !== $start ) {
			$separator = isset( $settings['end_date_separator'] ) ? (string) $settings['end_date_separator'] : '–';
			$html     .= '<span class="nlwp-date-separator"> ' . esc_html( $separator ) . ' </span>';
			$html     .= '<time datetime="' . esc_attr( $end ) . '">' . esc_html( self::format_date( $end, $current_lang, $settings ) ) . '</time>';
		}

		return $html . '</span>';
	}

	/**
	 * Render an attachment link.
	 *
	 * @param int    $event_id     Event ID.
	 * @param string $current_lang Active language.
	 * @param array  $settings     Settings.
	 * @param string $ui_mode      Interface string display mode.
	 * @return string
	 */
	private static function attachment_html( int $event_id, string $current_lang, array $settings, string $ui_mode ): string {
		if ( empty( $settings['show_attachments'] ) ) {
			return '';
		}

		$attachment_id = absint( get_post_meta( $event_id, '_nlwp_attachment_id', true ) );
		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return '';
		}

		$label = self::setting_string_html( 'attachment_label', $current_lang, $ui_mode, $settings );
		if ( '' === $label ) {
			$label = __( 'Attachment', 'newsletter-wp-plugin' );
		}

		$name = get_the_title( $attachment_id );
		if ( '' === $name ) {
			$name = wp_basename( $url );
		}

		$style = 'button' === $settings['attachment_style'] ? 'nlwp-attachment-button' : 'nlwp-attachment-link';
		$target = ! empty( $settings['attachment_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		return '<p class="nlwp-attachment"><a class="' . esc_attr( $style ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . $label . ': <span class="nlwp-attachment-name">' . esc_html( $name ) . '</span></a></p>';
	}

	/**
	 * Format a date for a language.
	 *
	 * @param string $date        Y-m-d date.
	 * @param string $lang        Language code.
	 * @param array  $settings    Settings.
	 * @return string
	 */
	private static function format_date( string $date, string $lang, array $settings ): string {
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
	 * Build the optional logo markup.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function logo_html( array $settings ): string {
		$logo_id = absint( $settings['appearance']['logo_id'] ?? 0 );
		if ( ! $logo_id ) {
			return '';
		}

		$size  = (string) ( $settings['appearance']['logo_size'] ?? '' );
		$align = (string) ( $settings['appearance']['logo_align'] ?? 'center' );
		$align = in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : 'center';

		$image = wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class' => 'nlwp-logo-image',
				'alt'   => get_the_title( $logo_id ),
				'style' => '' !== $size ? 'width:' . $size . ';' : '',
			)
		);

		if ( ! $image ) {
			return '';
		}

		return '<div class="nlwp-logo nlwp-logo-' . esc_attr( $align ) . '">' . $image . '</div>';
	}

	/**
	 * Enqueue public assets and build CSS variables.
	 *
	 * @param array  $settings Settings.
	 * @param string $lang     Active language.
	 * @return void
	 */
	private static function enqueue_assets( array $settings, string $lang ): void {
		wp_enqueue_style( 'nlwp-newsletter', NLWP_URL . 'assets/css/newsletter.css', array(), NLWP_VERSION );

		static $inline_added = false;
		if ( ! $inline_added ) {
			wp_add_inline_style( 'nlwp-newsletter', self::inline_css( $settings, $lang ) );
			$inline_added = true;
		}

		wp_enqueue_script( 'nlwp-newsletter', NLWP_URL . 'assets/js/newsletter.js', array(), NLWP_VERSION, true );
	}

	/**
	 * Build inline CSS variables from settings.
	 *
	 * @param array  $settings Settings.
	 * @param string $lang     Active language.
	 * @return string
	 */
	public static function inline_css( array $settings, string $lang ): string {
		$appearance = $settings['appearance'];
		$colors     = $appearance['colors'];

		if ( 'dark' === $appearance['preset'] ) {
			$colors = array(
				'background' => '#121212',
				'surface'    => '#1c1c1c',
				'text'       => '#f2f2f2',
				'muted'      => '#b3b3b3',
				'accent'     => '#8ab4f8',
				'border'     => '#333333',
				'heading'    => '#ffffff',
			);
		}

		$font_family = (string) $appearance['font_family'];
		$font_size   = (string) $appearance['font_size'];
		$max_width   = (string) $appearance['max_width'];
		$radius      = (string) $appearance['border_radius'];
		$gap         = (string) $appearance['gap'];

		$vars = array(
			'--nlwp-bg'          => $colors['background'],
			'--nlwp-surface'     => $colors['surface'],
			'--nlwp-text'        => $colors['text'],
			'--nlwp-muted'       => $colors['muted'],
			'--nlwp-accent'      => $colors['accent'],
			'--nlwp-border'      => $colors['border'],
			'--nlwp-heading'     => $colors['heading'],
			'--nlwp-radius'      => $radius,
			'--nlwp-gap'         => $gap,
			'--nlwp-max-width'   => $max_width,
			'--nlwp-font-size'   => $font_size,
			'--nlwp-font-family' => 'inherit' === $font_family ? 'inherit' : $font_family,
		);

		$css = '.nlwp{';
		foreach ( $vars as $key => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}
			$css .= $key . ':' . wp_strip_all_tags( $value ) . ';';
		}
		$css .= '}';

		$custom = nlwp_sanitize_css( (string) $appearance['custom_css'] );
		if ( '' !== $custom ) {
			$css .= $custom;
		}

		return $css;
	}
}
