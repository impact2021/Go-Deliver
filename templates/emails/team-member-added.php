<?php
/**
 * Email template: Welcome email to a newly added team member.
 *
 * Variables available:
 *   $member_first_name (string) New member's first name (or username).
 *   $member_username   (string) New member's login username.
 *   $member_password   (string) Plaintext password set by the mover.
 *   $team_name         (string) Parent mover's display name / company name.
 *   $login_url         (string) URL to the WordPress login page.
 *   $dashboard_url     (string) URL to the mover dashboard.
 *   $site_name         (string) Website / plugin name.
 *   $site_url          (string) Website URL.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name         = isset( $site_name )         ? $site_name         : get_bloginfo( 'name' );
$site_url          = isset( $site_url )          ? $site_url          : home_url();
$member_first_name = isset( $member_first_name ) ? $member_first_name : '';
$member_username   = isset( $member_username )   ? $member_username   : '';
$member_password   = isset( $member_password )   ? $member_password   : '';
$team_name         = isset( $team_name )         ? $team_name         : '';
$login_url         = isset( $login_url )         ? $login_url         : wp_login_url();
$dashboard_url     = isset( $dashboard_url )     ? $dashboard_url     : home_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( __( "You've been added to a team on %s", 'go-deliver' ), $site_name ) ); ?></title>
	<style>
		body { margin:0; padding:0; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1e293b; }
		.email-wrapper { max-width:580px; margin:32px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
		.email-header { background:#2563eb; padding:28px 32px; text-align:center; }
		.email-header__logo { color:#fff; font-size:22px; font-weight:800; letter-spacing:-.5px; text-decoration:none; }
		.email-body { padding:32px; }
		.email-greeting { font-size:18px; font-weight:700; margin:0 0 12px; }
		.email-text { font-size:15px; line-height:1.65; color:#475569; margin:0 0 20px; }
		.email-details { background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:20px; margin-bottom:24px; }
		.email-detail-row { display:flex; gap:12px; padding:8px 0; border-bottom:1px solid #e2e8f0; font-size:14px; }
		.email-detail-row:last-child { border-bottom:none; padding-bottom:0; }
		.email-detail-label { font-weight:600; color:#64748b; min-width:120px; }
		.email-detail-value { color:#1e293b; word-break:break-all; }
		.email-cta { text-align:center; margin:28px 0; }
		.email-cta__btn { display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:14px 32px; border-radius:6px; font-size:15px; font-weight:700; }
		.email-cta__btn:hover { background:#1d4ed8; }
		.email-notice { background:#fef9c3; border:1px solid #fde047; border-radius:6px; padding:14px 18px; font-size:13px; color:#713f12; margin-bottom:20px; }
		.email-footer { background:#f1f5f9; padding:20px 32px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
		.email-footer a { color:#64748b; }
		@media (max-width:600px) { .email-body { padding:20px; } .email-detail-row { flex-direction:column; gap:2px; } }
	</style>
</head>
<body>
<div class="email-wrapper">

	<div class="email-header">
		<a href="<?php echo esc_url( $site_url ); ?>" class="email-header__logo">
			🚚 <?php echo esc_html( $site_name ); ?>
		</a>
	</div>

	<div class="email-body">
		<h1 class="email-greeting">
			<?php
			if ( $member_first_name ) {
				/* translators: %s: member first name */
				printf( esc_html__( 'Hi %s,', 'go-deliver' ), esc_html( $member_first_name ) );
			} else {
				esc_html_e( 'Hi there,', 'go-deliver' );
			}
			?>
		</h1>

		<p class="email-text">
			<?php
			if ( $team_name ) {
				printf(
					/* translators: 1: team/mover name, 2: site name */
					esc_html__( '%1$s has added you as a team member on %2$s. You can now log in and use the mover dashboard to view and quote on jobs.', 'go-deliver' ),
					'<strong>' . esc_html( $team_name ) . '</strong>',
					esc_html( $site_name )
				);
			} else {
				printf(
					/* translators: %s: site name */
					esc_html__( 'You have been added as a team member on %s. You can now log in and use the mover dashboard to view and quote on jobs.', 'go-deliver' ),
					esc_html( $site_name )
				);
			}
			?>
		</p>

		<div class="email-details">
			<div class="email-detail-row">
				<span class="email-detail-label"><?php esc_html_e( 'Username', 'go-deliver' ); ?></span>
				<span class="email-detail-value"><?php echo esc_html( $member_username ); ?></span>
			</div>
			<?php if ( $member_password ) : ?>
			<div class="email-detail-row">
				<span class="email-detail-label"><?php esc_html_e( 'Password', 'go-deliver' ); ?></span>
				<span class="email-detail-value"><?php echo esc_html( $member_password ); ?></span>
			</div>
			<?php endif; ?>
			<div class="email-detail-row">
				<span class="email-detail-label"><?php esc_html_e( 'Login URL', 'go-deliver' ); ?></span>
				<span class="email-detail-value"><a href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_url( $login_url ); ?></a></span>
			</div>
		</div>

		<?php if ( $member_password ) : ?>
		<div class="email-notice">
			<?php esc_html_e( 'For your security, please log in and change your password as soon as possible.', 'go-deliver' ); ?>
		</div>
		<?php endif; ?>

		<div class="email-cta">
			<a href="<?php echo esc_url( $dashboard_url ); ?>" class="email-cta__btn">
				<?php esc_html_e( 'Go to Dashboard', 'go-deliver' ); ?>
			</a>
		</div>
	</div>

	<div class="email-footer">
		<p>
			<?php
			printf(
				/* translators: %s: site name */
				esc_html__( 'This account was created for you on %s. If you did not expect this email, please contact us.', 'go-deliver' ),
				esc_html( $site_name )
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a>
		</p>
	</div>

</div>
</body>
</html>
