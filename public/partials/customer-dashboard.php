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
$job_form_page_id = (int) get_option( 'gd_job_form_page_id', 0 );
$job_form_url     = $job_form_page_id ? get_permalink( $job_form_page_id ) : '';
$help_centre_page_id = absint( get_option( 'gd_help_centre_page_id', 0 ) );
$help_centre_url     = $help_centre_page_id ? get_permalink( $help_centre_page_id ) : '';

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
$total_jobs      = count( $jobs );
$active_jobs     = 0;
$completed_jobs  = 0;
$pending_quotes  = 0;
$total_quotes    = 0;

foreach ( $jobs as $job ) {
	$status      = get_post_meta( $job->ID, 'gd_job_status', true );
	$quote_count = (int) get_post_meta( $job->ID, 'gd_quote_count', true );

	if ( in_array( $status, array( 'open', 'locked', 'accepted' ), true ) ) {
		$active_jobs++;
	}
	if ( 'completed' === $status ) {
		$completed_jobs++;
	}
	if ( 'open' === $status && 0 === $quote_count ) {
		$pending_quotes++;
	}
	$total_quotes += $quote_count;
}

// Unread messages count.
$unread_messages = Go_Deliver_DB::get_unread_message_count( $user_id );

// Conversations for messages panel.
$conversations = Go_Deliver_DB::get_conversations_for_user( $user_id );

// Messaging nonce for inline panel.
$messaging_nonce = wp_create_nonce( 'gd_messaging' );

// 3 most recent jobs for overview.
$recent_jobs = array_slice( $jobs, 0, 3 );

// Display name.
$display_first_name = $current_user->first_name ?: $current_user->display_name;
?>
<div class="gd-wrap" id="gd-customer-dashboard">
<div class="gd-dashboard">

<!-- ============================================================
     Sidebar
     ============================================================ -->
<aside class="gd-dashboard__sidebar">
<nav class="gd-sidebar-nav">

<a class="gd-sidebar-nav__item gd-sidebar-nav__item--active" data-panel="dashboard" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg></span>
<?php esc_html_e( 'Dashboard', 'go-deliver' ); ?>
</a>

<a class="gd-sidebar-nav__item" data-panel="jobs" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span>
<?php esc_html_e( 'My Jobs', 'go-deliver' ); ?>
<?php if ( $total_jobs ) : ?>
<span class="gd-badge gd-badge--open" style="margin-left:auto;"><?php echo esc_html( $total_jobs ); ?></span>
<?php endif; ?>
</a>

<a class="gd-sidebar-nav__item" data-panel="messages" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
<?php esc_html_e( 'Messages', 'go-deliver' ); ?>
<?php if ( $unread_messages > 0 ) : ?>
<span class="gd-badge gd-badge--unread" style="margin-left:auto;"><?php echo esc_html( $unread_messages ); ?></span>
<?php endif; ?>
</a>

<?php if ( $job_form_url ) : ?>
<a class="gd-sidebar-nav__item" href="<?php echo esc_url( $job_form_url ); ?>">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></span>
<?php esc_html_e( 'Post a Job', 'go-deliver' ); ?>
</a>
<?php endif; ?>

<a class="gd-sidebar-nav__item" data-panel="profile" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
<?php esc_html_e( 'Profile', 'go-deliver' ); ?>
</a>

<a class="gd-sidebar-nav__item" <?php if ( $help_centre_url ) : ?>href="<?php echo esc_url( $help_centre_url ); ?>"<?php else : ?>role="button" tabindex="0" style="opacity:.6;cursor:default;"<?php endif; ?>>
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
<?php esc_html_e( 'Help Centre', 'go-deliver' ); ?>
</a>

<a class="gd-sidebar-nav__item" data-panel="profile" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
<?php esc_html_e( 'Settings', 'go-deliver' ); ?>
</a>

</nav>
<?php if ( $job_form_url ) : ?>
<div class="gd-sidebar-nav__footer">
<a href="<?php echo esc_url( $job_form_url ); ?>" class="gd-btn gd-btn--primary gd-btn--cta-sidebar">
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
<?php esc_html_e( 'Post a New Job', 'go-deliver' ); ?>
</a>
</div>
<?php endif; ?>
</aside>

<!-- ============================================================
     Main content
     ============================================================ -->
<div class="gd-dashboard__main">

<!-- ========================================================
     Panel: Dashboard Overview (default)
     ======================================================== -->
<div class="gd-panel gd-panel--active" id="gd-panel-dashboard">

	<!-- Welcome hero -->
	<div class="gd-welcome-hero">
		<div class="gd-welcome-hero__text">
			<h1 class="gd-welcome-hero__title">
				<?php
				printf(
					/* translators: %s: user first name */
					esc_html__( 'Welcome back, %s!', 'go-deliver' ),
					esc_html( $display_first_name )
				);
				?>
			</h1>
			<p class="gd-welcome-hero__subtitle"><?php esc_html_e( "Here's what's happening with your jobs.", 'go-deliver' ); ?></p>
		</div>
		<div class="gd-welcome-hero__illustration" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 220 120" fill="none">
				<!-- City skyline -->
				<rect x="130" y="55" width="20" height="45" rx="2" fill="#dde8f5"/>
				<rect x="155" y="45" width="25" height="55" rx="2" fill="#c8d8ed"/>
				<rect x="185" y="60" width="18" height="40" rx="2" fill="#dde8f5"/>
				<rect x="205" y="65" width="15" height="35" rx="2" fill="#c8d8ed"/>
				<!-- Windows -->
				<rect x="133" y="58" width="4" height="4" rx="1" fill="#a0bcd8"/>
				<rect x="140" y="58" width="4" height="4" rx="1" fill="#a0bcd8"/>
				<rect x="133" y="66" width="4" height="4" rx="1" fill="#a0bcd8"/>
				<rect x="140" y="66" width="4" height="4" rx="1" fill="#a0bcd8"/>
				<rect x="158" y="50" width="5" height="5" rx="1" fill="#a0bcd8"/>
				<rect x="168" y="50" width="5" height="5" rx="1" fill="#a0bcd8"/>
				<rect x="158" y="60" width="5" height="5" rx="1" fill="#a0bcd8"/>
				<rect x="168" y="60" width="5" height="5" rx="1" fill="#a0bcd8"/>
				<!-- Road -->
				<rect x="0" y="100" width="220" height="20" rx="0" fill="#e8eef4"/>
				<rect x="60" y="107" width="20" height="3" rx="2" fill="#c5cdd8"/>
				<rect x="100" y="107" width="20" height="3" rx="2" fill="#c5cdd8"/>
				<!-- Van body -->
				<rect x="10" y="72" width="90" height="38" rx="6" fill="white" stroke="#d0d8e4" stroke-width="1.5"/>
				<!-- Van cab -->
				<path d="M85 72 L100 72 L110 82 L110 110 L85 110 Z" fill="white" stroke="#d0d8e4" stroke-width="1.5"/>
				<!-- Windscreen -->
				<path d="M87 76 L100 76 L108 84 L87 84 Z" fill="#dde8f5"/>
				<!-- Wheels -->
				<circle cx="35" cy="110" r="10" fill="#94a3b8"/>
				<circle cx="35" cy="110" r="5" fill="#cbd5e1"/>
				<circle cx="95" cy="110" r="10" fill="#94a3b8"/>
				<circle cx="95" cy="110" r="5" fill="#cbd5e1"/>
				<!-- Box on the ground -->
				<rect x="118" y="90" width="18" height="18" rx="2" fill="#fbbf24" stroke="#f59e0b" stroke-width="1"/>
				<line x1="127" y1="90" x2="127" y2="108" stroke="#f59e0b" stroke-width="1"/>
				<line x1="118" y1="99" x2="136" y2="99" stroke="#f59e0b" stroke-width="1"/>
			</svg>
		</div>
	</div>

	<!-- 4 Stat cards -->
	<div class="gd-stat-overview-bar">

		<div class="gd-stat-overview-card">
			<div class="gd-stat-overview-card__icon gd-stat-overview-card__icon--blue">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
			</div>
			<div class="gd-stat-overview-card__body">
				<div class="gd-stat-overview-card__value"><?php echo esc_html( $active_jobs ); ?></div>
				<div class="gd-stat-overview-card__label"><?php esc_html_e( 'Active Jobs', 'go-deliver' ); ?></div>
				<a class="gd-stat-overview-card__link gd-dashboard-switch-panel" data-panel="jobs"><?php esc_html_e( 'View all', 'go-deliver' ); ?></a>
			</div>
		</div>

		<div class="gd-stat-overview-card">
			<div class="gd-stat-overview-card__icon gd-stat-overview-card__icon--green">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
			</div>
			<div class="gd-stat-overview-card__body">
				<div class="gd-stat-overview-card__value"><?php echo esc_html( $completed_jobs ); ?></div>
				<div class="gd-stat-overview-card__label"><?php esc_html_e( 'Completed', 'go-deliver' ); ?></div>
				<a class="gd-stat-overview-card__link gd-dashboard-switch-panel" data-panel="jobs"><?php esc_html_e( 'View all', 'go-deliver' ); ?></a>
			</div>
		</div>

		<div class="gd-stat-overview-card">
			<div class="gd-stat-overview-card__icon gd-stat-overview-card__icon--orange">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
			</div>
			<div class="gd-stat-overview-card__body">
				<div class="gd-stat-overview-card__value"><?php echo esc_html( $pending_quotes ); ?></div>
				<div class="gd-stat-overview-card__label"><?php esc_html_e( 'Pending Quote', 'go-deliver' ); ?></div>
				<a class="gd-stat-overview-card__link gd-dashboard-switch-panel" data-panel="jobs"><?php esc_html_e( 'View all', 'go-deliver' ); ?></a>
			</div>
		</div>

		<div class="gd-stat-overview-card">
			<div class="gd-stat-overview-card__icon gd-stat-overview-card__icon--purple">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
			</div>
			<div class="gd-stat-overview-card__body">
				<div class="gd-stat-overview-card__value"><?php echo esc_html( $unread_messages ); ?></div>
				<div class="gd-stat-overview-card__label"><?php esc_html_e( 'Unread Messages', 'go-deliver' ); ?></div>
				<a class="gd-stat-overview-card__link gd-dashboard-switch-panel" data-panel="messages"><?php esc_html_e( 'View messages', 'go-deliver' ); ?></a>
			</div>
		</div>

	</div><!-- /.gd-stat-overview-bar -->

	<!-- Overview columns: Recent Jobs + Recent Messages / Need Help -->
	<div class="gd-overview-columns">

		<!-- Left: Recent Jobs -->
		<div class="gd-overview-recent-jobs">
			<div class="gd-overview-section-header">
				<h2 class="gd-overview-section-header__title"><?php esc_html_e( 'My Recent Jobs', 'go-deliver' ); ?></h2>
				<a class="gd-overview-section-header__link gd-dashboard-switch-panel" data-panel="jobs"><?php esc_html_e( 'View all jobs', 'go-deliver' ); ?></a>
			</div>

			<?php if ( empty( $recent_jobs ) ) : ?>
				<div class="gd-empty-state">
					<div class="gd-empty-state__icon">📦</div>
					<p class="gd-empty-state__text"><?php esc_html_e( 'No jobs yet.', 'go-deliver' ); ?></p>
					<?php if ( $job_form_url ) : ?>
						<a href="<?php echo esc_url( $job_form_url ); ?>" class="gd-btn gd-btn--primary gd-btn--sm" style="margin-top:10px;">+ <?php esc_html_e( 'Post a Job', 'go-deliver' ); ?></a>
					<?php endif; ?>
				</div>
			<?php else : ?>
			<?php foreach ( $recent_jobs as $rj ) :
				$rj_id          = $rj->ID;
				$rj_status      = get_post_meta( $rj_id, 'gd_job_status', true ) ?: 'open';
				$rj_quote_count = (int) get_post_meta( $rj_id, 'gd_quote_count', true );
				$rj_title       = esc_html( Go_Deliver_Jobs::get_display_title( $rj_id ) ) ?: esc_html__( 'Moving Job', 'go-deliver' );
				$rj_pickup      = esc_html( get_post_meta( $rj_id, 'gd_pickup_address', true ) ) ?: esc_html( get_post_meta( $rj_id, 'gd_pickup_suburb', true ) );
				$rj_dropoff     = esc_html( get_post_meta( $rj_id, 'gd_dropoff_address', true ) ) ?: esc_html( get_post_meta( $rj_id, 'gd_dropoff_suburb', true ) );
				$rj_date        = esc_html( get_post_meta( $rj_id, 'gd_date_requested', true ) );
				$rj_items_count = (int) get_post_meta( $rj_id, 'gd_item_count', true );
				$rj_job_type    = get_post_meta( $rj_id, 'gd_job_type', true );

				// Determine compact card status label and detail.
				if ( 'completed' === $rj_status ) {
					$rj_status_slug  = 'completed';
					$rj_status_label = __( 'Completed', 'go-deliver' );
					$rj_status_detail = __( 'Job Completed', 'go-deliver' );
				} elseif ( 'accepted' === $rj_status ) {
					$rj_status_slug  = 'active';
					$rj_status_label = __( 'Active', 'go-deliver' );
					$rj_status_detail = __( 'Mover Booked', 'go-deliver' );
				} elseif ( in_array( $rj_status, array( 'open', 'locked' ), true ) && $rj_quote_count > 0 ) {
					$rj_status_slug  = 'active';
					$rj_status_label = __( 'Active', 'go-deliver' );
					$rj_status_detail = sprintf( _n( '%d Quote Received', '%d Quotes Received', $rj_quote_count, 'go-deliver' ), $rj_quote_count );
				} elseif ( 'open' === $rj_status || 'locked' === $rj_status ) {
					$rj_status_slug  = 'pending';
					$rj_status_label = __( 'Pending Quotes', 'go-deliver' );
					$rj_status_detail = __( 'Waiting for movers', 'go-deliver' );
				} elseif ( 'cancelled' === $rj_status ) {
					$rj_status_slug  = 'cancelled';
					$rj_status_label = __( 'Cancelled', 'go-deliver' );
					$rj_status_detail = '';
				} else {
					$rj_status_slug  = esc_attr( $rj_status );
					$rj_status_label = ucfirst( $rj_status );
					$rj_status_detail = '';
				}

				// Pick icon type by job type.
				if ( in_array( $rj_job_type, array( 'move' ), true ) ) {
					$rj_icon_class = 'gd-compact-card__icon--orange';
					$rj_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
				} elseif ( in_array( $rj_job_type, array( 'car', 'vehicle', 'vehicle_or_boat', 'other_vehicle', 'motorcycle', 'boat' ), true ) ) {
					$rj_icon_class = 'gd-compact-card__icon--green';
					$rj_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
				} else {
					$rj_icon_class = 'gd-compact-card__icon--blue';
					$rj_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>';
				}
			?>
				<div class="gd-compact-card">
					<div class="gd-compact-card__icon <?php echo esc_attr( $rj_icon_class ); ?>"><?php echo $rj_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
					<div class="gd-compact-card__body">
						<div class="gd-compact-card__title"><?php echo $rj_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php if ( $rj_pickup || $rj_dropoff ) : ?>
						<div class="gd-compact-card__route">
							<?php if ( $rj_pickup ) : ?><span><?php echo $rj_pickup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php endif; ?>
							<?php if ( $rj_pickup && $rj_dropoff ) : ?><span class="gd-compact-card__route-arrow">→</span><?php endif; ?>
							<?php if ( $rj_dropoff ) : ?><span><?php echo $rj_dropoff; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="gd-compact-card__meta">
							<?php if ( $rj_date ) : ?>
							<span>📅 <?php echo $rj_date; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<?php endif; ?>
							<?php if ( $rj_items_count ) : ?>
							<span>&bull; <?php echo esc_html( $rj_items_count ); ?> <?php echo esc_html( _n( 'item', 'items', $rj_items_count, 'go-deliver' ) ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					<div class="gd-compact-card__status">
						<span class="gd-compact-card__badge gd-compact-card__badge--<?php echo esc_attr( $rj_status_slug ); ?>"><?php echo esc_html( $rj_status_label ); ?></span>
						<?php if ( $rj_status_detail ) : ?>
						<div class="gd-compact-card__detail"><?php echo esc_html( $rj_status_detail ); ?></div>
						<?php endif; ?>
					</div>
					<div class="gd-compact-card__action">
						<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-compact-view-job-btn" data-job-id="<?php echo esc_attr( $rj_id ); ?>">
							<?php esc_html_e( 'View Job', 'go-deliver' ); ?> ›
						</button>
					</div>
				</div>
			<?php endforeach; ?>
			<?php endif; ?>
		</div><!-- /.gd-overview-recent-jobs -->

		<!-- Right: Recent Messages + Need Help -->
		<div class="gd-overview-right">

			<!-- Recent Messages -->
			<div class="gd-section-card gd-recent-messages-card">
				<div class="gd-section-card__header">
					<h2 class="gd-section-card__title"><?php esc_html_e( 'Recent Messages', 'go-deliver' ); ?></h2>
					<?php if ( ! empty( $conversations ) ) : ?>
					<a class="gd-section-card__header-link gd-dashboard-switch-panel" data-panel="messages"><?php esc_html_e( 'View all', 'go-deliver' ); ?></a>
					<?php endif; ?>
				</div>
				<div class="gd-section-card__body" style="padding:0;">
				<?php if ( empty( $conversations ) ) : ?>
					<p class="gd-text-muted" style="padding:16px;font-size:14px;"><?php esc_html_e( 'No messages yet.', 'go-deliver' ); ?></p>
				<?php else : ?>
					<?php
					$recent_convos = array_slice( $conversations, 0, 3 );
					foreach ( $recent_convos as $convo ) :
						$convo_job_id      = (int) $convo->job_id;
						$convo_unread      = (int) $convo->unread_count;
						$convo_last_at     = $convo->last_message_at;
						$convo_job_title   = esc_html( Go_Deliver_Jobs::get_display_title( $convo_job_id ) ) ?: esc_html__( 'Moving Job', 'go-deliver' );

						// Determine the other party.
						$convo_customer_id = (int) get_post_meta( $convo_job_id, 'gd_customer_id', true );
						if ( $convo_customer_id === $user_id ) {
							// Customer: find the mover.
							$acc_qid       = (int) get_post_meta( $convo_job_id, 'gd_accepted_quote_id', true );
							$convo_mover_id = $acc_qid ? (int) get_post_meta( $acc_qid, 'gd_mover_id', true ) : 0;
							if ( ! $convo_mover_id ) {
								// Fall back to first quote mover.
								$first_q = new WP_Query( array(
									'post_type'      => 'gd_quote',
									'post_status'    => 'publish',
									'posts_per_page' => 1,
									'orderby'        => 'date',
									'order'          => 'ASC',
									'no_found_rows'  => true,
									'fields'         => 'ids',
									'meta_query'     => array(
										array( 'key' => 'gd_job_id', 'value' => $convo_job_id, 'type' => 'NUMERIC' ),
									),
								) );
								$convo_mover_id = ! empty( $first_q->posts ) ? (int) get_post_meta( $first_q->posts[0], 'gd_mover_id', true ) : 0;
								wp_reset_postdata();
							}
							$other_user    = $convo_mover_id ? get_userdata( $convo_mover_id ) : null;
							$other_name    = $convo_mover_id ? ( get_user_meta( $convo_mover_id, 'gd_company_name', true ) ?: ( $other_user ? $other_user->display_name : '' ) ) : $convo_job_title;
						} else {
							$other_user = get_userdata( $convo_customer_id );
							$other_name = $other_user ? $other_user->display_name : $convo_job_title;
						}

						// Last message snippet.
						global $wpdb;
						$last_msg_row = $wpdb->get_row( $wpdb->prepare(
							"SELECT message, sender_id FROM `{$wpdb->prefix}gd_messages`
							 WHERE job_id = %d AND (sender_id = %d OR receiver_id = %d)
							 ORDER BY created_at DESC LIMIT 1",
							$convo_job_id, $user_id, $user_id
						) );
						$last_snippet = $last_msg_row ? esc_html( wp_trim_words( $last_msg_row->message, 8, '…' ) ) : '';

						// Time ago.
						$convo_time_label = '';
						if ( $convo_last_at ) {
							$diff = current_time( 'timestamp' ) - strtotime( $convo_last_at );
							if ( $diff < 3600 ) {
								$convo_time_label = sprintf( _n( '%dm ago', '%dm ago', (int) round( $diff / 60 ), 'go-deliver' ), max( 1, (int) round( $diff / 60 ) ) );
							} elseif ( $diff < 86400 ) {
								$convo_time_label = sprintf( _n( '%dh ago', '%dh ago', (int) round( $diff / 3600 ), 'go-deliver' ), (int) round( $diff / 3600 ) );
							} else {
								$convo_time_label = sprintf( _n( '%dd ago', '%dd ago', (int) round( $diff / 86400 ), 'go-deliver' ), (int) round( $diff / 86400 ) );
							}
						}

						// Initials for avatar.
						$other_initials = strtoupper( substr( $other_name, 0, 2 ) );
					?>
					<a class="gd-recent-msg-item gd-dashboard-open-convo" data-job-id="<?php echo esc_attr( $convo_job_id ); ?>" data-other-name="<?php echo esc_attr( $other_name ); ?>" href="#" role="button">
						<div class="gd-recent-msg-item__avatar"><?php echo esc_html( $other_initials ); ?></div>
						<div class="gd-recent-msg-item__body">
							<div class="gd-recent-msg-item__name"><?php echo esc_html( $other_name ); ?></div>
							<?php if ( $last_snippet ) : ?>
							<div class="gd-recent-msg-item__snippet"><?php echo $last_snippet; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
							<?php endif; ?>
						</div>
						<div class="gd-recent-msg-item__meta">
							<?php if ( $convo_time_label ) : ?>
							<span class="gd-recent-msg-item__time"><?php echo esc_html( $convo_time_label ); ?></span>
							<?php endif; ?>
							<?php if ( $convo_unread > 0 ) : ?>
							<span class="gd-recent-msg-item__unread-dot" title="<?php echo esc_attr( $convo_unread ); ?> unread"></span>
							<?php endif; ?>
						</div>
					</a>
					<?php endforeach; ?>
				<?php endif; ?>
				</div>
			</div><!-- /.gd-recent-messages-card -->

			<!-- Need help? -->
			<div class="gd-section-card gd-need-help-card">
				<div class="gd-section-card__body">
					<h3 class="gd-need-help-card__title"><?php esc_html_e( 'Need help?', 'go-deliver' ); ?></h3>
					<p class="gd-need-help-card__text"><?php esc_html_e( 'Visit our Help Centre or contact our support team.', 'go-deliver' ); ?></p>
					<?php if ( $help_centre_url ) : ?>
					<a href="<?php echo esc_url( $help_centre_url ); ?>" class="gd-btn gd-btn--outline gd-btn--sm gd-btn--block" style="margin-bottom:10px;">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
						<?php esc_html_e( 'Visit Help Centre', 'go-deliver' ); ?>
					</a>
					<?php endif; ?>
					<a href="mailto:<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="gd-need-help-card__contact-link">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
						<?php esc_html_e( 'Contact Support', 'go-deliver' ); ?>
					</a>
				</div>
			</div><!-- /.gd-need-help-card -->

		</div><!-- /.gd-overview-right -->

	</div><!-- /.gd-overview-columns -->

</div><!-- /#gd-panel-dashboard -->

<!-- ========================================================
     Panel: My Jobs
     ======================================================== -->
<div class="gd-panel" id="gd-panel-jobs" style="display:none;">

	<!-- Dashboard Header -->
	<div class="gd-dashboard-header">
		<h1 class="gd-dashboard-header__title">
			<?php esc_html_e( 'My Jobs', 'go-deliver' ); ?>
		</h1>
		<p class="gd-dashboard-header__subtitle">
			<?php esc_html_e( 'Manage your moving jobs and view quotes from movers.', 'go-deliver' ); ?>
		</p>
	</div>

	<!-- Stats Bar (legacy, kept for jobs panel) -->
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
			<div class="gd-stat-card__value"><?php echo esc_html( $total_quotes ); ?></div>
			<div class="gd-stat-card__label"><?php esc_html_e( 'Total Quotes', 'go-deliver' ); ?></div>
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

		$hj_msg_job_id = $hj_id;
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
							<strong class="gd-current-job-hero__mover-name"><?php echo esc_html( $hj_company ); ?></strong>
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
					<button type="button" class="gd-btn gd-btn--dark gd-btn--sm gd-dashboard-open-convo" data-job-id="<?php echo esc_attr( $hj_msg_job_id ); ?>" data-other-name="<?php echo esc_attr( $hj_company ); ?>">
						<?php esc_html_e( 'Message Mover', 'go-deliver' ); ?>
					</button>
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

			// Assign solid SVG icon and colour class based on job type.
			$job_type_raw = get_post_meta( $job_id, 'gd_job_type', true );
			switch ( $job_type_raw ) {
				case 'move':
					$job_icon_color = 'gd-job-card__icon--orange';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>';
					break;
				case 'furniture':
					$job_icon_color = 'gd-job-card__icon--orange';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 9V7c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v2c-1.1 0-2 .9-2 2v5h1.33L3 18h1l.67-2h14.67l.66 2h1l-.33-2H22v-5c0-1.1-.9-2-2-2zm-10 0V7h8v5h-8V9zm-6 0h4v2H5V9zm-2 5v-2c0-.55.45-1 1-1h14c.55 0 1 .45 1 1v2H3z"/></svg>';
					break;
				case 'car':
					$job_icon_color = 'gd-job-card__icon--green';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99z"/></svg>';
					break;
				case 'motorcycle':
					$job_icon_color = 'gd-job-card__icon--green';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19 7c0-1.1-.9-2-2-2h-3v2h3v2.65L13.52 14H10V9H6C3.79 9 2 10.79 2 13s1.79 4 4 4c1.86 0 3.41-1.28 3.86-3h4.14L14 17.35V19h2v-2.35L19.5 12H21V7h-2zM6 15c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm11 0c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2z"/></svg>';
					break;
				case 'vehicle':
				case 'other_vehicle':
					$job_icon_color = 'gd-job-card__icon--green';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm13.5-9l1.96 2.5H17V9h2.5zM18 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg>';
					break;
				case 'boat':
					$job_icon_color = 'gd-job-card__icon--blue';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3L6 11h12zM4 12h16l-1.5 7h-13z"/></svg>';
					break;
				case 'piano':
					$job_icon_color = 'gd-job-card__icon--purple';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>';
					break;
				case 'pet':
					$job_icon_color = 'gd-job-card__icon--purple';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.5 11c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm3-4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm5 0c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm3 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zM12 13c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>';
					break;
				case 'junk':
					$job_icon_color = 'gd-job-card__icon--red';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>';
					break;
				case 'trademe_pickup':
					$job_icon_color = 'gd-job-card__icon--blue';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.41 11.58l-9-9C12.05 2.22 11.55 2 11 2H4c-1.1 0-2 .9-2 2v7c0 .55.22 1.05.59 1.42l9 9c.36.36.86.58 1.41.58s1.05-.22 1.41-.59l7-7c.37-.36.59-.86.59-1.41s-.23-1.06-.59-1.42zM5.5 7C4.67 7 4 6.33 4 5.5S4.67 4 5.5 4 7 4.67 7 5.5 6.33 7 5.5 7z"/></svg>';
					break;
				default: // item, item_packed, other
					$job_icon_color = 'gd-job-card__icon--blue';
					$job_icon_svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21 3H3v6h18V3zm-2 4H5V5h14v2zM3 19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2v-8H3v8zm2-6h14v4H5v-4z"/></svg>';
					break;
			}
		?>
			<div class="gd-job-card gd-job-card--has-strip">
				<div class="gd-job-card__status-strip gd-job-card__status-strip--<?php echo esc_attr( $status ); ?>"></div>

				<div class="gd-job-card__header">
					<div class="gd-job-card__icon <?php echo esc_attr( $job_icon_color ); ?>"><?php echo $job_icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
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
     Panel: Messages (inline – conversations + chat)
     ======================================================== -->
<div class="gd-panel" id="gd-panel-messages" style="display:none;">

	<!-- Conversations list -->
	<div id="gd-dashboard-conversations">
		<div class="gd-dashboard-header">
			<h1 class="gd-dashboard-header__title"><?php esc_html_e( 'Messages', 'go-deliver' ); ?></h1>
			<p class="gd-dashboard-header__subtitle"><?php esc_html_e( 'Your conversations with movers.', 'go-deliver' ); ?></p>
		</div>

		<?php if ( empty( $conversations ) ) : ?>
			<div class="gd-empty-state">
				<div class="gd-empty-state__icon">💬</div>
				<p class="gd-empty-state__text"><?php esc_html_e( 'No conversations yet. Messages appear here after you receive a quote on a job.', 'go-deliver' ); ?></p>
			</div>
		<?php else : ?>
		<div class="gd-conversations-list">
		<?php foreach ( $conversations as $convo ) :
			$convo_job_id      = (int) $convo->job_id;
			$convo_unread      = (int) $convo->unread_count;
			$convo_last_at     = $convo->last_message_at;
			$convo_job_title   = esc_html( Go_Deliver_Jobs::get_display_title( $convo_job_id ) ) ?: esc_html__( 'Moving Job', 'go-deliver' );

			// Determine the other party name.
			$convo_customer_id = (int) get_post_meta( $convo_job_id, 'gd_customer_id', true );
			if ( $convo_customer_id === $user_id ) {
				$acc_qid        = (int) get_post_meta( $convo_job_id, 'gd_accepted_quote_id', true );
				$convo_mover_id = $acc_qid ? (int) get_post_meta( $acc_qid, 'gd_mover_id', true ) : 0;
				if ( ! $convo_mover_id ) {
					$first_q = new WP_Query( array(
						'post_type'      => 'gd_quote',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'orderby'        => 'date',
						'order'          => 'ASC',
						'no_found_rows'  => true,
						'fields'         => 'ids',
						'meta_query'     => array(
							array( 'key' => 'gd_job_id', 'value' => $convo_job_id, 'type' => 'NUMERIC' ),
						),
					) );
					$convo_mover_id = ! empty( $first_q->posts ) ? (int) get_post_meta( $first_q->posts[0], 'gd_mover_id', true ) : 0;
					wp_reset_postdata();
				}
				$other_user_msg  = $convo_mover_id ? get_userdata( $convo_mover_id ) : null;
				$other_name_msg  = $convo_mover_id ? ( get_user_meta( $convo_mover_id, 'gd_company_name', true ) ?: ( $other_user_msg ? $other_user_msg->display_name : '' ) ) : $convo_job_title;
			} else {
				$other_user_msg = get_userdata( $convo_customer_id );
				$other_name_msg = $other_user_msg ? $other_user_msg->display_name : $convo_job_title;
			}

			// Last snippet.
			global $wpdb;
			$last_msg_row2   = $wpdb->get_row( $wpdb->prepare(
				"SELECT message FROM `{$wpdb->prefix}gd_messages`
				 WHERE job_id = %d AND (sender_id = %d OR receiver_id = %d)
				 ORDER BY created_at DESC LIMIT 1",
				$convo_job_id, $user_id, $user_id
			) );
			$last_snippet2 = $last_msg_row2 ? esc_html( wp_trim_words( $last_msg_row2->message, 12, '…' ) ) : '';

			// Time label.
			$convo_time_lbl = '';
			if ( $convo_last_at ) {
				$diff2 = current_time( 'timestamp' ) - strtotime( $convo_last_at );
				if ( $diff2 < 3600 ) {
					$convo_time_lbl = sprintf( '%dm ago', max( 1, (int) round( $diff2 / 60 ) ) );
				} elseif ( $diff2 < 86400 ) {
					$convo_time_lbl = sprintf( '%dh ago', (int) round( $diff2 / 3600 ) );
				} else {
					$convo_time_lbl = sprintf( '%dd ago', (int) round( $diff2 / 86400 ) );
				}
			}
			$convo_initials2 = strtoupper( substr( $other_name_msg, 0, 2 ) );
		?>
		<a class="gd-convo-item gd-dashboard-open-convo<?php echo $convo_unread > 0 ? ' gd-convo-item--unread' : ''; ?>"
		   data-job-id="<?php echo esc_attr( $convo_job_id ); ?>"
		   data-other-name="<?php echo esc_attr( $other_name_msg ); ?>"
		   href="#" role="button">
			<div class="gd-convo-item__avatar"><?php echo esc_html( $convo_initials2 ); ?></div>
			<div class="gd-convo-item__body">
				<div class="gd-convo-item__name"><?php echo esc_html( $other_name_msg ); ?></div>
				<div class="gd-convo-item__job"><?php echo $convo_job_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php if ( $last_snippet2 ) : ?>
				<div class="gd-convo-item__snippet"><?php echo $last_snippet2; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>
			</div>
			<div class="gd-convo-item__meta">
				<?php if ( $convo_time_lbl ) : ?>
				<span class="gd-convo-item__time"><?php echo esc_html( $convo_time_lbl ); ?></span>
				<?php endif; ?>
				<?php if ( $convo_unread > 0 ) : ?>
				<span class="gd-badge gd-badge--unread gd-convo-item__unread-badge"><?php echo esc_html( $convo_unread ); ?></span>
				<?php endif; ?>
			</div>
		</a>
		<?php endforeach; ?>
		</div><!-- /.gd-conversations-list -->
		<?php endif; ?>
	</div><!-- /#gd-dashboard-conversations -->

	<!-- Inline messaging panel (populated by JS when a conversation is selected) -->
	<div id="gd-dashboard-messaging-wrap"
	     style="display:none;"
	     data-nonce="<?php echo esc_attr( $messaging_nonce ); ?>">
	</div><!-- /#gd-dashboard-messaging-wrap -->

</div><!-- /#gd-panel-messages -->

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

