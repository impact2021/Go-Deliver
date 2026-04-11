<?php
/**
 * Admin Mover Approval / Detail partial.
 *
 * Variables expected from caller:
 *   $mover         – WP_User object for the mover being reviewed
 *   $documents     – array of document row objects (id, user_id, doc_type, file_url, status, created_at)
 *   $transactions  – array of recent wallet transaction objects
 *   $status_log    – array of status-change log entries (user_id, old_status, new_status, reason, changed_at, admin_id)
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $mover ) || ! ( $mover instanceof WP_User ) ) {
	echo '<div class="wrap"><p>' . esc_html__( 'Mover not found.', 'go-deliver' ) . '</p></div>';
	return;
}

if ( ! isset( $documents ) )    { $documents    = array(); }
if ( ! isset( $transactions ) ) { $transactions = array(); }
if ( ! isset( $status_log ) )   { $status_log   = array(); }

$uid           = (int) $mover->ID;
$name          = esc_html( $mover->display_name );
$email         = esc_html( $mover->user_email );
$status        = get_user_meta( $uid, 'gd_mover_status', true );
$valid_statuses = array( 'pending', 'approved', 'rejected', 'suspended' );
if ( ! in_array( $status, $valid_statuses, true ) ) {
	$status = 'pending';
}
$balance  = floatval( get_user_meta( $uid, 'gd_wallet_balance', true ) );
$reg_date = esc_html( date_i18n( 'd M Y H:i', strtotime( $mover->user_registered ) ) );
$back_url = admin_url( 'admin.php?page=go-deliver-movers' );

$doc_valid_statuses = array( 'pending', 'approved', 'rejected' );
$txn_valid_types    = array( 'topup', 'charge', 'refund', 'adjustment' );

/**
 * Return HTML status badge.
 *
 * @param string $status
 * @param string[] $allowed
 * @param string $default
 * @return string
 */
function gd_approval_badge( $status, $allowed, $default = 'pending' ) {
	if ( ! in_array( $status, $allowed, true ) ) {
		$status = $default;
	}
	return '<span class="gd-badge gd-badge-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
}
?>
<div class="wrap gd-admin-wrap">
	<h1>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			&laquo; <?php esc_html_e( 'All Movers', 'go-deliver' ); ?>
		</a>
		<?php
		printf(
			/* translators: %s: mover display name */
			esc_html__( 'Mover: %s', 'go-deliver' ),
			$name
		);
		?>
	</h1>

	<!-- ======================================================
	     Mover Info
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Mover Information', 'go-deliver' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><?php esc_html_e( 'Name', 'go-deliver' ); ?></th>
					<td><?php echo $name; ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email', 'go-deliver' ); ?></th>
					<td><a href="mailto:<?php echo esc_attr( $mover->user_email ); ?>"><?php echo $email; ?></a></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Status', 'go-deliver' ); ?></th>
					<td>
						<span id="gd-mover-status-<?php echo esc_attr( $uid ); ?>"
							class="gd-badge gd-badge-<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( $status ); ?>
						</span>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Registered', 'go-deliver' ); ?></th>
					<td><?php echo $reg_date; ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Wallet Balance', 'go-deliver' ); ?></th>
					<td>
						<strong id="gd-wallet-balance-<?php echo esc_attr( $uid ); ?>">
							$<?php echo esc_html( number_format( $balance, 2 ) ); ?>
						</strong>
						<button
							type="button"
							class="button button-small gd-wallet-adjust-toggle"
							style="margin-left:8px;"
							data-user-id="<?php echo esc_attr( $uid ); ?>"
						><?php esc_html_e( 'Adjust', 'go-deliver' ); ?></button>

						<div id="gd-wallet-adjust-<?php echo esc_attr( $uid ); ?>" class="gd-wallet-adjust" style="margin-top:8px;">
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
										placeholder="<?php esc_attr_e( 'Reason for adjustment', 'go-deliver' ); ?>"
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
			</tbody>
		</table>
	</div><!-- /.gd-section: mover info -->

	<!-- ======================================================
	     Edit Profile
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Edit Profile', 'go-deliver' ); ?></h2>

		<form id="gd-mover-edit-form" data-user-id="<?php echo esc_attr( $uid ); ?>">
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th><label for="gd-edit-first-name"><?php esc_html_e( 'First Name', 'go-deliver' ); ?></label></th>
						<td>
							<input type="text" id="gd-edit-first-name" name="first_name" class="regular-text"
								value="<?php echo esc_attr( $mover->first_name ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="gd-edit-last-name"><?php esc_html_e( 'Last Name', 'go-deliver' ); ?></label></th>
						<td>
							<input type="text" id="gd-edit-last-name" name="last_name" class="regular-text"
								value="<?php echo esc_attr( $mover->last_name ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="gd-edit-email"><?php esc_html_e( 'Email', 'go-deliver' ); ?></label></th>
						<td>
							<input type="email" id="gd-edit-email" name="email" class="regular-text"
								value="<?php echo esc_attr( $mover->user_email ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="gd-edit-phone"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></label></th>
						<td>
							<input type="text" id="gd-edit-phone" name="phone" class="regular-text"
								value="<?php echo esc_attr( get_user_meta( $uid, 'gd_phone', true ) ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="gd-edit-base-suburb"><?php esc_html_e( 'Base Suburb', 'go-deliver' ); ?></label></th>
						<td>
							<input type="text" id="gd-edit-base-suburb" name="base_suburb" class="regular-text"
								value="<?php echo esc_attr( get_user_meta( $uid, 'gd_mover_base_suburb', true ) ); ?>">
							<input type="hidden" id="gd-edit-base-lat" name="base_lat"
								value="<?php echo esc_attr( get_user_meta( $uid, 'gd_mover_base_lat', true ) ); ?>">
							<input type="hidden" id="gd-edit-base-lng" name="base_lng"
								value="<?php echo esc_attr( get_user_meta( $uid, 'gd_mover_base_lng', true ) ); ?>">
							<p class="description"><?php esc_html_e( 'Enter the suburb/city the mover operates from.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="gd-edit-radius"><?php esc_html_e( 'Service Radius (km)', 'go-deliver' ); ?></label></th>
						<td>
							<input type="number" id="gd-edit-radius" name="radius" class="small-text" min="0" step="1"
								value="<?php echo esc_attr( get_user_meta( $uid, 'gd_mover_radius', true ) ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Job Types', 'go-deliver' ); ?></th>
						<td>
							<?php
							$saved_job_types  = (array) get_user_meta( $uid, 'gd_mover_job_types', true );
							$job_type_options = array(
								'trademe_pickup' => __( 'TradeMe Purchase Pickup', 'go-deliver' ),
								'item'           => __( 'Item', 'go-deliver' ),
								'move'           => __( 'Move', 'go-deliver' ),
								'vehicle'        => __( 'Vehicle', 'go-deliver' ),
								'boat'           => __( 'Boat', 'go-deliver' ),
								'piano'          => __( 'Piano', 'go-deliver' ),
								'pet'            => __( 'Pet', 'go-deliver' ),
								'junk'           => __( 'Junk', 'go-deliver' ),
								'other'          => __( 'Other', 'go-deliver' ),
							);
							foreach ( $job_type_options as $slug => $label ) :
							?>
								<label style="display:inline-flex;align-items:center;gap:6px;margin-right:14px;margin-bottom:6px;">
									<input type="checkbox" name="job_types[]" value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $saved_job_types, true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary" id="gd-mover-edit-submit">
					<?php esc_html_e( 'Save Changes', 'go-deliver' ); ?>
				</button>
			</p>
		</form>
	</div><!-- /.gd-section: edit profile -->

	<!-- ======================================================
	     Mover Actions
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Actions', 'go-deliver' ); ?></h2>

		<div class="gd-approval-actions">
			<?php if ( 'approved' !== $status ) : ?>
				<button
					type="button"
					class="button gd-btn-approve gd-action-approve"
					data-user-id="<?php echo esc_attr( $uid ); ?>"
					data-action="gd_approve_mover"
					data-confirm="<?php esc_attr_e( 'Approve this mover?', 'go-deliver' ); ?>"
				><?php esc_html_e( 'Approve Mover', 'go-deliver' ); ?></button>
			<?php endif; ?>

			<?php if ( 'rejected' !== $status ) : ?>
				<button
					type="button"
					class="button gd-btn-reject gd-action-reject"
					data-user-id="<?php echo esc_attr( $uid ); ?>"
					data-action="gd_reject_mover"
					data-confirm="<?php esc_attr_e( 'Reject this mover? Add a reason below.', 'go-deliver' ); ?>"
				><?php esc_html_e( 'Reject Mover', 'go-deliver' ); ?></button>
			<?php endif; ?>

			<?php if ( 'suspended' !== $status ) : ?>
				<button
					type="button"
					class="button gd-btn-suspend gd-action-suspend"
					data-user-id="<?php echo esc_attr( $uid ); ?>"
					data-action="gd_suspend_mover"
					data-confirm="<?php esc_attr_e( 'Suspend this mover?', 'go-deliver' ); ?>"
				><?php esc_html_e( 'Suspend Mover', 'go-deliver' ); ?></button>
			<?php endif; ?>
		</div><!-- /.gd-approval-actions -->

		<div id="gd-reason-wrap-<?php echo esc_attr( $uid ); ?>" class="gd-reason-wrap">
			<label for="gd-rejection-reason-<?php echo esc_attr( $uid ); ?>">
				<?php esc_html_e( 'Reason (optional, shown to mover):', 'go-deliver' ); ?>
			</label>
			<textarea
				id="gd-rejection-reason-<?php echo esc_attr( $uid ); ?>"
				placeholder="<?php esc_attr_e( 'Enter reason…', 'go-deliver' ); ?>"
				rows="3"
			></textarea>
		</div><!-- /.gd-reason-wrap -->
	</div><!-- /.gd-section: actions -->

	<!-- ======================================================
	     Documents
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Uploaded Documents', 'go-deliver' ); ?></h2>

		<?php if ( empty( $documents ) ) : ?>
			<p><?php esc_html_e( 'No documents uploaded.', 'go-deliver' ); ?></p>
		<?php else : ?>
			<div class="gd-table-wrap">
				<table class="gd-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Type', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Status', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Uploaded', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'go-deliver' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $documents as $doc ) :
							$doc_id    = isset( $doc->id )         ? (int) $doc->id              : 0;
							$doc_type  = isset( $doc->doc_type )   ? (string) $doc->doc_type     : '';
							$file_url  = isset( $doc->file_url )   ? (string) $doc->file_url     : '';
							$doc_stat  = isset( $doc->status )     ? (string) $doc->status       : 'pending';
							$doc_date  = isset( $doc->created_at ) ? (string) $doc->created_at   : '';
							if ( ! in_array( $doc_stat, $doc_valid_statuses, true ) ) {
								$doc_stat = 'pending';
							}
							$doc_date_fmt = $doc_date
								? esc_html( date_i18n( 'd M Y H:i', strtotime( $doc_date ) ) )
								: '—';
						?>
						<tr id="gd-doc-row-<?php echo esc_attr( $doc_id ); ?>">
							<td data-label="<?php esc_attr_e( 'ID', 'go-deliver' ); ?>"><?php echo esc_html( $doc_id ); ?></td>
							<td data-label="<?php esc_attr_e( 'Type', 'go-deliver' ); ?>">
								<?php echo esc_html( str_replace( '_', ' ', $doc_type ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'go-deliver' ); ?>">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function
								echo gd_approval_badge( $doc_stat, $doc_valid_statuses );
								?>
								<!-- hidden class name for JS to target -->
								<span class="gd-doc-status" style="display:none;" data-doc-id="<?php echo esc_attr( $doc_id ); ?>"></span>
							</td>
							<td data-label="<?php esc_attr_e( 'Uploaded', 'go-deliver' ); ?>"><?php echo $doc_date_fmt; ?></td>
							<td data-label="<?php esc_attr_e( 'Actions', 'go-deliver' ); ?>">
								<?php if ( $file_url ) : ?>
									<a
										href="<?php echo esc_url( $file_url ); ?>"
										target="_blank"
										rel="noopener noreferrer"
										class="button button-small"
									><?php esc_html_e( 'View', 'go-deliver' ); ?></a>
								<?php endif; ?>

								<?php if ( 'approved' !== $doc_stat ) : ?>
									<button
										type="button"
										class="button button-small gd-btn-approve gd-doc-approve"
										data-doc-id="<?php echo esc_attr( $doc_id ); ?>"
										data-status="approved"
									><?php esc_html_e( 'Approve', 'go-deliver' ); ?></button>
								<?php endif; ?>

								<?php if ( 'rejected' !== $doc_stat ) : ?>
									<button
										type="button"
										class="button button-small gd-btn-reject gd-doc-reject"
										data-doc-id="<?php echo esc_attr( $doc_id ); ?>"
										data-status="rejected"
									><?php esc_html_e( 'Reject', 'go-deliver' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.gd-table-wrap -->
		<?php endif; ?>
	</div><!-- /.gd-section: documents -->

	<!-- ======================================================
	     Status Change History
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Status Change History', 'go-deliver' ); ?></h2>

		<?php if ( empty( $status_log ) ) : ?>
			<p><?php esc_html_e( 'No status history recorded.', 'go-deliver' ); ?></p>
		<?php else : ?>
			<div class="gd-table-wrap">
				<table class="gd-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Changed By', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'From', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'To', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'go-deliver' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $status_log as $entry ) :
							$log_admin_id  = isset( $entry->admin_id )    ? (int) $entry->admin_id      : 0;
							$log_old       = isset( $entry->old_status )  ? (string) $entry->old_status : '';
							$log_new       = isset( $entry->new_status )  ? (string) $entry->new_status : '';
							$log_reason    = isset( $entry->reason )      ? (string) $entry->reason     : '';
							$log_date      = isset( $entry->changed_at )  ? (string) $entry->changed_at : '';
							$log_admin     = get_userdata( $log_admin_id );
							$log_admin_name = $log_admin ? esc_html( $log_admin->display_name ) : esc_html__( 'System', 'go-deliver' );
							$log_date_fmt  = $log_date
								? esc_html( date_i18n( 'd M Y H:i', strtotime( $log_date ) ) )
								: '—';
						?>
						<tr>
							<td><?php echo $log_date_fmt; ?></td>
							<td><?php echo $log_admin_name; ?></td>
							<td>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function
								echo $log_old ? gd_approval_badge( $log_old, $valid_statuses ) : '—';
								?>
							</td>
							<td>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function
								echo $log_new ? gd_approval_badge( $log_new, $valid_statuses ) : '—';
								?>
							</td>
							<td><?php echo $log_reason ? esc_html( $log_reason ) : '—'; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.gd-table-wrap -->
		<?php endif; ?>
	</div><!-- /.gd-section: status history -->

	<!-- ======================================================
	     Wallet Transactions
	     ====================================================== -->
	<div class="gd-section">
		<h2><?php esc_html_e( 'Wallet Transaction History', 'go-deliver' ); ?></h2>

		<?php if ( empty( $transactions ) ) : ?>
			<p><?php esc_html_e( 'No transactions found.', 'go-deliver' ); ?></p>
		<?php else : ?>
			<div class="gd-table-wrap">
				<table class="gd-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Type', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Description', 'go-deliver' ); ?></th>
							<th><?php esc_html_e( 'Date', 'go-deliver' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $transactions as $txn ) :
							$txn_id    = isset( $txn->id )          ? (int) $txn->id           : 0;
							$txn_type  = isset( $txn->type )        ? (string) $txn->type      : 'adjustment';
							$txn_amt   = isset( $txn->amount )      ? floatval( $txn->amount ) : 0.0;
							$txn_desc  = isset( $txn->description ) ? (string) $txn->description : '';
							$txn_date  = isset( $txn->created_at )  ? (string) $txn->created_at  : '';
							if ( ! in_array( $txn_type, $txn_valid_types, true ) ) {
								$txn_type = 'adjustment';
							}
							$txn_amount_class  = $txn_amt >= 0 ? 'gd-amount-positive' : 'gd-amount-negative';
							$txn_amount_prefix = $txn_amt >= 0 ? '+' : '';
							$txn_amount_fmt    = $txn_amount_prefix . '$' . number_format( abs( $txn_amt ), 2 );
							$txn_date_fmt      = $txn_date
								? esc_html( date_i18n( 'd M Y H:i', strtotime( $txn_date ) ) )
								: '—';
						?>
						<tr>
							<td><?php echo esc_html( $txn_id ); ?></td>
							<td>
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function
								echo '<span class="gd-badge gd-badge-' . esc_attr( $txn_type ) . '">' . esc_html( $txn_type ) . '</span>';
								?>
							</td>
							<td>
								<span class="<?php echo esc_attr( $txn_amount_class ); ?>">
									<?php echo esc_html( $txn_amount_fmt ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $txn_desc ); ?></td>
							<td><?php echo $txn_date_fmt; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div><!-- /.gd-table-wrap -->
		<?php endif; ?>
	</div><!-- /.gd-section: transactions -->

</div><!-- /.gd-admin-wrap -->
