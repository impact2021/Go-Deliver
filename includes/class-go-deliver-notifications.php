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

		$sender   = get_userdata( (int) $sender_id );
		$job_type = get_post_meta( (int) $job_id, 'gd_job_type', true ) ?: __( 'Moving Job', 'go-deliver' );

		$messaging_page_id = (int) get_option( 'gd_messaging_page_id', 0 );
		$conversation_url  = $messaging_page_id
			? add_query_arg( 'job_id', (int) $job_id, get_permalink( $messaging_page_id ) )
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
		$job_type          = get_post_meta( (int) $job_id, 'gd_job_type', true ) ?: __( 'Moving Job', 'go-deliver' );
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
		$job_type  = get_post_meta( (int) $job_id, 'gd_job_type', true ) ?: __( 'Moving Job', 'go-deliver' );
		$site_name = get_bloginfo( 'name' );

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
		$job_type     = get_post_meta( $job_id, 'gd_job_type', true ) ?: __( 'Moving Job', 'go-deliver' );
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
			? add_query_arg( 'job_id', $job_id, get_permalink( $messaging_page_id ) )
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

		$site_name     = get_bloginfo( 'name' );
		$job_type      = get_post_meta( (int) $job_id, 'gd_job_type', true ) ?: __( 'Moving Job', 'go-deliver' );
		$pickup_suburb = get_post_meta( (int) $job_id, 'gd_pickup_suburb', true );
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
		$from_address = get_option( 'gd_email_from_address', get_option( 'admin_email' ) );

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
