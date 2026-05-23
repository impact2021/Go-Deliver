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
$can_post_jobs = ! $is_mover && ! $is_mover_sub;

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
		<a href="mailto:<?php echo esc_attr( gd_get_admin_email() ); ?>" class="gd-btn gd-btn--outline" style="margin-top:16px;">
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
		<a href="mailto:<?php echo esc_attr( gd_get_admin_email() ); ?>" class="gd-btn gd-btn--outline" style="margin-top:16px;">
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

// Extended profile meta.
$company_name    = esc_html( get_user_meta( $user_id, 'gd_company_name', true ) );
$display_name    = $company_name ?: esc_html( $current_user->first_name ?: $current_user->display_name );
$bio             = get_user_meta( $user_id, 'gd_bio', true );

// Unified photo gallery (gd_mover_photos), with legacy fallback.
$raw_mover_photos  = get_user_meta( $user_id, 'gd_mover_photos', true );
$mover_photo_ids   = is_string( $raw_mover_photos ) ? (array) json_decode( $raw_mover_photos, true ) : array();
$mover_photo_ids   = array_values( array_filter( array_map( 'absint', $mover_photo_ids ) ) );

// Profile photo: first gallery image, else legacy gd_profile_photo_id.
$profile_photo_id  = ! empty( $mover_photo_ids ) ? $mover_photo_ids[0] : (int) get_user_meta( $user_id, 'gd_profile_photo_id', true );

// Fleet photos for display: photos 2–4 from gallery, else legacy gd_fleet_photos.
if ( count( $mover_photo_ids ) > 1 ) {
	$fleet_photo_ids = array_slice( $mover_photo_ids, 1, 3 );
} else {
	$raw_fleet       = get_user_meta( $user_id, 'gd_fleet_photos', true );
	$fleet_photo_ids = is_array( json_decode( $raw_fleet, true ) ) ? json_decode( $raw_fleet, true ) : array();
}
$review_count    = (int) get_user_meta( $user_id, 'gd_review_count', true );

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

// Compute stats and recent past jobs from accepted quotes.
// Separate into active (job accepted), completed, and cancelled buckets.
$active_accepted_quotes   = array();
$completed_job_quotes     = array();
$cancelled_job_quotes     = array();
$completed_count          = 0;
$recent_past_jobs         = array();
foreach ( $accepted_quotes as $_aq ) {
	$_aq_jid   = (int) get_post_meta( $_aq->ID, 'gd_job_id', true );
	$_aq_jstat = $_aq_jid ? get_post_meta( $_aq_jid, 'gd_job_status', true ) : '';
	if ( 'completed' === $_aq_jstat ) {
		$completed_job_quotes[] = $_aq;
		$completed_count++;
		if ( count( $recent_past_jobs ) < 3 ) {
			$recent_past_jobs[] = array(
				'job_id'   => $_aq_jid,
				'amount'   => (float) get_post_meta( $_aq->ID, 'gd_amount', true ),
				'job_type' => esc_html( Go_Deliver_Jobs::get_display_title( $_aq_jid ) ),
				'pickup'   => esc_html( get_post_meta( $_aq_jid, 'gd_pickup_suburb', true ) ),
				'dropoff'  => esc_html( get_post_meta( $_aq_jid, 'gd_dropoff_suburb', true ) ),
				'date'     => esc_html( get_the_date( 'd M Y', $_aq->ID ) ),
			);
		}
	} elseif ( 'cancelled' === $_aq_jstat ) {
		$cancelled_job_quotes[] = $_aq;
	} else {
		// 'accepted' or any unexpected status – treat as still-active.
		$active_accepted_quotes[] = $_aq;
	}
}
$quoted_count = count( $my_quotes );

// Fetch dismissed jobs for the My Jobs panel.
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

// Fetch reviews.
$reviews_handler  = new Go_Deliver_Reviews();
$overview_reviews = $reviews_handler->get_mover_reviews( $user_id, 5 );
$all_reviews      = $reviews_handler->get_mover_reviews( $user_id, 30 );

// Wallet transactions.
$transactions = Go_Deliver_DB::get_transactions( $user_id, 5 );

// Page links.
$messaging_page_id  = (int) get_option( 'gd_messaging_page_id', 0 );
$messaging_base_url = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();
$job_form_page_id   = (int) get_option( 'gd_job_form_page_id', 0 );
$job_form_url       = $job_form_page_id ? get_permalink( $job_form_page_id ) : home_url();
$wallet_url         = get_option( 'gd_wallet_page_id' ) ? get_permalink( get_option( 'gd_wallet_page_id' ) ) : '#';
$fee_percentage     = (float) get_option( 'gd_fee_percentage', 10 );

// Profile photo URL.
$profile_photo_url = $profile_photo_id ? wp_get_attachment_image_url( $profile_photo_id, 'thumbnail' ) : '';
?>
<div class="gd-wrap" id="gd-mover-dashboard">
<div class="gd-dashboard">

<!-- ============================================================
     Sidebar
     ============================================================ -->
<aside class="gd-dashboard__sidebar">
<nav class="gd-sidebar-nav">
<a class="gd-sidebar-nav__item gd-sidebar-nav__item--active" data-panel="overview" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span> <?php esc_html_e( 'Dashboard', 'go-deliver' ); ?>
</a>
<a class="gd-sidebar-nav__item" data-panel="browse-jobs" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span> <?php esc_html_e( 'Browse Jobs', 'go-deliver' ); ?>
</a>
<a class="gd-sidebar-nav__item" data-panel="my-jobs" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg></span> <?php esc_html_e( 'My Jobs', 'go-deliver' ); ?>
<?php if ( ! empty( $active_accepted_quotes ) ) : ?>
<span class="gd-badge gd-badge--accepted" style="margin-left:auto;"><?php echo esc_html( count( $active_accepted_quotes ) ); ?></span>
<?php endif; ?>
</a>
<a class="gd-sidebar-nav__item" href="<?php echo esc_url( $messaging_base_url ); ?>">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span> <?php esc_html_e( 'Messages', 'go-deliver' ); ?>
</a>
<a class="gd-sidebar-nav__item" data-panel="reviews" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></span> <?php esc_html_e( 'Reviews', 'go-deliver' ); ?>
<?php if ( $review_count ) : ?>
<span class="gd-badge" style="margin-left:auto;background:var(--gd-primary-light);color:var(--gd-primary);"><?php echo esc_html( $review_count ); ?></span>
<?php endif; ?>
</a>
<a class="gd-sidebar-nav__item" data-panel="profile" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span> <?php esc_html_e( 'Settings', 'go-deliver' ); ?>
</a>
<?php if ( $is_mover ) : ?>
<a class="gd-sidebar-nav__item" data-panel="team" role="button" tabindex="0">
<span class="gd-sidebar-nav__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span> <?php esc_html_e( 'Team Members', 'go-deliver' ); ?>
</a>
<?php endif; ?>
</nav>
<?php if ( $job_form_url && '#' !== $job_form_url && $can_post_jobs ) : ?>
<div class="gd-sidebar-nav__footer">
<a href="<?php echo esc_url( $job_form_url ); ?>" class="gd-btn gd-btn--cta" style="width:100%;justify-content:center;">
<?php esc_html_e( 'Post a Job', 'go-deliver' ); ?>
</a>
</div>
<?php endif; ?>
</aside>

<!-- ============================================================
     Main content
     ============================================================ -->
<div class="gd-dashboard__main">

<!-- ========================================================
     Panel: Overview (default)
     ======================================================== -->
<div class="gd-panel gd-panel--active" id="gd-panel-overview">

<!-- Welcome -->
<div class="gd-dashboard-header">
<h1 class="gd-dashboard-header__title">
<?php
printf(
/* translators: %s: display / company name */
esc_html__( 'Welcome, %s!', 'go-deliver' ),
esc_html( $display_name )
);
?>
</h1>
<p class="gd-dashboard-header__subtitle">
<?php esc_html_e( 'Manage your jobs, earnings, reviews, and profile all in one place.', 'go-deliver' ); ?>
</p>
</div>

<!-- Profile card -->
<div class="gd-profile-card">
<div class="gd-profile-card__top">
<div class="gd-profile-card__top-left">
<div class="gd-profile-card__avatar">
<?php if ( $profile_photo_url ) : ?>
<img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>">
<?php else : ?>
<div class="gd-profile-card__avatar-placeholder"><?php echo esc_html( mb_substr( $display_name, 0, 1 ) ); ?></div>
<?php endif; ?>
</div>
<div class="gd-profile-card__info">
<h2 class="gd-profile-card__name"><?php echo esc_html( $display_name ); ?></h2>
<div class="gd-profile-card__meta">
<?php
$_r = (int) round( $avg_rating );
for ( $_s = 1; $_s <= 5; $_s++ ) {
$_cls = $_s <= $_r ? 'gd-star gd-star--filled' : 'gd-star';
echo '<span class="' . esc_attr( $_cls ) . '">★</span>';
}
if ( $avg_rating ) {
echo '<span class="gd-profile-card__rating-num">' . esc_html( number_format( $avg_rating, 1 ) ) . '</span>';
}
if ( $base_suburb ) {
echo '<span class="gd-profile-card__location"> · ' . esc_html__( 'Based in', 'go-deliver' ) . ' ' . esc_html( $base_suburb ) . '</span>';
}
?>
</div>
</div>
</div>
<button type="button" id="gd-edit-profile-btn" class="gd-btn gd-btn--outline gd-btn--sm">
✏️ <?php esc_html_e( 'Edit Profile', 'go-deliver' ); ?>
</button>
</div>

<!-- Fleet photos (display only – manage via Settings > My Photos) -->
<div class="gd-fleet-photos">
<?php for ( $_fi = 0; $_fi < 3; $_fi++ ) :
$_fid  = isset( $fleet_photo_ids[ $_fi ] ) ? (int) $fleet_photo_ids[ $_fi ] : 0;
$_furl = $_fid ? wp_get_attachment_image_url( $_fid, 'medium' ) : '';
?>
<div class="gd-fleet-photos__slot">
<?php if ( $_furl ) : ?>
<img src="<?php echo esc_url( $_furl ); ?>" alt="<?php esc_attr_e( 'Fleet photo', 'go-deliver' ); ?>">
<?php else : ?>
<div class="gd-fleet-photos__empty">📷</div>
<?php endif; ?>
</div>
<?php endfor; ?>
</div>

<!-- Stats row -->
<div class="gd-profile-stats">
<div class="gd-profile-stat">
<span class="gd-profile-stat__value"><?php echo esc_html( $completed_count ); ?></span>
<span class="gd-profile-stat__label"><?php esc_html_e( 'Completed', 'go-deliver' ); ?></span>
</div>
<div class="gd-profile-stat">
<span class="gd-profile-stat__value"><?php echo esc_html( $quoted_count ); ?></span>
<span class="gd-profile-stat__label"><?php esc_html_e( 'Quoted', 'go-deliver' ); ?></span>
</div>
<div class="gd-profile-stat">
<span class="gd-profile-stat__value"><?php echo esc_html( $review_count ); ?></span>
<span class="gd-profile-stat__label"><?php esc_html_e( 'Reviews', 'go-deliver' ); ?></span>
</div>
<div class="gd-profile-stat">
<span class="gd-profile-stat__value"><?php echo esc_html( count( $active_accepted_quotes ) ); ?></span>
<span class="gd-profile-stat__label"><?php esc_html_e( 'Active Jobs', 'go-deliver' ); ?></span>
</div>
</div>
</div><!-- /.gd-profile-card -->

<!-- Two-column overview layout -->
<div class="gd-overview-cols">

<!-- Left column: public profile -->
<div class="gd-overview-cols__main">

<!-- About -->
<div class="gd-section-card" style="margin-bottom:20px;">
<div class="gd-about-block">
<div class="gd-about-block__avatar">
<?php if ( $profile_photo_url ) : ?>
<img src="<?php echo esc_url( $profile_photo_url ); ?>" alt="<?php echo esc_attr( $display_name ); ?>">
<?php else : ?>
<div class="gd-about-block__avatar-placeholder"><?php echo esc_html( mb_substr( $display_name, 0, 1 ) ); ?></div>
<?php endif; ?>
</div>
<div class="gd-about-block__body">
<h3 class="gd-about-block__title">
<?php
printf(
/* translators: %s: company / display name */
esc_html__( 'About %s', 'go-deliver' ),
esc_html( $display_name )
);
?>
</h3>
<?php if ( $bio ) : ?>
<p class="gd-about-block__text"><?php echo wp_kses_post( nl2br( $bio ) ); ?></p>
<?php else : ?>
<p class="gd-about-block__text gd-text-muted">
<?php esc_html_e( 'No bio added yet. Go to Settings to tell customers about yourself.', 'go-deliver' ); ?>
</p>
<?php endif; ?>
</div>
</div>
</div>

<!-- Reviews / Past Jobs sub-tabs -->
<div class="gd-section-card">
<div class="gd-section-card__body">
<div class="gd-sub-tabs-wrapper">
<div class="gd-sub-tabs">
<span class="gd-sub-tab gd-sub-tab--active" data-subtab="ov-reviews"><?php esc_html_e( 'Reviews', 'go-deliver' ); ?></span>
<span class="gd-sub-tab" data-subtab="ov-past-jobs"><?php esc_html_e( 'Past Jobs', 'go-deliver' ); ?></span>
</div>

<!-- Sub-panel: Reviews -->
<div class="gd-sub-panel" id="gd-subtab-ov-reviews">
<?php if ( empty( $overview_reviews ) ) : ?>
<div class="gd-empty-state" style="padding:20px 0;">
<div class="gd-empty-state__icon">⭐</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No reviews yet.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $overview_reviews as $_rev ) :
$_rev_jid   = (int) $_rev['job_id'];
$_rev_cid   = $_rev_jid ? (int) get_post_meta( $_rev_jid, 'gd_customer_id', true ) : 0;
$_rev_cust  = $_rev_cid ? get_userdata( $_rev_cid ) : null;
if ( $_rev_cust ) {
$_rev_cname = esc_html( $_rev_cust->first_name . ' ' . mb_substr( (string) $_rev_cust->last_name, 0, 1 ) . '.' );
} else {
$_rev_cname = esc_html__( 'Customer', 'go-deliver' );
}
$_rev_age   = $_rev['date'] ? esc_html( human_time_diff( strtotime( $_rev['date'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'go-deliver' ) ) : '';
$_rev_stars = (int) $_rev['rating'];
?>
<div class="gd-review-card">
<div class="gd-review-card__header">
<div class="gd-review-card__avatar">
<?php if ( $_rev_cid ) : ?>
<?php echo get_avatar( $_rev_cid, 44, '', '', array( 'class' => 'gd-review-card__avatar-img' ) ); ?>
<?php else : ?>
<div class="gd-review-card__avatar-fallback">👤</div>
<?php endif; ?>
</div>
<div>
<div class="gd-review-card__name"><?php echo $_rev_cname; ?></div>
<?php if ( $_rev_age ) : ?>
<div class="gd-review-card__age"><?php echo $_rev_age; ?></div>
<?php endif; ?>
</div>
</div>
<div class="gd-review-card__stars">
<?php for ( $_rs = 1; $_rs <= 5; $_rs++ ) : ?>
<span class="gd-star<?php echo $_rs <= $_rev_stars ? ' gd-star--filled' : ''; ?>">★</span>
<?php endfor; ?>
</div>
<?php if ( $_rev['comment'] ) : ?>
<p class="gd-review-card__comment"><?php echo wp_kses_post( $_rev['comment'] ); ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php if ( $review_count > 5 ) : ?>
<div style="text-align:center;margin-top:12px;">
<a class="gd-btn gd-btn--outline gd-btn--sm gd-view-all-reviews-btn" role="button" tabindex="0">
<?php esc_html_e( 'View All Reviews', 'go-deliver' ); ?> &rsaquo;
</a>
</div>
<?php endif; ?>
<?php endif; ?>
</div><!-- /#gd-subtab-ov-reviews -->

<!-- Sub-panel: Past Jobs -->
<div class="gd-sub-panel" id="gd-subtab-ov-past-jobs" style="display:none;">
<?php if ( empty( $recent_past_jobs ) ) : ?>
<div class="gd-empty-state" style="padding:20px 0;">
<div class="gd-empty-state__icon">📦</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No completed jobs yet.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $recent_past_jobs as $_pj ) : ?>
<div class="gd-recent-job-item">
<span class="gd-recent-job-item__icon">🧳</span>
<div class="gd-recent-job-item__body">
<div class="gd-recent-job-item__title"><?php echo $_pj['job_type'] ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></div>
<div class="gd-recent-job-item__route">
<?php if ( $_pj['pickup'] || $_pj['dropoff'] ) :
echo esc_html( $_pj['pickup'] );
if ( $_pj['pickup'] && $_pj['dropoff'] ) echo ' → ';
echo esc_html( $_pj['dropoff'] );
endif; ?>
</div>
</div>
<div class="gd-recent-job-item__amount">$<?php echo esc_html( number_format( $_pj['amount'], 0 ) ); ?></div>
</div>
<?php endforeach; ?>
<div style="text-align:center;margin-top:12px;">
<a class="gd-btn gd-btn--outline gd-btn--sm gd-view-my-jobs-btn" role="button" tabindex="0">
<?php esc_html_e( 'View All Jobs', 'go-deliver' ); ?> &rsaquo;
</a>
</div>
<?php endif; ?>
</div><!-- /#gd-subtab-ov-past-jobs -->
</div><!-- /.gd-sub-tabs-wrapper -->
</div>
</div>

</div><!-- /.gd-overview-cols__main -->

<!-- Right column: private (mover only) -->
<div class="gd-overview-cols__aside">

<!-- Wallet -->
<div class="gd-wallet-section">
<div class="gd-wallet-section__label"><?php esc_html_e( 'Wallet Balance', 'go-deliver' ); ?></div>
<div class="gd-wallet-section__balance">
<span class="gd-wallet-section__currency">$</span><?php echo esc_html( number_format( $balance, 2 ) ); ?>
</div>
<div class="gd-wallet-section__actions">
<a href="<?php echo esc_url( $wallet_url ); ?>" class="gd-btn gd-btn--topup">
+ <?php esc_html_e( 'Top Up Wallet', 'go-deliver' ); ?>
</a>
</div>
</div>

<?php if ( $balance < 20 ) : ?>
<div class="gd-alert gd-alert--warning" style="margin-bottom:16px;">
<span class="gd-alert__icon">⚠️</span>
<div class="gd-alert__body">
<?php printf(
/* translators: 1: current balance, 2: fee percent */
esc_html__( 'Low balance ($%1$s). Top up to keep quoting. A %2$s%% fee is charged on acceptance.', 'go-deliver' ),
esc_html( number_format( $balance, 2 ) ),
esc_html( $fee_percentage )
); ?>
</div>
</div>
<?php endif; ?>

<!-- Transaction history -->
<?php if ( ! empty( $transactions ) ) : ?>
<div class="gd-section-card" style="margin-bottom:20px;">
<div class="gd-section-card__header">
<h3 class="gd-section-card__title" style="font-size:14px;"><?php esc_html_e( 'Recent Transactions', 'go-deliver' ); ?></h3>
</div>
<div class="gd-section-card__body" style="padding:0;">
<table class="gd-table" style="font-size:13px;">
<tbody>
<?php foreach ( $transactions as $_tx ) :
$_tx_sign  = in_array( $_tx->type, array( 'topup' ), true ) ? '+' : '−';
$_tx_color = 'topup' === $_tx->type ? 'var(--gd-success)' : 'var(--gd-danger)';
?>
<tr>
<td><?php echo esc_html( date_i18n( 'd M', strtotime( $_tx->created_at ) ) ); ?></td>
<td><?php echo esc_html( $_tx->description ?: ucfirst( $_tx->type ) ); ?></td>
<td style="text-align:right;font-weight:700;color:<?php echo esc_attr( $_tx_color ); ?>;">
<?php echo esc_html( $_tx_sign . '$' . number_format( abs( (float) $_tx->amount ), 2 ) ); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<!-- Recent Past Jobs -->
<div class="gd-section-card">
<div class="gd-section-card__header">
<h3 class="gd-section-card__title" style="font-size:14px;"><?php esc_html_e( 'Recent Past Jobs', 'go-deliver' ); ?></h3>
<a class="gd-view-my-jobs-btn" role="button" tabindex="0" style="font-size:13px;color:var(--gd-primary);cursor:pointer;text-decoration:none;">
<?php esc_html_e( 'View All', 'go-deliver' ); ?>
</a>
</div>
<div class="gd-section-card__body" style="padding:0;">
<?php if ( empty( $recent_past_jobs ) ) : ?>
<p style="padding:16px;font-size:13px;color:var(--gd-text-muted);">
<?php esc_html_e( 'No completed jobs yet.', 'go-deliver' ); ?>
</p>
<?php else : ?>
<?php foreach ( $recent_past_jobs as $_rj ) : ?>
<div class="gd-recent-job-item gd-recent-job-item--card">
<span class="gd-recent-job-item__icon">🧳</span>
<div class="gd-recent-job-item__body">
<div class="gd-recent-job-item__title"><?php echo $_rj['job_type'] ?: esc_html__( 'Moving Job', 'go-deliver' ); ?></div>
<div class="gd-recent-job-item__route">
<?php
echo esc_html( $_rj['pickup'] );
if ( $_rj['pickup'] && $_rj['dropoff'] ) {
echo ' → ' . esc_html( mb_substr( $_rj['dropoff'], 0, 8 ) ) . '…';
}
?>
</div>
<div class="gd-recent-job-item__date"><?php echo $_rj['date']; ?></div>
</div>
<div class="gd-recent-job-item__amount">$<?php echo esc_html( number_format( $_rj['amount'], 0 ) ); ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php if ( $review_count ) : ?>
<div style="padding:12px 16px;border-top:1px solid var(--gd-border);">
<a class="gd-btn gd-btn--outline gd-btn--sm gd-view-all-reviews-btn" role="button" tabindex="0" style="width:100%;justify-content:center;">
<?php esc_html_e( 'View All Reviews', 'go-deliver' ); ?> &rsaquo;
</a>
</div>
<?php endif; ?>
</div>

</div><!-- /.gd-overview-cols__aside -->

</div><!-- /.gd-overview-cols -->

</div><!-- /#gd-panel-overview -->

<!-- ========================================================
     Panel: Browse Jobs
     ======================================================== -->
<div class="gd-panel" id="gd-panel-browse-jobs" style="display:none;">

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

</div><!-- /#gd-panel-browse-jobs -->

<!-- ========================================================
     Panel: My Jobs (quotes + accepted + dismissed)
     ======================================================== -->
<div class="gd-panel" id="gd-panel-my-jobs" style="display:none;">

<div class="gd-tabs" role="tablist">
<div class="gd-tab gd-tab--active" data-tab="my-quotes" role="tab" tabindex="0">
<?php esc_html_e( 'My Quotes', 'go-deliver' ); ?>
<?php if ( ! empty( $my_quotes ) ) : ?>
<span class="gd-badge gd-badge--open" style="margin-left:6px;"><?php echo esc_html( count( $my_quotes ) ); ?></span>
<?php endif; ?>
</div>
<div class="gd-tab" data-tab="accepted-jobs" role="tab" tabindex="0">
<?php esc_html_e( 'Accepted Jobs', 'go-deliver' ); ?>
<?php if ( ! empty( $active_accepted_quotes ) ) : ?>
<span class="gd-badge gd-badge--accepted" style="margin-left:6px;"><?php echo esc_html( count( $active_accepted_quotes ) ); ?></span>
<?php endif; ?>
</div>
<div class="gd-tab" data-tab="completed-jobs" role="tab" tabindex="0">
<?php esc_html_e( 'Completed Jobs', 'go-deliver' ); ?>
<?php if ( ! empty( $completed_job_quotes ) ) : ?>
<span class="gd-badge gd-badge--accepted" style="margin-left:6px;"><?php echo esc_html( count( $completed_job_quotes ) ); ?></span>
<?php endif; ?>
</div>
<div class="gd-tab" data-tab="cancelled-jobs" role="tab" tabindex="0">
<?php esc_html_e( 'Cancelled Jobs', 'go-deliver' ); ?>
<?php if ( ! empty( $cancelled_job_quotes ) ) : ?>
<span class="gd-badge gd-badge--open" style="margin-left:6px;"><?php echo esc_html( count( $cancelled_job_quotes ) ); ?></span>
<?php endif; ?>
</div>
<div class="gd-tab" data-tab="dismissed-jobs" role="tab" tabindex="0">
<?php esc_html_e( 'Dismissed Jobs', 'go-deliver' ); ?>
<?php if ( ! empty( $dismissed_jobs ) ) : ?>
<span class="gd-badge gd-badge--open" id="gd-dismissed-badge" style="margin-left:6px;"><?php echo esc_html( count( $dismissed_jobs ) ); ?></span>
<?php endif; ?>
</div>
</div>

<!-- Tab: My Quotes -->
<div class="gd-tab-panel gd-tab-panel--active" id="gd-tab-my-quotes" role="tabpanel">
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
<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn" data-job-id="<?php echo esc_attr( $q_job_id ); ?>">
<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
</button>
<?php endif; ?>
<?php if ( 'pending' === $q_status ) : ?>
<button type="button" class="gd-btn gd-btn--danger gd-btn--sm gd-withdraw-quote-btn" data-quote-id="<?php echo esc_attr( $q_id ); ?>">
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
<?php if ( empty( $active_accepted_quotes ) ) : ?>
<div class="gd-empty-state">
<div class="gd-empty-state__icon">✅</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No accepted jobs yet.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $active_accepted_quotes as $quote ) :
$q_id        = $quote->ID;
$q_amount    = (float) get_post_meta( $q_id, 'gd_amount', true );
$q_fee       = (float) get_post_meta( $q_id, 'gd_fee_amount', true );
$q_job_id    = (int) get_post_meta( $q_id, 'gd_job_id', true );
$q_date      = esc_html( get_the_date( 'd M Y', $q_id ) );
$q_job_status = $q_job_id ? get_post_meta( $q_job_id, 'gd_job_status', true ) : '';

$raw_job_type = $q_job_id ? Go_Deliver_Jobs::get_display_title( $q_job_id ) : '';
$raw_pickup   = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_pickup_address', true ) ?: get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
$raw_dropoff  = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_dropoff_address', true ) ?: get_post_meta( $q_job_id, 'gd_dropoff_suburb', true ) ) : '';
$job_type     = esc_html( $raw_job_type );
$pickup_full  = esc_html( $raw_pickup );
$dropoff_full = esc_html( $raw_dropoff );
$date_req     = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_date_requested', true ) ) : '';

$cust_id    = $q_job_id ? (int) get_post_meta( $q_job_id, 'gd_customer_id', true ) : 0;
$cust_obj   = $cust_id ? get_userdata( $cust_id ) : null;
$raw_name   = $cust_obj ? ( trim( $cust_obj->first_name . ' ' . $cust_obj->last_name ) ?: $cust_obj->display_name ) : '';
$cust_name  = esc_html( $raw_name );
$cust_phone = $cust_id ? esc_html( get_user_meta( $cust_id, 'gd_phone', true ) ) : '';
$cust_email = $cust_obj ? esc_html( $cust_obj->user_email ) : '';

$msg_url = $q_job_id ? esc_url( add_query_arg( 'job_id', $q_job_id, $messaging_base_url ) ) : '';
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
<span class="gd-badge gd-badge--accepted"><?php esc_html_e( 'Accepted', 'go-deliver' ); ?></span>
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
<?php if ( $cust_email ) : ?>
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Email', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value">
<a href="mailto:<?php echo esc_attr( $cust_email ); ?>"><?php echo $cust_email; ?></a>
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
<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn" data-job-id="<?php echo esc_attr( $q_job_id ); ?>">
<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
</button>
<?php endif; ?>
<?php if ( $msg_url ) : ?>
<a href="<?php echo $msg_url; ?>" class="gd-btn gd-btn--primary gd-btn--sm">
💬 <?php esc_html_e( 'Open Messaging', 'go-deliver' ); ?>
</a>
<?php endif; ?>
<button type="button" class="gd-btn gd-btn--success gd-btn--sm gd-complete-job-btn" data-job-id="<?php echo esc_attr( $q_job_id ); ?>" data-quote-amount="<?php echo esc_attr( number_format( $q_amount, 0 ) ); ?>" data-job-type="<?php echo esc_attr( $raw_job_type ); ?>" data-accepted-date="<?php echo esc_attr( $q_date ); ?>">
✓ <?php esc_html_e( 'Mark as Complete', 'go-deliver' ); ?>
</button>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /#gd-tab-accepted-jobs -->

<!-- Tab: Completed Jobs -->
<div class="gd-tab-panel" id="gd-tab-completed-jobs" role="tabpanel" style="display:none;">
<?php if ( empty( $completed_job_quotes ) ) : ?>
<div class="gd-empty-state">
<div class="gd-empty-state__icon">🏁</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No completed jobs yet.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $completed_job_quotes as $quote ) :
$q_id        = $quote->ID;
$q_amount    = (float) get_post_meta( $q_id, 'gd_amount', true );
$q_fee       = (float) get_post_meta( $q_id, 'gd_fee_amount', true );
$q_job_id    = (int) get_post_meta( $q_id, 'gd_job_id', true );
$q_date      = esc_html( get_the_date( 'd M Y', $q_id ) );

$raw_job_type = $q_job_id ? Go_Deliver_Jobs::get_display_title( $q_job_id ) : '';
$raw_pickup   = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_pickup_address', true ) ?: get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
$raw_dropoff  = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_dropoff_address', true ) ?: get_post_meta( $q_job_id, 'gd_dropoff_suburb', true ) ) : '';
$job_type     = esc_html( $raw_job_type );
$pickup_full  = esc_html( $raw_pickup );
$dropoff_full = esc_html( $raw_dropoff );
$date_req     = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_date_requested', true ) ) : '';

$cust_id    = $q_job_id ? (int) get_post_meta( $q_job_id, 'gd_customer_id', true ) : 0;
$cust_obj   = $cust_id ? get_userdata( $cust_id ) : null;
$cust_name  = $cust_obj ? esc_html( trim( $cust_obj->first_name . ' ' . $cust_obj->last_name ) ?: $cust_obj->display_name ) : '';
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
<span class="gd-badge gd-badge--accepted">✓ <?php esc_html_e( 'Completed', 'go-deliver' ); ?></span>
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
<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-job-view-btn" data-job-id="<?php echo esc_attr( $q_job_id ); ?>">
<?php esc_html_e( 'View Job', 'go-deliver' ); ?>
</button>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /#gd-tab-completed-jobs -->

<!-- Tab: Cancelled Jobs -->
<div class="gd-tab-panel" id="gd-tab-cancelled-jobs" role="tabpanel" style="display:none;">
<?php if ( empty( $cancelled_job_quotes ) ) : ?>
<div class="gd-empty-state">
<div class="gd-empty-state__icon">❌</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No cancelled jobs.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $cancelled_job_quotes as $quote ) :
$q_id        = $quote->ID;
$q_amount    = (float) get_post_meta( $q_id, 'gd_amount', true );
$q_fee       = (float) get_post_meta( $q_id, 'gd_fee_amount', true );
$q_refunded  = (bool) get_post_meta( $q_id, 'gd_fee_refunded', true );
$q_job_id    = (int) get_post_meta( $q_id, 'gd_job_id', true );
$q_date      = esc_html( get_the_date( 'd M Y', $q_id ) );

$raw_job_type = $q_job_id ? Go_Deliver_Jobs::get_display_title( $q_job_id ) : '';
$raw_pickup   = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_pickup_address', true ) ?: get_post_meta( $q_job_id, 'gd_pickup_suburb', true ) ) : '';
$raw_dropoff  = $q_job_id ? ( get_post_meta( $q_job_id, 'gd_dropoff_address', true ) ?: get_post_meta( $q_job_id, 'gd_dropoff_suburb', true ) ) : '';
$job_type     = esc_html( $raw_job_type );
$pickup_full  = esc_html( $raw_pickup );
$dropoff_full = esc_html( $raw_dropoff );
$date_req     = $q_job_id ? esc_html( get_post_meta( $q_job_id, 'gd_date_requested', true ) ) : '';
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
<span class="gd-badge gd-badge--open"><?php esc_html_e( 'Cancelled', 'go-deliver' ); ?></span>
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
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Accepted', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value"><?php echo $q_date; ?></div>
</div>
<?php if ( $q_refunded && $q_fee > 0 ) : ?>
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Fee Refunded', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value gd-text-success">+$<?php echo esc_html( number_format( $q_fee, 2 ) ); ?></div>
</div>
<?php elseif ( $q_fee > 0 ) : ?>
<div class="gd-mover-card__info-item">
<div class="gd-mover-card__info-label"><?php esc_html_e( 'Fee Charged', 'go-deliver' ); ?></div>
<div class="gd-mover-card__info-value">$<?php echo esc_html( number_format( $q_fee, 2 ) ); ?></div>
</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /#gd-tab-cancelled-jobs -->

<!-- Tab: Dismissed Jobs -->
<div class="gd-tab-panel" id="gd-tab-dismissed-jobs" role="tabpanel" style="display:none;">
<?php if ( empty( $dismissed_jobs ) ) : ?>
<div class="gd-empty-state">
<div class="gd-empty-state__icon">🚫</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No dismissed jobs.', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php
$dj_type_labels = Go_Deliver_Jobs::get_type_labels();
$dj_all_ids     = array_column( $dismissed_jobs, 'id' );
$dj_quote_stats = Go_Deliver_Jobs::get_quote_stats_bulk( $dj_all_ids );
$dj_empty_stats = array( 'count' => 0, 'min' => null, 'max' => null );
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
<button type="button" class="gd-btn gd-btn--outline gd-btn--sm gd-restore-job-btn" data-job-id="<?php echo esc_attr( $dj['id'] ); ?>">
<?php esc_html_e( 'Restore to Browse Jobs', 'go-deliver' ); ?>
</button>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /#gd-tab-dismissed-jobs -->

</div><!-- /#gd-panel-my-jobs -->

<!-- ========================================================
     Panel: Reviews
     ======================================================== -->
<div class="gd-panel" id="gd-panel-reviews" style="display:none;">
<div class="gd-dashboard-header">
<h2 class="gd-dashboard-header__title" style="font-size:20px;"><?php esc_html_e( 'My Reviews', 'go-deliver' ); ?></h2>
<?php if ( $avg_rating ) : ?>
<p class="gd-dashboard-header__subtitle">
<?php
$_rr = (int) round( $avg_rating );
for ( $_rs = 1; $_rs <= 5; $_rs++ ) {
echo '<span class="' . esc_attr( $_rs <= $_rr ? 'gd-star gd-star--filled' : 'gd-star' ) . '">★</span>';
}
printf(
' %s &nbsp;·&nbsp; %s',
esc_html( number_format( $avg_rating, 1 ) ),
esc_html( sprintf(
/* translators: %d: review count */
_n( '%d review', '%d reviews', $review_count, 'go-deliver' ),
$review_count
) )
);
?>
</p>
<?php endif; ?>
</div>
<?php if ( empty( $all_reviews ) ) : ?>
<div class="gd-empty-state">
<div class="gd-empty-state__icon">⭐</div>
<p class="gd-empty-state__text"><?php esc_html_e( 'No reviews yet. Complete jobs to earn reviews!', 'go-deliver' ); ?></p>
</div>
<?php else : ?>
<?php foreach ( $all_reviews as $_ar ) :
$_ar_jid   = (int) $_ar['job_id'];
$_ar_cid   = $_ar_jid ? (int) get_post_meta( $_ar_jid, 'gd_customer_id', true ) : 0;
$_ar_cust  = $_ar_cid ? get_userdata( $_ar_cid ) : null;
if ( $_ar_cust ) {
$_ar_cname = esc_html( $_ar_cust->first_name . ' ' . mb_substr( (string) $_ar_cust->last_name, 0, 1 ) . '.' );
} else {
$_ar_cname = esc_html__( 'Customer', 'go-deliver' );
}
$_ar_age   = $_ar['date'] ? esc_html( human_time_diff( strtotime( $_ar['date'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'go-deliver' ) ) : '';
$_ar_stars = (int) $_ar['rating'];
?>
<div class="gd-review-card" style="margin-bottom:16px;">
<div class="gd-review-card__header">
<div class="gd-review-card__avatar">
<?php if ( $_ar_cid ) : ?>
<?php echo get_avatar( $_ar_cid, 44, '', '', array( 'class' => 'gd-review-card__avatar-img' ) ); ?>
<?php else : ?>
<div class="gd-review-card__avatar-fallback">👤</div>
<?php endif; ?>
</div>
<div>
<div class="gd-review-card__name"><?php echo $_ar_cname; ?></div>
<?php if ( $_ar_age ) : ?>
<div class="gd-review-card__age"><?php echo $_ar_age; ?></div>
<?php endif; ?>
</div>
</div>
<div class="gd-review-card__stars">
<?php for ( $_rs = 1; $_rs <= 5; $_rs++ ) : ?>
<span class="gd-star<?php echo $_rs <= $_ar_stars ? ' gd-star--filled' : ''; ?>">★</span>
<?php endfor; ?>
</div>
<?php if ( $_ar['comment'] ) : ?>
<p class="gd-review-card__comment"><?php echo wp_kses_post( $_ar['comment'] ); ?></p>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div><!-- /#gd-panel-reviews -->

<!-- ========================================================
     Panel: Settings / Profile
     ======================================================== -->
<div class="gd-panel" id="gd-panel-profile" style="display:none;">
<div class="gd-section-card">
<div class="gd-section-card__header">
<h2 class="gd-section-card__title"><?php esc_html_e( 'My Profile & Settings', 'go-deliver' ); ?></h2>
</div>
<div class="gd-section-card__body">

<form id="gd-mover-profile-form" novalidate>
<?php wp_nonce_field( 'gd_public_nonce', '_wpnonce', false ); ?>

<!-- Profile Photo & Company -->
<div class="gd-profile-section">
<h3 class="gd-profile-section__title"><?php esc_html_e( 'Public Profile', 'go-deliver' ); ?></h3>

<div class="gd-job-detail__grid">

<!-- My Photos gallery -->
<div class="gd-job-detail__field" style="grid-column:1/-1;">
<label class="gd-job-detail__field-label"><?php esc_html_e( 'My Photos', 'go-deliver' ); ?></label>
<?php
$_gallery_count = count( $mover_photo_ids );
$_gallery_max   = 10;
?>
<div class="gd-photo-gallery" id="gd-photo-gallery"
	data-max="<?php echo esc_attr( $_gallery_max ); ?>"
	data-count="<?php echo esc_attr( $_gallery_count ); ?>">

	<div class="gd-photo-gallery__grid" id="gd-photo-grid">
	<?php foreach ( $mover_photo_ids as $_pid ) :
		$_purl = wp_get_attachment_image_url( $_pid, 'thumbnail' );
		if ( ! $_purl ) { continue; }
	?>
	<div class="gd-photo-gallery__item" data-id="<?php echo esc_attr( $_pid ); ?>">
		<img src="<?php echo esc_url( $_purl ); ?>" alt="">
		<button type="button" class="gd-photo-delete-btn" data-id="<?php echo esc_attr( $_pid ); ?>" title="<?php esc_attr_e( 'Delete photo', 'go-deliver' ); ?>">×</button>
	</div>
	<?php endforeach; ?>
	</div>

	<div class="gd-photo-gallery__footer">
		<span class="gd-photo-gallery__count" id="gd-photo-count">
			<?php printf( esc_html__( '%1$d of %2$d photos used', 'go-deliver' ), $_gallery_count, $_gallery_max ); ?>
		</span>
		<?php if ( $_gallery_count < $_gallery_max ) : ?>
		<label class="gd-btn gd-btn--outline gd-btn--sm gd-photo-add-btn" id="gd-photo-add-label">
			<input type="file" id="gd-photo-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
			+ <?php esc_html_e( 'Add Photo', 'go-deliver' ); ?>
		</label>
		<?php else : ?>
		<span class="gd-photo-gallery__max-notice"><?php esc_html_e( 'Maximum photos reached. Delete one to add more.', 'go-deliver' ); ?></span>
		<?php endif; ?>
	</div>

	<p style="font-size:12px;color:var(--gd-text-muted);margin-top:8px;">
		<?php esc_html_e( 'The first photo is used as your profile picture. The next three appear as fleet photos on your public profile.', 'go-deliver' ); ?>
	</p>
</div>
</div>

<!-- Company name -->
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-company-name">
<?php esc_html_e( 'Company / Trading Name', 'go-deliver' ); ?>
</label>
<input
type="text"
id="gd-profile-company-name"
name="company_name"
class="gd-input"
value="<?php echo esc_attr( get_user_meta( $user_id, 'gd_company_name', true ) ); ?>"
placeholder="<?php esc_attr_e( 'e.g. Power Transport', 'go-deliver' ); ?>"
>
</div>

<!-- Bio -->
<div class="gd-job-detail__field" style="grid-column:1/-1;">
<label class="gd-job-detail__field-label" for="gd-profile-bio">
<?php esc_html_e( 'About / Bio', 'go-deliver' ); ?>
</label>
<textarea
id="gd-profile-bio"
name="bio"
class="gd-input"
rows="5"
placeholder="<?php esc_attr_e( 'Tell customers about yourself, your experience and what makes you the best choice…', 'go-deliver' ); ?>"
><?php echo esc_textarea( get_user_meta( $user_id, 'gd_bio', true ) ); ?></textarea>
</div>

</div>
</div>

<!-- Personal Details -->
<div class="gd-profile-section">
<h3 class="gd-profile-section__title"><?php esc_html_e( 'Personal Details', 'go-deliver' ); ?></h3>

<div class="gd-job-detail__grid">
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-first-name">
<?php esc_html_e( 'First Name', 'go-deliver' ); ?>
</label>
<input type="text" id="gd-profile-first-name" name="first_name" class="gd-input" value="<?php echo esc_attr( $current_user->first_name ); ?>">
</div>
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-last-name">
<?php esc_html_e( 'Last Name', 'go-deliver' ); ?>
</label>
<input type="text" id="gd-profile-last-name" name="last_name" class="gd-input" value="<?php echo esc_attr( $current_user->last_name ); ?>">
</div>
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-email">
<?php esc_html_e( 'Email', 'go-deliver' ); ?>
</label>
<input type="email" id="gd-profile-email" name="email" class="gd-input" value="<?php echo esc_attr( $current_user->user_email ); ?>">
</div>
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-phone">
<?php esc_html_e( 'Phone', 'go-deliver' ); ?>
</label>
<input type="text" id="gd-profile-phone" name="phone" class="gd-input" value="<?php echo esc_attr( get_user_meta( $user_id, 'gd_phone', true ) ); ?>">
</div>
</div>
</div>

<!-- Service Area -->
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

<!-- Job Types -->
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
<input type="checkbox" name="job_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $job_types, true ) ); ?>>
<?php echo esc_html( $label ); ?>
</label>
<?php endforeach; ?>
</div>
</div>

<!-- Notification Preferences -->
<div class="gd-profile-section">
<h3 class="gd-profile-section__title"><?php esc_html_e( 'Notification Preferences', 'go-deliver' ); ?></h3>

<div class="gd-job-detail__grid">
<div class="gd-job-detail__field">
<label class="gd-job-detail__field-label" for="gd-profile-notification-frequency">
<?php esc_html_e( 'New Job Emails', 'go-deliver' ); ?>
</label>
<select id="gd-profile-notification-frequency" name="notification_frequency" class="gd-input">
<option value="instant" <?php selected( $notification_frequency, 'instant' ); ?>><?php esc_html_e( 'Immediately', 'go-deliver' ); ?></option>
<option value="hourly"  <?php selected( $notification_frequency, 'hourly' ); ?> ><?php esc_html_e( 'Hourly Digest', 'go-deliver' ); ?></option>
<option value="daily"   <?php selected( $notification_frequency, 'daily' ); ?>  ><?php esc_html_e( 'Daily Digest', 'go-deliver' ); ?></option>
</select>
<p class="description" style="margin-top:4px;font-size:13px;color:var(--gd-text-muted);">
<?php esc_html_e( 'How often you receive email notifications when new jobs matching your profile are posted.', 'go-deliver' ); ?>
</p>
</div>
</div>
</div>

<!-- Account Status -->
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
</div><!-- /#gd-panel-profile -->

<?php if ( $is_mover ) :
$sub_users_handler = new Go_Deliver_Sub_Users();
$sub_user_list     = $sub_users_handler->get_sub_users( $user_id );
$sub_user_count    = count( $sub_user_list );
$can_add_more      = $sub_user_count < Go_Deliver_Sub_Users::MAX_SUB_USERS;
?>
<!-- ========================================================
     Panel: Team Members
     ======================================================== -->
<div class="gd-panel" id="gd-panel-team" style="display:none;">
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
$su_user = get_userdata( (int) $su_row->user_id );
if ( ! $su_user ) continue;
$su_name = trim( $su_user->first_name . ' ' . $su_user->last_name ) ?: $su_user->user_login;
?>
<div class="gd-mover-card" style="margin-bottom:8px;" id="gd-sub-user-<?php echo esc_attr( $su_row->user_id ); ?>">
<div class="gd-mover-card__header">
<div>
<div class="gd-mover-card__job-type"><?php echo esc_html( $su_name ); ?></div>
<div class="gd-mover-card__suburb"><?php echo esc_html( $su_user->user_email ); ?></div>
</div>
<div>
<button type="button" class="gd-btn gd-btn--danger gd-btn--sm gd-remove-sub-user-btn" data-sub-user-id="<?php echo esc_attr( $su_row->user_id ); ?>">
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
<input type="password" id="gd-su-password" name="password" class="gd-input" required autocomplete="new-password" minlength="8">
<span class="gd-field-hint"><?php esc_html_e( 'Minimum 8 characters.', 'go-deliver' ); ?></span>
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
</div><!-- /#gd-panel-team -->
<?php endif; ?>

</div><!-- /.gd-dashboard__main -->

</div><!-- /.gd-dashboard -->
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
