<?php
/**
 * Issue admin fields and event synchronisation.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Adds issue details, manual event selection and resync actions.
 */
class Admin_Issues {

	const NONCE = 'nlwp_issue_meta_nonce';

	/**
	 * ID of the event currently being deleted, or 0.
	 *
	 * @var int
	 */
	private static int $deleting_event = 0;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Post_Types::ISSUE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'save_post_' . Post_Types::EVENT, array( __CLASS__, 'resync_issues' ), 20 );
		add_action( 'before_delete_post', array( __CLASS__, 'mark_deleting_event' ) );
		add_action( 'deleted_post', array( __CLASS__, 'resync_after_delete' ) );
		add_action( 'trashed_post', array( __CLASS__, 'resync_issues' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'resync_issues' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_nlwp_sync_issue', array( __CLASS__, 'handle_sync' ) );
	}

	/**
	 * Register the issue details meta box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'nlwp-issue-details',
			__( 'Issue details', 'newsletter-wp-plugin' ),
			array( __CLASS__, 'render' ),
			Post_Types::ISSUE,
			'normal',
			'high'
		);
	}

	/**
	 * Render issue fields.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );

		$settings     = Settings::get_settings();
		$issue_date   = (string) get_post_meta( $post->ID, '_nlwp_issue_date', true );
		$period_start = (string) get_post_meta( $post->ID, '_nlwp_period_start', true );
		$period_end   = (string) get_post_meta( $post->ID, '_nlwp_period_end', true );
		$mode         = (string) get_post_meta( $post->ID, '_nlwp_selection_mode', true );
		$event_ids    = nlwp_sanitize_id_array( get_post_meta( $post->ID, '_nlwp_event_ids', true ) );

		$mode = 'manual' === $mode ? 'manual' : 'auto';

		if ( '' === $issue_date ) {
			$issue_date = (string) current_time( 'Y-m-d' );
		}
		if ( '' === $period_start ) {
			$period_start = $issue_date;
		}
		if ( '' === $period_end ) {
			$period_end = nlwp_add_days( $period_start, (int) $settings['default_period_days'] );
		}

		echo '<table class="form-table nlwp-issue-fields" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="nlwp-issue-date">' . esc_html__( 'Issue date', 'newsletter-wp-plugin' ) . '</label></th><td>';
		echo '<input type="date" id="nlwp-issue-date" name="nlwp_issue_date" value="' . esc_attr( $issue_date ) . '">';
		echo '<p class="description">' . esc_html__( 'Shown when issue dates are enabled in the settings.', 'newsletter-wp-plugin' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Event period', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<label for="nlwp-period-start">' . esc_html__( 'From', 'newsletter-wp-plugin' ) . '</label> ';
		echo '<input type="date" id="nlwp-period-start" name="nlwp_period_start" value="' . esc_attr( $period_start ) . '"> ';
		echo '<label for="nlwp-period-end">' . esc_html__( 'To', 'newsletter-wp-plugin' ) . '</label> ';
		echo '<input type="date" id="nlwp-period-end" name="nlwp_period_end" value="' . esc_attr( $period_end ) . '">';
		echo '<p class="description">' . esc_html__( 'Events overlapping this range are selected automatically.', 'newsletter-wp-plugin' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Event selection', 'newsletter-wp-plugin' ) . '</th><td>';
		echo '<label><input type="radio" name="nlwp_selection_mode" value="auto"' . checked( $mode, 'auto', false ) . '> ' . esc_html__( 'Select automatically from the period', 'newsletter-wp-plugin' ) . '</label><br>';
		echo '<label><input type="radio" name="nlwp_selection_mode" value="manual"' . checked( $mode, 'manual', false ) . '> ' . esc_html__( 'Choose events manually', 'newsletter-wp-plugin' ) . '</label>';
		echo '</td></tr>';

		echo '<tr class="nlwp-manual-events"><th scope="row">' . esc_html__( 'Events', 'newsletter-wp-plugin' ) . '</th><td>';

		$events = get_posts(
			array(
				'post_type'      => Post_Types::EVENT,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $events ) ) {
			echo '<p>' . esc_html__( 'No events have been created yet.', 'newsletter-wp-plugin' ) . '</p>';
		} else {
			echo '<div class="nlwp-event-checklist">';
			foreach ( $events as $event ) {
				$start = (string) get_post_meta( $event->ID, '_nlwp_start_date', true );
				echo '<label class="nlwp-event-choice">';
				echo '<input type="checkbox" name="nlwp_event_ids[]" value="' . esc_attr( (string) $event->ID ) . '"' . checked( in_array( (int) $event->ID, $event_ids, true ), true, false ) . '>';
				echo '<span class="nlwp-event-choice-title">' . esc_html( get_the_title( $event ) ) . '</span>';
				if ( '' !== $start ) {
					echo ' <span class="nlwp-event-choice-date">' . esc_html( $start ) . '</span>';
				}
				echo '</label>';
			}
			echo '</div>';
		}

		echo '<p class="description">' . esc_html__( 'Manual choices are used as-is. Automatic selection is refreshed when the issue is saved and whenever an event is saved.', 'newsletter-wp-plugin' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		$synced = (string) get_post_meta( $post->ID, '_nlwp_events_synced_at', true );
		if ( '' !== $synced ) {
			echo '<p class="description">' . esc_html( sprintf( /* translators: %s: date and time. */ __( 'Events last synchronised: %s', 'newsletter-wp-plugin' ), $synced ) ) . '</p>';
		}
	}

	/**
	 * Save issue fields.
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

		if ( ! current_user_can( 'edit_post', $post_id ) || Post_Types::ISSUE !== $post->post_type ) {
			return;
		}

		$issue_date   = isset( $_POST['nlwp_issue_date'] ) ? nlwp_sanitize_date( wp_unslash( $_POST['nlwp_issue_date'] ) ) : '';
		$period_start = isset( $_POST['nlwp_period_start'] ) ? nlwp_sanitize_date( wp_unslash( $_POST['nlwp_period_start'] ) ) : '';
		$period_end   = isset( $_POST['nlwp_period_end'] ) ? nlwp_sanitize_date( wp_unslash( $_POST['nlwp_period_end'] ) ) : '';
		$mode         = isset( $_POST['nlwp_selection_mode'] ) && 'manual' === $_POST['nlwp_selection_mode'] ? 'manual' : 'auto';

		if ( '' !== $period_start && '' !== $period_end && $period_end < $period_start ) {
			$period_end = $period_start;
		}

		if ( 'auto' === $mode ) {
			if ( '' === $period_start && '' !== $issue_date ) {
				$period_start = $issue_date;
			}
			if ( '' === $period_end && '' !== $period_start ) {
				$settings     = Settings::get_settings();
				$period_end   = nlwp_add_days( $period_start, (int) $settings['default_period_days'] );
			}
		}

		update_post_meta( $post_id, '_nlwp_issue_date', $issue_date );
		update_post_meta( $post_id, '_nlwp_period_start', $period_start );
		update_post_meta( $post_id, '_nlwp_period_end', $period_end );
		update_post_meta( $post_id, '_nlwp_selection_mode', $mode );

		if ( 'manual' === $mode ) {
			$ids = isset( $_POST['nlwp_event_ids'] ) ? nlwp_sanitize_id_array( wp_unslash( $_POST['nlwp_event_ids'] ) ) : array();
		} else {
			$ids = Issue_Selection::get_auto_event_ids( $period_start, $period_end );
		}

		update_post_meta( $post_id, '_nlwp_event_ids', Issue_Selection::sort_event_ids( $ids ) );
		update_post_meta( $post_id, '_nlwp_events_synced_at', (string) current_time( 'mysql' ) );
	}

	/**
	 * Recalculate automatic issues after event changes.
	 *
	 * @param int $event_id Event post ID.
	 * @return void
	 */
	public static function resync_issues( int $event_id ): void {
		if ( Post_Types::EVENT !== get_post_type( $event_id ) ) {
			return;
		}

		self::refresh_auto_issues();
	}

	/**
	 * Remember that the post being deleted is an event.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function mark_deleting_event( int $post_id ): void {
		if ( Post_Types::EVENT === get_post_type( $post_id ) ) {
			self::$deleting_event = $post_id;
		}
	}

	/**
	 * Recalculate automatic issues after an event has been deleted.
	 *
	 * @param int $post_id Deleted post ID.
	 * @return void
	 */
	public static function resync_after_delete( int $post_id ): void {
		if ( ! self::$deleting_event || $post_id !== self::$deleting_event ) {
			return;
		}

		self::$deleting_event = 0;
		self::refresh_auto_issues();
	}

	/**
	 * Refresh event selections for every automatic issue.
	 *
	 * @return void
	 */
	private static function refresh_auto_issues(): void {
		$issue_ids = get_posts(
			array(
				'post_type'      => Post_Types::ISSUE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_nlwp_selection_mode',
						'value' => 'auto',
					),
				),
			)
		);

		foreach ( $issue_ids as $issue_id ) {
			$start = (string) get_post_meta( $issue_id, '_nlwp_period_start', true );
			$end   = (string) get_post_meta( $issue_id, '_nlwp_period_end', true );
			$ids   = Issue_Selection::get_auto_event_ids( $start, $end );

			update_post_meta( $issue_id, '_nlwp_event_ids', $ids );
			update_post_meta( $issue_id, '_nlwp_events_synced_at', (string) current_time( 'mysql' ) );
		}
	}

	/**
	 * Add a sync action to issue rows.
	 *
	 * @param array    $actions Existing actions.
	 * @param \WP_Post $post    Post object.
	 * @return array
	 */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( Post_Types::ISSUE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'nlwp_sync_issue',
					'issue_id' => $post->ID,
				),
				admin_url( 'admin-post.php' )
			),
			'nlwp_sync_issue_' . $post->ID
		);

		$actions['nlwp_sync'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Sync events', 'newsletter-wp-plugin' ) . '</a>';

		return $actions;
	}

	/**
	 * Handle the manual sync action.
	 *
	 * @return void
	 */
	public static function handle_sync(): void {
		$issue_id = isset( $_GET['issue_id'] ) ? absint( $_GET['issue_id'] ) : 0;

		if ( ! $issue_id || Post_Types::ISSUE !== get_post_type( $issue_id ) ) {
			wp_die( esc_html__( 'Invalid issue.', 'newsletter-wp-plugin' ) );
		}

		check_admin_referer( 'nlwp_sync_issue_' . $issue_id );

		if ( ! current_user_can( 'edit_post', $issue_id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this issue.', 'newsletter-wp-plugin' ) );
		}

		$mode = get_post_meta( $issue_id, '_nlwp_selection_mode', true );

		if ( 'manual' === $mode ) {
			$ids = nlwp_sanitize_id_array( get_post_meta( $issue_id, '_nlwp_event_ids', true ) );
		} else {
			$ids = Issue_Selection::get_auto_event_ids(
				(string) get_post_meta( $issue_id, '_nlwp_period_start', true ),
				(string) get_post_meta( $issue_id, '_nlwp_period_end', true )
			);
		}

		update_post_meta( $issue_id, '_nlwp_event_ids', Issue_Selection::sort_event_ids( $ids ) );
		update_post_meta( $issue_id, '_nlwp_events_synced_at', (string) current_time( 'mysql' ) );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . Post_Types::ISSUE ) );
		exit;
	}
}
