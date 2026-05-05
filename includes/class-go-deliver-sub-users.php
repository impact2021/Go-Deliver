<?php
/**
 * Sub-user management for Go Deliver movers.
 *
 * Allows approved movers to add up to MAX_SUB_USERS team members who
 * share the parent mover's wallet and approval status.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Sub_Users
 */
class Go_Deliver_Sub_Users {

/** Maximum number of sub-users a mover may have. */
const MAX_SUB_USERS = 5;

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_add_sub_user',    array( $this, 'ajax_add_sub_user' ) );
add_action( 'wp_ajax_gd_remove_sub_user', array( $this, 'ajax_remove_sub_user' ) );
}

// =========================================================================
// Sub-user management.
// =========================================================================

/**
 * Add a sub-user to a mover account.
 *
 * @param int   $parent_mover_id Approved mover user ID.
 * @param array $data {
 *   @type string $username
 *   @type string $email
 *   @type string $password
 *   @type string $first_name
 *   @type string $last_name
 *   @type bool   $can_view_financials
 * }
 * @return int|WP_Error New user ID or WP_Error.
 */
public function add_sub_user( $parent_mover_id, $data ) {
$parent_mover_id = (int) $parent_mover_id;

// Verify parent is an approved mover.
$mover_status = get_user_meta( $parent_mover_id, 'gd_mover_status', true );
if ( 'approved' !== $mover_status ) {
return new WP_Error( 'mover_not_approved', __( 'Your account must be approved before adding team members.', 'go-deliver' ) );
}

// Enforce sub-user cap.
$current_sub_users = $this->get_sub_users( $parent_mover_id );
if ( count( $current_sub_users ) >= self::MAX_SUB_USERS ) {
return new WP_Error(
'sub_user_limit',
sprintf(
/* translators: %d: maximum allowed sub-users */
__( 'You may have at most %d team members.', 'go-deliver' ),
self::MAX_SUB_USERS
)
);
}

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

$user = new WP_User( $user_id );
$user->set_role( 'gd_mover_sub' );

wp_update_user(
array(
'ID'         => $user_id,
'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
)
);

update_user_meta( $user_id, 'gd_parent_mover_id',    $parent_mover_id );
update_user_meta( $user_id, 'gd_can_view_financials', ! empty( $data['can_view_financials'] ) ? 1 : 0 );

Go_Deliver_DB::add_sub_user( $parent_mover_id, $user_id );

// Send welcome email with a secure password-reset link to the new team member.
Go_Deliver_Notifications::notify_team_member_added( $user_id, $parent_mover_id );

return $user_id;
}

/**
 * Remove a sub-user from a mover account and delete their WP user.
 *
 * @param int $parent_mover_id Parent mover user ID.
 * @param int $sub_user_id     Sub-user user ID.
 * @return true|WP_Error
 */
public function remove_sub_user( $parent_mover_id, $sub_user_id ) {
$parent_mover_id = (int) $parent_mover_id;
$sub_user_id     = (int) $sub_user_id;

// Confirm the sub-user belongs to this parent.
$stored_parent = (int) get_user_meta( $sub_user_id, 'gd_parent_mover_id', true );
if ( $stored_parent !== $parent_mover_id ) {
return new WP_Error( 'permission_denied', __( 'This team member does not belong to your account.', 'go-deliver' ) );
}

if ( ! function_exists( 'wp_delete_user' ) ) {
require_once ABSPATH . 'wp-admin/includes/user.php';
}

wp_delete_user( $sub_user_id );
Go_Deliver_DB::remove_sub_user( $parent_mover_id, $sub_user_id );

return true;
}

/**
 * Get all sub-users for a parent mover.
 *
 * @param int $parent_mover_id Parent mover user ID.
 * @return array Array of user data arrays.
 */
public function get_sub_users( $parent_mover_id ) {
return Go_Deliver_DB::get_sub_users( (int) $parent_mover_id );
}

// =========================================================================
// Effective identity resolution.
// =========================================================================

/**
 * Return the effective mover ID for billing/quota purposes.
 *
 * Sub-users share their parent mover's wallet, so quoting/billing
 * should reference the parent mover's ID.
 *
 * @param int $user_id WordPress user ID.
 * @return int Effective mover user ID.
 */
public function get_effective_mover_id( $user_id ) {
$user_id   = (int) $user_id;
$parent_id = (int) get_user_meta( $user_id, 'gd_parent_mover_id', true );
return $parent_id ?: $user_id;
}

/**
 * Return the effective wallet balance for a user (uses parent mover if sub-user).
 *
 * @param int $user_id WordPress user ID.
 * @return float Wallet balance.
 */
public function get_effective_wallet_balance( $user_id ) {
$effective_id = $this->get_effective_mover_id( (int) $user_id );
$wallet       = new Go_Deliver_Wallet();
return $wallet->get_balance( $effective_id );
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: add a sub-user.
 */
public function ajax_add_sub_user() {
check_ajax_referer( 'gd_sub_users', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_manage_sub_users' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$data = array(
'username'            => sanitize_user( wp_unslash( $_POST['username'] ?? '' ) ),
'email'               => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
'password'            => $_POST['password'] ?? '',
'first_name'          => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
'last_name'           => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
'can_view_financials' => ! empty( $_POST['can_view_financials'] ),
);

$effective_parent = $this->get_effective_mover_id( get_current_user_id() );
$result           = $this->add_sub_user( $effective_parent, $data );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'user_id' => $result ) );
}

/**
 * AJAX: remove a sub-user.
 */
public function ajax_remove_sub_user() {
check_ajax_referer( 'gd_sub_users', 'nonce' );

if ( ! is_user_logged_in() || ! current_user_can( 'gd_manage_sub_users' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$sub_user_id = absint( $_POST['sub_user_id'] ?? 0 );
if ( ! $sub_user_id ) {
wp_send_json_error( array( 'message' => __( 'Invalid user ID.', 'go-deliver' ) ) );
}

$effective_parent = $this->get_effective_mover_id( get_current_user_id() );
$result           = $this->remove_sub_user( $effective_parent, $sub_user_id );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Team member removed.', 'go-deliver' ) ) );
}
}
