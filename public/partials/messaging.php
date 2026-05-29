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

$participant_id = 0;
if ( isset( $atts['participant_id'] ) ) {
	$participant_id = (int) $atts['participant_id'];
}
if ( ! $participant_id && isset( $_GET['participant_id'] ) ) {
	$participant_id = absint( $_GET['participant_id'] );
}

if ( ! $job_id ) {
	// No job selected – show inbox: all conversations ordered by unread first.
	$conversations = Go_Deliver_DB::get_conversations_for_user( $current_user_id );

	if ( empty( $conversations ) ) {
		echo '<div class="gd-wrap"><div class="gd-empty-state">'
			. '<div class="gd-empty-state__icon">💬</div>'
			. '<p class="gd-empty-state__text">' . esc_html__( 'You have no conversations yet.', 'go-deliver' ) . '</p>'
			. '</div></div>';
		return;
	}

	$messaging_base_url = get_permalink();
	?>
	<div class="gd-wrap">
		<h2 style="font-size:20px;font-weight:700;margin:0 0 16px;"><?php esc_html_e( 'Messages', 'go-deliver' ); ?></h2>
		<div class="gd-conversation-list">
			<?php foreach ( $conversations as $conv ) :
				$cjob_id        = (int) $conv->job_id;
				$cother_user_id = (int) $conv->other_user_id;
				$unread         = (int) $conv->unread_count;
				$last_at        = $conv->last_message_at;
				$job_post       = get_post( $cjob_id );
				if ( ! $job_post ) continue;

				$job_title   = esc_html( Go_Deliver_Jobs::get_display_title( $cjob_id ) );
				$raw_loc     = json_decode( get_post_meta( $cjob_id, 'gd_pickup_location', true ), true );
				$suburb      = esc_html( $raw_loc['suburb'] ?? $raw_loc['address'] ?? '' );
				$other_user  = $cother_user_id ? get_userdata( $cother_user_id ) : null;
				$other_name  = $other_user ? ( get_user_meta( $cother_user_id, 'gd_company_name', true ) ?: ( trim( $other_user->first_name . ' ' . $other_user->last_name ) ?: $other_user->display_name ) ) : $job_title;
				$conv_url    = esc_url( add_query_arg( array( 'job_id' => $cjob_id, 'participant_id' => $cother_user_id ), $messaging_base_url ) );
				$time_label  = esc_html( human_time_diff( strtotime( $last_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'go-deliver' ) );
			?>
				<a href="<?php echo $conv_url; ?>" class="gd-conversation-item<?php echo $unread ? ' gd-conversation-item--unread' : ''; ?>">
					<div class="gd-conversation-item__icon">💬</div>
					<div class="gd-conversation-item__body">
						<div class="gd-conversation-item__title">
							<?php echo esc_html( $other_name ); ?>
							<?php if ( $suburb ) : ?>
								<span class="gd-conversation-item__suburb"> — <?php echo $suburb; ?></span>
							<?php endif; ?>
						</div>
						<div class="gd-conversation-item__meta"><?php echo esc_html( $job_title ); ?> · <?php echo $time_label; ?></div>
					</div>
					<?php if ( $unread ) : ?>
						<span class="gd-conversation-item__badge"><?php echo esc_html( $unread ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return;
}

$messaging   = new Go_Deliver_Messaging();
$participant_id = $messaging->resolve_conversation_partner( $job_id, $current_user_id, $participant_id );
$can_message    = (bool) $participant_id;

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

if ( $participant_id ) {
	$participant_user = get_userdata( $participant_id );
	if ( $participant_user ) {
		$participant_name = get_user_meta( $participant_id, 'gd_company_name', true );
		if ( ! $participant_name ) {
			$participant_name = trim( $participant_user->first_name . ' ' . $participant_user->last_name ) ?: $participant_user->display_name;
		}
		$other_name = esc_html( $participant_name );
	}
}

if ( ! $other_name ) {
	$other_name = $is_customer ? esc_html__( 'Mover', 'go-deliver' ) : esc_html__( 'Customer', 'go-deliver' );
}

$messaging_nonce = wp_create_nonce( 'gd_messaging' );
$quote_accepted  = (bool) get_post_meta( $job_id, 'gd_accepted_quote_id', true );
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
	data-participant-id="<?php echo esc_attr( $participant_id ); ?>"
	data-nonce="<?php echo esc_attr( $messaging_nonce ); ?>"
	data-quote-accepted="<?php echo $quote_accepted ? '1' : '0'; ?>"
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

		<!-- Contact-details policy notice -->
		<?php if ( $quote_accepted ) : ?>
			<div class="gd-alert gd-alert--success gd-alert--panel">
				<span class="gd-alert__icon">✅</span>
				<div class="gd-alert__body">
					<div class="gd-alert__title"><?php esc_html_e( 'Quote accepted', 'go-deliver' ); ?></div>
					<?php esc_html_e( 'You can now share contact details with each other.', 'go-deliver' ); ?>
				</div>
			</div>
		<?php else : ?>
			<div class="gd-alert gd-alert--warning gd-alert--panel">
				<span class="gd-alert__icon">⚠️</span>
				<div class="gd-alert__body">
					<div class="gd-alert__title"><?php esc_html_e( 'Contact details are not permitted yet', 'go-deliver' ); ?></div>
					<strong><?php esc_html_e( "Contact numbers can't be shared until the job has been quoted and accepted.", 'go-deliver' ); ?></strong>
					<div><?php esc_html_e( 'Phone numbers, email addresses, and links are blocked until then, so please keep all communication on-platform.', 'go-deliver' ); ?></div>
				</div>
			</div>
		<?php endif; ?>

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
