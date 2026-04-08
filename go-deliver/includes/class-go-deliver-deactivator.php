<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Deactivator
 *
 * Cleans up scheduled events and rewrite rules on deactivation.
 * Data (tables, options, roles) is intentionally preserved so that
 * re-activating the plugin restores the previous state.
 */
class Go_Deliver_Deactivator {

	/**
	 * Run all deactivation routines.
	 */
	public static function deactivate() {
		self::clear_cron_events();
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Clear all plugin-specific cron events.
	 */
	private static function clear_cron_events() {
		$cron_hooks = array(
			'gd_hourly_notifications',
			'gd_daily_notifications',
		);

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
