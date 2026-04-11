<?php
/**
 * Admin Movers List partial.
 *
 * Variables expected from caller:
 *   $movers        – array of WP_User objects
 *   $total_pages   – int
 *   $current_page  – int
 *   $status_filter – string, current mover status filter
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $movers ) )        { $movers        = array(); }
if ( ! isset( $total_pages ) )   { $total_pages   = 1; }
if ( ! isset( $current_page ) )  { $current_page  = 1; }
if ( ! isset( $status_filter ) ) { $status_filter = ''; }

$valid_statuses = array( 'pending', 'approved', 'rejected', 'suspended' );
$base_url       = admin_url( 'admin.php?page=go-deliver-movers' );

/**
 * Return HTML for a mover status badge.
 *
 * @param string $status
 * @return string
 */
function gd_mover_status_badge( $status ) {
	$allowed = array( 'pending', 'approved', 'rejected', 'suspended' );
	if ( ! in_array( $status, $allowed, true ) ) {
		$status = 'pending';
	}
	return '<span class="gd-badge gd-badge-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
}
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Movers', 'go-deliver' ); ?></h1>

	<!-- Filter bar -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gd-filter-bar">
		<input type="hidden" name="page" value="go-deliver-movers">

		<div class="gd-filter-group">
			<label for="gd-mover-status-filter"><?php esc_html_e( 'Status', 'go-deliver' ); ?></label>
			<select id="gd-mover-status-filter" name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'go-deliver' ); ?></option>
				<?php foreach ( $valid_statuses as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $status_filter, $s ); ?>>
						<?php echo esc_html( ucfirst( $s ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="gd-filter-group">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'go-deliver' ); ?></button>
			<?php if ( $status_filter ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'go-deliver' ); ?></a>
			<?php endif; ?>
		</div>
	</form><!-- /.gd-filter-bar -->

	<?php if ( empty( $movers ) ) : ?>
		<p><?php esc_html_e( 'No movers found.', 'go-deliver' ); ?></p>
	<?php else : ?>
		<div class="gd-table-wrap">
			<table class="gd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Name', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Email', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Status', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Wallet', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Rating', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Sub-users', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'go-deliver' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $movers as $mover ) :
						$uid       = (int) $mover->ID;
						$name      = esc_html( $mover->display_name );
						$email     = esc_html( $mover->user_email );
						$status    = get_user_meta( $uid, 'gd_mover_status', true );
						if ( ! in_array( $status, $valid_statuses, true ) ) {
							$status = 'pending';
						}
						$balance   = floatval( get_user_meta( $uid, 'gd_wallet_balance', true ) );
						$rating    = floatval( get_user_meta( $uid, 'gd_average_rating', true ) );
						$sub_count = Go_Deliver_DB::count_sub_users( $uid );
						$reg_date  = esc_html( date_i18n( 'd M Y', strtotime( $mover->user_registered ) ) );
						$detail_url = add_query_arg(
							array( 'page' => 'go-deliver-movers', 'user_id' => $uid ),
							admin_url( 'admin.php' )
						);
					?>
					<tr id="gd-mover-row-<?php echo esc_attr( $uid ); ?>">
						<td data-label="<?php esc_attr_e( 'ID', 'go-deliver' ); ?>"><?php echo esc_html( $uid ); ?></td>
						<td data-label="<?php esc_attr_e( 'Name', 'go-deliver' ); ?>">
							<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo $name; ?></a>
						</td>
						<td data-label="<?php esc_attr_e( 'Email', 'go-deliver' ); ?>"><?php echo $email; ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'go-deliver' ); ?>">
							<span id="gd-mover-status-<?php echo esc_attr( $uid ); ?>"
								class="gd-badge gd-badge-<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $status ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'Wallet', 'go-deliver' ); ?>">
							<span id="gd-wallet-balance-<?php echo esc_attr( $uid ); ?>">
								$<?php echo esc_html( number_format( $balance, 2 ) ); ?>
							</span>
						</td>
						<td data-label="<?php esc_attr_e( 'Rating', 'go-deliver' ); ?>">
							<?php echo $rating > 0 ? esc_html( number_format( $rating, 1 ) ) . '/5' : '—'; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Sub-users', 'go-deliver' ); ?>"><?php echo esc_html( $sub_count ); ?></td>
						<td data-label="<?php esc_attr_e( 'Registered', 'go-deliver' ); ?>"><?php echo $reg_date; ?></td>
						<td data-label="<?php esc_attr_e( 'Actions', 'go-deliver' ); ?>">
							<div class="gd-approval-actions">
								<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">
									<?php esc_html_e( 'View', 'go-deliver' ); ?>
								</a>

								<?php if ( 'approved' !== $status ) : ?>
									<button
										type="button"
										class="button button-small gd-btn-approve gd-action-approve"
										data-user-id="<?php echo esc_attr( $uid ); ?>"
										data-action="gd_approve_mover"
										data-confirm="<?php esc_attr_e( 'Approve this mover?', 'go-deliver' ); ?>"
									><?php esc_html_e( 'Approve', 'go-deliver' ); ?></button>
								<?php endif; ?>

								<?php if ( 'rejected' !== $status ) : ?>
									<button
										type="button"
										class="button button-small gd-btn-reject gd-action-reject"
										data-user-id="<?php echo esc_attr( $uid ); ?>"
										data-action="gd_reject_mover"
										data-confirm="<?php esc_attr_e( 'Reject this mover?', 'go-deliver' ); ?>"
									><?php esc_html_e( 'Reject', 'go-deliver' ); ?></button>
								<?php endif; ?>

								<?php if ( 'suspended' !== $status ) : ?>
									<button
										type="button"
										class="button button-small gd-btn-suspend gd-action-suspend"
										data-user-id="<?php echo esc_attr( $uid ); ?>"
										data-action="gd_suspend_mover"
										data-confirm="<?php esc_attr_e( 'Suspend this mover?', 'go-deliver' ); ?>"
									><?php esc_html_e( 'Suspend', 'go-deliver' ); ?></button>
								<?php endif; ?>

								<button
									type="button"
									class="button button-small gd-wallet-adjust-toggle"
									data-user-id="<?php echo esc_attr( $uid ); ?>"
								><?php esc_html_e( 'Adjust Wallet', 'go-deliver' ); ?></button>
							</div><!-- /.gd-approval-actions -->

							<!-- Reason textarea (hidden until needed by JS) -->
							<div id="gd-reason-wrap-<?php echo esc_attr( $uid ); ?>" class="gd-reason-wrap" style="display:none;">
								<textarea
									placeholder="<?php esc_attr_e( 'Optional reason…', 'go-deliver' ); ?>"
									rows="2"
								></textarea>
							</div>

							<!-- Wallet adjust panel -->
							<div id="gd-wallet-adjust-<?php echo esc_attr( $uid ); ?>" class="gd-wallet-adjust">
								<form data-user-id="<?php echo esc_attr( $uid ); ?>">
									<div class="gd-wa-field">
										<label for="gd-wa-amount-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Amount', 'go-deliver' ); ?></label>
										<input
											type="number"
											id="gd-wa-amount-<?php echo esc_attr( $uid ); ?>"
											name="gd_wa_amount"
											step="0.01"
											placeholder="<?php esc_attr_e( 'e.g. -10.00', 'go-deliver' ); ?>"
										>
									</div>
									<div class="gd-wa-field">
										<label for="gd-wa-desc-<?php echo esc_attr( $uid ); ?>"><?php esc_html_e( 'Description', 'go-deliver' ); ?></label>
										<input
											type="text"
											id="gd-wa-desc-<?php echo esc_attr( $uid ); ?>"
											name="gd_wa_description"
											class="gd-wa-desc"
											placeholder="<?php esc_attr_e( 'e.g. Manual adjustment', 'go-deliver' ); ?>"
										>
									</div>
									<div class="gd-wa-field">
										<button type="submit" class="button button-primary">
											<?php esc_html_e( 'Apply', 'go-deliver' ); ?>
										</button>
									</div>
								</form>
							</div><!-- /.gd-wallet-adjust -->
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div><!-- /.gd-table-wrap -->

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="gd-pagination">
				<?php if ( $current_page > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page - 1, 'status' => $status_filter ), $base_url ) ); ?>">&laquo; <?php esc_html_e( 'Prev', 'go-deliver' ); ?></a>
				<?php endif; ?>

				<?php for ( $p = 1; $p <= $total_pages; $p++ ) :
					if ( $p === (int) $current_page ) : ?>
						<span class="gd-current-page"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $p, 'status' => $status_filter ), $base_url ) ); ?>"><?php echo esc_html( $p ); ?></a>
					<?php endif;
				endfor; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page + 1, 'status' => $status_filter ), $base_url ) ); ?>"><?php esc_html_e( 'Next', 'go-deliver' ); ?> &raquo;</a>
				<?php endif; ?>
			</div><!-- /.gd-pagination -->
		<?php endif; ?>

	<?php endif; ?>

</div><!-- /.gd-admin-wrap -->
