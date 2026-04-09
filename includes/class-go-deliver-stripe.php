<?php
/**
 * Stripe payment integration for Go Deliver.
 *
 * Uses only core WordPress HTTP functions — no Stripe PHP SDK required.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Stripe
 */
class Go_Deliver_Stripe {

// =========================================================================
// Key helpers.
// =========================================================================

/**
 * Return the Stripe publishable key from options.
 *
 * @return string
 */
public function get_publishable_key() {
return get_option( 'gd_stripe_publishable_key', '' );
}

/**
 * Return the Stripe secret key from options.
 *
 * @return string
 */
public function get_secret_key() {
return get_option( 'gd_stripe_secret_key', '' );
}

/**
 * Return the Stripe webhook signing secret from options.
 *
 * @return string
 */
public function get_webhook_secret() {
return get_option( 'gd_stripe_webhook_secret', '' );
}

// =========================================================================
// Checkout Session.
// =========================================================================

/**
 * Create a Stripe Checkout Session for a wallet top-up.
 *
 * @param int   $user_id        WordPress user ID.
 * @param float $amount_dollars Amount in dollars (will be converted to cents).
 * @return object|WP_Error Decoded Stripe session object or WP_Error.
 */
public function create_checkout_session( $user_id, $amount_dollars ) {
$secret_key = $this->get_secret_key();
if ( empty( $secret_key ) ) {
return new WP_Error( 'stripe_not_configured', __( 'Stripe is not configured.', 'go-deliver' ) );
}

$amount_cents = (int) round( (float) $amount_dollars * 100 );
if ( $amount_cents < 1000 ) {
return new WP_Error( 'amount_too_small', __( 'Minimum top-up amount is $10.00.', 'go-deliver' ) );
}
if ( $amount_cents > 1000000 ) {
return new WP_Error( 'amount_too_large', __( 'Maximum top-up amount is $10,000.00.', 'go-deliver' ) );
}

$body = array(
'payment_method_types[]'                                => 'card',
'mode'                                                  => 'payment',
'line_items[0][price_data][currency]'                   => 'aud',
'line_items[0][price_data][unit_amount]'                => $amount_cents,
'line_items[0][price_data][product_data][name]'         => 'Wallet Top-up',
'line_items[0][quantity]'                               => 1,
'success_url'                                           => home_url( '?gd_topup_success=1' ),
'cancel_url'                                            => home_url( '?gd_topup_cancelled=1' ),
'metadata[user_id]'                                     => (int) $user_id,
'metadata[plugin]'                                      => 'go-deliver',
);

$response = wp_remote_post(
'https://api.stripe.com/v1/checkout/sessions',
array(
'headers' => array(
'Authorization' => 'Bearer ' . $secret_key,
'Content-Type'  => 'application/x-www-form-urlencoded',
),
'body'    => $body,
'timeout' => 20,
)
);

if ( is_wp_error( $response ) ) {
return $response;
}

$status_code = wp_remote_retrieve_response_code( $response );
$body_raw    = wp_remote_retrieve_body( $response );
$session     = json_decode( $body_raw );

if ( 200 !== (int) $status_code ) {
$error_message = isset( $session->error->message )
? $session->error->message
: __( 'Unknown Stripe error.', 'go-deliver' );
return new WP_Error( 'stripe_error', $error_message );
}

return $session;
}

// =========================================================================
// Webhook.
// =========================================================================

/**
 * Register the webhook listener on the init hook.
 */
public function register_hooks() {
add_action( 'init',              array( $this, 'handle_webhook' ) );
add_action( 'wp_ajax_gd_stripe_topup', array( $this, 'ajax_create_topup_session' ) );
}

/**
 * Listen for incoming Stripe webhook events.
 *
 * Triggered when ?gd_stripe_webhook=1 is in the request.
 *
 * @return void
 */
public function handle_webhook() {
if ( empty( $_GET['gd_stripe_webhook'] ) || '1' !== $_GET['gd_stripe_webhook'] ) {
return;
}

$payload    = file_get_contents( 'php://input' );
$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
$secret     = $this->get_webhook_secret();

if ( ! $this->verify_stripe_signature( $payload, $sig_header, $secret ) ) {
status_header( 400 );
echo 'Webhook signature verification failed.';
exit;
}

$event = json_decode( $payload );
if ( ! $event || ! isset( $event->type ) ) {
status_header( 400 );
echo 'Invalid payload.';
exit;
}

if ( 'checkout.session.completed' === $event->type ) {
$session          = $event->data->object;
$user_id          = isset( $session->metadata->user_id ) ? (int) $session->metadata->user_id : 0;
$amount_cents     = isset( $session->amount_total ) ? (int) $session->amount_total : 0;
$payment_intent   = isset( $session->payment_intent ) ? sanitize_text_field( $session->payment_intent ) : '';

if ( $user_id && $amount_cents > 0 && get_userdata( $user_id ) ) {
$amount_dollars = $amount_cents / 100;
$wallet = new Go_Deliver_Wallet();
$wallet->credit(
$user_id,
$amount_dollars,
'Wallet top-up via Stripe',
$payment_intent
);
}
}

status_header( 200 );
echo 'OK';
exit;
}

/**
 * Verify a Stripe webhook signature.
 *
 * Implements the manual HMAC SHA-256 verification documented by Stripe.
 *
 * @param string $payload    Raw request body.
 * @param string $sig_header Contents of the Stripe-Signature header.
 * @param string $secret     Webhook signing secret.
 * @return bool
 */
public function verify_stripe_signature( $payload, $sig_header, $secret ) {
if ( empty( $payload ) || empty( $sig_header ) || empty( $secret ) ) {
return false;
}

// Parse t= and v1= values from the header.
$parts     = explode( ',', $sig_header );
$timestamp = '';
$signatures = array();

foreach ( $parts as $part ) {
$kv = explode( '=', trim( $part ), 2 );
if ( count( $kv ) !== 2 ) {
continue;
}
if ( 't' === $kv[0] ) {
$timestamp = $kv[1];
} elseif ( 'v1' === $kv[0] ) {
$signatures[] = $kv[1];
}
}

if ( empty( $timestamp ) || empty( $signatures ) ) {
return false;
}

// Reject events older than 5 minutes.
if ( abs( time() - (int) $timestamp ) > 300 ) {
return false;
}

$signed_payload   = $timestamp . '.' . $payload;
$expected_sig     = hash_hmac( 'sha256', $signed_payload, $secret );

foreach ( $signatures as $sig ) {
if ( hash_equals( $expected_sig, $sig ) ) {
return true;
}
}

return false;
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: create a Stripe Checkout Session for a wallet top-up.
 */
public function ajax_create_topup_session() {
check_ajax_referer( 'gd_wallet_topup', 'nonce' );

if ( ! is_user_logged_in() ) {
wp_send_json_error( array( 'message' => __( 'Please log in to top up your wallet.', 'go-deliver' ) ), 403 );
}

$amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : 0.0;

if ( $amount < 10 ) {
wp_send_json_error( array( 'message' => __( 'Minimum top-up amount is $10.00.', 'go-deliver' ) ) );
}

if ( $amount > 10000 ) {
wp_send_json_error( array( 'message' => __( 'Maximum top-up amount is $10,000.00.', 'go-deliver' ) ) );
}

$session = $this->create_checkout_session( get_current_user_id(), $amount );

if ( is_wp_error( $session ) ) {
wp_send_json_error( array( 'message' => $session->get_error_message() ) );
}

$session_url = isset( $session->url ) ? esc_url_raw( $session->url ) : '';

if ( empty( $session_url ) ) {
wp_send_json_error( array( 'message' => __( 'Could not create checkout session.', 'go-deliver' ) ) );
}

wp_send_json_success( array( 'session_url' => $session_url ) );
}
}
