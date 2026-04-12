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

// Fetch customer's jobs.
$jobs_query = new WP_Query( array(
	'post_type'      => 'gd_job',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'meta_query'     => array(
		array(
			'key'   => 'gd_customer_id',
			'value' => get_current_user_id(),
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

	<!-- Action Button -->
	<div style="margin-bottom:20px;">
		<?php if ( get_option( 'gd_job_form_page_id' ) ) : ?>
			<a href="<?php echo esc_url( get_permalink( get_option( 'gd_job_form_page_id' ) ) ); ?>" class="gd-btn gd-btn--primary">
				+ <?php esc_html_e( 'Post New Job', 'go-deliver' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<!-- Tabs -->
	<div class="gd-tabs" role="tablist">
		<div class="gd-tab gd-tab--active" data-tab="jobs" role="tab" tabindex="0">
			<?php esc_html_e( 'My Jobs', 'go-deliver' ); ?>
			<?php if ( $total_jobs ) : ?>
				<span class="gd-badge gd-badge--open" style="margin-left:6px;"><?php echo esc_html( $total_jobs ); ?></span>
			<?php endif; ?>
		</div>
		<div class="gd-tab" data-tab="messages" role="tab" tabindex="0">
			<?php esc_html_e( 'Messages', 'go-deliver' ); ?>
		</div>
	</div>

	<!-- Tab: My Jobs -->
	<div class="gd-tab-panel gd-tab-panel--active" id="gd-tab-jobs" role="tabpanel">

		<?php if ( empty( $jobs ) ) : ?>
			<div class="gd-empty-state">
				<div class="gd-empty-state__icon">📦</div>
				<p class="gd-empty-state__text"><?php esc_html_e( 'You haven\'t posted any jobs yet.', 'go-deliver' ); ?></p>
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

				// Expiry date for open/locked jobs.
				$expiry_label = '';
				if ( in_array( $status, array( 'open', 'locked' ), true ) ) {
					$expiry_days  = (int) get_option( 'gd_job_expiry_days', 14 );
					$expiry_ts    = strtotime( get_post_field( 'post_date', $job_id ) ) + $expiry_days * DAY_IN_SECONDS;
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

						<?php if ( 'open' === $status ) : ?>
							<button
								type="button"
								class="gd-btn gd-btn--danger gd-btn--sm gd-job-cancel-btn"
								data-job-id="<?php echo esc_attr( $job_id ); ?>"
							>
								<?php esc_html_e( 'Cancel Job', 'go-deliver' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( in_array( $status, array( 'accepted', 'completed' ), true ) && $accepted_mover && ! $review_submitted ) : ?>
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
					<?php if ( in_array( $status, array( 'accepted', 'completed' ), true ) && $accepted_mover ) : ?>
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
	</div><!-- /#gd-tab-jobs -->

	<!-- Tab: Messages -->
	<div class="gd-tab-panel" id="gd-tab-messages" role="tabpanel" style="display:none;">
		<p class="gd-text-muted">
			<?php esc_html_e( 'Messages are available for jobs that have received quotes. Select a job above to view or start a conversation.', 'go-deliver' ); ?>
		</p>
	</div>

	<!-- Container for inline job detail / AJAX loaded content -->
	<div id="gd-job-detail-container" style="margin-top:20px;"></div>

</div><!-- /.gd-wrap -->
