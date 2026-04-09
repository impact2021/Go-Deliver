<?php
/**
 * Quote submission form template.
 *
 * Rendered inside job detail views for approved movers.
 * Expects $job_id to be set in the including scope.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $job_id ) ) {
	return;
}

$job_id = (int) $job_id;

// Only show to approved movers who are logged in.
if ( ! is_user_logged_in() ) {
	echo '<p class="gd-text-muted">' . esc_html__( 'Please log in to submit a quote.', 'go-deliver' ) . '</p>';
	return;
}

$current_user_id = get_current_user_id();
$user            = wp_get_current_user();
$is_mover        = in_array( 'gd_mover', (array) $user->roles, true )
                || in_array( 'gd_mover_sub', (array) $user->roles, true );

if ( ! $is_mover ) {
	return;
}

$mover_status = get_user_meta( $current_user_id, 'gd_mover_status', true );
if ( 'approved' !== $mover_status ) {
	return;
}

// Check the job is still open.
$job_status = get_post_meta( $job_id, 'gd_job_status', true );
if ( 'open' !== $job_status ) {
	return;
}

// Check mover hasn't already quoted on this job.
$existing = new WP_Query( array(
	'post_type'      => 'gd_quote',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'meta_query'     => array(
		'relation' => 'AND',
		array(
			'key'   => 'gd_job_id',
			'value' => $job_id,
			'type'  => 'NUMERIC',
		),
		array(
			'key'   => 'gd_mover_id',
			'value' => $current_user_id,
			'type'  => 'NUMERIC',
		),
	),
	'no_found_rows'  => true,
	'fields'         => 'ids',
) );
$already_quoted = $existing->found_posts > 0;
wp_reset_postdata();

if ( $already_quoted ) {
	echo '<div class="gd-alert gd-alert--info">';
	echo '<span class="gd-alert__icon">ℹ️</span>';
	echo '<div class="gd-alert__body">' . esc_html__( 'You have already submitted a quote for this job.', 'go-deliver' ) . '</div>';
	echo '</div>';
	return;
}

$fee_percentage = (float) get_option( 'gd_fee_percentage', 10 );
$wallet         = new Go_Deliver_Wallet();
$balance        = $wallet->get_balance( $current_user_id );
?>
<div class="gd-quote-form" id="gd-quote-form-wrap">

	<h3 class="gd-quote-form__title"><?php esc_html_e( 'Submit Your Quote', 'go-deliver' ); ?></h3>

	<?php if ( $balance < 10 ) : ?>
		<div class="gd-alert gd-alert--warning">
			<span class="gd-alert__icon">⚠️</span>
			<div class="gd-alert__body">
				<strong><?php esc_html_e( 'Insufficient wallet balance.', 'go-deliver' ); ?></strong>
				<?php
				printf(
					/* translators: 1: balance, 2: top-up URL */
					esc_html__( 'Your current balance is $%1$s. You need sufficient funds to cover the platform fee (charged on acceptance). %2$s', 'go-deliver' ),
					esc_html( number_format( $balance, 2 ) ),
					'<a href="' . esc_url( get_permalink( get_option( 'gd_wallet_page_id' ) ) ) . '">' . esc_html__( 'Top up your wallet →', 'go-deliver' ) . '</a>'
				);
				?>
			</div>
		</div>
	<?php endif; ?>

	<p class="gd-text-muted gd-text-sm" style="margin-bottom:16px;">
		<?php
		printf(
			/* translators: 1: balance, 2: percentage */
			esc_html__( 'Current wallet balance: $%1$s &nbsp;|&nbsp; Platform fee: %2$s%% of quote (charged on acceptance)', 'go-deliver' ),
			esc_html( number_format( $balance, 2 ) ),
			esc_html( $fee_percentage )
		);
		?>
	</p>

	<form id="gd-quote-form" method="post" novalidate>
		<?php wp_nonce_field( 'gd_submit_quote', 'gd_submit_quote_nonce' ); ?>
		<input type="hidden" name="action" value="gd_submit_quote">
		<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">

		<div class="gd-field-group">
			<label for="gd-quote-amount">
				<?php esc_html_e( 'Your Quote Amount ($)', 'go-deliver' ); ?>
				<span class="gd-required" aria-hidden="true">*</span>
			</label>
			<input
				type="number"
				id="gd-quote-amount"
				name="amount"
				class="gd-quote-amount-input"
				min="1"
				step="1"
				placeholder="<?php esc_attr_e( 'Enter amount in AUD', 'go-deliver' ); ?>"
				required
			>
			<span class="gd-quote-form__fee-info">
				<?php esc_html_e( 'Platform fee:', 'go-deliver' ); ?>
				<strong class="gd-fee-preview" data-fee-pct="<?php echo esc_attr( $fee_percentage ); ?>"></strong>
				<?php
				printf(
					/* translators: %s: fee percentage */
					esc_html__( '(%s%% of quote amount – deducted from wallet on acceptance)', 'go-deliver' ),
					esc_html( $fee_percentage )
				);
				?>
			</span>
		</div>

		<div class="gd-field-group">
			<label for="gd-quote-message">
				<?php esc_html_e( 'Message to Customer', 'go-deliver' ); ?>
			</label>
			<textarea
				id="gd-quote-message"
				name="message"
				rows="4"
				placeholder="<?php esc_attr_e( 'Introduce yourself and explain why you\'re the right mover for this job…', 'go-deliver' ); ?>"
			></textarea>
		</div>

		<button
			type="submit"
			class="gd-btn gd-btn--primary"
			<?php echo $balance < 1 ? 'disabled' : ''; ?>
		>
			<?php esc_html_e( 'Submit Quote', 'go-deliver' ); ?>
		</button>
	</form>

</div><!-- /.gd-quote-form -->
