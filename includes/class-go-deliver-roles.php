<?php
/**
 * Manages custom user roles for the plugin.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Roles
 *
 * Adds and removes the three custom roles used by Go Deliver:
 *   - gd_customer   : end users who post moving jobs.
 *   - gd_mover      : registered moving companies / individuals.
 *   - gd_mover_sub  : sub-users created under a gd_mover account.
 */
class Go_Deliver_Roles {

	/**
	 * Register all plugin roles.
	 *
	 * Safe to call on every request – WordPress silently ignores add_role()
	 * when the role already exists.
	 */
	public static function register_roles() {
		// ---------------------------------------------------------------
		// Customer role.
		// ---------------------------------------------------------------
		add_role(
			'gd_customer',
			__( 'GD Customer', 'go-deliver' ),
			array(
				'read'             => true,
				'upload_files'     => true,
				'gd_submit_jobs'   => true,
				'gd_view_own_jobs' => true,
			)
		);

		// ---------------------------------------------------------------
		// Mover role.
		// ---------------------------------------------------------------
		add_role(
			'gd_mover',
			__( 'GD Mover', 'go-deliver' ),
			array(
				'read'                  => true,
				'upload_files'          => true,
				'gd_submit_quotes'      => true,
				'gd_view_jobs'          => true,
				'gd_view_own_quotes'    => true,
				'gd_manage_sub_users'   => true,
			)
		);

		// ---------------------------------------------------------------
		// Mover sub-user role.
		// ---------------------------------------------------------------
		add_role(
			'gd_mover_sub',
			__( 'GD Mover Sub-user', 'go-deliver' ),
			array(
				'read'               => true,
				'upload_files'       => true,
				'gd_submit_quotes'   => true,
				'gd_view_jobs'       => true,
				'gd_view_own_quotes' => true,
			)
		);

		// Ensure upload_files is present on all three roles for existing sites
		// where add_role() was already a no-op (roles persisted in wp_options).
		$customer_role = get_role( 'gd_customer' );
		if ( $customer_role && ! $customer_role->has_cap( 'upload_files' ) ) {
			$customer_role->add_cap( 'upload_files' );
		}
		$mover_role = get_role( 'gd_mover' );
		if ( $mover_role && ! $mover_role->has_cap( 'upload_files' ) ) {
			$mover_role->add_cap( 'upload_files' );
		}
		$mover_sub_role = get_role( 'gd_mover_sub' );
		if ( $mover_sub_role && ! $mover_sub_role->has_cap( 'upload_files' ) ) {
			$mover_sub_role->add_cap( 'upload_files' );
		}
	}

	/**
	 * Remove all plugin roles.
	 *
	 * Called during uninstall to clean up the WordPress roles table.
	 */
	public static function remove_roles() {
		remove_role( 'gd_customer' );
		remove_role( 'gd_mover' );
		remove_role( 'gd_mover_sub' );
	}
}
