<?php
/**
 * Admin Transactions List partial.
 *
 * Variables expected from caller:
 *   $transactions  – array of transaction row objects (stdClass with id, user_id, type,
 *                    amount, description, job_id, quote_id, stripe_payment_id, created_at)
 *   $total_pages   – int
 *   $current_page  – int
 *   $filter_user   – string, user search value
 *   $filter_type   – string, type filter value
 *   $filter_from   – string, date from (Y-m-d)
 *   $filter_to     – string, date to (Y-m-d)
 *   $total_credits – float, sum of positive amounts in result set
 *   $total_debits  – float, sum of negative amounts (absolute) in result set
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $transactions ) )  { $transactions  = array(); }
if ( ! isset( $total_pages ) )   { $total_pages   = 1; }
if ( ! isset( $current_page ) )  { $current_page  = 1; }
if ( ! isset( $filter_user ) )   { $filter_user   = ''; }
if ( ! isset( $filter_type ) )   { $filter_type   = ''; }
if ( ! isset( $filter_from ) )   { $filter_from   = ''; }
if ( ! isset( $filter_to ) )     { $filter_to     = ''; }
if ( ! isset( $total_credits ) ) { $total_credits = 0.0; }
if ( ! isset( $total_debits ) )  { $total_debits  = 0.0; }

$valid_types = array( 'topup', 'charge', 'refund', 'adjustment' );
$base_url    = admin_url( 'admin.php?page=go-deliver-transactions' );

/**
 * Return HTML for a transaction type badge.
 *
 * @param string $type
 * @return string
 */
function gd_txn_type_badge( $type ) {
	$allowed = array( 'topup', 'charge', 'refund', 'adjustment' );
	if ( ! in_array( $type, $allowed, true ) ) {
		$type = 'adjustment';
	}
	return '<span class="gd-badge gd-badge-' . esc_attr( $type ) . '">' . esc_html( $type ) . '</span>';
}
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Transactions', 'go-deliver' ); ?></h1>

	<!-- Summary bar -->
	<div class="gd-summary-bar">
		<span class="gd-summary-item">
			<?php esc_html_e( 'Total Credits:', 'go-deliver' ); ?>
			<strong class="gd-amount-positive">$<?php echo esc_html( number_format( floatval( $total_credits ), 2 ) ); ?></strong>
		</span>
		<span class="gd-summary-item">
			<?php esc_html_e( 'Total Debits:', 'go-deliver' ); ?>
			<strong class="gd-amount-negative">$<?php echo esc_html( number_format( floatval( $total_debits ), 2 ) ); ?></strong>
		</span>
		<span class="gd-summary-item">
			<?php esc_html_e( 'Net:', 'go-deliver' ); ?>
			<?php $net = floatval( $total_credits ) - floatval( $total_debits ); ?>
			<strong class="<?php echo $net >= 0 ? 'gd-amount-positive' : 'gd-amount-negative'; ?>">
				$<?php echo esc_html( number_format( abs( $net ), 2 ) ); ?>
			</strong>
		</span>
	</div><!-- /.gd-summary-bar -->

	<!-- Filter bar -->
	<form method="get" id="gd-transactions-filter" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gd-filter-bar">
		<input type="hidden" name="page" value="go-deliver-transactions">

		<div class="gd-filter-group">
			<label for="gd-txn-user"><?php esc_html_e( 'User', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd-txn-user"
				name="user"
				value="<?php echo esc_attr( $filter_user ); ?>"
				placeholder="<?php esc_attr_e( 'Name or email…', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-filter-group">
			<label for="gd-txn-type"><?php esc_html_e( 'Type', 'go-deliver' ); ?></label>
			<select id="gd-txn-type" name="type">
				<option value=""><?php esc_html_e( 'All Types', 'go-deliver' ); ?></option>
				<?php foreach ( $valid_types as $t ) : ?>
					<option value="<?php echo esc_attr( $t ); ?>"<?php selected( $filter_type, $t ); ?>>
						<?php echo esc_html( ucfirst( $t ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="gd-filter-group">
			<label for="gd-txn-from"><?php esc_html_e( 'From', 'go-deliver' ); ?></label>
			<input type="date" id="gd-txn-from" name="date_from" value="<?php echo esc_attr( $filter_from ); ?>">
		</div>

		<div class="gd-filter-group">
			<label for="gd-txn-to"><?php esc_html_e( 'To', 'go-deliver' ); ?></label>
			<input type="date" id="gd-txn-to" name="date_to" value="<?php echo esc_attr( $filter_to ); ?>">
		</div>

		<div class="gd-filter-group">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'go-deliver' ); ?></button>
			<?php if ( $filter_user || $filter_type || $filter_from || $filter_to ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'go-deliver' ); ?></a>
			<?php endif; ?>
		</div>
	</form><!-- /.gd-filter-bar -->

	<?php if ( empty( $transactions ) ) : ?>
		<p><?php esc_html_e( 'No transactions found.', 'go-deliver' ); ?></p>
	<?php else : ?>
		<div class="gd-table-wrap">
			<table class="gd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'User', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Type', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Description', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Job ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Quote ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Stripe Payment ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Date', 'go-deliver' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $transactions as $txn ) :
						$txn_id     = isset( $txn->id )               ? (int) $txn->id              : 0;
						$user_id    = isset( $txn->user_id )          ? (int) $txn->user_id          : 0;
						$type       = isset( $txn->type )             ? (string) $txn->type          : 'adjustment';
						$amount     = isset( $txn->amount )           ? floatval( $txn->amount )     : 0.0;
						$desc       = isset( $txn->description )      ? (string) $txn->description   : '';
						$job_id     = isset( $txn->job_id )           ? (int) $txn->job_id           : 0;
						$quote_id   = isset( $txn->quote_id )         ? (int) $txn->quote_id         : 0;
						$stripe_id  = isset( $txn->stripe_payment_id) ? (string) $txn->stripe_payment_id : '';
						$created_at = isset( $txn->created_at )       ? (string) $txn->created_at    : '';

						$user = get_userdata( $user_id );
						$user_display = $user
							? '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user->display_name ) . '</a>'
							: esc_html( $user_id );

						$amount_class  = $amount >= 0 ? 'gd-amount-positive' : 'gd-amount-negative';
						$amount_prefix = $amount >= 0 ? '+' : '';
						$amount_fmt    = $amount_prefix . '$' . number_format( abs( $amount ), 2 );

						$date_fmt = $created_at
							? esc_html( date_i18n( 'd M Y H:i', strtotime( $created_at ) ) )
							: '—';

						$job_link = $job_id
							? '<a href="' . esc_url( add_query_arg( array( 'page' => 'go-deliver-jobs', 'job_id' => $job_id ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $job_id ) . '</a>'
							: '—';
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'ID', 'go-deliver' ); ?>"><?php echo esc_html( $txn_id ); ?></td>
						<td data-label="<?php esc_attr_e( 'User', 'go-deliver' ); ?>"><?php echo $user_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></td>
						<td data-label="<?php esc_attr_e( 'Type', 'go-deliver' ); ?>">
							<?php echo gd_txn_type_badge( $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Amount', 'go-deliver' ); ?>">
							<span class="<?php echo esc_attr( $amount_class ); ?>"><?php echo esc_html( $amount_fmt ); ?></span>
						</td>
						<td data-label="<?php esc_attr_e( 'Description', 'go-deliver' ); ?>"><?php echo esc_html( $desc ); ?></td>
						<td data-label="<?php esc_attr_e( 'Job ID', 'go-deliver' ); ?>"><?php echo $job_link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></td>
						<td data-label="<?php esc_attr_e( 'Quote ID', 'go-deliver' ); ?>"><?php echo $quote_id ? esc_html( $quote_id ) : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Stripe Payment ID', 'go-deliver' ); ?>">
							<?php echo $stripe_id ? '<code>' . esc_html( $stripe_id ) . '</code>' : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Date', 'go-deliver' ); ?>"><?php echo $date_fmt; ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div><!-- /.gd-table-wrap -->

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) :
			$filter_args = array(
				'user'      => $filter_user,
				'type'      => $filter_type,
				'date_from' => $filter_from,
				'date_to'   => $filter_to,
			);
		?>
			<div class="gd-pagination">
				<?php if ( $current_page > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'paged' => $current_page - 1 ) ), $base_url ) ); ?>">
						&laquo; <?php esc_html_e( 'Prev', 'go-deliver' ); ?>
					</a>
				<?php endif; ?>

				<?php for ( $p = 1; $p <= $total_pages; $p++ ) :
					if ( $p === (int) $current_page ) : ?>
						<span class="gd-current-page"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'paged' => $p ) ), $base_url ) ); ?>"><?php echo esc_html( $p ); ?></a>
					<?php endif;
				endfor; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array_merge( $filter_args, array( 'paged' => $current_page + 1 ) ), $base_url ) ); ?>">
						<?php esc_html_e( 'Next', 'go-deliver' ); ?> &raquo;
					</a>
				<?php endif; ?>
			</div><!-- /.gd-pagination -->
		<?php endif; ?>

	<?php endif; ?>

</div><!-- /.gd-admin-wrap -->
