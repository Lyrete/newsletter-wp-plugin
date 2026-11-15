<?php
/**
 * Translation editor meta boxes.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Adds translation fields to issue and event editors.
 */
class Admin_Translations {

	const NONCE = 'nlwp_translations_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Types::ISSUE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'save_post_' . Post_Types::EVENT, array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		foreach ( array( Post_Types::ISSUE, Post_Types::EVENT ) as $post_type ) {
			add_meta_box(
				'nlwp-translations',
				__( 'Translations', 'newsletter-wp-plugin' ),
				array( __CLASS__, 'render' ),
				$post_type,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render translation fields.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		$settings  = Settings::get_settings();
		$languages = array();

		foreach ( $settings['languages'] as $language ) {
			if ( $language['code'] !== $settings['default_language'] && ! empty( $language['enabled'] ) ) {
				$languages[] = $language;
			}
		}

		if ( empty( $languages ) ) {
			echo '<p>' . esc_html__( 'Add another language under Newsletter → Settings → Languages to translate this content.', 'newsletter-wp-plugin' ) . '</p>';
			return;
		}

		wp_nonce_field( self::NONCE, self::NONCE );
		$translations = Translations::get_post_translations( $post->ID );
		$is_issue     = Post_Types::ISSUE === $post->post_type;

		echo '<p class="description">' . esc_html__( 'The default language uses the main title and content above. Enter translations for the other languages here.', 'newsletter-wp-plugin' ) . '</p>';

		foreach ( $languages as $language ) {
			$code   = $language['code'];
			$values = isset( $translations[ $code ] ) && is_array( $translations[ $code ] ) ? $translations[ $code ] : array();
			$title  = isset( $values['title'] ) ? (string) $values['title'] : '';
			$body   = isset( $values[ $is_issue ? 'intro' : 'description' ] ) ? (string) $values[ $is_issue ? 'intro' : 'description' ] : '';

			echo '<fieldset class="nlwp-translation" lang="' . esc_attr( $code ) . '" dir="' . esc_attr( $language['dir'] ) . '">';
			echo '<legend>' . esc_html( $language['label'] ) . ' <code>' . esc_html( $code ) . '</code></legend>';

			echo '<p><label for="nlwp-translation-' . esc_attr( $code ) . '-title">' . esc_html__( 'Title', 'newsletter-wp-plugin' ) . '</label>';
			echo '<input type="text" class="widefat" id="nlwp-translation-' . esc_attr( $code ) . '-title" name="nlwp_translations[' . esc_attr( $code ) . '][title]" value="' . esc_attr( $title ) . '"></p>';

			$body_label = $is_issue ? __( 'Introduction', 'newsletter-wp-plugin' ) : __( 'Description', 'newsletter-wp-plugin' );
			echo '<p><label for="nlwp-translation-' . esc_attr( $code ) . '-body">' . esc_html( $body_label ) . '</label>';
			echo '<textarea class="widefat" rows="5" id="nlwp-translation-' . esc_attr( $code ) . '-body" name="nlwp_translations[' . esc_attr( $code ) . '][' . esc_attr( $is_issue ? 'intro' : 'description' ) . ']">' . esc_textarea( $body ) . '</textarea></p>';

			echo '</fieldset>';
		}

		echo '<p class="description">' . esc_html__( 'Empty translations fall back to the default language unless fallback is hidden in the display settings.', 'newsletter-wp-plugin' ) . '</p>';
	}

	/**
	 * Save translation fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( Post_Types::ISSUE, Post_Types::EVENT ), true ) ) {
			return;
		}

		$raw = isset( $_POST['nlwp_translations'] ) && is_array( $_POST['nlwp_translations'] )
			? wp_unslash( $_POST['nlwp_translations'] )
			: array();

		$clean = Post_Types::ISSUE === $post->post_type
			? Translations::sanitize_issue_translations( $raw )
			: Translations::sanitize_event_translations( $raw );

		update_post_meta( $post_id, '_nlwp_translations', wp_slash( $clean ) );
	}
}
