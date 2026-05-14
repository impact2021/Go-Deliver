<?php
/**
 * Admin Emails Page partial.
 *
 * Displays every outgoing email template organised by recipient group, with a
 * live HTML preview for each.  The "Emails to Site Admin" tab also lets you
 * configure which admin users receive each notification type.
 *
 * Tabs: Emails to Customers | Emails to Movers | Emails to Site Admin
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Handle save of admin-recipient settings ───────────────────────────────────
$gd_emails_saved = false;
if (
	isset( $_POST['gd_emails_nonce'] ) &&
	wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gd_emails_nonce'] ) ), 'gd_save_admin_recipients' ) &&
	current_user_can( 'manage_options' )
) {
	// Fetch every administrator user ID so we can validate the incoming list.
	$valid_admin_ids = array_map(
		'intval',
		get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) )
	);

	// One option key per notification event.
	foreach ( array( 'new_mover' ) as $event ) {
		$option_key = 'gd_notify_admins_' . $event;
		$raw        = isset( $_POST[ $option_key ] ) ? (array) $_POST[ $option_key ] : array();
		$clean      = array_values( array_intersect( array_map( 'absint', $raw ), $valid_admin_ids ) );
		update_option( $option_key, $clean );
	}

	$gd_emails_saved = true;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'customers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// ── Preview helper ────────────────────────────────────────────────────────────

if ( ! function_exists( 'gd_admin_email_preview' ) ) {
	/**
	 * Render an email template with sample variables and return the HTML.
	 *
	 * @param string $template_file Filename inside templates/emails/.
	 * @param array  $vars          Sample variables to inject into the template.
	 * @return string Rendered HTML.
	 */
	function gd_admin_email_preview( $template_file, $vars ) {
		foreach ( $vars as $k => $v ) {
			$$k = $v;
		}
		ob_start();
		$path = GD_PLUGIN_DIR . 'templates/emails/' . $template_file;
		if ( file_exists( $path ) ) {
			include $path;
		}
		return ob_get_clean();
	}
}

// ── Shared sample values ──────────────────────────────────────────────────────
$gd_site_name = get_bloginfo( 'name' ) ?: 'Go Deliver';
$gd_site_url  = home_url();

// ── Email definitions: each entry is rendered as a preview card ───────────────

$gd_customer_emails = array(
	array(
		'title'    => __( 'Job Posted Confirmation', 'go-deliver' ),
		'trigger'  => __( 'Sent to the customer immediately after they post a new job.', 'go-deliver' ),
		'file'     => 'job-posted-confirmation.php',
		'vars'     => array(
			'customer_first_name' => 'Sarah',
			'job_id'              => 1234,
			'job_type'            => 'House Move',
			'pickup_suburb'       => 'Ponsonby',
			'dropoff_suburb'      => 'Newmarket',
			'date_requested'      => '15 June 2025',
			'dashboard_url'       => $gd_site_url,
			'site_name'           => $gd_site_name,
			'site_url'            => $gd_site_url,
		),
	),
	array(
		'title'    => __( 'New Quote Received', 'go-deliver' ),
		'trigger'  => __( 'Sent to the customer when a mover submits a quote on their job.', 'go-deliver' ),
		'file'     => 'quote-submitted.php',
		'vars'     => array(
			'customer_first_name' => 'Sarah',
			'mover_first_name'    => 'Mike',
			'mover_rating'        => 4.7,
			'mover_review_count'  => 23,
			'quote_amount'        => 480,
			'job_type'            => 'House Move',
			'quotes_url'          => $gd_site_url,
			'site_name'           => $gd_site_name,
			'site_url'            => $gd_site_url,
		),
	),
	array(
		'title'    => __( 'Your Mover is Confirmed', 'go-deliver' ),
		'trigger'  => __( 'Sent to the customer after they accept a quote, with mover contact details.', 'go-deliver' ),
		'file'     => 'job-assigned.php',
		'vars'     => array(
			'customer_first_name' => 'Sarah',
			'job_type'            => 'House Move',
			'pickup_suburb'       => 'Ponsonby',
			'date_requested'      => '15 June 2025',
			'mover_name'          => 'Mike Johnson',
			'mover_phone'         => '021 123 4567',
			'mover_email'         => 'mike@example.com',
			'quote_amount'        => 480,
			'dashboard_url'       => $gd_site_url,
			'site_name'           => $gd_site_name,
			'site_url'            => $gd_site_url,
		),
	),
	array(
		'title'    => __( 'Your Move is Complete', 'go-deliver' ),
		'trigger'  => __( 'Sent to the customer when the mover marks the job as completed, prompting a review.', 'go-deliver' ),
		'file'     => 'job-completed.php',
		'vars'     => array(
			'customer_first_name' => 'Sarah',
			'mover_first_name'    => 'Mike',
			'job_type'            => 'House Move',
			'review_url'          => $gd_site_url,
			'site_name'           => $gd_site_name,
			'site_url'            => $gd_site_url,
		),
	),
	array(
		'title'    => __( 'New Message Notification', 'go-deliver' ),
		'trigger'  => __( 'Sent to the customer when they receive a new message from a mover.', 'go-deliver' ),
		'file'     => 'new-message.php',
		'vars'     => array(
			'recipient_first_name' => 'Sarah',
			'sender_first_name'    => 'Mike',
			'job_type'             => 'House Move',
			'message_preview'      => 'Hi Sarah, I can have a truck at your address by 8am. Does that work for you?',
			'conversation_url'     => $gd_site_url,
			'site_name'            => $gd_site_name,
			'site_url'             => $gd_site_url,
		),
	),
	array(
		'title'    => __( 'Unread Messages Reminder', 'go-deliver' ),
		'trigger'  => __( 'Sent hourly by cron when the customer has unread messages waiting.', 'go-deliver' ),
		'file'     => 'unread-messages.php',
		'vars'     => array(
			'recipient_first_name' => 'Sarah',
			'unread_count'         => 3,
			'messaging_url'        => $gd_site_url,
			'site_name'            => $gd_site_name,
			'site_url'             => $gd_site_url,
		),
	),
);

$gd_mover_emails = array(
	array(
		'title'   => __( 'Welcome to GoDeliver', 'go-deliver' ),
		'trigger' => __( 'Sent to new movers immediately after they complete registration.', 'go-deliver' ),
		'file'    => 'mover-welcome.php',
		'vars'    => array(
			'mover_first_name' => 'Mike',
			'login_url'        => wp_login_url(),
			'site_name'        => $gd_site_name,
			'site_url'         => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'New Job Available', 'go-deliver' ),
		'trigger' => __( 'Sent to movers in the matching service area when a new job is posted.', 'go-deliver' ),
		'file'    => 'new-job-notification.php',
		'vars'    => array(
			'mover_first_name' => 'Mike',
			'job_id'           => 1234,
			'job_type'         => 'House Move',
			'pickup_suburb'    => 'Ponsonby',
			'dropoff_suburb'   => 'Newmarket',
			'date_requested'   => '15 June 2025',
			'job_url'          => $gd_site_url,
			'site_name'        => $gd_site_name,
			'site_url'         => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'Quote Accepted', 'go-deliver' ),
		'trigger' => __( 'Sent to the mover when a customer accepts their quote, with the customer\'s contact details.', 'go-deliver' ),
		'file'    => 'quote-accepted.php',
		'vars'    => array(
			'mover_first_name' => 'Mike',
			'job_type'         => 'House Move',
			'pickup_full'      => '14 Ponsonby Road, Ponsonby, Auckland',
			'dropoff_full'     => '8 Broadway, Newmarket, Auckland',
			'date_requested'   => '15 June 2025',
			'customer_name'    => 'Sarah Thompson',
			'customer_phone'   => '021 987 6543',
			'customer_email'   => 'sarah@example.com',
			'quote_amount'     => 480,
			'fee_amount'       => 24,
			'job_url'          => $gd_site_url,
			'site_name'        => $gd_site_name,
			'site_url'         => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'Job Cancelled', 'go-deliver' ),
		'trigger' => __( 'Sent to the mover when a customer cancels a job after the quote was accepted. Includes a wallet refund notice.', 'go-deliver' ),
		'file'    => 'job-cancelled.php',
		'vars'    => array(
			'mover_first_name' => 'Mike',
			'job_type'         => 'House Move',
			'refund_amount'    => 24.00,
			'dashboard_url'    => $gd_site_url,
			'site_name'        => $gd_site_name,
			'site_url'         => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'Team Member Added', 'go-deliver' ),
		'trigger' => __( 'Sent to a newly added sub-user when a mover adds them to their team.', 'go-deliver' ),
		'file'    => 'team-member-added.php',
		'vars'    => array(
			'member_first_name' => 'Jake',
			'member_username'   => 'jake.driver',
			'team_name'         => 'Johnson Removals',
			'reset_url'         => wp_login_url(),
			'login_url'         => wp_login_url(),
			'dashboard_url'     => $gd_site_url,
			'site_name'         => $gd_site_name,
			'site_url'          => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'New Message Notification', 'go-deliver' ),
		'trigger' => __( 'Sent to the mover when they receive a new message from a customer.', 'go-deliver' ),
		'file'    => 'new-message.php',
		'vars'    => array(
			'recipient_first_name' => 'Mike',
			'sender_first_name'    => 'Sarah',
			'job_type'             => 'House Move',
			'message_preview'      => 'Hi Mike, can you confirm whether your truck can handle a piano?',
			'conversation_url'     => $gd_site_url,
			'site_name'            => $gd_site_name,
			'site_url'             => $gd_site_url,
		),
	),
	array(
		'title'   => __( 'Unread Messages Reminder', 'go-deliver' ),
		'trigger' => __( 'Sent hourly by cron when the mover has unread messages waiting.', 'go-deliver' ),
		'file'    => 'unread-messages.php',
		'vars'    => array(
			'recipient_first_name' => 'Mike',
			'unread_count'         => 2,
			'messaging_url'        => $gd_site_url,
			'site_name'            => $gd_site_name,
			'site_url'             => $gd_site_url,
		),
	),
);

// Admin notification definitions — each has an option key for recipient config.
$gd_admin_notifications = array(
	array(
		'title'      => __( 'New Mover Registration', 'go-deliver' ),
		'trigger'    => __( 'Sent when a mover registers and their account is awaiting approval.', 'go-deliver' ),
		'option_key' => 'gd_notify_admins_new_mover',
		'preview'    => sprintf(
			/* translators: 1: site name, 2: admin URL placeholder */
			__(
				"Subject: [%1\$s] New Mover Registration Pending Approval\n\n"
				. "A new mover has registered and requires approval.\n\n"
				. "Name:   Jane Driver\n"
				. "Email:  jane@example.com\n\n"
				. "Approve or reject: %2\$s",
				'go-deliver'
			),
			esc_html( $gd_site_name ),
			esc_url( admin_url( 'admin.php?page=go-deliver-movers' ) )
		),
	),
);

// Current saved recipient lists.
$gd_notify_new_mover = (array) get_option( 'gd_notify_admins_new_mover', array() );

// All administrator users for the recipient checkboxes.
$gd_all_admins = get_users( array( 'role' => 'administrator' ) );
?>
<div class="wrap gd-admin-wrap">

	<h1><?php esc_html_e( 'Go Deliver – Emails', 'go-deliver' ); ?></h1>

	<?php if ( $gd_emails_saved ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Recipient settings saved.', 'go-deliver' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="gd-tabs" aria-label="<?php esc_attr_e( 'Email tabs', 'go-deliver' ); ?>">
		<a href="#customers" class="gd-tab-link<?php echo 'customers' === $active_tab ? ' is-active' : ''; ?>" data-tab="customers">
			<?php esc_html_e( 'Emails to Customers', 'go-deliver' ); ?>
		</a>
		<a href="#movers" class="gd-tab-link<?php echo 'movers' === $active_tab ? ' is-active' : ''; ?>" data-tab="movers">
			<?php esc_html_e( 'Emails to Movers', 'go-deliver' ); ?>
		</a>
		<a href="#site-admin" class="gd-tab-link<?php echo 'site-admin' === $active_tab ? ' is-active' : ''; ?>" data-tab="site-admin">
			<?php esc_html_e( 'Emails to Site Admin', 'go-deliver' ); ?>
		</a>
	</nav>

	<!-- ======================================================
	     Customers tab
	     ====================================================== -->
	<div id="gd-tab-customers" class="gd-tab-panel<?php echo 'customers' === $active_tab ? ' is-active' : ''; ?>">
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'These emails are sent to customers at key stages of their job.', 'go-deliver' ); ?>
		</p>
		<div class="gd-email-grid">
			<?php foreach ( $gd_customer_emails as $gd_email ) : ?>
				<div class="gd-email-card">
					<div class="gd-email-card__header">
						<div>
							<p class="gd-email-card__title"><?php echo esc_html( $gd_email['title'] ); ?></p>
							<p class="gd-email-card__trigger"><?php echo esc_html( $gd_email['trigger'] ); ?></p>
						</div>
						<span class="gd-email-card__badge"><?php esc_html_e( 'Customer', 'go-deliver' ); ?></span>
					</div>
					<div class="gd-email-preview">
						<iframe
							class="gd-email-preview__iframe"
							srcdoc="<?php echo esc_attr( gd_admin_email_preview( $gd_email['file'], $gd_email['vars'] ) ); ?>"
							sandbox="allow-same-origin"
							loading="lazy"
							title="<?php echo esc_attr( $gd_email['title'] ); ?>"
						></iframe>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div><!-- /#gd-tab-customers -->

	<!-- ======================================================
	     Movers tab
	     ====================================================== -->
	<div id="gd-tab-movers" class="gd-tab-panel<?php echo 'movers' === $active_tab ? ' is-active' : ''; ?>">
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'These emails are sent to movers at key stages of a job or their account.', 'go-deliver' ); ?>
		</p>
		<div class="gd-email-grid">
			<?php foreach ( $gd_mover_emails as $gd_email ) : ?>
				<div class="gd-email-card">
					<div class="gd-email-card__header">
						<div>
							<p class="gd-email-card__title"><?php echo esc_html( $gd_email['title'] ); ?></p>
							<p class="gd-email-card__trigger"><?php echo esc_html( $gd_email['trigger'] ); ?></p>
						</div>
						<span class="gd-email-card__badge gd-email-card__badge--mover"><?php esc_html_e( 'Mover', 'go-deliver' ); ?></span>
					</div>
					<div class="gd-email-preview">
						<iframe
							class="gd-email-preview__iframe"
							srcdoc="<?php echo esc_attr( gd_admin_email_preview( $gd_email['file'], $gd_email['vars'] ) ); ?>"
							sandbox="allow-same-origin"
							loading="lazy"
							title="<?php echo esc_attr( $gd_email['title'] ); ?>"
						></iframe>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div><!-- /#gd-tab-movers -->

	<!-- ======================================================
	     Site Admin tab
	     ====================================================== -->
	<div id="gd-tab-site-admin" class="gd-tab-panel<?php echo 'site-admin' === $active_tab ? ' is-active' : ''; ?>">
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'These emails are sent to site administrators. Use the checkboxes below each email to choose which admins receive that notification.', 'go-deliver' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'gd_save_admin_recipients', 'gd_emails_nonce' ); ?>

			<div class="gd-email-grid">

				<?php foreach ( $gd_admin_notifications as $gd_notif ) :
					// Retrieve the currently-saved recipient IDs for this notification.
					$gd_current_recipients = (array) get_option( $gd_notif['option_key'], array() );
				?>
					<div class="gd-email-card">
						<div class="gd-email-card__header">
							<div>
								<p class="gd-email-card__title"><?php echo esc_html( $gd_notif['title'] ); ?></p>
								<p class="gd-email-card__trigger"><?php echo esc_html( $gd_notif['trigger'] ); ?></p>
							</div>
							<span class="gd-email-card__badge gd-email-card__badge--admin"><?php esc_html_e( 'Site Admin', 'go-deliver' ); ?></span>
						</div>

						<!-- Plain-text preview -->
						<div class="gd-email-preview">
							<pre class="gd-email-text-preview"><?php echo esc_html( $gd_notif['preview'] ); ?></pre>
						</div>

						<!-- Recipient selector -->
						<div class="gd-recipients-section">
							<h4 class="gd-recipients-section__title">
								📬 <?php esc_html_e( 'Who receives this notification?', 'go-deliver' ); ?>
							</h4>

							<?php if ( empty( $gd_all_admins ) ) : ?>
								<p class="description"><?php esc_html_e( 'No administrator accounts found.', 'go-deliver' ); ?></p>
							<?php else : ?>
								<p class="description" style="margin-bottom:10px;">
									<?php esc_html_e( 'If no admins are selected, the notification falls back to the WordPress site admin email.', 'go-deliver' ); ?>
								</p>
								<ul class="gd-recipients-list">
									<?php foreach ( $gd_all_admins as $gd_admin_user ) : ?>
										<li>
											<label class="gd-recipients-list__label">
												<input
													type="checkbox"
													name="<?php echo esc_attr( $gd_notif['option_key'] ); ?>[]"
													value="<?php echo esc_attr( $gd_admin_user->ID ); ?>"
													<?php checked( in_array( (int) $gd_admin_user->ID, array_map( 'intval', $gd_current_recipients ), true ) ); ?>
												>
												<span class="gd-recipients-list__name"><?php echo esc_html( $gd_admin_user->display_name ); ?></span>
												<span class="gd-recipients-list__email">&lt;<?php echo esc_html( $gd_admin_user->user_email ); ?>&gt;</span>
											</label>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div><!-- /.gd-recipients-section -->
					</div><!-- /.gd-email-card -->

				<?php endforeach; ?>

			</div><!-- /.gd-email-grid -->

			<p class="submit" style="margin-top:8px;">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Recipient Settings', 'go-deliver' ); ?>
				</button>
			</p>
		</form>
	</div><!-- /#gd-tab-site-admin -->

</div><!-- /.gd-admin-wrap -->
