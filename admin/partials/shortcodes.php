<?php
/**
 * Shortcodes reference page.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shortcodes = array(
	array(
		'tag'         => '[gd_job_form]',
		'description' => __( 'Displays the customer job submission form. Place on a public page so customers can post new moving jobs.', 'go-deliver' ),
		'roles'       => __( 'All visitors (guests and logged-in users)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_dashboard]',
		'description' => __( 'Smart dashboard shortcode. Automatically shows the <strong>customer dashboard</strong> for customers and the <strong>mover dashboard</strong> for approved movers, based on the logged-in user\'s role.', 'go-deliver' ),
		'roles'       => __( 'Logged-in users (customers and movers)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_customer_dashboard]',
		'description' => __( 'Displays the customer dashboard showing the user\'s jobs, quote activity, and inline messaging. Requires the <code>gd_submit_jobs</code> capability.', 'go-deliver' ),
		'roles'       => __( 'Customers (gd_customer role)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_mover_dashboard]',
		'description' => __( 'Displays the mover dashboard with available jobs, the mover\'s submitted quotes, wallet balance, and profile details. Requires the <code>gd_mover</code> or <code>gd_mover_sub</code> role.', 'go-deliver' ),
		'roles'       => __( 'Movers (gd_mover / gd_mover_sub roles)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_mover_registration]',
		'description' => __( 'Displays the mover sign-up form. New movers fill in their details and upload required documents. Submissions create a pending mover account for admin review.', 'go-deliver' ),
		'roles'       => __( 'All visitors (guests and logged-in users)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_wallet_topup]',
		'description' => __( 'Displays the wallet top-up form for movers to add credit to their Go Deliver wallet via Stripe. Requires the mover to be logged in.', 'go-deliver' ),
		'roles'       => __( 'Movers (gd_mover / gd_mover_sub roles)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_messaging]',
		'description' => __( 'Displays the standalone messaging interface. Typically loaded inline inside dashboard or job-detail views rather than placed directly on a page.', 'go-deliver' ),
		'roles'       => __( 'Logged-in users (customers and movers)', 'go-deliver' ),
	),
	array(
		'tag'         => '[gd_job_list]',
		'description' => __( 'Displays a list of available (open) jobs. Intended as a browse view for movers. Note: job browsing is also built into the mover dashboard.', 'go-deliver' ),
		'roles'       => __( 'Logged-in users (movers)', 'go-deliver' ),
	),
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Go Deliver – Shortcodes Reference', 'go-deliver' ); ?></h1>
	<p class="description" style="margin-bottom:20px;">
		<?php esc_html_e( 'Add these shortcodes to any WordPress page. Create a page, paste the shortcode into the page content, and publish. Refer to the table below for what each shortcode does and which user roles can see it.', 'go-deliver' ); ?>
	</p>

	<table class="widefat striped" style="max-width:960px;">
		<thead>
			<tr>
				<th style="width:220px;"><?php esc_html_e( 'Shortcode', 'go-deliver' ); ?></th>
				<th><?php esc_html_e( 'Description', 'go-deliver' ); ?></th>
				<th style="width:260px;"><?php esc_html_e( 'Visible to', 'go-deliver' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $shortcodes as $sc ) : ?>
				<tr>
					<td><code><?php echo esc_html( $sc['tag'] ); ?></code></td>
					<td><?php echo wp_kses( $sc['description'], array( 'strong' => array(), 'code' => array() ) ); ?></td>
					<td><?php echo esc_html( $sc['roles'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2 style="margin-top:32px;"><?php esc_html_e( 'Recommended Page Setup', 'go-deliver' ); ?></h2>
	<table class="widefat striped" style="max-width:960px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Page title (suggestion)', 'go-deliver' ); ?></th>
				<th><?php esc_html_e( 'Shortcode to add', 'go-deliver' ); ?></th>
				<th><?php esc_html_e( 'Notes', 'go-deliver' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php esc_html_e( 'Get a Quote / Book a Move', 'go-deliver' ); ?></td>
				<td><code>[gd_job_form]</code></td>
				<td><?php esc_html_e( 'Set the page ID in Settings → Job Form Page.', 'go-deliver' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'My Dashboard', 'go-deliver' ); ?></td>
				<td><code>[gd_dashboard]</code></td>
				<td><?php esc_html_e( 'Works for both customers and movers – no separate pages needed.', 'go-deliver' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Become a Mover', 'go-deliver' ); ?></td>
				<td><code>[gd_mover_registration]</code></td>
				<td><?php esc_html_e( 'Public sign-up page for new movers.', 'go-deliver' ); ?></td>
			</tr>
			<tr>
				<td><?php esc_html_e( 'Top Up Wallet', 'go-deliver' ); ?></td>
				<td><code>[gd_wallet_topup]</code></td>
				<td><?php esc_html_e( 'Set the page ID in Settings → Wallet Page. Movers only.', 'go-deliver' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>
