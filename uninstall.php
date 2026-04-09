<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Go_Deliver
 */

// Exit if not called from the WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// -------------------------------------------------------------------------
// 1. Drop custom tables.
// -------------------------------------------------------------------------
$tables = array(
	$wpdb->prefix . 'gd_wallet_transactions',
	$wpdb->prefix . 'gd_messages',
	$wpdb->prefix . 'gd_notifications',
	$wpdb->prefix . 'gd_mover_documents',
	$wpdb->prefix . 'gd_sub_users',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table names are safe, built from prefix + known string.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// -------------------------------------------------------------------------
// 2. Delete plugin options.
// -------------------------------------------------------------------------
$options = array(
	'gd_fee_percentage',
	'gd_job_expiry_days',
	'gd_quote_expiry_days',
	'gd_stripe_publishable_key',
	'gd_stripe_secret_key',
	'gd_stripe_webhook_secret',
	'gd_form_fields',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// -------------------------------------------------------------------------
// 3. Delete all posts of custom post types.
// -------------------------------------------------------------------------
$post_types = array( 'gd_job', 'gd_quote' );

foreach ( $post_types as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// -------------------------------------------------------------------------
// 4. Remove custom roles.
// -------------------------------------------------------------------------
$roles = array( 'gd_mover', 'gd_customer', 'gd_mover_sub' );

foreach ( $roles as $role ) {
	remove_role( $role );
}
