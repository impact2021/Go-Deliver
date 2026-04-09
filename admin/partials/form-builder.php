<?php
/**
 * Admin Form Builder partial.
 *
 * Displays the sortable list of form fields and the add-new-field form.
 * Variables expected from caller:
 *   $fields  – array of field definition arrays from Go_Deliver_Form_Builder::get_fields()
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $fields ) || ! is_array( $fields ) ) {
	$builder = new Go_Deliver_Form_Builder();
	$fields  = $builder->get_fields();
}

$allowed_types = array( 'text', 'textarea', 'select', 'checkbox', 'radio', 'number' );
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Job Form Builder', 'go-deliver' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Drag fields to reorder them. Changes take effect when you click "Save Order &amp; Fields".', 'go-deliver' ); ?>
	</p>

	<div class="gd-form-builder">

		<!-- Existing fields -->
		<ul id="gd-field-list" class="gd-field-list">
			<?php if ( empty( $fields ) ) : ?>
				<!-- empty: JS will show placeholder text via CSS :empty -->
			<?php else : ?>
				<?php foreach ( $fields as $field ) :
					$key       = isset( $field['key'] )   ? $field['key']   : '';
					$label     = isset( $field['label'] ) ? $field['label'] : '';
					$type      = isset( $field['type'] )  ? $field['type']  : 'text';
					$required  = ! empty( $field['required'] );
					$cond_on   = isset( $field['conditional_on'] )    ? $field['conditional_on']    : '';
					$cond_val  = isset( $field['conditional_value'] ) ? $field['conditional_value'] : '';
					$opts_arr  = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
					$opts_str  = implode( ', ', $opts_arr );
				?>
				<li class="gd-field-row"
					data-key="<?php echo esc_attr( $key ); ?>"
					data-label="<?php echo esc_attr( $label ); ?>"
					data-type="<?php echo esc_attr( $type ); ?>"
					data-required="<?php echo $required ? '1' : '0'; ?>"
					data-conditional_on="<?php echo esc_attr( $cond_on ); ?>"
					data-conditional_value="<?php echo esc_attr( $cond_val ); ?>"
					data-options="<?php echo esc_attr( $opts_str ); ?>"
				>
					<span class="gd-field-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'go-deliver' ); ?>"></span>

					<span class="gd-field-info">
						<span class="gd-field-label">
							<?php echo esc_html( $label ); ?>
							<?php if ( $required ) : ?>
								<span class="gd-required-badge"><?php esc_html_e( 'required', 'go-deliver' ); ?></span>
							<?php endif; ?>
						</span>
						<span class="gd-field-key"><?php echo esc_html( $key ); ?></span>
					</span>

					<span class="gd-field-meta">
						<?php if ( $cond_on ) : ?>
							<span class="gd-tag">
								<?php
								printf(
									/* translators: 1: field key, 2: expected value */
									esc_html__( 'if %1$s = %2$s', 'go-deliver' ),
									esc_html( $cond_on ),
									esc_html( $cond_val )
								);
								?>
							</span>
						<?php endif; ?>
						<span class="gd-field-type"><?php echo esc_html( $type ); ?></span>
					</span>

					<span class="gd-field-actions">
						<button type="button" class="button button-small gd-edit-field-btn">
							<?php esc_html_e( 'Edit', 'go-deliver' ); ?>
						</button>
						<button type="button" class="button button-small gd-delete-field-btn">
							<?php esc_html_e( 'Delete', 'go-deliver' ); ?>
						</button>
					</span>
				</li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul><!-- /#gd-field-list -->

		<p>
			<button type="button" id="gd-save-fields-btn" class="button button-primary">
				<?php esc_html_e( 'Save Order &amp; Fields', 'go-deliver' ); ?>
			</button>
		</p>

		<!-- Add new field form -->
		<div class="gd-add-field-form" id="gd-new-field-form">
			<h3><?php esc_html_e( 'Add New Field', 'go-deliver' ); ?></h3>

			<div class="gd-form-row">
				<div class="gd-form-col">
					<label for="gd_field_key"><?php esc_html_e( 'Field Key', 'go-deliver' ); ?></label>
					<input
						type="text"
						id="gd_field_key"
						name="gd_field_key"
						placeholder="<?php esc_attr_e( 'e.g. pickup_suburb', 'go-deliver' ); ?>"
					>
					<p class="description"><?php esc_html_e( 'Unique, lowercase, underscores only. Used as the data key.', 'go-deliver' ); ?></p>
				</div>
				<div class="gd-form-col">
					<label for="gd_field_label"><?php esc_html_e( 'Field Label', 'go-deliver' ); ?></label>
					<input
						type="text"
						id="gd_field_label"
						name="gd_field_label"
						placeholder="<?php esc_attr_e( 'e.g. Pickup Suburb', 'go-deliver' ); ?>"
					>
				</div>
				<div class="gd-form-col">
					<label for="gd_field_type"><?php esc_html_e( 'Field Type', 'go-deliver' ); ?></label>
					<select id="gd_field_type" name="gd_field_type">
						<option value=""><?php esc_html_e( '— Select type —', 'go-deliver' ); ?></option>
						<?php foreach ( $allowed_types as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div><!-- /.gd-form-row -->

			<div class="gd-form-row">
				<div class="gd-form-col">
					<label for="gd_field_conditional_on"><?php esc_html_e( 'Conditional On (field key)', 'go-deliver' ); ?></label>
					<input
						type="text"
						id="gd_field_conditional_on"
						name="gd_field_conditional_on"
						placeholder="<?php esc_attr_e( 'Leave blank for always visible', 'go-deliver' ); ?>"
					>
				</div>
				<div class="gd-form-col">
					<label for="gd_field_conditional_value"><?php esc_html_e( 'Conditional Value', 'go-deliver' ); ?></label>
					<input
						type="text"
						id="gd_field_conditional_value"
						name="gd_field_conditional_value"
						placeholder="<?php esc_attr_e( 'e.g. yes', 'go-deliver' ); ?>"
					>
				</div>
				<div class="gd-form-col gd-options-row" style="display:none;">
					<label for="gd_field_options"><?php esc_html_e( 'Options (comma-separated)', 'go-deliver' ); ?></label>
					<textarea
						id="gd_field_options"
						name="gd_field_options"
						placeholder="<?php esc_attr_e( 'Option A, Option B, Option C', 'go-deliver' ); ?>"
					></textarea>
					<p class="description"><?php esc_html_e( 'Required for select and radio field types.', 'go-deliver' ); ?></p>
				</div>
			</div><!-- /.gd-form-row -->

			<div class="gd-form-row">
				<div class="gd-form-col">
					<label>
						<input type="checkbox" name="gd_field_required" id="gd_field_required" value="1">
						<?php esc_html_e( 'Required field', 'go-deliver' ); ?>
					</label>
				</div>
			</div><!-- /.gd-form-row -->

			<button type="button" id="gd-add-field-btn" class="button button-secondary">
				<?php esc_html_e( '+ Add Field to List', 'go-deliver' ); ?>
			</button>
		</div><!-- /#gd-new-field-form -->

	</div><!-- /.gd-form-builder -->
</div><!-- /.gd-admin-wrap -->
