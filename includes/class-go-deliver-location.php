<?php
/**
 * Location and geo-filtering utilities for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Location
 */
class Go_Deliver_Location {

/** Earth radius in kilometres used in the Haversine formula. */
const EARTH_RADIUS_KM = 6371.0;

/** Nominatim User-Agent — required by Nominatim usage policy. */
const NOMINATIM_USER_AGENT = 'GoDeliver/1.0 (contact@go-deliver.co.nz)';

/** Sentinel value stored when a mover covers all of New Zealand. */
const ALL_NZ_RADIUS = 9999;

// =========================================================================
// Distance calculation.
// =========================================================================

/**
 * Calculate the great-circle distance between two points using the Haversine formula.
 *
 * @param float $lat1 Latitude of point 1 (decimal degrees).
 * @param float $lng1 Longitude of point 1 (decimal degrees).
 * @param float $lat2 Latitude of point 2 (decimal degrees).
 * @param float $lng2 Longitude of point 2 (decimal degrees).
 * @return float Distance in kilometres.
 */
public function haversine_distance( $lat1, $lng1, $lat2, $lng2 ) {
$lat1 = deg2rad( (float) $lat1 );
$lng1 = deg2rad( (float) $lng1 );
$lat2 = deg2rad( (float) $lat2 );
$lng2 = deg2rad( (float) $lng2 );

$dlat = $lat2 - $lat1;
$dlng = $lng2 - $lng1;

$a = sin( $dlat / 2 ) ** 2
+ cos( $lat1 ) * cos( $lat2 ) * sin( $dlng / 2 ) ** 2;

$c = 2 * asin( sqrt( $a ) );

return self::EARTH_RADIUS_KM * $c;
}

// =========================================================================
// Job filtering.
// =========================================================================

/**
 * Filter a list of jobs to those within a mover's service radius and matching job types.
 *
 * @param array $jobs     Array of job data arrays (as returned by Go_Deliver_Jobs::get_job()).
 * @param int   $mover_id Mover user ID.
 * @return array Filtered job array.
 */
public function filter_jobs_by_radius( $jobs, $mover_id ) {
$mover_id = (int) $mover_id;

$base_lat   = (float) get_user_meta( $mover_id, 'gd_mover_base_lat', true );
$base_lng   = (float) get_user_meta( $mover_id, 'gd_mover_base_lng', true );
$radius_km  = (float) get_user_meta( $mover_id, 'gd_mover_radius', true );
$job_types  = get_user_meta( $mover_id, 'gd_mover_job_types', true );

if ( ! is_array( $job_types ) ) {
$job_types = array();
}

if ( ! $radius_km ) {
return array();
}

$all_nz = ( self::ALL_NZ_RADIUS === (int) $radius_km );

if ( ! $all_nz && ( ! $base_lat || ! $base_lng ) ) {
return array();
}

$filtered = array();

foreach ( $jobs as $job ) {
// Filter by job type.
if ( ! empty( $job_types ) && ! in_array( $job['job_type'], $job_types, true ) ) {
continue;
}

if ( $all_nz ) {
$filtered[] = $job;
continue;
}

$pickup  = $job['pickup_location'] ?? array();
$job_lat = isset( $pickup['lat'] ) ? (float) $pickup['lat'] : 0.0;
$job_lng = isset( $pickup['lng'] ) ? (float) $pickup['lng'] : 0.0;

// If coordinates are missing (e.g. geocoding failed at job-creation time),
// attempt to geocode now and persist so it won't need re-geocoding next time.
if ( ! $job_lat || ! $job_lng ) {
$address_or_suburb = ( $pickup['address'] ?? '' ) ?: ( $pickup['suburb'] ?? '' );
if ( ! empty( $address_or_suburb ) ) {
$coords = $this->geocode_address( $address_or_suburb );
if ( ! is_wp_error( $coords ) ) {
$job_lat         = $coords['lat'];
$job_lng         = $coords['lng'];
$pickup['lat']   = $job_lat;
$pickup['lng']   = $job_lng;
$job['pickup_location'] = $pickup;
// Persist so we don't re-geocode on every page load.
if ( ! empty( $job['id'] ) ) {
$updated = update_post_meta( (int) $job['id'], 'gd_pickup_location', wp_json_encode( $pickup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
if ( false === $updated ) {
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
error_log( 'Go Deliver: failed to persist geocoded coordinates for job ' . (int) $job['id'] );
}
}
}
}

// If coordinates are still unavailable after geocoding, include the job
// anyway — never silently drop it — so movers matching on job type can see it.
if ( ! $job_lat || ! $job_lng ) {
$filtered[] = $job;
continue;
}
}

$distance = $this->haversine_distance( $base_lat, $base_lng, $job_lat, $job_lng );
if ( $distance <= $radius_km ) {
$job['distance_km'] = round( $distance, 1 );
$filtered[] = $job;
}
}

// Sort by proximity (All of NZ jobs won't have distance_km set).
usort(
$filtered,
function ( $a, $b ) {
$da = isset( $a['distance_km'] ) ? $a['distance_km'] : PHP_INT_MAX;
$db = isset( $b['distance_km'] ) ? $b['distance_km'] : PHP_INT_MAX;
return $da <=> $db;
}
);

return $filtered;
}

// =========================================================================
// Geocoding.
// =========================================================================

/**
 * Geocode a free-text address using the OpenStreetMap Nominatim API.
 *
 * @param string $address Address to geocode.
 * @return array|WP_Error ['lat'=>float, 'lng'=>float] or WP_Error.
 */
public function geocode_address( $address ) {
$address = sanitize_text_field( $address );
if ( empty( $address ) ) {
return new WP_Error( 'empty_address', __( 'Address cannot be empty.', 'go-deliver' ) );
}

$url = add_query_arg(
array(
'q'              => rawurlencode( $address ),
'format'         => 'json',
'limit'          => 1,
'addressdetails' => 1,
),
'https://nominatim.openstreetmap.org/search'
);

$response = wp_remote_get(
$url,
array(
'headers' => array( 'User-Agent' => self::NOMINATIM_USER_AGENT ),
'timeout' => 10,
)
);

if ( is_wp_error( $response ) ) {
return $response;
}

$status_code = wp_remote_retrieve_response_code( $response );
if ( 200 !== (int) $status_code ) {
return new WP_Error( 'geocode_error', __( 'Geocoding service returned an error.', 'go-deliver' ) );
}

$results = json_decode( wp_remote_retrieve_body( $response ), true );
if ( empty( $results ) || ! isset( $results[0]['lat'] ) ) {
return new WP_Error( 'not_found', __( 'Address could not be found.', 'go-deliver' ) );
}

return array(
'lat' => (float) $results[0]['lat'],
'lng' => (float) $results[0]['lon'],
);
}

/**
 * Reverse-geocode a latitude/longitude pair to a suburb name.
 *
 * @param float $lat Latitude.
 * @param float $lng Longitude.
 * @return string|WP_Error Suburb string or WP_Error.
 */
public function get_suburb_from_latlong( $lat, $lng ) {
$url = add_query_arg(
array(
'lat'            => (float) $lat,
'lon'            => (float) $lng,
'format'         => 'json',
'addressdetails' => 1,
),
'https://nominatim.openstreetmap.org/reverse'
);

$response = wp_remote_get(
$url,
array(
'headers' => array( 'User-Agent' => self::NOMINATIM_USER_AGENT ),
'timeout' => 10,
)
);

if ( is_wp_error( $response ) ) {
return $response;
}

$status_code = wp_remote_retrieve_response_code( $response );
if ( 200 !== (int) $status_code ) {
return new WP_Error( 'reverse_geocode_error', __( 'Reverse geocoding service returned an error.', 'go-deliver' ) );
}

$result = json_decode( wp_remote_retrieve_body( $response ), true );

if ( empty( $result ) || ! isset( $result['address'] ) ) {
return new WP_Error( 'not_found', __( 'Location could not be determined.', 'go-deliver' ) );
}

$address = $result['address'];

// NZ address hierarchy: suburb > town > city > county.
$suburb = $address['suburb']
?? $address['town']
?? $address['city']
?? $address['county']
?? '';

return sanitize_text_field( $suburb );
}
}
