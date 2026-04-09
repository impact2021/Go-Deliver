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
?>
<div class="gd-wrap">
<?php if ( ! is_user_logged_in() ) : ?>
	<div class="gd-login-prompt">
		<div class="gd-login-prompt__icon">🔐</div>
		<h2 class="gd-login-prompt__title"><?php esc_html_e( 'Login Required', 'go-deliver' ); ?></h2>
		<p class="gd-login-prompt__text">
			<?php esc_html_e( 'Please log in or create an account to post a moving job.', 'go-deliver' ); ?>
		</p>
		<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="gd-btn gd-btn--primary">
			<?php esc_html_e( 'Log In', 'go-deliver' ); ?>
		</a>
		&nbsp;
		<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="gd-btn gd-btn--outline">
			<?php esc_html_e( 'Register', 'go-deliver' ); ?>
		</a>
	</div>
<?php elseif ( ! current_user_can( 'gd_submit_jobs' ) ) : ?>
	<div class="gd-alert gd-alert--warning">
		<span class="gd-alert__icon">⚠️</span>
		<div class="gd-alert__body">
			<?php esc_html_e( 'Your account does not have permission to submit jobs. Please contact support.', 'go-deliver' ); ?>
		</div>
	</div>
<?php else : ?>
	<div class="gd-job-form" id="gd-job-form-wrap">

		<!-- Header with progress steps -->
		<div class="gd-job-form__header">
			<h1 class="gd-job-form__title"><?php esc_html_e( 'Post a Moving Job', 'go-deliver' ); ?></h1>
			<nav class="gd-steps" aria-label="<?php esc_attr_e( 'Form steps', 'go-deliver' ); ?>">
				<div class="gd-step gd-step--active" data-step="1">
					<span class="gd-step__number">1</span>
					<span class="gd-step__label"><?php esc_html_e( 'Job Details', 'go-deliver' ); ?></span>
				</div>
				<div class="gd-step" data-step="2">
					<span class="gd-step__number">2</span>
					<span class="gd-step__label"><?php esc_html_e( 'Items & Access', 'go-deliver' ); ?></span>
				</div>
				<div class="gd-step" data-step="3">
					<span class="gd-step__number">3</span>
					<span class="gd-step__label"><?php esc_html_e( 'Review', 'go-deliver' ); ?></span>
				</div>
			</nav>
		</div>

		<form id="gd-job-form" method="post" enctype="multipart/form-data" novalidate>
			<?php wp_nonce_field( 'gd_submit_job', 'gd_submit_job_nonce' ); ?>
			<input type="hidden" name="action" value="gd_submit_job">

			<!-- =========================================================
			     Step 1: Basic Info + Location
			     ========================================================= -->
			<div class="gd-job-form__body">
				<section class="gd-form-section gd-form-section--active" data-step="1">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Job Type & Locations', 'go-deliver' ); ?></h2>

					<!-- Dynamic form fields from the admin form builder -->
					<?php
					$form_builder = new Go_Deliver_Form_Builder();
					$form_builder->render_form_fields();
					?>

					<!-- Pickup Location -->
					<div class="gd-field-group gd-location-field">
						<label for="gd_pickup_suburb">
							<?php esc_html_e( 'Pickup Suburb / Address', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div style="display:flex;gap:8px;align-items:flex-start;">
							<input
								type="text"
								id="gd_pickup_suburb"
								name="pickup_suburb"
								class="gd-suburb-input"
								placeholder="<?php esc_attr_e( 'e.g. Sydney CBD NSW', 'go-deliver' ); ?>"
								required
								autocomplete="off"
							>
							<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-geocode-btn" style="white-space:nowrap;">
								<?php esc_html_e( 'Verify', 'go-deliver' ); ?>
							</button>
						</div>
						<span class="gd-geocode-status gd-field-hint"></span>
						<input type="hidden" name="pickup_lat" class="gd-lat-input">
						<input type="hidden" name="pickup_lng" class="gd-lng-input">
					</div>

					<!-- Dropoff Location -->
					<div class="gd-field-group gd-location-field">
						<label for="gd_dropoff_suburb">
							<?php esc_html_e( 'Dropoff Suburb / Address', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div style="display:flex;gap:8px;align-items:flex-start;">
							<input
								type="text"
								id="gd_dropoff_suburb"
								name="dropoff_suburb"
								class="gd-suburb-input"
								placeholder="<?php esc_attr_e( 'e.g. Parramatta NSW', 'go-deliver' ); ?>"
								required
								autocomplete="off"
							>
							<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-geocode-btn" style="white-space:nowrap;">
								<?php esc_html_e( 'Verify', 'go-deliver' ); ?>
							</button>
						</div>
						<span class="gd-geocode-status gd-field-hint"></span>
						<input type="hidden" name="dropoff_lat" class="gd-lat-input">
						<input type="hidden" name="dropoff_lng" class="gd-lng-input">
					</div>

					<!-- Date Requested -->
					<div class="gd-field-group">
						<label for="gd_date_requested">
							<?php esc_html_e( 'Preferred Moving Date', 'go-deliver' ); ?>
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

					<!-- Special Instructions -->
					<div class="gd-field-group">
						<label for="gd_special_instructions">
							<?php esc_html_e( 'Special Instructions', 'go-deliver' ); ?>
						</label>
						<textarea
							id="gd_special_instructions"
							name="special_instructions"
							rows="4"
							placeholder="<?php esc_attr_e( 'Any additional details the mover should know…', 'go-deliver' ); ?>"
						></textarea>
					</div>
				</section>

				<!-- ==========================================================
				     Step 2: Items & Access
				     ========================================================== -->
				<section class="gd-form-section" data-step="2">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Items & Access', 'go-deliver' ); ?></h2>

					<!-- Labour -->
					<div class="gd-field-group">
						<label><?php esc_html_e( 'Labour Required', 'go-deliver' ); ?></label>
						<div class="gd-checkbox-group">
							<label class="gd-checkbox-label">
								<input type="checkbox" name="labour_pickup" value="1">
								<?php esc_html_e( 'Labour at Pickup', 'go-deliver' ); ?>
							</label>
							<label class="gd-checkbox-label">
								<input type="checkbox" name="labour_dropoff" value="1">
								<?php esc_html_e( 'Labour at Dropoff', 'go-deliver' ); ?>
							</label>
						</div>
					</div>

					<!-- Inventory Description -->
					<div class="gd-field-group">
						<label for="gd_inventory">
							<?php esc_html_e( 'Inventory / Items Description', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<textarea
							id="gd_inventory"
							name="inventory"
							rows="5"
							required
							placeholder="<?php esc_attr_e( 'List your items, e.g. 3-seater sofa, queen bed, fridge, 20 boxes…', 'go-deliver' ); ?>"
						></textarea>
						<span class="gd-field-hint"><?php esc_html_e( 'The more detail you provide, the better the quotes you\'ll receive.', 'go-deliver' ); ?></span>
					</div>

					<!-- Access Notes -->
					<div class="gd-field-group">
						<label for="gd_access_notes">
							<?php esc_html_e( 'Access Notes', 'go-deliver' ); ?>
						</label>
						<textarea
							id="gd_access_notes"
							name="access_notes"
							rows="3"
							placeholder="<?php esc_attr_e( 'e.g. Elevator available, parking on street, narrow driveway…', 'go-deliver' ); ?>"
						></textarea>
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
				</section>

				<!-- ==========================================================
				     Step 3: Review & Submit
				     ========================================================== -->
				<section class="gd-form-section" data-step="3">
					<h2 class="gd-form-section__title"><?php esc_html_e( 'Review Your Job', 'go-deliver' ); ?></h2>
					<p class="gd-text-muted" style="margin-bottom:16px;">
						<?php esc_html_e( 'Please review the details below before submitting.', 'go-deliver' ); ?>
					</p>
					<div class="gd-review-summary" id="gd-review-summary">
						<!-- Populated by JavaScript -->
					</div>

					<div class="gd-alert gd-alert--info" style="margin-top:20px;">
						<span class="gd-alert__icon">ℹ️</span>
						<div class="gd-alert__body">
							<?php esc_html_e( 'Once submitted, your job will be visible to approved movers in your area. You can cancel an open job from your dashboard.', 'go-deliver' ); ?>
						</div>
					</div>
				</section>
			</div><!-- /.gd-job-form__body -->

			<!-- Footer navigation -->
			<div class="gd-job-form__footer">
				<button type="button" id="gd-job-prev" class="gd-btn gd-btn--secondary" style="display:none;">
					← <?php esc_html_e( 'Previous', 'go-deliver' ); ?>
				</button>

				<div style="margin-left:auto;display:flex;gap:10px;align-items:center;">
					<button type="button" id="gd-job-next" class="gd-btn gd-btn--primary">
						<?php esc_html_e( 'Next Step', 'go-deliver' ); ?> →
					</button>
					<button type="submit" id="gd-job-submit" class="gd-btn gd-btn--success" style="display:none;">
						✓ <?php esc_html_e( 'Submit Job', 'go-deliver' ); ?>
					</button>
				</div>
			</div>

		</form>
	</div><!-- /.gd-job-form -->
<?php endif; ?>
</div><!-- /.gd-wrap -->
