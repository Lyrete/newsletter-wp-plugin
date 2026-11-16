<?php
/**
 * Newsletter email admin screen.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Provides the issue email list, compose form and send handlers.
 */
class Admin_Email {

	const PAGE  = 'nlwp-email';
	const NONCE = 'nlwp_email_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_nlwp_send_email', array( __CLASS__, 'handle' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
	}

	/**
	 * Register the email submenu.
	 *
	 * @return void
	 */
	public static function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . Post_Types::ISSUE,
			__( 'Newsletter email', 'newsletter-wp-plugin' ),
			__( 'Email', 'newsletter-wp-plugin' ),
			'edit_posts',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Email screen URL.
	 *
	 * @param int $issue_id Issue ID.
	 * @return string
	 */
	public static function url( int $issue_id = 0 ): string {
		$args = array(
			'post_type' => Post_Types::ISSUE,
			'page'      => self::PAGE,
		);

		if ( $issue_id ) {
			$args['issue_id'] = $issue_id;
		}

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$issue_id = isset( $_GET['issue_id'] ) ? absint( $_GET['issue_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap nlwp-email">';
		echo '<h1>' . esc_html__( 'Newsletter email', 'newsletter-wp-plugin' ) . '</h1>';
		self::render_notice();

		if ( $issue_id ) {
			$issue = get_post( $issue_id );
			if ( ! $issue || Post_Types::ISSUE !== $issue->post_type ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid newsletter issue.', 'newsletter-wp-plugin' ) . '</p></div>';
			} elseif ( ! current_user_can( 'edit_post', $issue_id ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'You are not allowed to edit this issue.', 'newsletter-wp-plugin' ) . '</p></div>';
			} else {
				self::render_compose( $issue );
			}
		} else {
			self::render_list();
		}

		echo '</div>';
	}

	/**
	 * Render the issue list.
	 *
	 * @return void
	 */
	private static function render_list(): void {
		echo self::smtp_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$issues = get_posts(
			array(
				'post_type'      => Post_Types::ISSUE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( empty( $issues ) ) {
			echo '<p>' . esc_html__( 'No newsletter issues have been created yet.', 'newsletter-wp-plugin' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped nlwp-email-list"><thead><tr>';
		echo '<th>' . esc_html__( 'Issue', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Issue date', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Recipients', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Last sent', 'newsletter-wp-plugin' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'newsletter-wp-plugin' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $issues as $issue ) {
			$recipients = count( Email_Sender::recipients( $issue->ID ) );
			$last_send  = (string) get_post_meta( $issue->ID, '_nlwp_email_last_send', true );
			$issue_date = (string) get_post_meta( $issue->ID, '_nlwp_issue_date', true );

			echo '<tr>';
			echo '<td><strong>' . esc_html( get_the_title( $issue ) ) . '</strong></td>';
			echo '<td>' . esc_html( get_post_status_object( $issue->post_status )->label ?? $issue->post_status ) . '</td>';
			echo '<td>' . esc_html( $issue_date ) . '</td>';
			echo '<td>' . esc_html( (string) $recipients ) . '</td>';
			echo '<td>' . esc_html( '' !== $last_send ? $last_send : __( 'Not sent', 'newsletter-wp-plugin' ) ) . '</td>';
			echo '<td><a class="button button-small" href="' . esc_url( self::url( $issue->ID ) ) . '">' . esc_html__( 'Compose', 'newsletter-wp-plugin' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render the compose form for an issue.
	 *
	 * @param \WP_Post $issue Issue post.
	 * @return void
	 */
	private static function render_compose( \WP_Post $issue ): void {
		$settings   = Settings::get_settings();
		$formatting = Email_Renderer::formatting( $issue->ID, $settings );
		$format     = Email_Renderer::format( $settings );
		$recipients = (string) get_post_meta( $issue->ID, '_nlwp_email_recipients', true );
		$subject    = (string) get_post_meta( $issue->ID, '_nlwp_email_subject', true );
		$last_send  = (string) get_post_meta( $issue->ID, '_nlwp_email_last_send', true );
		$last_test  = (string) get_post_meta( $issue->ID, '_nlwp_email_last_test', true );
		$plain      = Email_Renderer::message( $issue->ID, 'plain', $settings );
		$html       = Email_Renderer::message( $issue->ID, 'html', $settings );
		$languages  = Languages::all();

		echo '<p><a href="' . esc_url( self::url() ) . '">&larr; ' . esc_html__( 'All issues', 'newsletter-wp-plugin' ) . '</a></p>';
		echo '<h2>' . esc_html( get_the_title( $issue ) ) . '</h2>';

		echo self::smtp_notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<p class="description">';
		echo esc_html__( 'The compose form saves the email settings and sends from the same values, so the sent email matches this preview. Plain text and minimal HTML are always generated.', 'newsletter-wp-plugin' );
		echo '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nlwp-email-form">';
		echo '<input type="hidden" name="action" value="nlwp_send_email">';
		echo '<input type="hidden" name="issue_id" value="' . esc_attr( (string) $issue->ID ) . '">';
		wp_nonce_field( self::NONCE . '_' . $issue->ID );

		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nlwp-email-recipients">' . esc_html__( 'Recipients', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<textarea id="nlwp-email-recipients" class="large-text code" rows="4" name="recipients">' . esc_textarea( $recipients ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One address per line, or separate addresses with commas. Recipients are sent as Bcc.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-email-subject">' . esc_html__( 'Subject', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="text" id="nlwp-email-subject" class="large-text" name="subject" value="' . esc_attr( $subject ) . '" placeholder="' . esc_attr( $plain['subject'] ) . '">';
		echo '<p class="description">' . esc_html__( 'Leave empty to use the issue title in the primary email language.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Email format', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<p><code>' . esc_html( 'html' === $format ? __( 'Minimal HTML', 'newsletter-wp-plugin' ) : __( 'Plain text', 'newsletter-wp-plugin' ) ) . '</code></p>';
		echo '<p class="description">' . esc_html__( 'The sending format is configured on the Email settings tab.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Languages in this email', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<fieldset class="nlwp-email-languages">';
		foreach ( $languages as $language ) {
			$code = (string) $language['code'];
			echo '<label class="nlwp-email-language"><input type="checkbox" name="formatting[languages][]" value="' . esc_attr( $code ) . '"' . checked( in_array( $code, $formatting['languages'], true ), true, false ) . '> ';
			echo '<span>' . esc_html( $language['label'] ) . ' <code>' . esc_html( $code ) . '</code></span></label>';
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Selected languages are combined into one email. If none are selected, the primary language is used.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-email-primary">' . esc_html__( 'Primary language', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<select id="nlwp-email-primary" name="formatting[primary_language]">';
		foreach ( $languages as $language ) {
			echo '<option value="' . esc_attr( (string) $language['code'] ) . '"' . selected( $formatting['primary_language'], (string) $language['code'], false ) . '>' . esc_html( $language['label'] ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'The subject, header, footer and interface labels use the primary language.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Title formatting', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Applies to the issue title, category labels and event titles.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nlwp-email-title-layout">' . esc_html__( 'Title layout', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<select id="nlwp-email-title-layout" name="formatting[title_layout]">';
		echo '<option value="inline"' . selected( $formatting['title_layout'], 'inline', false ) . '>' . esc_html__( 'Inline', 'newsletter-wp-plugin' ) . '</option>';
		echo '<option value="block"' . selected( $formatting['title_layout'], 'block', false ) . '>' . esc_html__( 'Own line per language', 'newsletter-wp-plugin' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-email-title-separator">' . esc_html__( 'Title separator', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="text" id="nlwp-email-title-separator" class="regular-text" name="formatting[title_separator]" value="' . esc_attr( $formatting['title_separator'] ) . '">';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Language labels', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<label><input type="checkbox" name="formatting[title_labels]" value="1"' . checked( ! empty( $formatting['title_labels'] ), true, false ) . '> ' . esc_html__( 'Prefix titles with a language label', 'newsletter-wp-plugin' ) . '</label>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-email-label-format">' . esc_html__( 'Language label format', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<select id="nlwp-email-label-format" name="formatting[label_format]">';
		echo '<option value="short"' . selected( $formatting['label_format'], 'short', false ) . '>' . esc_html__( 'Short code (EN)', 'newsletter-wp-plugin' ) . '</option>';
		echo '<option value="name"' . selected( $formatting['label_format'], 'name', false ) . '>' . esc_html__( 'Language name', 'newsletter-wp-plugin' ) . '</option>';
		echo '<option value="code"' . selected( $formatting['label_format'], 'code', false ) . '>' . esc_html__( 'Code (en)', 'newsletter-wp-plugin' ) . '</option>';
		echo '</select></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Description formatting', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Applies to the issue introduction and event descriptions.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nlwp-email-description-separator">' . esc_html__( 'Description separator', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<select id="nlwp-email-description-separator" name="formatting[description_separator]">';
		echo '<option value="none"' . selected( $formatting['description_separator'], 'none', false ) . '>' . esc_html__( 'Blank line', 'newsletter-wp-plugin' ) . '</option>';
		echo '<option value="line"' . selected( $formatting['description_separator'], 'line', false ) . '>' . esc_html__( 'Horizontal line', 'newsletter-wp-plugin' ) . '</option>';
		echo '<option value="custom"' . selected( $formatting['description_separator'], 'custom', false ) . '>' . esc_html__( 'Custom separator', 'newsletter-wp-plugin' ) . '</option>';
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-email-description-custom">' . esc_html__( 'Custom separator', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="text" id="nlwp-email-description-custom" class="regular-text" name="formatting[description_custom_separator]" value="' . esc_attr( $formatting['description_custom_separator'] ) . '">';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Language labels', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<label><input type="checkbox" name="formatting[description_labels]" value="1"' . checked( ! empty( $formatting['description_labels'] ), true, false ) . '> ' . esc_html__( 'Prefix descriptions with a language label', 'newsletter-wp-plugin' ) . '</label>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary nlwp-send-email" name="nlwp_email_action" value="send">' . esc_html__( 'Send email', 'newsletter-wp-plugin' ) . '</button> ';
		echo '<button type="submit" class="button" name="nlwp_email_action" value="preview">' . esc_html__( 'Save and preview', 'newsletter-wp-plugin' ) . '</button>';
		echo '</p>';

		echo '<h2>' . esc_html__( 'Send a test', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p>';
		echo '<input type="email" class="regular-text" name="test_email" value="' . esc_attr( (string) get_option( 'admin_email', '' ) ) . '" aria-label="' . esc_attr__( 'Test email address', 'newsletter-wp-plugin' ) . '"> ';
		echo '<button type="submit" class="button" name="nlwp_email_action" value="test">' . esc_html__( 'Send test', 'newsletter-wp-plugin' ) . '</button>';
		echo '</p>';

		echo '</form>';

		echo '<h2>' . esc_html__( 'Preview and export', 'newsletter-wp-plugin' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The preview uses the saved email settings. Choose Save and preview after changing the form.', 'newsletter-wp-plugin' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Subject:', 'newsletter-wp-plugin' ) . '</strong> ' . esc_html( $plain['subject'] ) . '</p>';

		$plain_target = 'nlwp-preview-plain';
		echo '<details class="nlwp-email-preview" open>';
		echo '<summary>' . esc_html__( 'Plain text', 'newsletter-wp-plugin' ) . '</summary>';
		echo '<textarea id="' . esc_attr( $plain_target ) . '" class="large-text code nlwp-preview-text" rows="18" readonly>' . esc_textarea( $plain['body'] ) . '</textarea>';
		echo '<p>';
		echo '<button type="button" class="button nlwp-copy" data-target="#' . esc_attr( $plain_target ) . '">' . esc_html__( 'Copy plain text', 'newsletter-wp-plugin' ) . '</button> ';
		echo '<button type="button" class="button nlwp-download" data-target="#' . esc_attr( $plain_target ) . '" data-filename="newsletter-' . esc_attr( (string) $issue->ID ) . '.txt" data-mime="text/plain">' . esc_html__( 'Download .txt', 'newsletter-wp-plugin' ) . '</button>';
		echo '</p>';
		echo '</details>';

		$html_target = 'nlwp-preview-html';
		echo '<details class="nlwp-email-preview">';
		echo '<summary>' . esc_html__( 'Minimal HTML', 'newsletter-wp-plugin' ) . '</summary>';
		echo '<textarea id="' . esc_attr( $html_target ) . '" class="large-text code nlwp-preview-text" rows="18" readonly>' . esc_textarea( $html['body'] ) . '</textarea>';
		echo '<p>';
		echo '<button type="button" class="button nlwp-copy" data-target="#' . esc_attr( $html_target ) . '">' . esc_html__( 'Copy HTML', 'newsletter-wp-plugin' ) . '</button> ';
		echo '<button type="button" class="button nlwp-download" data-target="#' . esc_attr( $html_target ) . '" data-filename="newsletter-' . esc_attr( (string) $issue->ID ) . '.html" data-mime="text/html">' . esc_html__( 'Download .html', 'newsletter-wp-plugin' ) . '</button>';
		echo '</p>';
		echo '</details>';

		echo '<p class="description">';
		echo esc_html(
			sprintf(
				/* translators: 1: last send time, 2: last test time. */
				__( 'Last sent: %1$s. Last test: %2$s.', 'newsletter-wp-plugin' ),
				'' !== $last_send ? $last_send : __( 'never', 'newsletter-wp-plugin' ),
				'' !== $last_test ? $last_test : __( 'never', 'newsletter-wp-plugin' )
			)
		);
		echo '</p>';
	}

	/**
	 * Handle the compose form.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$issue_id = isset( $_POST['issue_id'] ) ? absint( $_POST['issue_id'] ) : 0;

		check_admin_referer( self::NONCE . '_' . $issue_id );

		if ( ! $issue_id || Post_Types::ISSUE !== get_post_type( $issue_id ) ) {
			wp_die( esc_html__( 'Invalid newsletter issue.', 'newsletter-wp-plugin' ) );
		}
		if ( ! current_user_can( 'edit_post', $issue_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this issue.', 'newsletter-wp-plugin' ) );
		}

		$settings   = Settings::get_settings();
		$formatting = isset( $_POST['formatting'] ) && is_array( $_POST['formatting'] )
			? Email_Renderer::sanitize_formatting( wp_unslash( $_POST['formatting'] ), $settings )
			: Email_Renderer::sanitize_formatting( array(), $settings );

		update_post_meta( $issue_id, Email_Renderer::META_FORMATTING, wp_slash( $formatting ) );
		update_post_meta( $issue_id, '_nlwp_email_recipients', sanitize_textarea_field( wp_unslash( (string) ( $_POST['recipients'] ?? '' ) ) ) );
		update_post_meta( $issue_id, '_nlwp_email_subject', sanitize_text_field( wp_unslash( (string) ( $_POST['subject'] ?? '' ) ) ) );

		$action = sanitize_key( wp_unslash( (string) ( $_POST['nlwp_email_action'] ?? 'preview' ) ) );

		if ( 'send' === $action ) {
			$result = Email_Sender::send( $issue_id );
		} elseif ( 'test' === $action ) {
			$result = Email_Sender::send_test( $issue_id, wp_unslash( (string) ( $_POST['test_email'] ?? '' ) ) );
		} else {
			$result = array(
				'success' => true,
				'message' => __( 'Email settings saved.', 'newsletter-wp-plugin' ),
			);
		}

		self::set_notice( ! empty( $result['success'] ) ? 'success' : 'error', (string) ( $result['message'] ?? '' ) );

		wp_safe_redirect( self::url( $issue_id ) );
		exit;
	}

	/**
	 * Register the email status meta box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'nlwp-email-status',
			__( 'Email', 'newsletter-wp-plugin' ),
			array( __CLASS__, 'render_meta_box' ),
			Post_Types::ISSUE,
			'side',
			'default'
		);
	}

	/**
	 * Render the email status meta box.
	 *
	 * @param \WP_Post $post Issue post.
	 * @return void
	 */
	public static function render_meta_box( \WP_Post $post ): void {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		$recipients = count( Email_Sender::recipients( $post->ID ) );
		$last_send  = (string) get_post_meta( $post->ID, '_nlwp_email_last_send', true );
		$last_test  = (string) get_post_meta( $post->ID, '_nlwp_email_last_test', true );

		echo '<p><strong>' . esc_html__( 'Subject:', 'newsletter-wp-plugin' ) . '</strong> ' . esc_html( Email_Renderer::subject( $post->ID ) ) . '</p>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of recipients. */
				_n( '%d recipient configured.', '%d recipients configured.', $recipients, 'newsletter-wp-plugin' ),
				$recipients
			)
		) . '</p>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: 1: last send time, 2: last test time. */
				__( 'Last sent: %1$s. Last test: %2$s.', 'newsletter-wp-plugin' ),
				'' !== $last_send ? $last_send : __( 'never', 'newsletter-wp-plugin' ),
				'' !== $last_test ? $last_test : __( 'never', 'newsletter-wp-plugin' )
			)
		) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( self::url( $post->ID ) ) . '">' . esc_html__( 'Compose email', 'newsletter-wp-plugin' ) . '</a></p>';
	}

	/**
	 * Add an email action to issue rows.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( Post_Types::ISSUE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$actions['nlwp_email'] = '<a href="' . esc_url( self::url( $post->ID ) ) . '">' . esc_html__( 'Email', 'newsletter-wp-plugin' ) . '</a>';

		return $actions;
	}

	/**
	 * External mail transport notice.
	 *
	 * @return string
	 */
	public static function smtp_notice(): string {
		return '<div class="notice notice-info inline nlwp-smtp-notice"><p>' . esc_html__( 'Email is sent with the WordPress wp_mail() function. Install and configure an SMTP or email delivery plugin for reliable sending. Newsletter Publisher does not configure mail transport.', 'newsletter-wp-plugin' ) . '</p></div>';
	}

	/**
	 * Store a notice for the current user.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message.
	 * @return void
	 */
	private static function set_notice( string $type, string $message ): void {
		set_transient(
			'nlwp_email_notice_' . get_current_user_id(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => $message,
			),
			60
		);
	}

	/**
	 * Render and clear the current user's notice.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		$key    = 'nlwp_email_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $key );

		$class = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}
}
