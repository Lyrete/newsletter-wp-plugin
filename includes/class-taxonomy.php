<?php
/**
 * Event categories.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the event category taxonomy.
 */
class Taxonomy {

	const TAXONOMY = 'nlwp_category';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register taxonomy.
	 *
	 * @return void
	 */
	public static function register(): void {
		register_taxonomy(
			self::TAXONOMY,
			array( Post_Types::EVENT ),
			array(
				'labels'            => array(
					'name'              => __( 'Event categories', 'newsletter-wp-plugin' ),
					'singular_name'     => __( 'Event category', 'newsletter-wp-plugin' ),
					'search_items'      => __( 'Search categories', 'newsletter-wp-plugin' ),
					'all_items'         => __( 'All categories', 'newsletter-wp-plugin' ),
					'edit_item'         => __( 'Edit category', 'newsletter-wp-plugin' ),
					'update_item'       => __( 'Update category', 'newsletter-wp-plugin' ),
					'add_new_item'      => __( 'Add category', 'newsletter-wp-plugin' ),
					'new_item_name'     => __( 'New category name', 'newsletter-wp-plugin' ),
					'menu_name'         => __( 'Categories', 'newsletter-wp-plugin' ),
				),
				'public'            => false,
				'publicly_queryable'=> false,
				'show_ui'           => true,
				'show_in_menu'      => false,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'hierarchical'      => false,
				'query_var'         => false,
				'rewrite'           => false,
				'capabilities'      => array(
					'manage_terms' => 'manage_categories',
					'edit_terms'   => 'manage_categories',
					'delete_terms' => 'manage_categories',
					'assign_terms' => 'edit_posts',
				),
			)
		);
	}

	/**
	 * Create the initial category terms on activation.
	 *
	 * @return void
	 */
	public static function create_default_terms(): void {
		$terms = array(
			'announcements' => array(
				'name'  => __( 'Announcements', 'newsletter-wp-plugin' ),
				'order' => 10,
			),
			'guild-events'  => array(
				'name'  => __( 'Guild events', 'newsletter-wp-plugin' ),
				'order' => 20,
			),
			'other-events'  => array(
				'name'  => __( 'Other events', 'newsletter-wp-plugin' ),
				'order' => 30,
			),
			'miscellaneous' => array(
				'name'  => __( 'Miscellaneous', 'newsletter-wp-plugin' ),
				'order' => 40,
			),
		);

		foreach ( $terms as $slug => $data ) {
			if ( term_exists( $slug, self::TAXONOMY ) ) {
				continue;
			}

			$result = wp_insert_term(
				$data['name'],
				self::TAXONOMY,
				array( 'slug' => $slug )
			);

			if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
				continue;
			}

			update_term_meta( (int) $result['term_id'], '_nlwp_order', (int) $data['order'] );
			update_term_meta( (int) $result['term_id'], '_nlwp_enabled', '1' );
		}
	}
}
