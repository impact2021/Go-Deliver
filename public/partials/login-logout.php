<?php
/**
 * Login / logout shortcode template.
 *
 * Shortcode: [gd_login_logout]
 *
 * Attributes:
 *   redirect        – URL to send the user after a successful login.
 *                     Defaults to the current page.
 *   redirect_logout – URL to send the user after logging out.
 *                     Defaults to the current page.
 *
 * @package Go_Deliver
 *
 * @var array $atts Shortcode attributes (passed from render_login_logout).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_url    = esc_url( get_permalink() );
$login_redirect = ! empty( $atts['redirect'] ) ? esc_url_raw( $atts['redirect'] ) : $current_url;
$logout_redirect = ! empty( $atts['redirect_logout'] ) ? esc_url_raw( $atts['redirect_logout'] ) : $current_url;

if ( is_user_logged_in() ) :
	$current_user = wp_get_current_user();
	?>
	<span class="gd-login-logout gd-login-logout--logged-in">
		<?php
		printf(
			/* translators: %s: user display name */
			esc_html__( 'Hello, %s!', 'go-deliver' ),
			esc_html( $current_user->display_name )
		);
		?>
		<a href="<?php echo esc_url( wp_logout_url( $logout_redirect ) ); ?>" class="gd-login-logout__link">
			<?php esc_html_e( 'Log Out', 'go-deliver' ); ?>
		</a>
	</span>
<?php else : ?>
	<div class="gd-login-logout gd-login-logout--logged-out">
		<?php
		wp_login_form( array(
			'redirect'       => $login_redirect,
			'label_username' => __( 'Username or Email', 'go-deliver' ),
			'label_password' => __( 'Password', 'go-deliver' ),
			'label_log_in'   => __( 'Log In', 'go-deliver' ),
			'remember'       => true,
			'label_remember' => __( 'Remember me', 'go-deliver' ),
		) );
		?>
	</div>
<?php endif;
