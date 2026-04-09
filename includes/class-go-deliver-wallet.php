<?php
/**
 * Wallet management for Go Deliver.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Wallet
 */
class Go_Deliver_Wallet {

// =========================================================================
// Balance helpers.
// =========================================================================

/**
 * Return the current wallet balance for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return float
 */
public function get_balance( $user_id ) {
return Go_Deliver_DB::get_wallet_balance( (int) $user_id );
}

/**
 * Credit an amount to a user's wallet.
 *
 * @param int    $user_id           WordPress user ID.
 * @param float  $amount            Amount to add.
 * @param string $description       Human-readable description.
 * @param string $stripe_payment_id Optional Stripe PaymentIntent ID.
 * @return float New balance.
 */
public function credit( $user_id, $amount, $description, $stripe_payment_id = '' ) {
$user_id = (int) $user_id;
$amount  = (float) $amount;

$current = $this->get_balance( $user_id );
$new_balance = $current + $amount;

Go_Deliver_DB::update_wallet_balance( $user_id, $new_balance );
Go_Deliver_DB::log_transaction(
$user_id,
'topup',
$amount,
sanitize_text_field( $description ),
0,
0,
sanitize_text_field( $stripe_payment_id )
);

return $new_balance;
}

/**
 * Charge the platform fee for a quote.
 *
 * @param int $mover_id Mover user ID.
 * @param int $quote_id Quote post ID.
 * @param int $job_id   Job post ID.
 * @return true|WP_Error
 */
public function charge_quote_fee( $mover_id, $quote_id, $job_id ) {
$mover_id = (int) $mover_id;
$quote_id = (int) $quote_id;
$job_id   = (int) $job_id;

$quote_amount = (float) get_post_meta( $quote_id, 'gd_amount', true );
$fee          = $this->get_fee_amount( $quote_amount );

$current_balance = $this->get_balance( $mover_id );
if ( $current_balance < $fee ) {
return new WP_Error(
'insufficient_balance',
sprintf(
/* translators: %s: formatted fee amount */
__( 'Insufficient wallet balance. Required: $%s.', 'go-deliver' ),
number_format( $fee, 2 )
)
);
}

$new_balance = $current_balance - $fee;
Go_Deliver_DB::update_wallet_balance( $mover_id, $new_balance );
Go_Deliver_DB::log_transaction(
$mover_id,
'charge',
-$fee,
sprintf(
/* translators: %d: job ID */
__( 'Platform fee for job #%d', 'go-deliver' ),
$job_id
),
$job_id,
$quote_id
);

update_post_meta( $quote_id, 'gd_fee_charged', 1 );
update_post_meta( $quote_id, 'gd_fee_amount', $fee );

return true;
}

/**
 * Check whether a mover has sufficient balance to cover the fee for a quote.
 *
 * @param int   $mover_id     Mover user ID.
 * @param float $quote_amount Quote amount in dollars.
 * @return true|WP_Error
 */
public function check_balance_for_quote( $mover_id, $quote_amount ) {
$fee     = $this->get_fee_amount( (float) $quote_amount );
$balance = $this->get_balance( (int) $mover_id );

if ( $balance >= $fee ) {
return true;
}

return new WP_Error(
'insufficient_balance',
sprintf(
/* translators: 1: required fee, 2: current balance */
__( 'Insufficient wallet balance. Required: $%1$s, Available: $%2$s.', 'go-deliver' ),
number_format( $fee, 2 ),
number_format( $balance, 2 )
)
);
}

/**
 * Calculate the platform fee for a given quote amount.
 *
 * @param float $quote_amount Quote amount in dollars.
 * @return float Fee in dollars.
 */
public function get_fee_amount( $quote_amount ) {
$fee_percentage = (float) get_option( 'gd_fee_percentage', 10 );
return round( (float) $quote_amount * $fee_percentage / 100, 2 );
}

/**
 * Get transaction history for a user.
 *
 * @param int $user_id WordPress user ID.
 * @param int $limit   Maximum number of rows to return.
 * @return array
 */
public function get_transactions( $user_id, $limit = 20 ) {
return Go_Deliver_DB::get_transactions( (int) $user_id, (int) $limit );
}

/**
 * Administratively adjust a user's wallet balance (positive credit or negative deduction).
 *
 * @param int    $user_id     WordPress user ID.
 * @param float  $amount      Amount (positive or negative).
 * @param string $description Reason for adjustment.
 * @return float New balance.
 */
public function admin_adjust_balance( $user_id, $amount, $description ) {
$user_id = (int) $user_id;
$amount  = (float) $amount;

$current     = $this->get_balance( $user_id );
$new_balance = $current + $amount;

Go_Deliver_DB::update_wallet_balance( $user_id, $new_balance );
Go_Deliver_DB::log_transaction(
$user_id,
'adjustment',
$amount,
sanitize_text_field( $description )
);

return $new_balance;
}
}
