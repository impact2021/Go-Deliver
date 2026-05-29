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
				<div class="gd-progress-connector"></div>
				<div class="gd-progress-step" data-step="5">
					<div class="gd-progress-step__dot">5</div>
					<span><?php esc_html_e( 'Verify Email', 'go-deliver' ); ?></span>
				</div>
			</div>
		</div><!-- /.gd-registration-form__header -->

		<form id="gd-mover-registration-form" method="post" enctype="multipart/form-data" novalidate>
			<?php wp_nonce_field( 'gd_mover_registration', 'nonce' ); ?>
			<input type="hidden" name="action" value="gd_register_mover">
			<input type="hidden" name="email_verification_token" id="gd_reg_email_verification_token" value="">

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
							placeholder="<?php esc_attr_e( 'e.g. 021 123 4567', 'go-deliver' ); ?>"
							pattern="^(\+?64|0)[2-9][\d\s\-]{6,11}$"
						>
						<span class="gd-field-hint"><?php esc_html_e( 'NZ mobile or landline, e.g. 021 123 4567 or 09 123 4567.', 'go-deliver' ); ?></span>
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
							<?php esc_html_e( 'Your Address', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<input
							type="text"
							id="gd-reg-base-suburb"
							name="base_suburb"
							class="gd-suburb-input"
							required
							placeholder=""
							autocomplete="off"
						>
						<input type="hidden" name="base_address" class="gd-address-input">
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
							<?php foreach ( array( 5, 10, 20, 50, 100, 200, 500 ) as $km ) : ?>
								<option value="<?php echo esc_attr( $km ); ?>">
									<?php printf( esc_html__( '%d km', 'go-deliver' ), $km ); ?>
								</option>
							<?php endforeach; ?>
							<option value="9999"><?php esc_html_e( 'All of NZ', 'go-deliver' ); ?></option>
						</select>
						<span class="gd-field-hint"><?php esc_html_e( 'Maximum distance from your base you\'ll travel for jobs.', 'go-deliver' ); ?></span>
					</div>

					<div class="gd-field-group">
						<label><?php esc_html_e( 'Job Types You Accept', 'go-deliver' ); ?> <span class="gd-required">*</span></label>
						<div class="gd-checkbox-group">
							<label class="gd-checkbox-label gd-checkbox-label--select-all">
								<input type="checkbox" id="gd-reg-select-all-jobs">
								<?php esc_html_e( 'Select all', 'go-deliver' ); ?>
							</label>
							<?php
							$job_type_options = array(
								'trademe_pickup' => __( 'TradeMe Purchase Pickup', 'go-deliver' ),
								'item'           => __( 'Item', 'go-deliver' ),
								'furniture'      => __( 'Furniture', 'go-deliver' ),
								'item_packed'    => __( 'Packed Item', 'go-deliver' ),
								'move'           => __( 'Move', 'go-deliver' ),
								'vehicle'        => __( 'Vehicle', 'go-deliver' ),
								'car'            => __( 'Car', 'go-deliver' ),
								'motorcycle'     => __( 'Motorcycle', 'go-deliver' ),
								'other_vehicle'  => __( 'Other Vehicle', 'go-deliver' ),
								'boat'           => __( 'Boat', 'go-deliver' ),
								'piano'          => __( 'Piano', 'go-deliver' ),
								'pet'            => __( 'Pet', 'go-deliver' ),
								'junk'           => __( 'Junk', 'go-deliver' ),
								'other'          => __( 'Other', 'go-deliver' ),
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

					<!-- Driver's Licence – Front -->
					<div class="gd-doc-upload">
						<span class="gd-doc-upload__label">
							<?php esc_html_e( "Driver's Licence – Front", 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</span>
						<div class="gd-upload-area">
							<input
								type="file"
								name="drivers_licence_front"
								id="gd-doc-licence-front"
								accept="image/*,.pdf"
								required
							>
							<div class="gd-upload-area__icon">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="40" height="40" aria-hidden="true">
								<path fill-rule="evenodd" d="M4.5 3.75a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V6.75a3 3 0 0 0-3-3h-15Zm4.125 3a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Zm-3.873 8.703a4.126 4.126 0 0 1 7.746 0 .75.75 0 0 1-.272.83A5.597 5.597 0 0 1 8.625 17.5a5.597 5.597 0 0 1-3.255-1.017.75.75 0 0 1-.272-.83ZM15 8.25a.75.75 0 0 0 0 1.5h3.75a.75.75 0 0 0 0-1.5H15ZM14.25 12a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H15a.75.75 0 0 1-.75-.75Zm.75 2.25a.75.75 0 0 0 0 1.5H18a.75.75 0 0 0 0-1.5h-3Z" clip-rule="evenodd"/>
							</svg>
						</div>
							<p class="gd-upload-area__text">
								<?php esc_html_e( 'Drag here or ', 'go-deliver' ); ?>
								<strong><?php esc_html_e( 'click to upload', 'go-deliver' ); ?></strong>
							</p>
							<p class="gd-upload-area__hint"><?php esc_html_e( 'Front side of licence · JPG, PNG or PDF · Max 10 MB', 'go-deliver' ); ?></p>
						</div>
						<div class="gd-upload-preview"></div>
					</div>

					<!-- Driver's Licence – Back -->
					<div class="gd-doc-upload">
						<span class="gd-doc-upload__label">
							<?php esc_html_e( "Driver's Licence – Back", 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</span>
						<div class="gd-upload-area">
							<input
								type="file"
								name="drivers_licence_back"
								id="gd-doc-licence-back"
								accept="image/*,.pdf"
								required
							>
							<div class="gd-upload-area__icon">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="40" height="40" aria-hidden="true">
								<path fill-rule="evenodd" d="M4.5 3.75a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V6.75a3 3 0 0 0-3-3h-15Zm4.125 3a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Zm-3.873 8.703a4.126 4.126 0 0 1 7.746 0 .75.75 0 0 1-.272.83A5.597 5.597 0 0 1 8.625 17.5a5.597 5.597 0 0 1-3.255-1.017.75.75 0 0 1-.272-.83ZM15 8.25a.75.75 0 0 0 0 1.5h3.75a.75.75 0 0 0 0-1.5H15ZM14.25 12a.75.75 0 0 1 .75-.75h3.75a.75.75 0 0 1 0 1.5H15a.75.75 0 0 1-.75-.75Zm.75 2.25a.75.75 0 0 0 0 1.5H18a.75.75 0 0 0 0-1.5h-3Z" clip-rule="evenodd"/>
							</svg>
						</div>
							<p class="gd-upload-area__text">
								<?php esc_html_e( 'Drag here or ', 'go-deliver' ); ?>
								<strong><?php esc_html_e( 'click to upload', 'go-deliver' ); ?></strong>
							</p>
							<p class="gd-upload-area__hint"><?php esc_html_e( 'Back side of licence · JPG, PNG or PDF · Max 10 MB', 'go-deliver' ); ?></p>
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
							<div class="gd-upload-area__icon">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="40" height="40" aria-hidden="true">
								<path d="M3.375 4.5C2.339 4.5 1.5 5.34 1.5 6.375V13.5h12V6.375c0-1.036-.84-1.875-1.875-1.875h-8.25ZM13.5 15h-12v2.625c0 1.035.84 1.875 1.875 1.875h.375a3 3 0 1 1 6 0h3a.75.75 0 0 0 .75-.75V15Z"/>
								<path d="M8.25 19.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0ZM15.75 6.75a.75.75 0 0 0-.75.75v11.25c0 .087.015.17.042.248a3 3 0 0 1 5.958.464c.853-.175 1.522-.935 1.464-1.883a18.659 18.659 0 0 0-3.732-10.104 1.837 1.837 0 0 0-1.47-.725H15.75Z"/>
								<path d="M19.5 19.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0Z"/>
							</svg>
						</div>
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
							<div class="gd-upload-area__icon">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="40" height="40" aria-hidden="true">
								<path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 0 0-1.032 0 11.209 11.209 0 0 1-7.877 3.08.75.75 0 0 0-.722.515A12.74 12.74 0 0 0 2.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.749.749 0 0 0 .374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 0 0-.722-.516l-.143.001c-2.996 0-5.717-1.17-7.705-3.079Z" clip-rule="evenodd"/>
							</svg>
						</div>
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
							$mover_terms_id  = absint( get_option( 'gd_mover_terms_page_id', 0 ) );
							$mover_terms_url = $mover_terms_id ? get_permalink( $mover_terms_id ) : '';
							if ( $mover_terms_url ) {
								printf(
									/* translators: 1: opening anchor tag, 2: closing anchor tag */
									esc_html__( 'I agree to the %1$sTerms & Conditions%2$s and understand the platform fee structure.', 'go-deliver' ),
									'<a href="' . esc_url( $mover_terms_url ) . '" target="_blank" rel="noopener noreferrer">',
									'</a>'
								);
							} else {
								esc_html_e( 'I agree to the Terms & Conditions and understand the platform fee structure.', 'go-deliver' );
							}
							?>
						</label>
					</div>
				</div><!-- /step 4 -->

				<!-- ============================================================
				     Step 5: Email Verification
				     ============================================================ -->
				<div class="gd-reg-section" data-step="5">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Verify Your Email', 'go-deliver' ); ?></h2>
					<p class="gd-text-muted" style="margin-bottom:16px;">
						<?php esc_html_e( 'We’ve sent a 6-digit verification code to your email address. Enter the code below to unlock Submit Application.', 'go-deliver' ); ?>
					</p>

					<div class="gd-alert gd-alert--info" style="margin-bottom:16px;">
						<span class="gd-alert__icon">ℹ️</span>
						<div class="gd-alert__body">
							<?php esc_html_e( 'If you can’t find the email, please check your spam or junk folder.', 'go-deliver' ); ?>
						</div>
					</div>

					<div class="gd-field-group">
						<label for="gd_reg_email_verification_code">
							<?php esc_html_e( 'Verification Code', 'go-deliver' ); ?>
							<span class="gd-required">*</span>
						</label>
						<input
							type="text"
							id="gd_reg_email_verification_code"
							inputmode="numeric"
							pattern="[0-9]{6}"
							maxlength="6"
							autocomplete="one-time-code"
							placeholder="<?php esc_attr_e( 'Enter 6-digit code', 'go-deliver' ); ?>"
						>
					</div>

					<div id="gd-reg-email-verification-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
						<button type="button" id="gd-reg-verify-email-btn" class="gd-btn gd-btn--primary">
							<?php esc_html_e( 'Verify Code', 'go-deliver' ); ?>
						</button>
						<button type="button" id="gd-reg-resend-email-code" class="gd-btn gd-btn--outline">
							<?php esc_html_e( 'Resend Code', 'go-deliver' ); ?>
						</button>
					</div>

					<div class="gd-alert gd-alert--success" id="gd-reg-email-verified-notice" style="display:none;margin-top:14px;">
						<span class="gd-alert__icon">✅</span>
						<div class="gd-alert__body">
							<?php esc_html_e( 'Email verified. You can now submit your application.', 'go-deliver' ); ?>
						</div>
					</div>
				</div><!-- /step 5 -->

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
