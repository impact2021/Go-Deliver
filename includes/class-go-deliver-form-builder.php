<?php
/**
 * Dynamic form builder for the Go Deliver job submission form.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Class Go_Deliver_Form_Builder
 */
class Go_Deliver_Form_Builder {

/** Allowed field types. */
const ALLOWED_TYPES = array( 'text', 'textarea', 'select', 'checkbox', 'number', 'radio' );

// =========================================================================
// Registration helpers.
// =========================================================================

/**
 * Register AJAX hooks.
 */
public function register_hooks() {
add_action( 'wp_ajax_gd_save_form_fields', array( $this, 'ajax_save_fields' ) );
add_action( 'wp_ajax_gd_get_form_fields',  array( $this, 'ajax_get_fields' ) );
}

// =========================================================================
// Field configuration.
// =========================================================================

/**
 * Return the current set of configured form fields.
 *
 * @return array Array of field definition arrays.
 */
public function get_fields() {
$stored = get_option( 'gd_form_fields', array() );

if ( is_string( $stored ) ) {
$decoded = json_decode( $stored, true );
return is_array( $decoded ) ? $decoded : array();
}

return is_array( $stored ) ? $stored : array();
}

/**
 * Validate and persist form field definitions.
 *
 * @param array $fields Array of field definition arrays.
 * @return true|WP_Error
 */
public function save_fields( $fields ) {
if ( ! is_array( $fields ) ) {
return new WP_Error( 'invalid_fields', __( 'Fields must be an array.', 'go-deliver' ) );
}

$sanitized = array();

foreach ( $fields as $index => $field ) {
if ( ! is_array( $field ) ) {
continue;
}

if ( empty( $field['key'] ) || empty( $field['label'] ) || empty( $field['type'] ) ) {
return new WP_Error(
'invalid_field',
sprintf(
/* translators: %d: field index */
__( 'Field at index %d must have key, label, and type.', 'go-deliver' ),
(int) $index
)
);
}

if ( ! in_array( $field['type'], self::ALLOWED_TYPES, true ) ) {
return new WP_Error(
'invalid_field_type',
sprintf(
/* translators: %s: invalid type */
__( 'Field type "%s" is not supported.', 'go-deliver' ),
sanitize_text_field( $field['type'] )
)
);
}

$sanitized_field = array(
'key'      => sanitize_key( $field['key'] ),
'label'    => sanitize_text_field( $field['label'] ),
'type'     => sanitize_text_field( $field['type'] ),
'required' => ! empty( $field['required'] ),
);

if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
$sanitized_field['options'] = array_map( 'sanitize_text_field', $field['options'] );
}

if ( ! empty( $field['conditional_on'] ) ) {
$sanitized_field['conditional_on']    = sanitize_key( $field['conditional_on'] );
$sanitized_field['conditional_value'] = sanitize_text_field( $field['conditional_value'] ?? '' );
}

if ( ! empty( $field['placeholder'] ) ) {
$sanitized_field['placeholder'] = sanitize_text_field( $field['placeholder'] );
}

$sanitized[] = $sanitized_field;
}

update_option( 'gd_form_fields', wp_json_encode( $sanitized ) );

return true;
}

/**
 * Return hardcoded default fields for the job submission form.
 *
 * @return array
 */
public function get_default_fields() {
return array(
// ── Level 1: top-level job type ──────────────────────────────────────
array(
'key'      => 'item_type',
'label'    => __( 'Job Type', 'go-deliver' ),
'type'     => 'select',
'required' => true,
'options'  => array(
'TradeMe Purchase Pickup',
'Item',
'Move',
'Vehicle',
'Boat',
'Piano',
'Pet',
'Junk',
'Other',
),
),
// ── Level 2: Item sub-type ────────────────────────────────────────────
array(
'key'               => 'item_subtype',
'label'             => __( 'Item Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Furniture', 'Item' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Item',
),
// ── Level 3: Furniture detail ─────────────────────────────────────────
array(
'key'               => 'furniture_subtype',
'label'             => __( 'Furniture Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Sofa', 'Table', 'Bed', 'Bookcase', 'Chest', 'Drawer' ),
'conditional_on'    => 'item_subtype',
'conditional_value' => 'Furniture',
),
// ── Level 3: Loose / packed item detail ───────────────────────────────
array(
'key'               => 'item_detail_subtype',
'label'             => __( 'Item Detail', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Packed Item', 'Electronics', 'Bicycle', 'Box' ),
'conditional_on'    => 'item_subtype',
'conditional_value' => 'Item',
),
// ── Level 2: Move sub-type ────────────────────────────────────────────
array(
'key'               => 'move_subtype',
'label'             => __( 'Move Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Home', 'Office', 'Storage' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Move',
),
// ── Level 2: Vehicle sub-type ─────────────────────────────────────────
array(
'key'               => 'vehicle_subtype',
'label'             => __( 'Vehicle Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Car', 'Motorcycle', 'Other Vehicle' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Vehicle',
),
// ── Level 3: Car detail ───────────────────────────────────────────────
array(
'key'               => 'car_subtype',
'label'             => __( 'Car Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Sedan', 'Minivan', '4x4', 'Pickup', 'Sports Car', 'Coupe' ),
'conditional_on'    => 'vehicle_subtype',
'conditional_value' => 'Car',
),
// ── Level 3: Motorcycle detail ────────────────────────────────────────
array(
'key'               => 'motorcycle_subtype',
'label'             => __( 'Motorcycle Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Chopper', 'Superbike', 'Moped', 'Scooter' ),
'conditional_on'    => 'vehicle_subtype',
'conditional_value' => 'Motorcycle',
),
// ── Level 3: Other vehicle detail ─────────────────────────────────────
array(
'key'               => 'other_vehicle_subtype',
'label'             => __( 'Vehicle Detail', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Truck', 'Bus', 'RV', 'Tractor' ),
'conditional_on'    => 'vehicle_subtype',
'conditional_value' => 'Other Vehicle',
),
// ── Level 2: Boat sub-type ────────────────────────────────────────────
array(
'key'               => 'boat_subtype',
'label'             => __( 'Boat Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Powerboat', 'Sailboat', 'Houseboat', 'Jet Ski', 'Watercraft' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Boat',
),
// ── Level 2: Piano sub-type ───────────────────────────────────────────
array(
'key'               => 'piano_subtype',
'label'             => __( 'Piano Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Grand Piano', 'Upright Piano', 'Digital Piano' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Piano',
),
// ── Level 2: Pet sub-type ─────────────────────────────────────────────
array(
'key'               => 'pet_subtype',
'label'             => __( 'Pet Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Cat', 'Dog', 'Bird', 'Horse' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Pet',
),
// ── Level 2: Junk sub-type ────────────────────────────────────────────
array(
'key'               => 'junk_subtype',
'label'             => __( 'Junk Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Junk Clearance', 'Rubbish Removal' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Junk',
),
// ── Level 2: Other sub-type ───────────────────────────────────────────
array(
'key'               => 'other_subtype',
'label'             => __( 'Other Type', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Heavy', 'Farm Equipment', 'Construction Material' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'Other',
),
);
}

// =========================================================================
// Flat job type dropdown.
// =========================================================================

/**
 * Return the flat list of job types for the single-selection dropdown.
 *
 * Each entry has 'value' (submitted) and 'label' (display text with description).
 *
 * @return array
 */
public function get_flat_job_types() {
return array(
array(
'value' => 'trademe_pickup',
'label' => __( 'TradeMe Purchase Pickup — purchase pickup from trademe.co.nz', 'go-deliver' ),
),
array(
'value' => 'item',
'label' => __( 'Item — furniture, electronics, antique, box, vehicle part etc.', 'go-deliver' ),
),
array(
'value' => 'furniture',
'label' => __( 'Furniture — sofa, table, bed, bookcase, chest, drawer etc.', 'go-deliver' ),
),
array(
'value' => 'item_packed',
'label' => __( 'Item — packed item, electronics, bicycle, box etc.', 'go-deliver' ),
),
array(
'value' => 'move',
'label' => __( 'Move — home move, office move, storage move', 'go-deliver' ),
),
array(
'value' => 'vehicle',
'label' => __( 'Vehicle — car, motorcycle, RV, truck and other motor vehicles', 'go-deliver' ),
),
array(
'value' => 'car',
'label' => __( 'Car — sedan, minivan, 4x4, pickup, sports car, coupe etc.', 'go-deliver' ),
),
array(
'value' => 'motorcycle',
'label' => __( 'Motorcycle — chopper, superbike, moped, scooter etc.', 'go-deliver' ),
),
array(
'value' => 'other_vehicle',
'label' => __( 'Other Vehicle — truck, bus, RV, tractor and other motor vehicles', 'go-deliver' ),
),
array(
'value' => 'boat',
'label' => __( 'Boat — powerboat, sailboat, houseboat, jet ski, watercraft etc.', 'go-deliver' ),
),
array(
'value' => 'piano',
'label' => __( 'Piano — grand piano, upright piano, digital piano etc.', 'go-deliver' ),
),
array(
'value' => 'pet',
'label' => __( 'Pet — cat, dog, bird, horse etc.', 'go-deliver' ),
),
array(
'value' => 'junk',
'label' => __( 'Junk — junk clearance, rubbish removal etc.', 'go-deliver' ),
),
array(
'value' => 'other',
'label' => __( 'Other — heavy, farm equipment, construction material etc.', 'go-deliver' ),
),
);
}

/**
 * Echo HTML for the flat job type <select> element.
 *
 * @param string $selected Currently selected value (for pre-population).
 */
public function render_flat_job_type_dropdown( $selected = '' ) {
$types = $this->get_flat_job_types();
echo '<select id="gd_job_type" name="job_type" required>';
echo '<option value="">' . esc_html__( '-- Select job type --', 'go-deliver' ) . '</option>';
foreach ( $types as $type ) {
$sel = selected( $selected, $type['value'], false );
echo '<option value="' . esc_attr( $type['value'] ) . '"' . $sel . '>' . esc_html( $type['label'] ) . '</option>';
}
echo '</select>';
}

// =========================================================================
// Rendering.
// =========================================================================

/**
 * Echo HTML markup for all configured form fields.
 *
 * @param array $values Pre-populated field values keyed by field key.
 */
public function render_form_fields( $values = array() ) {
$fields = $this->get_fields();

if ( empty( $fields ) ) {
$fields = $this->get_default_fields();
}

foreach ( $fields as $field ) {
$key      = esc_attr( $field['key'] );
$label    = esc_html( $field['label'] );
$type     = $field['type'];
$required = ! empty( $field['required'] );
$value    = isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : '';

$data_attrs = '';
if ( ! empty( $field['conditional_on'] ) ) {
$data_attrs = sprintf(
' data-conditional-on="%s" data-conditional-value="%s"',
esc_attr( $field['conditional_on'] ),
esc_attr( $field['conditional_value'] ?? '' )
);
}

$req_attr  = $required ? ' required' : '';
$req_label = $required ? ' <span class="gd-required" aria-hidden="true">*</span>' : '';

echo '<div class="gd-form-field gd-field-' . esc_attr( $type ) . '"' . $data_attrs . '>';
echo '<label for="gd_field_' . $key . '">' . $label . $req_label . '</label>';

switch ( $type ) {
case 'select':
echo '<select id="gd_field_' . $key . '" name="form_data[' . $key . ']"' . $req_attr . '>';
echo '<option value="">' . esc_html__( '-- Select --', 'go-deliver' ) . '</option>';
if ( ! empty( $field['options'] ) ) {
foreach ( $field['options'] as $option ) {
$selected = selected( $value, $option, false );
echo '<option value="' . esc_attr( $option ) . '"' . $selected . '>' . esc_html( $option ) . '</option>';
}
}
echo '</select>';
break;

case 'textarea':
echo '<textarea id="gd_field_' . $key . '" name="form_data[' . $key . ']"' . $req_attr . '>' . esc_textarea( $value ) . '</textarea>';
break;

case 'checkbox':
$checked = checked( $value, '1', false );
echo '<input type="checkbox" id="gd_field_' . $key . '" name="form_data[' . $key . ']" value="1"' . $checked . $req_attr . '>';
break;

case 'number':
$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
echo '<input type="number" id="gd_field_' . $key . '" name="form_data[' . $key . ']" value="' . esc_attr( $value ) . '"' . $placeholder . $req_attr . '>';
break;

case 'radio':
if ( ! empty( $field['options'] ) ) {
foreach ( $field['options'] as $option ) {
$checked = checked( $value, $option, false );
echo '<label class="gd-radio-label">';
echo '<input type="radio" name="form_data[' . $key . ']" value="' . esc_attr( $option ) . '"' . $checked . $req_attr . '>';
echo ' ' . esc_html( $option );
echo '</label>';
}
}
break;

case 'text':
default:
$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';
echo '<input type="text" id="gd_field_' . $key . '" name="form_data[' . $key . ']" value="' . esc_attr( $value ) . '"' . $placeholder . $req_attr . '>';
break;
}

echo '</div>';
}
}

// =========================================================================
// Validation.
// =========================================================================

/**
 * Validate and extract form field data from submitted POST data.
 *
 * @param array $post_data Raw POST data (e.g. $_POST).
 * @return array {
 *   @type bool   $valid
 *   @type array  $errors  Validation error messages.
 *   @type array  $data    Sanitized field values.
 * }
 */
public function validate_and_extract( $post_data ) {
$fields  = $this->get_fields();
$errors  = array();
$data    = array();

if ( empty( $fields ) ) {
$fields = $this->get_default_fields();
}

$submitted = isset( $post_data['form_data'] ) && is_array( $post_data['form_data'] )
? $post_data['form_data']
: array();

foreach ( $fields as $field ) {
$key      = $field['key'];
$required = ! empty( $field['required'] );
$type     = $field['type'];
$raw      = $submitted[ $key ] ?? '';

// Skip conditional fields whose condition is not met.
if ( ! empty( $field['conditional_on'] ) ) {
$parent_value = $submitted[ $field['conditional_on'] ] ?? '';
if ( $parent_value !== ( $field['conditional_value'] ?? '' ) ) {
continue;
}
}

if ( $required && '' === trim( (string) $raw ) ) {
$errors[] = sprintf(
/* translators: %s: field label */
__( '%s is required.', 'go-deliver' ),
$field['label']
);
continue;
}

switch ( $type ) {
case 'number':
$data[ $key ] = is_numeric( $raw ) ? (float) $raw : '';
break;
case 'checkbox':
$data[ $key ] = ( '1' === $raw || true === $raw ) ? '1' : '0';
break;
case 'textarea':
$data[ $key ] = sanitize_textarea_field( wp_unslash( $raw ) );
break;
default:
$data[ $key ] = sanitize_text_field( wp_unslash( $raw ) );
break;
}
}

return array(
'valid'  => empty( $errors ),
'errors' => $errors,
'data'   => $data,
);
}

// =========================================================================
// AJAX handlers.
// =========================================================================

/**
 * AJAX: save form field configuration (admin only).
 */
public function ajax_save_fields() {
check_ajax_referer( 'gd_form_builder', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

$raw_fields = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array();

// Accept either a JSON string or a pre-decoded array.
if ( is_string( $raw_fields ) ) {
$raw_fields = json_decode( $raw_fields, true );
}

if ( ! is_array( $raw_fields ) ) {
wp_send_json_error( array( 'message' => __( 'Invalid fields data.', 'go-deliver' ) ) );
}

$result = $this->save_fields( $raw_fields );

if ( is_wp_error( $result ) ) {
wp_send_json_error( array( 'message' => $result->get_error_message() ) );
}

wp_send_json_success( array( 'message' => __( 'Form fields saved.', 'go-deliver' ) ) );
}

/**
 * AJAX: retrieve form field configuration (admin only).
 */
public function ajax_get_fields() {
check_ajax_referer( 'gd_form_builder', 'nonce' );

if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
}

wp_send_json_success( $this->get_fields() );
}
}
