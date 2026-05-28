<?php
/**
 * Go Deliver
 *
 * @package           Go_Deliver
 * @author            Go Deliver
 * @copyright         2024 Go Deliver
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Go Deliver
 * Plugin URI:        https://godeliver.com
 * Description:       A moving marketplace plugin connecting customers with professional movers.
 * Version:           2.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Go Deliver
 * Author URI:        https://godeliver.com
 * Text Domain:       go-deliver
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
if ( ! defined( 'GD_VERSION' ) ) {
	define( 'GD_VERSION', '2.0.0' );
}
if ( ! defined( 'GD_PLUGIN_DIR' ) ) {
	define( 'GD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'GD_PLUGIN_URL' ) ) {
	define( 'GD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'GD_PLUGIN_BASENAME' ) ) {
	define( 'GD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'GD_JOB_CARD_PREVIEW_WORDS' ) ) {
	define( 'GD_JOB_CARD_PREVIEW_WORDS', 50 );
}

if ( ! function_exists( 'gd_get_admin_email' ) ) {
	/**
	 * Get the plugin-level admin/support email address.
	 *
	 * Falls back to the WordPress site admin email when not configured.
	 *
	 * @return string
	 */
	function gd_get_admin_email() {
		$email = sanitize_email( (string) get_option( 'gd_admin_email', '' ) );
		if ( ! $email ) {
			$email = sanitize_email( (string) get_option( 'admin_email' ) );
		}

		return $email;
	}
}

if ( ! function_exists( 'gd_normalize_unicode_escapes' ) ) {
	/**
	 * Normalize JSON-style unicode escapes and common de-slashed macron escapes.
	 *
	 * @param string $value Raw input value.
	 * @return string
	 */
	function gd_normalize_unicode_escapes( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return $value;
		}

		// Decode escaped unicode sequences such as "\u0101".
		$value = preg_replace_callback(
			'/\\\\u([0-9a-fA-F]{4})/',
			static function ( $matches ) {
				return html_entity_decode( '&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8' );
			},
			$value
		);

		// Recover common Māori macrons when a backslash has been stripped (e.g. "Tu0101wharanui").
		// Hex set: ā/Ā (0101/0100), ē/Ē (0113/0112), ī/Ī (012B/012A), ō/Ō (014D/014C), ū/Ū (016B/016A).
		$value = preg_replace_callback(
			'/(?<![0-9a-fA-F])u(0101|0113|012[bB]|014[dD]|016[bB]|0100|0112|012A|014C|016A)(?![0-9a-fA-F])/',
			static function ( $matches ) {
				return html_entity_decode( '&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8' );
			},
			$value
		);

		return $value;
	}
}

if ( ! function_exists( 'gd_heal_location_meta' ) ) {
	/**
	 * One-time database heal: fix any address/suburb meta that still contains
	 * broken Unicode escapes (e.g. "Tu0101wharanui") from before v2.0.
	 *
	 * Runs on admin_init, gated by a weekly transient so it only queries the DB
	 * once rather than on every admin page load.
	 */
	function gd_heal_location_meta() {
		if ( get_transient( 'gd_location_healed_v2' ) ) {
			return;
		}

		global $wpdb;

		// Fix flat address / suburb meta.
		$flat_keys = array( 'gd_pickup_address', 'gd_dropoff_address', 'gd_pickup_suburb', 'gd_dropoff_suburb' );
		foreach ( $flat_keys as $meta_key ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
					$meta_key
				)
			);
			foreach ( $rows as $row ) {
				$fixed = gd_normalize_unicode_escapes( $row->meta_value );
				if ( $fixed !== $row->meta_value ) {
					update_post_meta( (int) $row->post_id, $meta_key, $fixed );
				}
			}
		}

		// Fix JSON location blobs (gd_pickup_location / gd_dropoff_location).
		$json_keys = array( 'gd_pickup_location', 'gd_dropoff_location' );
		foreach ( $json_keys as $meta_key ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
					$meta_key
				)
			);
			foreach ( $rows as $row ) {
				$data = json_decode( $row->meta_value, true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				$changed = false;
				foreach ( array( 'address', 'suburb' ) as $field ) {
					if ( isset( $data[ $field ] ) ) {
						$fixed = gd_normalize_unicode_escapes( $data[ $field ] );
						if ( $fixed !== $data[ $field ] ) {
							$data[ $field ] = $fixed;
							$changed        = true;
						}
					}
				}

				if ( $changed ) {
					update_post_meta(
						(int) $row->post_id,
						$meta_key,
						wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
					);
					// Also sync the corresponding flat meta keys.
					$prefix = ( 'gd_pickup_location' === $meta_key ) ? 'gd_pickup' : 'gd_dropoff';
					if ( isset( $data['address'] ) ) {
						update_post_meta( (int) $row->post_id, $prefix . '_address', $data['address'] );
					}
					if ( isset( $data['suburb'] ) ) {
						update_post_meta( (int) $row->post_id, $prefix . '_suburb', $data['suburb'] );
					}
				}
			}
		}

		set_transient( 'gd_location_healed_v2', true, WEEK_IN_SECONDS );
	}
}
add_action( 'admin_init', 'gd_heal_location_meta' );

/**
 * Metadata filter: transparently normalise any broken macron escapes in
 * address/suburb meta values the instant they are read from the database.
 * This is belt-and-suspenders — the heal function above fixes stored data,
 * but this filter ensures any remaining stragglers are corrected at read time.
 */
add_filter(
	'get_post_metadata',
	static function ( $value, $object_id, $meta_key, $single ) {
		static $normalizing = false;

		if ( $normalizing ) {
			return $value; // Prevent recursion.
		}

		$address_keys = array( 'gd_pickup_address', 'gd_dropoff_address', 'gd_pickup_suburb', 'gd_dropoff_suburb' );
		if ( ! in_array( $meta_key, $address_keys, true ) ) {
			return $value; // Not an address field — let WordPress handle it.
		}

		// Let WordPress fetch the raw value, then normalise it.
		$normalizing = true;
		$raw         = get_post_meta( $object_id, $meta_key, $single );
		$normalizing = false;

		if ( null === $raw || false === $raw || '' === $raw ) {
			return $raw; // Nothing to normalise.
		}

		if ( is_array( $raw ) ) {
			return array_map( 'gd_normalize_unicode_escapes', $raw );
		}

		return gd_normalize_unicode_escapes( $raw );
	},
	10,
	4
);

// Include all class files from the includes/ directory.
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-activator.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-deactivator.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-roles.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-db.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-post-types.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-notifications.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-location.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-wallet.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-stripe.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-jobs.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-quotes.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-messaging.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-reviews.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-mover-reg.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-customer.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-sub-users.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-form-builder.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-debug.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver.php';

// Admin and public classes are loaded conditionally inside Go_Deliver.
require_once GD_PLUGIN_DIR . 'admin/class-go-deliver-admin.php';
require_once GD_PLUGIN_DIR . 'public/class-go-deliver-public.php';

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Go_Deliver_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Go_Deliver_Deactivator', 'deactivate' ) );

// Bootstrap the plugin.
Go_Deliver::run();
