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

	<div class="gd-job-form__header">
		<h1 class="gd-job-form__title"><?php esc_html_e( 'Post a Job', 'go-deliver' ); ?></h1>

		<!-- Step progress indicators -->
		<div class="gd-steps">
			<div class="gd-step" data-step="1">
				<span class="gd-step__number">1</span>
				<span class="gd-step__label"><?php esc_html_e( 'What?', 'go-deliver' ); ?></span>
			</div>
			<div class="gd-step" data-step="2">
				<span class="gd-step__number">2</span>
				<span class="gd-step__label"><?php esc_html_e( 'Collection', 'go-deliver' ); ?></span>
			</div>
			<div class="gd-step" data-step="3">
				<span class="gd-step__number">3</span>
				<span class="gd-step__label"><?php esc_html_e( 'Delivery', 'go-deliver' ); ?></span>
			</div>
			<div class="gd-step" data-step="4">
				<span class="gd-step__number">4</span>
				<span class="gd-step__label"><?php esc_html_e( 'When?', 'go-deliver' ); ?></span>
			</div>
			<div class="gd-step" data-step="5">
				<span class="gd-step__number">5</span>
				<span class="gd-step__label"><?php esc_html_e( 'Photos &amp; Notes', 'go-deliver' ); ?></span>
			</div>
			<div class="gd-step" data-step="6">
				<span class="gd-step__number">6</span>
				<span class="gd-step__label"><?php esc_html_e( 'Your Details', 'go-deliver' ); ?></span>
			</div>
		</div>
	</div><!-- /.gd-job-form__header -->

	<form id="gd-job-form" method="post" enctype="multipart/form-data" novalidate>
		<?php wp_nonce_field( 'gd_submit_job', 'gd_submit_job_nonce' ); ?>
		<input type="hidden" name="action" value="gd_submit_job">

		<div class="gd-job-form__body">

			<!-- ============================================================
			     Step 1: What do you need moved?
			     ============================================================ -->
			<div class="gd-form-section" data-step="1">
				<h2 class="gd-form-section__title"><?php esc_html_e( 'What do you need moved?', 'go-deliver' ); ?></h2>

				<!-- ── Category picker ──────────────────────────────────────── -->
				<div class="gd-field-group">
					<div class="gd-type-picker" id="gd-category-picker" role="group" aria-label="<?php esc_attr_e( 'Item category', 'go-deliver' ); ?>">
						<button type="button" class="gd-type-picker__btn"
							data-category="trademe"
							data-job-type="trademe_pickup"
							data-title-label="<?php esc_attr_e( 'What did you win on Trade Me?', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. Samsung 65&quot; TV', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">🛒</span>
							<?php esc_html_e( 'TradeMe', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn" data-category="item">
							<span class="gd-type-picker__icon" aria-hidden="true">📦</span>
							<?php esc_html_e( 'Item', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn"
							data-category="move"
							data-job-type="move"
							data-title-label="<?php esc_attr_e( 'Describe your move', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. 3 bedroom house contents', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">🏠</span>
							<?php esc_html_e( 'Move', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn" data-category="vehicle">
							<span class="gd-type-picker__icon" aria-hidden="true">🚗</span>
							<?php esc_html_e( 'Vehicle', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn"
							data-category="boat"
							data-job-type="boat"
							data-title-label="<?php esc_attr_e( 'Describe the boat', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. 5m fibreglass fishing boat', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">⛵</span>
							<?php esc_html_e( 'Boat', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn"
							data-category="pet"
							data-job-type="pet"
							data-title-label="<?php esc_attr_e( 'Describe your pet', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. Golden Retriever, large dog', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">🐾</span>
							<?php esc_html_e( 'Pet', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn"
							data-category="junk"
							data-job-type="junk"
							data-title-label="<?php esc_attr_e( 'Describe the junk', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. Mixed household rubbish, approx 1 trailer load', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">🗑️</span>
							<?php esc_html_e( 'Junk', 'go-deliver' ); ?>
						</button>
						<button type="button" class="gd-type-picker__btn"
							data-category="other"
							data-job-type="other"
							data-title-label="<?php esc_attr_e( 'Describe the job', 'go-deliver' ); ?>"
							data-title-placeholder="<?php esc_attr_e( 'e.g. Grand piano, Auckland to Wellington', 'go-deliver' ); ?>">
							<span class="gd-type-picker__icon" aria-hidden="true">✨</span>
							<?php esc_html_e( 'Other', 'go-deliver' ); ?>
						</button>
					</div>
					<span class="gd-field-error" id="gd-category-error" style="display:none;">
						<?php esc_html_e( 'Please select what you need moved.', 'go-deliver' ); ?>
					</span>
					<input type="hidden" name="job_type" id="gd_job_type">
				</div>

				<!-- ── Sub-section: TradeMe ──────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="trademe" style="display:none;">
					<div class="gd-field-group">
						<label for="gd_trademe_url">
							<?php esc_html_e( 'Trade Me Listing URL', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<input
							type="url"
							id="gd_trademe_url"
							name="form_data[trademe_url]"
							placeholder="https://www.trademe.co.nz/..."
							autocomplete="off"
							required
						>
						<span class="gd-field-hint">
							<?php esc_html_e( "Paste the link to the Trade Me listing you've won.", 'go-deliver' ); ?>
						</span>
					</div>
				</div>

				<!-- ── Sub-section: Item ────────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="item" style="display:none;">
					<div class="gd-field-group">
						<label>
							<?php esc_html_e( 'What kind of item?', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div class="gd-type-picker gd-type-picker--sm">
							<button type="button" class="gd-type-picker__btn"
								data-subtype="furniture"
								data-job-type="furniture"
								data-title-label="<?php esc_attr_e( 'Describe the furniture', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Double bed, Queen size', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🪑</span>
								<?php esc_html_e( 'Furniture', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="general"
								data-job-type="item"
								data-title-label="<?php esc_attr_e( 'Describe the item', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Large refrigerator, Samsung', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">📦</span>
								<?php esc_html_e( 'General Item', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="packed"
								data-job-type="item_packed"
								data-title-label="<?php esc_attr_e( 'Describe the packed items', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. 3 boxes of household goods', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">📫</span>
								<?php esc_html_e( 'Packed / Boxed', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="piano"
								data-job-type="piano"
								data-title-label="<?php esc_attr_e( 'Describe the piano', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Upright piano, Baldwin', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🎹</span>
								<?php esc_html_e( 'Piano', 'go-deliver' ); ?>
							</button>
						</div>
						<input type="hidden" name="form_data[item_subtype]" id="gd_item_subtype">
						<span class="gd-field-error" id="gd-item-subtype-error" style="display:none;">
							<?php esc_html_e( 'Please select an item type.', 'go-deliver' ); ?>
						</span>
					</div>
				</div>

				<!-- ── Sub-section: Move ────────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="move" style="display:none;">
					<div class="gd-field-group">
						<label>
							<?php esc_html_e( 'Type of move', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div class="gd-type-picker gd-type-picker--sm">
							<button type="button" class="gd-type-picker__btn"
								data-subtype="home"
								data-title-placeholder="<?php esc_attr_e( 'e.g. 3 bedroom house contents, Auckland CBD', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🏠</span>
								<?php esc_html_e( 'House', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="office"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Small office, 10 workstations', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🏢</span>
								<?php esc_html_e( 'Office', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="single"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Sofa, 3 seater + coffee table', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">📦</span>
								<?php esc_html_e( 'Single Item Move', 'go-deliver' ); ?>
							</button>
						</div>
						<input type="hidden" name="form_data[move_type]" id="gd_move_type">
						<span class="gd-field-error" id="gd-move-type-error" style="display:none;">
							<?php esc_html_e( 'Please select a move type.', 'go-deliver' ); ?>
						</span>
					</div>
				</div>

				<!-- ── Sub-section: Vehicle ──────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="vehicle" style="display:none;">
					<div class="gd-field-group">
						<label>
							<?php esc_html_e( 'Vehicle type', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div class="gd-type-picker gd-type-picker--sm">
							<button type="button" class="gd-type-picker__btn"
								data-subtype="car"
								data-job-type="car"
								data-title-label="<?php esc_attr_e( 'Describe the car', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Toyota Corolla 2018, white', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🚗</span>
								<?php esc_html_e( 'Car', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="motorcycle"
								data-job-type="motorcycle"
								data-title-label="<?php esc_attr_e( 'Describe the motorcycle', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Yamaha MT-07 2020', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🏍️</span>
								<?php esc_html_e( 'Motorcycle', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="vehicle"
								data-job-type="vehicle"
								data-title-label="<?php esc_attr_e( 'Describe the vehicle', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Box trailer, 6x4', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🚛</span>
								<?php esc_html_e( 'Large Vehicle', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="other_vehicle"
								data-job-type="other_vehicle"
								data-title-label="<?php esc_attr_e( 'Describe the vehicle', 'go-deliver' ); ?>"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Golf cart, ride-on mower', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🚜</span>
								<?php esc_html_e( 'Other', 'go-deliver' ); ?>
							</button>
						</div>
						<input type="hidden" name="form_data[vehicle_type]" id="gd_vehicle_type">
						<span class="gd-field-error" id="gd-vehicle-type-error" style="display:none;">
							<?php esc_html_e( 'Please select a vehicle type.', 'go-deliver' ); ?>
						</span>
					</div>
				</div>

				<!-- ── Sub-section: Pet ──────────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="pet" style="display:none;">
					<div class="gd-field-group">
						<label>
							<?php esc_html_e( 'What kind of pet?', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div class="gd-type-picker gd-type-picker--sm">
							<button type="button" class="gd-type-picker__btn"
								data-subtype="dog"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Golden Retriever, large dog', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🐕</span>
								<?php esc_html_e( 'Dog', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="cat"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Siamese cat, indoor', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🐈</span>
								<?php esc_html_e( 'Cat', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="other"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Rabbit, small animal', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🐾</span>
								<?php esc_html_e( 'Other', 'go-deliver' ); ?>
							</button>
						</div>
						<input type="hidden" name="form_data[pet_type]" id="gd_pet_type">
						<span class="gd-field-error" id="gd-pet-type-error" style="display:none;">
							<?php esc_html_e( 'Please select a pet type.', 'go-deliver' ); ?>
						</span>
					</div>
				</div>

				<!-- ── Sub-section: Junk ────────────────────────────────────── -->
				<div class="gd-type-section" data-category-section="junk" style="display:none;">
					<div class="gd-field-group">
						<label>
							<?php esc_html_e( 'Type of junk', 'go-deliver' ); ?>
							<span class="gd-required" aria-hidden="true">*</span>
						</label>
						<div class="gd-type-picker gd-type-picker--sm">
							<button type="button" class="gd-type-picker__btn"
								data-subtype="household"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Old furniture and appliances', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🏠</span>
								<?php esc_html_e( 'Household', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="green_waste"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Garden clippings and branches', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">🌿</span>
								<?php esc_html_e( 'Green Waste', 'go-deliver' ); ?>
							</button>
							<button type="button" class="gd-type-picker__btn"
								data-subtype="mixed"
								data-title-placeholder="<?php esc_attr_e( 'e.g. Mixed rubbish, approx 1 trailer load', 'go-deliver' ); ?>">
								<span class="gd-type-picker__icon" aria-hidden="true">♻️</span>
								<?php esc_html_e( 'Mixed', 'go-deliver' ); ?>
							</button>
						</div>
						<input type="hidden" name="form_data[junk_type]" id="gd_junk_type">
						<span class="gd-field-error" id="gd-junk-type-error" style="display:none;">
							<?php esc_html_e( 'Please select a junk type.', 'go-deliver' ); ?>
						</span>
					</div>
					<div class="gd-field-group">
						<label for="gd_junk_volume">
							<?php esc_html_e( 'Estimate volume', 'go-deliver' ); ?>
						</label>
						<select id="gd_junk_volume" name="form_data[junk_volume]">
							<option value=""><?php esc_html_e( '— Select approximate volume —', 'go-deliver' ); ?></option>
							<option value="small"><?php esc_html_e( 'Small — fits in a car boot', 'go-deliver' ); ?></option>
							<option value="medium"><?php esc_html_e( 'Medium — half trailer load', 'go-deliver' ); ?></option>
							<option value="large"><?php esc_html_e( 'Large — full trailer load', 'go-deliver' ); ?></option>
							<option value="extra_large"><?php esc_html_e( 'Extra large — multiple loads', 'go-deliver' ); ?></option>
						</select>
					</div>
				</div>

				<!-- ── Listing title (appears after category / sub-type selection) ── -->
				<div class="gd-field-group" id="gd-listing-title-group" style="display:none;">
					<label for="gd_listing_title" id="gd-listing-title-label">
						<?php esc_html_e( 'Describe what you need moved', 'go-deliver' ); ?>
						<span class="gd-required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gd_listing_title"
						name="listing_title"
						maxlength="80"
						placeholder="<?php esc_attr_e( 'e.g. Double bed, Queen size', 'go-deliver' ); ?>"
						autocomplete="off"
						required
					>
					<span class="gd-field-hint">
						<?php esc_html_e( 'Keep it brief. Do not include phone numbers or addresses — these are shared privately once a quote is accepted.', 'go-deliver' ); ?>
					</span>
				</div>

			</div><!-- /step 1 -->

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
					<span class="gd-field-hint"><?php esc_html_e( 'The more detail you provide, the better the quotes you\'ll receive.', 'go-deliver' ); ?></span>
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

		</div><!-- /.gd-job-form__body -->

		<!-- Form navigation -->
		<div class="gd-job-form__footer">
			<button type="button" id="gd-job-prev" class="gd-btn gd-btn--outline" style="display:none;">
				<?php esc_html_e( '← Back', 'go-deliver' ); ?>
			</button>
			<div>
				<button type="button" id="gd-job-next" class="gd-btn gd-btn--primary">
					<?php esc_html_e( 'Next →', 'go-deliver' ); ?>
				</button>
				<button type="submit" id="gd-job-submit" class="gd-btn gd-btn--success" style="display:none;">
					✓ <?php esc_html_e( 'Submit Job', 'go-deliver' ); ?>
				</button>
			</div>
		</div><!-- /.gd-job-form__footer -->

	</form>
</div><!-- /.gd-job-form -->
<?php endif; ?>
</div><!-- /.gd-wrap -->
