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

if ( ! $is_mover && ! $is_mover_sub ) {
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
		<div class="gd-tab" data-tab="profile" role="tab" tabindex="0">
			<?php esc_html_e( 'Profile', 'go-deliver' ); ?>
		</div>
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
				'trademe_pickup' => __( 'TradeMe Purchase Pickup', 'go-deliver' ),
				'item'           => __( 'Item', 'go-deliver' ),
				'move'           => __( 'Move', 'go-deliver' ),
				'vehicle'        => __( 'Vehicle', 'go-deliver' ),
				'boat'           => __( 'Boat', 'go-deliver' ),
				'piano'          => __( 'Piano', 'go-deliver' ),
				'pet'            => __( 'Pet', 'go-deliver' ),
				'junk'           => __( 'Junk', 'go-deliver' ),
				'other'          => __( 'Other', 'go-deliver' ),
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
				$job_type   = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_job_type', true ) ?: get_post_meta( $q_job_id, 'gd_form_data_item_type', true ) ) : '';
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

	<!-- Tab: Profile -->
	<div class="gd-tab-panel" id="gd-tab-profile" role="tabpanel" style="display:none;">
		<div class="gd-section-card">
			<div class="gd-section-card__header">
				<h2 class="gd-section-card__title"><?php esc_html_e( 'My Profile', 'go-deliver' ); ?></h2>
			</div>
			<div class="gd-section-card__body">

				<div class="gd-profile-section">
					<h3 class="gd-profile-section__title"><?php esc_html_e( 'Service Area', 'go-deliver' ); ?></h3>
					<div class="gd-job-detail__grid">
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Base Location', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value"><?php echo $base_suburb ?: '—'; ?></div>
						</div>
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Service Radius', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value">
								<?php echo $radius ? esc_html( $radius ) . ' km' : '—'; ?>
							</div>
						</div>
						<?php if ( $base_lat && $base_lng ) : ?>
							<div class="gd-job-detail__field">
								<div class="gd-job-detail__field-label"><?php esc_html_e( 'Coordinates', 'go-deliver' ); ?></div>
								<div class="gd-job-detail__field-value">
									<?php echo esc_html( number_format( $base_lat, 5 ) . ', ' . number_format( $base_lng, 5 ) ); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="gd-profile-section">
					<h3 class="gd-profile-section__title"><?php esc_html_e( 'Job Types', 'go-deliver' ); ?></h3>
					<?php if ( ! empty( $job_types ) ) : ?>
						<div style="display:flex;flex-wrap:wrap;gap:8px;">
							<?php foreach ( $job_types as $jt ) : ?>
								<span class="gd-badge gd-badge--accepted"><?php echo esc_html( $jt ); ?></span>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="gd-text-muted"><?php esc_html_e( 'No job types specified.', 'go-deliver' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="gd-profile-section">
					<h3 class="gd-profile-section__title"><?php esc_html_e( 'Account', 'go-deliver' ); ?></h3>
					<div class="gd-job-detail__grid">
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Name', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value"><?php echo esc_html( $current_user->display_name ); ?></div>
						</div>
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Email', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value"><?php echo esc_html( $current_user->user_email ); ?></div>
						</div>
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value">
								<?php echo esc_html( get_user_meta( $user_id, 'gd_phone', true ) ?: '—' ); ?>
							</div>
						</div>
						<div class="gd-job-detail__field">
							<div class="gd-job-detail__field-label"><?php esc_html_e( 'Status', 'go-deliver' ); ?></div>
							<div class="gd-job-detail__field-value">
								<span class="gd-badge gd-badge--approved"><?php esc_html_e( 'Approved', 'go-deliver' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<p class="gd-text-muted gd-text-sm">
					<?php esc_html_e( 'To update your profile details, please contact support.', 'go-deliver' ); ?>
				</p>

			</div>
		</div>
	</div><!-- /#gd-tab-profile -->

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
