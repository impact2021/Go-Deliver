<?php
/**
 * Email: Job cancelled by customer – sent to the mover with refund notice.
 *
 * Variables: $mover_first_name, $job_type, $refund_amount,
 *            $dashboard_url, $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name         = isset( $site_name )         ? $site_name         : get_bloginfo( 'name' );
$site_url          = isset( $site_url )          ? $site_url          : home_url();
$mover_first_name  = isset( $mover_first_name )  ? $mover_first_name  : '';
$job_type          = isset( $job_type )          ? $job_type          : __( 'Moving Job', 'go-deliver' );
$refund_amount     = isset( $refund_amount )     ? (float) $refund_amount : 0.0;
$dashboard_url     = isset( $dashboard_url )     ? $dashboard_url     : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Job Cancelled – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#dc2626;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.refund-box{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:16px 20px;margin:20px 0;text-align:center}
.refund-label{font-size:13px;color:#15803d;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.refund-amount{font-size:28px;font-weight:800;color:#15803d;margin:4px 0 0}
.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg">
<?php
if ( $mover_first_name ) {
	/* translators: %s: mover first name */
	printf( esc_html__( 'Hi %s, a job has been cancelled', 'go-deliver' ), esc_html( $mover_first_name ) );
} else {
	esc_html_e( 'A job has been cancelled', 'go-deliver' );
}
?>
</h1>
<p class="et">
<?php
/* translators: %s: job type */
printf( esc_html__( 'Unfortunately, the customer has cancelled their %s job.', 'go-deliver' ), esc_html( $job_type ) );
?>
</p>
<?php if ( $refund_amount > 0 ) : ?>
<div class="refund-box">
	<div class="refund-label"><?php esc_html_e( 'Credits Refunded', 'go-deliver' ); ?></div>
	<div class="refund-amount">$<?php echo esc_html( number_format( $refund_amount, 2 ) ); ?></div>
</div>
<p class="et"><?php esc_html_e( 'Your platform fee has been fully refunded to your Go Deliver wallet. You can view your updated balance and continue quoting on new jobs.', 'go-deliver' ); ?></p>
<?php else : ?>
<p class="et"><?php esc_html_e( 'You can continue browsing and quoting on other available jobs.', 'go-deliver' ); ?></p>
<?php endif; ?>
<div style="text-align:center;margin-top:24px;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn">
		<?php esc_html_e( 'Go to Dashboard', 'go-deliver' ); ?>
	</a>
</div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
