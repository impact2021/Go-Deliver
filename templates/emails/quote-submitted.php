<?php
/**
 * Email: Quote submitted – sent to customer.
 *
 * Variables: $customer_first_name, $mover_first_name, $mover_rating, $mover_review_count,
 *            $quote_amount, $job_type, $quotes_url, $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name          = isset( $site_name )          ? $site_name          : get_bloginfo( 'name' );
$site_url           = isset( $site_url )           ? $site_url           : home_url();
$customer_first_name = isset( $customer_first_name ) ? $customer_first_name : '';
$mover_first_name   = isset( $mover_first_name )   ? $mover_first_name   : __( 'A mover', 'go-deliver' );
$mover_rating        = isset( $mover_rating )        ? (float) $mover_rating : 0.0;
$mover_review_count  = isset( $mover_review_count )  ? (int) $mover_review_count : 0;
$quote_amount       = isset( $quote_amount )       ? (float) $quote_amount : 0;
$job_type           = isset( $job_type )           ? $job_type           : __( 'Moving Job', 'go-deliver' );
$quotes_url         = isset( $quotes_url )         ? $quotes_url         : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'New Quote Received – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#067AE4;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:24px;margin-bottom:24px;text-align:center}
.card__amount{font-size:38px;font-weight:800;color:#2563eb;line-height:1}
.card__mover{font-size:15px;font-weight:600;margin:10px 0 4px}
.card__rating{color:#f59e0b;font-size:20px;letter-spacing:2px}
.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg"><?php $customer_first_name ? printf( esc_html__( 'Hi %s,', 'go-deliver' ), esc_html( $customer_first_name ) ) : esc_html_e( 'Hi there,', 'go-deliver' ); ?></h1>
<p class="et"><?php printf( esc_html__( 'Great news! You have received a new quote for your %s job.', 'go-deliver' ), esc_html( $job_type ) ); ?></p>
<div class="card">
	<?php if ( $mover_rating > 0 ) : ?>
	<?php $stars = (int) round( $mover_rating ); ?>
	<div class="card__rating"><?php echo esc_html( str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars ) ); ?></div>
	<?php endif; ?>
	<div class="card__mover"><?php echo esc_html( $mover_first_name ); ?></div>
	<?php if ( $mover_rating > 0 ) : ?>
	<div style="color:#64748b;font-size:13px;margin-bottom:12px;"><?php printf( esc_html( _n( 'Rating: %1$s/5.0 from %2$d authenticated review', 'Rating: %1$s/5.0 from %2$d authenticated reviews', $mover_review_count, 'go-deliver' ) ), esc_html( number_format( $mover_rating, 1 ) ), $mover_review_count ); ?></div>
	<?php endif; ?>
	<div class="card__amount">$<?php echo esc_html( number_format( $quote_amount, 0 ) ); ?></div>
	<div style="color:#64748b;font-size:13px;margin-top:4px;"><?php esc_html_e( 'Quoted amount', 'go-deliver' ); ?></div>
</div>
<p class="et"><?php esc_html_e( 'Log in to your dashboard to view the full message from the mover and accept or decline the quote.', 'go-deliver' ); ?></p>
<div style="text-align:center;margin:24px 0;"><a href="<?php echo esc_url( $quotes_url ); ?>" class="btn"><?php esc_html_e( 'View Quotes', 'go-deliver' ); ?></a></div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
