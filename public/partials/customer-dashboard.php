<?php
/**
 * Customer dashboard template.
 *
 * Shortcode: [gd_customer_dashboard]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() || ! current_user_can( 'gd_submit_jobs' ) ) {
	echo '<div class="gd-wrap"><div class="gd-login-prompt">';
	echo '<div class="gd-login-prompt__icon">🔐</div>';
	echo '<h2 class="gd-login-prompt__title">' . esc_html__( 'Access Denied', 'go-deliver' ) . '</h2>';
	echo '<p class="gd-login-prompt__text">' . esc_html__( 'Please log in as a customer to view your dashboard.', 'go-deliver' ) . '</p>';
	echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="gd-btn gd-btn--primary">' . esc_html__( 'Log In', 'go-deliver' ) . '</a>';
	echo '</div></div>';
	return;
}

$current_user = wp_get_current_user();
$user_id      = get_current_user_id();

// Page links.
$messaging_page_id  = (int) get_option( 'gd_messaging_page_id', 0 );
$messaging_base_url = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();
$job_form_page_id   = (int) get_option( 'gd_job_form_page_id', 0 );
$job_form_url       = $job_form_page_id ? get_permalink( $job_form_page_id ) : '';

// Fetch customer's jobs.
$jobs_query = new WP_Query( array(
	'post_type'      => 'gd_job',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'meta_query'     => array(
		array(
			'key'   => 'gd_customer_id',
			'value' => $user_id,
			'type'  => 'NUMERIC',
		),
	),
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => false,
) );

$jobs = $jobs_query->posts;
wp_reset_postdata();

// Calculate stats.
$total_jobs   = count( $jobs );
$active_jobs  = 0;
$pending_jobs = 0;
$total_quotes = 0;

foreach ( $jobs as $job ) {
	$status      = get_post_meta( $job->ID, 'gd_job_status', true );
	$quote_count = (int) get_post_meta( $job->ID, 'gd_quote_count', true );

	if ( in_array( $status, array( 'open', 'locked' ), true ) ) {
		$active_jobs++;
	}
	if ( 'open' === $status && $quote_count > 0 ) {
		$pending_jobs++;
	}
	$total_quotes += $quote_count;
}
?>
<div class="gd-wrap" id="gd-customer-dashboard">
<div class="gd-dashboard">

<!-- ============================================================
     Sidebar
     ============================================================ -->
<aside class="gd-dashboard__sidebar">
<nav class="gd-sidebar-nav">
<a class="gd-sidebar-nav__item gd-sidebar-nav__item--active" data-panel="jobs" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> <?php esc_html_e( 'My Jobs', 'go-deliver' ); ?>
<?php if ( $total_jobs ) : ?>
<span class="gd-badge gd-badge--open" style="margin-left:auto;"><?php echo esc_html( $total_jobs ); ?></span>
<?php endif; ?>
</a>
<a class="gd-sidebar-nav__item" href="<?php echo esc_url( $messaging_base_url ); ?>">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span> <?php esc_html_e( 'Messages', 'go-deliver' ); ?>
</a>
<a class="gd-sidebar-nav__item" data-panel="profile" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span> <?php esc_html_e( 'My Profile', 'go-deliver' ); ?>
</a>
</nav>
<?php if ( $job_form_url ) : ?>
<div class="gd-sidebar-nav__footer">
<a href="<?php echo esc_url( $job_form_url ); ?>" class="gd-btn gd-btn--cta" style="width:100%;justify-content:center;">
+ <?php esc_html_e( 'Post New Job', 'go-deliver' ); ?>
</a>
</div>
<?php endif; ?>
</aside>

<!-- ============================================================
     Main content
     ============================================================ -->
<div class="gd-dashboard__main">

<!-- ========================================================
     Panel: My Jobs (default)
     ======================================================== -->
<div class="gd-panel gd-panel--active" id="gd-panel-jobs">

	<!-- Dashboard Header -->
	<div class="gd-dashboard-header">
		<h1 class="gd-dashboard-header__title">
			<?php
			printf(
				/* translators: %s: user first name */
				esc_html__( 'Welcome back, %s!', 'go-deliver' ),
				esc_html( $current_user->first_name ?: $current_user->display_name )
			);
			?>
		</h1>
		<p class="gd-dashboard-header__subtitle">
			<?php esc_html_e( 'Manage your moving jobs and view quotes from movers.', 'go-deliver' ); ?>
		</p>
	</div>

	<!-- Stats Bar -->
	<div class="gd-stats-bar">
		<div class="gd-stat-card">
			<div class="gd-stat-card__value"><?php echo esc_html( $total_jobs ); ?></div>
			<div class="gd-stat-card__label"><?php esc_html_e( 'Total Jobs', 'go-deliver' ); ?></div>
		</div>
		<div class="gd-stat-card">
			<div class="gd-stat-card__value"><?php echo esc_html( $active_jobs ); ?></div>
			<div class="gd-stat-card__label"><?php esc_html_e( 'Active Jobs', 'go-deliver' ); ?></div>
		</div>
		<div class="gd-stat-card">
			<div class="gd-stat-card__value"><?php echo esc_html( $pending_jobs ); ?></div>
			<div class="gd-stat-card__label"><?php esc_html_e( 'Awaiting Action', 'go-deliver' ); ?></div>
		</div>
	</div>

	<?php
	// ----------------------------------------------------------------
	// Your Current Job hero card (most recently accepted job)
	// ----------------------------------------------------------------
	$hero_job = null;
	foreach ( $jobs as $j ) {
		if ( 'accepted' === get_post_meta( $j->ID, 'gd_job_status', true ) ) {
			$hero_job = $j;
			break;
		}
	}

	if ( $hero_job ) :
		$hj_id           = $hero_job->ID;
		$hj_title        = esc_html( Go_Deliver_Jobs::get_display_title( $hj_id ) ) ?: esc_html__( 'Moving Job', 'go-deliver' );
		$hj_pickup       = esc_html( get_post_meta( $hj_id, 'gd_pickup_address', true ) ) ?: esc_html( get_post_meta( $hj_id, 'gd_pickup_suburb', true ) );
		$hj_dropoff      = esc_html( get_post_meta( $hj_id, 'gd_dropoff_address', true ) ) ?: esc_html( get_post_meta( $hj_id, 'gd_dropoff_suburb', true ) );
		$hj_date_raw     = get_post_meta( $hj_id, 'gd_date_requested', true );
		$hj_date_fmt     = $hj_date_raw ? date_i18n( 'F j', strtotime( $hj_date_raw ) ) : '';
		$hj_acc_qid      = (int) get_post_meta( $hj_id, 'gd_accepted_quote_id', true );
		$hj_mover_id     = $hj_acc_qid ? (int) get_post_meta( $hj_acc_qid, 'gd_mover_id', true ) : 0;
		$hj_amount       = $hj_acc_qid ? (float) get_post_meta( $hj_acc_qid, 'gd_amount', true ) : 0.0;
		$hj_mover        = $hj_mover_id ? get_userdata( $hj_mover_id ) : null;
		$hj_company      = $hj_mover_id ? ( esc_html( get_user_meta( $hj_mover_id, 'gd_company_name', true ) ) ?: esc_html( $hj_mover ? $hj_mover->display_name : '' ) ) : '';
		$hj_rating       = $hj_mover_id ? (float) get_user_meta( $hj_mover_id, 'gd_average_rating', true ) : 0.0;
		$hj_review_count = $hj_mover_id ? (int) get_user_meta( $hj_mover_id, 'gd_review_count', true ) : 0;

		// Resolve mover profile + fleet photo URLs.
		$hj_profile_url = '';
		$hj_fleet_url   = '';
		if ( $hj_mover_id ) {
			$raw_photos = get_user_meta( $hj_mover_id, 'gd_mover_photos', true );
			$photo_ids  = is_string( $raw_photos ) ? (array) json_decode( $raw_photos, true ) : array();
			$photo_ids  = array_values( array_filter( array_map( 'absint', $photo_ids ) ) );

			if ( ! empty( $photo_ids ) ) {
				$hj_profile_url = wp_get_attachment_image_url( $photo_ids[0], 'thumbnail' ) ?: '';
				if ( isset( $photo_ids[1] ) ) {
					$hj_fleet_url = wp_get_attachment_image_url( $photo_ids[1], 'medium_large' ) ?: '';
				}
			}
			if ( ! $hj_profile_url ) {
				$legacy_photo_id = (int) get_user_meta( $hj_mover_id, 'gd_profile_photo_id', true );
				if ( $legacy_photo_id ) {
					$hj_profile_url = wp_get_attachment_image_url( $legacy_photo_id, 'thumbnail' ) ?: '';
				}
			}
			if ( ! $hj_fleet_url ) {
				$raw_fleet    = get_user_meta( $hj_mover_id, 'gd_fleet_photos', true );
				$fleet_decode = json_decode( $raw_fleet, true );
				if ( is_array( $fleet_decode ) && ! empty( $fleet_decode ) ) {
					$hj_fleet_url = wp_get_attachment_image_url( absint( $fleet_decode[0] ), 'medium_large' ) ?: '';
				}
			}
		}

		$hj_msg_url = ( $messaging_page_id && $hj_mover_id )
			? esc_url( add_query_arg( 'with', $hj_mover_id, get_permalink( $messaging_page_id ) ) )
			: '';
	?>
	<h2 class="gd-current-job-hero__section-heading"><?php esc_html_e( 'Your Current Job', 'go-deliver' ); ?></h2>
	<div class="gd-current-job-hero">
		<div class="gd-current-job-hero__body">

			<!-- Fleet photo / media column -->
			<div class="gd-current-job-hero__media"<?php if ( $hj_fleet_url ) echo ' style="background-image:url(\'' . esc_url( $hj_fleet_url ) . '\')"'; ?>>
				<div class="gd-current-job-hero__avatar-wrap">
					<?php if ( $hj_profile_url ) : ?>
						<img src="<?php echo esc_url( $hj_profile_url ); ?>" alt="<?php echo esc_attr( $hj_company ); ?>" class="gd-current-job-hero__avatar-img">
					<?php else : ?>
						<span class="gd-current-job-hero__avatar-initials" aria-hidden="true">
							<?php echo esc_html( strtoupper( mb_substr( $hj_company ?: 'M', 0, 1 ) ) ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div><!-- /.gd-current-job-hero__media -->

			<!-- Content column -->
			<div class="gd-current-job-hero__info">

				<!-- Top row: title + price/badge -->
				<div class="gd-current-job-hero__top">
					<div class="gd-current-job-hero__title-group">
						<h3 class="gd-current-job-hero__title"><?php echo $hj_title; ?></h3>
						<?php if ( $hj_pickup || $hj_dropoff ) : ?>
							<p class="gd-current-job-hero__route">
								<?php
								if ( $hj_pickup && $hj_dropoff ) {
									echo $hj_pickup . ' <span aria-hidden="true">→</span> ' . $hj_dropoff;
								} else {
									echo $hj_pickup ?: $hj_dropoff;
								}
								?>
							</p>
						<?php endif; ?>
					</div>
					<div class="gd-current-job-hero__price-group">
						<?php if ( $hj_amount ) : ?>
							<span class="gd-current-job-hero__price">$<?php echo esc_html( number_format( $hj_amount, 0 ) ); ?></span>
						<?php endif; ?>
						<span class="gd-badge gd-badge--accepted"><?php esc_html_e( 'Accepted', 'go-deliver' ); ?></span>
						<?php if ( $hj_date_fmt ) : ?>
							<p class="gd-current-job-hero__date">
								<?php esc_html_e( 'Moving Date:', 'go-deliver' ); ?>
								<strong><?php echo esc_html( $hj_date_fmt ); ?></strong>
							</p>
						<?php endif; ?>
					</div>
				</div><!-- /.gd-current-job-hero__top -->

				<!-- Mover row: name/rating + View Mover button -->
				<?php if ( $hj_mover ) : ?>
				<div class="gd-current-job-hero__mover-row">
					<div class="gd-current-job-hero__mover-info">
						<?php if ( $hj_company ) : ?>
							<strong class="gd-current-job-hero__mover-name"><?php echo $hj_company; ?></strong>
						<?php endif; ?>
						<?php if ( $hj_rating > 0 ) : ?>
							<div class="gd-rating-display">
								<?php
								$hj_rating_int = (int) round( $hj_rating );
								for ( $s = 1; $s <= 5; $s++ ) {
									$star_cls = $s <= $hj_rating_int ? 'gd-star gd-star--filled' : 'gd-star';
									echo '<span class="' . esc_attr( $star_cls ) . '">★</span>';
								}
								?>
								<span class="gd-current-job-hero__rating-score"><?php echo esc_html( number_format( $hj_rating, 1 ) ); ?></span>
								<?php if ( $hj_review_count ) : ?>
									<span class="gd-rating-display__count">(<?php echo esc_html( $hj_review_count ); ?>)</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
					<?php if ( $hj_msg_url ) : ?>
						<a href="<?php echo $hj_msg_url; ?>" class="gd-btn gd-btn--dark gd-btn--sm">
							<?php esc_html_e( 'View Mover', 'go-deliver' ); ?>
						</a>
					<?php endif; ?>
				</div><!-- /.gd-current-job-hero__mover-row -->
				<?php endif; ?>

			</div><!-- /.gd-current-job-hero__info -->
		</div><!-- /.gd-current-job-hero__body -->

		<div class="gd-current-job-hero__footer">
			<button type="button" class="gd-current-job-hero__view-link gd-job-view-btn" data-job-id="<?php echo esc_attr( $hj_id ); ?>">
				<?php esc_html_e( 'View Job Details', 'go-deliver' ); ?> &rsaquo;
			</button>
		</div>
	</div><!-- /.gd-current-job-hero -->
	<?php endif; // hero job ?>

	<?php if ( empty( $jobs ) ) : ?>
		<div class="gd-empty-state">
			<div class="gd-empty-state__icon">📦</div>
			<p class="gd-empty-state__text"><?php esc_html_e( 'You haven\'t posted any jobs yet.', 'go-deliver' ); ?></p>
			<?php if ( $job_form_url ) : ?>
				<a href="<?php echo esc_url( $job_form_url ); ?>" class="gd-btn gd-btn--cta" style="margin-top:12px;">
					+ <?php esc_html_e( 'Post Your First Job', 'go-deliver' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<?php foreach ( $jobs as $job ) :
			$job_id      = $job->ID;
			$status      = esc_attr( get_post_meta( $job_id, 'gd_job_status', true ) ?: 'open' );
			$status_labels = array(
				'open'      => __( 'New', 'go-deliver' ),
				'locked'    => __( 'Receiving Quotes', 'go-deliver' ),
				'accepted'  => __( 'Accepted', 'go-deliver' ),
				'expired'   => __( 'Expired', 'go-deliver' ),
'completed' => __( 'Completed', 'go-deliver' ),
				'cancelled' => __( 'Cancelled', 'go-deliver' ),
			);
			$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
			$pickup      = esc_html( get_post_meta( $job_id, 'gd_pickup_address', true ) ) ?: esc_html( get_post_meta( $job_id, 'gd_pickup_suburb', true ) );
			$dropoff     = esc_html( get_post_meta( $job_id, 'gd_dropoff_address', true ) ) ?: esc_html( get_post_meta( $job_id, 'gd_dropoff_suburb', true ) );
			$date_req    = esc_html( get_post_meta( $job_id, 'gd_date_requested', true ) );
			$quote_count = (int) get_post_meta( $job_id, 'gd_quote_count', true );
			$display_title = esc_html( Go_Deliver_Jobs::get_display_title( $job_id ) );
			$created     = esc_html( get_the_date( 'd M Y', $job_id ) );
			$created_at  = get_post_meta( $job_id, 'gd_created_at', true ) ?: get_post_field( 'post_date', $job_id );
			$time_since  = Go_Deliver_Jobs::time_since( (string) $created_at );
			$posted_utc  = $created_at ? strtotime( get_gmt_from_date( (string) $created_at ) ) : 0;

			// Expiry date for open/locked jobs.
		$expiry_label = '';
		if ( in_array( $status, array( 'open', 'locked' ), true ) ) {
			$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );
			$expiry_ts   = strtotime( get_post_field( 'post_date', $job_id ) ) + $expiry_days * DAY_IN_SECONDS;
			// Cap expiry at the job date: no point showing a listing-expiry date
			// that is later than the actual moving date.
			$raw_date_req = get_post_meta( $job_id, 'gd_date_requested', true );
			if ( $raw_date_req ) {
				$job_date_ts = strtotime( $raw_date_req );
				if ( $job_date_ts && $job_date_ts < $expiry_ts ) {
					$expiry_ts = $job_date_ts;
				}
			}
			$expiry_label = date_i18n( 'd M Y', $expiry_ts );
		}

			// Fetch accepted quote (if any).
			$accepted_quote_id  = get_post_meta( $job_id, 'gd_accepted_quote_id', true );
			$accepted_mover_id  = $accepted_quote_id ? (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true ) : 0;
			$accepted_mover     = $accepted_mover_id ? get_userdata( $accepted_mover_id ) : null;
			$review_submitted   = (bool) get_post_meta( $job_id, 'gd_review_submitted', true );
		?>
			<div class="gd-job-card gd-job-card--has-strip">
				<div class="gd-job-card__status-strip gd-job-card__status-strip--<?php echo esc_attr( $status ); ?>"></div>

				<div class="gd-job-card__header">
					<div class="gd-job-card__icon">🚚</div>
					<div class="gd-job-card__title">
						<h3 class="gd-job-card__type"><?php echo $display_title ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></h3>
						<p class="gd-job-card__meta">
							<?php esc_html_e( 'Posted', 'go-deliver' ); ?> <?php echo $created; ?>
							<?php if ( $time_since ) : ?>
								— <span class="gd-job-card__time-since"<?php echo $posted_utc ? ' data-gd-posted-utc="' . esc_attr( $posted_utc ) . '"' : ''; ?>><?php echo esc_html( $time_since ); ?></span>
							<?php endif; ?>
							<?php if ( $expiry_label ) : ?>
								(<?php printf( esc_html__( 'listing expires %s', 'go-deliver' ), esc_html( $expiry_label ) ); ?>)
							<?php endif; ?>
						</p>
					</div>
					<span class="gd-badge gd-badge--<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</div>

				<div class="gd-job-card__details">
					<div class="gd-job-card__detail">
						<div class="gd-job-card__detail-label"><?php esc_html_e( 'From', 'go-deliver' ); ?></div>
						<div class="gd-job-card__detail-value"><?php echo $pickup ?: '—'; ?></div>
					</div>
					<div class="gd-job-card__detail">
						<div class="gd-job-card__detail-label"><?php esc_html_e( 'To', 'go-deliver' ); ?></div>
						<div class="gd-job-card__detail-value"><?php echo $dropoff ?: '—'; ?></div>
					</div>
					<div class="gd-job-card__detail">
						<div class="gd-job-card__detail-label"><?php esc_html_e( 'Date', 'go-deliver' ); ?></div>
						<div class="gd-job-card__detail-value"><?php echo $date_req ?: '—'; ?></div>
					</div>
					<div class="gd-job-card__detail">
						<div class="gd-job-card__detail-label"><?php esc_html_e( 'Quotes', 'go-deliver' ); ?></div>
						<div class="gd-job-card__detail-value">
							<?php echo esc_html( $quote_count ); ?>
							<?php if ( 'open' === $status && $quote_count > 0 ) : ?>
								<span class="gd-badge gd-badge--open" style="margin-left:4px;"><?php esc_html_e( 'New!', 'go-deliver' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="gd-job-card__actions">
					<button
						type="button"
						class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn"
						data-job-id="<?php echo esc_attr( $job_id ); ?>"
					>
						<?php esc_html_e( 'View Details', 'go-deliver' ); ?>
					</button>

					<?php if ( $quote_count > 0 ) : ?>
						<button
							type="button"
							class="gd-btn gd-btn--primary gd-btn--sm gd-toggle-quotes-btn"
							data-job-id="<?php echo esc_attr( $job_id ); ?>"
						>
							<?php printf( esc_html__( 'View Quotes (%d)', 'go-deliver' ), $quote_count ); ?>
						</button>
					<?php endif; ?>

					<?php if ( in_array( $status, array( 'open', 'locked', 'accepted' ), true ) ) : ?>
						<button
							type="button"
							class="gd-btn gd-btn--danger gd-btn--sm gd-job-cancel-btn"
							data-job-id="<?php echo esc_attr( $job_id ); ?>"
							data-job-status="<?php echo esc_attr( $status ); ?>"
							<?php if ( 'accepted' === $status && $accepted_mover_id ) : ?>
							data-accepted-mover-id="<?php echo esc_attr( $accepted_mover_id ); ?>"
							data-accepted-mover-company="<?php echo esc_attr( get_user_meta( $accepted_mover_id, 'gd_company_name', true ) ?: ( $accepted_mover ? $accepted_mover->display_name : '' ) ); ?>"
							<?php endif; ?>
						>
							<?php esc_html_e( 'Cancel Job', 'go-deliver' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( 'completed' === $status && $accepted_mover && ! $review_submitted ) : ?>
						<button
							type="button"
							class="gd-btn gd-btn--outline gd-btn--sm"
							onclick="document.getElementById('gd-review-<?php echo esc_attr( $job_id ); ?>').scrollIntoView({behavior:'smooth'})"
						>
							⭐ <?php esc_html_e( 'Write Review', 'go-deliver' ); ?>
						</button>
					<?php endif; ?>
				</div>

				<!-- Inline quotes list (toggled by JS) -->
				<?php if ( $quote_count > 0 ) : ?>
				<div id="gd-quotes-<?php echo esc_attr( $job_id ); ?>" style="display:none;margin-top:16px;">
					<?php
					$quotes_query = new WP_Query( array(
						'post_type'      => 'gd_quote',
						'post_status'    => 'publish',
						'posts_per_page' => 20,
						'meta_query'     => array(
							array(
								'key'   => 'gd_job_id',
								'value' => $job_id,
								'type'  => 'NUMERIC',
							),
						),
						'orderby'        => 'date',
						'order'          => 'ASC',
						'no_found_rows'  => true,
					) );

					foreach ( $quotes_query->posts as $quote ) :
						$q_id        = $quote->ID;
						$q_status    = esc_attr( get_post_meta( $q_id, 'gd_status', true ) ?: 'pending' );
						$q_amount    = (float) get_post_meta( $q_id, 'gd_amount', true );
						$q_message   = esc_html( get_post_meta( $q_id, 'gd_message', true ) );
						$q_mover_id  = (int) get_post_meta( $q_id, 'gd_mover_id', true );
						$q_mover     = get_userdata( $q_mover_id );
						$q_mover_name = $q_mover ? esc_html( $q_mover->first_name ) : esc_html__( 'Mover', 'go-deliver' );
						$q_rating    = (float) get_user_meta( $q_mover_id, 'gd_average_rating', true );
						$is_accepted = ( 'accepted' === $q_status );
					?>
						<div class="gd-quote-card <?php echo $is_accepted ? 'gd-quote-card--accepted' : ''; ?>">
							<div class="gd-quote-card__header">
								<div class="gd-quote-card__mover">
									<div class="gd-quote-card__avatar">
										<?php echo esc_html( strtoupper( substr( $q_mover_name, 0, 1 ) ) ); ?>
									</div>
									<div>
										<div class="gd-quote-card__mover-name"><?php echo $q_mover_name; ?></div>
										<div class="gd-rating-display gd-quote-card__mover-meta">
											<?php
											$rating_int = (int) round( $q_rating );
											for ( $s = 1; $s <= 5; $s++ ) {
												$class = $s <= $rating_int ? 'gd-star gd-star--filled' : 'gd-star';
												echo '<span class="' . esc_attr( $class ) . '">★</span>';
											}
											?>
											<span class="gd-rating-display__count">
												(<?php echo esc_html( number_format( $q_rating, 1 ) ); ?>)
											</span>
										</div>
									</div>
								</div>
								<div>
									<div class="gd-quote-card__amount-label"><?php esc_html_e( 'Quote', 'go-deliver' ); ?></div>
									<div class="gd-quote-card__amount">$<?php echo esc_html( number_format( $q_amount, 0 ) ); ?></div>
									<span class="gd-badge gd-badge--<?php echo esc_attr( $q_status ); ?>">
										<?php echo esc_html( ucfirst( $q_status ) ); ?>
									</span>
								</div>
							</div>

							<?php if ( $q_message ) : ?>
								<div class="gd-quote-card__message"><?php echo $q_message; ?></div>
							<?php endif; ?>

							<?php if ( 'pending' === $q_status && in_array( $status, array( 'open', 'locked' ), true ) ) : ?>
								<div class="gd-quote-card__actions">
									<button
										type="button"
										class="gd-btn gd-btn--success gd-btn--sm gd-accept-quote-btn"
										data-quote-id="<?php echo esc_attr( $q_id ); ?>"
										data-job-id="<?php echo esc_attr( $job_id ); ?>"
									>
										✓ <?php esc_html_e( 'Accept Quote', 'go-deliver' ); ?>
									</button>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<?php wp_reset_postdata(); ?>
				</div>
				<?php endif; ?>

				<!-- Review section for completed jobs -->
				<?php if ( 'completed' === $status && $accepted_mover ) : ?>
					<div id="gd-review-<?php echo esc_attr( $job_id ); ?>" class="gd-review-section" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--gd-border);">
						<?php if ( $review_submitted ) : ?>
							<p class="gd-text-success">✓ <?php esc_html_e( 'Review submitted for this job.', 'go-deliver' ); ?></p>
						<?php else : ?>
							<h4 style="margin-bottom:12px;"><?php esc_html_e( 'Leave a Review', 'go-deliver' ); ?></h4>
							<form id="gd-review-form" class="gd-review-form">
								<?php wp_nonce_field( 'gd_submit_review', 'nonce' ); ?>
								<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>">
								<input type="hidden" name="mover_id" value="<?php echo esc_attr( $accepted_mover_id ); ?>">
								<input type="hidden" name="rating" value="">

								<div class="gd-field-group">
									<label><?php esc_html_e( 'Rating', 'go-deliver' ); ?> <span class="gd-required">*</span></label>
									<div class="gd-star-input" role="radiogroup">
										<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
											<input type="radio" id="star-<?php echo esc_attr( $job_id . '-' . $star ); ?>" name="star_rating_<?php echo esc_attr( $job_id ); ?>" value="<?php echo esc_attr( $star ); ?>">
											<label for="star-<?php echo esc_attr( $job_id . '-' . $star ); ?>" title="<?php echo esc_attr( $star ); ?> star">★</label>
										<?php endfor; ?>
									</div>
								</div>

								<div class="gd-field-group">
									<label for="gd-review-comment-<?php echo esc_attr( $job_id ); ?>"><?php esc_html_e( 'Comment (optional)', 'go-deliver' ); ?></label>
									<textarea
										id="gd-review-comment-<?php echo esc_attr( $job_id ); ?>"
										name="comment"
										rows="3"
										placeholder="<?php esc_attr_e( 'Share your experience with this mover…', 'go-deliver' ); ?>"
									></textarea>
								</div>

								<button type="submit" class="gd-btn gd-btn--primary gd-btn--sm">
									<?php esc_html_e( 'Submit Review', 'go-deliver' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div><!-- /.gd-job-card -->
		<?php endforeach; ?>

	<?php endif; ?>

	<!-- Container for inline job detail / AJAX loaded content -->
	<div id="gd-job-detail-container" style="margin-top:20px;"></div>

</div><!-- /#gd-panel-jobs -->

<!-- ========================================================
     Panel: My Profile
     ======================================================== -->
<div class="gd-panel" id="gd-panel-profile" style="display:none;">
<div class="gd-section-card">
<div class="gd-section-card__header">
<h2 class="gd-section-card__title"><?php esc_html_e( 'My Profile', 'go-deliver' ); ?></h2>
</div>
<div class="gd-section-card__body">

<form id="gd-customer-profile-form" novalidate>
<?php wp_nonce_field( 'gd_public_nonce', '_wpnonce', false ); ?>

<div class="gd-profile-section">
<h3 class="gd-profile-section__title"><?php esc_html_e( 'Personal Details', 'go-deliver' ); ?></h3>

<div class="gd-job-detail__grid">
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-cust-first-name">
<?php esc_html_e( 'First Name', 'go-deliver' ); ?>
</label>
<input
	type="text"
	id="gd-cust-first-name"
	name="first_name"
	class="gd-input"
	value="<?php echo esc_attr( $current_user->first_name ); ?>"
>
</div>

<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-cust-last-name">
<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
</label>
<input
	type="text"
	id="gd-cust-last-name"
	name="last_name"
	class="gd-input"
	value="<?php echo esc_attr( $current_user->last_name ); ?>"
>
</div>

<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-cust-email">
<?php esc_html_e( 'Email Address', 'go-deliver' ); ?>
</label>
<input
	type="email"
	id="gd-cust-email"
	name="email"
	class="gd-input"
	value="<?php echo esc_attr( $current_user->user_email ); ?>"
>
</div>

<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-cust-phone">
<?php esc_html_e( 'Phone Number', 'go-deliver' ); ?>
</label>
<input
	type="text"
	id="gd-cust-phone"
	name="phone"
	class="gd-input"
	value="<?php echo esc_attr( get_user_meta( $user_id, 'gd_phone', true ) ); ?>"
	placeholder="<?php esc_attr_e( 'e.g. 021 123 4567', 'go-deliver' ); ?>"
>
</div>
</div>
</div><!-- /.gd-profile-section -->

<div style="margin-top:16px;">
<button type="submit" id="gd-customer-profile-save-btn" class="gd-btn gd-btn--primary">
<?php esc_html_e( 'Save Changes', 'go-deliver' ); ?>
</button>
</div>

</form>

</div>
</div>
</div><!-- /#gd-panel-profile -->

</div><!-- /.gd-dashboard__main -->
</div><!-- /.gd-dashboard -->
</div><!-- /.gd-wrap -->

<!-- Modal: Cancel job – reason selection (shown for accepted jobs) -->
<div class="gd-modal-overlay" id="gd-cancel-reason-modal" role="dialog" aria-modal="true" aria-labelledby="gd-cancel-reason-title">
	<div class="gd-modal" style="max-width:460px;">
		<div class="gd-modal__header">
			<h3 class="gd-modal__title" id="gd-cancel-reason-title"><?php esc_html_e( 'Cancel Job', 'go-deliver' ); ?></h3>
			<button type="button" class="gd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'go-deliver' ); ?>">✕</button>
		</div>
		<div class="gd-modal__body">
			<p style="margin-bottom:16px;"><?php esc_html_e( 'Please let us know why you\'re cancelling:', 'go-deliver' ); ?></p>
			<div class="gd-radio-group">
				<label class="gd-radio-label">
					<input type="radio" name="gd_cancel_reason" value="no_longer_needed" checked>
					<?php esc_html_e( 'No longer needed', 'go-deliver' ); ?>
				</label>
				<label class="gd-radio-label">
					<input type="radio" name="gd_cancel_reason" value="mover_didnt_read">
					<?php esc_html_e( 'Mover didn\'t read the job clearly', 'go-deliver' ); ?>
				</label>
			</div>
		</div>
		<div class="gd-modal__footer">
			<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-modal__close">
				<?php esc_html_e( 'Keep Job', 'go-deliver' ); ?>
			</button>
			<button type="button" class="gd-btn gd-btn--danger gd-btn--sm" id="gd-cancel-reason-confirm">
				<?php esc_html_e( 'Confirm Cancellation', 'go-deliver' ); ?>
			</button>
		</div>
	</div>
</div><!-- /#gd-cancel-reason-modal -->

<!-- Modal: Re-post job (shown when cancellation reason is "mover didn't read") -->
<div class="gd-modal-overlay" id="gd-repost-job-modal" role="dialog" aria-modal="true" aria-labelledby="gd-repost-job-title">
	<div class="gd-modal" style="max-width:460px;">
		<div class="gd-modal__header">
			<h3 class="gd-modal__title" id="gd-repost-job-title"><?php esc_html_e( 'Re-post This Job?', 'go-deliver' ); ?></h3>
			<button type="button" class="gd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'go-deliver' ); ?>">✕</button>
		</div>
		<div class="gd-modal__body">
			<p style="margin-bottom:16px;"><?php esc_html_e( 'Would you like to re-post this job so other movers can quote on it?', 'go-deliver' ); ?></p>
			<label class="gd-checkbox-label" id="gd-repost-exclude-wrap" style="display:none;">
				<input type="checkbox" id="gd-repost-exclude-check">
				<span id="gd-repost-exclude-text"><?php esc_html_e( 'Exclude previous mover from seeing this job', 'go-deliver' ); ?></span>
			</label>
		</div>
		<div class="gd-modal__footer">
			<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-modal__close">
				<?php esc_html_e( 'No Thanks', 'go-deliver' ); ?>
			</button>
			<button type="button" class="gd-btn gd-btn--primary gd-btn--sm" id="gd-repost-job-confirm">
				<?php esc_html_e( 'Re-post Job', 'go-deliver' ); ?>
			</button>
		</div>
	</div>
</div><!-- /#gd-repost-job-modal -->

