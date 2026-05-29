<?php
/**
 * Email verification helper for customer and mover signup flows.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Email_Verification
 */
class Go_Deliver_Email_Verification {

	/**
	 * Verification code lifetime in seconds.
	 */
	const CODE_TTL = 900;

	/**
	 * Verified-token lifetime in seconds.
	 */
	const TOKEN_TTL = 1800;

	/**
	 * Generate and email a 6-digit verification code.
	 *
	 * @param string $email Email address to verify.
	 * @param string $flow  Flow key (job_submission|mover_registration).
	 * @return true|WP_Error
	 */
	public static function send_code( $email, $flow ) {
		$email = sanitize_email( $email );
		$flow  = self::sanitize_flow( $flow );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'go-deliver' ) );
		}

		$code = (string) random_int( 100000, 999999 );
		set_transient(
			self::code_key( $email, $flow ),
			array(
				'code'     => $code,
				'email'    => strtolower( $email ),
				'flow'     => $flow,
				'created'  => current_time( 'timestamp' ),
				'attempts' => 0,
			),
			self::CODE_TTL
		);

		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject   = sprintf(
			/* translators: %s: site name */
			__( '[%s] Your verification code', 'go-deliver' ),
			$site_name
		);
		$message   = sprintf(
			/* translators: 1: verification code, 2: minutes */
			__(
				"Your Go Deliver verification code is: %1\$s\n\nThis code expires in %2\$d minutes.\n\nIf you can't find this email, please check your spam or junk folder.",
				'go-deliver'
			),
			$code,
			(int) round( self::CODE_TTL / MINUTE_IN_SECONDS )
		);

		Go_Deliver_Notifications::send_plain_email( $email, $subject, $message );

		return true;
	}

	/**
	 * Verify a code and issue a short-lived verification token.
	 *
	 * @param string $email Email address.
	 * @param string $flow  Flow key.
	 * @param string $code  6-digit code.
	 * @return string|WP_Error Verification token on success.
	 */
	public static function verify_code( $email, $flow, $code ) {
		$email = sanitize_email( $email );
		$flow  = self::sanitize_flow( $flow );
		$code  = preg_replace( '/\D+/', '', (string) $code );

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'go-deliver' ) );
		}

		if ( strlen( $code ) !== 6 ) {
			return new WP_Error( 'invalid_code', __( 'Please enter the 6-digit code we sent.', 'go-deliver' ) );
		}

		$stored = get_transient( self::code_key( $email, $flow ) );
		if ( ! is_array( $stored ) || empty( $stored['code'] ) ) {
			return new WP_Error( 'code_expired', __( 'Your verification code expired. Please request a new one.', 'go-deliver' ) );
		}

		$attempts = isset( $stored['attempts'] ) ? (int) $stored['attempts'] : 0;
		if ( $attempts >= 5 ) {
			delete_transient( self::code_key( $email, $flow ) );
			return new WP_Error( 'too_many_attempts', __( 'Too many incorrect attempts. Please request a new code.', 'go-deliver' ) );
		}

		if ( ! hash_equals( (string) $stored['code'], $code ) ) {
			$stored['attempts'] = $attempts + 1;
			set_transient( self::code_key( $email, $flow ), $stored, self::CODE_TTL );
			return new WP_Error( 'invalid_code', __( 'That code is not correct. Please try again.', 'go-deliver' ) );
		}

		$token = wp_generate_password( 40, false, false );
		set_transient(
			self::token_key( $token ),
			array(
				'email'       => strtolower( $email ),
				'flow'        => $flow,
				'verified_at' => current_time( 'timestamp' ),
			),
			self::TOKEN_TTL
		);

		delete_transient( self::code_key( $email, $flow ) );

		return $token;
	}

	/**
	 * Validate a verification token against a flow/email pair.
	 *
	 * @param string $token   Token from browser.
	 * @param string $email   Email address to validate against.
	 * @param string $flow    Flow key.
	 * @param bool   $consume Whether to consume token after validation.
	 * @return true|WP_Error
	 */
	public static function validate_token( $token, $email, $flow, $consume = false ) {
		$token = sanitize_text_field( (string) $token );
		$email = sanitize_email( $email );
		$flow  = self::sanitize_flow( $flow );

		if ( '' === $token || ! is_email( $email ) ) {
			return new WP_Error( 'verification_required', __( 'Please verify your email address to continue.', 'go-deliver' ) );
		}

		$stored = get_transient( self::token_key( $token ) );
		if ( ! is_array( $stored ) ) {
			return new WP_Error( 'verification_expired', __( 'Email verification expired. Please verify your email again.', 'go-deliver' ) );
		}

		$stored_email = isset( $stored['email'] ) ? strtolower( sanitize_email( $stored['email'] ) ) : '';
		$stored_flow  = isset( $stored['flow'] ) ? self::sanitize_flow( $stored['flow'] ) : '';

		if ( $stored_email !== strtolower( $email ) || $stored_flow !== $flow ) {
			return new WP_Error( 'verification_mismatch', __( 'Email verification does not match this submission. Please verify again.', 'go-deliver' ) );
		}

		if ( $consume ) {
			delete_transient( self::token_key( $token ) );
		}

		return true;
	}

	/**
	 * AJAX: send verification code.
	 */
	public static function ajax_send_code() {
		check_ajax_referer( 'gd_public_nonce', 'nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$flow  = self::sanitize_flow( wp_unslash( $_POST['flow'] ?? '' ) );
		$sent  = self::send_code( $email, $flow );

		if ( is_wp_error( $sent ) ) {
			wp_send_json_error( array( 'message' => $sent->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'We sent a 6-digit code to your email. Please also check your spam folder.', 'go-deliver' ),
			)
		);
	}

	/**
	 * AJAX: verify submitted code and return a token.
	 */
	public static function ajax_verify_code() {
		check_ajax_referer( 'gd_public_nonce', 'nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$flow  = self::sanitize_flow( wp_unslash( $_POST['flow'] ?? '' ) );
		$code  = sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) );
		$token = self::verify_code( $email, $flow, $code );

		if ( is_wp_error( $token ) ) {
			wp_send_json_error( array( 'message' => $token->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'token'   => $token,
				'message' => __( 'Email verified successfully.', 'go-deliver' ),
			)
		);
	}

	/**
	 * Build code transient key.
	 *
	 * @param string $email Email.
	 * @param string $flow  Flow.
	 * @return string
	 */
	private static function code_key( $email, $flow ) {
		return 'gd_email_code_' . md5( strtolower( $flow . '|' . $email ) );
	}

	/**
	 * Build token transient key.
	 *
	 * @param string $token Plain token.
	 * @return string
	 */
	private static function token_key( $token ) {
		return 'gd_email_token_' . md5( wp_hash( (string) $token ) );
	}

	/**
	 * Sanitize/allow only known flow keys.
	 *
	 * @param string $flow Raw flow.
	 * @return string
	 */
	private static function sanitize_flow( $flow ) {
		$flow    = sanitize_key( (string) $flow );
		$allowed = array( 'job_submission', 'mover_registration' );
		return in_array( $flow, $allowed, true ) ? $flow : 'job_submission';
	}
}
