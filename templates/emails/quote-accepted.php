<?php
/**
 * Email: Quote accepted – sent to mover.
 *
 * Variables: $mover_first_name, $job_type, $pickup_suburb, $pickup_full,
 *            $dropoff_full, $date_requested, $customer_name, $customer_phone,
 *            $customer_email, $quote_amount, $fee_amount, $job_url,
 *            $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name        = isset( $site_name )        ? $site_name        : get_bloginfo( 'name' );
$site_url         = isset( $site_url )         ? $site_url         : home_url();
$mover_first_name = isset( $mover_first_name ) ? $mover_first_name : '';
$job_type         = isset( $job_type )         ? $job_type         : __( 'Moving Job', 'go-deliver' );
$pickup_full      = isset( $pickup_full )      ? $pickup_full      : ( isset( $pickup_suburb ) ? $pickup_suburb : '' );
$dropoff_full     = isset( $dropoff_full )     ? $dropoff_full     : '';
$date_requested   = isset( $date_requested )   ? $date_requested   : '';
$customer_name    = isset( $customer_name )    ? $customer_name    : '';
$customer_phone   = isset( $customer_phone )   ? $customer_phone   : '';
$customer_email   = isset( $customer_email )   ? $customer_email   : '';
$quote_amount     = isset( $quote_amount )     ? $quote_amount     : 0;
$fee_amount       = isset( $fee_amount )       ? $fee_amount       : 0;
$job_url          = isset( $job_url )          ? $job_url          : ( isset( $site_url ) ? $site_url : home_url() );
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Quote Accepted – %s', 'go-deliver' ), $site_name ) ); ?></title>
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
.highlight{background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:16px;margin-bottom:20px;font-size:14px}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg"><?php $mover_first_name ? printf( esc_html__( 'Congratulations, %s!', 'go-deliver' ), esc_html( $mover_first_name ) ) : esc_html_e( 'Congratulations!', 'go-deliver' ); ?></h1>
<p class="et"><?php esc_html_e( 'Your quote has been accepted! The customer is expecting your contact. Please reach out promptly to confirm the moving arrangements.', 'go-deliver' ); ?></p>
<div class="highlight">
<?php printf( esc_html__( 'A platform fee of $%s has been deducted from your wallet.', 'go-deliver' ), esc_html( number_format( (float) $fee_amount, 2 ) ) ); ?>
</div>
<div class="ed">
<div class="dr"><span class="dl"><?php esc_html_e( 'Job Type', 'go-deliver' ); ?></span><span><?php echo esc_html( $job_type ); ?></span></div>
<div class="dr"><span class="dl"><?php esc_html_e( 'Pickup Address', 'go-deliver' ); ?></span><span><?php echo esc_html( $pickup_full ); ?></span></div>
<div class="dr"><span class="dl"><?php esc_html_e( 'Dropoff Address', 'go-deliver' ); ?></span><span><?php echo esc_html( $dropoff_full ); ?></span></div>
<div class="dr"><span class="dl"><?php esc_html_e( 'Date Requested', 'go-deliver' ); ?></span><span><?php echo esc_html( $date_requested ); ?></span></div>
<div class="dr"><span class="dl"><?php esc_html_e( 'Your Quote', 'go-deliver' ); ?></span><span>$<?php echo esc_html( number_format( (float) $quote_amount, 2 ) ); ?></span></div>
</div>
<h3 style="margin:0 0 12px;font-size:16px;"><?php esc_html_e( 'Customer Contact Details', 'go-deliver' ); ?></h3>
<div class="ed">
<?php if ( $customer_name )  : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Name', 'go-deliver' ); ?></span><span><?php echo esc_html( $customer_name ); ?></span></div><?php endif; ?>
<?php if ( $customer_phone ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></span><span><?php echo esc_html( $customer_phone ); ?></span></div><?php endif; ?>
<?php if ( $customer_email ) : ?><div class="dr"><span class="dl"><?php esc_html_e( 'Email', 'go-deliver' ); ?></span><span><?php echo esc_html( $customer_email ); ?></span></div><?php endif; ?>
</div>
<p class="et"><?php esc_html_e( 'Please contact the customer as soon as possible to confirm the details and arrange the move.', 'go-deliver' ); ?></p>
<div style="text-align:center;margin-top:24px;"><a href="<?php echo esc_url( $job_url ); ?>" class="btn"><?php esc_html_e( 'View Job & Messages', 'go-deliver' ); ?></a></div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
