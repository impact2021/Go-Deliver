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
add_action( 'wp_ajax_gd_send_message', array( $this, 'ajax_send_message' ) );
add_action( 'wp_ajax_gd_get_messages', array( $this, 'ajax_get_messages' ) );
}

// =========================================================================
// Permission check.
// =========================================================================

/**
 * Determine whether a user is allowed to send/read messages for a job.
 *
 * The job must have received at least one quote, and the user must be
 * either the customer who owns the job or a mover who submitted a quote.
 *
 * @param int $job_id  Job post ID.
 * @param int $user_id WordPress user ID.
 * @return bool
 */
public function can_message( $job_id, $user_id ) {
$job_id  = (int) $job_id;
$user_id = (int) $user_id;

if ( ! $job_id || ! $user_id ) {
return false;
}

$job_status = get_post_meta( $job_id, 'gd_job_status', true );

// Must have at least one quote (status != 'open').
if ( 'open' === $job_status ) {
return false;
}

// Check if user is the customer.
$customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );
if ( $customer_id === $user_id ) {
return true;
}

// Check if user has a quote on this job.
$query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => 1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_job_id',
'value' => $job_id,
'type'  => 'NUMERIC',
),
array(
'key'   => 'gd_mover_id',
'value' => $user_id,
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

// =========================================================================
// Core messaging.
// =========================================================================

/**
 * Send a message for a job.
 *
 * @param int    $job_id    Job post ID.
 * @param int    $sender_id Sender user ID.
 * @param string $message   Raw message text.
 * @return int|WP_Error Inserted message ID or WP_Error.
 */
public function send_message( $job_id, $sender_id, $message ) {
$job_id    = (int) $job_id;
$sender_id = (int) $sender_id;

if ( ! $this->can_message( $job_id, $sender_id ) ) {
return new WP_Error( 'permission_denied', __( 'You are not allowed to message on this job.', 'go-deliver' ) );
}

$message = sanitize_textarea_field( wp_unslash( $message ) );
$message = $this->contact_filter( $message );

if ( empty( $message ) ) {
return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'go-deliver' ) );
}

$receiver_id = $this->determine_receiver( $job_id, $sender_id );
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
private function determine_receiver( $job_id, $sender_id ) {
$customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );

if ( $sender_id === $customer_id ) {
// Sender is customer: find mover to reply to.
// Prefer the accepted quote mover.
$accepted_query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => 1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_job_id',
'value' => $job_id,
'type'  => 'NUMERIC',
),
array(
'key'   => 'gd_status',
'value' => 'accepted',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

if ( ! empty( $accepted_query->posts ) ) {
$mover_id = (int) get_post_meta( $accepted_query->posts[0], 'gd_mover_id', true );
wp_reset_postdata();
return $mover_id;
}

// Fall back to first quote mover.
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
'value' => $job_id,
'type'  => 'NUMERIC',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

if ( empty( $first_query->posts ) ) {
wp_reset_postdata();
return new WP_Error( 'no_mover', __( 'No mover found for this job.', 'go-deliver' ) );
}

$mover_id = (int) get_post_meta( $first_query->posts[0], 'gd_mover_id', true );
wp_reset_postdata();
return $mover_id;
}

// Sender is mover: receiver is customer.
return $customer_id;
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

// Remove email addresses.
$message = preg_replace(
'/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
'[email removed]',
$message
);

// Remove URLs (http, https, www).
$message = preg_replace(
'/(https?:\/\/|www\.)[^\s]+/',
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
public function get_messages( $job_id, $user_id ) {
if ( ! $this->can_message( (int) $job_id, (int) $user_id ) ) {
return new WP_Error( 'permission_denied', __( 'You are not allowed to view messages for this job.', 'go-deliver' ) );
}

return Go_Deliver_DB::get_messages( (int) $job_id, (int) $user_id );
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

$job_id  = absint( $_POST['job_id'] ?? 0 );
$message = isset( $_POST['message'] ) ? wp_unslash( $_POST['message'] ) : '';

if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

if ( empty( trim( $message ) ) ) {
wp_send_json_error( array( 'message' => __( 'Message cannot be empty.', 'go-deliver' ) ) );
}

$result = $this->send_message( $job_id, get_current_user_id(), $message );

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

$job_id = absint( $_POST['job_id'] ?? $_GET['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$messages = $this->get_messages( $job_id, get_current_user_id() );

if ( is_wp_error( $messages ) ) {
wp_send_json_error( array( 'message' => $messages->get_error_message() ) );
}

wp_send_json_success( $messages );
}
}
