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
	 * Output the viewport meta tag so responsive CSS media queries fire on
	 * real mobile devices.  If the active theme already emits a viewport tag
	 * this runs before it (priority 1) and browsers silently ignore the second
	 * duplicate, so there is no downside to always outputting it here.
	 *
	 * @since 1.2.33
	 */
	public function add_viewport_meta() {
		// Static, hard-coded string – no user input, no escaping needed.
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	}

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

		// Override brand colours with admin-saved values.
		$job_card_bg     = get_option( 'gd_job_card_bg', '#2D1B0E' );
		$job_card_accent = get_option( 'gd_job_card_accent', '#C9A227' );
		$inline_css      = sprintf(
			':root { --gd-job-card-bg: %1$s; --gd-job-card-accent: %2$s; }',
			sanitize_hex_color( $job_card_bg ),
			sanitize_hex_color( $job_card_accent )
		);
		wp_add_inline_style( 'go-deliver-public', $inline_css );

		$google_maps_key = get_option( 'gd_google_maps_api_key', '' );

		if ( $google_maps_key ) {
			wp_enqueue_script(
				'google-maps-places',
				'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $google_maps_key ) . '&libraries=places',
				array(),
				null,
				true
			);
		}

		wp_enqueue_script(
			'go-deliver-public',
			GD_PLUGIN_URL . 'public/js/go-deliver-public.js',
			array( 'jquery' ),
			GD_VERSION,
			true
		);

		$job_redirect_page_id = absint( get_option( 'gd_job_redirect_page_id', 0 ) );
		$dashboard_url        = $job_redirect_page_id ? get_permalink( $job_redirect_page_id ) : '';

		$mover_reg_redirect_page_id = absint( get_option( 'gd_mover_reg_redirect_page_id', 0 ) );
		$mover_reg_redirect_url     = $mover_reg_redirect_page_id ? get_permalink( $mover_reg_redirect_page_id ) : '';

		wp_localize_script(
			'go-deliver-public',
			'gdPublic',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'gd_public_nonce' ),
				'hasGooglePlaces'       => $google_maps_key ? '1' : '',
				'dashboardUrl'          => $dashboard_url ? esc_url( $dashboard_url ) : '',
				'moverRegRedirectUrl'   => $mover_reg_redirect_url ? esc_url( $mover_reg_redirect_url ) : '',
				'userId'                => is_user_logged_in() ? (string) get_current_user_id() : '0',
				'subUsersNonce'         => wp_create_nonce( 'gd_sub_users' ),
				'moverTourSeen'         => is_user_logged_in() && get_user_meta( get_current_user_id(), 'gd_mover_tour_completed', true ) ? '1' : '',
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
		$template = GD_PLUGIN_DIR . 'public/partials/job-form.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
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
		$template = GD_PLUGIN_DIR . 'public/partials/customer-dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
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
		$template = GD_PLUGIN_DIR . 'public/partials/mover-dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
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
		$template = GD_PLUGIN_DIR . 'public/partials/mover-registration.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
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
		$template = GD_PLUGIN_DIR . 'public/partials/messaging.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
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
		$template = GD_PLUGIN_DIR . 'public/partials/wallet-topup.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the login / logout block.
	 *
	 * Shortcode: [gd_login_logout]
	 *
	 * When the user is not logged in, displays a login form.
	 * When the user is logged in, displays a greeting and a logout link.
	 *
	 * Supported attributes:
	 *   redirect        – URL to redirect to after a successful login.
	 *   redirect_logout – URL to redirect to after logging out.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_login_logout( $atts ) {
		$atts = shortcode_atts(
			array(
				'redirect'        => '',
				'redirect_logout' => '',
			),
			$atts,
			'gd_login_logout'
		);

		ob_start();
		$template = GD_PLUGIN_DIR . 'public/partials/login-logout.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}
}
