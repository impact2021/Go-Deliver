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
Go_Deliver_DB::save_document( $user_id, sanitize_text_field( $doc['type'] ), (int) $doc['file_id'] );
}
}
}

// Notify admin.
$this->notify_admin_new_mover( $user_id );

return $user_id;
}

/**
 * Email the site admin when a new mover registers.
 *
 * @param int $user_id New mover's user ID.
 */
private function notify_admin_new_mover( $user_id ) {
$admin_email = get_option( 'admin_email' );
$mover       = get_userdata( $user_id );
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
admin_url( 'users.php?role=gd_mover' )
);

wp_mail( $admin_email, $subject, $message );
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

$mover = get_userdata( (int) $user_id );
if ( $mover ) {
$subject = sprintf(
/* translators: %s: site name */
__( '[%s] Your Mover Account Has Been Approved', 'go-deliver' ),
get_bloginfo( 'name' )
);
$message = __( 'Congratulations! Your mover account has been approved. You can now log in and start quoting on jobs.', 'go-deliver' );
wp_mail( $mover->user_email, $subject, $message );
}

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

wp_mail( $mover->user_email, $subject, $message );
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

$data = array(
'username'    => sanitize_user( wp_unslash( $_POST['username'] ?? '' ) ),
'email'       => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
'password'    => $_POST['password'] ?? '',
'first_name'  => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
'last_name'   => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
'phone'       => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
'base_suburb' => sanitize_text_field( wp_unslash( $_POST['base_suburb'] ?? '' ) ),
'base_lat'    => isset( $_POST['base_lat'] ) ? (float) $_POST['base_lat'] : 0.0,
'base_lng'    => isset( $_POST['base_lng'] ) ? (float) $_POST['base_lng'] : 0.0,
'radius'      => isset( $_POST['radius'] ) ? absint( $_POST['radius'] ) : 0,
'job_types'   => array(),
'documents'   => array(),
);

if ( ! empty( $_POST['job_types'] ) && is_array( $_POST['job_types'] ) ) {
foreach ( $_POST['job_types'] as $jt ) {
$data['job_types'][] = sanitize_text_field( wp_unslash( $jt ) );
}
}

// Handle document uploads.
$document_types = array( 'drivers_licence', 'police_check', 'insurance' );
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
}
