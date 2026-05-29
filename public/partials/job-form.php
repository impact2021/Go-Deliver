<?php
/**
 * Job submission form template.
 *
 * Shortcode: [gd_job_form]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_builder = new Go_Deliver_Form_Builder();
?>
<div class="gd-wrap">
<?php if ( is_user_logged_in() && ! current_user_can( 'gd_submit_jobs' ) && ! current_user_can( 'manage_options' ) ) : ?>
	<div class="gd-alert gd-alert--warning">
		<span class="gd-alert__icon">⚠️</span>
		<div class="gd-alert__body">
			<?php esc_html_e( 'Your account does not have permission to submit jobs. Please contact support.', 'go-deliver' ); ?>
		</div>
	</div>
<?php else : ?>
<div class="gd-job-form" id="gd-job-form-wrap">

	<form id="gd-job-form" method="post" enctype="multipart/form-data" novalidate>
		<?php wp_nonce_field( 'gd_submit_job', 'gd_submit_job_nonce' ); ?>
		<input type="hidden" name="action" value="gd_submit_job">
		<input type="hidden" name="email_verification_token" id="gd_job_email_verification_token" value="">

		<!-- Wizard header: title + progress bar (shown for steps 2+) -->
		<div class="gd-job-form__header" style="display:none;">
			<h2 class="gd-job-form__title"><?php esc_html_e( 'Post a Job', 'go-deliver' ); ?></h2>
			<div class="gd-form-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
				<div class="gd-form-progress__fill"></div>
			</div>
		</div>

		<!-- ============================================================
		     Form steps (all inline; step 1 shown on load, steps 2-6 on Next)
		     ============================================================ -->
		<div class="gd-job-form__body">

			<div class="gd-form-section" data-step="1">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'What are you moving?', 'go-deliver' ); ?></h2>

				<div class="gd-field-group">
					<label for="gd_job_type">
						<?php esc_html_e( 'Item type', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<?php $form_builder->render_flat_job_type_dropdown(); ?>
				</div>

				<!-- Item: size sub-category -->
				<div class="gd-field-group gd-type-field" data-job-type-show="item" style="display:none;">
					<label for="gd_item_size">
						<?php esc_html_e( 'How big is the item?', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<select id="gd_item_size" name="form_data[item_size]">
						<option value=""><?php esc_html_e( '-- Select --', 'go-deliver' ); ?></option>
						<option value="very_large"><?php esc_html_e( 'Very large item (e.g. piano)', 'go-deliver' ); ?></option>
						<option value="large_furniture"><?php esc_html_e( 'Large item / furniture', 'go-deliver' ); ?></option>
						<option value="smaller_item"><?php esc_html_e( 'Smaller item (e.g. computer)', 'go-deliver' ); ?></option>
					</select>
				</div>

				<!-- Move: home or office -->
				<div class="gd-field-group gd-type-field" data-job-type-show="move" style="display:none;">
					<label for="gd_move_type">
						<?php esc_html_e( 'What kind of move?', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<select id="gd_move_type" name="form_data[move_type]">
						<option value=""><?php esc_html_e( '-- Select --', 'go-deliver' ); ?></option>
						<option value="home"><?php esc_html_e( 'Home move', 'go-deliver' ); ?></option>
						<option value="office"><?php esc_html_e( 'Office move', 'go-deliver' ); ?></option>
					</select>
				</div>

				<!-- Vehicle or boat: sub-type -->
				<div class="gd-field-group gd-type-field" data-job-type-show="vehicle_or_boat" style="display:none;">
					<label for="gd_vehicle_boat_type">
						<?php esc_html_e( 'What type of vehicle or boat?', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<select id="gd_vehicle_boat_type" name="form_data[vehicle_boat_type]">
						<option value=""><?php esc_html_e( '-- Select --', 'go-deliver' ); ?></option>
						<option value="car"><?php esc_html_e( 'Car', 'go-deliver' ); ?></option>
						<option value="motorbike"><?php esc_html_e( 'Motorbike', 'go-deliver' ); ?></option>
						<option value="boat"><?php esc_html_e( 'Boat', 'go-deliver' ); ?></option>
						<option value="caravan"><?php esc_html_e( 'Caravan', 'go-deliver' ); ?></option>
						<option value="other_vehicle"><?php esc_html_e( 'Other vehicle type', 'go-deliver' ); ?></option>
					</select>
				</div>

				<!-- Vehicle / boat: Make (shown for car / motorbike / boat / caravan) -->
				<div class="gd-field-group gd-subtype-field" data-subtype-show="car,motorbike,boat,caravan" style="display:none;">
					<label for="gd_vehicle_make">
						<?php esc_html_e( 'Make', 'go-deliver' ); ?>
					</label>
					<input
						type="text"
						id="gd_vehicle_make"
						name="form_data[vehicle_make]"
						placeholder="<?php esc_attr_e( 'e.g. Toyota', 'go-deliver' ); ?>"
						autocomplete="off"
					>
				</div>

				<!-- Vehicle / boat: Model (shown for car / motorbike / boat / caravan) -->
				<div class="gd-field-group gd-subtype-field" data-subtype-show="car,motorbike,boat,caravan" style="display:none;">
					<label for="gd_vehicle_model">
						<?php esc_html_e( 'Model', 'go-deliver' ); ?>
					</label>
					<input
						type="text"
						id="gd_vehicle_model"
						name="form_data[vehicle_model]"
						placeholder="<?php esc_attr_e( 'e.g. Corolla', 'go-deliver' ); ?>"
						autocomplete="off"
					>
				</div>

				<!-- Pet type -->
				<div class="gd-field-group gd-type-field" data-job-type-show="pet" style="display:none;">
					<label for="gd_pet_type"><?php esc_html_e( 'What kind of pet?', 'go-deliver' ); ?></label>
					<select id="gd_pet_type" name="form_data[pet_type]">
						<option value=""><?php esc_html_e( '-- Select --', 'go-deliver' ); ?></option>
						<option value="cat"><?php esc_html_e( 'Cat', 'go-deliver' ); ?></option>
						<option value="dog"><?php esc_html_e( 'Dog', 'go-deliver' ); ?></option>
						<option value="bird"><?php esc_html_e( 'Bird', 'go-deliver' ); ?></option>
						<option value="horse"><?php esc_html_e( 'Horse / Large animal', 'go-deliver' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'go-deliver' ); ?></option>
					</select>
				</div>

			</div><!-- /step 1 -->

		<!-- (steps 2–6 continue inside the same body below) -->

			<!-- ============================================================
			     Step 2: Collection Address
			     ============================================================ -->
			<div class="gd-form-section" data-step="2">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'Collection Address', 'go-deliver' ); ?></h2>

				<div class="gd-field-group gd-location-field">
					<label for="gd_pickup_suburb">
						<?php esc_html_e( 'Address', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gd_pickup_suburb"
						name="pickup_display"
						class="gd-suburb-input"
						placeholder="<?php esc_attr_e( 'e.g. 123 Queen Street, Auckland', 'go-deliver' ); ?>"
						required
						autocomplete="off"
					>
					<input type="hidden" name="pickup_suburb" class="gd-suburb-hidden-input">
					<input type="hidden" name="pickup_address" class="gd-address-input">
					<input type="hidden" name="pickup_lat" class="gd-lat-input">
					<input type="hidden" name="pickup_lng" class="gd-lng-input">
				</div>

				<div class="gd-field-group">
					<label for="gd_collection_floors">
						<?php esc_html_e( 'Floors / flights of stairs at collection', 'go-deliver' ); ?>
					</label>
					<select id="gd_collection_floors" name="form_data[collection_floors]">
						<option value="0"><?php esc_html_e( 'Ground floor / no stairs', 'go-deliver' ); ?></option>
						<option value="1"><?php esc_html_e( '1 floor / flight of stairs', 'go-deliver' ); ?></option>
						<option value="2"><?php esc_html_e( '2 floors / flights of stairs', 'go-deliver' ); ?></option>
						<option value="3"><?php esc_html_e( '3 or more floors / flights', 'go-deliver' ); ?></option>
					</select>
				</div>

				<div class="gd-field-group">
					<label><?php esc_html_e( 'How many people might be needed to load?', 'go-deliver' ); ?></label>
					<div class="gd-radio-group">
						<label class="gd-radio-label">
							<input type="radio" name="form_data[collection_helpers]" value="self" checked>
							<?php esc_html_e( 'I\'ll load it myself', 'go-deliver' ); ?>
						</label>
						<label class="gd-radio-label">
							<input type="radio" name="form_data[collection_helpers]" value="1">
							<?php esc_html_e( 'Need 1 person to help', 'go-deliver' ); ?>
						</label>
						<label class="gd-radio-label">
							<input type="radio" name="form_data[collection_helpers]" value="2plus">
							<?php esc_html_e( 'Need 2+ people to help', 'go-deliver' ); ?>
						</label>
					</div>
				</div>

			</div><!-- /step 2 -->

			<!-- ============================================================
			     Step 3: Delivery Address
			     ============================================================ -->
			<div class="gd-form-section" data-step="3">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'Delivery Address', 'go-deliver' ); ?></h2>

				<div class="gd-field-group gd-location-field">
					<label for="gd_dropoff_suburb">
						<?php esc_html_e( 'Address', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gd_dropoff_suburb"
						name="dropoff_display"
						class="gd-suburb-input"
						placeholder="<?php esc_attr_e( 'e.g. 45 High Street, Christchurch', 'go-deliver' ); ?>"
						required
						autocomplete="off"
					>
					<input type="hidden" name="dropoff_suburb" class="gd-suburb-hidden-input">
					<input type="hidden" name="dropoff_address" class="gd-address-input">
					<input type="hidden" name="dropoff_lat" class="gd-lat-input">
					<input type="hidden" name="dropoff_lng" class="gd-lng-input">
				</div>

				<div class="gd-field-group">
					<label for="gd_dropoff_floors">
						<?php esc_html_e( 'Floors / flights of stairs at delivery', 'go-deliver' ); ?>
					</label>
					<select id="gd_dropoff_floors" name="form_data[dropoff_floors]">
						<option value="0"><?php esc_html_e( 'Ground floor / no stairs', 'go-deliver' ); ?></option>
						<option value="1"><?php esc_html_e( '1 floor / flight of stairs', 'go-deliver' ); ?></option>
						<option value="2"><?php esc_html_e( '2 floors / flights of stairs', 'go-deliver' ); ?></option>
						<option value="3"><?php esc_html_e( '3 or more floors / flights', 'go-deliver' ); ?></option>
					</select>
				</div>

				<div class="gd-field-group">
					<label><?php esc_html_e( 'How many people might be needed to unload?', 'go-deliver' ); ?></label>
					<div class="gd-radio-group">
						<label class="gd-radio-label">
							<input type="radio" name="form_data[dropoff_helpers]" value="self" checked>
							<?php esc_html_e( 'I\'ll unload it myself', 'go-deliver' ); ?>
						</label>
						<label class="gd-radio-label">
							<input type="radio" name="form_data[dropoff_helpers]" value="1">
							<?php esc_html_e( 'Need 1 person to help', 'go-deliver' ); ?>
						</label>
						<label class="gd-radio-label">
							<input type="radio" name="form_data[dropoff_helpers]" value="2plus">
							<?php esc_html_e( 'Need 2+ people to help', 'go-deliver' ); ?>
						</label>
					</div>
				</div>

			</div><!-- /step 3 -->

			<!-- ============================================================
			     Step 4: When?
			     ============================================================ -->
			<div class="gd-form-section" data-step="4">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'When do you need it?', 'go-deliver' ); ?></h2>

				<div class="gd-field-group">
					<label for="gd_date_requested">
						<?php esc_html_e( 'Preferred date', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="date"
						id="gd_date_requested"
						name="date_requested"
						required
						min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>"
					>
				</div>

				<div class="gd-field-group">
					<label class="gd-checkbox-label">
						<input type="checkbox" name="form_data[date_flexible]" value="1">
						<?php esc_html_e( 'My date is flexible', 'go-deliver' ); ?>
					</label>
					<span class="gd-field-hint">
						<?php esc_html_e( 'Check this if you can move on a different date for a better price.', 'go-deliver' ); ?>
					</span>
				</div>

			</div><!-- /step 4 -->

			<!-- ============================================================
			     Step 5: Photos & Notes
			     ============================================================ -->
			<div class="gd-form-section" data-step="5">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'Photos &amp; Extra Information', 'go-deliver' ); ?></h2>

				<!-- Listing Title -->
				<div class="gd-field-group">
					<label for="gd_listing_title">
						<?php esc_html_e( 'Listing title', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gd_listing_title"
						name="listing_title"
						maxlength="80"
						placeholder="<?php esc_attr_e( 'e.g. 2-seater sofa from Auckland CBD to Ponsonby', 'go-deliver' ); ?>"
						required
						autocomplete="off"
					>
					<span class="gd-field-hint"><?php esc_html_e( 'A short title shown to movers on the listings page. Max 80 characters. Do not include phone numbers, addresses, or email addresses.', 'go-deliver' ); ?></span>
				</div>

				<!-- Photo Upload -->
				<div class="gd-field-group">
					<label><?php esc_html_e( 'Photos (optional)', 'go-deliver' ); ?></label>
					<div class="gd-upload-area" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Upload photos', 'go-deliver' ); ?>">
						<input
							type="file"
							name="job_photos[]"
							accept="image/*"
							multiple
							id="gd_job_photos"
						>
						<div class="gd-upload-area__icon">📷</div>
						<p class="gd-upload-area__text">
							<?php esc_html_e( 'Drag photos here or ', 'go-deliver' ); ?>
							<strong><?php esc_html_e( 'click to browse', 'go-deliver' ); ?></strong>
						</p>
						<p class="gd-upload-area__hint"><?php esc_html_e( 'JPEG, PNG up to 10 MB each. Multiple allowed.', 'go-deliver' ); ?></p>
					</div>
					<div class="gd-upload-preview" id="gd-photo-preview"></div>
				</div>

				<!-- Any more information -->
				<div class="gd-field-group">
					<label for="gd_inventory"><?php esc_html_e( 'Any more information?', 'go-deliver' ); ?></label>
					<textarea
						id="gd_inventory"
						name="inventory"
						rows="5"
						placeholder="<?php esc_attr_e( 'Anything else the mover should know — dimensions, fragile items, parking details, access notes…', 'go-deliver' ); ?>"
					></textarea>
					<span class="gd-field-hint"><?php esc_html_e( 'The more detail you provide, the better the quotes you\'ll receive. Do not include phone numbers, addresses, or email addresses.', 'go-deliver' ); ?></span>
				</div>

			</div><!-- /step 5 -->

			<!-- ============================================================
			     Step 6: Contact Details
			     ============================================================ -->
			<div class="gd-form-section" data-step="6">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'Your Details', 'go-deliver' ); ?></h2>

				<?php if ( ! is_user_logged_in() ) : ?>

					<p class="gd-text-muted" style="margin-bottom:16px;">
						<?php esc_html_e( 'We\'ll create a free account so you can track quotes and manage your job.', 'go-deliver' ); ?>
					</p>

					<div class="gd-field-row" style="display:flex;gap:16px;">
						<div class="gd-field-group" style="flex:1;">
							<label for="gd_account_first_name">
								<?php esc_html_e( 'First Name', 'go-deliver' ); ?>
								<span class="gd-required" aria-hidden="true">*</span>
							</label>
							<input
								type="text"
								id="gd_account_first_name"
								name="account_first_name"
								required
								autocomplete="given-name"
							>
						</div>
						<div class="gd-field-group" style="flex:1;">
							<label for="gd_account_last_name">
								<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
								<span class="gd-required" aria-hidden="true">*</span>
							</label>
							<input
								type="text"
								id="gd_account_last_name"
								name="account_last_name"
								required
								autocomplete="family-name"
							>
						</div>
					</div>

					<div class="gd-field-group">
						<label for="gd_account_email">
							<?php esc_html_e( 'Email Address', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<input
							type="email"
							id="gd_account_email"
							name="account_email"
							required
							autocomplete="email"
						>
					</div>

					<div class="gd-field-group">
						<label for="gd_contact_phone_guest"><?php esc_html_e( 'Phone Number', 'go-deliver' ); ?></label>
						<input
							type="tel"
							id="gd_contact_phone_guest"
							name="form_data[contact_phone]"
							placeholder="<?php esc_attr_e( 'e.g. 021 123 4567', 'go-deliver' ); ?>"
							autocomplete="tel"
						>
					</div>

					<div class="gd-field-group">
						<label for="gd_account_password">
							<?php esc_html_e( 'Password', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<input
							type="password"
							id="gd_account_password"
							name="account_password"
							required
							autocomplete="new-password"
						>
						<span class="gd-field-hint"><?php esc_html_e( 'Minimum 8 characters.', 'go-deliver' ); ?></span>
					</div>

					<div class="gd-field-group">
						<label for="gd_account_password_confirm">
							<?php esc_html_e( 'Confirm Password', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<input
							type="password"
							id="gd_account_password_confirm"
							name="account_password_confirm"
							required
							autocomplete="new-password"
						>
					</div>

					<p class="gd-text-muted" style="font-size:0.85em;margin-top:12px;">
						<?php esc_html_e( 'Already have an account?', 'go-deliver' ); ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
							<?php esc_html_e( 'Log in here', 'go-deliver' ); ?>
						</a>
					</p>

				<?php else : ?>

					<?php $current_user = wp_get_current_user(); ?>

					<div class="gd-field-group">
						<label for="gd_contact_name"><?php esc_html_e( 'Name', 'go-deliver' ); ?></label>
						<input
							type="text"
							id="gd_contact_name"
							name="form_data[contact_name]"
							value="<?php echo esc_attr( trim( $current_user->first_name . ' ' . $current_user->last_name ) ?: $current_user->display_name ); ?>"
							autocomplete="name"
						>
					</div>

					<div class="gd-field-group">
						<label for="gd_contact_email"><?php esc_html_e( 'Email Address', 'go-deliver' ); ?></label>
						<input
							type="email"
							id="gd_contact_email"
							name="form_data[contact_email]"
							value="<?php echo esc_attr( $current_user->user_email ); ?>"
							readonly
						>
					</div>

					<div class="gd-field-group">
						<label for="gd_contact_phone"><?php esc_html_e( 'Phone Number', 'go-deliver' ); ?></label>
						<input
							type="tel"
							id="gd_contact_phone"
							name="form_data[contact_phone]"
							value="<?php echo esc_attr( get_user_meta( $current_user->ID, 'gd_phone', true ) ); ?>"
							placeholder="<?php esc_attr_e( 'e.g. 021 123 4567', 'go-deliver' ); ?>"
							autocomplete="tel"
						>
					</div>

				<?php endif; ?>

				<?php if ( ! is_user_logged_in() ) : ?>
				<div class="gd-field-group" style="margin-top:16px;">
					<label class="gd-checkbox-label">
						<input type="checkbox" name="customer_terms_agreed" value="1" required id="gd-job-terms">
						<?php
						$customer_terms_id  = absint( get_option( 'gd_customer_terms_page_id', 0 ) );
						$customer_terms_url = $customer_terms_id ? get_permalink( $customer_terms_id ) : '';
						if ( $customer_terms_url ) {
							printf(
								/* translators: 1: opening anchor tag, 2: closing anchor tag */
								esc_html__( 'I agree to the %1$sTerms & Conditions%2$s.', 'go-deliver' ),
								'<a href="' . esc_url( $customer_terms_url ) . '" target="_blank" rel="noopener noreferrer">',
								'</a>'
							);
						} else {
							esc_html_e( 'I agree to the Terms & Conditions.', 'go-deliver' );
						}
						?>
					</label>
				</div>
				<?php endif; ?>

				<div class="gd-alert gd-alert--info" style="margin-top:20px;">
					<span class="gd-alert__icon">ℹ️</span>
					<div class="gd-alert__body">
						<?php esc_html_e( 'Once submitted, your job will be visible to approved movers in your area. You can cancel an open job from your dashboard.', 'go-deliver' ); ?>
					</div>
				</div>

			</div><!-- /step 6 -->

			<!-- ============================================================
			     Step 7: Email Verification
			     ============================================================ -->
			<div class="gd-form-section" data-step="7">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'Verify Your Email', 'go-deliver' ); ?></h2>
				<p class="gd-text-muted" style="margin-bottom:14px;">
					<?php esc_html_e( 'We’ve sent a 6-digit verification code to your email. Enter it below to unlock the final Submit Job button.', 'go-deliver' ); ?>
				</p>
				<div class="gd-alert gd-alert--info" style="margin-bottom:14px;">
					<span class="gd-alert__icon">ℹ️</span>
					<div class="gd-alert__body">
						<?php esc_html_e( 'If you don’t see it in your inbox, please check your spam or junk folder.', 'go-deliver' ); ?>
					</div>
				</div>

				<div class="gd-field-group">
					<label for="gd_job_email_verification_code">
						<?php esc_html_e( 'Verification Code', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gd_job_email_verification_code"
						inputmode="numeric"
						pattern="[0-9]{6}"
						maxlength="6"
						autocomplete="one-time-code"
						placeholder="<?php esc_attr_e( 'Enter 6-digit code', 'go-deliver' ); ?>"
					>
				</div>

				<div id="gd-job-email-verification-actions" style="display:flex;gap:10px;flex-wrap:wrap;">
					<button type="button" id="gd-job-verify-email-btn" class="gd-btn gd-btn--primary">
						<?php esc_html_e( 'Verify Code', 'go-deliver' ); ?>
					</button>
					<button type="button" id="gd-job-resend-email-code" class="gd-btn gd-btn--outline">
						<?php esc_html_e( 'Resend Code', 'go-deliver' ); ?>
					</button>
				</div>

				<div class="gd-alert gd-alert--success" id="gd-job-email-verified-notice" style="display:none;margin-top:14px;">
					<span class="gd-alert__icon">✅</span>
					<div class="gd-alert__body">
						<?php esc_html_e( 'Email verified. You can now submit your job.', 'go-deliver' ); ?>
					</div>
				</div>
			</div><!-- /step 7 -->

			</div><!-- /.gd-job-form__body -->

		<!-- Step 1 Continue button (shown for step 1) -->
		<div class="gd-job-form__step1-footer">
			<button type="button" id="gd-job-step1-continue" class="gd-btn gd-btn--cta gd-btn--block">
				<?php esc_html_e( 'Post a Job', 'go-deliver' ); ?>
			</button>
		</div>

		<!-- Navigation: Prev / Next / Submit (shown for steps 2+) -->
		<div class="gd-job-form__footer" style="display:none;">
			<button type="button" id="gd-job-prev" class="gd-btn gd-btn--outline" style="display:none;">
				<?php esc_html_e( '← Back', 'go-deliver' ); ?>
			</button>
			<div>
				<button type="button" id="gd-job-next" class="gd-btn gd-btn--primary">
					<?php esc_html_e( 'Next →', 'go-deliver' ); ?>
				</button>
				<button type="submit" id="gd-job-submit" class="gd-btn gd-btn--success" style="display:none;">
					&#x2713; <?php esc_html_e( 'Submit Job', 'go-deliver' ); ?>
				</button>
			</div>
		</div><!-- /.gd-job-form__footer -->

	</form>
</div><!-- /.gd-job-form -->
<?php endif; ?>
</div><!-- /.gd-wrap -->
