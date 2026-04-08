<?php
/**
 * Admin-facing functionality.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Admin
 *
 * Registers admin menus, settings pages, and admin-side scripts/styles.
 */
class Go_Deliver_Admin {

	/**
	 * Register admin menu pages.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Go Deliver', 'go-deliver' ),
			__( 'Go Deliver', 'go-deliver' ),
			'manage_options',
			'go-deliver',
			array( $this, 'render_dashboard_page' ),
			'dashicons-car',
			24
		);

		add_submenu_page(
			'go-deliver',
			__( 'Dashboard', 'go-deliver' ),
			__( 'Dashboard', 'go-deliver' ),
			'manage_options',
			'go-deliver',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Settings', 'go-deliver' ),
			__( 'Settings', 'go-deliver' ),
			'manage_options',
			'go-deliver-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Movers', 'go-deliver' ),
			__( 'Movers', 'go-deliver' ),
			'manage_options',
			'go-deliver-movers',
			array( $this, 'render_movers_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Transactions', 'go-deliver' ),
			__( 'Transactions', 'go-deliver' ),
			'manage_options',
			'go-deliver-transactions',
			array( $this, 'render_transactions_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Only load on plugin pages.
		if ( strpos( $hook_suffix, 'go-deliver' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'go-deliver-admin',
			GD_PLUGIN_URL . 'admin/css/go-deliver-admin.css',
			array(),
			GD_VERSION
		);

		wp_enqueue_script(
			'go-deliver-admin',
			GD_PLUGIN_URL . 'admin/js/go-deliver-admin.js',
			array( 'jquery' ),
			GD_VERSION,
			true
		);

		wp_localize_script(
			'go-deliver-admin',
			'gdAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gd_admin_nonce' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers.
	// -------------------------------------------------------------------------

	/**
	 * Render the admin dashboard overview page.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Go Deliver Dashboard', 'go-deliver' ) . '</h1></div>';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Go Deliver Settings', 'go-deliver' ) . '</h1></div>';
	}

	/**
	 * Render the movers management page.
	 */
	public function render_movers_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Movers', 'go-deliver' ) . '</h1></div>';
	}

	/**
	 * Render the transactions page.
	 */
	public function render_transactions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Transactions', 'go-deliver' ) . '</h1></div>';
	}
}
