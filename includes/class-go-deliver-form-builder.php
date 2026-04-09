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
array(
'key'      => 'item_type',
'label'    => __( 'Type of Move', 'go-deliver' ),
'type'     => 'select',
'required' => true,
'options'  => array( 'House', 'Apartment', 'Office', 'Single Items' ),
),
array(
'key'               => 'bedrooms',
'label'             => __( 'Number of Bedrooms', 'go-deliver' ),
'type'              => 'select',
'required'          => false,
'options'           => array( 'Studio', '1', '2', '3', '4', '5+' ),
'conditional_on'    => 'item_type',
'conditional_value' => 'House',
),
array(
'key'      => 'special_items',
'label'    => __( 'Special Items (piano, pool table, etc)', 'go-deliver' ),
'type'     => 'textarea',
'required' => false,
),
array(
'key'      => 'access_info',
'label'    => __( 'Access Information', 'go-deliver' ),
'type'     => 'textarea',
'required' => false,
),
array(
'key'      => 'packing_required',
'label'    => __( 'Packing Required', 'go-deliver' ),
'type'     => 'checkbox',
'required' => false,
),
array(
'key'      => 'estimated_volume',
'label'    => __( 'Estimated Volume (cubic meters)', 'go-deliver' ),
'type'     => 'number',
'required' => false,
),
);
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
