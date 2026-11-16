<?php
/**
 * Plugin Name: Newsletter Publisher
 * Description: Publish multilingual newsletter issues on your website with configurable layouts, categories, events and appearance.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.5
 * Author: Newsletter Publisher contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: newsletter-wp-plugin
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'NLWP_VERSION', '0.2.0' );
define( 'NLWP_FILE', __FILE__ );
define( 'NLWP_DIR', plugin_dir_path( __FILE__ ) );
define( 'NLWP_URL', plugin_dir_url( __FILE__ ) );

require_once NLWP_DIR . 'includes/helpers.php';
require_once NLWP_DIR . 'includes/class-post-types.php';
require_once NLWP_DIR . 'includes/class-taxonomy.php';
require_once NLWP_DIR . 'includes/class-meta.php';
require_once NLWP_DIR . 'includes/class-languages.php';
require_once NLWP_DIR . 'includes/class-translations.php';
require_once NLWP_DIR . 'includes/class-settings.php';
require_once NLWP_DIR . 'includes/class-settings-page.php';
require_once NLWP_DIR . 'includes/class-issue-selection.php';
require_once NLWP_DIR . 'includes/class-content.php';
require_once NLWP_DIR . 'includes/class-admin-assets.php';
require_once NLWP_DIR . 'includes/class-admin-translations.php';
require_once NLWP_DIR . 'includes/class-admin-events.php';
require_once NLWP_DIR . 'includes/class-admin-issues.php';
require_once NLWP_DIR . 'includes/class-renderer.php';
require_once NLWP_DIR . 'includes/class-email-renderer.php';
require_once NLWP_DIR . 'includes/class-email-sender.php';
require_once NLWP_DIR . 'includes/class-admin-email.php';
require_once NLWP_DIR . 'includes/class-shortcode.php';
require_once NLWP_DIR . 'includes/class-block.php';
require_once NLWP_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'NLWP\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NLWP\\Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'NLWP\\Plugin', 'instance' ) );
