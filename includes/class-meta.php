<?php
/**
 * Post and term metadata.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers metadata with sanitization callbacks.
 */
class Meta {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register all metadata.
	 *
	 * @return void
	 */
	public static function register(): void {
		$post_auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', (int) $post_id );
		};

		$event_meta = array(
			'_nlwp_start_date'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_date',
			),
			'_nlwp_end_date'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_date',
			),
			'_nlwp_show_date'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_bool',
			),
			'_nlwp_attachment_id'  => array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			),
			'_nlwp_translations'   => array(
				'type'              => 'array',
				'sanitize_callback' => array( Translations::class, 'sanitize_event_translations' ),
			),
		);

		foreach ( $event_meta as $key => $args ) {
			register_post_meta(
				Post_Types::EVENT,
				$key,
				array_merge(
					array(
						'single'        => true,
						'auth_callback' => $post_auth,
						'show_in_rest'  => false,
					),
					$args
				)
			);
		}

		$issue_meta = array(
			'_nlwp_issue_date'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_date',
			),
			'_nlwp_period_start'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_date',
			),
			'_nlwp_period_end'        => array(
				'type'              => 'string',
				'sanitize_callback' => 'nlwp_sanitize_date',
			),
			'_nlwp_selection_mode'    => array(
				'type'              => 'string',
				'sanitize_callback' => static function ( $value ) {
					return 'manual' === $value ? 'manual' : 'auto';
				},
			),
			'_nlwp_event_ids'         => array(
				'type'              => 'array',
				'sanitize_callback' => 'nlwp_sanitize_id_array',
			),
			'_nlwp_events_synced_at'  => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'_nlwp_translations'      => array(
				'type'              => 'array',
				'sanitize_callback' => array( Translations::class, 'sanitize_issue_translations' ),
			),
		);

		foreach ( $issue_meta as $key => $args ) {
			register_post_meta(
				Post_Types::ISSUE,
				$key,
				array_merge(
					array(
						'single'        => true,
						'auth_callback' => $post_auth,
						'show_in_rest'  => false,
					),
					$args
				)
			);
		}

		register_term_meta(
			Taxonomy::TAXONOMY,
			'_nlwp_order',
			array(
				'type'              => 'integer',
				'single'            => true,
				'sanitize_callback' => 'absint',
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
				'show_in_rest'      => false,
			)
		);

		register_term_meta(
			Taxonomy::TAXONOMY,
			'_nlwp_enabled',
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => 'nlwp_sanitize_bool',
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
				'show_in_rest'      => false,
			)
		);

		register_term_meta(
			Taxonomy::TAXONOMY,
			'_nlwp_label_translations',
			array(
				'type'              => 'array',
				'single'            => true,
				'sanitize_callback' => array( Translations::class, 'sanitize_term_label_translations' ),
				'auth_callback'     => static function () {
					return current_user_can( 'manage_categories' );
				},
				'show_in_rest'      => false,
			)
		);
	}
}
