<?php
/**
 * Database abstraction layer for Go Deliver.
 *
 * Provides static helper methods for all custom-table and user-meta
 * operations, keeping SQL out of the rest of the codebase.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_DB
 *
 * All methods are static so callers never need to instantiate the class.
 */
class Go_Deliver_DB {

	// =========================================================================
	// Wallet helpers.
	// =========================================================================

	/**
	 * Return the current wallet balance for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return float Balance in the site currency.
	 */
	public static function get_wallet_balance( $user_id ) {
		$balance = get_user_meta( (int) $user_id, 'gd_wallet_balance', true );
		return $balance !== '' ? (float) $balance : 0.0;
	}

	/**
	 * Overwrite the stored wallet balance for a user.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param float $amount  New balance value.
	 * @return bool|int Result of update_user_meta().
	 */
	public static function update_wallet_balance( $user_id, $amount ) {
		return update_user_meta( (int) $user_id, 'gd_wallet_balance', (float) $amount );
	}

	/**
	 * Insert a wallet transaction record.
	 *
	 * @param int    $user_id          WordPress user ID.
	 * @param string $type             Transaction type (e.g. 'topup', 'payment', 'refund').
	 * @param float  $amount           Monetary amount (positive or negative).
	 * @param string $description      Human-readable description.
	 * @param int    $job_id           Related job post ID (optional).
	 * @param int    $quote_id         Related quote post ID (optional).
	 * @param string $stripe_payment_id Stripe PaymentIntent / Charge ID (optional).
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function log_transaction( $user_id, $type, $amount, $description, $job_id = 0, $quote_id = 0, $stripe_payment_id = '' ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gd_wallet_transactions',
			array(
				'user_id'           => (int) $user_id,
				'type'              => sanitize_text_field( $type ),
				'amount'            => (float) $amount,
				'description'       => sanitize_textarea_field( $description ),
				'job_id'            => (int) $job_id,
				'quote_id'          => (int) $quote_id,
				'stripe_payment_id' => sanitize_text_field( $stripe_payment_id ),
			),
			array( '%d', '%s', '%f', '%s', '%d', '%d', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve recent transactions for a user.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $limit   Maximum number of rows to return.
	 * @return array Array of transaction row objects.
	 */
	public static function get_transactions( $user_id, $limit = 20 ) {
		global $wpdb;

		$limit = absint( $limit );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is safe; limit is absint().
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
				"SELECT * FROM `{$wpdb->prefix}gd_wallet_transactions`
				 WHERE user_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d",
				(int) $user_id,
				$limit
			)
		);
	}

	// =========================================================================
	// Messaging helpers.
	// =========================================================================

	/**
	 * Persist a new message between two users on a job thread.
	 *
	 * @param int    $job_id      ID of the related gd_job post.
	 * @param int    $sender_id   User ID of the message author.
	 * @param int    $receiver_id User ID of the intended recipient.
	 * @param string $message     Raw message text.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function save_message( $job_id, $sender_id, $receiver_id, $message ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gd_messages',
			array(
				'job_id'      => (int) $job_id,
				'sender_id'   => (int) $sender_id,
				'receiver_id' => (int) $receiver_id,
				'message'     => sanitize_textarea_field( $message ),
			),
			array( '%d', '%d', '%d', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve the message thread for a job, filtered so only participants
	 * can see the messages (privacy fence).
	 *
	 * @param int $job_id  ID of the related gd_job post.
	 * @param int $user_id ID of the requesting user.
	 * @return array Array of message row objects.
	 */
	public static function get_messages( $job_id, $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}gd_messages`
				 WHERE job_id = %d
				   AND ( sender_id = %d OR receiver_id = %d )
				 ORDER BY created_at ASC",
				(int) $job_id,
				(int) $user_id,
				(int) $user_id
			)
		);
	}

	// =========================================================================
	// Notification helpers.
	// =========================================================================

	/**
	 * Insert a notification for a user.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $type     Machine-readable notification type.
	 * @param string $subject  Short subject line.
	 * @param string $message  Full notification body.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function save_notification( $user_id, $type, $subject, $message ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gd_notifications',
			array(
				'user_id' => (int) $user_id,
				'type'    => sanitize_text_field( $type ),
				'subject' => sanitize_text_field( $subject ),
				'message' => wp_kses_post( $message ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve notifications for a user.
	 *
	 * @param int  $user_id    WordPress user ID.
	 * @param bool $unread_only When true, only unread notifications are returned.
	 * @return array Array of notification row objects.
	 */
	public static function get_notifications( $user_id, $unread_only = false ) {
		global $wpdb;

		$where_read = $unread_only ? 'AND is_read = 0' : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_read is safe, constructed locally.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}gd_notifications`
				 WHERE user_id = %d {$where_read}
				 ORDER BY created_at DESC",
				(int) $user_id
			)
		);
	}

	/**
	 * Mark a single notification as read.
	 *
	 * Ownership check (user_id) prevents one user marking another's notification.
	 *
	 * @param int $id      Row ID of the notification.
	 * @param int $user_id ID of the requesting user (ownership check).
	 * @return int|false Number of rows updated or false on failure.
	 */
	public static function mark_notification_read( $id, $user_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'gd_notifications',
			array( 'is_read' => 1 ),
			array(
				'id'      => (int) $id,
				'user_id' => (int) $user_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	// =========================================================================
	// Mover document helpers.
	// =========================================================================

	/**
	 * Store a newly uploaded mover document record.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $doc_type Document category (e.g. 'insurance', 'licence').
	 * @param string $file_url Absolute URL of the uploaded file.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function save_document( $user_id, $doc_type, $file_url ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gd_mover_documents',
			array(
				'user_id'  => (int) $user_id,
				'doc_type' => sanitize_text_field( $doc_type ),
				'file_url' => esc_url_raw( $file_url ),
				'status'   => 'pending',
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve all documents for a mover.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array Array of document row objects.
	 */
	public static function get_documents( $user_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}gd_mover_documents`
				 WHERE user_id = %d
				 ORDER BY uploaded_at DESC",
				(int) $user_id
			)
		);
	}

	/**
	 * Update the status of a document (e.g. 'pending' → 'approved' / 'rejected').
	 *
	 * @param int    $id     Row ID of the document.
	 * @param string $status New status string.
	 * @return int|false Number of rows updated or false on failure.
	 */
	public static function update_document_status( $id, $status ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'gd_mover_documents',
			array( 'status' => sanitize_text_field( $status ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// =========================================================================
	// Sub-user helpers.
	// =========================================================================

	/**
	 * Retrieve all sub-users belonging to a parent mover.
	 *
	 * @param int $parent_mover_id User ID of the parent mover account.
	 * @return array Array of sub-user row objects.
	 */
	public static function get_sub_users( $parent_mover_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}gd_sub_users`
				 WHERE parent_mover_id = %d
				 ORDER BY created_at ASC",
				(int) $parent_mover_id
			)
		);
	}

	/**
	 * Link a user as a sub-user under a parent mover.
	 *
	 * @param int $parent_mover_id     User ID of the parent mover account.
	 * @param int $user_id             User ID of the sub-user.
	 * @param int $can_view_financials 1 to grant financial visibility, 0 to deny.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function add_sub_user( $parent_mover_id, $user_id, $can_view_financials = 0 ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'gd_sub_users',
			array(
				'parent_mover_id'    => (int) $parent_mover_id,
				'user_id'            => (int) $user_id,
				'can_view_financials' => (int) (bool) $can_view_financials,
			),
			array( '%d', '%d', '%d' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Remove a sub-user from a parent mover's account.
	 *
	 * @param int $parent_mover_id User ID of the parent mover account.
	 * @param int $user_id         User ID of the sub-user to remove.
	 * @return int|false Number of rows deleted or false on failure.
	 */
	public static function remove_sub_user( $parent_mover_id, $user_id ) {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'gd_sub_users',
			array(
				'parent_mover_id' => (int) $parent_mover_id,
				'user_id'         => (int) $user_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Return the number of sub-users registered under a parent mover.
	 *
	 * @param int $parent_mover_id User ID of the parent mover account.
	 * @return int Sub-user count.
	 */
	public static function count_sub_users( $parent_mover_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$wpdb->prefix}gd_sub_users`
				 WHERE parent_mover_id = %d",
				(int) $parent_mover_id
			)
		);
	}
}
