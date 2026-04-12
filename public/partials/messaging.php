<?php
/**
 * Messaging template.
 *
 * Shortcode: [gd_messaging]
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
	echo '<p class="gd-login-prompt__text">' . esc_html__( 'Please log in to view messages.', 'go-deliver' ) . '</p>';
	echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="gd-btn gd-btn--primary">' . esc_html__( 'Log In', 'go-deliver' ) . '</a>';
	echo '</div></div>';
	return;
}

$current_user_id = get_current_user_id();

// Resolve job_id from shortcode attributes or query param.
$job_id = 0;
if ( isset( $atts['job_id'] ) ) {
	$job_id = (int) $atts['job_id'];
}
if ( ! $job_id && isset( $_GET['job_id'] ) ) {
	$job_id = absint( $_GET['job_id'] );
}

if ( ! $job_id ) {
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--info"><span class="gd-alert__icon">ℹ️</span><div class="gd-alert__body">'
	     . esc_html__( 'Please select a job to view its conversation.', 'go-deliver' ) . '</div></div></div>';
	return;
}

$messaging   = new Go_Deliver_Messaging();
$can_message = $messaging->can_message( $job_id, $current_user_id );

if ( ! $can_message ) {
	echo '<div class="gd-wrap"><div class="gd-alert gd-alert--warning"><span class="gd-alert__icon">⚠️</span><div class="gd-alert__body">'
	     . esc_html__( 'You do not have permission to view messages for this job.', 'go-deliver' ) . '</div></div></div>';
	return;
}

$job = get_post( $job_id );
if ( ! $job ) {
	echo '<div class="gd-wrap"><p class="gd-text-muted">' . esc_html__( 'Job not found.', 'go-deliver' ) . '</p></div>';
	return;
}

$job_type   = esc_html( Go_Deliver_Jobs::get_display_title( $job_id ) );
$job_suburb = esc_html( get_post_meta( $job_id, 'gd_pickup_suburb', true ) );

// Determine the other party's name for the header.
$customer_id = (int) get_post_meta( $job_id, 'gd_customer_id', true );
$is_customer = ( $current_user_id === $customer_id );
$other_name  = '';

if ( $is_customer ) {
	// Find accepted or first-quoted mover.
	$accepted_quote_id = (int) get_post_meta( $job_id, 'gd_accepted_quote_id', true );
	if ( $accepted_quote_id ) {
		$mover_id  = (int) get_post_meta( $accepted_quote_id, 'gd_mover_id', true );
		$mover_obj = get_userdata( $mover_id );
		if ( $mover_obj ) {
			$other_name = esc_html( $mover_obj->first_name ?: $mover_obj->display_name );
		}
	}
	if ( ! $other_name ) {
		$other_name = esc_html__( 'Mover', 'go-deliver' );
	}
} else {
	$cust_obj   = get_userdata( $customer_id );
	$other_name = $cust_obj ? esc_html( $cust_obj->first_name ?: $cust_obj->display_name ) : esc_html__( 'Customer', 'go-deliver' );
}

$messaging_nonce = wp_create_nonce( 'gd_messaging' );
?>
<div class="gd-wrap">

	<!-- Back link -->
	<div style="margin-bottom:16px;">
		<?php
		$back_url = wp_get_referer() ?: get_permalink( get_option( 'gd_customer_dashboard_page_id' ) );
		if ( $back_url ) :
		?>
			<a href="<?php echo esc_url( $back_url ); ?>" class="gd-btn gd-btn--outline gd-btn--sm">
				← <?php esc_html_e( 'Back', 'go-deliver' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<!-- Messaging Panel -->
	<div
		class="gd-messaging-panel"
		id="gd-messaging-panel"
		data-job-id="<?php echo esc_attr( $job_id ); ?>"
		data-nonce="<?php echo esc_attr( $messaging_nonce ); ?>"
	>
		<!-- Header -->
		<div class="gd-messaging-panel__header">
			<div style="font-size:22px;">💬</div>
			<div>
				<div class="gd-messaging-panel__title">
					<?php
					printf(
						/* translators: %s: other party name */
						esc_html__( 'Conversation with %s', 'go-deliver' ),
						esc_html( $other_name )
					);
					?>
				</div>
				<div class="gd-messaging-panel__subtitle">
					<?php echo $job_type; ?>
					<?php if ( $job_suburb ) : ?>
						— <?php echo $job_suburb; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Message list -->
		<div class="gd-message-list" id="gd-message-list">
			<div class="gd-inline-spinner">
				<div class="gd-loading-spinner" style="width:28px;height:28px;border-color:var(--gd-border);border-top-color:var(--gd-primary);"></div>
			</div>
		</div>

		<!-- Input area -->
		<div class="gd-messaging-panel__input">
			<textarea
				id="gd-message-input"
				class="gd-message-textarea"
				rows="2"
				placeholder="<?php esc_attr_e( 'Type a message… (Ctrl+Enter to send)', 'go-deliver' ); ?>"
				aria-label="<?php esc_attr_e( 'Message input', 'go-deliver' ); ?>"
			></textarea>
			<button
				type="button"
				id="gd-send-message-btn"
				class="gd-btn gd-btn--primary"
				aria-label="<?php esc_attr_e( 'Send message', 'go-deliver' ); ?>"
			>
				<?php esc_html_e( 'Send', 'go-deliver' ); ?>
			</button>
		</div>
	</div><!-- /.gd-messaging-panel -->

	<!-- Info -->
	<p class="gd-text-muted gd-text-sm" style="margin-top:12px;text-align:center;">
		<?php esc_html_e( 'Messages are automatically checked every 30 seconds for new replies.', 'go-deliver' ); ?>
	</p>

</div><!-- /.gd-wrap -->
