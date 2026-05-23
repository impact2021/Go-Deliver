<?php
/**
 * Email: Unread messages digest – sent hourly by cron when the user has
 * messages they haven't read yet.
 *
 * Variables: $recipient_first_name, $unread_count, $messaging_url,
 *            $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$recipient_first_name = isset( $recipient_first_name ) ? $recipient_first_name : '';
$unread_count         = isset( $unread_count )         ? (int) $unread_count   : 0;
$messaging_url        = isset( $messaging_url )        ? $messaging_url        : home_url();
$site_name            = isset( $site_name )            ? $site_name            : get_bloginfo( 'name' );
$site_url             = isset( $site_url )             ? $site_url             : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Unread Messages – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#067AE4;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg"><?php $recipient_first_name ? printf( esc_html__( 'Hi %s,', 'go-deliver' ), esc_html( $recipient_first_name ) ) : esc_html_e( 'Hi there,', 'go-deliver' ); ?></h1>
<p class="et">
<?php
printf(
	/* translators: %d: number of unread messages */
	esc_html( _n(
		'You have %d unread message waiting for you on %s.',
		'You have %d unread messages waiting for you on %s.',
		$unread_count,
		'go-deliver'
	) ),
	$unread_count,
	esc_html( $site_name )
);
?>
</p>
<div style="text-align:center;margin:24px 0;"><a href="<?php echo esc_url( $messaging_url ); ?>" class="btn"><?php esc_html_e( 'View Your Messages', 'go-deliver' ); ?></a></div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
