<?php
/**
 * Email: Job completed – sent to customer asking for a review.
 *
 * Variables: $customer_first_name, $mover_first_name, $job_type,
 *            $review_url, $site_name, $site_url
 *
 * @package Go_Deliver
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$site_name            = isset( $site_name )            ? $site_name            : get_bloginfo( 'name' );
$site_url             = isset( $site_url )             ? $site_url             : home_url();
$customer_first_name  = isset( $customer_first_name )  ? $customer_first_name  : '';
$mover_first_name     = isset( $mover_first_name )     ? $mover_first_name     : __( 'Your mover', 'go-deliver' );
$job_type             = isset( $job_type )             ? $job_type             : __( 'Moving Job', 'go-deliver' );
$review_url           = isset( $review_url )           ? $review_url           : home_url();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo esc_html( sprintf( __( 'Your Move is Complete – %s', 'go-deliver' ), $site_name ) ); ?></title>
<style>
body{margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1e293b}
.ew{max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.eh{background:#067AE4;padding:28px 32px;text-align:center}
.eh a{color:#fff;font-size:22px;font-weight:800;text-decoration:none}
.eb{padding:32px}
.eg{font-size:18px;font-weight:700;margin:0 0 12px}
.et{font-size:15px;line-height:1.65;color:#475569;margin:0 0 20px}
.stars{font-size:32px;letter-spacing:4px;color:#f59e0b;display:block;text-align:center;margin:20px 0 8px}
.btn{display:inline-block;background:#16a34a;color:#fff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:15px;font-weight:700}
.ef{background:#f1f5f9;padding:20px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0}
</style></head><body>
<div class="ew">
<div class="eh"><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></div>
<div class="eb">
<h1 class="eg">
<?php
if ( $customer_first_name ) {
	/* translators: %s: customer first name */
	printf( esc_html__( 'Your move is complete, %s!', 'go-deliver' ), esc_html( $customer_first_name ) );
} else {
	esc_html_e( 'Your move is complete!', 'go-deliver' );
}
?>
</h1>
<p class="et">
<?php
printf(
	/* translators: 1: mover first name, 2: job type */
	esc_html__( '%1$s has marked your %2$s job as completed. We hope everything went smoothly!', 'go-deliver' ),
	esc_html( $mover_first_name ),
	esc_html( $job_type )
);
?>
</p>
<p class="et"><?php esc_html_e( 'Your feedback helps other customers choose great movers. It only takes a moment — please leave a star rating and a short comment.', 'go-deliver' ); ?></p>
<span class="stars">★★★★★</span>
<div style="text-align:center;margin-top:24px;">
	<a href="<?php echo esc_url( $review_url ); ?>" class="btn">
		<?php esc_html_e( 'Leave a Review', 'go-deliver' ); ?>
	</a>
</div>
</div>
<div class="ef"><p><?php printf( esc_html__( '© %s %s', 'go-deliver' ), esc_html( gmdate( 'Y' ) ), esc_html( $site_name ) ); ?></p><p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a></p></div>
</div></body></html>
