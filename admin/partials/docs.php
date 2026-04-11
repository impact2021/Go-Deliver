<?php
/**
 * Admin Docs Page partial.
 *
 * Tabs: Google Maps API | (more tabs can be added here)
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'google-maps'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Go Deliver – Docs', 'go-deliver' ); ?></h1>

	<nav class="gd-tabs" aria-label="<?php esc_attr_e( 'Docs tabs', 'go-deliver' ); ?>">
		<a href="#google-maps" class="gd-tab-link<?php echo 'google-maps' === $active_tab ? ' is-active' : ''; ?>" data-tab="google-maps">
			<?php esc_html_e( 'Google Maps API Setup', 'go-deliver' ); ?>
		</a>
	</nav>

	<!-- ======================================================
	     Google Maps API Setup Tab
	     ====================================================== -->
	<div id="gd-tab-google-maps" class="gd-tab-panel<?php echo 'google-maps' === $active_tab ? ' is-active' : ''; ?>">

		<div class="gd-section">
			<h2><?php esc_html_e( 'How to Set Up the Google Maps API', 'go-deliver' ); ?></h2>

			<p><?php esc_html_e( 'Go Deliver uses the Google Maps Places API to provide address autocomplete on the job submission form and on admin job edit screens. Follow the steps below to obtain and configure your API key.', 'go-deliver' ); ?></p>

			<ol class="gd-docs-steps">

				<li>
					<h3><?php esc_html_e( 'Open the Google Cloud Console', 'go-deliver' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: hyperlink to Google Cloud Console */
							esc_html__( 'Go to %s and sign in with a Google account.', 'go-deliver' ),
							'<a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">console.cloud.google.com</a>'
						);
						?>
					</p>
				</li>

				<li>
					<h3><?php esc_html_e( 'Create or Select a Project', 'go-deliver' ); ?></h3>
					<p><?php esc_html_e( 'In the top navigation bar, click the project dropdown and either select an existing project or click "New Project" to create one specifically for this site.', 'go-deliver' ); ?></p>
				</li>

				<li>
					<h3><?php esc_html_e( 'Enable the Required APIs', 'go-deliver' ); ?></h3>
					<p><?php esc_html_e( 'Navigate to "APIs & Services" → "Library" and enable the following APIs:', 'go-deliver' ); ?></p>
					<ul class="gd-docs-list">
						<li><strong><?php esc_html_e( 'Maps JavaScript API', 'go-deliver' ); ?></strong> – <?php esc_html_e( 'renders interactive maps.', 'go-deliver' ); ?></li>
						<li><strong><?php esc_html_e( 'Places API', 'go-deliver' ); ?></strong> – <?php esc_html_e( 'powers the address autocomplete widget.', 'go-deliver' ); ?></li>
						<li><strong><?php esc_html_e( 'Geocoding API', 'go-deliver' ); ?></strong> – <?php esc_html_e( 'converts addresses into coordinates (used as a fallback).', 'go-deliver' ); ?></li>
					</ul>
					<p class="gd-docs-note">
						<span class="dashicons dashicons-info" aria-hidden="true"></span>
						<?php esc_html_e( 'Search for each API by name and click "Enable". You may need to enable billing on your project before activating these APIs. Google provides a monthly free credit that is sufficient for most small sites.', 'go-deliver' ); ?>
					</p>
				</li>

				<li>
					<h3><?php esc_html_e( 'Create an API Key', 'go-deliver' ); ?></h3>
					<p><?php esc_html_e( 'Go to "APIs & Services" → "Credentials" and click "Create Credentials" → "API key". Copy the key that is generated.', 'go-deliver' ); ?></p>
				</li>

				<li>
					<h3><?php esc_html_e( 'Restrict the API Key (Recommended)', 'go-deliver' ); ?></h3>
					<p><?php esc_html_e( 'Click the pencil icon next to your new key to edit it and apply the following restrictions:', 'go-deliver' ); ?></p>
					<ul class="gd-docs-list">
						<li>
							<strong><?php esc_html_e( 'Application restrictions', 'go-deliver' ); ?></strong> –
							<?php
							printf(
								/* translators: %s: example domain */
								esc_html__( 'Set to "HTTP referrers (websites)" and add your site domain, e.g. %s', 'go-deliver' ),
								'<code>' . esc_html( home_url( '/*' ) ) . '</code>'
							);
							?>
						</li>
						<li>
							<strong><?php esc_html_e( 'API restrictions', 'go-deliver' ); ?></strong> –
							<?php esc_html_e( 'Select "Restrict key" and choose the three APIs you enabled above.', 'go-deliver' ); ?>
						</li>
					</ul>
				</li>

				<li>
					<h3><?php esc_html_e( 'Add the Key to Go Deliver Settings', 'go-deliver' ); ?></h3>
					<p>
						<?php
						printf(
							/* translators: %s: hyperlink to Settings page */
							esc_html__( 'Open the %s page, paste the key into the "Google Maps API Key" field and click "Save General Settings".', 'go-deliver' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=go-deliver-settings' ) ) . '">' . esc_html__( 'Settings', 'go-deliver' ) . '</a>'
						);
						?>
					</p>
				</li>

				<li>
					<h3><?php esc_html_e( 'Verify It Is Working', 'go-deliver' ); ?></h3>
					<p><?php esc_html_e( 'Visit the front-end job submission form. When you start typing in an address field you should see Google Places suggestions appear in a dropdown. On the admin side, the same autocomplete will be active on any job edit screen.', 'go-deliver' ); ?></p>
					<?php if ( ! get_option( 'gd_google_maps_api_key', '' ) ) : ?>
						<div class="notice notice-warning inline" style="margin-top:10px;">
							<p>
								<strong><?php esc_html_e( 'No API key saved yet.', 'go-deliver' ); ?></strong>
								<?php
								printf(
									/* translators: %s: hyperlink to Settings page */
									esc_html__( 'Go to %s to add your key.', 'go-deliver' ),
									'<a href="' . esc_url( admin_url( 'admin.php?page=go-deliver-settings' ) ) . '">' . esc_html__( 'Settings', 'go-deliver' ) . '</a>'
								);
								?>
							</p>
						</div>
					<?php else : ?>
						<div class="notice notice-success inline" style="margin-top:10px;">
							<p><?php esc_html_e( 'An API key is saved. Address autocomplete should be active.', 'go-deliver' ); ?></p>
						</div>
					<?php endif; ?>
				</li>

			</ol>
		</div><!-- /.gd-section -->

	</div><!-- /#gd-tab-google-maps -->

</div><!-- /.gd-admin-wrap -->
