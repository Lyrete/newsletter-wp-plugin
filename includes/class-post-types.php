<?php
/**
 * Custom post types.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the issue and event post types.
 */
class Post_Types {

	const ISSUE = 'nlwp_issue';
	const EVENT = 'nlwp_event';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register post types.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::ISSUE,
			array(
				'labels'              => array(
					'name'                  => __( 'Newsletter Issues', 'newsletter-wp-plugin' ),
					'singular_name'         => __( 'Newsletter Issue', 'newsletter-wp-plugin' ),
					'menu_name'             => __( 'Newsletter', 'newsletter-wp-plugin' ),
					'add_new'               => __( 'Add issue', 'newsletter-wp-plugin' ),
					'add_new_item'          => __( 'Add newsletter issue', 'newsletter-wp-plugin' ),
					'edit_item'             => __( 'Edit newsletter issue', 'newsletter-wp-plugin' ),
					'new_item'              => __( 'New newsletter issue', 'newsletter-wp-plugin' ),
					'view_item'             => __( 'View newsletter issue', 'newsletter-wp-plugin' ),
					'search_items'          => __( 'Search newsletter issues', 'newsletter-wp-plugin' ),
					'not_found'             => __( 'No newsletter issues found.', 'newsletter-wp-plugin' ),
					'not_found_in_trash'    => __( 'No newsletter issues found in Trash.', 'newsletter-wp-plugin' ),
					'all_items'             => __( 'All issues', 'newsletter-wp-plugin' ),
					'item_published'        => __( 'Newsletter issue published.', 'newsletter-wp-plugin' ),
					'item_updated'          => __( 'Newsletter issue updated.', 'newsletter-wp-plugin' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'menu_position'       => 26,
				'menu_icon'           => 'dashicons-email-alt',
				'show_in_rest'        => false,
				'has_archive'         => false,
				'query_var'           => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		register_post_type(
			self::EVENT,
			array(
				'labels'              => array(
					'name'               => __( 'Events', 'newsletter-wp-plugin' ),
					'singular_name'      => __( 'Event', 'newsletter-wp-plugin' ),
					'menu_name'          => __( 'Events', 'newsletter-wp-plugin' ),
					'add_new'            => __( 'Add event', 'newsletter-wp-plugin' ),
					'add_new_item'       => __( 'Add event', 'newsletter-wp-plugin' ),
					'edit_item'          => __( 'Edit event', 'newsletter-wp-plugin' ),
					'new_item'           => __( 'New event', 'newsletter-wp-plugin' ),
					'view_item'          => __( 'View event', 'newsletter-wp-plugin' ),
					'search_items'       => __( 'Search events', 'newsletter-wp-plugin' ),
					'not_found'          => __( 'No events found.', 'newsletter-wp-plugin' ),
					'not_found_in_trash' => __( 'No events found in Trash.', 'newsletter-wp-plugin' ),
					'all_items'          => __( 'All events', 'newsletter-wp-plugin' ),
					'item_published'     => __( 'Event published.', 'newsletter-wp-plugin' ),
					'item_updated'       => __( 'Event updated.', 'newsletter-wp-plugin' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . self::ISSUE,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'query_var'           => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'show_in_nav_menus'   => false,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
