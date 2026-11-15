<?php
/**
 * Settings screen.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the tabbed settings screen and category editor.
 */
class Settings_Page {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_nlwp_save_categories', array( __CLASS__, 'save_categories' ) );
	}

	/**
	 * Register the settings submenu.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::ISSUE,
			__( 'Newsletter settings', 'newsletter-wp-plugin' ),
			__( 'Settings', 'newsletter-wp-plugin' ),
			'manage_options',
			Settings::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Available tabs.
	 *
	 * @return array<string,string>
	 */
	public static function tabs(): array {
		return array(
			'general'    => __( 'General', 'newsletter-wp-plugin' ),
			'languages'  => __( 'Languages', 'newsletter-wp-plugin' ),
			'layout'     => __( 'Layout', 'newsletter-wp-plugin' ),
			'appearance' => __( 'Appearance', 'newsletter-wp-plugin' ),
			'categories' => __( 'Categories', 'newsletter-wp-plugin' ),
			'advanced'   => __( 'Advanced', 'newsletter-wp-plugin' ),
		);
	}

	/**
	 * Current tab key.
	 *
	 * @return string
	 */
	public static function current_tab(): string {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = self::tabs();

		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $tab Tab key.
	 * @return string
	 */
	public static function url( string $tab = 'general' ): string {
		return add_query_arg(
			array(
				'post_type' => Post_Types::ISSUE,
				'page'      => Settings::PAGE,
				'tab'       => $tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = self::tabs();
		$tab  = self::current_tab();
		$settings = Settings::get_settings();

		echo '<div class="wrap nlwp-settings">';
		echo '<h1>' . esc_html__( 'Newsletter settings', 'newsletter-wp-plugin' ) . '</h1>';

		if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'newsletter-wp-plugin' ) . '</p></div>';
		}

		echo '<nav class="nav-tab-wrapper nlwp-tabs">';
		foreach ( $tabs as $key => $label ) {
			$class = 'nav-tab' . ( $key === $tab ? ' nav-tab-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::url( $key ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';

		if ( 'categories' === $tab ) {
			self::render_categories( $settings );
		} else {
			self::render_settings_form( $tab, $settings );
		}

		echo '</div>';
	}

	/**
	 * Render a settings form for the active tab.
	 *
	 * @param string $tab      Tab key.
	 * @param array  $settings Current settings.
	 * @return void
	 */
	private static function render_settings_form( string $tab, array $settings ): void {
		echo '<form method="post" action="options.php" class="nlwp-settings-form">';
		settings_fields( Settings::GROUP );
		echo '<input type="hidden" name="' . esc_attr( Settings::OPTION ) . '[nlwp_tab]" value="' . esc_attr( $tab ) . '">';

		echo '<div class="nlwp-panel">';
		switch ( $tab ) {
			case 'languages':
				self::panel_languages( $settings );
				break;
			case 'layout':
				self::panel_layout( $settings );
				break;
			case 'appearance':
				self::panel_appearance( $settings );
				break;
			case 'advanced':
				self::panel_advanced( $settings );
				break;
			default:
				self::panel_general( $settings );
				break;
		}
		echo '</div>';

		submit_button( __( 'Save changes', 'newsletter-wp-plugin' ) );
		echo '</form>';
	}

	/**
	 * General panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function panel_general( array $settings ): void {
		echo '<h2>' . esc_html__( 'Content defaults', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::select_row(
			'title_source',
			__( 'Newsletter title', 'newsletter-wp-plugin' ),
			$settings['title_source'],
			array(
				'issue' => __( 'Use the issue title', 'newsletter-wp-plugin' ),
				'fixed' => __( 'Use a fixed title', 'newsletter-wp-plugin' ),
				'none'  => __( 'Do not show a title', 'newsletter-wp-plugin' ),
			),
			__( 'Applies to the title rendered above the newsletter.', 'newsletter-wp-plugin' )
		);

		self::select_row(
			'title_tag',
			__( 'Title HTML tag', 'newsletter-wp-plugin' ),
			$settings['title_tag'],
			self::heading_tags(),
			''
		);

		self::checkbox_row( 'show_intro', __( 'Show the issue introduction', 'newsletter-wp-plugin' ), ! empty( $settings['show_intro'] ) );
		self::checkbox_row( 'show_issue_date', __( 'Show the issue date', 'newsletter-wp-plugin' ), ! empty( $settings['show_issue_date'] ) );

		self::text_row(
			'default_period_days',
			__( 'Default period length', 'newsletter-wp-plugin' ),
			(string) $settings['default_period_days'],
			'number',
			__( 'Days used to prefill the period end date on new issues.', 'newsletter-wp-plugin' )
		);

		self::text_row(
			'date_format',
			__( 'Date format', 'newsletter-wp-plugin' ),
			(string) $settings['date_format'],
			'text',
			__( 'PHP date format, for example j.n.Y.', 'newsletter-wp-plugin' )
		);

		self::text_row(
			'end_date_separator',
			__( 'Date range separator', 'newsletter-wp-plugin' ),
			(string) $settings['end_date_separator'],
			'text',
			''
		);

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Interface strings', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Leave a field empty to use the default language value or the built-in text.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<table class="form-table nlwp-string-table" role="presentation"><thead><tr><th>' . esc_html__( 'String', 'newsletter-wp-plugin' ) . '</th>';
		foreach ( $settings['languages'] as $language ) {
			echo '<th><span title="' . esc_attr( $language['label'] ) . '">' . esc_html( $language['short'] ) . '</span></th>';
		}
		echo '</tr></thead><tbody>';

		$labels = array(
			'attachment_label'    => __( 'Attachment link text', 'newsletter-wp-plugin' ),
			'empty_text'          => __( 'Empty newsletter text', 'newsletter-wp-plugin' ),
			'fixed_title'         => __( 'Fixed newsletter title', 'newsletter-wp-plugin' ),
			'issue_date_label'    => __( 'Issue date label', 'newsletter-wp-plugin' ),
			'uncategorized_label' => __( 'Uncategorized events label', 'newsletter-wp-plugin' ),
		);

		foreach ( $labels as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '<div class="nlwp-string-key"><code>' . esc_html( $key ) . '</code></div></th>';
			foreach ( $settings['languages'] as $language ) {
				$code  = $language['code'];
				$value = isset( $settings['strings'][ $key ][ $code ] ) ? (string) $settings['strings'][ $key ][ $code ] : '';
				echo '<td>';
				if ( 'empty_text' === $key ) {
					echo '<textarea rows="2" class="large-text" name="' . esc_attr( Settings::OPTION . '[strings][' . $key . '][' . $code . ']' ) . '">' . esc_textarea( $value ) . '</textarea>';
				} else {
					echo '<input type="text" class="regular-text" name="' . esc_attr( Settings::OPTION . '[strings][' . $key . '][' . $code . ']' ) . '" value="' . esc_attr( $value ) . '">';
				}
				echo '</td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Languages panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function panel_languages( array $settings ): void {
		echo '<h2>' . esc_html__( 'Languages', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The default language uses the regular post title and content. Other languages are stored as translations. Clear the code, name and locale fields in a row to remove that language.', 'newsletter-wp-plugin' ) . '</p>';

		echo '<table class="widefat striped nlwp-language-table" id="nlwp-languages"><thead><tr>';
		echo '<th>' . esc_html__( 'Code', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Short label', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Locale', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Direction', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Date format', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Enabled', 'newsletter-wp-plugin' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $settings['languages'] as $index => $language ) {
			$base = Settings::OPTION . '[languages][' . (int) $index . ']';
			echo '<tr>';
			echo '<td><input type="text" class="small-text" name="' . esc_attr( $base . '[code]' ) . '" value="' . esc_attr( $language['code'] ) . '" required></td>';
			echo '<td><input type="text" class="regular-text" name="' . esc_attr( $base . '[label]' ) . '" value="' . esc_attr( $language['label'] ) . '"></td>';
			echo '<td><input type="text" class="small-text" name="' . esc_attr( $base . '[short]' ) . '" value="' . esc_attr( $language['short'] ) . '"></td>';
			echo '<td><input type="text" class="regular-text" name="' . esc_attr( $base . '[locale]' ) . '" value="' . esc_attr( $language['locale'] ) . '" placeholder="en_US"></td>';
			echo '<td><select name="' . esc_attr( $base . '[dir]' ) . '"><option value="ltr"' . selected( $language['dir'], 'ltr', false ) . '>ltr</option><option value="rtl"' . selected( $language['dir'], 'rtl', false ) . '>rtl</option></select></td>';
			echo '<td><input type="text" class="regular-text" name="' . esc_attr( $base . '[date_format]' ) . '" value="' . esc_attr( $language['date_format'] ) . '" placeholder="' . esc_attr( $settings['date_format'] ) . '"></td>';
			echo '<td><input type="checkbox" name="' . esc_attr( $base . '[enabled]' ) . '" value="1"' . checked( ! empty( $language['enabled'] ), true, false ) . '></td>';
			echo '</tr>';
		}

		$next = count( $settings['languages'] );
		echo '<tr class="nlwp-language-new">';
		echo '<td><input type="text" class="small-text" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][code]' ) . '" placeholder="fi"></td>';
		echo '<td><input type="text" class="regular-text" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][label]' ) . '" placeholder="' . esc_attr__( 'Language name', 'newsletter-wp-plugin' ) . '"></td>';
		echo '<td><input type="text" class="small-text" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][short]' ) . '" placeholder="FI"></td>';
		echo '<td><input type="text" class="regular-text" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][locale]' ) . '" placeholder="fi_FI"></td>';
		echo '<td><select name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][dir]' ) . '"><option value="ltr">ltr</option><option value="rtl">rtl</option></select></td>';
		echo '<td><input type="text" class="regular-text" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][date_format]' ) . '"></td>';
		echo '<td><input type="checkbox" name="' . esc_attr( Settings::OPTION . '[languages][' . $next . '][enabled]' ) . '" value="1"></td>';
		echo '</tr>';
		echo '</tbody></table>';

		echo '<table class="form-table" role="presentation" style="margin-top:1.5rem"><tbody>';

		$default_options = array();
		foreach ( $settings['languages'] as $language ) {
			$default_options[ $language['code'] ] = $language['label'];
		}

		self::select_row( 'default_language', __( 'Default language', 'newsletter-wp-plugin' ), $settings['default_language'], $default_options, __( 'The default language is always enabled.', 'newsletter-wp-plugin' ) );

		self::select_row(
			'display_mode',
			__( 'Display mode', 'newsletter-wp-plugin' ),
			$settings['display_mode'],
			array(
				'site'    => __( 'Site language', 'newsletter-wp-plugin' ),
				'stacked' => __( 'All languages, back to back', 'newsletter-wp-plugin' ),
			),
			__( 'Site language shows only the active language. Back to back shows every configured language together.', 'newsletter-wp-plugin' )
		);

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Per-field display mode', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Inherit uses the global display mode above.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		$fields = array(
			'category_labels'    => __( 'Category labels', 'newsletter-wp-plugin' ),
			'event_titles'       => __( 'Event titles', 'newsletter-wp-plugin' ),
			'event_descriptions' => __( 'Event descriptions', 'newsletter-wp-plugin' ),
			'issue_title'        => __( 'Issue title', 'newsletter-wp-plugin' ),
			'issue_intro'        => __( 'Issue introduction', 'newsletter-wp-plugin' ),
			'ui_strings'         => __( 'Interface strings', 'newsletter-wp-plugin' ),
		);

		$mode_options = array(
			'inherit' => __( 'Inherit', 'newsletter-wp-plugin' ),
			'site'    => __( 'Site language', 'newsletter-wp-plugin' ),
			'stacked' => __( 'Back to back', 'newsletter-wp-plugin' ),
		);

		foreach ( $fields as $key => $label ) {
			$value = isset( $settings['field_modes'][ $key ] ) ? $settings['field_modes'][ $key ] : 'inherit';
			self::select_row( 'field_modes[' . $key . ']', $label, $value, $mode_options, '' );
		}

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Back-to-back formatting', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::checkbox_row( 'stacked[show_labels]', __( 'Show language labels', 'newsletter-wp-plugin' ), ! empty( $settings['stacked']['show_labels'] ) );
		self::select_row( 'stacked[label_format]', __( 'Language label format', 'newsletter-wp-plugin' ), $settings['stacked']['label_format'], array( 'short' => __( 'Short code (EN)', 'newsletter-wp-plugin' ), 'name' => __( 'Language name', 'newsletter-wp-plugin' ), 'code' => __( 'Code (en)', 'newsletter-wp-plugin' ) ), '' );
		self::select_row( 'stacked[title_layout]', __( 'Title layout', 'newsletter-wp-plugin' ), $settings['stacked']['title_layout'], array( 'inline' => __( 'Inline', 'newsletter-wp-plugin' ), 'block' => __( 'Own line per language', 'newsletter-wp-plugin' ) ), '' );
		self::text_row( 'stacked[separator]', __( 'Title separator', 'newsletter-wp-plugin' ), (string) $settings['stacked']['separator'], 'text', '' );

		$desc = isset( $settings['stacked']['description_separator'] ) ? $settings['stacked']['description_separator'] : 'none';
		self::select_row( 'stacked[description_separator]', __( 'Description separator', 'newsletter-wp-plugin' ), $desc, array( 'none' => __( 'No separator', 'newsletter-wp-plugin' ), 'line' => __( 'Horizontal line', 'newsletter-wp-plugin' ) ), '' );

		self::checkbox_row( 'hide_untranslated_events', __( 'Hide events without a translation for the active language', 'newsletter-wp-plugin' ), ! empty( $settings['hide_untranslated_events'] ) );

		echo '</tbody></table>';
	}

	/**
	 * Layout panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function panel_layout( array $settings ): void {
		echo '<h2>' . esc_html__( 'Structure', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::checkbox_row( 'show_numbers', __( 'Number events', 'newsletter-wp-plugin' ), ! empty( $settings['show_numbers'] ) );
		self::select_row( 'numbering', __( 'Numbering', 'newsletter-wp-plugin' ), $settings['numbering'], array( 'continuous' => __( 'Continuous across categories', 'newsletter-wp-plugin' ), 'per_category' => __( 'Restart for each category', 'newsletter-wp-plugin' ) ), '' );
		self::checkbox_row( 'show_categories', __( 'Show category headings', 'newsletter-wp-plugin' ), ! empty( $settings['show_categories'] ) );
		self::checkbox_row( 'show_empty_categories', __( 'Show empty categories', 'newsletter-wp-plugin' ), ! empty( $settings['show_empty_categories'] ) );
		self::select_row( 'category_heading_tag', __( 'Category heading tag', 'newsletter-wp-plugin' ), $settings['category_heading_tag'], self::heading_tags(), '' );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Events', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::checkbox_row( 'accordion', __( 'Collapse events into an accordion', 'newsletter-wp-plugin' ), ! empty( $settings['accordion'] ) );
		self::checkbox_row( 'open_first', __( 'Open the first event', 'newsletter-wp-plugin' ), ! empty( $settings['open_first'] ) );
		self::checkbox_row( 'open_all', __( 'Open all events', 'newsletter-wp-plugin' ), ! empty( $settings['open_all'] ) );
		self::checkbox_row( 'exclusive_accordion', __( 'Allow only one open event at a time', 'newsletter-wp-plugin' ), ! empty( $settings['exclusive_accordion'] ) );

		self::checkbox_row( 'show_dates', __( 'Show event dates', 'newsletter-wp-plugin' ), ! empty( $settings['show_dates'] ) );
		self::checkbox_row( 'show_end_dates', __( 'Show event end dates', 'newsletter-wp-plugin' ), ! empty( $settings['show_end_dates'] ) );

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Attachments and links', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::checkbox_row( 'show_attachments', __( 'Show event attachments', 'newsletter-wp-plugin' ), ! empty( $settings['show_attachments'] ) );
		self::select_row( 'attachment_style', __( 'Attachment style', 'newsletter-wp-plugin' ), $settings['attachment_style'], array( 'link' => __( 'Text link', 'newsletter-wp-plugin' ), 'button' => __( 'Button', 'newsletter-wp-plugin' ) ), '' );
		self::checkbox_row( 'attachment_new_tab', __( 'Open attachments in a new tab', 'newsletter-wp-plugin' ), ! empty( $settings['attachment_new_tab'] ) );
		self::checkbox_row( 'autolink', __( 'Automatically link URLs in descriptions', 'newsletter-wp-plugin' ), ! empty( $settings['autolink'] ) );

		echo '</tbody></table>';
	}

	/**
	 * Appearance panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function panel_appearance( array $settings ): void {
		$appearance = $settings['appearance'];

		echo '<h2>' . esc_html__( 'Theme', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::select_row(
			'appearance[preset]',
			__( 'Color preset', 'newsletter-wp-plugin' ),
			$appearance['preset'],
			array(
				'light'  => __( 'Light', 'newsletter-wp-plugin' ),
				'dark'   => __( 'Dark', 'newsletter-wp-plugin' ),
				'custom' => __( 'Custom colors', 'newsletter-wp-plugin' ),
			),
			''
		);

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Colors', 'newsletter-wp-plugin' ) . '</h3>';
		echo '<table class="form-table nlwp-color-table" role="presentation"><tbody>';

		$color_labels = array(
			'background' => __( 'Newsletter background', 'newsletter-wp-plugin' ),
			'surface'    => __( 'Cards and summaries', 'newsletter-wp-plugin' ),
			'text'       => __( 'Body text', 'newsletter-wp-plugin' ),
			'muted'      => __( 'Muted text', 'newsletter-wp-plugin' ),
			'accent'     => __( 'Accent', 'newsletter-wp-plugin' ),
			'border'     => __( 'Borders', 'newsletter-wp-plugin' ),
			'heading'    => __( 'Headings', 'newsletter-wp-plugin' ),
		);

		foreach ( $color_labels as $key => $label ) {
			$value = isset( $appearance['colors'][ $key ] ) ? $appearance['colors'][ $key ] : '';
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			echo '<input type="color" name="' . esc_attr( Settings::OPTION . '[appearance][colors][' . $key . ']' ) . '" value="' . esc_attr( $value ) . '"> ';
			echo '<code>' . esc_html( $value ) . '</code>';
			echo '</td></tr>';
		}

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Typography and spacing', 'newsletter-wp-plugin' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';

		self::text_row( 'appearance[font_family]', __( 'Font family', 'newsletter-wp-plugin' ), (string) $appearance['font_family'], 'text', __( 'Any CSS font-family value, or inherit.', 'newsletter-wp-plugin' ) );
		self::text_row( 'appearance[font_size]', __( 'Base font size', 'newsletter-wp-plugin' ), (string) $appearance['font_size'], 'text', __( 'For example 16px.', 'newsletter-wp-plugin' ) );
		self::text_row( 'appearance[max_width]', __( 'Maximum width', 'newsletter-wp-plugin' ), (string) $appearance['max_width'], 'text', '' );
		self::text_row( 'appearance[border_radius]', __( 'Border radius', 'newsletter-wp-plugin' ), (string) $appearance['border_radius'], 'text', '' );
		self::text_row( 'appearance[gap]', __( 'Spacing between events', 'newsletter-wp-plugin' ), (string) $appearance['gap'], 'text', '' );

		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Logo', 'newsletter-wp-plugin' ) . '</h3>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Logo image', 'newsletter-wp-plugin' ) . '</th><td>';
		nlwp_admin_media_field( Settings::OPTION . '[appearance][logo_id]', (int) $appearance['logo_id'], '' );
		echo '</td></tr>';
		self::text_row( 'appearance[logo_size]', __( 'Logo width', 'newsletter-wp-plugin' ), (string) $appearance['logo_size'], 'text', '' );
		self::select_row( 'appearance[logo_align]', __( 'Logo alignment', 'newsletter-wp-plugin' ), $appearance['logo_align'], array( 'left' => __( 'Left', 'newsletter-wp-plugin' ), 'center' => __( 'Center', 'newsletter-wp-plugin' ), 'right' => __( 'Right', 'newsletter-wp-plugin' ) ), '' );
		echo '</tbody></table>';

		echo '<h3>' . esc_html__( 'Custom CSS', 'newsletter-wp-plugin' ) . '</h3>';
		echo '<p><textarea rows="8" class="large-text code" name="' . esc_attr( Settings::OPTION . '[appearance][custom_css]' ) . '">' . esc_textarea( (string) $appearance['custom_css'] ) . '</textarea></p>';
	}

	/**
	 * Advanced panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function panel_advanced( array $settings ): void {
		echo '<h2>' . esc_html__( 'Data', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		self::checkbox_row( 'delete_data_on_uninstall', __( 'Delete all newsletter data when the plugin is uninstalled', 'newsletter-wp-plugin' ), ! empty( $settings['delete_data_on_uninstall'] ) );
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Issues, events, categories and settings are removed permanently when this option is enabled and the plugin is deleted.', 'newsletter-wp-plugin' ) . '</p>';

		echo '<h2>' . esc_html__( 'Shortcode and block', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p>' . esc_html__( 'Place a newsletter with the shortcode below or with the Newsletter block.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<p><code>[nlwp_newsletter]</code></p>';
		echo '<p class="description">' . esc_html__( 'Optional attributes: issue_id, language, display_mode, show_title, show_intro, class.', 'newsletter-wp-plugin' ) . '</p>';
	}

	/**
	 * Category editor panel.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private static function render_categories( array $settings ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			echo '<p>' . esc_html( $terms->get_error_message() ) . '</p>';
			return;
		}

		$terms = Issue_Selection::sort_terms( $terms );

		echo '<p>' . esc_html__( 'Control the order of categories, hide categories from the newsletter, and translate category labels.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="nlwp_save_categories">';
		wp_nonce_field( 'nlwp_save_categories' );

		echo '<table class="widefat striped nlwp-category-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Category', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Order', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Visible', 'newsletter-wp-plugin' ) . '</th>';
		foreach ( $settings['languages'] as $language ) {
			if ( $language['code'] === $settings['default_language'] ) {
				continue;
			}
			echo '<th><span title="' . esc_attr( $language['label'] ) . '">' . esc_html( $language['short'] ) . '</span></th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $terms as $term ) {
			$order   = (int) get_term_meta( $term->term_id, '_nlwp_order', true );
			$enabled = get_term_meta( $term->term_id, '_nlwp_enabled', true );
			$enabled = '' === $enabled ? true : (bool) $enabled;
			$labels  = get_term_meta( $term->term_id, '_nlwp_label_translations', true );
			$labels  = is_array( $labels ) ? $labels : array();

			echo '<tr>';
			echo '<td><strong>' . esc_html( $term->name ) . '</strong></td>';
			echo '<td><code>' . esc_html( $term->slug ) . '</code></td>';
			echo '<td><input type="number" class="small-text" min="0" step="1" name="nlwp_order[' . (int) $term->term_id . ']" value="' . esc_attr( (string) $order ) . '"></td>';
			echo '<td><input type="checkbox" name="nlwp_enabled[' . (int) $term->term_id . ']" value="1"' . checked( $enabled, true, false ) . '></td>';

			foreach ( $settings['languages'] as $language ) {
				if ( $language['code'] === $settings['default_language'] ) {
					continue;
				}
				$code  = $language['code'];
				$value = isset( $labels[ $code ] ) ? (string) $labels[ $code ] : '';
				echo '<td><input type="text" class="regular-text" name="nlwp_labels[' . (int) $term->term_id . '][' . esc_attr( $code ) . ']" value="' . esc_attr( $value ) . '"></td>';
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Save categories', 'newsletter-wp-plugin' ) );
		echo '</form>';

		echo '<p class="description">' . esc_html__( 'Category names in the default language come from the category term itself.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . Taxonomy::TAXONOMY . '&post_type=' . Post_Types::EVENT ) ) . '">' . esc_html__( 'Add or rename categories', 'newsletter-wp-plugin' ) . '</a></p>';
	}

	/**
	 * Save category settings.
	 *
	 * @return void
	 */
	public static function save_categories(): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			wp_die( esc_html__( 'You are not allowed to edit categories.', 'newsletter-wp-plugin' ) );
		}

		check_admin_referer( 'nlwp_save_categories' );

		$settings = Settings::get_settings();
		$orders   = isset( $_POST['nlwp_order'] ) ? (array) wp_unslash( $_POST['nlwp_order'] ) : array();
		$enabled  = isset( $_POST['nlwp_enabled'] ) ? (array) wp_unslash( $_POST['nlwp_enabled'] ) : array();
		$labels   = isset( $_POST['nlwp_labels'] ) ? (array) wp_unslash( $_POST['nlwp_labels'] ) : array();

		$terms = get_terms(
			array(
				'taxonomy'   => Taxonomy::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$term_id = (int) $term_id;
				update_term_meta( $term_id, '_nlwp_order', isset( $orders[ $term_id ] ) ? absint( $orders[ $term_id ] ) : 0 );
				update_term_meta( $term_id, '_nlwp_enabled', ! empty( $enabled[ $term_id ] ) ? '1' : '0' );

				$term_labels = array();
				$raw_labels  = isset( $labels[ $term_id ] ) && is_array( $labels[ $term_id ] ) ? $labels[ $term_id ] : array();
				foreach ( $settings['languages'] as $language ) {
					$code = $language['code'];
					if ( $code === $settings['default_language'] || ! isset( $raw_labels[ $code ] ) ) {
						continue;
					}
					$value = sanitize_text_field( (string) $raw_labels[ $code ] );
					if ( '' !== $value ) {
						$term_labels[ $code ] = $value;
					}
				}
				update_term_meta( $term_id, '_nlwp_label_translations', wp_slash( $term_labels ) );
			}
		}

		wp_safe_redirect( add_query_arg( 'settings-updated', '1', self::url( 'categories' ) ) );
		exit;
	}

	/**
	 * Build a full option field name, expanding nested keys.
	 *
	 * @param string $name Option-relative field name.
	 * @return string
	 */
	private static function field_name( string $name ): string {
		$parts = preg_split( '/[\[\]]+/', $name, -1, PREG_SPLIT_NO_EMPTY );
		$parts = is_array( $parts ) ? $parts : array();
		return Settings::OPTION . '[' . implode( '][', $parts ) . ']';
	}

	/**
	 * Heading tag choices.
	 *
	 * @return array<string,string>
	 */
	private static function heading_tags(): array {
		$tags = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$tags[ 'h' . $i ] = 'h' . $i;
		}
		return $tags;
	}

	/**
	 * Render a text input row.
	 *
	 * @param string $name  Option-relative field name.
	 * @param string $label Field label.
	 * @param string $value Current value.
	 * @param string $type  Input type.
	 * @param string $help  Help text.
	 * @return void
	 */
	private static function text_row( string $name, string $label, string $value, string $type = 'text', string $help = '' ): void {
		echo '<tr><th scope="row"><label for="nlwp-' . esc_attr( sanitize_html_class( $name ) ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="nlwp-' . esc_attr( sanitize_html_class( $name ) ) . '" name="' . esc_attr( self::field_name( $name ) ) . '" value="' . esc_attr( $value ) . '">';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a select row.
	 *
	 * @param string $name    Option-relative field name.
	 * @param string $label   Field label.
	 * @param string $value   Current value.
	 * @param array  $options Choice map.
	 * @param string $help    Help text.
	 * @return void
	 */
	private static function select_row( string $name, string $label, string $value, array $options, string $help = '' ): void {
		echo '<tr><th scope="row"><label for="nlwp-' . esc_attr( sanitize_html_class( $name ) ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<select id="nlwp-' . esc_attr( sanitize_html_class( $name ) ) . '" name="' . esc_attr( self::field_name( $name ) ) . '">';
		foreach ( $options as $key => $option_label ) {
			echo '<option value="' . esc_attr( (string) $key ) . '"' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( (string) $option_label ) . '</option>';
		}
		echo '</select>';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a checkbox row.
	 *
	 * @param string $name    Option-relative field name.
	 * @param string $label   Field label.
	 * @param bool   $checked Whether checked.
	 * @return void
	 */
	private static function checkbox_row( string $name, string $label, bool $checked ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<input type="checkbox" id="nlwp-' . esc_attr( sanitize_html_class( $name ) ) . '" name="' . esc_attr( self::field_name( $name ) ) . '" value="1"' . checked( $checked, true, false ) . '>';
		echo '</td></tr>';
	}
}
