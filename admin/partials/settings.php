<?php
/**
 * Admin Settings Page partial.
 *
 * Tabs: General | Stripe | Email
 * Variables available from caller:
 *   (none required – all options fetched directly)
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Handle form submission.
$saved   = false;
$save_error = '';
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( isset( $_POST['gd_settings_nonce'] ) ) {
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gd_settings_nonce'] ) ), 'gd_save_settings' ) ) {
		$save_error = __( 'Security check failed.', 'go-deliver' );
	} elseif ( ! current_user_can( 'manage_options' ) ) {
		$save_error = __( 'Permission denied.', 'go-deliver' );
	} else {
		$tab = isset( $_POST['gd_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['gd_active_tab'] ) ) : 'general';
		$active_tab = $tab;

		if ( 'general' === $tab ) {
			update_option( 'gd_fee_percentage', floatval( $_POST['gd_fee_percentage'] ?? 0 ) );
			update_option( 'gd_job_expiry_days', absint( $_POST['gd_job_expiry_days'] ?? 7 ) );
			update_option( 'gd_quote_expiry_days', absint( $_POST['gd_quote_expiry_days'] ?? 3 ) );
			update_option( 'gd_google_maps_api_key', sanitize_text_field( wp_unslash( $_POST['gd_google_maps_api_key'] ?? '' ) ) );
			update_option( 'gd_job_form_page_id', absint( $_POST['gd_job_form_page_id'] ?? 0 ) );
			update_option( 'gd_customer_dashboard_page_id', absint( $_POST['gd_customer_dashboard_page_id'] ?? 0 ) );
			update_option( 'gd_mover_dashboard_page_id', absint( $_POST['gd_mover_dashboard_page_id'] ?? 0 ) );
			update_option( 'gd_messaging_page_id', absint( $_POST['gd_messaging_page_id'] ?? 0 ) );
			update_option( 'gd_wallet_page_id', absint( $_POST['gd_wallet_page_id'] ?? 0 ) );
			update_option( 'gd_job_redirect_page_id', absint( $_POST['gd_job_redirect_page_id'] ?? 0 ) );
			update_option( 'gd_mover_reg_redirect_page_id', absint( $_POST['gd_mover_reg_redirect_page_id'] ?? 0 ) );
			update_option( 'gd_mover_terms_page_id', absint( $_POST['gd_mover_terms_page_id'] ?? 0 ) );
			update_option( 'gd_customer_terms_page_id', absint( $_POST['gd_customer_terms_page_id'] ?? 0 ) );
			update_option( 'gd_help_centre_page_id', absint( $_POST['gd_help_centre_page_id'] ?? 0 ) );
			update_option( 'gd_debug_panel', isset( $_POST['gd_debug_panel'] ) ? 1 : 0 );
		} elseif ( 'stripe' === $tab ) {
			update_option( 'gd_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['gd_stripe_publishable_key'] ?? '' ) ) );
			if ( ! empty( $_POST['gd_stripe_secret_key'] ) ) {
				update_option( 'gd_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['gd_stripe_secret_key'] ) ) );
			}
			if ( ! empty( $_POST['gd_stripe_webhook_secret'] ) ) {
				update_option( 'gd_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['gd_stripe_webhook_secret'] ) ) );
			}
		} elseif ( 'email' === $tab ) {
			update_option( 'gd_admin_email', sanitize_email( wp_unslash( $_POST['gd_admin_email'] ?? '' ) ) );
			update_option( 'gd_email_from_name', sanitize_text_field( wp_unslash( $_POST['gd_email_from_name'] ?? '' ) ) );
			update_option( 'gd_email_from_address', sanitize_email( wp_unslash( $_POST['gd_email_from_address'] ?? '' ) ) );
		} elseif ( 'appearance' === $tab ) {
			$bg     = sanitize_hex_color( wp_unslash( $_POST['gd_job_card_bg'] ?? '' ) );
			$accent = sanitize_hex_color( wp_unslash( $_POST['gd_job_card_accent'] ?? '' ) );
			if ( $bg ) {
				update_option( 'gd_job_card_bg', $bg );
			}
			if ( $accent ) {
				update_option( 'gd_job_card_accent', $accent );
			}
		}

		$saved = true;
	}
}

// Fetch current values.
$fee_pct                    = floatval( get_option( 'gd_fee_percentage', 10 ) );
$job_expiry                 = absint( get_option( 'gd_job_expiry_days', 7 ) );
$quote_expiry               = absint( get_option( 'gd_quote_expiry_days', 3 ) );
$google_maps_key            = get_option( 'gd_google_maps_api_key', '' );
$job_form_page_id           = absint( get_option( 'gd_job_form_page_id', 0 ) );
$customer_dashboard_page_id = absint( get_option( 'gd_customer_dashboard_page_id', 0 ) );
$mover_dashboard_page_id    = absint( get_option( 'gd_mover_dashboard_page_id', 0 ) );
$messaging_page_id          = absint( get_option( 'gd_messaging_page_id', 0 ) );
$wallet_page_id             = absint( get_option( 'gd_wallet_page_id', 0 ) );
$job_redirect_page_id       = absint( get_option( 'gd_job_redirect_page_id', 0 ) );
$mover_reg_redirect_page_id = absint( get_option( 'gd_mover_reg_redirect_page_id', 0 ) );
$mover_terms_page_id        = absint( get_option( 'gd_mover_terms_page_id', 0 ) );
$customer_terms_page_id     = absint( get_option( 'gd_customer_terms_page_id', 0 ) );
$help_centre_page_id     = absint( get_option( 'gd_help_centre_page_id', 0 ) );
$debug_panel_enabled        = (bool) get_option( 'gd_debug_panel', 1 );
$stripe_pub         = get_option( 'gd_stripe_publishable_key', '' );
$stripe_sec_masked  = get_option( 'gd_stripe_secret_key', '' ) ? '••••••••••••••••' : '';
$stripe_wh_masked   = get_option( 'gd_stripe_webhook_secret', '' ) ? '••••••••••••••••' : '';
$webhook_url        = home_url( '/?gd_stripe_webhook=1' );
$admin_email        = gd_get_admin_email();
$email_from_name    = get_option( 'gd_email_from_name', get_bloginfo( 'name' ) );
$email_from_address = get_option( 'gd_email_from_address', gd_get_admin_email() );
$job_card_bg        = get_option( 'gd_job_card_bg', '#2D1B0E' );
$job_card_accent    = get_option( 'gd_job_card_accent', '#C9A227' );
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Go Deliver Settings', 'go-deliver' ); ?></h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'go-deliver' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $save_error ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $save_error ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="gd-tabs" aria-label="<?php esc_attr_e( 'Settings tabs', 'go-deliver' ); ?>">
		<a href="#general" class="gd-tab-link<?php echo 'general' === $active_tab ? ' is-active' : ''; ?>" data-tab="general">
			<?php esc_html_e( 'General', 'go-deliver' ); ?>
		</a>
		<a href="#stripe" class="gd-tab-link<?php echo 'stripe' === $active_tab ? ' is-active' : ''; ?>" data-tab="stripe">
			<?php esc_html_e( 'Stripe', 'go-deliver' ); ?>
		</a>
		<a href="#email" class="gd-tab-link<?php echo 'email' === $active_tab ? ' is-active' : ''; ?>" data-tab="email">
			<?php esc_html_e( 'Email', 'go-deliver' ); ?>
		</a>
		<a href="#appearance" class="gd-tab-link<?php echo 'appearance' === $active_tab ? ' is-active' : ''; ?>" data-tab="appearance">
			<?php esc_html_e( 'Appearance', 'go-deliver' ); ?>
		</a>
	</nav>

	<!-- ======================================================
	     General Tab
	     ====================================================== -->
	<div id="gd-tab-general" class="gd-tab-panel<?php echo 'general' === $active_tab ? ' is-active' : ''; ?>">
		<form method="post" class="gd-settings-form">
			<?php wp_nonce_field( 'gd_save_settings', 'gd_settings_nonce' ); ?>
			<input type="hidden" name="gd_active_tab" value="general">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="gd_fee_percentage"><?php esc_html_e( 'Platform Fee (%)', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="gd_fee_percentage"
								name="gd_fee_percentage"
								class="small-text"
								value="<?php echo esc_attr( $fee_pct ); ?>"
								min="0"
								max="100"
								step="0.01"
							>
							<p class="description"><?php esc_html_e( 'Percentage of each transaction taken as a platform fee (0–100).', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_job_expiry_days"><?php esc_html_e( 'Job Expiry (days)', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="gd_job_expiry_days"
								name="gd_job_expiry_days"
								class="small-text"
								value="<?php echo esc_attr( $job_expiry ); ?>"
								min="1"
								step="1"
							>
							<p class="description"><?php esc_html_e( 'Number of days before an open job automatically expires.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_quote_expiry_days"><?php esc_html_e( 'Quote Expiry (days)', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="gd_quote_expiry_days"
								name="gd_quote_expiry_days"
								class="small-text"
								value="<?php echo esc_attr( $quote_expiry ); ?>"
								min="1"
								step="1"
							>
							<p class="description"><?php esc_html_e( 'Number of days before an unaccepted quote expires.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_google_maps_api_key"><?php esc_html_e( 'Google Maps API Key', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="gd_google_maps_api_key"
								name="gd_google_maps_api_key"
								class="regular-text"
								value="<?php echo esc_attr( $google_maps_key ); ?>"
								placeholder="AIza…"
								autocomplete="off"
							>
							<p class="description"><?php esc_html_e( 'Google Maps API key with the Places API enabled. Used for address autocomplete on the job submission form.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_job_form_page_id"><?php esc_html_e( 'Job Form Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_job_form_page_id',
									'id'                => 'gd_job_form_page_id',
									'selected'          => $job_form_page_id,
									'show_option_none'  => __( '— Not set —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page containing the [gd_job_form] shortcode. Used for the "Post a Job" link in the customer dashboard.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_customer_dashboard_page_id"><?php esc_html_e( 'Customer Dashboard Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_customer_dashboard_page_id',
									'id'                => 'gd_customer_dashboard_page_id',
									'selected'          => $customer_dashboard_page_id,
									'show_option_none'  => __( '— Not set —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page containing the [gd_customer_dashboard] or [gd_dashboard] shortcode. Used for links in customer email notifications.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_mover_dashboard_page_id"><?php esc_html_e( 'Mover Dashboard Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_mover_dashboard_page_id',
									'id'                => 'gd_mover_dashboard_page_id',
									'selected'          => $mover_dashboard_page_id,
									'show_option_none'  => __( '— Not set —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page containing the [gd_mover_dashboard] or [gd_dashboard] shortcode. Used for links in mover email notifications and after mover registration.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_messaging_page_id"><?php esc_html_e( 'Messaging Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_messaging_page_id',
									'id'                => 'gd_messaging_page_id',
									'selected'          => $messaging_page_id,
									'show_option_none'  => __( '— Not set —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page containing the [gd_messaging] shortcode. Used for messaging links in the mover dashboard, job detail view, and email notifications. This must be set for movers to access messaging.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_wallet_page_id"><?php esc_html_e( 'Wallet Top-Up Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_wallet_page_id',
									'id'                => 'gd_wallet_page_id',
									'selected'          => $wallet_page_id,
									'show_option_none'  => __( '— Not set —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page containing the [gd_wallet_topup] shortcode. Used for the "Top Up Wallet" link in the mover dashboard and quote form.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_job_redirect_page_id"><?php esc_html_e( 'Job Submission Redirect Page', 'go-deliver' ); ?></label>
						</th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => 'gd_job_redirect_page_id',
									'id'                => 'gd_job_redirect_page_id',
									'selected'          => $job_redirect_page_id,
									'show_option_none'  => __( '— Reset form (no redirect) —', 'go-deliver' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Page to redirect customers to after a job is successfully submitted. Leave blank to reset the form instead.', 'go-deliver' ); ?></p>
						</td>
					</tr>
				<tr>
					<th scope="row">
						<label for="gd_mover_reg_redirect_page_id"><?php esc_html_e( 'Mover Registration Redirect Page', 'go-deliver' ); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'gd_mover_reg_redirect_page_id',
								'id'                => 'gd_mover_reg_redirect_page_id',
								'selected'          => $mover_reg_redirect_page_id,
								'show_option_none'  => __( '— Show success message (no redirect) —', 'go-deliver' ),
								'option_none_value' => '0',
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Page to redirect movers to after registration is successfully submitted. Leave blank to show a success message instead.', 'go-deliver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gd_mover_terms_page_id"><?php esc_html_e( 'Mover Terms &amp; Conditions Page', 'go-deliver' ); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'gd_mover_terms_page_id',
								'id'                => 'gd_mover_terms_page_id',
								'selected'          => $mover_terms_page_id,
								'show_option_none'  => __( '— None —', 'go-deliver' ),
								'option_none_value' => '0',
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Page containing the Terms &amp; Conditions for movers. Linked from the mover registration form.', 'go-deliver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gd_customer_terms_page_id"><?php esc_html_e( 'Customer Terms &amp; Conditions Page', 'go-deliver' ); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'gd_customer_terms_page_id',
								'id'                => 'gd_customer_terms_page_id',
								'selected'          => $customer_terms_page_id,
								'show_option_none'  => __( '— None —', 'go-deliver' ),
								'option_none_value' => '0',
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Page containing the Terms &amp; Conditions for customers. Linked from the job submission form.', 'go-deliver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="gd_help_centre_page_id"><?php esc_html_e( 'Help Centre Page', 'go-deliver' ); ?></label>
					</th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'gd_help_centre_page_id',
								'id'                => 'gd_help_centre_page_id',
								'selected'          => $help_centre_page_id,
								'show_option_none'  => __( '— None —', 'go-deliver' ),
								'option_none_value' => '0',
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Page for the Help Centre / helpdesk. Shown in the customer dashboard sidebar and overview. Leave as None to hide.', 'go-deliver' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<?php esc_html_e( 'Debug Panel', 'go-deliver' ); ?>
					</th>
					<td>
						<label class="gd-toggle-label">
							<input
								type="checkbox"
								id="gd_debug_panel"
								name="gd_debug_panel"
								value="1"
								<?php checked( $debug_panel_enabled ); ?>
							>
							<?php esc_html_e( 'Show debug panel on the front end (admins only)', 'go-deliver' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Displays a floating debug panel visible only to logged-in administrators. Shows AJAX request/response log, JS errors, and server info. On by default.', 'go-deliver' ); ?></p>
					</td>
				</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save General Settings', 'go-deliver' ) ); ?>
		</form>
	</div><!-- /#gd-tab-general -->

	<!-- ======================================================
	     Stripe Tab
	     ====================================================== -->
	<div id="gd-tab-stripe" class="gd-tab-panel<?php echo 'stripe' === $active_tab ? ' is-active' : ''; ?>">
		<form method="post" class="gd-settings-form">
			<?php wp_nonce_field( 'gd_save_settings', 'gd_settings_nonce' ); ?>
			<input type="hidden" name="gd_active_tab" value="stripe">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="gd_stripe_publishable_key"><?php esc_html_e( 'Publishable Key', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="gd_stripe_publishable_key"
								name="gd_stripe_publishable_key"
								class="regular-text"
								value="<?php echo esc_attr( $stripe_pub ); ?>"
								placeholder="pk_live_…"
								autocomplete="off"
							>
							<p class="description"><?php esc_html_e( 'Your Stripe publishable key (starts with pk_).', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_stripe_secret_key"><?php esc_html_e( 'Secret Key', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="gd_stripe_secret_key"
								name="gd_stripe_secret_key"
								class="regular-text"
								value=""
								placeholder="<?php echo $stripe_sec_masked ? esc_attr( $stripe_sec_masked ) : 'sk_live_…'; ?>"
								autocomplete="new-password"
							>
							<p class="description">
								<?php
								if ( $stripe_sec_masked ) {
									esc_html_e( 'A secret key is already saved. Enter a new value to replace it, or leave blank to keep the current key.', 'go-deliver' );
								} else {
									esc_html_e( 'Your Stripe secret key (starts with sk_). Stored encrypted in the database.', 'go-deliver' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_stripe_webhook_secret"><?php esc_html_e( 'Webhook Secret', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="gd_stripe_webhook_secret"
								name="gd_stripe_webhook_secret"
								class="regular-text"
								value=""
								placeholder="<?php echo $stripe_wh_masked ? esc_attr( $stripe_wh_masked ) : 'whsec_…'; ?>"
								autocomplete="new-password"
							>
							<p class="description">
								<?php
								if ( $stripe_wh_masked ) {
									esc_html_e( 'A webhook secret is already saved. Enter a new value to replace it.', 'go-deliver' );
								} else {
									esc_html_e( 'Webhook signing secret from your Stripe dashboard (starts with whsec_).', 'go-deliver' );
								}
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Webhook URL', 'go-deliver' ); ?>
						</th>
						<td>
							<div class="gd-input-copy-wrap">
								<input
									type="text"
									id="gd-webhook-url"
									class="regular-text"
									value="<?php echo esc_attr( $webhook_url ); ?>"
									readonly
								>
								<button type="button" id="gd-copy-webhook" class="button">
									<?php esc_html_e( 'Copy', 'go-deliver' ); ?>
								</button>
							</div>
							<p class="description"><?php esc_html_e( 'Add this URL as a webhook endpoint in your Stripe dashboard.', 'go-deliver' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Stripe Settings', 'go-deliver' ) ); ?>
		</form>
	</div><!-- /#gd-tab-stripe -->

	<!-- ======================================================
	     Email Tab
	     ====================================================== -->
	<div id="gd-tab-email" class="gd-tab-panel<?php echo 'email' === $active_tab ? ' is-active' : ''; ?>">
		<form method="post" class="gd-settings-form">
			<?php wp_nonce_field( 'gd_save_settings', 'gd_settings_nonce' ); ?>
			<input type="hidden" name="gd_active_tab" value="email">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="gd_admin_email"><?php esc_html_e( 'Admin / Support Email', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="email"
								id="gd_admin_email"
								name="gd_admin_email"
								class="regular-text"
								value="<?php echo esc_attr( $admin_email ); ?>"
							>
							<p class="description"><?php esc_html_e( 'Used for mover application notifications and support contact links across the plugin.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_email_from_name"><?php esc_html_e( 'From Name', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="gd_email_from_name"
								name="gd_email_from_name"
								class="regular-text"
								value="<?php echo esc_attr( $email_from_name ); ?>"
							>
							<p class="description"><?php esc_html_e( 'The "From" name used in all outgoing emails.', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_email_from_address"><?php esc_html_e( 'From Email', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="email"
								id="gd_email_from_address"
								name="gd_email_from_address"
								class="regular-text"
								value="<?php echo esc_attr( $email_from_address ); ?>"
							>
							<p class="description"><?php esc_html_e( 'The "From" email address used in all outgoing emails.', 'go-deliver' ); ?></p>
							<?php
							// Warn if the configured From Email address is empty or uses a different domain than the site.
							$gd_site_domain = wp_parse_url( home_url(), PHP_URL_HOST );
							$gd_site_domain = $gd_site_domain ? strtolower( ltrim( (string) $gd_site_domain, 'www.' ) ) : '';
							if ( empty( $email_from_address ) ) {
								echo '<p class="description" style="color:#d63638;">';
								esc_html_e( '⚠ From Email is empty. A configured address improves email deliverability and prevents messages from landing in spam.', 'go-deliver' );
								echo '</p>';
							} else {
								$at_pos          = strrpos( $email_from_address, '@' );
								$gd_from_domain  = false !== $at_pos ? strtolower( ltrim( substr( $email_from_address, $at_pos + 1 ), 'www.' ) ) : '';
								// Flag freemail providers.
								$gd_freemail_domains = array( 'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.uk', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com' );
								if ( in_array( $gd_from_domain, $gd_freemail_domains, true ) ) {
									echo '<p class="description" style="color:#d63638;">';
									/* translators: %s: free email domain */
									printf( esc_html__( '⚠ "%s" is a free email provider. Use a mailbox on your own domain (e.g. notifications@%s) for better deliverability and to avoid spam filtering.', 'go-deliver' ), esc_html( $gd_from_domain ), esc_html( $gd_site_domain ) );
									echo '</p>';
								} elseif ( $gd_site_domain && $gd_from_domain !== $gd_site_domain ) {
									echo '<p class="description" style="color:#b45309;">';
									/* translators: 1: from email domain, 2: site domain */
									printf( esc_html__( '⚠ From Email domain (%1$s) does not match your site domain (%2$s). Ensure SPF/DKIM records cover the sending domain, or use an address on %2$s.', 'go-deliver' ), esc_html( $gd_from_domain ), esc_html( $gd_site_domain ) );
									echo '</p>';
								}
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Email Settings', 'go-deliver' ) ); ?>
		</form>
	</div><!-- /#gd-tab-email -->

	<!-- ======================================================
	     Appearance Tab
	     ====================================================== -->
	<div id="gd-tab-appearance" class="gd-tab-panel<?php echo 'appearance' === $active_tab ? ' is-active' : ''; ?>">
		<form method="post" class="gd-settings-form">
			<?php wp_nonce_field( 'gd_save_settings', 'gd_settings_nonce' ); ?>
			<input type="hidden" name="gd_active_tab" value="appearance">

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="gd_job_card_bg"><?php esc_html_e( 'Job Card Background', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="gd_job_card_bg"
								name="gd_job_card_bg"
								class="gd-color-picker"
								value="<?php echo esc_attr( $job_card_bg ); ?>"
								data-default-color="#2D1B0E"
							>
							<p class="description"><?php esc_html_e( 'Background colour of the job cards on the available-jobs board (default: dark brown #2D1B0E).', 'go-deliver' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="gd_job_card_accent"><?php esc_html_e( 'Job Card Accent', 'go-deliver' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="gd_job_card_accent"
								name="gd_job_card_accent"
								class="gd-color-picker"
								value="<?php echo esc_attr( $job_card_accent ); ?>"
								data-default-color="#C9A227"
							>
							<p class="description"><?php esc_html_e( 'Accent / highlight colour used for badges, borders, and the underline on the job board (default: gold #C9A227).', 'go-deliver' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Appearance Settings', 'go-deliver' ) ); ?>
		</form>
	</div><!-- /#gd-tab-appearance -->

</div><!-- /.gd-admin-wrap -->
