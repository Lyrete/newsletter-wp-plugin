<?php
/**
 * Admin assets.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin styles and scripts.
 */
class Admin_Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Enqueue on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( string $hook ): void {
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$post_type = $screen && isset( $screen->post_type ) ? $screen->post_type : '';
		$page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$is_plugin_screen = in_array( $post_type, array( Post_Types::ISSUE, Post_Types::EVENT ), true )
			|| Settings::PAGE === $page
			|| false !== strpos( $hook, 'nlwp' );

		if ( ! $is_plugin_screen ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'nlwp-admin',
			NLWP_URL . 'assets/css/admin.css',
			array(),
			NLWP_VERSION
		);

		wp_enqueue_script(
			'nlwp-admin',
			NLWP_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			NLWP_VERSION,
			true
		);

		wp_localize_script(
			'nlwp-admin',
			'nlwpAdmin',
			array(
				'selectFile' => __( 'Select file', 'newsletter-wp-plugin' ),
				'useFile'    => __( 'Use this file', 'newsletter-wp-plugin' ),
				'removeFile' => __( 'Remove', 'newsletter-wp-plugin' ),
				'noFile'     => __( 'No file selected', 'newsletter-wp-plugin' ),
				'confirm'    => __( 'Are you sure?', 'newsletter-wp-plugin' ),
				'confirmSend' => __( 'Send this newsletter issue to all configured recipients?', 'newsletter-wp-plugin' ),
				'copied'     => __( 'Copied', 'newsletter-wp-plugin' ),
				'copyFailed' => __( 'Copy failed. Select the text and copy it manually.', 'newsletter-wp-plugin' ),
				'downloadFailed' => __( 'Download failed.', 'newsletter-wp-plugin' ),
			)
		);
	}
}
