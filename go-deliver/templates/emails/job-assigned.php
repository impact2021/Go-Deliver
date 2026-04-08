<?php
/**
 * Email: Job assigned – sent to customer after accepting a quote.
 *
 * Variables: $customer_first_name, $job_type, $pickup_suburb, $date_requested,
 *            $mover_name, $mover_phone, $mover_email, $quote_amount,
 *            $dashboard_url, $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name          = isset( $site_name )          ? $site_name          : get_bloginfo( 'name' );
$site_url           = isset( $site_url )           ? $site_url           : home_url();
$customer_first_name = isset( $customer_first_name ) ? $customer_first_name : '';
$job_type           = isset( $job_type )           ? $job_type           : __( 'Moving Job', 'go-deliver' );
$pickup_suburb      = isset( $pickup_suburb )      ? $pickup_suburb      : '';
$date_requested     = isset( $date_requested )     ? $date_requested     : '';
$mover_name         = isset( $mover_name )         ? $mover_name         : '';
$mover_phone        = isset( $mover_phone )        ? $mover_phone        : '';
$mover_email        = isset( $mover_email )        ? $mover_email        : '';
$quote_amount       = isset( $quote_amount )       ? (float) $quote_amount : 0;
$dashboard_url      = isset( $dashboard_url )      ? $dashboard_url      : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Your Mover is Confirmed – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#16a34a;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.ed{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:20px;margin-bottom:24px}
.dr{display:flex;gap:12px;padding:8px 0;border-bottom:1px solid #dcfce7;font-size:14px}
.dr:last-child{border-bottom:none;padding-bottom:0}
.dl{font-weight:600;color:#64748b;min-width:140px}
.btn{display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>">🚚 <?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg"><?php $customer_first_name ? printf( esc_html__( 'Hi %s,', 'go-deliver' ), esc_html( $customer_first_name ) ) : esc_html_e( 'Hi there,', 'go-deliver' ); ?></h1>
<p class="et"><?php esc_html_e( 'Your job has been assigned! Your mover will contact you shortly to confirm the details. Here is a summary:', 'go-deliver' ); ?></p>
<h3 style="margin:0 0 10px;font-size:15px;"><?php esc_html_e( 'Job Summary', 'go-deliver' ); ?></h3>
<div class="ed">
<div class="dr"><span class="dl"><?php esc_html_e( 'Job Type', 'go-deliver' ); ?></span><span><?php echo esc_html( $job_type ); ?></span></div>
<?php if ( $pickup_suburb ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Pickup Suburb', 'go-deliver' ); ?></span><span><?php echo esc_html( $pickup_suburb ); ?></span></div><?php endif; ?>
<?php if ( $date_requested ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Moving Date', 'go-deliver' ); ?></span><span><?php echo esc_html( $date_requested ); ?></span></div><?php endif; ?>
<div class="dr"><span class="dl"><?php esc_html_e( 'Agreed Price', 'go-deliver' ); ?></span><span>$<?php echo esc_html( number_format( $quote_amount, 2 ) ); ?></span></div>
</div>
<h3 style="margin:0 0 10px;font-size:15px;"><?php esc_html_e( 'Your Mover', 'go-deliver' ); ?></h3>
<div class="ed">
<?php if ( $mover_name )  : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Name', 'go-deliver' ); ?></span><span><?php echo esc_html( $mover_name ); ?></span></div><?php endif; ?>
<?php if ( $mover_phone ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></span><span><?php echo esc_html( $mover_phone ); ?></span></div><?php endif; ?>
<?php if ( $mover_email ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Email', 'go-deliver' ); ?></span><span><?php echo esc_html( $mover_email ); ?></span></div><?php endif; ?>
</div>
<p class="et"><?php esc_html_e( 'After the move is complete, please leave a review to help other customers.', 'go-deliver' ); ?></p>
<div style="text-align:center;margin-top:20px;"><a href="<?php echo esc_url( $dashboard_url ); ?>" class="btn"><?php esc_html_e( 'View Dashboard', 'go-deliver' ); ?></a></div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
