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
			update_option( 'gd_job_redirect_page_id', absint( $_POST['gd_job_redirect_page_id'] ?? 0 ) );
			update_option( 'gd_mover_reg_redirect_page_id', absint( $_POST['gd_mover_reg_redirect_page_id'] ?? 0 ) );
			update_option( 'gd_mover_terms_page_id', absint( $_POST['gd_mover_terms_page_id'] ?? 0 ) );
			update_option( 'gd_customer_terms_page_id', absint( $_POST['gd_customer_terms_page_id'] ?? 0 ) );
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
			update_option( 'gd_email_from_name', sanitize_text_field( wp_unslash( $_POST['gd_email_from_name'] ?? '' ) ) );
			update_option( 'gd_email_from_address', sanitize_email( wp_unslash( $_POST['gd_email_from_address'] ?? '' ) ) );
		}

		$saved = true;
	}
}

// Fetch current values.
$fee_pct               = floatval( get_option( 'gd_fee_percentage', 10 ) );
$job_expiry            = absint( get_option( 'gd_job_expiry_days', 7 ) );
$quote_expiry          = absint( get_option( 'gd_quote_expiry_days', 3 ) );
$google_maps_key       = get_option( 'gd_google_maps_api_key', '' );
$job_redirect_page_id  = absint( get_option( 'gd_job_redirect_page_id', 0 ) );
$mover_reg_redirect_page_id = absint( get_option( 'gd_mover_reg_redirect_page_id', 0 ) );
$mover_terms_page_id   = absint( get_option( 'gd_mover_terms_page_id', 0 ) );
$customer_terms_page_id = absint( get_option( 'gd_customer_terms_page_id', 0 ) );
$debug_panel_enabled    = (bool) get_option( 'gd_debug_panel', 1 );
$stripe_pub         = get_option( 'gd_stripe_publishable_key', '' );
$stripe_sec_masked  = get_option( 'gd_stripe_secret_key', '' ) ? '••••••••••••••••' : '';
$stripe_wh_masked   = get_option( 'gd_stripe_webhook_secret', '' ) ? '••••••••••••••••' : '';
$webhook_url        = home_url( '/wp-json/go-deliver/v1/stripe-webhook' );
$email_from_name    = get_option( 'gd_email_from_name', get_bloginfo( 'name' ) );
$email_from_address = get_option( 'gd_email_from_address', get_option( 'admin_email' ) );
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
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'Save Email Settings', 'go-deliver' ) ); ?>
		</form>
	</div><!-- /#gd-tab-email -->

</div><!-- /.gd-admin-wrap -->
