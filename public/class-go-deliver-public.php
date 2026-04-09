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
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_job_form( $atts ) {
		ob_start();
		$template = GD_PLUGIN_DIR . 'templates/job-form.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the available job listings for movers.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_job_list( $atts ) {
		ob_start();
		$template = GD_PLUGIN_DIR . 'templates/job-list.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the user dashboard.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_dashboard( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'go-deliver' ) . '</p>';
		}

		ob_start();
		$template = GD_PLUGIN_DIR . 'templates/dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the mover registration form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_register( $atts ) {
		ob_start();
		$template = GD_PLUGIN_DIR . 'templates/register.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}
}
