<?php
/**
 * Customer account management.
 *
 * Handles AJAX actions for the customer-facing dashboard.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Customer
 *
 * Provides AJAX handlers for customer profile updates.
 */
class Go_Deliver_Customer {

	/**
	 * AJAX: update the logged-in customer's profile.
	 *
	 * Accepts: first_name, last_name, email, phone.
	 * Requires the gd_public_nonce nonce.
	 */
	public function ajax_update_customer_profile() {
		check_ajax_referer( 'gd_public_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'go-deliver' ) ), 403 );
		}

		$user_id = get_current_user_id();
		$user    = wp_get_current_user();

		// Only customers (gd_submit_jobs cap) or admins may use this handler.
		if ( ! user_can( $user_id, 'gd_submit_jobs' ) && ! user_can( $user_id, 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'go-deliver' ) ), 403 );
		}

		// Sanitise and validate core WP user fields.
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

		// Phone number (stored as user meta, shared with mover meta key).
		$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		update_user_meta( $user_id, 'gd_phone', $phone );

		// Return the updated display name for the JS to reflect immediately.
		$updated_user    = get_userdata( $user_id );
		$updated_display = trim( $updated_user->first_name . ' ' . $updated_user->last_name )
			?: $updated_user->display_name;

		wp_send_json_success( array(
			'message'      => __( 'Profile updated successfully.', 'go-deliver' ),
			'display_name' => $updated_display,
		) );
	}
}
