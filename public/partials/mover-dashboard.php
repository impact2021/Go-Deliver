<?php
/**
 * Mover dashboard template.
 *
 * Shortcode: [gd_mover_dashboard]
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Auth check.
if ( ! is_user_logged_in() ) {
	echo '<div class="gd-wrap"><div class="gd-login-prompt">';
	echo '<div class="gd-login-prompt__icon">🔐</div>';
	echo '<h2 class="gd-login-prompt__title">' . esc_html__( 'Login Required', 'go-deliver' ) . '</h2>';
	echo '<p class="gd-login-prompt__text">' . esc_html__( 'Please log in to access your mover dashboard.', 'go-deliver' ) . '</p>';
	echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="gd-btn gd-btn--primary">' . esc_html__( 'Log In', 'go-deliver' ) . '</a>';
	echo '</div></div>';
	return;
}

$current_user = wp_get_current_user();
$is_mover     = in_array( 'gd_mover', (array) $current_user->roles, true );
$is_mover_sub = in_array( 'gd_mover_sub', (array) $current_user->roles, true );
$is_admin     = current_user_can( 'manage_options' );

if ( ! $is_mover && ! $is_mover_sub && ! $is_admin ) {
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">' .
	     esc_html__( 'This page is for registered movers only.', 'go-deliver' ) .
	     '</div></div></div>';
	return;
}

$mover_status = get_user_meta( get_current_user_id(), 'gd_mover_status', true );

// Handle non-approved statuses.
if ( 'pending' === $mover_status ) :
?>
<div class="gd-wrap">
	<div class="gd-status-message">
		<div class="gd-status-message__icon">⏳</div>
		<h2 class="gd-status-message__title"><?php esc_html_e( 'Application Under Review', 'go-deliver' ); ?></h2>
		<p class="gd-status-message__text">
			<?php esc_html_e( 'Thank you for registering! Your application is currently being reviewed by our team. You\'ll receive an email notification once your account has been approved, usually within 1–2 business days.', 'go-deliver' ); ?>
		</p>
	</div>
</div>
<?php return; endif;

if ( 'rejected' === $mover_status ) :
?>
<div class="gd-wrap">
	<div class="gd-status-message">
		<div class="gd-status-message__icon">❌</div>
		<h2 class="gd-status-message__title"><?php esc_html_e( 'Application Not Approved', 'go-deliver' ); ?></h2>
		<p class="gd-status-message__text">
			<?php esc_html_e( 'Unfortunately, your mover application was not approved. If you believe this is an error or would like more information, please contact our support team.', 'go-deliver' ); ?>
		</p>
		<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="gd-btn gd-btn--outline" style="margin-top:16px;">
			<?php esc_html_e( 'Contact Support', 'go-deliver' ); ?>
		</a>
	</div>
</div>
<?php return; endif;

if ( 'suspended' === $mover_status ) :
?>
<div class="gd-wrap">
	<div class="gd-status-message">
		<div class="gd-status-message__icon">🚫</div>
		<h2 class="gd-status-message__title"><?php esc_html_e( 'Account Suspended', 'go-deliver' ); ?></h2>
		<p class="gd-status-message__text">
			<?php esc_html_e( 'Your mover account has been suspended. Please contact support to resolve this issue.', 'go-deliver' ); ?>
		</p>
		<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="gd-btn gd-btn--outline" style="margin-top:16px;">
			<?php esc_html_e( 'Contact Support', 'go-deliver' ); ?>
		</a>
	</div>
</div>
<?php return; endif;

// Approved mover dashboard.
$user_id       = get_current_user_id();
$wallet        = new Go_Deliver_Wallet();
$balance       = $wallet->get_balance( $user_id );
$base_suburb   = esc_html( get_user_meta( $user_id, 'gd_mover_base_suburb', true ) );
$base_lat      = (float) get_user_meta( $user_id, 'gd_mover_base_lat', true );
$base_lng      = (float) get_user_meta( $user_id, 'gd_mover_base_lng', true );
$radius        = (int) get_user_meta( $user_id, 'gd_mover_radius', true );
$job_types     = (array) get_user_meta( $user_id, 'gd_mover_job_types', true );
$avg_rating    = (float) get_user_meta( $user_id, 'gd_average_rating', true );
$notification_frequency = get_user_meta( $user_id, 'gd_notification_frequency', true );
if ( ! in_array( $notification_frequency, Go_Deliver_Notifications::VALID_FREQUENCIES, true ) ) {
	$notification_frequency = 'instant';
}

// Fetch mover's submitted quotes.
$my_quotes_query = new WP_Query( array(
	'post_type'      => 'gd_quote',
	'post_status'    => 'publish',
	'posts_per_page' => 30,
	'meta_query'     => array(
		array(
			'key'   => 'gd_mover_id',
			'value' => $user_id,
			'type'  => 'NUMERIC',
		),
	),
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
) );
$my_quotes = $my_quotes_query->posts;
wp_reset_postdata();

// Fetch accepted quotes separately (for dedicated tab with full details).
$accepted_quotes_query = new WP_Query( array(
	'post_type'      => 'gd_quote',
	'post_status'    => 'publish',
	'posts_per_page' => 50,
	'meta_query'     => array(
		'relation' => 'AND',
		array(
			'key'   => 'gd_mover_id',
			'value' => $user_id,
			'type'  => 'NUMERIC',
		),
		array(
			'key'   => 'gd_status',
			'value' => 'accepted',
		),
	),
	'orderby'        => 'date',
	'order'          => 'DESC',
	'no_found_rows'  => true,
) );
$accepted_quotes = $accepted_quotes_query->posts;
wp_reset_postdata();

// Fetch dismissed jobs for the Dismissed Jobs tab.
$dismissed_job_ids = array_map( 'intval', (array) get_user_meta( $user_id, 'gd_dismissed_jobs', true ) );
$dismissed_jobs    = array();
if ( ! empty( $dismissed_job_ids ) ) {
	$jobs_handler = new Go_Deliver_Jobs();
	foreach ( $dismissed_job_ids as $djid ) {
		if ( ! $djid ) {
			continue;
		}
		$djdata = $jobs_handler->get_job( $djid );
		if ( ! is_wp_error( $djdata ) ) {
			$dismissed_jobs[] = $djdata;
		}
	}
}

$messaging_page_id  = (int) get_option( 'gd_messaging_page_id', 0 );
$messaging_base_url = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();

$fee_percentage = (float) get_option( 'gd_fee_percentage', 10 );
?>
<div class="gd-wrap" id="gd-mover-dashboard">

	<!-- Dashboard Header -->
	<div class="gd-dashboard-header">
		<h1 class="gd-dashboard-header__title">
			<?php
			printf(
				/* translators: %s: user first name */
				esc_html__( 'Mover Dashboard – %s', 'go-deliver' ),
				esc_html( $current_user->first_name ?: $current_user->display_name )
			);
			?>
		</h1>
		<div class="gd-rating-display" style="margin-top:4px;">
			<?php
			$r = (int) round( $avg_rating );
			for ( $s = 1; $s <= 5; $s++ ) {
				$cls = $s <= $r ? 'gd-star gd-star--filled' : 'gd-star';
				echo '<span class="' . esc_attr( $cls ) . '">★</span>';
			}
			?>
			<span class="gd-rating-display__count">
				(<?php echo esc_html( number_format( $avg_rating, 1 ) ); ?>)
			</span>
		</div>
	</div>

	<!-- Wallet Balance -->
	<div class="gd-wallet-section">
		<div class="gd-wallet-section__label"><?php esc_html_e( 'Wallet Balance', 'go-deliver' ); ?></div>
		<div class="gd-wallet-section__balance">
			<span class="gd-wallet-section__currency">$</span><?php echo esc_html( number_format( $balance, 2 ) ); ?>
		</div>
		<div class="gd-wallet-section__actions">
			<?php if ( get_option( 'gd_wallet_page_id' ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( get_option( 'gd_wallet_page_id' ) ) ); ?>" class="gd-btn gd-btn--topup">
					+ <?php esc_html_e( 'Top Up Wallet', 'go-deliver' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( $balance < 20 ) : ?>
		<div class="gd-alert gd-alert--warning" style="margin-bottom:16px;">
			<span class="gd-alert__icon">⚠️</span>
			<div class="gd-alert__body">
				<?php printf(
					/* translators: 1: current balance, 2: top-up URL */
					esc_html__( 'Your wallet balance is low ($%1$s). Top up to ensure you can accept quotes. A %2$s%% platform fee is charged on acceptance.', 'go-deliver' ),
					esc_html( number_format( $balance, 2 ) ),
					esc_html( $fee_percentage )
				); ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tabs -->
	<div class="gd-tabs" role="tablist">
		<div class="gd-tab gd-tab--active" data-tab="available-jobs" role="tab" tabindex="0">
			<?php esc_html_e( 'Available Jobs', 'go-deliver' ); ?>
		</div>
		<div class="gd-tab" data-tab="my-quotes" role="tab" tabindex="0">
			<?php esc_html_e( 'My Quotes', 'go-deliver' ); ?>
			<?php if ( ! empty( $my_quotes ) ) : ?>
				<span class="gd-badge gd-badge--open" style="margin-left:6px;"><?php echo esc_html( count( $my_quotes ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="gd-tab" data-tab="accepted-jobs" role="tab" tabindex="0">
			<?php esc_html_e( 'Accepted Jobs', 'go-deliver' ); ?>
			<?php if ( ! empty( $accepted_quotes ) ) : ?>
				<span class="gd-badge gd-badge--accepted" style="margin-left:6px;"><?php echo esc_html( count( $accepted_quotes ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="gd-tab" data-tab="dismissed-jobs" role="tab" tabindex="0">
			<?php esc_html_e( 'Dismissed Jobs', 'go-deliver' ); ?>
			<?php if ( ! empty( $dismissed_jobs ) ) : ?>
				<span class="gd-badge gd-badge--open" id="gd-dismissed-badge" style="margin-left:6px;"><?php echo esc_html( count( $dismissed_jobs ) ); ?></span>
			<?php endif; ?>
		</div>
		<div class="gd-tab" data-tab="profile" role="tab" tabindex="0">
			<?php esc_html_e( 'Profile', 'go-deliver' ); ?>
		</div>
		<?php if ( $is_mover ) : ?>
		<div class="gd-tab" data-tab="team" role="tab" tabindex="0">
			<?php esc_html_e( 'Team Members', 'go-deliver' ); ?>
		</div>
		<?php endif; ?>
	</div>

	<!-- Tab: Available Jobs -->
	<div class="gd-tab-panel gd-tab-panel--active" id="gd-tab-available-jobs" role="tabpanel">

		<!-- Filter bar -->
		<div class="gd-filter-bar">
			<span class="gd-filter-bar__label"><?php esc_html_e( 'Filter:', 'go-deliver' ); ?></span>
			<button type="button" class="gd-filter-chip gd-filter-chip--active" data-filter="">
				<?php esc_html_e( 'All Types', 'go-deliver' ); ?>
			</button>
			<?php
			$available_types = array(
				'trademe_pickup'  => __( 'Trademe Purchase Pickup', 'go-deliver' ),
				'item'            => __( 'Item', 'go-deliver' ),
				'move'            => __( 'Home or office move', 'go-deliver' ),
				'vehicle_or_boat' => __( 'Vehicle or boat', 'go-deliver' ),
				'pet'             => __( 'Pet', 'go-deliver' ),
				'junk'            => __( 'Junk', 'go-deliver' ),
				'other'           => __( 'Other', 'go-deliver' ),
			);
			foreach ( $available_types as $slug => $label ) :
			?>
				<button type="button" class="gd-filter-chip" data-filter="<?php echo esc_attr( $slug ); ?>">
					<?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- Job list (populated via AJAX) -->
		<div id="gd-available-jobs-list">
			<div class="gd-inline-spinner">
				<div class="gd-loading-spinner" style="width:32px;height:32px;border-color:var(--gd-border);border-top-color:var(--gd-primary);"></div>
			</div>
		</div>
	</div><!-- /#gd-tab-available-jobs -->

	<!-- Tab: My Quotes -->
	<div class="gd-tab-panel" id="gd-tab-my-quotes" role="tabpanel" style="display:none;">
		<?php if ( empty( $my_quotes ) ) : ?>
			<div class="gd-empty-state">
				<div class="gd-empty-state__icon">📝</div>
				<p class="gd-empty-state__text"><?php esc_html_e( "You haven't submitted any quotes yet.", 'go-deliver' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $my_quotes as $quote ) :
				$q_id       = $quote->ID;
				$q_status   = esc_attr( get_post_meta( $q_id, 'gd_status', true ) ?: 'pending' );
				$q_amount   = (float) get_post_meta( $q_id, 'gd_amount', true );
				$q_message  = esc_html( get_post_meta( $q_id, 'gd_message', true ) );
				$q_job_id   = (int) get_post_meta( $q_id, 'gd_job_id', true );
				$q_date     = esc_html( get_the_date( 'd M Y', $q_id ) );
				$job_suburb = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
				$job_type   = $q_job_id ? esc_html( Go_Deliver_Jobs::get_display_title( $q_job_id ) ) : '';
				$fee        = $q_amount > 0 ? ( $q_amount * $fee_percentage / 100 ) : 0;
			?>
				<div class="gd-mover-card">
					<div class="gd-mover-card__header">
						<div>
							<div class="gd-mover-card__job-type"><?php echo $job_type ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></div>
							<div class="gd-mover-card__suburb"><?php echo $job_suburb ?: '—'; ?></div>
						</div>
						<div style="text-align:right;">
							<div style="font-size:22px;font-weight:800;color:var(--gd-primary);">
								$<?php echo esc_html( number_format( $q_amount, 0 ) ); ?>
							</div>
							<span class="gd-badge gd-badge--<?php echo esc_attr( $q_status ); ?>">
								<?php echo esc_html( ucfirst( $q_status ) ); ?>
							</span>
						</div>
					</div>

					<?php if ( $q_message ) : ?>
						<p style="font-size:13px;color:var(--gd-text-muted);margin:8px 0;"><?php echo $q_message; ?></p>
					<?php endif; ?>

					<div class="gd-mover-card__info-grid">
						<div class="gd-mover-card__info-item">
							<div class="gd-mover-card__info-label"><?php esc_html_e( 'Quoted', 'go-deliver' ); ?></div>
							<div class="gd-mover-card__info-value"><?php echo $q_date; ?></div>
						</div>
						<?php if ( 'accepted' === $q_status ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Fee Charged', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value">
									$<?php echo esc_html( number_format( (float) get_post_meta( $q_id, 'gd_fee_amount', true ), 2 ) ); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<div class="gd-mover-card__actions">
						<?php if ( $q_job_id ) : ?>
							<button
								type="button"
								class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn"
								data-job-id="<?php echo esc_attr( $q_job_id ); ?>"
							>
								<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( 'pending' === $q_status ) : ?>
							<button
								type="button"
								class="gd-btn gd-btn--danger gd-btn--sm gd-withdraw-quote-btn"
								data-quote-id="<?php echo esc_attr( $q_id ); ?>"
							>
								<?php esc_html_e( 'Withdraw Quote', 'go-deliver' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div><!-- /#gd-tab-my-quotes -->

	<!-- Tab: Accepted Jobs -->
	<div class="gd-tab-panel" id="gd-tab-accepted-jobs" role="tabpanel" style="display:none;">
		<?php if ( empty( $accepted_quotes ) ) : ?>
			<div class="gd-empty-state">
				<div class="gd-empty-state__icon">✅</div>
				<p class="gd-empty-state__text"><?php esc_html_e( 'No accepted jobs yet.', 'go-deliver' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $accepted_quotes as $quote ) :
				$q_id        = $quote->ID;
				$q_amount    = (float) get_post_meta( $q_id, 'gd_amount', true );
				$q_fee       = (float) get_post_meta( $q_id, 'gd_fee_amount', true );
				$q_job_id    = (int) get_post_meta( $q_id, 'gd_job_id', true );
				$q_date      = esc_html( get_the_date( 'd M Y', $q_id ) );
				$q_job_status = $q_job_id ? get_post_meta( $q_job_id, 'gd_job_status', true ) : '';

				$raw_job_type    = $q_job_id ? Go_Deliver_Jobs::get_display_title( $q_job_id ) : '';
				$raw_pickup      = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_pickup_address', true ) ?: get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
				$raw_dropoff     = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_dropoff_address', true ) ?: get_post_meta( $q_job_id, 'gd_dropoff_suburb', true ) ) : '';
				$job_type        = esc_html( $raw_job_type );
				$pickup_full     = esc_html( $raw_pickup );
				$dropoff_full    = esc_html( $raw_dropoff );
				$date_req        = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_date_requested', true ) ) : '';

				$cust_id    = $q_job_id ? (int) get_post_meta( $q_job_id, 'gd_customer_id', true ) : 0;
				$cust_obj   = $cust_id ? get_userdata( $cust_id ) : null;
				$raw_name   = $cust_obj ? ( trim( $cust_obj->first_name . ' ' . $cust_obj->last_name ) ?: $cust_obj->display_name ) : '';
				$cust_name  = esc_html( $raw_name );
				$cust_phone = $cust_id ? esc_html( get_user_meta( $cust_id, 'gd_phone', true ) ) : '';

				$msg_url    = $q_job_id ? esc_url( add_query_arg( 'job_id', $q_job_id, $messaging_base_url ) ) : '';
			?>
				<div class="gd-mover-card">
					<div class="gd-mover-card__header">
						<div>
							<div class="gd-mover-card__job-type"><?php echo $job_type ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></div>
							<div class="gd-mover-card__suburb"><?php echo $pickup_full ?: '—'; ?></div>
						</div>
						<div style="text-align:right;">
							<div style="font-size:22px;font-weight:800;color:var(--gd-primary);">
								$<?php echo esc_html( number_format( $q_amount, 0 ) ); ?>
							</div>
							<?php if ( 'completed' === $q_job_status ) : ?>
								<span class="gd-badge gd-badge--accepted">✓ <?php esc_html_e( 'Completed', 'go-deliver' ); ?></span>
							<?php else : ?>
								<span class="gd-badge gd-badge--accepted"><?php esc_html_e( 'Accepted', 'go-deliver' ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="gd-mover-card__info-grid">
						<?php if ( $pickup_full ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Pickup', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value"><?php echo $pickup_full; ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $dropoff_full ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Dropoff', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value"><?php echo $dropoff_full; ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $date_req ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Date', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value"><?php echo $date_req; ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $cust_name ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Customer', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value"><?php echo $cust_name; ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $cust_phone ) : ?>
							<div class="gd-mover-card__info-item">
								<div class="gd-mover-card__info-label"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></div>
								<div class="gd-mover-card__info-value">
									<a href="tel:<?php echo esc_attr( $cust_phone ); ?>"><?php echo $cust_phone; ?></a>
								</div>
							</div>
						<?php endif; ?>
						<div class="gd-mover-card__info-item">
							<div class="gd-mover-card__info-label"><?php esc_html_e( 'Accepted', 'go-deliver' ); ?></div>
							<div class="gd-mover-card__info-value"><?php echo $q_date; ?></div>
						</div>
						<div class="gd-mover-card__info-item">
							<div class="gd-mover-card__info-label"><?php esc_html_e( 'Fee Charged', 'go-deliver' ); ?></div>
							<div class="gd-mover-card__info-value">$<?php echo esc_html( number_format( $q_fee, 2 ) ); ?></div>
						</div>
					</div>

					<div class="gd-mover-card__actions">
						<?php if ( $q_job_id ) : ?>
							<button
								type="button"
								class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn"
								data-job-id="<?php echo esc_attr( $q_job_id ); ?>"
							>
								<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
							</button>
						<?php endif; ?>
						<?php if ( $msg_url ) : ?>
							<a href="<?php echo $msg_url; ?>" class="gd-btn gd-btn--primary gd-btn--sm">
								💬 <?php esc_html_e( 'Open Messaging', 'go-deliver' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $q_job_id && 'accepted' === $q_job_status ) : ?>
							<button
								type="button"
								class="gd-btn gd-btn--success gd-btn--sm gd-complete-job-btn"
								data-job-id="<?php echo esc_attr( $q_job_id ); ?>"
							>
								✓ <?php esc_html_e( 'Mark as Complete', 'go-deliver' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div><!-- /#gd-tab-accepted-jobs -->

	<!-- Tab: Dismissed Jobs -->
	<div class="gd-tab-panel" id="gd-tab-dismissed-jobs" role="tabpanel" style="display:none;">
		<?php if ( empty( $dismissed_jobs ) ) : ?>
			<div class="gd-empty-state">
				<div class="gd-empty-state__icon">🚫</div>
				<p class="gd-empty-state__text"><?php esc_html_e( 'No dismissed jobs.', 'go-deliver' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$dj_type_labels    = Go_Deliver_Jobs::get_type_labels();
			$dj_all_ids        = array_column( $dismissed_jobs, 'id' );
			$dj_quote_stats    = Go_Deliver_Jobs::get_quote_stats_bulk( $dj_all_ids );
			$dj_empty_stats    = array( 'count' => 0, 'min' => null, 'max' => null );
			foreach ( $dismissed_jobs as $dj ) :
				$dj_pickup  = $dj['pickup_location'] ?? array();
				$dj_dropoff = $dj['dropoff_location'] ?? array();
				$dj_type    = $dj['job_type'] ?? '';
				$dj_title   = ! empty( $dj['listing_title'] )
					? esc_html( $dj['listing_title'] )
					: esc_html( $dj_type_labels[ $dj_type ] ?? ucwords( str_replace( '_', ' ', $dj_type ) ) );
				$dj_date    = ! empty( $dj['date_requested'] )
					? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $dj['date_requested'] ) ) )
					: '';
				$dj_from    = esc_html( $dj_pickup['suburb'] ?? $dj_pickup['address'] ?? __( 'Unknown', 'go-deliver' ) );
				$dj_to      = esc_html( $dj_dropoff['suburb'] ?? $dj_dropoff['address'] ?? __( 'Unknown', 'go-deliver' ) );
				$dj_stats   = $dj_quote_stats[ $dj['id'] ] ?? $dj_empty_stats;
				$dj_q_count = (int) $dj_stats['count'];
			?>
				<div class="gd-mover-card gd-dismissed-card" id="gd-dismissed-job-<?php echo esc_attr( $dj['id'] ); ?>">
					<div class="gd-mover-card__header">
						<div>
							<div class="gd-mover-card__job-type"><?php echo $dj_title; ?></div>
							<div class="gd-mover-card__suburb"><?php echo $dj_from; ?> → <?php echo $dj_to; ?></div>
						</div>
						<div style="text-align:right;">
							<?php if ( $dj_q_count > 0 ) : ?>
								<div style="font-size:13px;color:var(--gd-text-muted);">
									<?php
									printf(
										/* translators: %d: number of quotes */
										esc_html( _n( '%d quote', '%d quotes', $dj_q_count, 'go-deliver' ) ),
										$dj_q_count
									);
									if ( null !== $dj_stats['min'] ) {
										if ( $dj_stats['min'] === $dj_stats['max'] ) {
											echo ' · $' . esc_html( number_format( $dj_stats['min'], 0 ) );
										} else {
											echo ' · $' . esc_html( number_format( $dj_stats['min'], 0 ) ) . '–$' . esc_html( number_format( $dj_stats['max'], 0 ) );
										}
									}
									?>
								</div>
							<?php endif; ?>
							<?php if ( $dj_date ) : ?>
								<div style="font-size:12px;color:var(--gd-text-muted);margin-top:2px;"><?php echo $dj_date; ?></div>
							<?php endif; ?>
						</div>
					</div>
					<div class="gd-mover-card__actions">
						<button
							type="button"
							class="gd-btn gd-btn--outline gd-btn--sm gd-restore-job-btn"
							data-job-id="<?php echo esc_attr( $dj['id'] ); ?>"
						>
							<?php esc_html_e( 'Restore to Available Jobs', 'go-deliver' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div><!-- /#gd-tab-dismissed-jobs -->

	<!-- Tab: Profile -->
	<div class="gd-tab-panel" id="gd-tab-profile" role="tabpanel" style="display:none;">
		<div class="gd-section-card">
			<div class="gd-section-card__header">
				<h2 class="gd-section-card__title"><?php esc_html_e( 'My Profile', 'go-deliver' ); ?></h2>
			</div>
			<div class="gd-section-card__body">

				<form id="gd-mover-profile-form" novalidate>
					<?php wp_nonce_field( 'gd_public_nonce', '_wpnonce', false ); ?>

					<div class="gd-profile-section">
						<h3 class="gd-profile-section__title"><?php esc_html_e( 'Personal Details', 'go-deliver' ); ?></h3>

						<div class="gd-job-detail__grid">
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-first-name">
									<?php esc_html_e( 'First Name', 'go-deliver' ); ?>
								</label>
								<input
									type="text"
									id="gd-profile-first-name"
									name="first_name"
									class="gd-input"
									value="<?php echo esc_attr( $current_user->first_name ); ?>"
								>
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-last-name">
									<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
								</label>
								<input
									type="text"
									id="gd-profile-last-name"
									name="last_name"
									class="gd-input"
									value="<?php echo esc_attr( $current_user->last_name ); ?>"
								>
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-email">
									<?php esc_html_e( 'Email', 'go-deliver' ); ?>
								</label>
								<input
									type="email"
									id="gd-profile-email"
									name="email"
									class="gd-input"
									value="<?php echo esc_attr( $current_user->user_email ); ?>"
								>
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-phone">
									<?php esc_html_e( 'Phone', 'go-deliver' ); ?>
								</label>
								<input
									type="text"
									id="gd-profile-phone"
									name="phone"
									class="gd-input"
									value="<?php echo esc_attr( get_user_meta( $user_id, 'gd_phone', true ) ); ?>"
								>
							</div>
						</div>
					</div>

					<div class="gd-profile-section">
						<h3 class="gd-profile-section__title"><?php esc_html_e( 'Service Area', 'go-deliver' ); ?></h3>

						<div class="gd-job-detail__grid">
							<div class="gd-job-detail__field gd-location-field" style="grid-column:1/-1;">
								<label class="gd-job-detail__field-label" for="gd-profile-base-suburb">
									<?php esc_html_e( 'Your Address', 'go-deliver' ); ?>
								</label>
								<input
									type="text"
									id="gd-profile-base-suburb"
									name="base_suburb"
									class="gd-input gd-suburb-input"
									value="<?php echo esc_attr( $base_suburb ); ?>"
									placeholder="<?php esc_attr_e( 'Start typing your suburb…', 'go-deliver' ); ?>"
								>
								<input type="hidden" name="base_address" class="gd-address-input">
								<input type="hidden" id="gd-profile-base-lat" name="base_lat" class="gd-lat-input" value="<?php echo esc_attr( $base_lat ?: '' ); ?>">
								<input type="hidden" id="gd-profile-base-lng" name="base_lng" class="gd-lng-input" value="<?php echo esc_attr( $base_lng ?: '' ); ?>">
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-radius">
									<?php esc_html_e( 'Service Radius', 'go-deliver' ); ?>
								</label>
								<select id="gd-profile-radius" name="radius" class="gd-input">
									<option value=""><?php esc_html_e( '-- Select radius --', 'go-deliver' ); ?></option>
									<?php foreach ( array( 5, 10, 20, 50, 100, 200, 500 ) as $km ) : ?>
										<option value="<?php echo esc_attr( $km ); ?>" <?php selected( $radius, $km ); ?>>
											<?php printf( esc_html__( '%d km', 'go-deliver' ), $km ); ?>
										</option>
									<?php endforeach; ?>
									<option value="9999" <?php selected( $radius, 9999 ); ?>><?php esc_html_e( 'All of NZ', 'go-deliver' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<div class="gd-profile-section">
						<h3 class="gd-profile-section__title"><?php esc_html_e( 'Job Types', 'go-deliver' ); ?></h3>

						<div class="gd-checkbox-group">
							<?php
							$profile_job_types = array(
								'trademe_pickup'  => __( 'Trademe Purchase Pickup', 'go-deliver' ),
								'item'            => __( 'Item', 'go-deliver' ),
								'move'            => __( 'Home or office move', 'go-deliver' ),
								'vehicle_or_boat' => __( 'Vehicle or boat', 'go-deliver' ),
								'pet'             => __( 'Pet', 'go-deliver' ),
								'junk'            => __( 'Junk', 'go-deliver' ),
								'other'           => __( 'Other', 'go-deliver' ),
							);
							foreach ( $profile_job_types as $slug => $label ) :
							?>
								<label class="gd-checkbox-label">
									<input
										type="checkbox"
										name="job_types[]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $job_types, true ) ); ?>
									>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="gd-profile-section">
						<h3 class="gd-profile-section__title"><?php esc_html_e( 'Notification Preferences', 'go-deliver' ); ?></h3>

						<div class="gd-job-detail__grid">
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-profile-notification-frequency">
									<?php esc_html_e( 'New Job Emails', 'go-deliver' ); ?>
								</label>
								<select id="gd-profile-notification-frequency" name="notification_frequency" class="gd-input">
									<option value="instant" <?php selected( $notification_frequency, 'instant' ); ?>><?php esc_html_e( 'Immediately', 'go-deliver' ); ?></option>
									<option value="hourly" <?php selected( $notification_frequency, 'hourly' ); ?>><?php esc_html_e( 'Hourly Digest', 'go-deliver' ); ?></option>
									<option value="daily" <?php selected( $notification_frequency, 'daily' ); ?>><?php esc_html_e( 'Daily Digest', 'go-deliver' ); ?></option>
								</select>
								<p class="description" style="margin-top:4px;font-size:13px;color:var(--gd-text-muted);">
									<?php esc_html_e( 'How often you receive email notifications when new jobs matching your profile are posted.', 'go-deliver' ); ?>
								</p>
							</div>
						</div>
					</div>

					<div class="gd-profile-section">
						<h3 class="gd-profile-section__title"><?php esc_html_e( 'Account', 'go-deliver' ); ?></h3>
						<div class="gd-job-detail__grid">
							<div class="gd-job-detail__field">
								<div class="gd-job-detail__field-label"><?php esc_html_e( 'Status', 'go-deliver' ); ?></div>
								<div class="gd-job-detail__field-value">
									<span class="gd-badge gd-badge--approved"><?php esc_html_e( 'Approved', 'go-deliver' ); ?></span>
								</div>
							</div>
						</div>
					</div>

					<div style="margin-top:16px;">
						<button type="submit" id="gd-profile-save-btn" class="gd-btn gd-btn--primary">
							<?php esc_html_e( 'Save Changes', 'go-deliver' ); ?>
						</button>
					</div>

				</form>

			</div>
		</div>
	</div><!-- /#gd-tab-profile -->

	<?php if ( $is_mover ) :
		$sub_users_handler = new Go_Deliver_Sub_Users();
		$sub_user_list     = $sub_users_handler->get_sub_users( $user_id );
		$sub_user_count    = count( $sub_user_list );
		$can_add_more      = $sub_user_count < Go_Deliver_Sub_Users::MAX_SUB_USERS;
	?>
	<!-- Tab: Team Members -->
	<div class="gd-tab-panel" id="gd-tab-team" role="tabpanel" style="display:none;">
		<div class="gd-section-card">
			<div class="gd-section-card__header">
				<h2 class="gd-section-card__title"><?php esc_html_e( 'Team Members', 'go-deliver' ); ?></h2>
			</div>
			<div class="gd-section-card__body">

				<p style="margin-bottom:16px;color:var(--gd-text-muted);font-size:14px;">
					<?php printf(
						/* translators: 1: current count, 2: max count */
						esc_html__( 'You have %1$d of %2$d team member slots used. Team members share your wallet balance, job-posting ability, and messaging access.', 'go-deliver' ),
						$sub_user_count,
						Go_Deliver_Sub_Users::MAX_SUB_USERS
					); ?>
				</p>

				<?php if ( ! empty( $sub_user_list ) ) : ?>
					<div class="gd-team-list" style="margin-bottom:24px;">
						<?php foreach ( $sub_user_list as $su_row ) :
							$su_user   = get_userdata( (int) $su_row->user_id );
							if ( ! $su_user ) continue;
							$su_name   = trim( $su_user->first_name . ' ' . $su_user->last_name ) ?: $su_user->user_login;
						?>
							<div class="gd-mover-card" style="margin-bottom:8px;" id="gd-sub-user-<?php echo esc_attr( $su_row->user_id ); ?>">
								<div class="gd-mover-card__header">
									<div>
										<div class="gd-mover-card__job-type"><?php echo esc_html( $su_name ); ?></div>
										<div class="gd-mover-card__suburb"><?php echo esc_html( $su_user->user_email ); ?></div>
									</div>
									<div>
										<button
											type="button"
											class="gd-btn gd-btn--danger gd-btn--sm gd-remove-sub-user-btn"
											data-sub-user-id="<?php echo esc_attr( $su_row->user_id ); ?>"
										>
											<?php esc_html_e( 'Remove', 'go-deliver' ); ?>
										</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="gd-empty-state" style="padding:20px 0;">
						<div class="gd-empty-state__icon">👥</div>
						<p class="gd-empty-state__text"><?php esc_html_e( 'No team members yet.', 'go-deliver' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $can_add_more ) : ?>
					<h3 style="margin:8px 0 16px;font-size:16px;"><?php esc_html_e( 'Add Team Member', 'go-deliver' ); ?></h3>
					<form id="gd-add-sub-user-form" novalidate>
						<div id="gd-add-sub-user-msg" style="display:none;" class="gd-alert" role="alert"></div>

						<div class="gd-job-detail__grid">
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-su-first-name">
									<?php esc_html_e( 'First Name', 'go-deliver' ); ?> <span class="gd-required">*</span>
								</label>
								<input type="text" id="gd-su-first-name" name="first_name" class="gd-input" required>
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-su-last-name">
									<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
								</label>
								<input type="text" id="gd-su-last-name" name="last_name" class="gd-input">
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-su-username">
									<?php esc_html_e( 'Username', 'go-deliver' ); ?> <span class="gd-required">*</span>
								</label>
								<input type="text" id="gd-su-username" name="username" class="gd-input" required autocomplete="off">
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-su-email">
									<?php esc_html_e( 'Email', 'go-deliver' ); ?> <span class="gd-required">*</span>
								</label>
								<input type="email" id="gd-su-email" name="email" class="gd-input" required autocomplete="off">
							</div>
							<div class="gd-job-detail__field">
								<label class="gd-job-detail__field-label" for="gd-su-password">
									<?php esc_html_e( 'Password', 'go-deliver' ); ?> <span class="gd-required">*</span>
								</label>
								<input type="password" id="gd-su-password" name="password" class="gd-input" required autocomplete="new-password">
							</div>
						</div>

						<div style="margin-top:16px;">
							<button type="submit" class="gd-btn gd-btn--primary" id="gd-add-sub-user-btn">
								<?php esc_html_e( 'Add Team Member', 'go-deliver' ); ?>
							</button>
						</div>
					</form>
				<?php else : ?>
					<p class="gd-alert gd-alert--warning" style="margin-top:16px;">
						<?php printf(
							/* translators: %d: max sub-users */
							esc_html__( 'You have reached the maximum of %d team members.', 'go-deliver' ),
							Go_Deliver_Sub_Users::MAX_SUB_USERS
						); ?>
					</p>
				<?php endif; ?>

			</div>
		</div>
	</div><!-- /#gd-tab-team -->
	<?php endif; ?>

</div><!-- /.gd-wrap -->

<!-- Job detail modal -->
<div class="gd-modal-overlay" id="gd-job-modal-overlay" role="dialog" aria-modal="true">
	<div class="gd-modal">
		<div class="gd-modal__header">
			<h2 class="gd-modal__title"><?php esc_html_e( 'Job Details', 'go-deliver' ); ?></h2>
			<button type="button" class="gd-modal__close" aria-label="<?php esc_attr_e( 'Close', 'go-deliver' ); ?>">✕</button>
		</div>
		<div class="gd-modal__body">
			<!-- Loaded via AJAX -->
		</div>
	</div>
</div>
