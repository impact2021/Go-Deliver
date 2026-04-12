<?php
/**
 * Core plugin bootstrapper class.
 *
 * Registers all hooks, loads dependencies, and wires up admin / public areas.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver
 *
 * Orchestrates the entire plugin lifecycle.
 */
class Go_Deliver {

	/**
	 * Bootstrap the plugin by registering all hooks.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register init-time hooks.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'define_admin_hooks' ) );
		add_action( 'plugins_loaded', array( $this, 'define_public_hooks' ) );
		add_action( 'plugins_loaded', array( $this, 'define_common_hooks' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'go-deliver',
			false,
			dirname( GD_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register all admin-specific hooks.
	 */
	public function define_admin_hooks() {
		if ( ! is_admin() ) {
			return;
		}

		$admin = new Go_Deliver_Admin();

		add_action( 'admin_menu', array( $admin, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
		add_action( 'add_meta_boxes_gd_job', array( $admin, 'add_job_meta_boxes' ) );
		add_action( 'save_post_gd_job', array( $admin, 'save_job_meta' ) );
		add_action( 'admin_bar_menu', array( $admin, 'add_admin_bar_menu' ), 100 );

		// Admin-only AJAX handlers.
		$admin_ajax_actions = array(
			'gd_approve_mover',
			'gd_reject_mover',
			'gd_suspend_mover',
			'gd_adjust_wallet',
			'gd_update_document_status',
			'gd_save_form_fields',
			'gd_admin_update_mover_profile',
		);
		foreach ( $admin_ajax_actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $admin, 'dispatch_admin_ajax' ) );
		}
	}

	/**
	 * Register all public-facing hooks.
	 */
	public function define_public_hooks() {
		$public = new Go_Deliver_Public();

		add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );

		// Shortcodes.
		add_shortcode( 'gd_job_form', array( $public, 'render_job_form' ) );
		add_shortcode( 'gd_job_list', array( $public, 'render_job_list' ) );
		add_shortcode( 'gd_dashboard', array( $public, 'render_dashboard' ) );
		add_shortcode( 'gd_customer_dashboard', array( $public, 'render_customer_dashboard' ) );
		add_shortcode( 'gd_mover_dashboard', array( $public, 'render_mover_dashboard' ) );
		add_shortcode( 'gd_mover_registration', array( $public, 'render_mover_registration' ) );
		add_shortcode( 'gd_messaging', array( $public, 'render_messaging' ) );
		add_shortcode( 'gd_wallet_topup', array( $public, 'render_wallet_topup' ) );
		add_shortcode( 'gd_login_logout', array( $public, 'render_login_logout' ) );
	}

	/**
	 * Register hooks that are needed in both admin and public contexts.
	 */
	public function define_common_hooks() {
		// Stripe webhook listener.
		$stripe = new Go_Deliver_Stripe();
		$stripe->register_hooks();

		// Post types.
		$post_types = new Go_Deliver_Post_Types();
		add_action( 'init', array( $post_types, 'register_post_types' ) );

		// Roles (idempotent; safe to call on every request).
		add_action( 'init', array( 'Go_Deliver_Roles', 'register_roles' ) );

		// Notifications / cron.
		$notifications = new Go_Deliver_Notifications();
		$notifications->setup_cron();

		// Debug panel (admin-only, front end).
		$debug = new Go_Deliver_Debug();
		$debug->register_hooks();

		// Login redirect – send non-admin users to their dashboard page.
		add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );

		// Block WP admin area access for non-admin users.
		add_action( 'admin_init', array( $this, 'block_admin_for_non_admins' ) );

		// Hide the admin bar on the front end for non-admin users.
		add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_for_non_admins' ) );

		// ---------------------------------------------------------------
		// AJAX handlers – logged-in users.
		// ---------------------------------------------------------------
		$ajax_actions_auth = array(
			'gd_submit_job',
			'gd_submit_quote',
			'gd_accept_quote',
			'gd_withdraw_quote',
			'gd_send_message',
			'gd_get_messages',
			'gd_topup_wallet',
			'gd_stripe_topup',
			'gd_register_mover',
			'gd_submit_review',
			'gd_get_job_detail',
			'gd_get_job_details',
			'gd_cancel_job',
			'gd_complete_job',
			'gd_add_sub_user',
			'gd_remove_sub_user',
			'gd_get_available_jobs',
			'gd_update_mover_profile',
			'gd_get_my_quotes',
		);

		foreach ( $ajax_actions_auth as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'dispatch_ajax' ) );
		}

		// AJAX handlers – non-logged-in users.
		$ajax_actions_nopriv = array(
			'gd_submit_job',
			'gd_register_mover',
		);

		foreach ( $ajax_actions_nopriv as $action ) {
			add_action( 'wp_ajax_nopriv_' . $action, array( $this, 'dispatch_ajax' ) );
		}
	}

	/**
	 * Central AJAX dispatcher.
	 *
	 * Delegates to the appropriate handler based on the 'action' POST parameter.
	 * Each handler must verify a nonce before processing.
	 */
	public function dispatch_ajax() {
		$action = isset( $_POST['action'] ) ? sanitize_key( $_POST['action'] ) : '';

		$handler_map = array(
			'gd_submit_job'          => array( 'Go_Deliver_Jobs', 'ajax_submit_job' ),
			'gd_submit_quote'        => array( 'Go_Deliver_Quotes', 'ajax_submit_quote' ),
			'gd_accept_quote'        => array( 'Go_Deliver_Quotes', 'ajax_accept_quote' ),
			'gd_withdraw_quote'      => array( 'Go_Deliver_Quotes', 'ajax_withdraw_quote' ),
			'gd_send_message'        => array( 'Go_Deliver_Messaging', 'ajax_send_message' ),
			'gd_get_messages'        => array( 'Go_Deliver_Messaging', 'ajax_get_messages' ),
			'gd_topup_wallet'        => array( 'Go_Deliver_Wallet', 'ajax_topup_wallet' ),
			'gd_stripe_topup'        => array( 'Go_Deliver_Stripe', 'ajax_create_topup_session' ),
			'gd_register_mover'      => array( 'Go_Deliver_Mover_Reg', 'ajax_register_mover' ),
			'gd_submit_review'       => array( 'Go_Deliver_Reviews', 'ajax_submit_review' ),
			'gd_get_job_detail'      => array( 'Go_Deliver_Jobs', 'ajax_get_job_detail_html' ),
			'gd_get_job_details'     => array( 'Go_Deliver_Jobs', 'ajax_get_job_details' ),
			'gd_cancel_job'          => array( 'Go_Deliver_Jobs', 'ajax_cancel_job' ),
			'gd_complete_job'        => array( 'Go_Deliver_Jobs', 'ajax_complete_job' ),
			'gd_add_sub_user'        => array( 'Go_Deliver_Sub_Users', 'ajax_add_sub_user' ),
			'gd_remove_sub_user'     => array( 'Go_Deliver_Sub_Users', 'ajax_remove_sub_user' ),
			'gd_get_available_jobs'  => array( 'Go_Deliver_Jobs', 'ajax_get_available_jobs' ),
			'gd_update_mover_profile' => array( 'Go_Deliver_Mover_Reg', 'ajax_update_mover_profile' ),
			'gd_get_my_quotes'       => array( 'Go_Deliver_Quotes', 'ajax_get_my_quotes' ),
		);

		if ( isset( $handler_map[ $action ] ) ) {
			list( $class, $method ) = $handler_map[ $action ];

			if ( class_exists( $class ) && method_exists( $class, $method ) ) {
				call_user_func( array( new $class(), $method ) );
				return;
			}
		}

		wp_send_json_error( array( 'message' => __( 'Invalid action.', 'go-deliver' ) ), 400 );
	}

	/**
	 * Redirect non-admin users to their role-appropriate dashboard after login.
	 *
	 * Hooks onto WordPress's built-in login_redirect filter so that customers
	 * and movers never land on the WP admin dashboard.
	 *
	 * @param string  $redirect_to           The default redirect destination.
	 * @param string  $requested_redirect_to The requested redirect destination (may be empty).
	 * @param WP_User|WP_Error $user         The logged-in user object (or WP_Error on failure).
	 * @return string Redirect URL.
	 */
	public function redirect_after_login( $redirect_to, $requested_redirect_to, $user ) {
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		// Admins keep their normal redirect (usually the WP dashboard).
		if ( user_can( $user, 'manage_options' ) ) {
			return $redirect_to;
		}

		$roles = (array) $user->roles;

		if ( in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true ) ) {
			$page_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
			if ( $page_id ) {
				return get_permalink( $page_id );
			}
			return home_url();
		}

		// Customer or any other non-admin role.
		$page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
		if ( $page_id ) {
			return get_permalink( $page_id );
		}
		return home_url();
	}

	/**
	 * Prevent non-admin users from accessing the WP admin area.
	 *
	 * Fires on admin_init. AJAX requests are always allowed through so that
	 * front-end AJAX handlers registered under wp-admin/admin-ajax.php continue
	 * to work correctly.
	 */
	public function block_admin_for_non_admins() {
		if ( wp_doing_ajax() || current_user_can( 'manage_options' ) ) {
			return;
		}

		$roles = (array) wp_get_current_user()->roles;

		if ( in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true ) ) {
			$page_id = (int) get_option( 'gd_mover_dashboard_page_id', 0 );
			wp_safe_redirect( $page_id ? get_permalink( $page_id ) : home_url() );
			exit;
		}

		if ( is_user_logged_in() ) {
			$page_id = (int) get_option( 'gd_customer_dashboard_page_id', 0 );
			wp_safe_redirect( $page_id ? get_permalink( $page_id ) : home_url() );
			exit;
		}
	}

	/**
	 * Hide the WordPress admin bar for non-admin users.
	 *
	 * Filters the show_admin_bar value so that customers and movers never see
	 * the black admin toolbar on the front end.
	 *
	 * @param bool $show Whether the admin bar should be shown.
	 * @return bool
	 */
	public function hide_admin_bar_for_non_admins( $show ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return $show;
	}

	/**
	 * Create and return a plugin instance, bootstrapping all hooks.
	 *
	 * @return self
	 */
	public static function run() {
		return new self();
	}
}
