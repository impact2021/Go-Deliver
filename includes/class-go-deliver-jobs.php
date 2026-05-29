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
// Current parent categories.
'trademe_pickup' => __( 'Trademe Purchase Pickup', 'go-deliver' ),
'item'           => __( 'Item', 'go-deliver' ),
'move'            => __( 'Home or office move', 'go-deliver' ),
'vehicle_or_boat' => __( 'Vehicle or boat', 'go-deliver' ),
'pet'            => __( 'Pet Transport', 'go-deliver' ),
'junk'           => __( 'Junk Removal', 'go-deliver' ),
'other'          => __( 'Other', 'go-deliver' ),
// Legacy slugs retained for backward compatibility with existing jobs.
'furniture'      => __( 'Furniture', 'go-deliver' ),
'item_packed'    => __( 'Packed Item', 'go-deliver' ),
'car'            => __( 'Car', 'go-deliver' ),
'motorcycle'     => __( 'Motorcycle', 'go-deliver' ),
'vehicle'        => __( 'Vehicle', 'go-deliver' ),
'other_vehicle'  => __( 'Other Vehicle', 'go-deliver' ),
'boat'           => __( 'Boat', 'go-deliver' ),
'piano'          => __( 'Piano', 'go-deliver' ),
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
 * Return a human-readable "time since" string for a given datetime.
 *
 * Shows hours (up to 23 h 59 m), then switches to days once ≥ 24 hours
 * have elapsed.  Returns an empty string when no valid timestamp is given.
 *
 * @param string|int $datetime MySQL datetime string or Unix timestamp.
 * @return string E.g. "3 hours ago", "1 day ago", "5 days ago".
 */
public static function time_since( $datetime ): string {
	if ( empty( $datetime ) ) {
		return '';
	}
	$ts   = is_numeric( $datetime ) ? (int) $datetime : strtotime( $datetime );
	if ( ! $ts ) {
		return '';
	}
	$diff = max( 0, current_time( 'timestamp' ) - $ts );

	if ( $diff < DAY_IN_SECONDS ) {
		$hours = (int) floor( $diff / HOUR_IN_SECONDS );
		if ( $hours < 1 ) {
			$mins = (int) floor( $diff / MINUTE_IN_SECONDS );
			/* translators: %d: number of minutes */
			return $mins <= 1
				? __( 'Just now', 'go-deliver' )
				: sprintf( _n( '%d minute ago', '%d minutes ago', $mins, 'go-deliver' ), $mins );
		}
		/* translators: %d: number of hours */
		return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'go-deliver' ), $hours );
	}

	$days = (int) floor( $diff / DAY_IN_SECONDS );
	/* translators: %d: number of days */
	return sprintf( _n( '%d day ago', '%d days ago', $days, 'go-deliver' ), $days );
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
update_post_meta( $post_id, 'gd_pickup_location',      wp_json_encode( $sanitized_pickup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
update_post_meta( $post_id, 'gd_dropoff_location',     wp_json_encode( $sanitized_dropoff, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
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

// If excluded mover IDs were supplied (e.g. on a repost), persist them
// BEFORE sending notifications so excluded movers are never emailed.
if ( ! empty( $data['excluded_mover_ids'] ) && is_array( $data['excluded_mover_ids'] ) ) {
$excluded = array_values( array_unique( array_filter( array_map( 'intval', $data['excluded_mover_ids'] ) ) ) );
if ( ! empty( $excluded ) ) {
update_post_meta( $post_id, 'gd_excluded_mover_ids', $excluded );
}
}

// Notify eligible movers about the new job.
$notifications = new Go_Deliver_Notifications();
$notifications->notify_movers_new_job( $post_id );

// Send the customer a confirmation email with job details and a dashboard link.
$notifications->notify_customer_job_posted( $post_id );

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
'address' => isset( $location['address'] ) ? sanitize_text_field( gd_normalize_unicode_escapes( $location['address'] ) ) : '',
'suburb'  => isset( $location['suburb'] )  ? sanitize_text_field( gd_normalize_unicode_escapes( $location['suburb'] ) )  : '',
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
'created_at'           => get_post_meta( $post->ID, 'gd_created_at', true ) ?: get_post_field( 'post_date', $post->ID ),
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
$today = gmdate( 'Y-m-d' );
$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
array(
'relation' => 'OR',
array(
'key'     => 'gd_date_requested',
'value'   => '',
'compare' => '=',
),
array(
'key'     => 'gd_date_requested',
'compare' => 'NOT EXISTS',
),
array(
'key'     => 'gd_date_requested',
'value'   => $today,
'compare' => '>=',
'type'    => 'DATE',
),
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

$today = gmdate( 'Y-m-d' );
$query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
array(
'relation' => 'OR',
array(
'key'     => 'gd_date_requested',
'value'   => '',
'compare' => '=',
),
array(
'key'     => 'gd_date_requested',
'compare' => 'NOT EXISTS',
),
array(
'key'     => 'gd_date_requested',
'value'   => $today,
'compare' => '>=',
'type'    => 'DATE',
),
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
 * Cancel a job (customer only; must be open, locked, or accepted).
 *
 * When an accepted job is cancelled the mover's platform fee is automatically
 * refunded to their wallet and they are notified by email.
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
if ( ! in_array( $current_status, array( 'open', 'locked', 'accepted' ), true ) ) {
return new WP_Error( 'invalid_status', __( 'This job cannot be cancelled.', 'go-deliver' ) );
}

$result = $this->update_job_status( $job_id, 'cancelled' );
if ( is_wp_error( $result ) ) {
return $result;
}

// Refund the mover's platform fee when cancelling an accepted job.
$refund_amount = 0.0;
if ( 'accepted' === $current_status ) {
$accepted_quote_id = (int) get_post_meta( (int) $job_id, 'gd_accepted_quote_id', true );
if ( $accepted_quote_id ) {
$fee_charged = (bool) get_post_meta( $accepted_quote_id, 'gd_fee_charged', true );
$fee_amount  = (float) get_post_meta( $accepted_quote_id, 'gd_fee_amount', true );
$mover_id    = (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true );

if ( $fee_charged && $fee_amount > 0 && $mover_id ) {
$wallet = new Go_Deliver_Wallet();
$wallet->credit(
$mover_id,
$fee_amount,
sprintf(
/* translators: %d: job ID */
__( 'Refund – customer cancelled job #%d', 'go-deliver' ),
(int) $job_id
)
);
// Mark fee as refunded so it is not refunded twice.
update_post_meta( $accepted_quote_id, 'gd_fee_refunded', 1 );
$refund_amount = $fee_amount;
}
}
}

// Notify the mover (if there was an accepted quote).
$notifications = new Go_Deliver_Notifications();
$notifications->notify_mover_job_cancelled( $job_id, $refund_amount );

return true;
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
 * Return quote count, minimum bid, and maximum bid for a set of jobs in one
 * database query.  Only non-withdrawn quotes are counted.
 *
 * @param int[] $job_ids Array of job post IDs.
 * @return array<int,array{count:int,min:float|null,max:float|null}>
 *   Keyed by job ID; jobs with no quotes get count=0, min/max=null.
 */
public static function get_quote_stats_bulk( array $job_ids ): array {
if ( empty( $job_ids ) ) {
return array();
}

global $wpdb;

// Cast every element to int to prevent SQL injection via the IN list.
$int_ids          = array_map( 'intval', $job_ids );
$ids_placeholder  = implode( ',', $int_ids );

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$rows = $wpdb->get_results(
"SELECT
pm_job.meta_value AS job_id,
COUNT(p.ID) AS quote_count,
MIN(CAST(pm_amount.meta_value AS DECIMAL(10,2))) AS min_amount,
MAX(CAST(pm_amount.meta_value AS DECIMAL(10,2))) AS max_amount
FROM {$wpdb->posts} p
INNER JOIN {$wpdb->postmeta} pm_job
ON p.ID = pm_job.post_id AND pm_job.meta_key = 'gd_job_id'
INNER JOIN {$wpdb->postmeta} pm_status
ON p.ID = pm_status.post_id AND pm_status.meta_key = 'gd_status'
AND pm_status.meta_value NOT IN ('withdrawn','expired')
INNER JOIN {$wpdb->postmeta} pm_amount
ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'gd_amount'
WHERE p.post_type = 'gd_quote'
AND p.post_status = 'publish'
AND pm_job.meta_value IN ({$ids_placeholder})
GROUP BY pm_job.meta_value"
);

$stats = array();
foreach ( $rows as $row ) {
$stats[ (int) $row->job_id ] = array(
'count' => (int) $row->quote_count,
'min'   => null !== $row->min_amount ? (float) $row->min_amount : null,
'max'   => null !== $row->max_amount ? (float) $row->max_amount : null,
);
}

// Fill zeros for jobs that have no active quotes.
foreach ( $int_ids as $id ) {
if ( ! isset( $stats[ $id ] ) ) {
$stats[ $id ] = array( 'count' => 0, 'min' => null, 'max' => null );
}
}

return $stats;
}

/**
 * AJAX: dismiss (reject) a job so it no longer appears in Available Jobs.
 *
 * Stores the job ID in the mover's gd_dismissed_jobs user-meta array.
 */
public function ajax_dismiss_job() {
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

$user_id   = get_current_user_id();
$dismissed = (array) get_user_meta( $user_id, 'gd_dismissed_jobs', true );

if ( ! in_array( $job_id, array_map( 'intval', $dismissed ), true ) ) {
$dismissed[] = $job_id;
update_user_meta( $user_id, 'gd_dismissed_jobs', $dismissed );
}

// Build the dismissed-job card HTML so the JS can inject it immediately
// into the Dismissed Jobs tab without requiring a page reload.
$card_html   = '';
$job_data    = $this->get_job( $job_id );
if ( ! is_wp_error( $job_data ) ) {
$type_labels = self::get_type_labels();
$pickup      = $job_data['pickup_location'] ?? array();
$dropoff     = $job_data['dropoff_location'] ?? array();
$type        = $job_data['job_type'] ?? '';
$title       = ! empty( $job_data['listing_title'] )
	? $job_data['listing_title']
	: ( $type_labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) ) );
$date        = ! empty( $job_data['date_requested'] )
	? date_i18n( get_option( 'date_format' ), strtotime( $job_data['date_requested'] ) )
	: '';
$from        = $pickup['suburb']  ?? $pickup['address']  ?? __( 'Unknown', 'go-deliver' );
$to          = $dropoff['suburb'] ?? $dropoff['address'] ?? __( 'Unknown', 'go-deliver' );
$stats       = self::get_quote_stats_bulk( array( $job_id ) );
$q_stats     = $stats[ $job_id ] ?? array( 'count' => 0, 'min' => null, 'max' => null );
$q_count     = (int) $q_stats['count'];

ob_start();
?>
<div class="gd-mover-card gd-dismissed-card" id="gd-dismissed-job-<?php echo esc_attr( $job_id ); ?>">
	<div class="gd-mover-card__header">
		<div>
			<div class="gd-mover-card__job-type"><?php echo esc_html( $title ); ?></div>
			<div class="gd-mover-card__suburb"><?php echo esc_html( $from ); ?> → <?php echo esc_html( $to ); ?></div>
		</div>
		<div style="text-align:right;">
			<?php if ( $q_count > 0 ) : ?>
				<div style="font-size:13px;color:var(--gd-text-muted);">
					<?php
					echo esc_html( sprintf( _n( '%d quote', '%d quotes', $q_count, 'go-deliver' ), $q_count ) );
					if ( null !== $q_stats['min'] ) {
						if ( $q_stats['min'] === $q_stats['max'] ) {
							echo ' · $' . esc_html( number_format( $q_stats['min'], 0 ) );
						} else {
							echo ' · $' . esc_html( number_format( $q_stats['min'], 0 ) ) . '–$' . esc_html( number_format( $q_stats['max'], 0 ) );
						}
					}
					?>
				</div>
			<?php endif; ?>
			<?php if ( $date ) : ?>
				<div style="font-size:12px;color:var(--gd-text-muted);margin-top:2px;"><?php echo esc_html( $date ); ?></div>
			<?php endif; ?>
		</div>
	</div>
	<div class="gd-mover-card__actions">
		<button
			type="button"
			class="gd-btn gd-btn--outline gd-btn--sm gd-restore-job-btn"
			data-job-id="<?php echo esc_attr( $job_id ); ?>"
		>
			<?php esc_html_e( 'Restore to Available Jobs', 'go-deliver' ); ?>
		</button>
	</div>
</div>
<?php
$card_html = ob_get_clean();
}

wp_send_json_success( array( 'message' => __( 'Job dismissed.', 'go-deliver' ), 'card_html' => $card_html ) );
}

/**
 * AJAX: restore a previously dismissed job back to Available Jobs.
 *
 * Removes the job ID from the mover's gd_dismissed_jobs user-meta array.
 */
public function ajax_restore_job() {
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

$user_id   = get_current_user_id();
$dismissed = (array) get_user_meta( $user_id, 'gd_dismissed_jobs', true );
$dismissed = array_values(
array_filter( $dismissed, function ( $id ) use ( $job_id ) {
return (int) $id !== $job_id;
} )
);
update_user_meta( $user_id, 'gd_dismissed_jobs', $dismissed );

wp_send_json_success( array( 'message' => __( 'Job restored to Available Jobs.', 'go-deliver' ) ) );
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
 * A job is expired when either:
 *  - it was posted more than gd_job_expiry_days days ago (age-based expiry), or
 *  - its gd_date_requested field represents a date that is now in the past
 *    (i.e. the actual moving date has already passed).
 *
 * @return void
 */
public function expire_jobs() {
$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );
$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$expiry_days} days" ) );
$today       = gmdate( 'Y-m-d' );

// 1. Age-based expiry: posted before the cutoff date.
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

// 2. Date-based expiry: the requested moving date is in the past.
$dated_query = new WP_Query(
array(
'post_type'      => 'gd_job',
'post_status'    => 'publish',
'posts_per_page' => -1,
'meta_query'     => array(
'relation' => 'AND',
array(
'key'     => 'gd_job_status',
'value'   => array( 'open', 'locked' ),
'compare' => 'IN',
),
array(
'key'     => 'gd_date_requested',
'value'   => $today,
'compare' => '<',
'type'    => 'DATE',
),
),
'no_found_rows'  => true,
'fields'         => 'ids',
)
);

foreach ( $dated_query->posts as $job_id ) {
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
'address' => isset( $_POST['pickup_address'] ) ? sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['pickup_address'] ) ) ) : '',
'suburb'  => isset( $_POST['pickup_suburb'] )  ? sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['pickup_suburb'] ) ) )  : '',
);

$dropoff_location = array(
'lat'     => isset( $_POST['dropoff_lat'] )     ? (float) $_POST['dropoff_lat']                           : 0.0,
'lng'     => isset( $_POST['dropoff_lng'] )     ? (float) $_POST['dropoff_lng']                           : 0.0,
'address' => isset( $_POST['dropoff_address'] ) ? sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['dropoff_address'] ) ) ) : '',
'suburb'  => isset( $_POST['dropoff_suburb'] )  ? sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['dropoff_suburb'] ) ) )  : '',
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
// Grant upload_files capability for this request so wp_handle_upload()
// allows the upload regardless of the user's role (gd_customer does not
// have this cap by default).
$upload_cap_filter = function ( $allcaps, $caps ) {
if ( in_array( 'upload_files', $caps, true ) ) {
$allcaps['upload_files'] = true;
}
return $allcaps;
};
add_filter( 'user_has_cap', $upload_cap_filter, 10, 2 );
try {
$attachment_id = media_handle_upload( 'gd_job_photo_tmp', 0 );
} finally {
remove_filter( 'user_has_cap', $upload_cap_filter, 10 );
}
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
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id = absint( $_POST['job_id'] ?? 0 );
if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

// Validate and store the cancellation reason when provided.
$allowed_reasons = array( 'no_longer_needed', 'mover_didnt_read' );
$cancel_reason   = isset( $_POST['cancel_reason'] ) ? sanitize_key( wp_unslash( $_POST['cancel_reason'] ) ) : '';
if ( $cancel_reason && ! in_array( $cancel_reason, $allowed_reasons, true ) ) {
wp_send_json_error( array( 'message' => __( 'Invalid cancellation reason.', 'go-deliver' ) ) );
}

$result = $this->cancel_job( $job_id, get_current_user_id() );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

if ( $cancel_reason ) {
update_post_meta( $job_id, 'gd_cancel_reason', $cancel_reason );
}

wp_send_json_success( array( 'message' => __( 'Job cancelled.', 'go-deliver' ) ) );
}

/**
 * AJAX: re-post a cancelled job as a new job, optionally excluding a mover.
 *
 * Copies all job meta from the original job to create a fresh listing.
 * When exclude_mover_id is supplied, that mover will not see the new job.
 */
public function ajax_repost_job() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$job_id     = absint( isset( $_POST['job_id'] )          ? wp_unslash( $_POST['job_id'] )          : 0 );
$exclude_id = absint( isset( $_POST['exclude_mover_id'] ) ? wp_unslash( $_POST['exclude_mover_id'] ) : 0 );

if ( ! $job_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid job ID.', 'go-deliver' ) ) );
}

$post = get_post( $job_id );
if ( ! $post || 'gd_job' !== $post->post_type ) {
wp_send_json_error( array( 'message' => __( 'Job not found.', 'go-deliver' ) ) );
}

$current_user_id    = get_current_user_id();
$stored_customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );
if ( $stored_customer_id !== $current_user_id ) {
wp_send_json_error( array( 'message' => __( 'You do not own this job.', 'go-deliver' ) ) );
}

$current_status = get_post_meta( $job_id, 'gd_job_status', true );
if ( 'cancelled' !== $current_status ) {
wp_send_json_error( array( 'message' => __( 'Only cancelled jobs can be re-posted.', 'go-deliver' ) ) );
}

// Build data array from the original job's meta.
$pickup_raw  = json_decode( get_post_meta( $job_id, 'gd_pickup_location', true ), true ) ?: array();
$dropoff_raw = json_decode( get_post_meta( $job_id, 'gd_dropoff_location', true ), true ) ?: array();
$form_data   = json_decode( get_post_meta( $job_id, 'gd_form_data', true ), true ) ?: array();
$photos_raw  = json_decode( get_post_meta( $job_id, 'gd_photos', true ), true ) ?: array();

// Carry forward any existing excluded movers and add the newly-requested one.
$stored_excluded   = get_post_meta( $job_id, 'gd_excluded_mover_ids', true );
$existing_excluded = is_array( $stored_excluded ) ? array_map( 'intval', $stored_excluded ) : array();
$existing_excluded = array_filter( $existing_excluded, function ( int $id ) { return $id > 0; } );

if ( $exclude_id ) {
$existing_excluded[] = $exclude_id;
}

$excluded_mover_ids = array_values( array_unique( $existing_excluded ) );

$data = array(
'job_type'             => get_post_meta( $job_id, 'gd_job_type', true ),
'listing_title'        => get_post_meta( $job_id, 'gd_listing_title', true ),
'pickup_location'      => $pickup_raw,
'dropoff_location'     => $dropoff_raw,
'date_requested'       => get_post_meta( $job_id, 'gd_date_requested', true ),
'labour_pickup'        => (bool) get_post_meta( $job_id, 'gd_labour_pickup', true ),
'labour_dropoff'       => (bool) get_post_meta( $job_id, 'gd_labour_dropoff', true ),
'inventory'            => get_post_meta( $job_id, 'gd_inventory', true ),
'special_instructions' => get_post_meta( $job_id, 'gd_special_instructions', true ),
'customer_id'          => $current_user_id,
'access_notes'         => get_post_meta( $job_id, 'gd_access_notes', true ),
'form_data'            => $form_data,
'photos'               => array_map( 'intval', $photos_raw ),
// Pass excluded IDs so create_job() saves them BEFORE sending notifications.
'excluded_mover_ids'   => $excluded_mover_ids,
);

$new_job_id = $this->create_job( $data );

if ( is_wp_error( $new_job_id ) ) {
wp_send_json_error( array( 'message' => $new_job_id->get_error_message() ) );
}

// Link the new job back to the original for reference.
update_post_meta( $new_job_id, 'gd_reposted_from', $job_id );

wp_send_json_success( array( 'job_id' => $new_job_id ) );
}

/**
 * AJAX: get available jobs for the mover dashboard.
 *
 * Returns an HTML fragment of job cards for the #gd-available-jobs-list
 * container.  Optionally filtered by job_type.
 * Dismissed jobs are excluded for movers (admins see everything).
 */
public function ajax_get_available_jobs() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to view available jobs.', 'go-deliver' ) ), 403 );
}

$user_id  = get_current_user_id();
$is_admin = user_can( $user_id, 'manage_options' );

if ( ! $is_admin ) {
$roles    = (array) wp_get_current_user()->roles;
$is_mover = in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true );
if ( ! $is_mover ) {
wp_send_json_error( array( 'message' => __( 'Access denied.', 'go-deliver' ) ), 403 );
}
}

$job_type_filter = isset( $_POST['job_type'] ) ? sanitize_key( wp_unslash( $_POST['job_type'] ) ) : '';

$jobs = $is_admin
? $this->get_all_open_jobs()
: $this->get_open_jobs_for_mover( $user_id );

// Exclude jobs the mover has dismissed (admins always see everything).
if ( ! $is_admin ) {
$dismissed = array_map( 'intval', (array) get_user_meta( $user_id, 'gd_dismissed_jobs', true ) );
if ( ! empty( $dismissed ) ) {
$jobs = array_values( array_filter( $jobs, function ( $job ) use ( $dismissed ) {
return ! in_array( (int) $job['id'], $dismissed, true );
} ) );
}

// Exclude jobs where this mover has been explicitly blocked by the customer.
$jobs = array_values( array_filter( $jobs, function ( $job ) use ( $user_id ) {
$stored   = get_post_meta( (int) $job['id'], 'gd_excluded_mover_ids', true );
$excluded = is_array( $stored ) ? array_map( 'intval', $stored ) : array();
return ! in_array( (int) $user_id, $excluded, true );
} ) );
}

// Count jobs by type before filtering, for the filter bar counts.
$type_counts = array();
foreach ( $jobs as $job ) {
$t = $job['job_type'] ?? '';
if ( $t !== '' ) {
$type_counts[ $t ] = ( $type_counts[ $t ] ?? 0 ) + 1;
}
}

// Apply optional job-type filter.
if ( ! empty( $job_type_filter ) ) {
$jobs = array_values( array_filter( $jobs, function ( $job ) use ( $job_type_filter ) {
return isset( $job['job_type'] ) && $job['job_type'] === $job_type_filter;
} ) );
}

if ( empty( $jobs ) ) {
wp_send_json_success( array( 'html' => '', 'counts' => $type_counts ) );
}

// Fetch quote counts and bid ranges for all jobs in one query.
$job_ids     = array_column( $jobs, 'id' );
$quote_stats = self::get_quote_stats_bulk( $job_ids );

$job_type_labels = self::get_type_labels();

$status_labels = array(
'open'   => __( 'New', 'go-deliver' ),
'locked' => __( 'Receiving Quotes', 'go-deliver' ),
);

$messaging_page_id  = (int) get_option( 'gd_messaging_page_id', 0 );
$messaging_base_url = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();

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
$status_label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
$title         = self::get_display_title( (int) $job['id'] );
$pickup_name   = $pickup['suburb'] ?? $pickup['address'] ?? __( 'Unknown', 'go-deliver' );
$dropoff_name  = $dropoff['suburb'] ?? $dropoff['address'] ?? __( 'Unknown', 'go-deliver' );
$date_raw      = ! empty( $job['date_requested'] ) ? strtotime( $job['date_requested'] ) : 0;
$date          = $date_raw ? date_i18n( get_option( 'date_format' ), $date_raw ) : '';
$date_flexible = ! empty( $job['form_data']['date_flexible'] );
$time_since    = ! empty( $job['created_at'] ) ? self::time_since( $job['created_at'] ) : '';
$posted_utc    = ! empty( $job['created_at'] ) ? strtotime( get_gmt_from_date( $job['created_at'] ) ) : 0;
$posted_label  = $time_since ? sprintf( __( 'Posted %s', 'go-deliver' ), $time_since ) : '';
$q_stats       = $quote_stats[ $job['id'] ] ?? array( 'count' => 0, 'min' => null, 'max' => null );
$q_count       = (int) $q_stats['count'];

$quote_title = 0 === $q_count
	? __( '0 quotes yet', 'go-deliver' )
	: sprintf(
		/* translators: %d: number of quotes */
		_n( '%d quote', '%d quotes', $q_count, 'go-deliver' ),
		$q_count
	);
$quote_subtitle = 0 === $q_count ? __( 'Be the first!', 'go-deliver' ) : __( 'View quotes', 'go-deliver' );

$labour_pickup  = ! empty( $job['labour_pickup'] );
$labour_dropoff = ! empty( $job['labour_dropoff'] );
$is_vehicle_job = in_array( $type, array( 'vehicle_or_boat', 'vehicle', 'car', 'motorcycle', 'other_vehicle', 'boat' ), true );

if ( $is_vehicle_job ) {
	$requirements_title = __( 'Vehicle transport', 'go-deliver' );
	$requirements_text  = __( 'Special handling', 'go-deliver' );
} elseif ( $labour_pickup && $labour_dropoff ) {
	$requirements_title = __( 'Help required', 'go-deliver' );
	$requirements_text  = __( 'Load & unload', 'go-deliver' );
} elseif ( $labour_pickup ) {
	$requirements_title = __( 'Help required', 'go-deliver' );
	$requirements_text  = __( 'Load only', 'go-deliver' );
} elseif ( $labour_dropoff ) {
	$requirements_title = __( 'Help required', 'go-deliver' );
	$requirements_text  = __( 'Unload only', 'go-deliver' );
} else {
	$requirements_title = __( 'Standard items', 'go-deliver' );
	$requirements_text  = __( 'No special gear', 'go-deliver' );
}

$urgency_label = '';
if ( $date_flexible ) {
	$urgency_label = __( 'Flexible', 'go-deliver' );
} elseif ( $date_raw && ( $date_raw - current_time( 'timestamp' ) ) <= ( 3 * DAY_IN_SECONDS ) ) {
	$urgency_label = __( 'Urgent', 'go-deliver' );
}

$search_text = implode(
	' ',
	array_filter(
		array(
			$title,
			$type_label,
			$pickup_name,
			$dropoff_name,
			$date,
			$requirements_title,
			$requirements_text,
		)
	)
);
$search_text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search_text ) : strtolower( $search_text );
?>
<div class="gd-job-card" data-job-id="<?php echo esc_attr( $job['id'] ); ?>" data-job-search="<?php echo esc_attr( $search_text ); ?>">
<div class="gd-job-card__topline">
<div class="gd-job-card__pill-group">
<span class="gd-job-card__pill gd-job-card__pill--type"><?php echo esc_html( $type_label ); ?></span>
<span class="gd-job-card__pill gd-job-card__pill--status gd-job-card__pill--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
</div>
<div class="gd-job-card__meta-top">
<?php if ( $urgency_label ) : ?>
<span class="gd-job-card__pill gd-job-card__pill--accent gd-job-card__pill--<?php echo esc_attr( strtolower( $urgency_label ) ); ?>"><?php echo esc_html( $urgency_label ); ?></span>
<?php endif; ?>
<?php if ( $posted_label ) : ?>
<span class="gd-job-card__time-since"<?php echo $posted_utc ? ' data-gd-posted-utc="' . esc_attr( $posted_utc ) . '"' : ''; ?>><?php echo esc_html( $posted_label ); ?></span>
<?php endif; ?>
<button
type="button"
class="gd-job-card__dismiss gd-dismiss-job-btn"
data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
title="<?php esc_attr_e( 'Hide this job', 'go-deliver' ); ?>"
aria-label="<?php esc_attr_e( 'Hide this job', 'go-deliver' ); ?>"
>
×
</button>
</div>
</div>

<h3 class="gd-job-card__title"><?php echo esc_html( $title ); ?></h3>

<div class="gd-job-card__route">
<div class="gd-job-card__route-stop">
<span class="gd-job-card__route-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s-6-4.8-6-10a6 6 0 1112 0c0 5.2-6 10-6 10zm0-8.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"></path></svg>
</span>
<span><?php echo esc_html( $pickup_name ); ?></span>
</div>
<span class="gd-job-card__route-arrow" aria-hidden="true">→</span>
<div class="gd-job-card__route-stop">
<span><?php echo esc_html( $dropoff_name ); ?></span>
</div>
</div>

<div class="gd-job-card__stats">
<?php if ( $date ) : ?>
<div class="gd-job-card__stat">
<span class="gd-job-card__stat-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"></rect><line x1="16" y1="3" x2="16" y2="7"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="3" y1="11" x2="21" y2="11"></line></svg>
</span>
<div class="gd-job-card__stat-text">
<strong><?php echo esc_html( $date ); ?></strong>
<span><?php echo esc_html( $date_flexible ? __( '(Flexible)', 'go-deliver' ) : __( 'Scheduled date', 'go-deliver' ) ); ?></span>
</div>
</div>
<?php endif; ?>
<div class="gd-job-card__stat<?php echo $q_count > 0 ? ' gd-job-card__stat--link' : ''; ?>"<?php echo $q_count > 0 ? ' role="button" tabindex="0" title="' . esc_attr__( 'View job details and quotes', 'go-deliver' ) . '"' : ''; ?>>
<span class="gd-job-card__stat-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" focusable="false"><path d="M21 11.5a8.5 8.5 0 01-14.5 6L3 21l1.7-4.2A8.5 8.5 0 1112.5 20H21"></path><line x1="8.5" y1="10.5" x2="15.5" y2="10.5"></line><line x1="8.5" y1="14" x2="12.5" y2="14"></line></svg>
</span>
<div class="gd-job-card__stat-text">
<strong><?php echo esc_html( $quote_title ); ?></strong>
<span><?php echo esc_html( $quote_subtitle ); ?></span>
</div>
</div>
<div class="gd-job-card__stat">
<span class="gd-job-card__stat-icon" aria-hidden="true">
<svg viewBox="0 0 24 24" focusable="false"><path d="M3 16h6l2-3h5l2-3h3"></path><path d="M7 16v2a2 2 0 104 0v-2"></path><path d="M15 13v5a2 2 0 104 0v-5"></path><circle cx="6" cy="18" r="1.5"></circle><circle cx="17" cy="18" r="1.5"></circle><path d="M10 13V8l3-2 2 2"></path></svg>
</span>
<div class="gd-job-card__stat-text">
<strong><?php echo esc_html( $requirements_title ); ?></strong>
<span><?php echo esc_html( $requirements_text ); ?></span>
</div>
</div>
</div>

<div class="gd-job-card__actions">
<button
type="button"
class="gd-btn gd-job-card__action gd-job-card__action--primary gd-quote-btn"
data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
>
<?php esc_html_e( 'Quote Job', 'go-deliver' ); ?>
</button>
<a
href="<?php echo esc_url( add_query_arg( array( 'job_id' => $job['id'], 'participant_id' => (int) ( $job['customer_id'] ?? 0 ) ), $messaging_base_url ) ); ?>"
class="gd-btn gd-job-card__action gd-job-card__action--secondary"
>
<?php esc_html_e( 'Message Customer', 'go-deliver' ); ?>
</a>
<button
type="button"
class="gd-job-card__action-link gd-job-view-btn"
data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
>
<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
</button>
</div>

</div><!-- /.gd-job-card -->
<?php endforeach; ?>
</div><!-- /.gd-jobs-grid -->
<?php
$html = ob_get_clean();

wp_send_json_success( array( 'html' => $html, 'counts' => $type_counts ) );
}

/**
 * AJAX: render job detail as HTML (used by the mover-dashboard modal).
 */
public function ajax_get_job_detail_html() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

$job_id     = absint( $_POST['job_id'] ?? 0 );
$modal_view = sanitize_key( wp_unslash( $_POST['view'] ?? '' ) );
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
