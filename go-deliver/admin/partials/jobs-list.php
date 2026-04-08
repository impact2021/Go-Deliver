<?php
/**
 * Admin Jobs List partial.
 *
 * Variables expected from caller:
 *   $jobs        – array of WP_Post objects (job posts)
 *   $total_pages – int, total number of pages
 *   $current_page – int, current page number
 *   $status_filter – string, current status filter value
 *   $suburb_search – string, current suburb search value
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

$valid_statuses = array( 'open', 'locked', 'accepted', 'expired', 'cancelled' );

$base_url = admin_url( 'admin.php?page=go-deliver-jobs' );

/**
 * Return HTML for a status badge.
 *
 * @param string $status
 * @return string
 */
function gd_jobs_status_badge( $status ) {
	$allowed = array( 'open', 'locked', 'accepted', 'expired', 'cancelled' );
	if ( ! in_array( $status, $allowed, true ) ) {
		$status = 'open';
	}
	return '<span class="gd-badge gd-badge-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
}
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
						<?php echo esc_html( ucfirst( $s ) ); ?>
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
	</form><!-- /.gd-filter-bar -->

	<!-- Jobs table -->
	<?php if ( empty( $jobs ) ) : ?>
		<p><?php esc_html_e( 'No jobs found.', 'go-deliver' ); ?></p>
	<?php else : ?>
		<div class="gd-table-wrap">
			<table class="gd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Job Type', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Pickup Suburb', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Date Requested', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Status', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Quotes', 'go-deliver' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'go-deliver' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $jobs as $job ) :
						$job_id        = (int) $job->ID;
						$customer_id   = (int) $job->post_author;
						$customer      = get_userdata( $customer_id );
						$customer_name = $customer ? esc_html( $customer->display_name ) : esc_html__( '(deleted)', 'go-deliver' );
						$job_type      = esc_html( get_post_meta( $job_id, 'gd_job_type', true ) );
						$suburb        = esc_html( get_post_meta( $job_id, 'gd_pickup_suburb', true ) );
						$status        = get_post_meta( $job_id, 'gd_status', true );
						if ( ! in_array( $status, $valid_statuses, true ) ) {
							$status = 'open';
						}
						$quote_count   = (int) get_post_meta( $job_id, 'gd_quote_count', true );
						$date          = get_the_date( 'd M Y', $job );
						$detail_url    = add_query_arg(
							array( 'page' => 'go-deliver-jobs', 'job_id' => $job_id ),
							admin_url( 'admin.php' )
						);
					?>
					<tr>
						<td data-label="<?php esc_attr_e( 'ID', 'go-deliver' ); ?>">
							<?php echo esc_html( $job_id ); ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Customer', 'go-deliver' ); ?>">
							<?php if ( $customer ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $customer_id ) ); ?>"><?php echo $customer_name; ?></a>
							<?php else : ?>
								<?php echo $customer_name; ?>
							<?php endif; ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Job Type', 'go-deliver' ); ?>"><?php echo $job_type ? $job_type : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Pickup Suburb', 'go-deliver' ); ?>"><?php echo $suburb ? $suburb : '—'; ?></td>
						<td data-label="<?php esc_attr_e( 'Date Requested', 'go-deliver' ); ?>"><?php echo esc_html( $date ); ?></td>
						<td data-label="<?php esc_attr_e( 'Status', 'go-deliver' ); ?>">
							<?php echo gd_jobs_status_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside function ?>
						</td>
						<td data-label="<?php esc_attr_e( 'Quotes', 'go-deliver' ); ?>"><?php echo esc_html( $quote_count ); ?></td>
						<td data-label="<?php esc_attr_e( 'Actions', 'go-deliver' ); ?>">
							<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small">
								<?php esc_html_e( 'View', 'go-deliver' ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div><!-- /.gd-table-wrap -->

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
			</div><!-- /.gd-pagination -->
		<?php endif; ?>

	<?php endif; ?>

</div><!-- /.gd-admin-wrap -->
