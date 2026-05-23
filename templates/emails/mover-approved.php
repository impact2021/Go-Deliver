<?php
/**
 * Email template: Mover account approved.
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
	<title><?php echo esc_html( sprintf( __( 'Your account has been approved - %s', 'go-deliver' ), $site_name ) ); ?></title>
	<style>
		body { margin:0; padding:0; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1e293b; }
		.email-wrapper { max-width:620px; margin:32px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
		.email-header { background:#067AE4; padding:28px 32px; text-align:center; }
		.email-header__logo { color:#fff; font-size:22px; font-weight:800; letter-spacing:-.5px; text-decoration:none; }
		.email-body { padding:32px; }
		.email-greeting { font-size:18px; font-weight:700; margin:0 0 14px; }
		.email-text { font-size:15px; line-height:1.65; color:#475569; margin:0 0 14px; }
		.email-divider { border-top:1px solid #e2e8f0; margin:18px 0; }
		.email-title { font-size:16px; font-weight:700; margin:0 0 10px; }
		.email-list { margin:0 0 14px 18px; color:#475569; font-size:15px; line-height:1.65; }
		.email-list li { margin:0 0 6px; }
		.email-cta { text-align:center; margin:24px 0 16px; }
		.email-cta__btn { display:inline-block; background:#16a34a; color:#fff; text-decoration:none; padding:14px 28px; border-radius:6px; font-size:15px; font-weight:700; }
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

		<p class="email-text"><?php esc_html_e( 'Congratulations — your mover account has been approved.', 'go-deliver' ); ?></p>
		<p class="email-text"><?php esc_html_e( 'You can now log in and start quoting on available jobs.', 'go-deliver' ); ?></p>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( '🚀 Getting Started', 'go-deliver' ); ?></p>
		<p class="email-text"><?php esc_html_e( 'To begin quoting on jobs, you’ll need to top up your GoDeliver account balance. This allows you to access job opportunities and start submitting quotes.', 'go-deliver' ); ?></p>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( '📦 Quoting on Jobs', 'go-deliver' ); ?></p>
		<ul class="email-list">
			<li><?php esc_html_e( 'Browse available jobs in your dashboard.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Submit quotes on jobs that suit you.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Get notified when customers respond.', 'go-deliver' ); ?></li>
		</ul>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( '🏆 When You Win a Job', 'go-deliver' ); ?></p>
		<p class="email-text"><?php esc_html_e( 'When a customer accepts your quote: a 10% platform fee will be deducted from the total job value. This fee is for using the GoDeliver platform to secure the job.', 'go-deliver' ); ?></p>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( '⚡ Simple Summary', 'go-deliver' ); ?></p>
		<ul class="email-list">
			<li><?php esc_html_e( 'Top up your account.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Quote on jobs.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Win work.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( '10% fee applies only when you secure the job.', 'go-deliver' ); ?></li>
		</ul>

		<div class="email-divider"></div>
		<p class="email-title"><?php esc_html_e( '💡 Tips to Get Started', 'go-deliver' ); ?></p>
		<ul class="email-list">
			<li><?php esc_html_e( 'Respond quickly to new jobs.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Keep your pricing competitive.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Add a short, professional message.', 'go-deliver' ); ?></li>
			<li><?php esc_html_e( 'Keep your profile updated.', 'go-deliver' ); ?></li>
		</ul>

		<div class="email-cta">
			<a href="<?php echo esc_url( $login_url ); ?>" class="email-cta__btn"><?php esc_html_e( 'Log In to Dashboard', 'go-deliver' ); ?></a>
		</div>

		<p class="email-text"><?php esc_html_e( 'From a single box to a full house move — there’s work waiting.', 'go-deliver' ); ?></p>
	</div>

	<div class="email-footer">
		<p>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
		</p>
	</div>
</div>
</body>
</html>
