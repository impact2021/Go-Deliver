<?php
/**
 * Review management for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Reviews
 */
class Go_Deliver_Reviews {

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_submit_review', array( $this, 'ajax_submit_review' ) );
}

// =========================================================================
// Core review methods.
// =========================================================================

/**
 * Submit a review for a completed job.
 *
 * @param int    $job_id      Job post ID.
 * @param int    $customer_id Customer user ID.
 * @param int    $mover_id    Mover user ID.
 * @param int    $rating      Rating 1–5.
 * @param string $comment     Review comment.
 * @return true|WP_Error
 */
public function submit_review( $job_id, $customer_id, $mover_id, $rating, $comment ) {
$job_id      = (int) $job_id;
$customer_id = (int) $customer_id;
$mover_id    = (int) $mover_id;
$rating      = (int) $rating;

// Validate job.
$post = get_post( $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

// Job must be accepted/completed.
$job_status = get_post_meta( $job_id, 'gd_job_status', true );
if ( 'accepted' !== $job_status ) {
return new WP_Error( 'job_not_completed', __( 'Reviews can only be submitted for accepted jobs.', 'go-deliver' ) );
}

// Ensure customer owns the job.
$stored_customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );
if ( $stored_customer_id !== $customer_id ) {
return new WP_Error( 'permission_denied', __( 'You do not own this job.', 'go-deliver' ) );
}

// One review per job.
if ( get_post_meta( $job_id, 'gd_review_submitted', true ) ) {
return new WP_Error( 'review_exists', __( 'A review has already been submitted for this job.', 'go-deliver' ) );
}

// Validate rating.
if ( $rating < 1 || $rating > 5 ) {
return new WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'go-deliver' ) );
}

// Save review as job post meta.
update_post_meta( $job_id, 'gd_review_rating',    $rating );
update_post_meta( $job_id, 'gd_review_comment',   wp_kses_post( $comment ) );
update_post_meta( $job_id, 'gd_review_mover_id',  $mover_id );
update_post_meta( $job_id, 'gd_review_submitted', 1 );
update_post_meta( $job_id, 'gd_review_date',      current_time( 'mysql' ) );

// Update mover's average rating.
$this->recalculate_average( $mover_id );

return true;
}

/**
 * Get the review data stored on a job.
 *
 * @param int $job_id Job post ID.
 * @return array|null Review array or null if no review submitted.
 */
public function get_review( $job_id ) {
$job_id = (int) $job_id;

if ( ! get_post_meta( $job_id, 'gd_review_submitted', true ) ) {
return null;
}

return array(
'job_id'   => $job_id,
'mover_id' => (int) get_post_meta( $job_id, 'gd_review_mover_id', true ),
'rating'   => (int) get_post_meta( $job_id, 'gd_review_rating', true ),
'comment'  => get_post_meta( $job_id, 'gd_review_comment', true ),
'date'     => get_post_meta( $job_id, 'gd_review_date', true ),
);
}

/**
 * Get reviews written about a mover.
 *
 * @param int $mover_id Mover user ID.
 * @param int $limit    Maximum number of reviews.
 * @return array Array of review data arrays.
 */
public function get_mover_reviews( $mover_id, $limit = 10 ) {
$mover_id = (int) $mover_id;

$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => $limit,
'orderby'        => 'meta_value',
'meta_key'       => 'gd_review_date',
'order'          => 'DESC',
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_review_mover_id',
'value' => $mover_id,
'type'  => 'NUMERIC',
),
array(
'key'   => 'gd_review_submitted',
'value' => 1,
'type'  => 'NUMERIC',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

$reviews = array();
foreach ( $query->posts as $job_id ) {
$review = $this->get_review( $job_id );
if ( $review ) {
$reviews[] = $review;
}
}

wp_reset_postdata();

return $reviews;
}

/**
 * Recalculate and update a mover's average star rating.
 *
 * @param int $mover_id Mover user ID.
 * @return float Calculated average (0 if no reviews).
 */
public function recalculate_average( $mover_id ) {
$mover_id = (int) $mover_id;

$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_review_mover_id',
'value' => $mover_id,
'type'  => 'NUMERIC',
),
array(
'key'   => 'gd_review_submitted',
'value' => 1,
'type'  => 'NUMERIC',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

$total = 0;
$count = count( $query->posts );

foreach ( $query->posts as $job_id ) {
$total += (int) get_post_meta( $job_id, 'gd_review_rating', true );
}

wp_reset_postdata();

$average = $count > 0 ? round( $total / $count, 2 ) : 0.0;
update_user_meta( $mover_id, 'gd_average_rating', $average );

return (float) $average;
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: submit a review.
 */
public function ajax_submit_review() {
check_ajax_referer( 'gd_submit_review', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id   = absint( $_POST['job_id'] ?? 0 );
$mover_id = absint( $_POST['mover_id'] ?? 0 );
$rating   = absint( $_POST['rating'] ?? 0 );
$comment  = wp_kses_post( wp_unslash( $_POST['comment'] ?? '' ) );

if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

if ( ! $mover_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid mover ID.', 'go-deliver' ) ) );
}

$result = $this->submit_review( $job_id, get_current_user_id(), $mover_id, $rating, $comment );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Review submitted successfully.', 'go-deliver' ) ) );
}
}
