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
 * Version:           1.1.0
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
define( 'GD_VERSION', '1.1.0' );
define( 'GD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-sub-users.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver-form-builder.php';
require_once GD_PLUGIN_DIR . 'includes/class-go-deliver.php';

// Admin and public classes are loaded conditionally inside Go_Deliver.
require_once GD_PLUGIN_DIR . 'admin/class-go-deliver-admin.php';
require_once GD_PLUGIN_DIR . 'public/class-go-deliver-public.php';

// Activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Go_Deliver_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Go_Deliver_Deactivator', 'deactivate' ) );

// Bootstrap the plugin.
Go_Deliver::run();
