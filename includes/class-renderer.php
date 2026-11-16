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

		$context = Content::web_context( $current_lang, $field_mode );

		$site_event_fields = 'site' === $field_mode( 'event_titles' ) || 'site' === $field_mode( 'event_descriptions' );
		$context['hide_untranslated_lang'] = ( ! empty( $settings['hide_untranslated_events'] ) && $site_event_fields && $current_lang !== Languages::default_code() )
			? $current_lang
			: '';

		$title_html = '';
		if ( $show_title ) {
			if ( 'issue' === $settings['title_source'] ) {
				$title_items = Content::post_field_items(
					$issue_id,
					'title',
					$context['issue_title']['codes'],
					$context['issue_title']['fallback'],
					false,
					$settings
				);
				$title_html  = self::title_stack_html( $title_items, $settings );
			} elseif ( 'fixed' === $settings['title_source'] ) {
				$title_html = self::setting_string_html( 'fixed_title', $current_lang, $ui_mode, $settings, true );
			}
		}

		$intro_html = '';
		if ( $show_intro ) {
			$intro_items = Content::post_field_items(
				$issue_id,
				'intro',
				$context['issue_intro']['codes'],
				$context['issue_intro']['fallback'],
				true,
				$settings
			);
			$intro_html  = self::text_stack_html( $intro_items, $settings );
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
				$issue_date_html .= '<time datetime="' . esc_attr( $date ) . '">' . esc_html( Content::format_date( $date, $current_lang, $settings ) ) . '</time></p>';
			}
		}

		$groups   = self::build_groups( $issue_id, $context, $settings );
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
	 * @param int   $issue_id Issue ID.
	 * @param array $context  Language context.
	 * @param array $settings Settings.
	 * @return array
	 */
	private static function build_groups( int $issue_id, array $context, array $settings ): array {
		$groups = Content::build_groups( $issue_id, $context, $settings );

		foreach ( $groups as $index => $group ) {
			$groups[ $index ]['label'] = self::category_label_html( $group['label_items'], $settings );

			foreach ( $group['events'] as $event_index => $event ) {
				$groups[ $index ]['events'][ $event_index ] = array(
					'id'              => $event['id'],
					'number'          => $event['number'],
					'title_html'      => self::title_stack_html( $event['title_items'], $settings ),
					'description_html'=> self::text_stack_html( $event['description_items'], $settings ),
					'date_html'       => self::event_date_html( $event['date'], $context['lang'], $settings ),
					'attachment_html' => self::attachment_html( $event['attachment'], $settings ),
					'open'            => $event['open'],
					'anchor'          => $event['anchor'],
				);
			}
		}

		return $groups;
	}

	/**
	 * Render stacked titles.
	 *
	 * @param array $items    Text items.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function title_stack_html( array $items, array $settings ): string {
		if ( empty( $items ) ) {
			return '';
		}

		if ( 1 === count( $items ) ) {
			return '<span class="nlwp-lang-text" lang="' . esc_attr( $items[0]['lang'] ) . '" dir="' . esc_attr( $items[0]['dir'] ) . '">' . $items[0]['html'] . '</span>';
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
			$part .= '<span class="nlwp-lang-text" lang="' . esc_attr( $item['lang'] ) . '" dir="' . esc_attr( $item['dir'] ) . '">' . $item['html'] . '</span>';
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
		if ( empty( $items ) ) {
			return '';
		}

		if ( 1 === count( $items ) ) {
			return '<div class="nlwp-lang-text" lang="' . esc_attr( $items[0]['lang'] ) . '" dir="' . esc_attr( $items[0]['dir'] ) . '">' . $items[0]['html'] . '</div>';
		}

		$separator = isset( $settings['stacked']['description_separator'] ) ? $settings['stacked']['description_separator'] : 'none';
		$parts     = array();

		foreach ( $items as $item ) {
			$parts[] = '<div class="nlwp-lang-text" lang="' . esc_attr( $item['lang'] ) . '" dir="' . esc_attr( $item['dir'] ) . '">' . $item['html'] . '</div>';
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
		$codes    = 'stacked' === $mode ? array_column( Languages::all( true ), 'code' ) : array( $current_lang );
		$fallback = 'stacked' === $mode ? Languages::default_code() : $current_lang;
		$items    = Content::setting_string_items( $key, $codes, $fallback, $settings, 'empty_text' === $key, 'stacked' === $mode );

		if ( empty( $items ) ) {
			return '';
		}

		if ( $is_title ) {
			return self::title_stack_html( $items, $settings );
		}

		$separator = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';
		return implode( esc_html( $separator ), array_column( $items, 'html' ) );
	}

	/**
	 * Render category label items.
	 *
	 * @param array $items    Label items.
	 * @param array $settings Settings.
	 * @return string
	 */
	private static function category_label_html( array $items, array $settings ): string {
		if ( empty( $items ) ) {
			return '';
		}

		if ( 1 === count( $items ) ) {
			return $items[0]['html'];
		}

		$separator = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';
		return implode( esc_html( $separator ), array_column( $items, 'html' ) );
	}

	/**
	 * Render an event date range.
	 *
	 * @param array|null $date     Start/end date array.
	 * @param string     $lang     Active language.
	 * @param array      $settings Settings.
	 * @return string
	 */
	private static function event_date_html( ?array $date, string $lang, array $settings ): string {
		if ( empty( $date['start'] ) ) {
			return '';
		}

		$start = (string) $date['start'];
		$end   = isset( $date['end'] ) ? (string) $date['end'] : '';
		$html  = '<span class="nlwp-event-date"><time datetime="' . esc_attr( $start ) . '">' . esc_html( Content::format_date( $start, $lang, $settings ) ) . '</time>';

		if ( ! empty( $settings['show_end_dates'] ) && '' !== $end && $end !== $start ) {
			$separator = isset( $settings['end_date_separator'] ) ? (string) $settings['end_date_separator'] : '–';
			$html     .= '<span class="nlwp-date-separator"> ' . esc_html( $separator ) . ' </span>';
			$html     .= '<time datetime="' . esc_attr( $end ) . '">' . esc_html( Content::format_date( $end, $lang, $settings ) ) . '</time>';
		}

		return $html . '</span>';
	}

	/**
	 * Render an attachment link.
	 *
	 * @param array|null $attachment Attachment data.
	 * @param array      $settings   Settings.
	 * @return string
	 */
	private static function attachment_html( ?array $attachment, array $settings ): string {
		if ( empty( $attachment['url'] ) ) {
			return '';
		}

		$label = '';
		if ( ! empty( $attachment['label_items'] ) ) {
			$separator = isset( $settings['stacked']['separator'] ) ? (string) $settings['stacked']['separator'] : ' / ';
			$label     = implode( esc_html( $separator ), array_column( $attachment['label_items'], 'html' ) );
		}

		if ( '' === $label ) {
			$label = esc_html__( 'Attachment', 'newsletter-wp-plugin' );
		}

		$url    = (string) $attachment['url'];
		$name   = (string) ( $attachment['name'] ?? '' );
		$style  = 'button' === $settings['attachment_style'] ? 'nlwp-attachment-button' : 'nlwp-attachment-link';
		$target = ! empty( $settings['attachment_new_tab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';

		return '<p class="nlwp-attachment"><a class="' . esc_attr( $style ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . $label . ': <span class="nlwp-attachment-name">' . esc_html( $name ) . '</span></a></p>';
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
