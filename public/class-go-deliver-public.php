<?php
/**
 * Public-facing functionality.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Public
 *
 * Registers public scripts/styles and shortcode renderers.
 */
class Go_Deliver_Public {

	/**
	 * Enqueue public-facing scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'go-deliver-public',
			GD_PLUGIN_URL . 'public/css/go-deliver-public.css',
			array(),
			GD_VERSION
		);

		wp_enqueue_script(
			'go-deliver-public',
			GD_PLUGIN_URL . 'public/js/go-deliver-public.js',
			array( 'jquery' ),
			GD_VERSION,
			true
		);

		wp_localize_script(
			'go-deliver-public',
			'gdPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gd_public_nonce' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Shortcode renderers.
	// -------------------------------------------------------------------------

	/**
	 * Render the job submission form.
	 *
	 * Shortcode: [gd_job_form]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_job_form( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/job-form.php';
		return ob_get_clean();
	}

	/**
	 * Render the available job listings for movers.
	 *
	 * Shortcode: [gd_job_list]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_job_list( $atts ) {
		ob_start();
		$template = GD_PLUGIN_DIR . 'public/partials/job-list.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the appropriate dashboard based on the current user's role.
	 *
	 * Shortcode: [gd_dashboard]
	 * Routes movers to the mover dashboard and customers to the customer dashboard.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'go-deliver' ) . '</p>';
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true ) ) {
			return $this->render_mover_dashboard( $atts );
		}

		return $this->render_customer_dashboard( $atts );
	}

	/**
	 * Render the customer dashboard.
	 *
	 * Shortcode: [gd_customer_dashboard]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_customer_dashboard( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/customer-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render the mover dashboard.
	 *
	 * Shortcode: [gd_mover_dashboard]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_mover_dashboard( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/mover-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render the mover registration form.
	 *
	 * Shortcode: [gd_mover_registration]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_mover_registration( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/mover-registration.php';
		return ob_get_clean();
	}

	/**
	 * Render the messaging interface.
	 *
	 * Shortcode: [gd_messaging]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_messaging( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/messaging.php';
		return ob_get_clean();
	}

	/**
	 * Render the wallet top-up form.
	 *
	 * Shortcode: [gd_wallet_topup]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_wallet_topup( $atts ) {
		ob_start();
		include GD_PLUGIN_DIR . 'public/partials/wallet-topup.php';
		return ob_get_clean();
	}
}
