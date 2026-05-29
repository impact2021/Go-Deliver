<?php
/**
 * Job detail template.
 *
 * Shortcode: [gd_job_detail]  or loaded via AJAX (gd_get_job_detail).
 * Expects $job_id to be available either from the shortcode attribute or $_GET.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Resolve job ID from various sources.
if ( ! isset( $job_id ) ) {
	$job_id = isset( $atts['id'] ) ? (int) $atts['id'] : 0;
}
if ( ! $job_id && isset( $_GET['job_id'] ) ) {
	$job_id = absint( $_GET['job_id'] );
}

if ( ! $job_id ) {
	echo '<div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">'
	     . esc_html__( 'No job specified.', 'go-deliver' ) . '</div></div>';
	return;
}

$job = get_post( $job_id );
if ( ! $job || 'gd_job' !== $job->post_type ) {
	echo '<div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">'
	     . esc_html__( 'Job not found.', 'go-deliver' ) . '</div></div>';
	return;
}

$current_user_id = get_current_user_id();
$is_logged_in    = is_user_logged_in();
$current_user    = $is_logged_in ? wp_get_current_user() : null;

// Determine viewer role.
$is_customer  = $is_logged_in && ( $current_user && in_array( 'gd_customer', (array) $current_user->roles, true ) );
$is_mover     = $is_logged_in && ( $current_user && (
	in_array( 'gd_mover', (array) $current_user->roles, true ) ||
	in_array( 'gd_mover_sub', (array) $current_user->roles, true )
) );

// Job meta.
$job_status          = esc_attr( get_post_meta( $job_id, 'gd_job_status', true ) ?: 'open' );
$job_status_labels   = array(
	'open'      => __( 'New', 'go-deliver' ),
	'locked'    => __( 'Receiving Quotes', 'go-deliver' ),
	'accepted'  => __( 'Accepted', 'go-deliver' ),
	'expired'   => __( 'Expired', 'go-deliver' ),
	'cancelled' => __( 'Cancelled', 'go-deliver' ),
);
$job_customer_id     = (int) get_post_meta( $job_id, 'gd_customer_id', true );
$job_type            = esc_html( get_post_meta( $job_id, 'gd_job_type', true ) ?: get_post_meta( $job_id, 'gd_form_data_item_type', true ) );
$pickup_suburb       = esc_html( get_post_meta( $job_id, 'gd_pickup_suburb', true ) );
$dropoff_suburb      = esc_html( get_post_meta( $job_id, 'gd_dropoff_suburb', true ) );
$pickup_full         = esc_html( get_post_meta( $job_id, 'gd_pickup_address', true ) ?: $pickup_suburb );
$dropoff_full        = esc_html( get_post_meta( $job_id, 'gd_dropoff_address', true ) ?: $dropoff_suburb );
$date_requested      = esc_html( get_post_meta( $job_id, 'gd_date_requested', true ) );
$special_instructions = esc_html( get_post_meta( $job_id, 'gd_special_instructions', true ) );
$inventory           = esc_html( get_post_meta( $job_id, 'gd_inventory', true ) );
$access_notes        = esc_html( get_post_meta( $job_id, 'gd_access_notes', true ) );
$labour_pickup       = (bool) get_post_meta( $job_id, 'gd_labour_pickup', true );
$labour_dropoff      = (bool) get_post_meta( $job_id, 'gd_labour_dropoff', true );
$accepted_quote_id   = (int) get_post_meta( $job_id, 'gd_accepted_quote_id', true );
$quote_count         = (int) get_post_meta( $job_id, 'gd_quote_count', true );
$mover_has_quote     = false;

if ( $is_mover ) {
	$mover_quote_check = new WP_Query(
		array(
			'post_type'      => 'gd_quote',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'gd_job_id',
					'value' => $job_id,
					'type'  => 'NUMERIC',
				),
				array(
					'key'   => 'gd_mover_id',
					'value' => $current_user_id,
					'type'  => 'NUMERIC',
				),
			),
			'no_found_rows'  => true,
		)
	);
	$mover_has_quote = ! empty( $mover_quote_check->posts );
	wp_reset_postdata();
}

$can_view_market_quotes = $is_mover && $mover_has_quote;

// Privacy filter: only reveal full address to:
// - the customer who owns the job
// - a mover with an accepted quote on this job
$is_job_owner        = ( $current_user_id === $job_customer_id );
$is_accepted_mover   = false;
if ( $is_mover && $accepted_quote_id ) {
	$accepted_mover_id  = (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true );
	$is_accepted_mover  = ( $current_user_id === $accepted_mover_id );
}
$show_full_details = $is_job_owner || $is_accepted_mover;

// Customer details (only shown to job owner or accepted mover).
$customer_data = null;
if ( $show_full_details && $job_customer_id ) {
	$customer_obj = get_userdata( $job_customer_id );
	if ( $customer_obj ) {
		$customer_data = array(
			'name'  => esc_html( $customer_obj->display_name ),
			'email' => esc_html( $customer_obj->user_email ),
			'phone' => esc_html( get_user_meta( $job_customer_id, 'gd_phone', true ) ),
		);
	}
}

// Photo attachments.
$photos = json_decode( get_post_meta( $job_id, 'gd_photos', true ), true ) ?: array();

// Extended form data fields.
$form_data       = json_decode( get_post_meta( $job_id, 'gd_form_data', true ), true ) ?: array();
$date_flexible   = ! empty( $form_data['date_flexible'] );
$dropoff_floors  = $form_data['dropoff_floors'] ?? '0';
$dropoff_helpers = $form_data['dropoff_helpers'] ?? 'self';

$floors_labels = array(
	'0' => __( 'Ground floor / no stairs', 'go-deliver' ),
	'1' => __( '1 floor / flight of stairs', 'go-deliver' ),
	'2' => __( '2 floors / flights of stairs', 'go-deliver' ),
	'3' => __( '3 or more floors / flights', 'go-deliver' ),
);
$helpers_labels = array(
	'self'  => __( 'No extra help needed', 'go-deliver' ),
	'1'     => __( 'Need 1 person to help', 'go-deliver' ),
	'2plus' => __( 'Need 2+ people to help', 'go-deliver' ),
);
?>
<div class="gd-job-detail">

	<!-- Header -->
	<div class="gd-job-detail__header">
		<div>
			<h2 style="margin:0 0 6px;font-size:20px;font-weight:700;">
				<?php echo esc_html( Go_Deliver_Jobs::get_display_title( $job_id ) ); ?>
				<span style="font-size:14px;color:var(--gd-text-muted);font-weight:400;">#<?php echo esc_html( $job_id ); ?></span>
			</h2>
			<p style="margin:0;color:var(--gd-text-muted);font-size:14px;">
				<?php
				printf(
					/* translators: %s: formatted date */
					esc_html__( 'Posted %s', 'go-deliver' ),
					esc_html( get_the_date( 'd M Y', $job_id ) )
				);
				if ( in_array( $job_status, array( 'open', 'locked' ), true ) ) {
					$expiry_days = (int) get_option( 'gd_job_expiry_days', 14 );
					$expiry_ts   = strtotime( get_post_field( 'post_date', $job_id ) ) + $expiry_days * DAY_IN_SECONDS;
					echo ' (';
					printf(
						/* translators: %s: expiry date */
						esc_html__( 'listing expires %s', 'go-deliver' ),
						esc_html( date_i18n( 'd M Y', $expiry_ts ) )
					);
					echo ')';
				}
				?>
			</p>
		</div>
		<span class="gd-badge gd-badge--<?php echo esc_attr( $job_status ); ?>" style="font-size:13px;">
			<?php echo esc_html( isset( $job_status_labels[ $job_status ] ) ? $job_status_labels[ $job_status ] : ucfirst( $job_status ) ); ?>
		</span>
	</div>

	<div class="gd-job-detail__body">

		<!-- Locations -->
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Locations', 'go-deliver' ); ?></div>
			<div class="gd-job-detail__grid">
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Pickup', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo $show_full_details ? $pickup_full : $pickup_suburb; ?>
					</div>
				</div>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Dropoff', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo $show_full_details ? $dropoff_full : $dropoff_suburb; ?>
					</div>
				</div>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Date Requested', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo $date_requested ?: '—'; ?>
						<?php if ( $date_flexible ) : ?>
							<span class="gd-badge gd-badge--info" style="margin-left:6px;font-size:11px;"><?php esc_html_e( 'Flexible', 'go-deliver' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<?php if ( $is_mover && ! $is_accepted_mover && in_array( $job_status, array( 'open', 'locked' ), true ) ) : ?>
				<p class="gd-privacy-notice">
					🔒 <?php esc_html_e( 'Full address details are revealed only after your quote is accepted.', 'go-deliver' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<!-- Job Requirements -->
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Requirements', 'go-deliver' ); ?></div>
			<div class="gd-job-detail__grid">
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Labour at Pickup', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo $labour_pickup ? esc_html__( 'Yes', 'go-deliver' ) : esc_html__( 'No', 'go-deliver' ); ?>
					</div>
				</div>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Labour at Dropoff', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo $labour_dropoff ? esc_html__( 'Yes', 'go-deliver' ) : esc_html__( 'No', 'go-deliver' ); ?>
					</div>
				</div>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Floors / Stairs at Delivery', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo esc_html( $floors_labels[ $dropoff_floors ] ?? $dropoff_floors ); ?>
					</div>
				</div>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'People to Unload', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<?php echo esc_html( $helpers_labels[ $dropoff_helpers ] ?? $dropoff_helpers ); ?>
					</div>
				</div>
			</div>
		</div>

		<!-- Inventory -->
		<?php if ( $inventory ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Inventory', 'go-deliver' ); ?></div>
			<p style="font-size:14px;white-space:pre-line;"><?php echo $inventory; ?></p>
		</div>
		<?php endif; ?>

		<!-- Access Notes -->
		<?php if ( $access_notes ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Access Notes', 'go-deliver' ); ?></div>
			<p style="font-size:14px;"><?php echo $access_notes; ?></p>
		</div>
		<?php endif; ?>

		<!-- Special Instructions -->
		<?php if ( $special_instructions ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Special Instructions', 'go-deliver' ); ?></div>
			<p style="font-size:14px;"><?php echo $special_instructions; ?></p>
		</div>
		<?php endif; ?>

		<!-- Photos -->
		<?php if ( ! empty( $photos ) ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Photos', 'go-deliver' ); ?></div>
			<div class="gd-photo-gallery">
				<?php foreach ( $photos as $photo_id ) :
					$full_url  = wp_get_attachment_url( (int) $photo_id );
					$thumb_src = wp_get_attachment_image_src( (int) $photo_id, 'thumbnail' );
					if ( $full_url ) :
						$thumb_url = $thumb_src ? $thumb_src[0] : $full_url;
				?>
					<div class="gd-photo-gallery__item">
						<a href="<?php echo esc_url( $full_url ); ?>" class="gd-photo-gallery__link">
							<img
								src="<?php echo esc_url( $thumb_url ); ?>"
								alt="<?php esc_attr_e( 'Job photo', 'go-deliver' ); ?>"
								loading="lazy"
							>
						</a>
					</div>
				<?php endif; endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Customer Details (shown after acceptance) -->
		<?php if ( $show_full_details && $customer_data ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title"><?php esc_html_e( 'Customer Contact', 'go-deliver' ); ?></div>
			<div class="gd-job-detail__grid">
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Name', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value"><?php echo $customer_data['name']; ?></div>
				</div>
				<?php if ( $customer_data['phone'] ) : ?>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Phone', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<a href="tel:<?php echo esc_attr( $customer_data['phone'] ); ?>">
							<?php echo $customer_data['phone']; ?>
						</a>
					</div>
				</div>
				<?php endif; ?>
				<?php if ( $is_accepted_mover ) : ?>
				<div class="gd-job-detail__field">
					<div class="gd-job-detail__field-label"><?php esc_html_e( 'Email', 'go-deliver' ); ?></div>
					<div class="gd-job-detail__field-value">
						<a href="mailto:<?php echo esc_attr( $customer_data['email'] ); ?>">
							<?php echo $customer_data['email']; ?>
						</a>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- Quotes Section -->
		<?php if ( ( $is_job_owner || $can_view_market_quotes ) && $quote_count > 0 ) : ?>
		<div class="gd-job-detail__section">
			<div class="gd-job-detail__section-title">
				<?php
				if ( $is_job_owner ) {
					printf( esc_html__( 'Quotes Received (%d)', 'go-deliver' ), $quote_count );
				} else {
					printf( esc_html__( 'All Quotes On This Job (%d)', 'go-deliver' ), $quote_count );
				}
				?>
			</div>
			<?php if ( $can_view_market_quotes ) : ?>
				<p class="gd-text-sm" style="margin:0 0 10px;">
					<?php esc_html_e( 'You can view competing quotes and report suspicious activity from this job.', 'go-deliver' ); ?>
				</p>
			<?php endif; ?>
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
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'ASC',
			) );

			foreach ( $quotes_query->posts as $quote ) :
				$q_id         = $quote->ID;
				$q_status     = esc_attr( get_post_meta( $q_id, 'gd_status', true ) ?: 'pending' );
				$q_amount     = (float) get_post_meta( $q_id, 'gd_amount', true );
				$q_message    = esc_html( get_post_meta( $q_id, 'gd_message', true ) );
				$q_mover_id   = (int) get_post_meta( $q_id, 'gd_mover_id', true );
				$q_mover      = get_userdata( $q_mover_id );
				$q_mover_name = $q_mover ? ( get_user_meta( $q_mover_id, 'gd_company_name', true ) ?: $q_mover->first_name ) : '';
				$q_mover_name = $q_mover_name ? esc_html( $q_mover_name ) : esc_html__( 'Mover', 'go-deliver' );
				$q_rating     = (float) get_user_meta( $q_mover_id, 'gd_average_rating', true );
				$is_accepted  = ( 'accepted' === $q_status );
				$q_mover_phone = ( $is_accepted && $is_job_owner ) ? esc_html( get_user_meta( $q_mover_id, 'gd_phone', true ) ) : '';
				$q_mover_email = ( $is_accepted && $is_job_owner && $q_mover ) ? esc_html( $q_mover->user_email ) : '';
			?>
				<div class="gd-quote-card <?php echo $is_accepted ? 'gd-quote-card--accepted' : ''; ?>">
					<div class="gd-quote-card__header">
						<div class="gd-quote-card__mover">
							<div class="gd-quote-card__avatar">
								<?php echo esc_html( strtoupper( substr( $q_mover_name, 0, 1 ) ) ); ?>
							</div>
							<div>
								<div class="gd-quote-card__mover-name"><?php echo $q_mover_name; ?></div>
								<div class="gd-rating-display" style="font-size:12px;">
									<?php for ( $s = 1; $s <= 5; $s++ ) :
										$cls = $s <= (int) round( $q_rating ) ? 'gd-star gd-star--filled' : 'gd-star';
									?>
										<span class="<?php echo esc_attr( $cls ); ?>">★</span>
									<?php endfor; ?>
									<span class="gd-rating-display__count">(<?php echo esc_html( number_format( $q_rating, 1 ) ); ?>)</span>
								</div>
							</div>
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
						<div class="gd-quote-card__message"><?php echo $q_message; ?></div>
					<?php endif; ?>
					<?php if ( $is_accepted && ( $q_mover_phone || $q_mover_email ) ) : ?>
						<div class="gd-quote-card__message">
							<?php if ( $q_mover_phone ) : ?>
								<div><?php esc_html_e( 'Phone:', 'go-deliver' ); ?> <a href="tel:<?php echo esc_attr( $q_mover_phone ); ?>"><?php echo $q_mover_phone; ?></a></div>
							<?php endif; ?>
							<?php if ( $q_mover_email ) : ?>
								<div><?php esc_html_e( 'Email:', 'go-deliver' ); ?> <a href="mailto:<?php echo esc_attr( $q_mover_email ); ?>"><?php echo $q_mover_email; ?></a></div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( 'pending' === $q_status && $is_job_owner && in_array( $job_status, array( 'open', 'locked' ), true ) ) : ?>
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
					<?php elseif ( $can_view_market_quotes && $q_mover_id !== $current_user_id ) : ?>
						<div class="gd-quote-card__actions">
							<button
								type="button"
								class="gd-btn gd-btn--outline gd-btn--sm gd-report-activity-btn"
								data-report-type="quote"
								data-job-id="<?php echo esc_attr( $job_id ); ?>"
								data-quote-id="<?php echo esc_attr( $q_id ); ?>"
							>
								⚑ <?php esc_html_e( 'Report', 'go-deliver' ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php endif; ?>

		<!-- Quote Form (mover view, only for open jobs) -->
		<?php if ( $is_mover && in_array( $job_status, array( 'open', 'locked' ), true ) ) : ?>
			<?php include __DIR__ . '/quote-form.php'; ?>
		<?php endif; ?>

		<!-- Messaging Section -->
		<?php if ( $is_logged_in ) :
			$messaging = new Go_Deliver_Messaging();
			$message_participant_id = $is_job_owner ? ( $accepted_mover_id ?? 0 ) : $job_customer_id;
			if ( $messaging->can_message( $job_id, $current_user_id, $message_participant_id ) ) :
				$messaging_page_id  = (int) get_option( 'gd_messaging_page_id', 0 );
				$messaging_base_url = $messaging_page_id ? get_permalink( $messaging_page_id ) : home_url();
		?>
			<div class="gd-job-detail__section">
				<div class="gd-job-detail__section-title"><?php esc_html_e( 'Messages', 'go-deliver' ); ?></div>
				<p class="gd-text-sm" style="margin-bottom:10px;">
					<a
						href="<?php echo esc_url( add_query_arg( array( 'job_id' => $job_id, 'participant_id' => (int) $message_participant_id ), $messaging_base_url ) ); ?>"
						class="gd-btn gd-btn--outline gd-btn--sm"
					>
						💬 <?php echo esc_html( $is_mover && in_array( $job_status, array( 'open', 'locked' ), true ) ? __( 'Message Customer', 'go-deliver' ) : __( 'Open Messaging', 'go-deliver' ) ); ?>
					</a>
				</p>
			</div>
		<?php endif; endif; ?>

	</div><!-- /.gd-job-detail__body -->
</div><!-- /.gd-job-detail -->
