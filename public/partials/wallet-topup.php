<?php
/**
 * Wallet top-up template.
 *
 * Shortcode: [gd_wallet_topup]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Auth check.
if ( ! is_user_logged_in() ) {
	echo '<div class="gd-wrap"><div class="gd-login-prompt">';
	echo '<div class="gd-login-prompt__icon">🔐</div>';
	echo '<h2 class="gd-login-prompt__title">' . esc_html__( 'Login Required', 'go-deliver' ) . '</h2>';
	echo '<p class="gd-login-prompt__text">' . esc_html__( 'Please log in to top up your wallet.', 'go-deliver' ) . '</p>';
	echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="gd-btn gd-btn--primary">' . esc_html__( 'Log In', 'go-deliver' ) . '</a>';
	echo '</div></div>';
	return;
}

$current_user = wp_get_current_user();
$is_mover     = in_array( 'gd_mover', (array) $current_user->roles, true )
             || in_array( 'gd_mover_sub', (array) $current_user->roles, true );

if ( ! $is_mover ) {
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">' .
	     esc_html__( 'This page is only available to registered movers.', 'go-deliver' ) .
	     '</div></div></div>';
	return;
}

$user_id        = get_current_user_id();
$wallet         = new Go_Deliver_Wallet();
$balance        = $wallet->get_balance( $user_id );
$stripe         = new Go_Deliver_Stripe();
$publishable_key = esc_attr( $stripe->get_publishable_key() );
$fee_percentage  = (float) get_option( 'gd_fee_percentage', 10 );

// Query params.
$topup_success   = isset( $_GET['gd_topup_success'] )   && '1' === sanitize_text_field( wp_unslash( $_GET['gd_topup_success'] ) );
$topup_cancelled = isset( $_GET['gd_topup_cancelled'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['gd_topup_cancelled'] ) );

// Recent transactions.
$transactions = $wallet->get_transactions( $user_id, 10 );
?>
<div class="gd-wrap">
	<div class="gd-topup-page">

		<?php if ( $topup_success ) : ?>
			<div class="gd-alert gd-alert--success">
				<span class="gd-alert__icon">✓</span>
				<div class="gd-alert__body">
					<strong><?php esc_html_e( 'Top-up successful!', 'go-deliver' ); ?></strong>
					<?php esc_html_e( 'Your wallet has been credited. You can now submit quotes on jobs.', 'go-deliver' ); ?>
				</div>
			</div>
		<?php elseif ( $topup_cancelled ) : ?>
			<div class="gd-alert gd-alert--info">
				<span class="gd-alert__icon">ℹ️</span>
				<div class="gd-alert__body">
					<?php esc_html_e( 'Payment was cancelled. No charge was made to your card.', 'go-deliver' ); ?>
				</div>
			</div>
		<?php endif; ?>

		<!-- Current Balance -->
		<div class="gd-topup-page__balance">
			<div class="gd-topup-page__balance-label"><?php esc_html_e( 'Current Wallet Balance', 'go-deliver' ); ?></div>
			<div class="gd-topup-page__balance-value">$<?php echo esc_html( number_format( $balance, 2 ) ); ?></div>
			<p class="gd-text-muted gd-text-sm" style="margin-top:8px;">
				<?php
				printf(
					/* translators: %s: fee percentage */
					esc_html__( 'A %s%% platform fee is charged on each accepted quote.', 'go-deliver' ),
					esc_html( $fee_percentage )
				);
				?>
			</p>
		</div>

		<!-- Top-up Form -->
		<div class="gd-topup-page__form">
			<h2 class="gd-topup-page__form-title"><?php esc_html_e( 'Add Funds', 'go-deliver' ); ?></h2>

			<?php if ( empty( $stripe->get_publishable_key() ) ) : ?>
				<div class="gd-alert gd-alert--warning">
					<span class="gd-alert__icon">⚠️</span>
					<div class="gd-alert__body">
						<?php esc_html_e( 'Payment processing is not configured yet. Please contact the administrator.', 'go-deliver' ); ?>
					</div>
				</div>
			<?php else : ?>
				<form id="gd-topup-form" method="post" novalidate>
					<?php wp_nonce_field( 'gd_wallet_topup', 'gd_wallet_topup_nonce' ); ?>
					<input type="hidden" name="action" value="gd_stripe_topup">

					<!-- Quick-select amount buttons -->
					<div class="gd-field-group">
						<label><?php esc_html_e( 'Quick Select', 'go-deliver' ); ?></label>
						<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
							<?php foreach ( array( 50, 100, 200, 500 ) as $preset ) : ?>
								<button
									type="button"
									class="gd-btn gd-btn--outline gd-btn--sm gd-amount-preset"
									data-amount="<?php echo esc_attr( $preset ); ?>"
								>
									$<?php echo esc_html( $preset ); ?>
								</button>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="gd-field-group">
						<label for="gd-topup-amount">
							<?php esc_html_e( 'Amount', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<input
							type="number"
							id="gd-topup-amount"
							name="amount"
							min="10"
							max="10000"
							step="10"
							placeholder="<?php esc_attr_e( 'Enter amount (min. $10)', 'go-deliver' ); ?>"
							required
						>
						<span class="gd-field-hint">
							<?php esc_html_e( 'Minimum $10 · Maximum $10,000 per transaction.', 'go-deliver' ); ?>
						</span>
					</div>

					<button type="submit" class="gd-btn gd-btn--primary gd-btn--block">
						🔒 <?php esc_html_e( 'Proceed to Payment', 'go-deliver' ); ?>
					</button>

					<div class="gd-stripe-badge">
						<span class="gd-stripe-badge__icon">🔒</span>
						<?php esc_html_e( 'Secured by Stripe. We never store your card details.', 'go-deliver' ); ?>
					</div>
				</form>

				<!-- Hidden Stripe publishable key for JS use if needed -->
				<div
					id="gd-stripe-config"
					data-publishable-key="<?php echo $publishable_key; ?>"
					style="display:none;"
				></div>
			<?php endif; ?>
		</div>

		<!-- Transaction History -->
		<?php if ( ! empty( $transactions ) ) : ?>
			<div class="gd-section-card" style="margin-top:24px;">
				<div class="gd-section-card__header">
					<h3 class="gd-section-card__title"><?php esc_html_e( 'Recent Transactions', 'go-deliver' ); ?></h3>
				</div>
				<div class="gd-section-card__body" style="padding:0;">
					<table class="gd-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'go-deliver' ); ?></th>
								<th><?php esc_html_e( 'Description', 'go-deliver' ); ?></th>
								<th><?php esc_html_e( 'Amount', 'go-deliver' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $tx ) :
								$tx_amount = isset( $tx['amount'] ) ? (float) $tx['amount'] : 0;
								$tx_date   = isset( $tx['created_at'] ) ? esc_html( date_i18n( 'd M Y H:i', strtotime( $tx['created_at'] ) ) ) : '';
								$tx_desc   = isset( $tx['description'] ) ? esc_html( $tx['description'] ) : '';
								$is_credit = $tx_amount > 0;
							?>
								<tr>
									<td class="gd-text-sm"><?php echo $tx_date; ?></td>
									<td><?php echo $tx_desc; ?></td>
									<td style="font-weight:700;color:<?php echo $is_credit ? 'var(--gd-success)' : 'var(--gd-danger)'; ?>">
										<?php echo $is_credit ? '+' : ''; ?>$<?php echo esc_html( number_format( abs( $tx_amount ), 2 ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

	</div><!-- /.gd-topup-page -->
</div><!-- /.gd-wrap -->
