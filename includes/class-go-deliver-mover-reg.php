<?php
/**
 * Mover registration and approval for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Mover_Reg
 */
class Go_Deliver_Mover_Reg {

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_register_mover',        array( $this, 'ajax_register_mover' ) );
add_action( 'wp_ajax_nopriv_gd_register_mover', array( $this, 'ajax_register_mover' ) );
add_action( 'wp_ajax_gd_mover_dismiss_tour',    array( $this, 'ajax_dismiss_tour' ) );
}

// =========================================================================
// Core mover management.
// =========================================================================

/**
 * Register a new mover.
 *
 * @param array $data {
 *   @type string $username
 *   @type string $email
 *   @type string $password
 *   @type string $first_name
 *   @type string $last_name
 *   @type string $phone
 *   @type string $base_suburb
 *   @type float  $base_lat
 *   @type float  $base_lng
 *   @type int    $radius           Service radius in kilometres.
 *   @type array  $job_types        Accepted job type slugs.
 *   @type array  $documents        Each element: ['type'=>string, 'file_id'=>int].
 * }
 * @return int|WP_Error New user ID or WP_Error.
 */
public function register_mover( $data ) {
$username = sanitize_user( $data['username'] ?? '' );
$email    = sanitize_email( $data['email'] ?? '' );
$password = $data['password'] ?? '';

if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
return new WP_Error( 'missing_fields', __( 'Username, email, and password are required.', 'go-deliver' ) );
}

if ( strlen( $password ) < 8 ) {
return new WP_Error( 'password_too_short', __( 'Password must be at least 8 characters.', 'go-deliver' ) );
}

if ( ! is_email( $email ) ) {
return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'go-deliver' ) );
}

if ( username_exists( $username ) ) {
return new WP_Error( 'username_exists', __( 'That username is already taken.', 'go-deliver' ) );
}

if ( email_exists( $email ) ) {
return new WP_Error( 'email_exists', __( 'That email address is already registered.', 'go-deliver' ) );
}

$user_id = wp_create_user( $username, $password, $email );
if ( is_wp_error( $user_id ) ) {
return $user_id;
}

// Set role.
$user = new WP_User( $user_id );
$user->set_role( 'gd_mover' );

// Update display name.
wp_update_user(
array(
'ID'           => $user_id,
'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
'display_name' => trim(
sanitize_text_field( $data['first_name'] ?? '' ) . ' ' .
sanitize_text_field( $data['last_name'] ?? '' )
),
)
);

$job_types = array();
if ( ! empty( $data['job_types'] ) && is_array( $data['job_types'] ) ) {
foreach ( $data['job_types'] as $jt ) {
$job_types[] = sanitize_text_field( $jt );
}
}

// Set mover-specific user meta.
update_user_meta( $user_id, 'gd_mover_status',          'pending' );
update_user_meta( $user_id, 'gd_mover_base_lat',        isset( $data['base_lat'] ) ? (float) $data['base_lat'] : 0.0 );
update_user_meta( $user_id, 'gd_mover_base_lng',        isset( $data['base_lng'] ) ? (float) $data['base_lng'] : 0.0 );
update_user_meta( $user_id, 'gd_mover_base_suburb',     sanitize_text_field( $data['base_suburb'] ?? '' ) );
update_user_meta( $user_id, 'gd_mover_radius',          isset( $data['radius'] ) ? (int) $data['radius'] : 0 );
update_user_meta( $user_id, 'gd_mover_job_types',       $job_types );
update_user_meta( $user_id, 'gd_wallet_balance',        0 );
update_user_meta( $user_id, 'gd_notification_frequency', 'instant' );
update_user_meta( $user_id, 'gd_phone',                 sanitize_text_field( $data['phone'] ?? '' ) );

// Save documents.
if ( ! empty( $data['documents'] ) && is_array( $data['documents'] ) ) {
foreach ( $data['documents'] as $doc ) {
if ( is_array( $doc ) && ! empty( $doc['type'] ) && ! empty( $doc['file_id'] ) ) {
$file_url = wp_get_attachment_url( (int) $doc['file_id'] );
if ( $file_url ) {
Go_Deliver_DB::save_document( $user_id, sanitize_text_field( $doc['type'] ), $file_url );
}
}
}
}

	// Notify admin and send a welcome email to the new mover.
	$this->notify_admin_new_mover( $user_id );
	$this->notify_mover_welcome_email( $user_id );

return $user_id;
}

/**
 * Email the site admin(s) when a new mover registers.
 *
 * Recipient is always the plugin Admin / Support Email from settings.
 *
 * This ensures plugin-generated admin notifications are sent to the
 * specifically configured destination only.
 *
 * @param int $user_id New mover's user ID.
 */
	private function notify_admin_new_mover( $user_id ) {
$mover = get_userdata( $user_id );
if ( ! $mover ) {
return;
}

$subject = sprintf(
/* translators: %s: site name */
__( '[%s] New Mover Registration Pending Approval', 'go-deliver' ),
get_bloginfo( 'name' )
);

$message = sprintf(
/* translators: 1: mover name, 2: mover email, 3: admin URL */
__( "A new mover has registered and requires approval.\n\nName: %1\$s\nEmail: %2\$s\n\nApprove or reject: %3\$s", 'go-deliver' ),
$mover->display_name,
$mover->user_email,
admin_url( 'admin.php?page=go-deliver-movers' )
);

	$recipient_email = gd_get_admin_email();
	if ( ! is_email( $recipient_email ) ) {
		return;
	}

	Go_Deliver_Notifications::send_plain_email( $recipient_email, $subject, $message );
	}

	/**
	 * Send a welcome email to the newly registered mover.
	 *
	 * @param int $user_id New mover user ID.
	 */
	private function notify_mover_welcome_email( $user_id ) {
		$mover = get_userdata( (int) $user_id );
		if ( ! $mover || ! $mover->user_email ) {
			return;
		}

		$site_name         = get_bloginfo( 'name' );
		$mover_first_name  = $mover->first_name ?: $mover->display_name;
		$mover_dashboard_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
		$dashboard_url      = $mover_dashboard_id ? get_permalink( $mover_dashboard_id ) : home_url();
		$login_url          = wp_login_url( $dashboard_url );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] We Received Your Mover Application', 'go-deliver' ),
			$site_name
		);

		$template_path = GD_PLUGIN_DIR . 'templates/emails/mover-welcome.php';
		if ( ! file_exists( $template_path ) ) {
			return;
		}

		$from_name    = get_option( 'gd_email_from_name', $site_name );
		$from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );
		$headers      = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		$vars = array(
			'mover_first_name' => $mover_first_name,
			'login_url'        => $login_url,
			'site_name'        => $site_name,
			'site_url'         => home_url(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional for template rendering, keys are caller-controlled.
		extract( $vars, EXTR_SKIP );
		include $template_path;
		$html = ob_get_clean();

		wp_mail( $mover->user_email, wp_strip_all_tags( $subject ), $html, $headers );
	}

	/**
	 * Send an approval email to a mover after their account is approved.
	 *
	 * @param int $user_id Mover user ID.
	 */
	private function notify_mover_approved_email( $user_id ) {
		$mover = get_userdata( (int) $user_id );
		if ( ! $mover || ! $mover->user_email ) {
			return;
		}

		$site_name          = get_bloginfo( 'name' );
		$mover_first_name   = $mover->first_name ?: $mover->display_name;
		$mover_dashboard_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
		$dashboard_url      = $mover_dashboard_id ? get_permalink( $mover_dashboard_id ) : home_url();
		$login_url          = wp_login_url( $dashboard_url );
		$template_path      = GD_PLUGIN_DIR . 'templates/emails/mover-approved.php';

		if ( ! file_exists( $template_path ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your Mover Account Has Been Approved', 'go-deliver' ),
			$site_name
		);

		$from_name    = get_option( 'gd_email_from_name', $site_name );
		$from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );
		$headers      = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_address ),
		);

		$vars = array(
			'mover_first_name' => $mover_first_name,
			'login_url'        => $login_url,
			'site_name'        => $site_name,
			'site_url'         => home_url(),
		);

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional for template rendering, keys are caller-controlled.
		extract( $vars, EXTR_SKIP );
		include $template_path;
		$html = ob_get_clean();

		wp_mail( $mover->user_email, wp_strip_all_tags( $subject ), $html, $headers );
	}

/**
 * Approve a mover account.
 *
 * @param int $user_id  Mover user ID.
 * @param int $admin_id Admin user ID.
 * @return true|WP_Error
 */
public function approve_mover( $user_id, $admin_id ) {
if ( ! user_can( (int) $admin_id, 'manage_options' ) ) {
return new WP_Error( 'permission_denied', __( 'Only administrators can approve movers.', 'go-deliver' ) );
}

update_user_meta( (int) $user_id, 'gd_mover_status', 'approved' );
$this->notify_mover_approved_email( (int) $user_id );

return true;
}

/**
 * Reject a mover account.
 *
 * @param int    $user_id  Mover user ID.
 * @param int    $admin_id Admin user ID.
 * @param string $reason   Optional rejection reason.
 * @return true|WP_Error
 */
public function reject_mover( $user_id, $admin_id, $reason = '' ) {
if ( ! user_can( (int) $admin_id, 'manage_options' ) ) {
return new WP_Error( 'permission_denied', __( 'Only administrators can reject movers.', 'go-deliver' ) );
}

update_user_meta( (int) $user_id, 'gd_mover_status', 'rejected' );

$mover = get_userdata( (int) $user_id );
if ( $mover ) {
$subject = sprintf(
/* translators: %s: site name */
__( '[%s] Your Mover Application Was Not Approved', 'go-deliver' ),
get_bloginfo( 'name' )
);

$message = __( 'Unfortunately, your mover application has not been approved at this time.', 'go-deliver' );
if ( ! empty( $reason ) ) {
$message .= "\n\n" . sprintf(
/* translators: %s: rejection reason */
__( 'Reason: %s', 'go-deliver' ),
sanitize_textarea_field( $reason )
);
}

Go_Deliver_Notifications::send_plain_email( $mover->user_email, $subject, $message );
}

return true;
}

/**
 * Suspend a mover account.
 *
 * @param int $user_id  Mover user ID.
 * @param int $admin_id Admin user ID.
 * @return true|WP_Error
 */
public function suspend_mover( $user_id, $admin_id ) {
if ( ! user_can( (int) $admin_id, 'manage_options' ) ) {
return new WP_Error( 'permission_denied', __( 'Only administrators can suspend movers.', 'go-deliver' ) );
}

update_user_meta( (int) $user_id, 'gd_mover_status', 'suspended' );

return true;
}

// =========================================================================
// Document upload.
// =========================================================================

/**
 * Process a document file upload using WordPress media functions.
 *
 * @param string $file_key $_FILES key.
 * @param int    $user_id  Associated user ID.
 * @return int|WP_Error Attachment ID or WP_Error.
 */
public function handle_document_upload( $file_key, $user_id ) {
if ( ! isset( $_FILES[ $file_key ] ) ) {
return new WP_Error( 'no_file', __( 'No file provided.', 'go-deliver' ) );
}

if ( ! function_exists( 'wp_handle_upload' ) ) {
require_once ABSPATH . 'wp-admin/includes/file.php';
}

if ( ! function_exists( 'media_handle_upload' ) ) {
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
}

$overrides = array( 'test_form' => false );
$uploaded  = wp_handle_upload( $_FILES[ $file_key ], $overrides );

if ( isset( $uploaded['error'] ) ) {
return new WP_Error( 'upload_error', $uploaded['error'] );
}

if ( empty( $uploaded['file'] ) ) {
return new WP_Error( 'upload_failed', __( 'File upload failed.', 'go-deliver' ) );
}

// Create an attachment post for the uploaded file.
$attachment_data = array(
'post_mime_type' => $uploaded['type'],
'post_title'     => sanitize_file_name( basename( $uploaded['file'] ) ),
'post_content'   => '',
'post_status'    => 'inherit',
'post_author'    => (int) $user_id,
);

$attachment_id = wp_insert_attachment( $attachment_data, $uploaded['file'] );
if ( is_wp_error( $attachment_id ) ) {
return $attachment_id;
}

$metadata = wp_generate_attachment_metadata( $attachment_id, $uploaded['file'] );
wp_update_attachment_metadata( $attachment_id, $metadata );

return $attachment_id;
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: register a new mover (public endpoint).
 */
public function ajax_register_mover() {
check_ajax_referer( 'gd_mover_registration', 'nonce' );

if ( empty( $_POST['terms_agreed'] ) ) {
wp_send_json_error( array( 'message' => __( 'You must agree to the Terms & Conditions to register.', 'go-deliver' ) ) );
}

$data = array(
'username'    => sanitize_user( wp_unslash( $_POST['username'] ?? '' ) ),
'email'       => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
'password'    => $_POST['password'] ?? '',
'first_name'  => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
'last_name'   => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
'phone'       => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
'base_suburb' => sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['base_suburb'] ?? '' ) ) ),
'base_lat'    => isset( $_POST['base_lat'] ) ? (float) $_POST['base_lat'] : 0.0,
'base_lng'    => isset( $_POST['base_lng'] ) ? (float) $_POST['base_lng'] : 0.0,
'radius'      => isset( $_POST['radius'] ) ? absint( $_POST['radius'] ) : 0,
'job_types'   => array(),
'documents'   => array(),
);
$verification_token = sanitize_text_field( wp_unslash( $_POST['email_verification_token'] ?? '' ) );

if ( ! empty( $_POST['job_types'] ) && is_array( $_POST['job_types'] ) ) {
foreach ( $_POST['job_types'] as $jt ) {
$data['job_types'][] = sanitize_text_field( wp_unslash( $jt ) );
}
}

$verification_result = Go_Deliver_Email_Verification::validate_token( $verification_token, $data['email'], 'mover_registration', true );
if ( is_wp_error( $verification_result ) ) {
wp_send_json_error( array( 'message' => $verification_result->get_error_message() ) );
}

// Handle document uploads.
$document_types = array( 'drivers_licence_front', 'drivers_licence_back', 'police_check', 'insurance' );
foreach ( $document_types as $doc_type ) {
if ( isset( $_FILES[ $doc_type ] ) && ! empty( $_FILES[ $doc_type ]['name'] ) ) {
$user_id      = 0; // Will be set after user creation; upload to tmp first.
$attachment_id = $this->handle_document_upload( $doc_type, 0 );
if ( ! is_wp_error( $attachment_id ) ) {
$data['documents'][] = array(
'type'    => $doc_type,
'file_id' => $attachment_id,
);
}
}
}

$result = $this->register_mover( $data );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success(
array(
'user_id' => $result,
'message' => __( 'Registration successful. Your account is pending approval.', 'go-deliver' ),
)
);
}

/**
 * AJAX: update the currently-logged-in mover's own profile.
 */
public function ajax_update_mover_profile() {
check_ajax_referer( 'gd_public_nonce', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'go-deliver' ) ), 403 );
}

$user_id = get_current_user_id();
$user    = wp_get_current_user();
$roles   = (array) $user->roles;

$is_mover = in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true );
if ( ! $is_mover && ! user_can( $user_id, 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Access denied.', 'go-deliver' ) ), 403 );
}

// Core WP user fields.
$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
$last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

if ( $email && ! is_email( $email ) ) {
wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'go-deliver' ) ) );
}

if ( $email ) {
$existing = get_user_by( 'email', $email );
if ( $existing && (int) $existing->ID !== $user_id ) {
wp_send_json_error( array( 'message' => __( 'That email address is already in use.', 'go-deliver' ) ) );
}
}

$user_data = array( 'ID' => $user_id );
if ( $first_name ) {
$user_data['first_name']   = $first_name;
$user_data['display_name'] = $first_name . ( $last_name ? ' ' . $last_name : '' );
}
if ( $last_name ) {
$user_data['last_name'] = $last_name;
}
if ( $email ) {
$user_data['user_email'] = $email;
}

if ( count( $user_data ) > 1 ) {
$result = wp_update_user( $user_data );
if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}
}

// User meta.
$phone    = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
$suburb   = sanitize_text_field( gd_normalize_unicode_escapes( wp_unslash( $_POST['base_suburb'] ?? '' ) ) );
$base_lat = isset( $_POST['base_lat'] ) && '' !== $_POST['base_lat'] ? (float) $_POST['base_lat'] : null;
$base_lng = isset( $_POST['base_lng'] ) && '' !== $_POST['base_lng'] ? (float) $_POST['base_lng'] : null;
$radius   = isset( $_POST['radius'] ) && '' !== $_POST['radius'] ? absint( $_POST['radius'] ) : null;

$valid_types = array( 'trademe_pickup', 'item', 'furniture', 'item_packed', 'move', 'vehicle', 'car', 'motorcycle', 'other_vehicle', 'boat', 'piano', 'pet', 'junk', 'other' );
$raw_types   = isset( $_POST['job_types'] ) && is_array( $_POST['job_types'] )
? array_map( 'sanitize_key', wp_unslash( $_POST['job_types'] ) )
: array();
$job_types = array_values( array_intersect( $raw_types, $valid_types ) );

update_user_meta( $user_id, 'gd_phone', $phone );
update_user_meta( $user_id, 'gd_mover_base_suburb', $suburb );
if ( null !== $base_lat ) {
update_user_meta( $user_id, 'gd_mover_base_lat', $base_lat );
}
if ( null !== $base_lng ) {
update_user_meta( $user_id, 'gd_mover_base_lng', $base_lng );
}
if ( null !== $radius ) {
update_user_meta( $user_id, 'gd_mover_radius', $radius );
}
update_user_meta( $user_id, 'gd_mover_job_types', $job_types );

// Extended profile fields.
$company_name = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
update_user_meta( $user_id, 'gd_company_name', $company_name );

$bio = sanitize_textarea_field( wp_unslash( $_POST['bio'] ?? '' ) );
update_user_meta( $user_id, 'gd_bio', $bio );

$raw_fleet   = isset( $_POST['fleet_photos'] ) ? wp_unslash( $_POST['fleet_photos'] ) : '';
$fleet_ids   = json_decode( $raw_fleet, true );
if ( is_array( $fleet_ids ) ) {
$fleet_ids = array_map( 'absint', $fleet_ids );
update_user_meta( $user_id, 'gd_fleet_photos', wp_json_encode( $fleet_ids ) );
}

$profile_photo_id = absint( $_POST['profile_photo_id'] ?? 0 );
if ( $profile_photo_id ) {
update_user_meta( $user_id, 'gd_profile_photo_id', $profile_photo_id );
} elseif ( isset( $_POST['profile_photo_id'] ) && '' === $_POST['profile_photo_id'] ) {
delete_user_meta( $user_id, 'gd_profile_photo_id' );
}

$valid_frequencies   = Go_Deliver_Notifications::VALID_FREQUENCIES;
$notification_frequency = isset( $_POST['notification_frequency'] )
	? sanitize_key( wp_unslash( $_POST['notification_frequency'] ) )
	: '';
if ( in_array( $notification_frequency, $valid_frequencies, true ) ) {
	update_user_meta( $user_id, 'gd_notification_frequency', $notification_frequency );
}

// Build the values that the overview panel needs to reflect immediately.
$updated_user        = get_userdata( $user_id );
$updated_company     = get_user_meta( $user_id, 'gd_company_name', true );
$updated_display     = $updated_company
	? $updated_company
	: ( $updated_user->first_name ?: $updated_user->display_name );
$updated_bio         = get_user_meta( $user_id, 'gd_bio', true );
$updated_suburb      = get_user_meta( $user_id, 'gd_mover_base_suburb', true );
$updated_photo_id  = (int) get_user_meta( $user_id, 'gd_profile_photo_id', true );
$mover_photos_raw  = get_user_meta( $user_id, 'gd_mover_photos', true );
$mover_photos      = is_string( $mover_photos_raw ) ? (array) json_decode( $mover_photos_raw, true ) : array();
$mover_photos      = array_values( array_filter( array_map( 'absint', $mover_photos ) ) );

// Profile photo URL: prefer first photo from gallery, fall back to legacy meta.
if ( ! empty( $mover_photos ) ) {
	$updated_photo_url = wp_get_attachment_image_url( $mover_photos[0], 'thumbnail' ) ?: '';
} elseif ( $updated_photo_id ) {
	$updated_photo_url = wp_get_attachment_image_url( $updated_photo_id, 'thumbnail' ) ?: '';
} else {
	$updated_photo_url = '';
}

wp_send_json_success( array(
	'message'           => __( 'Profile updated successfully.', 'go-deliver' ),
	'display_name'      => $updated_display,
	'bio'               => $updated_bio,
	'suburb'            => $updated_suburb,
	'profile_photo_url' => $updated_photo_url,
) );
}

/**
 * AJAX: Upload a mover photo.
 *
 * Accepts a single file field named 'photo'. The uploaded image is stored as
 * a WordPress attachment owned by the current user and its ID is appended to
 * the gd_mover_photos user-meta array (max 10 items).
 */
public function ajax_upload_mover_photo() {
	check_ajax_referer( 'gd_public_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Not logged in.', 'go-deliver' ) ), 401 );
	}

	$user_id = get_current_user_id();
	$roles   = (array) wp_get_current_user()->roles;

	if (
		! in_array( 'gd_mover', $roles, true ) &&
		! in_array( 'gd_mover_sub', $roles, true ) &&
		! current_user_can( 'manage_options' )
	) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
	}

	// Load current gallery.
	$raw    = get_user_meta( $user_id, 'gd_mover_photos', true );
	$photos = is_string( $raw ) ? (array) json_decode( $raw, true ) : array();
	$photos = array_values( array_filter( array_map( 'absint', $photos ) ) );

	if ( count( $photos ) >= 10 ) {
		wp_send_json_error( array( 'message' => __( 'Maximum of 10 photos allowed. Please delete a photo first.', 'go-deliver' ) ) );
	}

	if ( empty( $_FILES['photo'] ) || UPLOAD_ERR_OK !== (int) $_FILES['photo']['error'] ) {
		wp_send_json_error( array( 'message' => __( 'Upload failed. Please try again.', 'go-deliver' ) ) );
	}

	// Validate MIME type before passing to WordPress.
	$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
	$detected_mime = false;
	if ( function_exists( 'finfo_open' ) ) {
		try {
			$finfo = new finfo( FILEINFO_MIME_TYPE );
			if ( $finfo ) {
				$detected_mime = $finfo->file( $_FILES['photo']['tmp_name'] );
			}
		} catch ( Exception $e ) {
			$detected_mime = false;
		}
	}
	// Fall back to WordPress's built-in type checker when finfo is unavailable.
	if ( false === $detected_mime ) {
		$wp_filetype   = wp_check_filetype( $_FILES['photo']['name'] );
		$detected_mime = $wp_filetype['type'] ?? '';
	}
	if ( ! $detected_mime || ! in_array( $detected_mime, $allowed_types, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Only JPEG, PNG, GIF or WebP images are allowed.', 'go-deliver' ) ) );
	}

	// Load WordPress upload helpers (safe to call multiple times).
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload( 'photo', 0, array( 'post_author' => $user_id ) );

	if ( is_wp_error( $attachment_id ) ) {
		wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
	}

	// Persist to user meta.
	$photos[] = $attachment_id;
	update_user_meta( $user_id, 'gd_mover_photos', wp_json_encode( $photos ) );

	wp_send_json_success( array(
		'attachment_id' => $attachment_id,
		'url'           => wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id ),
		'thumb_url'     => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: wp_get_attachment_url( $attachment_id ),
		'count'         => count( $photos ),
	) );
}

/**
 * AJAX: Delete a mover photo.
 *
 * Expects 'attachment_id' in POST. Verifies the attachment belongs to the
 * current user, removes it from gd_mover_photos, and permanently deletes the
 * file from the media library.
 */
public function ajax_delete_mover_photo() {
	check_ajax_referer( 'gd_public_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Not logged in.', 'go-deliver' ) ), 401 );
	}

	$user_id       = get_current_user_id();
	$attachment_id = absint( $_POST['attachment_id'] ?? 0 );

	if ( ! $attachment_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid photo.', 'go-deliver' ) ) );
	}

	// Verify the attachment was uploaded by this user (admins may bypass).
	$attachment = get_post( $attachment_id );
	if (
		! $attachment ||
		(
			(int) $attachment->post_author !== $user_id &&
			! current_user_can( 'manage_options' )
		)
	) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
	}

	// Remove from gallery meta.
	$raw    = get_user_meta( $user_id, 'gd_mover_photos', true );
	$photos = is_string( $raw ) ? (array) json_decode( $raw, true ) : array();
	$photos = array_values(
		array_filter(
			array_map( 'absint', $photos ),
			function ( $id ) use ( $attachment_id ) {
				return $id !== $attachment_id;
			}
		)
	);
	update_user_meta( $user_id, 'gd_mover_photos', wp_json_encode( $photos ) );

	// Permanently delete the attachment and its generated sizes.
	wp_delete_attachment( $attachment_id, true );

	wp_send_json_success( array( 'count' => count( $photos ) ) );
}

/**
 * AJAX: Mark the mover dashboard tour as completed/dismissed.
 */
public function ajax_dismiss_tour() {
	check_ajax_referer( 'gd_public_nonce', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Not logged in.', 'go-deliver' ) ), 401 );
	}

	update_user_meta( get_current_user_id(), 'gd_mover_tour_completed', 1 );
	wp_send_json_success();
}
}
