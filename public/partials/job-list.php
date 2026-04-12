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
			foreach ( $jobs as $job ) :
				$pickup        = $job['pickup_location'] ?? array();
				$dropoff       = $job['dropoff_location'] ?? array();
				$status        = $job['status'] ?? 'open';
				$type          = $job['job_type'] ?? '';
				$type_label    = $job_type_labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
				$listing_title = ! empty( $job['listing_title'] ) ? $job['listing_title'] : null;
				$status_label  = $status_labels[ $status ] ?? ucfirst( $status );
				$date          = ! empty( $job['date_requested'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $job['date_requested'] ) ) : '';
				$date_flexible = ! empty( $job['form_data']['date_flexible'] );
				$photos        = ! empty( $job['photos'] ) && is_array( $job['photos'] ) ? $job['photos'] : array();

				// Additional information with read-more support.
				$inventory       = ! empty( $job['inventory'] ) ? wp_strip_all_tags( $job['inventory'] ) : '';
				$inv_words       = $inventory ? preg_split( '/\s+/', $inventory, -1, PREG_SPLIT_NO_EMPTY ) : array();
				$inv_word_count  = count( $inv_words );
				$inv_needs_more  = $inv_word_count > 50;
				$inv_preview     = $inv_needs_more ? implode( ' ', array_slice( $inv_words, 0, 50 ) ) . '…' : $inventory;
			?>
			<div class="gd-job-card" data-job-id="<?php echo esc_attr( $job['id'] ); ?>">

				<!-- Header: status badge + job type + optional listing title -->
				<div class="gd-job-card__header">
					<span class="gd-badge gd-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<div class="gd-job-card__title-wrap">
						<span class="gd-job-card__type"><?php echo esc_html( $type_label ); ?></span>
						<?php if ( $listing_title ) : ?>
							<span class="gd-job-card__listing-title"><?php echo esc_html( $listing_title ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<!-- Info row: From / To / Date columns + CTA button -->
				<div class="gd-job-card__info-row">
					<div class="gd-job-card__info-cols">
						<div class="gd-job-card__info-col">
							<span class="gd-job-card__info-label"><?php esc_html_e( 'From', 'go-deliver' ); ?></span>
							<span class="gd-job-card__info-value">
								<?php echo esc_html( $pickup['suburb'] ?? $pickup['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
							</span>
						</div>
						<div class="gd-job-card__info-col">
							<span class="gd-job-card__info-label"><?php esc_html_e( 'To', 'go-deliver' ); ?></span>
							<span class="gd-job-card__info-value">
								<?php echo esc_html( $dropoff['suburb'] ?? $dropoff['address'] ?? __( 'Unknown', 'go-deliver' ) ); ?>
							</span>
						</div>
						<?php if ( $date ) : ?>
						<div class="gd-job-card__info-col">
							<span class="gd-job-card__info-label"><?php esc_html_e( 'Moving date', 'go-deliver' ); ?></span>
							<span class="gd-job-card__info-value"><?php echo esc_html( $date ); ?></span>
							<?php if ( $date_flexible ) : ?>
								<span class="gd-job-card__info-flex"><?php esc_html_e( '(flexible)', 'go-deliver' ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
					<div class="gd-job-card__cta">
						<button
							type="button"
							class="gd-btn gd-btn--primary gd-quote-btn"
							data-job-id="<?php echo esc_attr( $job['id'] ); ?>"
						>
							<?php esc_html_e( 'Submit a Quote', 'go-deliver' ); ?>
						</button>
					</div>
				</div>

				<?php if ( $inventory || ! empty( $photos ) ) : ?>
				<div class="gd-job-card__divider"></div>
				<?php endif; ?>

				<?php if ( $inventory ) : ?>
				<div class="gd-job-card__extra">
					<div class="gd-job-card__extra-label"><?php esc_html_e( 'Additional information', 'go-deliver' ); ?></div>
					<div class="gd-job-card__extra-text">
						<?php if ( $inv_needs_more ) : ?>
							<span class="gd-read-more-short"><?php echo esc_html( $inv_preview ); ?></span>
							<span class="gd-read-more-full gd-hidden"><?php echo esc_html( $inventory ); ?></span>
							<button type="button" class="gd-read-more-btn"><?php esc_html_e( 'Read more', 'go-deliver' ); ?></button>
						<?php else : ?>
							<?php echo esc_html( $inventory ); ?>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

				<?php if ( ! empty( $photos ) ) : ?>
					<div class="gd-photo-gallery">
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

			</div><!-- /.gd-job-card -->
			<?php endforeach; ?>
		</div><!-- /.gd-jobs-grid -->

	<?php endif; ?>

</div><!-- /#gd-job-list -->
