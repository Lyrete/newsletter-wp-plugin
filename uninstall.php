<?php
/**
 * Uninstall routine.
 *
 * @package NewsletterPublisher
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$nlwp_settings = get_option( 'nlwp_settings', array() );

if ( ! is_array( $nlwp_settings ) || empty( $nlwp_settings['delete_data_on_uninstall'] ) ) {
	return;
}

$nlwp_posts = get_posts(
	array(
		'post_type'      => array( 'nlwp_issue', 'nlwp_event' ),
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'auto-draft' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	)
);

foreach ( $nlwp_posts as $nlwp_post_id ) {
	wp_delete_post( (int) $nlwp_post_id, true );
}

require_once __DIR__ . '/includes/class-post-types.php';
require_once __DIR__ . '/includes/class-taxonomy.php';

\NLWP\Taxonomy::register();

$nlwp_terms = get_terms(
	array(
		'taxonomy'   => 'nlwp_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $nlwp_terms ) ) {
	foreach ( $nlwp_terms as $nlwp_term_id ) {
		wp_delete_term( (int) $nlwp_term_id, 'nlwp_category' );
	}
}

delete_option( 'nlwp_settings' );

global $wpdb;

$nlwp_transients = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_nlwp_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_nlwp_' ) . '%'
	)
);

foreach ( $nlwp_transients as $nlwp_transient ) {
	delete_option( (string) $nlwp_transient );
}
