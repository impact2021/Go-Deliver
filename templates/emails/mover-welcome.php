<?php
/**
 * Email template: Welcome email for newly registered movers.
 *
 * Variables:
 *   $mover_first_name (string) Mover's first name.
 *   $login_url        (string) URL to log in to dashboard.
 *   $site_name        (string) Site / platform name.
 *   $site_url         (string) Site URL.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name        = isset( $site_name ) ? $site_name : get_bloginfo( 'name' );
$site_url         = isset( $site_url ) ? $site_url : home_url();
$mover_first_name = isset( $mover_first_name ) ? $mover_first_name : '';
$login_url        = isset( $login_url ) ? $login_url : wp_login_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( __( 'We received your mover application - %s', 'go-deliver' ), $site_name ) ); ?></title>
	<style>
		body { margin:0; padding:0; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1e293b; }
		.email-wrapper { max-width:620px; margin:32px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
		.email-header { background:#2563eb; padding:28px 32px; text-align:center; }
		.email-header__logo { color:#fff; font-size:22px; font-weight:800; letter-spacing:-.5px; text-decoration:none; }
		.email-body { padding:32px; }
		.email-greeting { font-size:18px; font-weight:700; margin:0 0 14px; }
		.email-text { font-size:15px; line-height:1.65; color:#475569; margin:0 0 14px; }
		.email-divider { border-top:1px solid #e2e8f0; margin:18px 0; }
		.email-title { font-size:16px; font-weight:700; margin:0 0 10px; }
		.email-list { margin:0 0 14px 18px; color:#475569; font-size:15px; line-height:1.65; }
		.email-list li { margin:0 0 6px; }
		.email-cta { text-align:center; margin:24px 0 16px; }
		.email-cta__btn { display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:14px 28px; border-radius:6px; font-size:15px; font-weight:700; }
		.email-footer { background:#f1f5f9; padding:20px 32px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
		.email-footer a { color:#64748b; }
		@media (max-width:640px) { .email-body { padding:20px; } }
	</style>
</head>
<body>
<div class="email-wrapper">
	<div class="email-header">
		<a href="<?php echo esc_url( $site_url ); ?>" class="email-header__logo"><?php echo esc_html( $site_name ); ?></a>
	</div>

	<div class="email-body">
		<h1 class="email-greeting">
			<?php
			if ( $mover_first_name ) {
				/* translators: %s: mover first name */
				printf( esc_html__( 'Hi %s,', 'go-deliver' ), esc_html( $mover_first_name ) );
			} else {
				esc_html_e( 'Hi there,', 'go-deliver' );
			}
			?>
		</h1>

		<p class="email-text"><?php esc_html_e( 'Thanks for applying to join GoDeliver — we’ve received your mover application.', 'go-deliver' ); ?></p>
		<p class="email-text"><?php esc_html_e( 'Our team is now reviewing your details and documents. We’ll email you again as soon as your account is approved.', 'go-deliver' ); ?></p>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( 'What happens next', 'go-deliver' ); ?></p>
		<ul class="email-list">
			<li><?php esc_html_e( 'We review your application and documents.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'If we need anything else, we’ll contact you by email.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Once approved, you’ll receive your next email with steps to start quoting for jobs.', 'go-deliver' ); ?></li>
		</ul>

		<div class="email-divider"></div>
		<p class="email-text"><?php esc_html_e( 'You’re close to getting new jobs on GoDeliver.', 'go-deliver' ); ?></p>
		<p class="email-text"><?php esc_html_e( 'If you have any questions in the meantime, feel free to reach out.', 'go-deliver' ); ?></p>
	</div>

	<div class="email-footer">
		<p>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a><br>
			<?php esc_html_e( 'New Zealand’s Delivery Marketplace', 'go-deliver' ); ?>
		</p>
	</div>
</div>
</body>
</html>
