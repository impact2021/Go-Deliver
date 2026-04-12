<?php
/**
 * Admin Jobs List partial.
 *
 * Variables expected from caller:
 *   $jobs         – array of WP_Post objects (job posts)
 *   $total_pages  – int, total number of pages
 *   $current_page – int, current page number
 *   $status_filter – string, current status filter value
 *   $suburb_search – string, current suburb search value
 *   $bid_stats    – array keyed by job_id, each with bid_count/avg_price/best_price
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $jobs ) )         { $jobs          = array(); }
if ( ! isset( $total_pages ) )  { $total_pages   = 1; }
if ( ! isset( $current_page ) ) { $current_page  = 1; }
if ( ! isset( $status_filter ) ) { $status_filter = ''; }
if ( ! isset( $suburb_search ) ) { $suburb_search  = ''; }
if ( ! isset( $bid_stats ) )    { $bid_stats     = array(); }

$valid_statuses = array( 'open', 'locked', 'accepted', 'expired', 'cancelled' );
$base_url       = admin_url( 'admin.php?page=go-deliver-jobs' );

$status_labels = array(
	'open'      => __( 'Open', 'go-deliver' ),
	'locked'    => __( 'Locked', 'go-deliver' ),
	'accepted'  => __( 'Accepted', 'go-deliver' ),
	'expired'   => __( 'Expired', 'go-deliver' ),
	'cancelled' => __( 'Cancelled', 'go-deliver' ),
);
?>
<div class="wrap gd-admin-wrap">
	<h1><?php esc_html_e( 'Jobs', 'go-deliver' ); ?></h1>

	<!-- Filter bar -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="gd-filter-bar">
		<input type="hidden" name="page" value="go-deliver-jobs">

		<div class="gd-filter-group">
			<label for="gd-status-filter"><?php esc_html_e( 'Status', 'go-deliver' ); ?></label>
			<select id="gd-job-status-filter" name="status">
				<option value=""><?php esc_html_e( 'All Statuses', 'go-deliver' ); ?></option>
				<?php foreach ( $valid_statuses as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $status_filter, $s ); ?>>
						<?php echo esc_html( $status_labels[ $s ] ?? ucfirst( $s ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="gd-filter-group">
			<label for="gd-suburb-search"><?php esc_html_e( 'Pickup Suburb', 'go-deliver' ); ?></label>
			<input
				type="text"
				id="gd-suburb-search"
				name="suburb"
				value="<?php echo esc_attr( $suburb_search ); ?>"
				placeholder="<?php esc_attr_e( 'Search suburb…', 'go-deliver' ); ?>"
			>
		</div>

		<div class="gd-filter-group">
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'go-deliver' ); ?></button>
			<?php if ( $status_filter || $suburb_search ) : ?>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Clear', 'go-deliver' ); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<?php if ( empty( $jobs ) ) : ?>
		<p class="gd-empty-state"><?php esc_html_e( 'No jobs found matching the current filters.', 'go-deliver' ); ?></p>
	<?php else : ?>

		<div class="gd-jobs-grid">
			<?php foreach ( $jobs as $job ) :
				$job_id      = (int) $job->ID;
				$customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );
				if ( ! $customer_id ) {
					$customer_id = (int) $job->post_author;
				}
				$customer      = get_userdata( $customer_id );
				$customer_name = $customer ? $customer->display_name : __( '(deleted)', 'go-deliver' );
				$job_type      = Go_Deliver_Jobs::get_display_title( $job_id );
				$status        = get_post_meta( $job_id, 'gd_job_status', true );
				if ( ! in_array( $status, $valid_statuses, true ) ) {
					$status = 'open';
				}

				// Location.
				$pickup_suburb  = get_post_meta( $job_id, 'gd_pickup_suburb', true );
				$dropoff_raw    = get_post_meta( $job_id, 'gd_dropoff_location', true );
				$dropoff        = $dropoff_raw ? json_decode( $dropoff_raw, true ) : array();
				$dropoff_suburb = ! empty( $dropoff['suburb'] ) ? $dropoff['suburb'] : '';

				// Dates.
				$date_requested = get_post_meta( $job_id, 'gd_date_requested', true );
				$date_posted    = get_the_date( 'd M Y', $job );

				// Bid stats for this job.
				$stats      = isset( $bid_stats[ $job_id ] ) ? $bid_stats[ $job_id ] : null;
				$bid_count  = $stats ? (int) $stats->bid_count  : 0;
				$avg_price  = $stats ? (float) $stats->avg_price  : 0.0;
				$best_price = $stats ? (float) $stats->best_price : 0.0;

				$detail_url = add_query_arg(
					array( 'page' => 'go-deliver-jobs', 'job_id' => $job_id ),
					admin_url( 'admin.php' )
				);
				$edit_url = get_edit_post_link( $job_id );
			?>
			<article class="gd-job-card gd-job-card--<?php echo esc_attr( $status ); ?>">

				<!-- Card header -->
				<header class="gd-job-card__header">
					<div class="gd-job-card__meta">
						<span class="gd-job-card__id">#<?php echo esc_html( $job_id ); ?></span>
						<span class="gd-job-card__type"><?php echo esc_html( $job_type ?: __( 'General', 'go-deliver' ) ); ?></span>
					</div>
					<span class="gd-badge gd-badge-<?php echo esc_attr( $status ); ?>">
						<?php echo esc_html( $status_labels[ $status ] ?? ucfirst( $status ) ); ?>
					</span>
				</header>

				<!-- Locations -->
				<div class="gd-job-card__locations">
					<div class="gd-job-card__location">
						<span class="gd-job-card__location-label"><?php esc_html_e( 'From', 'go-deliver' ); ?></span>
						<span class="gd-job-card__location-value">
							<?php
							if ( $pickup_suburb ) {
								echo esc_html( $pickup_suburb );
							} else {
								echo '<span class="gd-muted">' . esc_html__( 'Not set', 'go-deliver' ) . '</span>';
							}
							?>
						</span>
					</div>
					<?php if ( $dropoff_suburb ) : ?>
					<div class="gd-job-card__location-arrow" aria-hidden="true">&rarr;</div>
					<div class="gd-job-card__location">
						<span class="gd-job-card__location-label"><?php esc_html_e( 'To', 'go-deliver' ); ?></span>
						<span class="gd-job-card__location-value"><?php echo esc_html( $dropoff_suburb ); ?></span>
					</div>
					<?php endif; ?>
				</div>

				<!-- Details row -->
				<dl class="gd-job-card__details">
					<div class="gd-job-card__detail">
						<dt><?php esc_html_e( 'Customer', 'go-deliver' ); ?></dt>
						<dd>
							<?php if ( $customer ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $customer_id ) ); ?>"><?php echo esc_html( $customer_name ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $customer_name ); ?>
							<?php endif; ?>
						</dd>
					</div>
					<div class="gd-job-card__detail">
						<dt><?php esc_html_e( 'Posted', 'go-deliver' ); ?></dt>
						<dd><?php echo esc_html( $date_posted ); ?></dd>
					</div>
					<?php if ( $date_requested ) : ?>
					<div class="gd-job-card__detail">
						<dt><?php esc_html_e( 'Moving date', 'go-deliver' ); ?></dt>
						<dd><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date_requested ) ) ); ?></dd>
					</div>
					<?php endif; ?>
				</dl>

				<!-- Bid stats -->
				<div class="gd-job-card__bids">
					<?php if ( $bid_count > 0 ) : ?>
						<div class="gd-job-card__bid-stat">
							<span class="gd-job-card__bid-number"><?php echo esc_html( $bid_count ); ?></span>
							<span class="gd-job-card__bid-label"><?php echo esc_html( _n( 'bid', 'bids', $bid_count, 'go-deliver' ) ); ?></span>
						</div>
						<div class="gd-job-card__bid-divider" aria-hidden="true"></div>
						<div class="gd-job-card__bid-stat">
							<span class="gd-job-card__bid-number">$<?php echo esc_html( number_format( $avg_price, 0 ) ); ?></span>
							<span class="gd-job-card__bid-label"><?php esc_html_e( 'avg', 'go-deliver' ); ?></span>
						</div>
						<div class="gd-job-card__bid-divider" aria-hidden="true"></div>
						<div class="gd-job-card__bid-stat gd-job-card__bid-stat--best">
							<span class="gd-job-card__bid-number">$<?php echo esc_html( number_format( $best_price, 0 ) ); ?></span>
							<span class="gd-job-card__bid-label"><?php esc_html_e( 'best', 'go-deliver' ); ?></span>
						</div>
					<?php else : ?>
						<span class="gd-job-card__no-bids"><?php esc_html_e( 'No bids yet', 'go-deliver' ); ?></span>
					<?php endif; ?>
				</div>

				<!-- Footer actions -->
				<footer class="gd-job-card__footer">
					<a href="<?php echo esc_url( $edit_url ); ?>" class="gd-job-card__action gd-job-card__action--secondary">
						<?php esc_html_e( 'Edit', 'go-deliver' ); ?>
					</a>
					<a href="<?php echo esc_url( $detail_url ); ?>" class="gd-job-card__action gd-job-card__action--primary">
						<?php esc_html_e( 'View details', 'go-deliver' ); ?>
					</a>
				</footer>

			</article>
			<?php endforeach; ?>
		</div><!-- /.gd-jobs-grid -->

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="gd-pagination">
				<?php if ( $current_page > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page - 1, 'status' => $status_filter, 'suburb' => $suburb_search ), $base_url ) ); ?>">&laquo; <?php esc_html_e( 'Prev', 'go-deliver' ); ?></a>
				<?php endif; ?>

				<?php for ( $p = 1; $p <= $total_pages; $p++ ) :
					if ( $p === (int) $current_page ) : ?>
						<span class="gd-current-page"><?php echo esc_html( $p ); ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $p, 'status' => $status_filter, 'suburb' => $suburb_search ), $base_url ) ); ?>"><?php echo esc_html( $p ); ?></a>
					<?php endif;
				endfor; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'paged' => $current_page + 1, 'status' => $status_filter, 'suburb' => $suburb_search ), $base_url ) ); ?>"><?php esc_html_e( 'Next', 'go-deliver' ); ?> &raquo;</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div><!-- /.gd-admin-wrap -->
