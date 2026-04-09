<?php
/**
 * Mover registration form template.
 *
 * Shortcode: [gd_mover_registration]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If already logged in as a mover, show a message.
if ( is_user_logged_in() ) {
	$current_user = wp_get_current_user();
	$is_mover     = in_array( 'gd_mover', (array) $current_user->roles, true )
	             || in_array( 'gd_mover_sub', (array) $current_user->roles, true );

	if ( $is_mover ) {
		echo '<div class="gd-wrap"><div class="gd-alert gd-alert--info">';
		echo '<span class="gd-alert__icon">ℹ️</span>';
		echo '<div class="gd-alert__body">';
		printf(
			/* translators: %s: dashboard URL */
			esc_html__( 'You are already registered as a mover. %s', 'go-deliver' ),
			'<a href="' . esc_url( get_permalink( get_option( 'gd_mover_dashboard_page_id' ) ) ) . '">' . esc_html__( 'Go to your dashboard →', 'go-deliver' ) . '</a>'
		);
		echo '</div></div></div>';
		return;
	}
}
?>
<div class="gd-wrap">

	<!-- Success message (hidden by default, shown after registration) -->
	<div id="gd-registration-success" class="gd-status-message" style="display:none;">
		<div class="gd-status-message__icon">✅</div>
		<h2 class="gd-status-message__title"><?php esc_html_e( 'Application Submitted!', 'go-deliver' ); ?></h2>
		<p class="gd-status-message__text">
			<?php esc_html_e( 'Thank you for registering as a mover. Your application is under review and you\'ll receive an email once approved, usually within 1–2 business days.', 'go-deliver' ); ?>
		</p>
	</div>

	<div class="gd-registration-form" id="gd-mover-registration-form-wrap">

		<div class="gd-registration-form__header">
			<div class="gd-registration-form__logo">🚚 <?php esc_html_e( 'Go Deliver', 'go-deliver' ); ?></div>
			<p class="gd-registration-form__subtitle">
				<?php esc_html_e( 'Register as a professional mover and start receiving job leads.', 'go-deliver' ); ?>
			</p>

			<!-- Progress Bar -->
			<div class="gd-progress-bar" style="margin-top:16px;">
				<div class="gd-progress-step gd-progress-step--active" data-step="1">
					<div class="gd-progress-step__dot">1</div>
					<span><?php esc_html_e( 'Account', 'go-deliver' ); ?></span>
				</div>
				<div class="gd-progress-connector"></div>
				<div class="gd-progress-step" data-step="2">
					<div class="gd-progress-step__dot">2</div>
					<span><?php esc_html_e( 'Location', 'go-deliver' ); ?></span>
				</div>
				<div class="gd-progress-connector"></div>
				<div class="gd-progress-step" data-step="3">
					<div class="gd-progress-step__dot">3</div>
					<span><?php esc_html_e( 'Documents', 'go-deliver' ); ?></span>
				</div>
				<div class="gd-progress-connector"></div>
				<div class="gd-progress-step" data-step="4">
					<div class="gd-progress-step__dot">4</div>
					<span><?php esc_html_e( 'Review', 'go-deliver' ); ?></span>
				</div>
			</div>
		</div><!-- /.gd-registration-form__header -->

		<form id="gd-mover-registration-form" method="post" enctype="multipart/form-data" novalidate>
			<?php wp_nonce_field( 'gd_mover_registration', 'nonce' ); ?>
			<input type="hidden" name="action" value="gd_register_mover">

			<div class="gd-registration-form__body">

				<!-- ============================================================
				     Step 1: Account Details
				     ============================================================ -->
				<div class="gd-reg-section" data-step="1">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Account Details', 'go-deliver' ); ?></h2>

					<div class="gd-field-row">
						<div class="gd-field-group">
							<label for="gd-reg-first-name">
								<?php esc_html_e( 'First Name', 'go-deliver' ); ?>
								<span class="gd-required">*</span>
							</label>
							<input
								type="text"
								id="gd-reg-first-name"
								name="first_name"
								required
								autocomplete="given-name"
							>
						</div>
						<div class="gd-field-group">
							<label for="gd-reg-last-name">
								<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
								<span class="gd-required">*</span>
							</label>
							<input
								type="text"
								id="gd-reg-last-name"
								name="last_name"
								required
								autocomplete="family-name"
							>
						</div>
					</div>

					<div class="gd-field-group">
						<label for="gd-reg-username">
							<?php esc_html_e( 'Username', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<input
							type="text"
							id="gd-reg-username"
							name="username"
							required
							autocomplete="username"
							pattern="[a-zA-Z0-9_\-]{3,60}"
						>
						<span class="gd-field-hint"><?php esc_html_e( '3–60 characters. Letters, numbers, hyphens and underscores only.', 'go-deliver' ); ?></span>
					</div>

					<div class="gd-field-group">
						<label for="gd-reg-email">
							<?php esc_html_e( 'Email Address', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<input
							type="email"
							id="gd-reg-email"
							name="email"
							required
							autocomplete="email"
						>
					</div>

					<div class="gd-field-group">
						<label for="gd-reg-phone">
							<?php esc_html_e( 'Phone Number', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<input
							type="tel"
							id="gd-reg-phone"
							name="phone"
							required
							autocomplete="tel"
							placeholder="<?php esc_attr_e( 'e.g. 0400 000 000', 'go-deliver' ); ?>"
						>
					</div>

					<div class="gd-field-row">
						<div class="gd-field-group">
							<label for="gd-reg-password">
								<?php esc_html_e( 'Password', 'go-deliver' ); ?>
								<span class="gd-required">*</span>
							</label>
							<input
								type="password"
								id="gd-reg-password"
								name="password"
								required
								autocomplete="new-password"
								minlength="8"
							>
							<span class="gd-field-hint"><?php esc_html_e( 'Minimum 8 characters.', 'go-deliver' ); ?></span>
						</div>
						<div class="gd-field-group">
							<label for="gd-reg-confirm-password">
								<?php esc_html_e( 'Confirm Password', 'go-deliver' ); ?>
								<span class="gd-required">*</span>
							</label>
							<input
								type="password"
								id="gd-reg-confirm-password"
								name="confirm_password"
								required
								autocomplete="new-password"
							>
						</div>
					</div>
				</div><!-- /step 1 -->

				<!-- ============================================================
				     Step 2: Location & Job Types
				     ============================================================ -->
				<div class="gd-reg-section" data-step="2">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Location & Service Area', 'go-deliver' ); ?></h2>

					<div class="gd-field-group gd-location-field">
						<label for="gd-reg-base-suburb">
							<?php esc_html_e( 'Base Suburb / City', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<div style="display:flex;gap:8px;align-items:flex-start;">
							<input
								type="text"
								id="gd-reg-base-suburb"
								name="base_suburb"
								class="gd-suburb-input"
								required
								placeholder="<?php esc_attr_e( 'e.g. Melbourne VIC', 'go-deliver' ); ?>"
								autocomplete="off"
							>
							<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-geocode-btn">
								<?php esc_html_e( 'Verify', 'go-deliver' ); ?>
							</button>
						</div>
						<span class="gd-geocode-status gd-field-hint"></span>
						<input type="hidden" name="base_lat" class="gd-lat-input">
						<input type="hidden" name="base_lng" class="gd-lng-input">
					</div>

					<div class="gd-field-group">
						<label for="gd-reg-radius">
							<?php esc_html_e( 'Service Radius', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<select id="gd-reg-radius" name="radius" required>
							<option value=""><?php esc_html_e( '-- Select radius --', 'go-deliver' ); ?></option>
							<?php foreach ( array( 5, 10, 20, 50, 100, 200 ) as $km ) : ?>
								<option value="<?php echo esc_attr( $km ); ?>">
									<?php printf( esc_html__( '%d km', 'go-deliver' ), $km ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="gd-field-hint"><?php esc_html_e( 'Maximum distance from your base you\'ll travel for jobs.', 'go-deliver' ); ?></span>
					</div>

					<div class="gd-field-group">
						<label><?php esc_html_e( 'Job Types You Accept', 'go-deliver' ); ?> <span class="gd-required">*</span></label>
						<div class="gd-checkbox-group">
							<?php
							$job_type_options = array(
								'house_move'     => __( 'House Move', 'go-deliver' ),
								'apartment_move' => __( 'Apartment Move', 'go-deliver' ),
								'office_move'    => __( 'Office Move', 'go-deliver' ),
								'single_items'   => __( 'Single Items', 'go-deliver' ),
								'piano'          => __( 'Piano', 'go-deliver' ),
								'interstate'     => __( 'Interstate', 'go-deliver' ),
							);
							foreach ( $job_type_options as $slug => $label ) :
							?>
								<label class="gd-checkbox-label">
									<input type="checkbox" name="job_types[]" value="<?php echo esc_attr( $slug ); ?>">
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div><!-- /step 2 -->

				<!-- ============================================================
				     Step 3: Documents
				     ============================================================ -->
				<div class="gd-reg-section" data-step="3">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Documents', 'go-deliver' ); ?></h2>
					<p class="gd-text-muted" style="margin-bottom:20px;">
						<?php esc_html_e( 'Please upload clear copies of the required documents. All documents are kept securely and reviewed by our team.', 'go-deliver' ); ?>
					</p>

					<!-- Driver's Licence -->
					<div class="gd-doc-upload">
						<span class="gd-doc-upload__label">
							<?php esc_html_e( "Driver's Licence", 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</span>
						<div class="gd-upload-area">
							<input
								type="file"
								name="drivers_licence"
								id="gd-doc-licence"
								accept="image/*,.pdf"
								required
							>
							<div class="gd-upload-area__icon">🪪</div>
							<p class="gd-upload-area__text">
								<?php esc_html_e( 'Drag here or ', 'go-deliver' ); ?>
								<strong><?php esc_html_e( 'click to upload', 'go-deliver' ); ?></strong>
							</p>
							<p class="gd-upload-area__hint"><?php esc_html_e( 'JPG, PNG or PDF · Max 10 MB', 'go-deliver' ); ?></p>
						</div>
						<div class="gd-upload-preview"></div>
					</div>

					<!-- Vehicle Registration -->
					<div class="gd-doc-upload">
						<span class="gd-doc-upload__label">
							<?php esc_html_e( 'Vehicle Registration', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</span>
						<div class="gd-upload-area">
							<input
								type="file"
								name="police_check"
								id="gd-doc-rego"
								accept="image/*,.pdf"
								required
							>
							<div class="gd-upload-area__icon">🚛</div>
							<p class="gd-upload-area__text">
								<?php esc_html_e( 'Drag here or ', 'go-deliver' ); ?>
								<strong><?php esc_html_e( 'click to upload', 'go-deliver' ); ?></strong>
							</p>
							<p class="gd-upload-area__hint"><?php esc_html_e( 'JPG, PNG or PDF · Max 10 MB', 'go-deliver' ); ?></p>
						</div>
						<div class="gd-upload-preview"></div>
					</div>

					<!-- Public Liability Insurance -->
					<div class="gd-doc-upload">
						<span class="gd-doc-upload__label">
							<?php esc_html_e( 'Public Liability Insurance', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</span>
						<div class="gd-upload-area">
							<input
								type="file"
								name="insurance"
								id="gd-doc-insurance"
								accept="image/*,.pdf"
								required
							>
							<div class="gd-upload-area__icon">📋</div>
							<p class="gd-upload-area__text">
								<?php esc_html_e( 'Drag here or ', 'go-deliver' ); ?>
								<strong><?php esc_html_e( 'click to upload', 'go-deliver' ); ?></strong>
							</p>
							<p class="gd-upload-area__hint"><?php esc_html_e( 'JPG, PNG or PDF · Max 10 MB', 'go-deliver' ); ?></p>
						</div>
						<div class="gd-upload-preview"></div>
					</div>
				</div><!-- /step 3 -->

				<!-- ============================================================
				     Step 4: Review & Submit
				     ============================================================ -->
				<div class="gd-reg-section" data-step="4">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Review & Submit', 'go-deliver' ); ?></h2>
					<p class="gd-text-muted" style="margin-bottom:16px;">
						<?php esc_html_e( 'Please review your information before submitting your application.', 'go-deliver' ); ?>
					</p>

					<div class="gd-alert gd-alert--info">
						<span class="gd-alert__icon">ℹ️</span>
						<div class="gd-alert__body">
							<?php esc_html_e( 'After submitting, your application will be reviewed by our team. You\'ll receive an approval email once your documents have been verified.', 'go-deliver' ); ?>
						</div>
					</div>

					<div style="margin-top:16px;">
						<label class="gd-checkbox-label">
							<input type="checkbox" name="terms_agreed" value="1" required id="gd-reg-terms">
							<?php
							printf(
								/* translators: 1: terms URL */
								esc_html__( 'I agree to the Terms & Conditions and understand the platform fee structure.', 'go-deliver' )
							);
							?>
						</label>
					</div>
				</div><!-- /step 4 -->

			</div><!-- /.gd-registration-form__body -->

			<!-- Footer navigation -->
			<div class="gd-registration-form__footer">
				<button type="button" id="gd-reg-prev" class="gd-btn gd-btn--secondary" style="display:none;">
					← <?php esc_html_e( 'Previous', 'go-deliver' ); ?>
				</button>
				<div style="margin-left:auto;display:flex;gap:10px;">
					<button type="button" id="gd-reg-next" class="gd-btn gd-btn--primary">
						<?php esc_html_e( 'Next Step', 'go-deliver' ); ?> →
					</button>
					<button type="submit" id="gd-reg-submit" class="gd-btn gd-btn--success" style="display:none;">
						✓ <?php esc_html_e( 'Submit Application', 'go-deliver' ); ?>
					</button>
				</div>
			</div>

		</form>
	</div><!-- /.gd-registration-form -->
</div><!-- /.gd-wrap -->
