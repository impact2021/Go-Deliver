<?php
/**
 * Admin meta box for the gd_job post type.
 *
 * Variables expected from caller:
 *   $post – WP_Post object for the current job.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_nonce_field( 'gd_save_job_meta', 'gd_job_meta_nonce' );

// Fetch existing meta values.
$job_type             = get_post_meta( $post->ID, 'gd_job_type', true );
$status               = get_post_meta( $post->ID, 'gd_job_status', true );
$customer_id          = (int) get_post_meta( $post->ID, 'gd_customer_id', true );
$date_requested       = get_post_meta( $post->ID, 'gd_date_requested', true );
$labour_pickup        = (bool) get_post_meta( $post->ID, 'gd_labour_pickup', true );
$labour_dropoff       = (bool) get_post_meta( $post->ID, 'gd_labour_dropoff', true );
$inventory            = get_post_meta( $post->ID, 'gd_inventory', true );
$access_notes         = get_post_meta( $post->ID, 'gd_access_notes', true );
$special_instructions = get_post_meta( $post->ID, 'gd_special_instructions', true );

$pickup_raw  = get_post_meta( $post->ID, 'gd_pickup_location', true );
$dropoff_raw = get_post_meta( $post->ID, 'gd_dropoff_location', true );

$pickup  = $pickup_raw  ? (array) json_decode( $pickup_raw, true )  : array();
$dropoff = $dropoff_raw ? (array) json_decode( $dropoff_raw, true ) : array();

$pickup_suburb  = $pickup['suburb']  ?? '';
$pickup_address = $pickup['address'] ?? '';
$pickup_lat     = $pickup['lat']     ?? '';
$pickup_lng     = $pickup['lng']     ?? '';

$dropoff_suburb  = $dropoff['suburb']  ?? '';
$dropoff_address = $dropoff['address'] ?? '';
$dropoff_lat     = $dropoff['lat']     ?? '';
$dropoff_lng     = $dropoff['lng']     ?? '';

$valid_statuses = Go_Deliver_Jobs::VALID_STATUSES;

// Build customer dropdown (gd_customer + administrator roles who can submit jobs).
$customer_users = get_users(
	array(
		'role__in' => array( 'gd_customer', 'gd_mover', 'administrator' ),
		'orderby'  => 'display_name',
		'order'    => 'ASC',
		'number'   => 500,
	)
);

$form_builder = new Go_Deliver_Form_Builder();
$job_types    = $form_builder->get_flat_job_types();
?>
<style>
.gd-mb-section { margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid #e0e0e0; }
.gd-mb-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
.gd-mb-section h3 { margin: 0 0 12px; font-size: 13px; font-weight: 600; color: #3c434a; text-transform: uppercase; letter-spacing: .03em; }
.gd-mb-row { display: flex; flex-wrap: wrap; gap: 16px; }
.gd-mb-field { display: flex; flex-direction: column; gap: 4px; flex: 1 1 200px; }
.gd-mb-field label { font-weight: 600; font-size: 12px; color: #3c434a; }
.gd-mb-field input[type="text"],
.gd-mb-field input[type="date"],
.gd-mb-field input[type="number"],
.gd-mb-field select,
.gd-mb-field textarea { width: 100%; }
.gd-mb-field textarea { min-height: 80px; }
.gd-mb-field .description { font-size: 11px; color: #646970; margin-top: 2px; }
.gd-mb-checkbox-group { display: flex; gap: 20px; flex-wrap: wrap; }
.gd-mb-checkbox-group label { display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; }
</style>

<!-- ── Job Details ─────────────────────────────────────────────────────── -->
<div class="gd-mb-section">
	<h3><?php esc_html_e( 'Job Details', 'go-deliver' ); ?></h3>
	<div class="gd-mb-row">

		<div class="gd-mb-field">
			<label for="gd_admin_job_type"><?php esc_html_e( 'Job Type', 'go-deliver' ); ?> <span style="color:#d63638;">*</span></label>
			<select id="gd_admin_job_type" name="gd_job_type" required>
				<option value=""><?php esc_html_e( '— Select —', 'go-deliver' ); ?></option>
				<?php foreach ( $job_types as $jt ) : ?>
					<option value="<?php echo esc_attr( $jt['value'] ); ?>"<?php selected( $job_type, $jt['value'] ); ?>>
						<?php echo esc_html( $jt['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="gd-mb-field">
			<label for="gd_admin_customer"><?php esc_html_e( 'Customer', 'go-deliver' ); ?></label>
			<select id="gd_admin_customer" name="gd_customer_id">
				<option value=""><?php esc_html_e( '— Select user —', 'go-deliver' ); ?></option>
				<?php foreach ( $customer_users as $u ) : ?>
					<option value="<?php echo esc_attr( $u->ID ); ?>"<?php selected( $customer_id, $u->ID ); ?>>
						<?php echo esc_html( $u->display_name . ' (' . $u->user_email . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="gd-mb-field">
			<label for="gd_admin_date_requested"><?php esc_html_e( 'Preferred Moving Date', 'go-deliver' ); ?></label>
			<input
				type="date"
				id="gd_admin_date_requested"
				name="gd_date_requested"
				value="<?php echo esc_attr( $date_requested ); ?>"
			>
		</div>

		<div class="gd-mb-field">
			<label for="gd_admin_job_status"><?php esc_html_e( 'Status', 'go-deliver' ); ?></label>
			<select id="gd_admin_job_status" name="gd_job_status">
				<?php foreach ( $valid_statuses as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $status, $s ); ?>>
						<?php echo esc_html( ucfirst( $s ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

	</div>
</div>

<!-- ── Pickup Location ─────────────────────────────────────────────────── -->
<div class="gd-mb-section">
	<h3><?php esc_html_e( 'Pickup Location', 'go-deliver' ); ?></h3>
	<div class="gd-mb-row gd-admin-location-field">

		<div class="gd-mb-field">
			<label for="gd_admin_pickup_suburb"><?php esc_html_e( 'Suburb', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd_admin_pickup_suburb"
				name="gd_pickup_suburb"
				class="gd-admin-suburb-input"
				value="<?php echo esc_attr( $pickup_suburb ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. Auckland CBD', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-mb-field">
			<label for="gd_admin_pickup_address"><?php esc_html_e( 'Full Address', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd_admin_pickup_address"
				name="gd_pickup_address"
				class="gd-admin-address-input"
				value="<?php echo esc_attr( $pickup_address ); ?>"
				placeholder="<?php esc_attr_e( 'Street address', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-mb-field" style="flex: 0 1 120px;">
			<label for="gd_admin_pickup_lat"><?php esc_html_e( 'Latitude', 'go-deliver' ); ?></label>
			<input
				type="number"
				id="gd_admin_pickup_lat"
				name="gd_pickup_lat"
				class="gd-admin-lat-input"
				value="<?php echo esc_attr( $pickup_lat ); ?>"
				step="any"
				placeholder="0.000000"
			>
			<span class="description"><?php esc_html_e( 'Auto-filled from address', 'go-deliver' ); ?></span>
		</div>

		<div class="gd-mb-field" style="flex: 0 1 120px;">
			<label for="gd_admin_pickup_lng"><?php esc_html_e( 'Longitude', 'go-deliver' ); ?></label>
			<input
				type="number"
				id="gd_admin_pickup_lng"
				name="gd_pickup_lng"
				class="gd-admin-lng-input"
				value="<?php echo esc_attr( $pickup_lng ); ?>"
				step="any"
				placeholder="0.000000"
			>
			<span class="description"><?php esc_html_e( 'Auto-filled from address', 'go-deliver' ); ?></span>
		</div>

	</div>
</div>

<!-- ── Dropoff Location ────────────────────────────────────────────────── -->
<div class="gd-mb-section">
	<h3><?php esc_html_e( 'Dropoff Location', 'go-deliver' ); ?></h3>
	<div class="gd-mb-row gd-admin-location-field">

		<div class="gd-mb-field">
			<label for="gd_admin_dropoff_suburb"><?php esc_html_e( 'Suburb', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd_admin_dropoff_suburb"
				name="gd_dropoff_suburb"
				class="gd-admin-suburb-input"
				value="<?php echo esc_attr( $dropoff_suburb ); ?>"
				placeholder="<?php esc_attr_e( 'e.g. Christchurch', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-mb-field">
			<label for="gd_admin_dropoff_address"><?php esc_html_e( 'Full Address', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd_admin_dropoff_address"
				name="gd_dropoff_address"
				class="gd-admin-address-input"
				value="<?php echo esc_attr( $dropoff_address ); ?>"
				placeholder="<?php esc_attr_e( 'Street address', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-mb-field" style="flex: 0 1 120px;">
			<label for="gd_admin_dropoff_lat"><?php esc_html_e( 'Latitude', 'go-deliver' ); ?></label>
			<input
				type="number"
				id="gd_admin_dropoff_lat"
				name="gd_dropoff_lat"
				class="gd-admin-lat-input"
				value="<?php echo esc_attr( $dropoff_lat ); ?>"
				step="any"
				placeholder="0.000000"
			>
			<span class="description"><?php esc_html_e( 'Auto-filled from address', 'go-deliver' ); ?></span>
		</div>

		<div class="gd-mb-field" style="flex: 0 1 120px;">
			<label for="gd_admin_dropoff_lng"><?php esc_html_e( 'Longitude', 'go-deliver' ); ?></label>
			<input
				type="number"
				id="gd_admin_dropoff_lng"
				name="gd_dropoff_lng"
				class="gd-admin-lng-input"
				value="<?php echo esc_attr( $dropoff_lng ); ?>"
				step="any"
				placeholder="0.000000"
			>
			<span class="description"><?php esc_html_e( 'Auto-filled from address', 'go-deliver' ); ?></span>
		</div>

	</div>
</div>

<!-- ── Items & Notes ───────────────────────────────────────────────────── -->
<div class="gd-mb-section">
	<h3><?php esc_html_e( 'Items &amp; Notes', 'go-deliver' ); ?></h3>

	<div class="gd-mb-row" style="margin-bottom: 12px;">
		<div class="gd-mb-field">
			<label for="gd_admin_inventory"><?php esc_html_e( 'Inventory / Items Description', 'go-deliver' ); ?></label>
			<textarea
				id="gd_admin_inventory"
				name="gd_inventory"
				rows="4"
				placeholder="<?php esc_attr_e( 'List items, e.g. 3-seater sofa, queen bed, fridge, 20 boxes…', 'go-deliver' ); ?>"
			><?php echo esc_textarea( $inventory ); ?></textarea>
		</div>
	</div>

	<div class="gd-mb-row" style="margin-bottom: 12px;">
		<div class="gd-mb-field">
			<label><?php esc_html_e( 'Labour Required', 'go-deliver' ); ?></label>
			<div class="gd-mb-checkbox-group">
				<label>
					<input type="checkbox" name="gd_labour_pickup" value="1"<?php checked( $labour_pickup ); ?>>
					<?php esc_html_e( 'Labour at Pickup', 'go-deliver' ); ?>
				</label>
				<label>
					<input type="checkbox" name="gd_labour_dropoff" value="1"<?php checked( $labour_dropoff ); ?>>
					<?php esc_html_e( 'Labour at Dropoff', 'go-deliver' ); ?>
				</label>
			</div>
		</div>
	</div>

	<div class="gd-mb-row" style="margin-bottom: 12px;">
		<div class="gd-mb-field">
			<label for="gd_admin_access_notes"><?php esc_html_e( 'Access Notes', 'go-deliver' ); ?></label>
			<textarea
				id="gd_admin_access_notes"
				name="gd_access_notes"
				rows="3"
				placeholder="<?php esc_attr_e( 'e.g. Elevator available, parking on street…', 'go-deliver' ); ?>"
			><?php echo esc_textarea( $access_notes ); ?></textarea>
		</div>
	</div>

	<div class="gd-mb-row">
		<div class="gd-mb-field">
			<label for="gd_admin_special_instructions"><?php esc_html_e( 'Special Instructions', 'go-deliver' ); ?></label>
			<textarea
				id="gd_admin_special_instructions"
				name="gd_special_instructions"
				rows="3"
				placeholder="<?php esc_attr_e( 'Any additional details the mover should know…', 'go-deliver' ); ?>"
			><?php echo esc_textarea( $special_instructions ); ?></textarea>
		</div>
	</div>

</div>
