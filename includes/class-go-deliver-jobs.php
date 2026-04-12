<?php
/**
 * Job management for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Jobs
 */
class Go_Deliver_Jobs {

/** Valid job statuses. */
const VALID_STATUSES = array( 'open', 'locked', 'accepted', 'completed', 'expired', 'cancelled' );

// =========================================================================
// Static helpers.
// =========================================================================

/**
 * Human-readable labels for every job-type slug.
 *
 * @return array<string,string>
 */
public static function get_type_labels(): array {
return array(
'trademe_pickup' => __( 'TradeMe Purchase Pickup', 'go-deliver' ),
'item'           => __( 'Item', 'go-deliver' ),
'furniture'      => __( 'Furniture', 'go-deliver' ),
'item_packed'    => __( 'Packed Item', 'go-deliver' ),
'move'           => __( 'House / Office Move', 'go-deliver' ),
'car'            => __( 'Car', 'go-deliver' ),
'motorcycle'     => __( 'Motorcycle', 'go-deliver' ),
'vehicle'        => __( 'Vehicle', 'go-deliver' ),
'other_vehicle'  => __( 'Other Vehicle', 'go-deliver' ),
'boat'           => __( 'Boat', 'go-deliver' ),
'piano'          => __( 'Piano', 'go-deliver' ),
'pet'            => __( 'Pet Transport', 'go-deliver' ),
'junk'           => __( 'Junk Removal', 'go-deliver' ),
'other'          => __( 'Other', 'go-deliver' ),
);
}

/**
 * Return the display title for a job.
 *
 * Uses the customer-supplied listing title when available; otherwise
 * translates the job-type slug to a human-readable label.
 *
 * @param int $job_id Post ID.
 * @return string
 */
public static function get_display_title( int $job_id ): string {
$listing_title = trim( (string) get_post_meta( $job_id, 'gd_listing_title', true ) );
if ( '' !== $listing_title ) {
return $listing_title;
}
$type   = (string) get_post_meta( $job_id, 'gd_job_type', true );
$labels = self::get_type_labels();
return $labels[ $type ] ?? ( $type ? ucwords( str_replace( '_', ' ', $type ) ) : __( 'Moving Job', 'go-deliver' ) );
}

/**
 * Return true if $text appears to contain a phone number or street address.
 *
 * Phone detection: strip common separators, then look for 7+ consecutive
 * digits (covers NZ, AU, and most international formats).
 *
 * Address detection: house number + one or two words + recognised street
 * type (Street, Road, Avenue, etc.).
 *
 * @param string $text Input to inspect.
 * @return bool
 */
private static function contains_contact_info( string $text ): bool {
// Email addresses.
if ( preg_match( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text ) ) {
return true;
}
// Phone numbers.
if ( preg_match( '/\d{7,}/', preg_replace( '/[\s\-\.\(\)\+]/', '', $text ) ) ) {
return true;
}
// Street addresses.
if ( preg_match(
'/\b\d+\s+\w+(?:\s+\w+)?\s+(?:Street|St|Road|Rd|Avenue|Ave|Drive|Dr|Place|Pl|Way|Lane|Ln|Crescent|Cres|Boulevard|Blvd|Court|Ct|Terrace|Tce|Close|Cl|Grove|Gr|Highway|Hwy)\b/i',
$text
) ) {
return true;
}
return false;
}

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_submit_job',      array( $this, 'ajax_submit_job' ) );
add_action( 'wp_ajax_gd_cancel_job',      array( $this, 'ajax_cancel_job' ) );
add_action( 'wp_ajax_gd_get_job_details', array( $this, 'ajax_get_job_details' ) );
add_action( 'wp_ajax_nopriv_gd_get_job_details', array( $this, 'ajax_get_job_details' ) );
}

// =========================================================================
// Core CRUD.
// =========================================================================

/**
 * Insert a new job post.
 *
 * @param array $data {
 *   @type string $job_type
 *   @type array  $pickup_location  Keys: lat, lng, address, suburb.
 *   @type array  $dropoff_location Keys: lat, lng, address, suburb.
 *   @type string $date_requested
 *   @type bool   $labour_pickup
 *   @type bool   $labour_dropoff
 *   @type string $inventory
 *   @type string $special_instructions
 *   @type int    $customer_id
 *   @type string $access_notes
 *   @type array  $form_data
 *   @type array  $photos  Attachment IDs.
 * }
 * @return int|WP_Error New post ID or WP_Error.
 */
public function create_job( $data ) {
if ( empty( $data['customer_id'] ) || ! get_userdata( (int) $data['customer_id'] ) ) {
return new WP_Error( 'invalid_customer', __( 'Invalid customer ID.', 'go-deliver' ) );
}

$pickup  = isset( $data['pickup_location'] ) && is_array( $data['pickup_location'] )
? $data['pickup_location'] : array();
$dropoff = isset( $data['dropoff_location'] ) && is_array( $data['dropoff_location'] )
? $data['dropoff_location'] : array();

if ( empty( $pickup['suburb'] ) && empty( $pickup['address'] ) ) {
return new WP_Error( 'invalid_pickup', __( 'Pickup location is required.', 'go-deliver' ) );
}
if ( empty( $dropoff['suburb'] ) && empty( $dropoff['address'] ) ) {
return new WP_Error( 'invalid_dropoff', __( 'Dropoff location is required.', 'go-deliver' ) );
}

$job_type = sanitize_text_field( $data['job_type'] ?? '' );
if ( empty( $job_type ) ) {
return new WP_Error( 'invalid_job_type', __( 'Job type is required.', 'go-deliver' ) );
}

$post_id = wp_insert_post(
array(
'post_type'   => 'gd_job',
'post_status' => 'publish',
'post_title'  => sprintf( 'Job #%s', wp_generate_uuid4() ),
'post_author' => (int) $data['customer_id'],
),
true
);

if ( is_wp_error( $post_id ) ) {
return $post_id;
}

// Sanitize location arrays.
$sanitized_pickup  = $this->sanitize_location( $pickup );
$sanitized_dropoff = $this->sanitize_location( $dropoff );

// Geocode missing coordinates server-side so jobs appear in mover radius searches.
$location_handler  = new Go_Deliver_Location();
$sanitized_pickup  = $this->fill_missing_coordinates( $sanitized_pickup, $location_handler );
$sanitized_dropoff = $this->fill_missing_coordinates( $sanitized_dropoff, $location_handler );

$form_data = isset( $data['form_data'] ) && is_array( $data['form_data'] ) ? $data['form_data'] : array();

$photos = array();
if ( ! empty( $data['photos'] ) && is_array( $data['photos'] ) ) {
foreach ( $data['photos'] as $photo_id ) {
$photos[] = (int) $photo_id;
}
}

update_post_meta( $post_id, 'gd_job_type',             $job_type );
update_post_meta( $post_id, 'gd_listing_title',        sanitize_text_field( $data['listing_title'] ?? '' ) );
update_post_meta( $post_id, 'gd_pickup_location',      wp_json_encode( $sanitized_pickup ) );
update_post_meta( $post_id, 'gd_dropoff_location',     wp_json_encode( $sanitized_dropoff ) );
update_post_meta( $post_id, 'gd_pickup_suburb',        $sanitized_pickup['suburb'] );
update_post_meta( $post_id, 'gd_pickup_address',       $sanitized_pickup['address'] );
update_post_meta( $post_id, 'gd_dropoff_suburb',       $sanitized_dropoff['suburb'] );
update_post_meta( $post_id, 'gd_dropoff_address',      $sanitized_dropoff['address'] );
update_post_meta( $post_id, 'gd_date_requested',       sanitize_text_field( $data['date_requested'] ?? '' ) );
update_post_meta( $post_id, 'gd_labour_pickup',        ! empty( $data['labour_pickup'] ) ? 1 : 0 );
update_post_meta( $post_id, 'gd_labour_dropoff',       ! empty( $data['labour_dropoff'] ) ? 1 : 0 );
update_post_meta( $post_id, 'gd_inventory',            wp_kses_post( $data['inventory'] ?? '' ) );
update_post_meta( $post_id, 'gd_special_instructions', wp_kses_post( $data['special_instructions'] ?? '' ) );
update_post_meta( $post_id, 'gd_customer_id',          (int) $data['customer_id'] );
update_post_meta( $post_id, 'gd_access_notes',         sanitize_text_field( $data['access_notes'] ?? '' ) );
update_post_meta( $post_id, 'gd_form_data',            wp_json_encode( $form_data ) );
update_post_meta( $post_id, 'gd_photos',               wp_json_encode( $photos ) );
update_post_meta( $post_id, 'gd_job_status',           'open' );
update_post_meta( $post_id, 'gd_created_at',           current_time( 'mysql' ) );

// Notify eligible movers about the new job.
$notifications = new Go_Deliver_Notifications();
$notifications->notify_movers_new_job( $post_id );

return $post_id;
}

/**
 * Sanitize a location array.
 *
 * @param array $location Raw location data.
 * @return array Sanitized location.
 */
private function sanitize_location( $location ) {
return array(
'lat'     => isset( $location['lat'] )     ? (float) $location['lat']                       : 0.0,
'lng'     => isset( $location['lng'] )     ? (float) $location['lng']                       : 0.0,
'address' => isset( $location['address'] ) ? sanitize_text_field( $location['address'] )    : '',
'suburb'  => isset( $location['suburb'] )  ? sanitize_text_field( $location['suburb'] )     : '',
);
}

/**
 * Attempt to fill missing lat/lng on a location array via geocoding.
 *
 * If the location already has non-zero coordinates they are returned unchanged.
 * When geocoding fails the original location is returned and the failure is
 * written to the PHP error log so it can be diagnosed.
 *
 * @param array               $location         Sanitized location array.
 * @param Go_Deliver_Location $location_handler Location handler instance.
 * @return array Location array, potentially with lat/lng populated.
 */
private function fill_missing_coordinates( $location, $location_handler ) {
if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
return $location;
}

$address_str = ( $location['address'] ?? '' ) ?: ( $location['suburb'] ?? '' );
if ( empty( $address_str ) ) {
return $location;
}

$coords = $location_handler->geocode_address( $address_str );
if ( is_wp_error( $coords ) ) {
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log( 'Go Deliver geocoding failed for "' . $address_str . '": ' . $coords->get_error_message() );
return $location;
}

$location['lat'] = $coords['lat'];
$location['lng'] = $coords['lng'];
return $location;
}

/**
 * Get a single job with all meta decoded.
 *
 * @param int $job_id  Post ID.
 * @return array|WP_Error Job data array or WP_Error.
 */
public function get_job( $job_id ) {
$post = get_post( (int) $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

$meta = get_post_meta( $post->ID );

$job = array(
'id'                   => $post->ID,
'job_type'             => get_post_meta( $post->ID, 'gd_job_type', true ),
'listing_title'        => get_post_meta( $post->ID, 'gd_listing_title', true ),
'pickup_location'      => json_decode( get_post_meta( $post->ID, 'gd_pickup_location', true ), true ) ?: array(),
'dropoff_location'     => json_decode( get_post_meta( $post->ID, 'gd_dropoff_location', true ), true ) ?: array(),
'date_requested'       => get_post_meta( $post->ID, 'gd_date_requested', true ),
'labour_pickup'        => (bool) get_post_meta( $post->ID, 'gd_labour_pickup', true ),
'labour_dropoff'       => (bool) get_post_meta( $post->ID, 'gd_labour_dropoff', true ),
'inventory'            => get_post_meta( $post->ID, 'gd_inventory', true ),
'special_instructions' => get_post_meta( $post->ID, 'gd_special_instructions', true ),
'customer_id'          => (int) get_post_meta( $post->ID, 'gd_customer_id', true ),
'access_notes'         => get_post_meta( $post->ID, 'gd_access_notes', true ),
'form_data'            => json_decode( get_post_meta( $post->ID, 'gd_form_data', true ), true ) ?: array(),
'photos'               => json_decode( get_post_meta( $post->ID, 'gd_photos', true ), true ) ?: array(),
'status'               => get_post_meta( $post->ID, 'gd_job_status', true ),
'created_at'           => get_post_meta( $post->ID, 'gd_created_at', true ),
'first_quote_at'       => get_post_meta( $post->ID, 'gd_first_quote_at', true ),
);

$current_user_id = get_current_user_id();
$job = $this->apply_privacy_filter( $job, $current_user_id );

return $job;
}

/**
 * Get jobs belonging to a customer.
 *
 * @param int    $customer_id Customer user ID.
 * @param string $status      Optional status filter.
 * @return array Array of job data arrays.
 */
public function get_jobs_for_customer( $customer_id, $status = '' ) {
$meta_query = array(
array(
'key'   => 'gd_customer_id',
'value' => (int) $customer_id,
'type'  => 'NUMERIC',
),
);

if ( ! empty( $status ) && in_array( $status, self::VALID_STATUSES, true ) ) {
$meta_query[] = array(
'key'   => 'gd_job_status',
'value' => sanitize_text_field( $status ),
);
}

$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => $meta_query,
'orderby'        => 'date',
'order'          => 'DESC',
'no_found_rows'  => true,
)
);

$jobs = array();
foreach ( $query->posts as $post ) {
$job = $this->get_job( $post->ID );
if ( ! is_wp_error( $job ) ) {
$jobs[] = $job;
}
}

wp_reset_postdata();

return $jobs;
}

/**
 * Get all open and locked jobs regardless of mover filters.
 *
 * Intended for admin users who should see every available job without
 * radius or mover-status restrictions.
 *
 * @return array Job array.
 */
public function get_all_open_jobs() {
$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
array(
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
),
'no_found_rows'  => true,
)
);

$jobs = array();
foreach ( $query->posts as $post ) {
$job = $this->get_job( $post->ID );
if ( ! is_wp_error( $job ) ) {
$jobs[] = $job;
}
}

wp_reset_postdata();

return $jobs;
}

/**
 * Get open jobs visible to a mover (within radius and matching job types).
 *
 * @param int $mover_id Mover user ID.
 * @return array Filtered job array.
 */
public function get_open_jobs_for_mover( $mover_id ) {
$mover_status = get_user_meta( (int) $mover_id, 'gd_mover_status', true );
if ( 'approved' !== $mover_status ) {
return array();
}

$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
array(
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
),
'no_found_rows'  => true,
)
);

$jobs = array();
foreach ( $query->posts as $post ) {
$job = $this->get_job( $post->ID );
if ( ! is_wp_error( $job ) ) {
$jobs[] = $job;
}
}

wp_reset_postdata();

$location_handler = new Go_Deliver_Location();
return $location_handler->filter_jobs_by_radius( $jobs, $mover_id );
}

/**
 * Update the status of a job.
 *
 * @param int    $job_id Post ID.
 * @param string $status New status.
 * @return true|WP_Error
 */
public function update_job_status( $job_id, $status ) {
if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
return new WP_Error( 'invalid_status', __( 'Invalid job status.', 'go-deliver' ) );
}

$post = get_post( (int) $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

update_post_meta( (int) $job_id, 'gd_job_status', $status );
return true;
}

/**
 * Lock a job (first quote received).
 *
 * @param int $job_id Post ID.
 * @return true|WP_Error
 */
public function lock_job( $job_id ) {
$current_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
if ( 'open' === $current_status ) {
$result = $this->update_job_status( $job_id, 'locked' );
if ( is_wp_error( $result ) ) {
return $result;
}
// Record first quote timestamp if not already set.
if ( ! get_post_meta( (int) $job_id, 'gd_first_quote_at', true ) ) {
update_post_meta( (int) $job_id, 'gd_first_quote_at', current_time( 'mysql' ) );
}
}
return true;
}

/**
 * Cancel a job (customer only, must be open).
 *
 * @param int $job_id      Post ID.
 * @param int $customer_id Customer user ID.
 * @return true|WP_Error
 */
public function cancel_job( $job_id, $customer_id ) {
$post = get_post( (int) $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

$stored_customer_id = (int) get_post_meta( (int) $job_id, 'gd_customer_id', true );
if ( $stored_customer_id !== (int) $customer_id ) {
return new WP_Error( 'permission_denied', __( 'You do not own this job.', 'go-deliver' ) );
}

$current_status = get_post_meta( (int) $job_id, 'gd_job_status', true );
if ( 'open' !== $current_status ) {
return new WP_Error( 'invalid_status', __( 'Only open jobs can be cancelled.', 'go-deliver' ) );
}

return $this->update_job_status( $job_id, 'cancelled' );
}

/**
 * Mark an accepted job as completed by the mover.
 *
 * Only the mover who holds the accepted quote for this job may complete it.
 * After completion an email notification is sent to the customer asking for
 * a review.
 *
 * @param int $job_id   Post ID of the gd_job.
 * @param int $mover_id User ID of the requesting mover (or their parent).
 * @return true|WP_Error
 */
public function complete_job( $job_id, $mover_id ) {
$job_id   = (int) $job_id;
$mover_id = (int) $mover_id;

$post = get_post( $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
return new WP_Error( 'job_not_found', __( 'Job not found.', 'go-deliver' ) );
}

$current_status = get_post_meta( $job_id, 'gd_job_status', true );
if ( 'accepted' !== $current_status ) {
return new WP_Error( 'invalid_status', __( 'Only accepted jobs can be marked as completed.', 'go-deliver' ) );
}

// Confirm the mover has the accepted quote for this job.
$accepted_quote_id = (int) get_post_meta( $job_id, 'gd_accepted_quote_id', true );
if ( ! $accepted_quote_id ) {
return new WP_Error( 'no_accepted_quote', __( 'No accepted quote found for this job.', 'go-deliver' ) );
}

$quote_mover_id = (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true );
if ( $quote_mover_id !== $mover_id ) {
return new WP_Error( 'permission_denied', __( 'You are not the mover for this job.', 'go-deliver' ) );
}

$result = $this->update_job_status( $job_id, 'completed' );
if ( is_wp_error( $result ) ) {
return $result;
}

// Notify the customer.
$notifications = new Go_Deliver_Notifications();
if ( method_exists( $notifications, 'notify_customer_job_completed' ) ) {
$notifications->notify_customer_job_completed( $job_id );
}

return true;
}

/**
 * AJAX: mark an accepted job as completed.
 */
public function ajax_complete_job() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in.', 'go-deliver' ) ), 403 );
}

$roles    = (array) wp_get_current_user()->roles;
$is_mover = in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true );

if ( ! $is_mover && ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id = absint( $_POST['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

// Resolve effective mover ID (sub-users share parent's wallet/jobs).
$sub_users    = new Go_Deliver_Sub_Users();
$effective_id = $sub_users->get_effective_mover_id( get_current_user_id() );

$result = $this->complete_job( $job_id, $effective_id );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Job marked as completed. The customer has been notified.', 'go-deliver' ) ) );
}

/**
 * Expire old open/locked jobs via cron.
 *
 * @return void
 */
public function expire_jobs() {
$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );
$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$expiry_days} days" ) );

$query = new WP_Query(
array(
'post_type'      => 'gd_job',
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
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

foreach ( $query->posts as $job_id ) {
$this->update_job_status( $job_id, 'expired' );
}

wp_reset_postdata();
}

// =========================================================================
// Privacy.
// =========================================================================

/**
 * Apply privacy filter to a job array.
 *
 * For non-accepted jobs: hide phone/email unless viewer is the customer.
 * For accepted jobs: mover who has the accepted quote sees full details.
 *
 * @param array $job             Job data array.
 * @param int   $current_user_id Currently logged-in user ID (0 = guest).
 * @return array Filtered job array.
 */
public function apply_privacy_filter( $job, $current_user_id ) {
$customer_id = (int) ( $job['customer_id'] ?? 0 );

// Customer always sees their own full details.
if ( $current_user_id && $current_user_id === $customer_id ) {
return $job;
}

// Admin/super-admin bypass.
if ( $current_user_id && user_can( $current_user_id, 'manage_options' ) ) {
return $job;
}

// For accepted jobs, the mover with the accepted quote sees full details.
if ( 'accepted' === ( $job['status'] ?? '' ) && $current_user_id ) {
global $wpdb;
$accepted_mover_id = (int) $wpdb->get_var(
$wpdb->prepare(
"SELECT pm.meta_value FROM {$wpdb->postmeta} pm
 INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
 WHERE pm.meta_key = 'gd_mover_id'
   AND pm2.meta_key = 'gd_status' AND pm2.meta_value = 'accepted'
   AND pm.post_id IN (
     SELECT post_id FROM {$wpdb->postmeta}
     WHERE meta_key = 'gd_job_id' AND meta_value = %d
   )
 LIMIT 1",
$job['id']
)
);

if ( $accepted_mover_id && $accepted_mover_id === (int) $current_user_id ) {
return $job;
}
}

// For all other cases, redact sensitive customer information.
$customer = get_userdata( $customer_id );
if ( $customer ) {
$job['customer_display'] = array(
'first_name' => $customer->first_name,
'suburb'     => $job['pickup_location']['suburb'] ?? '',
);
}

// Remove full address details for non-accepted jobs.
if ( 'accepted' !== ( $job['status'] ?? '' ) ) {
if ( isset( $job['pickup_location']['address'] ) ) {
$job['pickup_location']['address'] = '';
}
if ( isset( $job['dropoff_location']['address'] ) ) {
$job['dropoff_location']['address'] = '';
}
}

return $job;
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: submit a new job.
 *
 * Accepts submissions from both logged-in users and guests.
 * For guests, account creation fields are required and a new gd_customer
 * account is created before the job is saved.
 */
public function ajax_submit_job() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

$current_user_id = get_current_user_id();

if ( ! $current_user_id ) {
// Guest: validate account creation fields and register a new customer.
$first_name = sanitize_text_field( wp_unslash( $_POST['account_first_name'] ?? '' ) );
$last_name  = sanitize_text_field( wp_unslash( $_POST['account_last_name'] ?? '' ) );
$email      = sanitize_email( wp_unslash( $_POST['account_email'] ?? '' ) );
$password   = isset( $_POST['account_password'] ) ? wp_unslash( $_POST['account_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

if ( empty( $_POST['customer_terms_agreed'] ) ) {
wp_send_json_error( array( 'message' => __( 'You must agree to the Terms & Conditions to submit a job.', 'go-deliver' ) ) );
}

if ( empty( $first_name ) || empty( $last_name ) || empty( $email ) || empty( $password ) ) {
wp_send_json_error( array( 'message' => __( 'Please fill in all account fields.', 'go-deliver' ) ) );
}

if ( ! is_email( $email ) ) {
wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'go-deliver' ) ) );
}

if ( email_exists( $email ) ) {
wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please log in first.', 'go-deliver' ) ) );
}

if ( strlen( $password ) < 8 ) {
wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'go-deliver' ) ) );
}

// Use the email address as the WordPress username (common single-field
// registration pattern; username is not displayed to end users).
$user_id = wp_create_user( $email, $password, $email );
if ( is_wp_error( $user_id ) ) {
wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
}

$user = new WP_User( $user_id );
$user->set_role( 'gd_customer' );
wp_update_user(
array(
'ID'           => $user_id,
'first_name'   => $first_name,
'last_name'    => $last_name,
'display_name' => trim( $first_name . ' ' . $last_name ),
)
);

wp_set_current_user( $user_id );
wp_set_auth_cookie( $user_id );

$current_user_id = $user_id;
} elseif ( ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$pickup_location = array(
'lat'     => isset( $_POST['pickup_lat'] )     ? (float) $_POST['pickup_lat']                          : 0.0,
'lng'     => isset( $_POST['pickup_lng'] )     ? (float) $_POST['pickup_lng']                          : 0.0,
'address' => isset( $_POST['pickup_address'] ) ? sanitize_text_field( wp_unslash( $_POST['pickup_address'] ) ) : '',
'suburb'  => isset( $_POST['pickup_suburb'] )  ? sanitize_text_field( wp_unslash( $_POST['pickup_suburb'] ) )  : '',
);

$dropoff_location = array(
'lat'     => isset( $_POST['dropoff_lat'] )     ? (float) $_POST['dropoff_lat']                           : 0.0,
'lng'     => isset( $_POST['dropoff_lng'] )     ? (float) $_POST['dropoff_lng']                           : 0.0,
'address' => isset( $_POST['dropoff_address'] ) ? sanitize_text_field( wp_unslash( $_POST['dropoff_address'] ) ) : '',
'suburb'  => isset( $_POST['dropoff_suburb'] )  ? sanitize_text_field( wp_unslash( $_POST['dropoff_suburb'] ) )  : '',
);

$form_data = array();
if ( ! empty( $_POST['form_data'] ) && is_array( $_POST['form_data'] ) ) {
foreach ( $_POST['form_data'] as $key => $value ) {
$form_data[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
}
}

// Persist phone number to user meta so it pre-fills on future submissions.
if ( ! empty( $form_data['contact_phone'] ) ) {
update_user_meta( $current_user_id, 'gd_phone', $form_data['contact_phone'] );
}

$photos = array();
if ( ! empty( $_POST['photos'] ) && is_array( $_POST['photos'] ) ) {
foreach ( $_POST['photos'] as $photo_id ) {
$photos[] = absint( $photo_id );
}
}

// Also handle directly-uploaded files from the job_photos[] file input.
if ( ! empty( $_FILES['job_photos']['name'] ) ) {
if ( ! function_exists( 'media_handle_upload' ) ) {
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
}

// Normalize the $_FILES array into a flat list of individual files
// regardless of whether one file or multiple files were uploaded.
$raw       = $_FILES['job_photos'];
$file_keys = array( 'name', 'type', 'tmp_name', 'error', 'size' );
$file_list = array();
if ( is_array( $raw['name'] ) ) {
$count = count( $raw['name'] );
for ( $i = 0; $i < $count; $i++ ) {
$entry = array();
foreach ( $file_keys as $k ) {
$entry[ $k ] = $raw[ $k ][ $i ] ?? '';
}
$file_list[] = $entry;
}
} else {
$entry = array();
foreach ( $file_keys as $k ) {
$entry[ $k ] = $raw[ $k ] ?? '';
}
$file_list[] = $entry;
}

foreach ( $file_list as $single_file ) {
if ( UPLOAD_ERR_OK !== (int) $single_file['error'] || empty( $single_file['tmp_name'] ) ) {
continue;
}
$_FILES['gd_job_photo_tmp'] = $single_file;
$attachment_id = media_handle_upload( 'gd_job_photo_tmp', 0 );
if ( ! is_wp_error( $attachment_id ) ) {
$photos[] = $attachment_id;
}
}
unset( $_FILES['gd_job_photo_tmp'] );
}

$listing_title = sanitize_text_field( wp_unslash( $_POST['listing_title'] ?? '' ) );
if ( empty( $listing_title ) ) {
wp_send_json_error( array( 'message' => __( 'Please give your listing a title.', 'go-deliver' ) ) );
}
if ( mb_strlen( $listing_title ) > 80 ) {
wp_send_json_error( array( 'message' => __( 'Listing title must be 80 characters or fewer.', 'go-deliver' ) ) );
}
if ( self::contains_contact_info( $listing_title ) ) {
wp_send_json_error( array( 'message' => __( 'Your listing title may not include a phone number, street address, or email address. Please remove contact details — they are shared privately after a quote is accepted.', 'go-deliver' ) ) );
}

$inventory_raw = wp_kses_post( wp_unslash( $_POST['inventory'] ?? '' ) );
if ( self::contains_contact_info( wp_strip_all_tags( $inventory_raw ) ) ) {
wp_send_json_error( array( 'message' => __( 'The "More information" field may not include a phone number, street address, or email address. Contact details are shared privately after a quote is accepted.', 'go-deliver' ) ) );
}

$data = array(
'job_type'             => sanitize_text_field( wp_unslash( $_POST['job_type'] ?? $form_data['item_type'] ?? '' ) ),
'listing_title'        => $listing_title,
'pickup_location'      => $pickup_location,
'dropoff_location'     => $dropoff_location,
'date_requested'       => sanitize_text_field( wp_unslash( $_POST['date_requested'] ?? '' ) ),
'labour_pickup'        => ! empty( $_POST['labour_pickup'] ),
'labour_dropoff'       => ! empty( $_POST['labour_dropoff'] ),
'inventory'            => $inventory_raw,
'special_instructions' => wp_kses_post( wp_unslash( $_POST['special_instructions'] ?? '' ) ),
'customer_id'          => $current_user_id,
'access_notes'         => sanitize_text_field( wp_unslash( $_POST['access_notes'] ?? '' ) ),
'form_data'            => $form_data,
'photos'               => $photos,
);

$result = $this->create_job( $data );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

// Associate uploaded photo attachments with the new job post so they are
// correctly linked in the media library.
if ( ! empty( $photos ) ) {
foreach ( $photos as $attachment_id ) {
wp_update_post( array(
'ID'          => $attachment_id,
'post_parent' => $result,
) );
}
}

wp_send_json_success( array( 'job_id' => $result ) );
}

/**
 * AJAX: cancel a job.
 */
public function ajax_cancel_job() {
check_ajax_referer( 'gd_cancel_job', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id = absint( $_POST['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$result = $this->cancel_job( $job_id, get_current_user_id() );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Job cancelled.', 'go-deliver' ) ) );
}

/**
 * AJAX: get available jobs for the mover dashboard.
 *
 * Returns an HTML fragment of job cards for the #gd-available-jobs-list
 * container.  Optionally filtered by job_type.
 */
public function ajax_get_available_jobs() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to view available jobs.', 'go-deliver' ) ), 403 );
}

$user_id  = get_current_user_id();
$is_admin = user_can( $user_id, 'manage_options' );

if ( ! $is_admin ) {
$roles        = (array) wp_get_current_user()->roles;
$is_mover     = in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true );
if ( ! $is_mover ) {
wp_send_json_error( array( 'message' => __( 'Access denied.', 'go-deliver' ) ), 403 );
}
}

$job_type_filter = isset( $_POST['job_type'] ) ? sanitize_key( wp_unslash( $_POST['job_type'] ) ) : '';

$jobs = $is_admin
? $this->get_all_open_jobs()
: $this->get_open_jobs_for_mover( $user_id );

// Apply optional job-type filter.
if ( ! empty( $job_type_filter ) ) {
$jobs = array_values( array_filter( $jobs, function ( $job ) use ( $job_type_filter ) {
return isset( $job['job_type'] ) && $job['job_type'] === $job_type_filter;
} ) );
}

if ( empty( $jobs ) ) {
wp_send_json_success( array( 'html' => '' ) );
}

$job_type_labels = self::get_type_labels();

$status_labels = array(
'open'   => __( 'New', 'go-deliver' ),
'locked' => __( 'Receiving Quotes', 'go-deliver' ),
);

$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );

ob_start();
?>
<div class="gd-jobs-grid">
<?php foreach ( $jobs as $job ) :
$pickup        = $job['pickup_location'] ?? array();
$dropoff       = $job['dropoff_location'] ?? array();
$status        = $job['status'] ?? 'open';
$type          = $job['job_type'] ?? '';
$type_label    = isset( $job_type_labels[ $type ] ) ? $job_type_labels[ $type ] : ucwords( str_replace( '_', ' ', $type ) );
$listing_title = ! empty( $job['listing_title'] ) ? $job['listing_title'] : null;
$status_label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
$date          = ! empty( $job['date_requested'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $job['date_requested'] ) ) : '';
$date_flexible = ! empty( $job['form_data']['date_flexible'] );
$photos        = ! empty( $job['photos'] ) && is_array( $job['photos'] ) ? $job['photos'] : array();

$inventory      = ! empty( $job['inventory'] ) ? wp_strip_all_tags( $job['inventory'] ) : '';
$inv_words      = $inventory ? preg_split( '/\s+/', $inventory, -1, PREG_SPLIT_NO_EMPTY ) : array();
$inv_word_count = count( $inv_words );
$inv_needs_more = $inv_word_count > 50;
$inv_preview    = $inv_needs_more ? implode( ' ', array_slice( $inv_words, 0, 50 ) ) . '…' : $inventory;
?>
<div class="gd-job-card" data-job-id="<?php echo esc_attr( $job['id'] ); ?>">

<div class="gd-job-card__header">
<span class="gd-badge gd-badge--<?php echo esc_attr( $status ); ?>">
<?php echo esc_html( $status_label ); ?>
</span>
<div class="gd-job-card__title-wrap">
<span class="gd-job-card__type"><?php echo esc_html( $type_label ); ?></span>
<?php if ( $listing_title ) : ?>
<span class="gd-job-card__listing-title"><?php echo esc_html( $listing_title ); ?></span>
<?php endif; ?>
</div>
</div>

<div class="gd-job-card__info-row">
<div class="gd-job-card__info-cols">
<div class="gd-job-card__info-col">
<span class="gd-job-card__info-label"><?php esc_html_e( 'From', 'go-deliver' ); ?></span>
<span class="gd-job-card__info-value">
<?php echo esc_html( $pickup['suburb'] ?? $pickup['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
</span>
</div>
<div class="gd-job-card__info-col">
<span class="gd-job-card__info-label"><?php esc_html_e( 'To', 'go-deliver' ); ?></span>
<span class="gd-job-card__info-value">
<?php echo esc_html( $dropoff['suburb'] ?? $dropoff['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
</span>
</div>
<?php if ( $date ) : ?>
<div class="gd-job-card__info-col">
<span class="gd-job-card__info-label"><?php esc_html_e( 'Moving date', 'go-deliver' ); ?></span>
<span class="gd-job-card__info-value"><?php echo esc_html( $date ); ?></span>
<?php if ( $date_flexible ) : ?>
<span class="gd-job-card__info-flex"><?php esc_html_e( '(flexible)', 'go-deliver' ); ?></span>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
<div class="gd-job-card__cta">
<button
type="button"
class="gd-btn gd-btn--primary gd-quote-btn"
data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
>
<?php esc_html_e( 'Submit a Quote', 'go-deliver' ); ?>
</button>
</div>
</div>

<?php if ( $inventory || ! empty( $photos ) ) : ?>
<div class="gd-job-card__divider"></div>
<?php endif; ?>

<?php if ( $inventory ) : ?>
<div class="gd-job-card__extra">
<div class="gd-job-card__extra-label"><?php esc_html_e( 'Additional information', 'go-deliver' ); ?></div>
<div class="gd-job-card__extra-text">
<?php if ( $inv_needs_more ) : ?>
<span class="gd-read-more-short"><?php echo esc_html( $inv_preview ); ?></span>
<span class="gd-read-more-full gd-hidden"><?php echo esc_html( $inventory ); ?></span>
<button type="button" class="gd-read-more-btn"><?php esc_html_e( 'Read more', 'go-deliver' ); ?></button>
<?php else : ?>
<?php echo esc_html( $inventory ); ?>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if ( ! empty( $photos ) ) : ?>
<div class="gd-photo-gallery">
<?php foreach ( array_slice( $photos, 0, 4 ) as $photo_id ) :
$full_url  = wp_get_attachment_url( (int) $photo_id );
$thumb_src = wp_get_attachment_image_src( (int) $photo_id, 'thumbnail' );
if ( $full_url ) :
$thumb_url = $thumb_src ? $thumb_src[0] : $full_url;
?>
<div class="gd-photo-gallery__item">
<a href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener" class="gd-photo-gallery__link">
<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php esc_attr_e( 'Job photo', 'go-deliver' ); ?>" loading="lazy">
</a>
</div>
<?php endif; endforeach; ?>
</div>
<?php endif; ?>

</div><!-- /.gd-job-card -->
<?php endforeach; ?>
</div><!-- /.gd-jobs-grid -->
<?php
$html = ob_get_clean();

wp_send_json_success( array( 'html' => $html ) );
}

/**
 * AJAX: render job detail as HTML (used by the mover-dashboard modal).
 */
public function ajax_get_job_detail_html() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

$job_id = absint( $_POST['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

ob_start();
$template = GD_PLUGIN_DIR . 'public/partials/job-detail.php';
if ( file_exists( $template ) ) {
include $template;
}
$html = ob_get_clean();

wp_send_json_success( array( 'html' => $html ) );
}

/**
 * AJAX: get job details (privacy-filtered).
 */
public function ajax_get_job_details() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

$job_id = absint( $_POST['job_id'] ?? $_GET['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$job = $this->get_job( $job_id );

if ( is_wp_error( $job ) ) {
wp_send_json_error( array( 'message' => $job->get_error_message() ) );
}

wp_send_json_success( $job );
}
}
