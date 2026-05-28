<?php
/**
 * In-app messaging between customers and movers for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Messaging
 */
class Go_Deliver_Messaging {

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_send_message',      array( $this, 'ajax_send_message' ) );
add_action( 'wp_ajax_gd_get_messages',      array( $this, 'ajax_get_messages' ) );
add_action( 'wp_ajax_gd_get_conversations', array( $this, 'ajax_get_conversations' ) );
}

// =========================================================================
// Permission check.
// =========================================================================

/**
 * Determine whether a user is allowed to send/read messages for a job.
 *
 * @param int $job_id        Job post ID.
 * @param int $user_id       WordPress user ID.
 * @param int $other_user_id Optional conversation participant ID.
 * @return bool
 */
public function can_message( $job_id, $user_id, $other_user_id = 0 ) {
	return (bool) $this->resolve_conversation_partner( $job_id, $user_id, $other_user_id );
}

/**
 * Resolve the other participant for a job conversation.
 *
 * @param int $job_id        Job post ID.
 * @param int $user_id       Current user ID.
 * @param int $other_user_id Optional preferred participant ID.
 * @return int Resolved participant ID, or 0 when no conversation is allowed.
 */
public function resolve_conversation_partner( $job_id, $user_id, $other_user_id = 0 ) {
	$job_id         = (int) $job_id;
	$user_id        = (int) $user_id;
	$other_user_id  = (int) $other_user_id;
	$customer_id    = (int) get_post_meta( $job_id, 'gd_customer_id', true );
	$job_status     = get_post_meta( $job_id, 'gd_job_status', true );
	$is_customer    = ( $customer_id === $user_id );
	$accepted_quote = (int) get_post_meta( $job_id, 'gd_accepted_quote_id', true );
	$accepted_mover = $accepted_quote ? (int) get_post_meta( $accepted_quote, 'gd_mover_id', true ) : 0;

	$job_post = get_post( $job_id );
	if ( ! $job_id || ! $user_id || ! $customer_id || ! $job_post || 'gd_job' !== $job_post->post_type ) {
		return 0;
	}

	if ( $is_customer ) {
		if ( $other_user_id && $other_user_id !== $user_id && $this->is_valid_customer_counterparty( $job_id, $customer_id, $other_user_id ) ) {
			return $other_user_id;
		}

		if ( $accepted_mover ) {
			return $accepted_mover;
		}

		$latest_partner = $this->get_latest_conversation_partner( $job_id, $user_id );
		if ( $latest_partner ) {
			return $latest_partner;
		}

		return $this->get_first_quote_mover( $job_id );
	}

	if ( $other_user_id && $other_user_id !== $customer_id ) {
		return 0;
	}

	if ( $this->user_has_quote( $job_id, $user_id ) ) {
		return $customer_id;
	}

	if ( in_array( $job_status, array( 'open', 'locked' ), true ) && $this->can_start_prequote_conversation( $job_id, $user_id ) ) {
		return $customer_id;
	}

	if ( $this->has_existing_conversation( $job_id, $user_id, $customer_id ) ) {
		return $customer_id;
	}

	return 0;
}

/**
 * Check whether a mover already has a quote on a job.
 *
 * @param int $job_id   Job post ID.
 * @param int $mover_id Mover user ID.
 * @return bool
 */
private function user_has_quote( $job_id, $mover_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'gd_quote',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'gd_job_id',
					'value' => (int) $job_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'   => 'gd_mover_id',
					'value' => (int) $mover_id,
					'type'  => 'NUMERIC',
				),
			),
			'no_found_rows'  => false,
			'fields'         => 'ids',
		)
	);

	$has_quote = $query->found_posts > 0;
	wp_reset_postdata();

	return $has_quote;
}

/**
 * Determine whether a mover can start a pre-quote conversation on a job.
 *
 * @param int $job_id   Job post ID.
 * @param int $mover_id Mover user ID.
 * @return bool
 */
private function can_start_prequote_conversation( $job_id, $mover_id ) {
	$mover = get_userdata( (int) $mover_id );
	if ( ! $mover ) {
		return false;
	}

	$roles = (array) $mover->roles;

	if ( ! in_array( 'gd_mover', $roles, true ) && ! in_array( 'gd_mover_sub', $roles, true ) ) {
		return false;
	}

	if ( 'approved' !== get_user_meta( (int) $mover_id, 'gd_mover_status', true ) ) {
		return false;
	}

	$excluded = get_post_meta( (int) $job_id, 'gd_excluded_mover_ids', true );
	$excluded = is_array( $excluded ) ? array_map( 'intval', $excluded ) : array();
	if ( in_array( (int) $mover_id, $excluded, true ) ) {
		return false;
	}

	$jobs_handler = new Go_Deliver_Jobs();
	$available    = $jobs_handler->get_open_jobs_for_mover( (int) $mover_id );

	foreach ( $available as $job ) {
		if ( (int) ( $job['id'] ?? 0 ) === (int) $job_id ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether the selected customer thread participant is valid.
 *
 * @param int $job_id        Job post ID.
 * @param int $customer_id   Customer user ID.
 * @param int $other_user_id Other participant ID.
 * @return bool
 */
private function is_valid_customer_counterparty( $job_id, $customer_id, $other_user_id ) {
	if ( $other_user_id === $customer_id ) {
		return false;
	}

	return $this->has_existing_conversation( $job_id, $customer_id, $other_user_id )
		|| $this->user_has_quote( $job_id, $other_user_id );
}

/**
 * Return true when two users already have messages on a job.
 *
 * @param int $job_id        Job post ID.
 * @param int $user_id       First user ID.
 * @param int $other_user_id Second user ID.
 * @return bool
 */
private function has_existing_conversation( $job_id, $user_id, $other_user_id ) {
	global $wpdb;

	$message_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM `{$wpdb->prefix}gd_messages`
			 WHERE job_id = %d
			   AND (
					( sender_id = %d AND receiver_id = %d )
					OR
					( sender_id = %d AND receiver_id = %d )
			   )
			 LIMIT 1",
			(int) $job_id,
			(int) $user_id,
			(int) $other_user_id,
			(int) $other_user_id,
			(int) $user_id
		)
	);

	return ! empty( $message_id );
}

/**
 * Find the latest conversation partner for a user's job thread.
 *
 * @param int $job_id  Job post ID.
 * @param int $user_id User ID.
 * @return int
 */
private function get_latest_conversation_partner( $job_id, $user_id ) {
	global $wpdb;

	$partner_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT CASE
					WHEN sender_id = %d THEN receiver_id
					ELSE sender_id
				END AS other_user_id
			 FROM `{$wpdb->prefix}gd_messages`
			 WHERE job_id = %d
			   AND ( sender_id = %d OR receiver_id = %d )
			 ORDER BY created_at DESC, id DESC
			 LIMIT 1",
			(int) $user_id,
			(int) $job_id,
			(int) $user_id,
			(int) $user_id
		)
	);

	return (int) $partner_id;
}

/**
 * Get the mover on the earliest quote for a job.
 *
 * @param int $job_id Job post ID.
 * @return int
 */
private function get_first_quote_mover( $job_id ) {
	$first_query = new WP_Query(
		array(
			'post_type'      => 'gd_quote',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'   => 'gd_job_id',
					'value' => (int) $job_id,
					'type'  => 'NUMERIC',
				),
			),
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);

	$mover_id = 0;
	if ( ! empty( $first_query->posts ) ) {
		$mover_id = (int) get_post_meta( $first_query->posts[0], 'gd_mover_id', true );
	}
	wp_reset_postdata();

	return $mover_id;
}

// =========================================================================
// Core messaging.
// =========================================================================

/**
 * Determine whether the accepted quote exists for a job.
 *
 * @param int $job_id Job post ID.
 * @return bool True if a quote has been accepted.
 */
public function is_quote_accepted( $job_id ) {
return (bool) get_post_meta( (int) $job_id, 'gd_accepted_quote_id', true );
}

/**
 * Return true if the message text contains contact details
 * (phone numbers, email addresses, or URLs).
 *
 * Uses the same patterns as contact_filter() so the two methods
 * stay in sync.
 *
 * @param string $message Message text.
 * @return bool
 */
public function has_contact_details( $message ) {
if ( preg_match( '/(?:\+?\d[\d\s\-().]{7,}\d)/', $message ) ) {
return true;
}
if ( preg_match( '/(?:\+?\s*\d(?:[\s\-().]*\d){6,})/', $message ) ) {
return true;
}
if ( preg_match( '/\b(?:zero|oh|one|two|three|four|five|six|seven|eight|nine)\b(?:[\s,.\-]+\b(?:zero|oh|one|two|three|four|five|six|seven|eight|nine)\b){6,}/i', $message ) ) {
return true;
}
	if ( false !== strpos( $message, '@' ) ) {
		return true;
	}
	if ( preg_match( '/\b[a-z0-9._%+\-]+(?:\s*[\(\[\{]?\s*at\s*[\)\]\}]?\s*|\s+at\s+)[a-z0-9\-]+(?:\s*(?:\.|[\(\[\{]?\s*dot\s*[\)\]\}]?)\s*[a-z0-9\-]+)+\b/i', $message ) ) {
		return true;
	}
if ( preg_match( '/(https?:\/\/|www\.)[^\s]+/', $message ) ) {
return true;
}
if ( preg_match( '/\b(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i', $message ) ) {
return true;
}
return false;
}

/**
 * Send a message for a job.
 *
 * @param int    $job_id    Job post ID.
 * @param int    $sender_id Sender user ID.
 * @param string $message   Raw message text.
 * @return int|WP_Error Inserted message ID or WP_Error.
 */
public function send_message( $job_id, $sender_id, $message, $other_user_id = 0 ) {
$job_id    = (int) $job_id;
$sender_id = (int) $sender_id;

if ( ! $this->can_message( $job_id, $sender_id, $other_user_id ) ) {
return new WP_Error( 'permission_denied', __( 'You are not allowed to message on this job.', 'go-deliver' ) );
}

$message        = sanitize_textarea_field( wp_unslash( $message ) );
$quote_accepted = $this->is_quote_accepted( $job_id );

// Before acceptance: block messages that contain contact details.
	if ( ! $quote_accepted && $this->has_contact_details( $message ) ) {
		return new WP_Error(
			'contact_details_blocked',
			__( 'Contact details cannot be shared before a quote has been accepted. Please remove any phone numbers, email addresses, @ symbols, or links.', 'go-deliver' )
		);
	}

// Before acceptance: apply the contact filter as a safety net for any
// patterns not caught above. After acceptance, contact details are allowed.
if ( ! $quote_accepted ) {
$message = $this->contact_filter( $message );
}

if ( empty( $message ) ) {
return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'go-deliver' ) );
}

$receiver_id = $this->determine_receiver( $job_id, $sender_id, $other_user_id );
if ( is_wp_error( $receiver_id ) ) {
return $receiver_id;
}

$message_id = Go_Deliver_DB::save_message( $job_id, $sender_id, $receiver_id, $message );

if ( ! $message_id ) {
return new WP_Error( 'db_error', __( 'Could not save message.', 'go-deliver' ) );
}

// Notify receiver.
$notifications = new Go_Deliver_Notifications();
if ( method_exists( $notifications, 'notify_new_message' ) ) {
$notifications->notify_new_message( $job_id, $sender_id, $receiver_id, $message_id );
}

return $message_id;
}

/**
 * Determine the receiver for a message based on who is sending it.
 *
 * If the sender is the customer, the receiver is the mover with the
 * accepted quote (or the first pending-quote mover if no accepted quote).
 * If the sender is a mover, the receiver is the job's customer.
 *
 * @param int $job_id    Job post ID.
 * @param int $sender_id Sender user ID.
 * @return int|WP_Error Receiver user ID or WP_Error.
 */
private function determine_receiver( $job_id, $sender_id, $other_user_id = 0 ) {
	$receiver_id = $this->resolve_conversation_partner( $job_id, $sender_id, $other_user_id );

	if ( $receiver_id ) {
		return $receiver_id;
	}

	return new WP_Error( 'no_receiver', __( 'No recipient found for this conversation.', 'go-deliver' ) );
}

/**
 * Filter contact details (phone numbers, email addresses, URLs) from a message.
 *
 * @param string $message Raw message.
 * @return string Filtered message.
 */
public function contact_filter( $message ) {
// Remove phone numbers (various formats including New Zealand).
$message = preg_replace(
'/(?:\+?\d[\d\s\-().]{7,}\d)/',
'[phone removed]',
$message
);

// Remove spaced-out digit numbers, e.g. 0 2 1 4 5 6 7 8.
$message = preg_replace(
'/(?:\+?\s*\d(?:[\s\-().]*\d){6,})/',
'[phone removed]',
$message
);

// Remove phone numbers written as words, e.g. one two three...
$message = preg_replace(
'/\b(?:zero|oh|one|two|three|four|five|six|seven|eight|nine)\b(?:[\s,.\-]+\b(?:zero|oh|one|two|three|four|five|six|seven|eight|nine)\b){6,}/i',
'[phone removed]',
$message
);

// Remove email addresses.
$message = preg_replace(
'/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
'[email removed]',
$message
);

	// Remove obfuscated email addresses, e.g. name(at)domain(dot)com.
	$message = preg_replace(
		'/\b[a-z0-9._%+\-]+(?:\s*[\(\[\{]?\s*at\s*[\)\]\}]?\s*|\s+at\s+)[a-z0-9\-]+(?:\s*(?:\.|[\(\[\{]?\s*dot\s*[\)\]\}]?)\s*[a-z0-9\-]+)+\b/i',
		'[email removed]',
		$message
	);

	// Remove @ symbols before quote acceptance.
	$message = str_replace( '@', '[email removed]', $message );

// Remove URLs (http, https, www).
$message = preg_replace(
'/(https?:\/\/|www\.)[^\s]+/',
'[link removed]',
$message
);

// Remove bare domain names, e.g. example.co.nz.
$message = preg_replace(
'/\b(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i',
'[link removed]',
$message
);

return $message;
}

/**
 * Get all messages for a job (permission-checked).
 *
 * @param int $job_id  Job post ID.
 * @param int $user_id Requesting user ID.
 * @return array|WP_Error Message rows or WP_Error.
 */
public function get_messages( $job_id, $user_id, $other_user_id = 0 ) {
$resolved_partner = $this->resolve_conversation_partner( (int) $job_id, (int) $user_id, (int) $other_user_id );

if ( ! $resolved_partner ) {
return new WP_Error( 'permission_denied', __( 'You are not allowed to view messages for this job.', 'go-deliver' ) );
}

return Go_Deliver_DB::get_messages( (int) $job_id, (int) $user_id, $resolved_partner );
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: send a message.
 */
public function ajax_send_message() {
check_ajax_referer( 'gd_messaging', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to send messages.', 'go-deliver' ) ), 403 );
}

$job_id         = absint( $_POST['job_id'] ?? 0 );
$other_user_id  = absint( $_POST['participant_id'] ?? 0 );
$message        = isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';

if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

if ( empty( trim( $message ) ) ) {
wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'go-deliver' ) ) );
}

$result = $this->send_message( $job_id, get_current_user_id(), $message, $other_user_id );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message_id' => $result ) );
}

/**
 * AJAX: retrieve messages for a job.
 */
public function ajax_get_messages() {
check_ajax_referer( 'gd_messaging', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to view messages.', 'go-deliver' ) ), 403 );
}

$job_id        = absint( $_POST['job_id'] ?? $_GET['job_id'] ?? 0 );
$other_user_id = absint( $_POST['participant_id'] ?? $_GET['participant_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$current_user_id = get_current_user_id();
$resolved_partner = $this->resolve_conversation_partner( $job_id, $current_user_id, $other_user_id );
$messages         = $this->get_messages( $job_id, $current_user_id, $resolved_partner );

if ( is_wp_error( $messages ) ) {
wp_send_json_error( array( 'message' => $messages->get_error_message() ) );
}

// Mark all messages addressed to this user for this job as read so the
// hourly cron does not keep re-sending "unread messages" emails for
// conversations the user has already viewed.
Go_Deliver_DB::mark_messages_read( $job_id, $current_user_id, $resolved_partner );

// Clear the cron "already notified" flag so a future unread message
// can trigger a fresh notification.
delete_user_meta( $current_user_id, 'gd_unread_msg_cron_notified' );

// Clear the per-message notification flag so the next incoming message will
// trigger a fresh email notification to this user.
delete_user_meta( $current_user_id, 'gd_msg_notified_' . $job_id . '_' . $resolved_partner );

wp_send_json_success( $messages );
}

/**
 * AJAX: retrieve all conversation threads for the current user.
 */
public function ajax_get_conversations() {
check_ajax_referer( 'gd_messaging', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to view messages.', 'go-deliver' ) ), 403 );
}

$conversations = Go_Deliver_DB::get_conversations_for_user( get_current_user_id() );
wp_send_json_success( $conversations );
}
}
