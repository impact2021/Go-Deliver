<?php
/**
 * Email template: New job notification to movers.
 *
 * Variables available:
 *   $mover_first_name  (string) Mover's first name.
 *   $job_id            (int)    Job post ID.
 *   $job_type          (string) Type of move.
 *   $pickup_suburb     (string) Pickup suburb (no full address for privacy).
 *   $dropoff_suburb    (string) Drop-off suburb (no full address for privacy).
 *   $date_requested    (string) Preferred moving date.
 *   $job_url           (string) URL to view the job.
 *   $site_name         (string) Website / plugin name.
 *   $site_url          (string) Website URL.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name      = isset( $site_name )      ? $site_name      : get_bloginfo( 'name' );
$site_url       = isset( $site_url )       ? $site_url       : home_url();
$mover_first_name = isset( $mover_first_name ) ? $mover_first_name : '';
$job_type       = isset( $job_type )       ? $job_type       : __( 'Moving Job', 'go-deliver' );
$pickup_suburb  = isset( $pickup_suburb )  ? $pickup_suburb  : '';
$dropoff_suburb = isset( $dropoff_suburb ) ? $dropoff_suburb : '';
$date_requested = isset( $date_requested ) ? $date_requested : '';
$job_url        = isset( $job_url )        ? $job_url        : home_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( sprintf( __( 'New Job Available – %s', 'go-deliver' ), $site_name ) ); ?></title>
	<style>
		body { margin:0; padding:0; background:#f8fafc; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1e293b; }
		.email-wrapper { max-width:580px; margin:32px auto; background:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
		.email-header { background:#2563eb; padding:28px 32px; text-align:center; }
		.email-header__logo { color:#fff; font-size:22px; font-weight:800; letter-spacing:-.5px; text-decoration:none; }
		.email-body { padding:32px; }
		.email-greeting { font-size:18px; font-weight:700; margin:0 0 12px; }
		.email-text { font-size:15px; line-height:1.65; color:#475569; margin:0 0 20px; }
		.email-section { border-top:2px solid #e2e8f0; border-bottom:2px solid #e2e8f0; padding:20px 0; margin:20px 0; }
		.email-section__title { font-size:16px; font-weight:700; color:#1e293b; margin:0 0 10px; }
		.email-section__text { font-size:15px; line-height:1.65; color:#475569; margin:0 0 8px; }
		.email-section__list { font-size:15px; line-height:1.65; color:#475569; margin:0; padding-left:20px; }
		.email-section__list li { margin-bottom:6px; }
		.email-cta { text-align:center; margin:28px 0; }
		.email-cta__btn { display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:14px 32px; border-radius:6px; font-size:15px; font-weight:700; }
		.email-cta__btn:hover { background:#1d4ed8; }
		.email-footer { background:#f1f5f9; padding:20px 32px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
		.email-footer a { color:#64748b; }
		@media (max-width:600px) { .email-body { padding:20px; } }
	</style>
</head>
<body>
<div class="email-wrapper">

	<div class="email-header">
		<a href="<?php echo esc_url( $site_url ); ?>" class="email-header__logo">
			<?php echo esc_html( $site_name ); ?>
		</a>
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

		<p class="email-text">
			<?php esc_html_e( 'A new job has just been posted on GoDeliver that matches your service area and job type.', 'go-deliver' ); ?>
		</p>

		<div class="email-section">
			<p class="email-section__title"><?php esc_html_e( 'Job Alert', 'go-deliver' ); ?></p>
			<p class="email-section__text"><?php esc_html_e( 'Customers are actively looking for providers right now.', 'go-deliver' ); ?></p>
			<p class="email-section__text"><?php esc_html_e( 'Log in to view full job details, pricing, and submit your quote.', 'go-deliver' ); ?></p>
		</div>

		<div class="email-section">
			<p class="email-section__title"><?php esc_html_e( 'Why act fast?', 'go-deliver' ); ?></p>
			<ul class="email-section__list">
				<li><?php esc_html_e( 'Jobs are limited to a set number of quotes', 'go-deliver' ); ?></li>
				<li><?php esc_html_e( 'Early quotes get seen first', 'go-deliver' ); ?></li>
				<li><?php esc_html_e( 'Faster responses = higher chance of winning', 'go-deliver' ); ?></li>
			</ul>
		</div>

		<div class="email-section">
			<p class="email-section__title"><?php esc_html_e( 'Start Quoting', 'go-deliver' ); ?></p>
			<p class="email-section__text"><?php esc_html_e( 'Click below to view the job and submit your quote:', 'go-deliver' ); ?></p>
		</div>

		<div class="email-cta">
			<a href="<?php echo esc_url( $job_url ); ?>" class="email-cta__btn">
				<?php esc_html_e( 'View Job & Quote Now', 'go-deliver' ); ?>
			</a>
		</div>

		<p class="email-text">
			<?php esc_html_e( 'From a single box to a full house move — there\'s always work available.', 'go-deliver' ); ?><br>
			<?php esc_html_e( 'Stay active, quote early, and win more jobs.', 'go-deliver' ); ?>
		</p>
	</div>

	<div class="email-footer">
		<p>
			<?php
			printf(
				/* translators: %s: site name */
				esc_html__( 'You are receiving this email because you are a registered mover on %s.', 'go-deliver' ),
				esc_html( $site_name )
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_name ); ?></a><br>
			<?php esc_html_e( 'New Zealand\'s Own Delivery Marketplace', 'go-deliver' ); ?>
		</p>
	</div>

</div>
</body>
</html>
