<?php
/**
 * Quote management for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Quotes
 */
class Go_Deliver_Quotes {

/** Valid quote statuses. */
const VALID_STATUSES = array( 'pending', 'accepted', 'expired', 'withdrawn' );

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_submit_quote',   array( $this, 'ajax_submit_quote' ) );
add_action( 'wp_ajax_gd_accept_quote',   array( $this, 'ajax_accept_quote' ) );
add_action( 'wp_ajax_gd_withdraw_quote', array( $this, 'ajax_withdraw_quote' ) );
}

// =========================================================================
// Core CRUD.
// =========================================================================

/**
 * Create a new quote.
 *
 * @param int    $job_id   Job post ID.
 * @param int    $mover_id Mover user ID.
 * @param float  $amount   Quote amount.
 * @param string $message  Optional message to customer.
 * @return int|WP_Error Quote post ID or WP_Error.
 */
public function create_quote( $job_id, $mover_id, $amount, $message = '' ) {
// Validate mover approval.
$mover_status = get_user_meta( (int) $mover_id, 'gd_mover_status', true );
if ( 'approved' !== $mover_status ) {
return new WP_Error( 'mover_not_approved', __( 'Your account must be approved before submitting quotes.', 'go-deliver' ) );
}

// Validate job.
$post = get_post( (int) $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

$job_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
if ( ! in_array( $job_status, array( 'open', 'locked' ), true ) ) {
return new WP_Error( 'job_not_available', __( 'This job is no longer accepting quotes.', 'go-deliver' ) );
}

$amount = (float) $amount;
if ( $amount <= 0 ) {
return new WP_Error( 'invalid_amount', __( 'Quote amount must be greater than zero.', 'go-deliver' ) );
}

// Check wallet balance.
$wallet  = new Go_Deliver_Wallet();
$balance = $wallet->check_balance_for_quote( $mover_id, $amount );
if ( is_wp_error( $balance ) ) {
return $balance;
}

// Insert quote post.
$quote_id = wp_insert_post(
array(
'post_type'   => 'gd_quote',
'post_status' => 'publish',
'post_title'  => sprintf( 'Quote for Job #%d', $job_id ),
'post_author' => (int) $mover_id,
),
true
);

if ( is_wp_error( $quote_id ) ) {
return $quote_id;
}

update_post_meta( $quote_id, 'gd_job_id',       (int) $job_id );
update_post_meta( $quote_id, 'gd_mover_id',     (int) $mover_id );
update_post_meta( $quote_id, 'gd_amount',        $amount );
update_post_meta( $quote_id, 'gd_message',       wp_kses_post( $message ) );
update_post_meta( $quote_id, 'gd_status',        'pending' );
update_post_meta( $quote_id, 'gd_submitted_at',  current_time( 'mysql' ) );
update_post_meta( $quote_id, 'gd_fee_charged',   0 );
update_post_meta( $quote_id, 'gd_fee_amount',    0 );

// Increment quote count on the job.
$current_count = (int) get_post_meta( (int) $job_id, 'gd_quote_count', true );
update_post_meta( (int) $job_id, 'gd_quote_count', $current_count + 1 );

// Lock the job if it was the first quote.
$jobs_handler = new Go_Deliver_Jobs();
$jobs_handler->lock_job( $job_id );

// Notify customer.
$notifications = new Go_Deliver_Notifications();
if ( method_exists( $notifications, 'notify_customer_new_quote' ) ) {
$notifications->notify_customer_new_quote( $job_id, $quote_id );
}

return $quote_id;
}

/**
 * Get a single quote with all meta.
 *
 * @param int $quote_id Quote post ID.
 * @return array|WP_Error Quote data or WP_Error.
 */
public function get_quote( $quote_id ) {
$post = get_post( (int) $quote_id );
if ( ! $post || 'gd_quote' !== $post->post_type ) {
return new WP_Error( 'quote_not_found', __( 'Quote not found.', 'go-deliver' ) );
}

return array(
'id'           => $post->ID,
'job_id'       => (int) get_post_meta( $post->ID, 'gd_job_id', true ),
'mover_id'     => (int) get_post_meta( $post->ID, 'gd_mover_id', true ),
'amount'       => (float) get_post_meta( $post->ID, 'gd_amount', true ),
'message'      => get_post_meta( $post->ID, 'gd_message', true ),
'status'       => get_post_meta( $post->ID, 'gd_status', true ),
'submitted_at' => get_post_meta( $post->ID, 'gd_submitted_at', true ),
'fee_charged'  => (bool) get_post_meta( $post->ID, 'gd_fee_charged', true ),
'fee_amount'   => (float) get_post_meta( $post->ID, 'gd_fee_amount', true ),
);
}

/**
 * Get all non-withdrawn quotes for a job.
 *
 * @param int $job_id Job post ID.
 * @return array Array of quote data arrays.
 */
public function get_quotes_for_job( $job_id ) {
$query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_job_id',
'value' => (int) $job_id,
'type'  => 'NUMERIC',
),
array(
'key'     => 'gd_status',
'value'   => 'withdrawn',
'compare' => '!=',
),
),
'orderby'        => 'date',
'order'          => 'ASC',
'no_found_rows'  => true,
)
);

$quotes           = array();
$current_user_id  = get_current_user_id();
$job_customer_id  = (int) get_post_meta( (int) $job_id, 'gd_customer_id', true );

foreach ( $query->posts as $post ) {
$quote    = $this->get_quote( $post->ID );
if ( is_wp_error( $quote ) ) {
continue;
}

$mover_id = $quote['mover_id'];
$mover    = get_userdata( $mover_id );

// Privacy: show mover's first name and suburb only unless job is accepted.
$job_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
if ( 'accepted' === $job_status && $current_user_id === $job_customer_id ) {
$quote['mover'] = $mover
? array(
'first_name' => $mover->first_name,
'last_name'  => $mover->last_name,
'email'      => $mover->user_email,
'phone'      => get_user_meta( $mover_id, 'gd_phone', true ),
)
: array();
} else {
$quote['mover'] = $mover
? array(
'first_name' => $mover->first_name,
'suburb'     => get_user_meta( $mover_id, 'gd_mover_base_suburb', true ),
'avg_rating' => (float) get_user_meta( $mover_id, 'gd_average_rating', true ),
)
: array();
}

$quotes[] = $quote;
}

wp_reset_postdata();

return $quotes;
}

/**
 * Get quotes submitted by a mover.
 *
 * @param int    $mover_id Mover user ID.
 * @param string $status   Optional status filter.
 * @return array Array of quote data arrays.
 */
public function get_quotes_for_mover( $mover_id, $status = '' ) {
$meta_query = array(
array(
'key'   => 'gd_mover_id',
'value' => (int) $mover_id,
'type'  => 'NUMERIC',
),
);

if ( ! empty( $status ) && in_array( $status, self::VALID_STATUSES, true ) ) {
$meta_query[] = array(
'key'   => 'gd_status',
'value' => sanitize_text_field( $status ),
);
}

$query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => $meta_query,
'orderby'        => 'date',
'order'          => 'DESC',
'no_found_rows'  => true,
)
);

$quotes = array();
foreach ( $query->posts as $post ) {
$quote = $this->get_quote( $post->ID );
if ( ! is_wp_error( $quote ) ) {
$quotes[] = $quote;
}
}

wp_reset_postdata();

return $quotes;
}

/**
 * Accept a quote.
 *
 * @param int $quote_id    Quote post ID.
 * @param int $customer_id Customer user ID.
 * @return true|WP_Error
 */
public function accept_quote( $quote_id, $customer_id ) {
$quote = $this->get_quote( $quote_id );
if ( is_wp_error( $quote ) ) {
return $quote;
}

if ( 'pending' !== $quote['status'] ) {
return new WP_Error( 'invalid_quote_status', __( 'Only pending quotes can be accepted.', 'go-deliver' ) );
}

$job_id = $quote['job_id'];
$job_customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );

if ( $job_customer_id !== (int) $customer_id ) {
return new WP_Error( 'permission_denied', __( 'You do not own this job.', 'go-deliver' ) );
}

$wallet = new Go_Deliver_Wallet();
$fee_charged = $wallet->charge_quote_fee( $quote['mover_id'], $quote_id, $job_id );
if ( is_wp_error( $fee_charged ) ) {
return $fee_charged;
}

// Accept this quote.
update_post_meta( $quote_id, 'gd_status', 'accepted' );

// Store accepted quote ID on the job for quick lookups.
update_post_meta( $job_id, 'gd_accepted_quote_id', $quote_id );

// Expire other pending quotes for the same job.
$this->expire_quotes_for_job( $job_id, $quote_id );

// Update job status.
$jobs_handler = new Go_Deliver_Jobs();
$jobs_handler->update_job_status( $job_id, 'accepted' );

// Notify mover.
$notifications = new Go_Deliver_Notifications();
if ( method_exists( $notifications, 'notify_mover_quote_accepted' ) ) {
$notifications->notify_mover_quote_accepted( $quote_id );
}

return true;
}

/**
 * Expire all pending quotes for a job except the accepted one.
 *
 * @param int $job_id           Job post ID.
 * @param int $accepted_quote_id Quote ID that was accepted.
 */
private function expire_quotes_for_job( $job_id, $accepted_quote_id ) {
$query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => -1,
'post__not_in'   => array( (int) $accepted_quote_id ),
'meta_query'     => array(
'relation' => 'AND',
array(
'key'   => 'gd_job_id',
'value' => (int) $job_id,
'type'  => 'NUMERIC',
),
array(
'key'   => 'gd_status',
'value' => 'pending',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

foreach ( $query->posts as $qid ) {
update_post_meta( $qid, 'gd_status', 'expired' );
}

wp_reset_postdata();
}

/**
 * Withdraw a quote.
 *
 * @param int $quote_id  Quote post ID.
 * @param int $mover_id  Mover user ID.
 * @return true|WP_Error
 */
public function withdraw_quote( $quote_id, $mover_id ) {
$quote = $this->get_quote( $quote_id );
if ( is_wp_error( $quote ) ) {
return $quote;
}

if ( $quote['mover_id'] !== (int) $mover_id ) {
return new WP_Error( 'permission_denied', __( 'You do not own this quote.', 'go-deliver' ) );
}

if ( 'pending' !== $quote['status'] ) {
return new WP_Error( 'invalid_quote_status', __( 'Only pending quotes can be withdrawn.', 'go-deliver' ) );
}

update_post_meta( $quote_id, 'gd_status', 'withdrawn' );

// Decrement quote count on the job (floor at 0).
$job_id        = $quote['job_id'];
$current_count = (int) get_post_meta( (int) $job_id, 'gd_quote_count', true );
update_post_meta( (int) $job_id, 'gd_quote_count', max( 0, $current_count - 1 ) );

return true;
}

/**
 * Expire pending quotes older than the configured number of days.
 *
 * @return void
 */
public function expire_quotes() {
$expiry_days = (int) get_option( 'gd_quote_expiry_days', 7 );
$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$expiry_days} days" ) );

$query = new WP_Query(
array(
'post_type'      => 'gd_quote',
'post_status'    => 'publish',
'posts_per_page' => -1,
'date_query'     => array(
array(
'before'    => $cutoff_date,
'inclusive' => false,
),
),
'meta_query'     => array(
array(
'key'   => 'gd_status',
'value' => 'pending',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

foreach ( $query->posts as $quote_id ) {
update_post_meta( $quote_id, 'gd_status', 'expired' );
}

wp_reset_postdata();
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: submit a quote.
 */
public function ajax_submit_quote() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_quotes' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id  = absint( $_POST['job_id'] ?? 0 );
$amount  = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0.0;
$message = wp_kses_post( wp_unslash( $_POST['message'] ?? '' ) );

if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$result = $this->create_quote( $job_id, get_current_user_id(), $amount, $message );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'quote_id' => $result ) );
}

/**
 * AJAX: accept a quote (customer).
 */
public function ajax_accept_quote() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$quote_id = absint( $_POST['quote_id'] ?? 0 );
if ( ! $quote_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid quote ID.', 'go-deliver' ) ) );
}

$result = $this->accept_quote( $quote_id, get_current_user_id() );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Quote accepted.', 'go-deliver' ) ) );
}

/**
 * AJAX: withdraw a quote (mover).
 */
public function ajax_withdraw_quote() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_quotes' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$quote_id = absint( $_POST['quote_id'] ?? 0 );
if ( ! $quote_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid quote ID.', 'go-deliver' ) ) );
}

$result = $this->withdraw_quote( $quote_id, get_current_user_id() );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Quote withdrawn.', 'go-deliver' ) ) );
}

/**
 * AJAX: return the mover's My Quotes tab HTML for live refresh after submission.
 */
public function ajax_get_my_quotes() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in.', 'go-deliver' ) ), 403 );
}

$user_id = get_current_user_id();
$quotes  = $this->get_quotes_for_mover( $user_id );
$fee_percentage = (float) get_option( 'gd_fee_percentage', 10 );

ob_start();
if ( empty( $quotes ) ) {
echo '<div class="gd-empty-state">';
echo '<div class="gd-empty-state__icon">📝</div>';
echo '<p class="gd-empty-state__text">' . esc_html__( "You haven't submitted any quotes yet.", 'go-deliver' ) . '</p>';
echo '</div>';
} else {
foreach ( $quotes as $quote ) {
$q_id     = $quote['id'];
$q_status = esc_attr( $quote['status'] ?: 'pending' );
$q_amount = (float) $quote['amount'];
$q_message = esc_html( $quote['message'] );
$q_job_id  = (int) $quote['job_id'];
$q_date    = esc_html( get_the_date( 'd M Y', $q_id ) );
$job_suburb = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
$job_type   = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_job_type', true ) ?: get_post_meta( $q_job_id, 'gd_form_data_item_type', true ) ) : '';
?>
<div class="gd-mover-card">
<div class="gd-mover-card__header">
<div>
<div class="gd-mover-card__job-type"><?php echo $job_type ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></div>
<div class="gd-mover-card__suburb"><?php echo $job_suburb ?: '—'; ?></div>
</div>
<div style="text-align:right;">
<div style="font-size:22px;font-weight:800;color:var(--gd-primary);">
$<?php echo esc_html( number_format( $q_amount, 0 ) ); ?>
</div>
<span class="gd-badge gd-badge--<?php echo esc_attr( $q_status ); ?>">
<?php echo esc_html( ucfirst( $q_status ) ); ?>
</span>
</div>
</div>
<?php if ( $q_message ) : ?>
<p style="font-size:13px;color:var(--gd-text-muted);margin:8px 0;"><?php echo $q_message; ?></p>
<?php endif; ?>
<div class="gd-mover-card__info-grid">
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Quoted', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value"><?php echo $q_date; ?></div>
</div>
<?php if ( 'accepted' === $q_status ) : ?>
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Fee Charged', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value">
$<?php echo esc_html( number_format( (float) get_post_meta( $q_id, 'gd_fee_amount', true ), 2 ) ); ?>
</div>
</div>
<?php endif; ?>
</div>
<div class="gd-mover-card__actions">
<?php if ( $q_job_id ) : ?>
<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn" data-job-id="<?php echo esc_attr( $q_job_id ); ?>">
<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
</button>
<?php endif; ?>
<?php if ( 'pending' === $q_status ) : ?>
<button type="button" class="gd-btn gd-btn--danger gd-btn--sm gd-withdraw-quote-btn" data-quote-id="<?php echo esc_attr( $q_id ); ?>">
<?php esc_html_e( 'Withdraw Quote', 'go-deliver' ); ?>
</button>
<?php endif; ?>
</div>
</div>
<?php
}
}
$html = ob_get_clean();

wp_send_json_success( array( 'html' => $html ) );
}
}
