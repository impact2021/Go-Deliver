<?php
/**
 * Handles in-app and cron-based notifications.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Notifications
 *
 * Wires up cron hooks for scheduled notification processing and provides
 * helper methods for sending notifications to users.
 */
class Go_Deliver_Notifications {

	/**
	 * Register cron action callbacks.
	 *
	 * Called from Go_Deliver::define_common_hooks() so the actions are
	 * always registered, even if no event is currently scheduled.
	 */
	public function setup_cron() {
		add_action( 'gd_hourly_notifications', array( $this, 'process_hourly_notifications' ) );
		add_action( 'gd_daily_notifications', array( $this, 'process_daily_notifications' ) );
	}

	/**
	 * Hourly cron callback.
	 *
	 * Checks for unread messages and dispatches in-app notifications.
	 */
	public function process_hourly_notifications() {
		$this->notify_unread_messages();
	}

	/**
	 * Daily cron callback.
	 *
	 * Handles job-expiry warnings and quote-expiry reminders.
	 */
	public function process_daily_notifications() {
		$this->notify_expiring_jobs();
		$this->notify_expiring_quotes();
	}

	// -------------------------------------------------------------------------
	// Notification dispatch helpers.
	// -------------------------------------------------------------------------

	/**
	 * Send an in-app notification and optionally an email to a user.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $type    Machine-readable notification type.
	 * @param string $subject Short subject line.
	 * @param string $message Full notification body (may contain HTML).
	 * @param bool   $send_email Whether to also send a WordPress email.
	 */
	public static function notify( $user_id, $type, $subject, $message, $send_email = false ) {
		Go_Deliver_DB::save_notification( $user_id, $type, $subject, $message );

		if ( $send_email ) {
			$user = get_userdata( (int) $user_id );
			if ( $user && $user->user_email ) {
				wp_mail(
					$user->user_email,
					wp_strip_all_tags( $subject ),
					wp_strip_all_tags( $message )
				);
			}
		}
	}

	// -------------------------------------------------------------------------
	// Private cron processing methods.
	// -------------------------------------------------------------------------

	/**
	 * Notify users who have unread messages older than one hour.
	 */
	private function notify_unread_messages() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT receiver_id, COUNT(*) AS cnt
			 FROM `{$wpdb->prefix}gd_messages`
			 WHERE is_read = 0
			   AND created_at <= DATE_SUB( NOW(), INTERVAL 1 HOUR )
			 GROUP BY receiver_id"
		);

		foreach ( $rows as $row ) {
			/* translators: %d: number of unread messages */
			$subject = sprintf( __( 'You have %d unread message(s)', 'go-deliver' ), (int) $row->cnt );
			/* translators: %d: number of unread messages */
			$message = sprintf( __( 'You have %d unread message(s) waiting for you on Go Deliver. Log in to view them.', 'go-deliver' ), (int) $row->cnt );

			self::notify( $row->receiver_id, 'unread_messages', $subject, $message, true );
		}
	}

	/**
	 * Notify customers whose jobs are nearing the expiry date.
	 */
	private function notify_expiring_jobs() {
		$expiry_days    = (int) get_option( 'gd_job_expiry_days', 30 );
		$warning_days   = max( 1, (int) floor( $expiry_days * 0.2 ) ); // 20% of expiry window.
		$threshold_date = date( 'Y-m-d', strtotime( "+{$warning_days} days" ) );

		$jobs = get_posts(
			array(
				'post_type'      => 'gd_job',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_gd_expiry_date',
						'value'   => $threshold_date,
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);

		foreach ( $jobs as $job_id ) {
			$author_id = (int) get_post_field( 'post_author', $job_id );
			$subject   = __( 'Your job listing is expiring soon', 'go-deliver' );
			/* translators: %d: job ID */
			$message = sprintf( __( 'Your job listing #%d on Go Deliver is expiring soon. Log in to renew it.', 'go-deliver' ), $job_id );
			self::notify( $author_id, 'job_expiring', $subject, $message, true );
		}
	}

	/**
	 * Notify movers whose quotes are nearing the expiry date.
	 */
	private function notify_expiring_quotes() {
		$expiry_days    = (int) get_option( 'gd_quote_expiry_days', 14 );
		$warning_days   = max( 1, (int) floor( $expiry_days * 0.2 ) );
		$threshold_date = date( 'Y-m-d', strtotime( "+{$warning_days} days" ) );

		$quotes = get_posts(
			array(
				'post_type'      => 'gd_quote',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_gd_expiry_date',
						'value'   => $threshold_date,
						'compare' => '<=',
						'type'    => 'DATE',
					),
				),
			)
		);

		foreach ( $quotes as $quote_id ) {
			$author_id = (int) get_post_field( 'post_author', $quote_id );
			$subject   = __( 'Your quote is expiring soon', 'go-deliver' );
			/* translators: %d: quote ID */
			$message = sprintf( __( 'Your quote #%d on Go Deliver is expiring soon. Log in to update it.', 'go-deliver' ), $quote_id );
			self::notify( $author_id, 'quote_expiring', $subject, $message, true );
		}
	}
}
