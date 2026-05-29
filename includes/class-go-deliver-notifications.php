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

	/** Valid mover notification frequency values. */
	const VALID_FREQUENCIES = array( 'instant', 'hourly', 'daily' );

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
	 * Checks for unread messages, dispatches in-app notifications, and sends
	 * hourly job-notification digests to movers who prefer them.
	 */
	public function process_hourly_notifications() {
		$this->notify_unread_messages();
		$this->process_pending_job_notifications( 'hourly' );
	}

	/**
	 * Daily cron callback.
	 *
	 * Handles job-expiry warnings, quote-expiry reminders, and sends daily
	 * job-notification digests to movers who prefer them.
	 */
	public function process_daily_notifications() {
		$this->notify_expiring_jobs();
		$this->notify_expiring_quotes();
		$this->process_pending_job_notifications( 'daily' );
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
				self::send_plain_email(
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
	 *
	 * A per-user meta flag `gd_unread_msg_cron_notified` is set after each
	 * email so the same batch of unread messages never triggers more than one
	 * email. The flag is cleared in `ajax_get_messages()` (via
	 * `mark_messages_read`) when the user actually views the thread, ensuring
	 * any subsequent new unread messages will generate a fresh notification.
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

		$messaging_page_id = (int) get_option( 'gd_messaging_page_id', 0 );
		$messaging_url     = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();
		$site_name         = get_bloginfo( 'name' );

		foreach ( $rows as $row ) {
			$user_id = (int) $row->receiver_id;

			// Skip if we already sent an unread-message cron email for this
			// user and they haven't viewed the thread yet.
			if ( get_user_meta( $user_id, 'gd_unread_msg_cron_notified', true ) ) {
				continue;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			$unread_count = (int) $row->cnt;

			/* translators: %d: number of unread messages */
			$subject = sprintf( __( 'You have %d unread message(s)', 'go-deliver' ), $unread_count );
			/* translators: %d: number of unread messages */
			$body = sprintf( __( 'You have %d unread message(s) waiting for you on Go Deliver. Log in to view them.', 'go-deliver' ), $unread_count );

			// Save in-app notification.
			Go_Deliver_DB::save_notification( $user_id, 'unread_messages', $subject, $body );

			// Send HTML email with direct link to messaging inbox.
			$this->send_html_email(
				$user->user_email,
				$subject,
				GD_PLUGIN_DIR . 'templates/emails/unread-messages.php',
				array(
					'recipient_first_name' => $user->first_name ?: $user->display_name,
					'unread_count'         => $unread_count,
					'messaging_url'        => $messaging_url,
					'site_name'            => $site_name,
					'site_url'             => home_url(),
				)
			);

			// Record that we notified this user so we don't spam them again
			// until they view their messages (which clears this flag).
			update_user_meta( $user_id, 'gd_unread_msg_cron_notified', 1 );
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

	// -------------------------------------------------------------------------
	// Message notifications.
	// -------------------------------------------------------------------------

	/**
	 * Email the receiver when a new message is sent.
	 *
	 * @param int $job_id      gd_job post ID.
	 * @param int $sender_id   Sender user ID.
	 * @param int $receiver_id Receiver user ID.
	 * @param int $message_id  Saved message ID (unused, reserved for future use).
	 */
	public function notify_new_message( $job_id, $sender_id, $receiver_id, $message_id ) {
		$receiver = get_userdata( (int) $receiver_id );
		if ( ! $receiver || ! $receiver->user_email ) {
			return;
		}

		// Only send one email per unread conversation thread. If the receiver has
		// already been notified since they last viewed the conversation, skip the
		// email. The flag is cleared when the receiver fetches messages.
		$notified_meta_key = 'gd_msg_notified_' . (int) $job_id . '_' . (int) $sender_id;
		if ( get_user_meta( (int) $receiver_id, $notified_meta_key, true ) ) {
			return;
		}
		update_user_meta( (int) $receiver_id, $notified_meta_key, 1 );

		$sender   = get_userdata( (int) $sender_id );
		$job_type = Go_Deliver_Jobs::get_display_title( (int) $job_id );

		$messaging_page_id = (int) get_option( 'gd_messaging_page_id', 0 );
		$conversation_url  = $messaging_page_id
			? add_query_arg(
				array(
					'job_id'         => (int) $job_id,
					'participant_id' => (int) $sender_id,
				),
				get_permalink( $messaging_page_id )
			)
			: home_url();

		$site_name = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'New Message – %s', 'go-deliver' ),
			$site_name
		);

		// Retrieve the last saved message for a preview snippet.
		$messages        = Go_Deliver_DB::get_messages( (int) $job_id, (int) $receiver_id );
		$message_preview = '';
		if ( is_array( $messages ) && ! empty( $messages ) ) {
			$last            = end( $messages );
			$raw             = isset( $last->message ) ? $last->message : ( isset( $last['message'] ) ? $last['message'] : '' );
			$message_preview = wp_trim_words( $raw, 30, '…' );
		}

		$this->send_html_email(
			$receiver->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/new-message.php',
			array(
				'recipient_first_name' => $receiver->first_name ?: $receiver->display_name,
				'sender_first_name'    => $sender ? ( $sender->first_name ?: $sender->display_name ) : __( 'Someone', 'go-deliver' ),
				'job_type'             => $job_type,
				'message_preview'      => $message_preview,
				'conversation_url'     => $conversation_url,
				'site_name'            => $site_name,
				'site_url'             => home_url(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// New-job notifications for movers.
	// -------------------------------------------------------------------------

	/**
	 * Notify all approved movers that match a newly-posted job.
	 *
	 * Movers with an 'instant' notification preference receive an email
	 * straight away.  Movers with 'hourly' or 'daily' preferences have the
	 * job ID added to their pending queue; the digest is flushed by the
	 * corresponding cron callback.
	 *
	 * @param int $job_id Newly-created gd_job post ID.
	 */
	public function notify_movers_new_job( $job_id ) {
		$job_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
		if ( 'open' !== $job_status ) {
			return;
		}

		// Build a minimal job array that filter_jobs_by_radius() can consume.
		$pickup_location = json_decode( get_post_meta( (int) $job_id, 'gd_pickup_location', true ), true );
		$job_data = array(
			'id'              => (int) $job_id,
			'job_type'        => get_post_meta( (int) $job_id, 'gd_job_type', true ),
			'status'          => $job_status,
			'pickup_location' => is_array( $pickup_location ) ? $pickup_location : array(),
		);

		$location_handler = new Go_Deliver_Location();

		$movers = get_users(
			array(
				'role'       => 'gd_mover',
				'meta_key'   => 'gd_mover_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'approved', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'     => 'ID',
				'number'     => -1,
			)
		);

		foreach ( $movers as $mover_id ) {
			// Skip movers explicitly excluded from this job by the customer.
			$stored_excluded = get_post_meta( (int) $job_id, 'gd_excluded_mover_ids', true );
			$excluded_ids    = is_array( $stored_excluded ) ? array_map( 'intval', $stored_excluded ) : array();
			if ( in_array( (int) $mover_id, $excluded_ids, true ) ) {
				continue;
			}

			$matching = $location_handler->filter_jobs_by_radius( array( $job_data ), $mover_id );
			if ( empty( $matching ) ) {
				continue;
			}

			$frequency = get_user_meta( (int) $mover_id, 'gd_notification_frequency', true );
			if ( ! in_array( $frequency, self::VALID_FREQUENCIES, true ) ) {
				$frequency = 'instant';
			}

			if ( 'instant' === $frequency ) {
				$this->send_mover_new_job_email( (int) $mover_id, (int) $job_id );
			} else {
				// Queue for digest.
				$pending = get_user_meta( (int) $mover_id, 'gd_pending_job_notifications', true );
				if ( ! is_array( $pending ) ) {
					$pending = array();
				}
				if ( ! in_array( (int) $job_id, $pending, true ) ) {
					$pending[] = (int) $job_id;
					update_user_meta( (int) $mover_id, 'gd_pending_job_notifications', $pending );
				}
			}
		}
	}

	// -------------------------------------------------------------------------
	// Quote notifications.
	// -------------------------------------------------------------------------

	/**
	 * Send a confirmation email to the customer immediately after they post a job.
	 *
	 * Includes a summary of the job details and a link to their dashboard
	 * where they can view quotes or cancel the listing.
	 *
	 * @param int $job_id Newly-created gd_job post ID.
	 */
	public function notify_customer_job_posted( $job_id ) {
		$customer_id = (int) get_post_meta( (int) $job_id, 'gd_customer_id', true );
		$customer    = get_userdata( $customer_id );
		if ( ! $customer || ! $customer->user_email ) {
			return;
		}

		$site_name      = get_bloginfo( 'name' );
		$job_type       = Go_Deliver_Jobs::get_display_title( (int) $job_id );
		$pickup_suburb  = get_post_meta( (int) $job_id, 'gd_pickup_suburb', true );
		$dropoff_suburb = get_post_meta( (int) $job_id, 'gd_dropoff_suburb', true );
		$date_requested = get_post_meta( (int) $job_id, 'gd_date_requested', true );

		$dashboard_page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
		$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Job Has Been Posted – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$customer->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/job-posted-confirmation.php',
			array(
				'customer_first_name' => $customer->first_name ?: $customer->display_name,
				'job_id'              => (int) $job_id,
				'job_type'            => $job_type,
				'pickup_suburb'       => $pickup_suburb,
				'dropoff_suburb'      => $dropoff_suburb,
				'date_requested'      => $date_requested,
				'dashboard_url'       => $dashboard_url,
				'site_name'           => $site_name,
				'site_url'            => home_url(),
			)
		);
	}

	/**
	 * Email the customer when a mover marks a job as completed.
	 *
	 * Prompts the customer to leave a review for the mover.
	 *
	 * @param int $job_id gd_job post ID.
	 */
	public function notify_customer_job_completed( $job_id ) {
		$customer_id = (int) get_post_meta( (int) $job_id, 'gd_customer_id', true );
		$customer    = get_userdata( $customer_id );
		if ( ! $customer || ! $customer->user_email ) {
			return;
		}

		$accepted_quote_id = (int) get_post_meta( (int) $job_id, 'gd_accepted_quote_id', true );
		$mover_id          = $accepted_quote_id ? (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true ) : 0;
		$mover             = $mover_id ? get_userdata( $mover_id ) : null;
		$job_type          = Go_Deliver_Jobs::get_display_title( (int) $job_id );
		$site_name         = get_bloginfo( 'name' );

		$dashboard_page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
		$review_url        = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Move is Complete – Please Leave a Review – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$customer->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/job-completed.php',
			array(
				'customer_first_name' => $customer->first_name,
				'mover_first_name'    => $mover ? $mover->first_name : __( 'Your mover', 'go-deliver' ),
				'job_type'            => $job_type,
				'review_url'          => $review_url,
				'site_name'           => $site_name,
				'site_url'            => home_url(),
			)
		);
	}

	/**
	 * Email the mover when a customer cancels a job they had won.
	 *
	 * Also informs them of any credits that were refunded to their wallet.
	 *
	 * @param int   $job_id        gd_job post ID.
	 * @param float $refund_amount Amount refunded to the mover's wallet (0 = none).
	 */
	public function notify_mover_job_cancelled( $job_id, $refund_amount = 0.0 ) {
		$accepted_quote_id = (int) get_post_meta( (int) $job_id, 'gd_accepted_quote_id', true );
		if ( ! $accepted_quote_id ) {
			return;
		}

		$mover_id = (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true );
		$mover    = $mover_id ? get_userdata( $mover_id ) : null;
		if ( ! $mover || ! $mover->user_email ) {
			return;
		}

		$job_type  = Go_Deliver_Jobs::get_display_title( (int) $job_id );
		$site_name = get_bloginfo( 'name' );

		$dashboard_page_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
		$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Job Cancelled – Credits Refunded – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$mover->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/job-cancelled.php',
			array(
				'mover_first_name' => $mover->first_name,
				'job_type'         => $job_type,
				'refund_amount'    => (float) $refund_amount,
				'dashboard_url'    => $dashboard_url,
				'site_name'        => $site_name,
				'site_url'         => home_url(),
			)
		);
	}

	/**
	 * Email the customer when a mover submits a new quote on their job.
	 *
	 * @param int $job_id   gd_job post ID.
	 * @param int $quote_id gd_quote post ID.
	 */
	public function notify_customer_new_quote( $job_id, $quote_id ) {
		$customer_id = (int) get_post_meta( (int) $job_id, 'gd_customer_id', true );
		$customer    = get_userdata( $customer_id );
		if ( ! $customer || ! $customer->user_email ) {
			return;
		}

		$mover_id  = (int) get_post_meta( (int) $quote_id, 'gd_mover_id', true );
		$mover     = get_userdata( $mover_id );
		$amount    = (float) get_post_meta( (int) $quote_id, 'gd_amount', true );
		$job_type  = Go_Deliver_Jobs::get_display_title( (int) $job_id );
		$site_name = get_bloginfo( 'name' );

		// Ensure the mover's cached rating and review count are up-to-date
		// before including them in the notification email.
		if ( $mover ) {
			$reviews = new Go_Deliver_Reviews();
			$reviews->recalculate_average( $mover_id );
		}

		// Link to the customer's own dashboard where they can view quotes.
		$dashboard_page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
		$quotes_url        = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'New Quote Received – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$customer->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/quote-submitted.php',
			array(
				'customer_first_name' => $customer->first_name,
				'mover_first_name'    => $mover ? $mover->first_name : __( 'A mover', 'go-deliver' ),
				'mover_rating'        => $mover ? (float) get_user_meta( $mover_id, 'gd_average_rating', true ) : 0.0,
				'mover_review_count'  => $mover ? (int) get_user_meta( $mover_id, 'gd_review_count', true ) : 0,
				'quote_amount'        => $amount,
				'job_type'            => $job_type,
				'quotes_url'          => $quotes_url,
				'site_name'           => $site_name,
				'site_url'            => home_url(),
			)
		);
	}

	/**
	 * Email the mover when their quote is accepted by a customer.
	 *
	 * @param int $quote_id gd_quote post ID.
	 */
	public function notify_mover_quote_accepted( $quote_id ) {
		$mover_id = (int) get_post_meta( (int) $quote_id, 'gd_mover_id', true );
		$mover    = get_userdata( $mover_id );
		if ( ! $mover || ! $mover->user_email ) {
			return;
		}

		$job_id       = (int) get_post_meta( (int) $quote_id, 'gd_job_id', true );
		$quote_amount = (float) get_post_meta( (int) $quote_id, 'gd_amount', true );
		$fee_amount   = (float) get_post_meta( (int) $quote_id, 'gd_fee_amount', true );
		$job_type     = Go_Deliver_Jobs::get_display_title( $job_id );
		$site_name    = get_bloginfo( 'name' );

		$pickup_location  = json_decode( get_post_meta( $job_id, 'gd_pickup_location', true ), true ) ?: array();
		$dropoff_location = json_decode( get_post_meta( $job_id, 'gd_dropoff_location', true ), true ) ?: array();
		$date_requested   = get_post_meta( $job_id, 'gd_date_requested', true );

		$customer_id    = (int) get_post_meta( $job_id, 'gd_customer_id', true );
		$customer       = get_userdata( $customer_id );
		$customer_name  = $customer ? trim( $customer->first_name . ' ' . $customer->last_name ) : '';
		$customer_phone = $customer ? get_user_meta( $customer_id, 'gd_phone', true ) : '';
		$customer_email = $customer ? $customer->user_email : '';

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Quote Accepted – %s', 'go-deliver' ),
			$site_name
		);

		$messaging_page_id = (int) get_option( 'gd_messaging_page_id', 0 );
		$job_url           = $messaging_page_id
			? add_query_arg(
				array(
					'job_id'         => $job_id,
					'participant_id' => $customer_id,
				),
				get_permalink( $messaging_page_id )
			)
			: home_url();

		$this->send_html_email(
			$mover->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/quote-accepted.php',
			array(
				'mover_first_name' => $mover->first_name,
				'job_type'         => $job_type,
				'pickup_full'      => $pickup_location['address'] ?? ( $pickup_location['suburb'] ?? '' ),
				'dropoff_full'     => $dropoff_location['address'] ?? ( $dropoff_location['suburb'] ?? '' ),
				'date_requested'   => $date_requested,
				'customer_name'    => $customer_name,
				'customer_phone'   => $customer_phone,
				'customer_email'   => $customer_email,
				'quote_amount'     => $quote_amount,
				'fee_amount'       => $fee_amount,
				'job_url'          => $job_url,
				'site_name'        => $site_name,
				'site_url'         => home_url(),
			)
		);
	}

	/**
	 * Email the customer when they accept a mover's quote.
	 *
	 * Includes the mover's contact details so both parties can coordinate.
	 *
	 * @param int $quote_id gd_quote post ID.
	 */
	public function notify_customer_quote_accepted( $quote_id ) {
		$job_id       = (int) get_post_meta( (int) $quote_id, 'gd_job_id', true );
		$customer_id  = (int) get_post_meta( $job_id, 'gd_customer_id', true );
		$customer     = get_userdata( $customer_id );
		if ( ! $customer || ! $customer->user_email ) {
			return;
		}

		$mover_id      = (int) get_post_meta( (int) $quote_id, 'gd_mover_id', true );
		$mover         = get_userdata( $mover_id );
		$quote_amount  = (float) get_post_meta( (int) $quote_id, 'gd_amount', true );
		$job_type      = Go_Deliver_Jobs::get_display_title( $job_id );
		$pickup_suburb = get_post_meta( $job_id, 'gd_pickup_suburb', true );
		$date_requested = get_post_meta( $job_id, 'gd_date_requested', true );
		$site_name     = get_bloginfo( 'name' );

		$mover_name = '';
		if ( $mover ) {
			$company_name = get_user_meta( $mover_id, 'gd_company_name', true );
			$person_name  = trim( $mover->first_name . ' ' . $mover->last_name );
			$mover_name   = $company_name ?: ( $person_name ?: $mover->display_name );
		}
		$mover_phone = $mover ? get_user_meta( $mover_id, 'gd_phone', true ) : '';
		$mover_email = $mover ? $mover->user_email : '';

		$dashboard_page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
		$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Your Mover is Confirmed – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$customer->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/job-assigned.php',
			array(
				'customer_first_name' => $customer->first_name ?: $customer->display_name,
				'job_type'            => $job_type,
				'pickup_suburb'       => $pickup_suburb,
				'date_requested'      => $date_requested,
				'mover_name'          => $mover_name,
				'mover_phone'         => $mover_phone,
				'mover_email'         => $mover_email,
				'quote_amount'        => $quote_amount,
				'dashboard_url'       => $dashboard_url,
				'site_name'           => $site_name,
				'site_url'            => home_url(),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Send a single mover their new-job notification email.
	 *
	 * @param int $mover_id Mover user ID.
	 * @param int $job_id   gd_job post ID.
	 */
	private function send_mover_new_job_email( $mover_id, $job_id ) {
		$mover = get_userdata( (int) $mover_id );
		if ( ! $mover || ! $mover->user_email ) {
			return;
		}

		$site_name      = get_bloginfo( 'name' );
		$job_type       = Go_Deliver_Jobs::get_display_title( (int) $job_id );
		$pickup_suburb  = get_post_meta( (int) $job_id, 'gd_pickup_suburb', true );
		$dropoff_suburb = get_post_meta( (int) $job_id, 'gd_dropoff_suburb', true );
		$date_requested = get_post_meta( (int) $job_id, 'gd_date_requested', true );

		// Link movers to the mover dashboard (jobs are viewed via AJAX modal).
		$dashboard_page_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
		$job_url           = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url();

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'New Job Available – %s', 'go-deliver' ),
			$site_name
		);

		$this->send_html_email(
			$mover->user_email,
			$subject,
			GD_PLUGIN_DIR . 'templates/emails/new-job-notification.php',
			array(
				'mover_first_name' => $mover->first_name,
				'job_id'           => (int) $job_id,
				'job_type'         => $job_type,
				'pickup_suburb'    => $pickup_suburb,
				'dropoff_suburb'   => $dropoff_suburb,
				'date_requested'   => $date_requested,
				'job_url'          => $job_url,
				'site_name'        => $site_name,
				'site_url'         => home_url(),
			)
		);
	}

	/**
	 * Flush the pending job-notification queue for movers with a given frequency.
	 *
	 * Each queued job results in one notification email to the mover.
	 * The queue is cleared after processing so jobs are not re-sent.
	 *
	 * @param string $frequency 'hourly' or 'daily'.
	 */
	private function process_pending_job_notifications( $frequency ) {
		$movers = get_users(
			array(
				'role'       => 'gd_mover',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => 'gd_notification_frequency',
						'value' => sanitize_key( $frequency ),
					),
				),
				'fields'     => 'ID',
				'number'     => -1,
			)
		);

		foreach ( $movers as $mover_id ) {
			$pending = get_user_meta( (int) $mover_id, 'gd_pending_job_notifications', true );
			if ( ! is_array( $pending ) || empty( $pending ) ) {
				continue;
			}

			foreach ( $pending as $job_id ) {
				$job_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
				// Only notify if the job is still open or receiving quotes.
				if ( in_array( $job_status, array( 'open', 'locked' ), true ) ) {
					$this->send_mover_new_job_email( (int) $mover_id, (int) $job_id );
				}
			}

			// Clear the queue regardless of job status.
			delete_user_meta( (int) $mover_id, 'gd_pending_job_notifications' );
		}
	}

	/**
	 * Send a plain-text email using the configured From name and address.
	 *
	 * @param string $to      Recipient email address.
	 * @param string $subject Email subject line.
	 * @param string $message Plain-text message body.
	 */
	private static function send_plain_email( $to, $subject, $message ) {
		if ( ! is_email( $to ) ) {
			return;
		}

		$site_name    = get_bloginfo( 'name' );
		$from_name    = get_option( 'gd_email_from_name', $site_name );
		$from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );
		$headers      = array(
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send an HTML email using a PHP template file.
	 *
	 * Template variables are extracted from $vars into the template scope.
	 * Standard site from-name and from-address settings are applied.
	 *
	 * @param string $to            Recipient email address.
	 * @param string $subject       Email subject line.
	 * @param string $template_path Absolute path to the PHP email template.
	 * @param array  $vars          Variables to expose inside the template.
	 */
	private function send_html_email( $to, $subject, $template_path, $vars = array() ) {
		if ( ! is_email( $to ) || ! file_exists( $template_path ) ) {
			return;
		}

		$from_name    = get_option( 'gd_email_from_name', get_bloginfo( 'name' ) );
		$from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		// Render the template.
		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional for template rendering, keys are caller-controlled.
		extract( $vars, EXTR_SKIP );
		include $template_path;
		$html = ob_get_clean();

		wp_mail( $to, wp_strip_all_tags( $subject ), $html, $headers );
	}

	/**
	 * Send a welcome email to a newly added team member (sub-user).
	 *
	 * Called by Go_Deliver_Sub_Users::add_sub_user() after the WP user
	 * account has been created so the new member receives their credentials
	 * and a link to the mover dashboard.
	 *
	 * A password-reset link is generated so the member can set their own
	 * password securely — the plaintext password is never sent in the email.
	 *
	 * @param int $sub_user_id      WordPress user ID of the new team member.
	 * @param int $parent_mover_id  WordPress user ID of the mover who added them.
	 */
	public static function notify_team_member_added( $sub_user_id, $parent_mover_id ) {
		$member = get_userdata( (int) $sub_user_id );
		$mover  = get_userdata( (int) $parent_mover_id );

		if ( ! $member || ! $member->user_email ) {
			return;
		}

		$site_name     = get_bloginfo( 'name' );
		$site_url      = home_url();
		$team_name     = $mover ? trim( $mover->first_name . ' ' . $mover->last_name ) : '';
		if ( ! $team_name && $mover ) {
			$team_name = $mover->display_name;
		}

		$first_name    = trim( $member->first_name );
		$dashboard_url = get_option( 'gd_mover_dashboard_url', home_url() );

		// Generate a secure one-time password-reset link.
		$reset_key  = get_password_reset_key( $member );
		$reset_url  = $reset_key instanceof WP_Error ? wp_login_url() : add_query_arg(
			array(
				'action' => 'rp',
				'key'    => rawurlencode( $reset_key ),
				'login'  => rawurlencode( $member->user_login ),
			),
			wp_login_url()
		);

		$from_name    = get_option( 'gd_email_from_name', $site_name );
		$from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		/* translators: %s: site name */
		$subject       = sprintf( __( "You've been added to a team on %s", 'go-deliver' ), $site_name );
		$template_path = GD_PLUGIN_DIR . 'templates/emails/team-member-added.php';

		if ( ! file_exists( $template_path ) ) {
			return;
		}

		$vars = array(
			'member_first_name' => $first_name ?: $member->user_login,
			'member_username'   => $member->user_login,
			'reset_url'         => $reset_url,
			'team_name'         => $team_name,
			'login_url'         => wp_login_url( $dashboard_url ),
			'dashboard_url'     => $dashboard_url,
			'site_name'         => $site_name,
			'site_url'          => $site_url,
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional for template rendering, keys are caller-controlled.
		extract( $vars, EXTR_SKIP );
		include $template_path;
		$html = ob_get_clean();

		wp_mail( $member->user_email, wp_strip_all_tags( $subject ), $html, $headers );
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
