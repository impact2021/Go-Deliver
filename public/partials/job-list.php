<?php
/**
 * Job list template.
 *
 * Displays all open and locked jobs visible to the current mover.
 * Guests and non-movers see a login / access-denied notice instead.
 *
 * Shortcode: [gd_job_list]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guests – prompt login.
if ( ! is_user_logged_in() ) {
	echo '<div class="gd-wrap"><div class="gd-login-prompt">';
	echo '<div class="gd-login-prompt__icon">🔐</div>';
	echo '<h2 class="gd-login-prompt__title">' . esc_html__( 'Login Required', 'go-deliver' ) . '</h2>';
	echo '<p class="gd-login-prompt__text">' . esc_html__( 'Please log in as an approved mover to view available jobs.', 'go-deliver' ) . '</p>';
	echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="gd-btn gd-btn--primary">' . esc_html__( 'Log In', 'go-deliver' ) . '</a>';
	echo '</div></div>';
	return;
}

$current_user_id = get_current_user_id();
$user            = wp_get_current_user();
$roles           = (array) $user->roles;
$is_mover        = in_array( 'gd_mover', $roles, true ) || in_array( 'gd_mover_sub', $roles, true );
$is_admin        = current_user_can( 'manage_options' );

// Non-movers – access denied.
if ( ! $is_mover && ! $is_admin ) {
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">'
		. esc_html__( 'This page is for registered movers only.', 'go-deliver' )
		. '</div></div></div>';
	return;
}

$mover_status = get_user_meta( $current_user_id, 'gd_mover_status', true );

// Pending / rejected / suspended movers.
if ( ! $is_admin && 'approved' !== $mover_status ) {
	$messages = array(
		'pending'   => __( 'Your mover application is under review. You will be notified once approved.', 'go-deliver' ),
		'rejected'  => __( 'Your mover application was not approved. Please contact support for more information.', 'go-deliver' ),
		'suspended' => __( 'Your mover account has been suspended. Please contact support.', 'go-deliver' ),
	);
	$msg = isset( $messages[ $mover_status ] ) ? $messages[ $mover_status ] : __( 'Your account is not currently active.', 'go-deliver' );
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">' . esc_html( $msg ) . '</div></div></div>';
	return;
}

// Fetch open/locked jobs. Admins see everything; movers see only jobs
// within their configured radius and matching their job types.
$jobs_handler = new Go_Deliver_Jobs();
$jobs         = $is_admin
	? $jobs_handler->get_all_open_jobs()
	: $jobs_handler->get_open_jobs_for_mover( $current_user_id );

$job_type_labels = array(
	'trademe_pickup' => __( 'TradeMe Purchase Pickup', 'go-deliver' ),
	'item'           => __( 'Item', 'go-deliver' ),
	'furniture'      => __( 'Furniture', 'go-deliver' ),
	'item_packed'    => __( 'Packed Item', 'go-deliver' ),
	'move'           => __( 'House / Office Move', 'go-deliver' ),
	'car'            => __( 'Car', 'go-deliver' ),
	'motorcycle'     => __( 'Motorcycle', 'go-deliver' ),
	'vehicle'        => __( 'Vehicle', 'go-deliver' ),
	'other_vehicle'  => __( 'Other Vehicle', 'go-deliver' ),
	'boat'           => __( 'Boat', 'go-deliver' ),
	'piano'          => __( 'Piano', 'go-deliver' ),
	'pet'            => __( 'Pet Transport', 'go-deliver' ),
	'junk'           => __( 'Junk Removal', 'go-deliver' ),
	'other'          => __( 'Other', 'go-deliver' ),
);

$status_labels = array(
	'open'   => __( 'New', 'go-deliver' ),
	'locked' => __( 'Receiving Quotes', 'go-deliver' ),
);
?>
<div class="gd-wrap" id="gd-job-list">

	<div class="gd-dashboard-header">
		<h1 class="gd-dashboard-header__title"><?php esc_html_e( 'Available Jobs', 'go-deliver' ); ?></h1>
		<p class="gd-dashboard-header__subtitle">
			<?php esc_html_e( 'Jobs matching your service area and job types.', 'go-deliver' ); ?>
		</p>
	</div>

	<?php if ( empty( $jobs ) ) : ?>
		<div class="gd-empty-state">
			<div class="gd-empty-state__icon">📦</div>
			<h2 class="gd-empty-state__title"><?php esc_html_e( 'No jobs available right now', 'go-deliver' ); ?></h2>
			<p class="gd-empty-state__text">
				<?php esc_html_e( 'There are no open jobs in your area at the moment. Check back soon!', 'go-deliver' ); ?>
			</p>
		</div>
	<?php else : ?>

		<div class="gd-jobs-grid">
			<?php
			$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );
			foreach ( $jobs as $job ) :
				$pickup  = $job['pickup_location'] ?? array();
				$dropoff = $job['dropoff_location'] ?? array();
				$status  = $job['status'] ?? 'open';
				$type    = $job['job_type'] ?? '';
				$listing_title = ! empty( $job['listing_title'] ) ? $job['listing_title'] : null;
				$label   = $listing_title ?? ( $job_type_labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) ) );
				$status_label = $status_labels[ $status ] ?? ucfirst( $status );
				$date    = ! empty( $job['date_requested'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $job['date_requested'] ) ) : '';
				$date_flexible = ! empty( $job['form_data']['date_flexible'] );
				$photos  = ! empty( $job['photos'] ) && is_array( $job['photos'] ) ? $job['photos'] : array();
				$created_at  = $job['created_at'] ?? '';
				$expiry_str  = $created_at ? date_i18n( 'd M Y', strtotime( $created_at ) + $expiry_days * DAY_IN_SECONDS ) : '';
			?>
			<div class="gd-job-card" data-job-id="<?php echo esc_attr( $job['id'] ); ?>">

				<div class="gd-job-card__header">
					<span class="gd-badge gd-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<span class="gd-job-card__type"><?php echo esc_html( $label ); ?></span>
				</div>

				<div class="gd-job-card__body">
					<div class="gd-job-card__route">
						<div class="gd-job-card__location">
							<span class="gd-job-card__location-icon">📍</span>
							<span class="gd-job-card__location-text">
								<?php echo esc_html( $pickup['suburb'] ?? $pickup['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
							</span>
						</div>
						<div class="gd-job-card__arrow">→</div>
						<div class="gd-job-card__location">
							<span class="gd-job-card__location-icon">🏁</span>
							<span class="gd-job-card__location-text">
								<?php echo esc_html( $dropoff['suburb'] ?? $dropoff['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
							</span>
						</div>
					</div>

					<?php if ( $date ) : ?>
						<div class="gd-job-card__meta">
							<span class="gd-job-card__meta-icon">📅</span>
							<?php echo esc_html( $date ); ?>
							<?php if ( $date_flexible ) : ?>
								<span class="gd-badge gd-badge--info" style="margin-left:6px;font-size:11px;"><?php esc_html_e( 'Flexible', 'go-deliver' ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $expiry_str ) : ?>
						<div class="gd-job-card__meta">
							<span class="gd-job-card__meta-icon">⏰</span>
							<?php printf( esc_html__( 'Listing expires %s', 'go-deliver' ), esc_html( $expiry_str ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $job['inventory'] ) ) : ?>
						<div class="gd-job-card__notes">
							<?php echo esc_html( wp_trim_words( $job['inventory'], 20 ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $photos ) ) : ?>
						<div class="gd-photo-gallery" style="margin-top:10px;">
							<?php foreach ( array_slice( $photos, 0, 4 ) as $photo_id ) :
								$full_url  = wp_get_attachment_url( (int) $photo_id );
								$thumb_src = wp_get_attachment_image_src( (int) $photo_id, 'thumbnail' );
								if ( $full_url ) :
									$thumb_url = $thumb_src ? $thumb_src[0] : $full_url;
							?>
								<div class="gd-photo-gallery__item">
									<a href="<?php echo esc_url( $full_url ); ?>" target="_blank" rel="noopener" class="gd-photo-gallery__link">
										<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php esc_attr_e( 'Job photo', 'go-deliver' ); ?>" loading="lazy">
									</a>
								</div>
							<?php endif; endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="gd-job-card__footer">
					<button
						type="button"
						class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn"
						data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
					>
						<?php esc_html_e( 'View Details', 'go-deliver' ); ?>
					</button>
					<button
						type="button"
						class="gd-btn gd-btn--primary gd-btn--sm gd-quote-btn"
						data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
					>
						<?php esc_html_e( 'Submit Quote', 'go-deliver' ); ?>
					</button>
				</div>

			</div><!-- /.gd-job-card -->
			<?php endforeach; ?>
		</div><!-- /.gd-jobs-grid -->

	<?php endif; ?>

</div><!-- /#gd-job-list -->
