<?php
/**
 * Plugin bootstrap.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin controller.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function init(): void {
		load_plugin_textdomain( 'newsletter-wp-plugin', false, dirname( plugin_basename( NLWP_FILE ) ) . '/languages' );

		Post_Types::init();
		Taxonomy::init();
		Meta::init();
		Settings::init();
		Settings_Page::init();
		Admin_Assets::init();
		Admin_Translations::init();
		Admin_Events::init();
		Admin_Issues::init();
		Admin_Email::init();
		Shortcode::init();
		Block::init();

		add_filter( 'plugin_action_links_' . plugin_basename( NLWP_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add a settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( array $links ): array {
		$settings = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . Post_Types::ISSUE . '&page=' . Settings::PAGE ) ) . '">' . esc_html__( 'Settings', 'newsletter-wp-plugin' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Activation routine.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Post_Types::register();
		Taxonomy::register();
		Taxonomy::create_default_terms();
		Settings::install_defaults();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation routine.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
