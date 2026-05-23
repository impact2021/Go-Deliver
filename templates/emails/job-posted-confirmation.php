<?php
/**
 * Email: Job posted confirmation – sent to the customer immediately after they post a job.
 *
 * Variables: $customer_first_name, $job_id, $job_type, $pickup_suburb,
 *            $dropoff_suburb, $date_requested, $dashboard_url,
 *            $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name            = isset( $site_name )            ? $site_name            : get_bloginfo( 'name' );
$site_url             = isset( $site_url )             ? $site_url             : home_url();
$customer_first_name  = isset( $customer_first_name )  ? $customer_first_name  : '';
$job_id               = isset( $job_id )               ? (int) $job_id         : 0;
$job_type             = isset( $job_type )             ? $job_type             : __( 'Moving Job', 'go-deliver' );
$pickup_suburb        = isset( $pickup_suburb )        ? $pickup_suburb        : '';
$dropoff_suburb       = isset( $dropoff_suburb )       ? $dropoff_suburb       : '';
$date_requested       = isset( $date_requested )       ? $date_requested       : '';
$dashboard_url        = isset( $dashboard_url )        ? $dashboard_url        : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Job Posted – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#067AE4;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:24px}
.card__row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:14px}
.card__row:last-child{border-bottom:none}
.card__label{color:#64748b;font-weight:500}
.card__value{color:#1e293b;font-weight:600;text-align:right}
.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.notice{background:#eff6ff;border-left:4px solid #2563eb;border-radius:4px;padding:14px 16px;font-size:14px;color:#1e40af;margin-bottom:24px}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg">
<?php
if ( $customer_first_name ) {
	/* translators: %s: customer first name */
	printf( esc_html__( 'Hi %s, your job has been posted!', 'go-deliver' ), esc_html( $customer_first_name ) );
} else {
	esc_html_e( 'Your job has been posted!', 'go-deliver' );
}
?>
</h1>
<p class="et"><?php esc_html_e( 'Movers in your area are now being notified. You\'ll receive an email as soon as a mover submits a quote.', 'go-deliver' ); ?></p>

<div class="card">
	<?php if ( $job_type ) : ?>
	<div class="card__row">
		<span class="card__label"><?php esc_html_e( 'Job type', 'go-deliver' ); ?></span>
		<span class="card__value"><?php echo esc_html( $job_type ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $pickup_suburb ) : ?>
	<div class="card__row">
		<span class="card__label"><?php esc_html_e( 'Pickup', 'go-deliver' ); ?></span>
		<span class="card__value"><?php echo esc_html( $pickup_suburb ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $dropoff_suburb ) : ?>
	<div class="card__row">
		<span class="card__label"><?php esc_html_e( 'Dropoff', 'go-deliver' ); ?></span>
		<span class="card__value"><?php echo esc_html( $dropoff_suburb ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $date_requested ) : ?>
	<div class="card__row">
		<span class="card__label"><?php esc_html_e( 'Date', 'go-deliver' ); ?></span>
		<span class="card__value"><?php echo esc_html( $date_requested ); ?></span>
	</div>
	<?php endif; ?>
	<?php if ( $job_id ) : ?>
	<div class="card__row">
		<span class="card__label"><?php esc_html_e( 'Reference', 'go-deliver' ); ?></span>
		<span class="card__value">#<?php echo esc_html( $job_id ); ?></span>
	</div>
	<?php endif; ?>
</div>

<div class="notice">
	<?php esc_html_e( 'Need to make changes or cancel this job? You can do so at any time from your dashboard.', 'go-deliver' ); ?>
</div>

<div style="text-align:center;margin:24px 0;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn"><?php esc_html_e( 'View My Dashboard', 'go-deliver' ); ?></a>
</div>
</div>
<div class="ef">
	<p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p>
	<p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p>
</div>
</div></body></html>
