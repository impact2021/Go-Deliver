<?php
/**
 * Fired during plugin activation.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Activator
 *
 * Creates database tables, sets default options, registers roles,
 * schedules cron events, and flushes rewrite rules on activation.
 */
class Go_Deliver_Activator {

	/**
	 * Run all activation routines.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		Go_Deliver_Roles::register_roles();
		self::schedule_cron_events();
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Create (or upgrade) all custom database tables using dbDelta.
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// 1. Wallet transactions.
		$sql_wallet = "CREATE TABLE {$wpdb->prefix}gd_wallet_transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL,
			amount decimal(10,2) NOT NULL DEFAULT '0.00',
			description text NOT NULL,
			job_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quote_id bigint(20) unsigned NOT NULL DEFAULT 0,
			stripe_payment_id varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY job_id (job_id)
		) {$charset_collate};";

		// 2. Messages.
		$sql_messages = "CREATE TABLE {$wpdb->prefix}gd_messages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			receiver_id bigint(20) unsigned NOT NULL,
			message text NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY sender_id (sender_id),
			KEY receiver_id (receiver_id)
		) {$charset_collate};";

		// 3. Notifications.
		$sql_notifications = "CREATE TABLE {$wpdb->prefix}gd_notifications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(50) NOT NULL,
			subject varchar(255) NOT NULL,
			message text NOT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY is_read (is_read)
		) {$charset_collate};";

		// 4. Mover documents.
		$sql_mover_documents = "CREATE TABLE {$wpdb->prefix}gd_mover_documents (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			doc_type varchar(50) NOT NULL,
			file_url varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		// 5. Sub-users.
		$sql_sub_users = "CREATE TABLE {$wpdb->prefix}gd_sub_users (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			parent_mover_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			can_view_financials tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_mover_id (parent_mover_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql_wallet );
		dbDelta( $sql_messages );
		dbDelta( $sql_notifications );
		dbDelta( $sql_mover_documents );
		dbDelta( $sql_sub_users );
	}

	/**
	 * Set default plugin options when they do not already exist.
	 */
	private static function set_default_options() {
		$defaults = array(
			'gd_fee_percentage'   => 10,
			'gd_job_expiry_days'  => 30,
			'gd_quote_expiry_days' => 14,
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				add_option( $option, $value );
			}
		}

		// Default form fields schema (stored as JSON).
		if ( false === get_option( 'gd_form_fields' ) ) {
			$form_builder   = new Go_Deliver_Form_Builder();
			$default_fields = $form_builder->get_default_fields();
			add_option( 'gd_form_fields', wp_json_encode( $default_fields ) );
		}
	}

	/**
	 * Schedule cron events if not already scheduled.
	 */
	private static function schedule_cron_events() {
		if ( ! wp_next_scheduled( 'gd_hourly_notifications' ) ) {
			wp_schedule_event( time(), 'hourly', 'gd_hourly_notifications' );
		}

		if ( ! wp_next_scheduled( 'gd_daily_notifications' ) ) {
			wp_schedule_event( time(), 'daily', 'gd_daily_notifications' );
		}
	}
}
