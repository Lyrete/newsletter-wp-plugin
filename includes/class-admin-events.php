<?php
/**
 * Event admin fields.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Adds event details to the event editor.
 */
class Admin_Events {

	const NONCE = 'nlwp_event_meta_nonce';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Types::EVENT, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'manage_' . Post_Types::EVENT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . Post_Types::EVENT . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	/**
	 * Register the event details meta box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'nlwp-event-details',
			__( 'Event details', 'newsletter-wp-plugin' ),
			array( __CLASS__, 'render' ),
			Post_Types::EVENT,
			'normal',
			'high'
		);
	}

	/**
	 * Render event fields.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$start      = (string) get_post_meta( $post->ID, '_nlwp_start_date', true );
		$end        = (string) get_post_meta( $post->ID, '_nlwp_end_date', true );
		$show_date  = get_post_meta( $post->ID, '_nlwp_show_date', true );
		$show_date  = '' === $show_date ? '1' : (string) $show_date;
		$attachment = (int) get_post_meta( $post->ID, '_nlwp_attachment_id', true );

		echo '<table class="form-table nlwp-event-fields" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nlwp-start-date">' . esc_html__( 'Start date', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="date" id="nlwp-start-date" name="nlwp_start_date" value="' . esc_attr( $start ) . '">';
		echo '<p class="description">' . esc_html__( 'Events are included in issues based on this date.', 'newsletter-wp-plugin' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="nlwp-end-date">' . esc_html__( 'End date', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="date" id="nlwp-end-date" name="nlwp_end_date" value="' . esc_attr( $end ) . '">';
		echo '<p class="description">' . esc_html__( 'Optional. An event with an end date is active for the whole range.', 'newsletter-wp-plugin' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Date display', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<label><input type="checkbox" name="nlwp_show_date" value="1"' . checked( '1', $show_date, false ) . '> ' . esc_html__( 'Show the date with this event', 'newsletter-wp-plugin' ) . '</label>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Attachment', 'newsletter-wp-plugin' ) . '</th><td>';
		nlwp_admin_media_field( 'nlwp_attachment_id', $attachment, '' );
		echo '<p class="description">' . esc_html__( 'Optional file linked below the event description.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
	}

	/**
	 * Save event fields.
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

		if ( ! current_user_can( 'edit_post', $post_id ) || Post_Types::EVENT !== $post->post_type ) {
			return;
		}

		$start = isset( $_POST['nlwp_start_date'] ) ? nlwp_sanitize_date( wp_unslash( $_POST['nlwp_start_date'] ) ) : '';
		$end   = isset( $_POST['nlwp_end_date'] ) ? nlwp_sanitize_date( wp_unslash( $_POST['nlwp_end_date'] ) ) : '';

		if ( '' !== $start && '' !== $end && $end < $start ) {
			$end = $start;
		}

		update_post_meta( $post_id, '_nlwp_start_date', $start );
		update_post_meta( $post_id, '_nlwp_end_date', $end );
		update_post_meta( $post_id, '_nlwp_show_date', isset( $_POST['nlwp_show_date'] ) ? '1' : '0' );

		$attachment = isset( $_POST['nlwp_attachment_id'] ) ? absint( wp_unslash( $_POST['nlwp_attachment_id'] ) ) : 0;
		update_post_meta( $post_id, '_nlwp_attachment_id', $attachment );
	}

	/**
	 * Add event list columns.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public static function columns( array $columns ): array {
		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['nlwp_start'] = __( 'Start date', 'newsletter-wp-plugin' );
			}
		}

		if ( ! isset( $new['nlwp_start'] ) ) {
			$new['nlwp_start'] = __( 'Start date', 'newsletter-wp-plugin' );
		}

		return $new;
	}

	/**
	 * Render event list column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public static function column_content( string $column, int $post_id ): void {
		if ( 'nlwp_start' !== $column ) {
			return;
		}

		$start = (string) get_post_meta( $post_id, '_nlwp_start_date', true );
		$end   = (string) get_post_meta( $post_id, '_nlwp_end_date', true );

		if ( '' === $start ) {
			echo '&mdash;';
			return;
		}

		echo esc_html( $start );
		if ( '' !== $end && $end !== $start ) {
			echo ' &ndash; ' . esc_html( $end );
		}
	}
}
