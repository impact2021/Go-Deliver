<?php
/**
 * Admin-facing functionality.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Admin
 *
 * Registers admin menus, settings pages, and admin-side scripts/styles.
 */
class Go_Deliver_Admin {

	/**
	 * Register admin menu pages.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Go Deliver', 'go-deliver' ),
			__( 'Go Deliver', 'go-deliver' ),
			'manage_options',
			'go-deliver',
			array( $this, 'render_dashboard_page' ),
			'dashicons-car',
			24
		);

		add_submenu_page(
			'go-deliver',
			__( 'Dashboard', 'go-deliver' ),
			__( 'Dashboard', 'go-deliver' ),
			'manage_options',
			'go-deliver',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Jobs', 'go-deliver' ),
			__( 'Jobs', 'go-deliver' ),
			'manage_options',
			'go-deliver-jobs',
			array( $this, 'render_jobs_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Movers', 'go-deliver' ),
			__( 'Movers', 'go-deliver' ),
			'manage_options',
			'go-deliver-movers',
			array( $this, 'render_movers_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Transactions', 'go-deliver' ),
			__( 'Transactions', 'go-deliver' ),
			'manage_options',
			'go-deliver-transactions',
			array( $this, 'render_transactions_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Form Builder', 'go-deliver' ),
			__( 'Form Builder', 'go-deliver' ),
			'manage_options',
			'go-deliver-form-builder',
			array( $this, 'render_form_builder_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Settings', 'go-deliver' ),
			__( 'Settings', 'go-deliver' ),
			'manage_options',
			'go-deliver-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Shortcodes', 'go-deliver' ),
			__( 'Shortcodes', 'go-deliver' ),
			'manage_options',
			'go-deliver-shortcodes',
			array( $this, 'render_shortcodes_page' )
		);

		add_submenu_page(
			'go-deliver',
			__( 'Docs', 'go-deliver' ),
			__( 'Docs', 'go-deliver' ),
			'manage_options',
			'go-deliver-docs',
			array( $this, 'render_docs_page' )
		);
	}

	/**
	 * Register meta boxes for the gd_job post type.
	 */
	public function add_job_meta_boxes() {
		add_meta_box(
			'gd_job_details',
			__( 'Job Information', 'go-deliver' ),
			array( $this, 'render_job_meta_box' ),
			'gd_job',
			'normal',
			'high'
		);
	}

	/**
	 * Render the job meta box content.
	 *
	 * @param WP_Post $post The current post.
	 */
	public function render_job_meta_box( $post ) {
		require GD_PLUGIN_DIR . 'admin/partials/job-meta-box.php';
	}

	/**
	 * Save job meta when the gd_job post is saved.
	 *
	 * @param int $post_id The post ID being saved.
	 */
	public function save_job_meta( $post_id ) {
		// Verify nonce.
		if (
			! isset( $_POST['gd_job_meta_nonce'] ) ||
			! wp_verify_nonce( sanitize_key( $_POST['gd_job_meta_nonce'] ), 'gd_save_job_meta' )
		) {
			return;
		}

		// Bail on autosave / revision.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// ── Core fields ──────────────────────────────────────────────────────
		$job_type = isset( $_POST['gd_job_type'] )
			? sanitize_text_field( wp_unslash( $_POST['gd_job_type'] ) )
			: '';
		update_post_meta( $post_id, 'gd_job_type', $job_type );

		$customer_id = isset( $_POST['gd_customer_id'] ) ? absint( $_POST['gd_customer_id'] ) : 0;
		update_post_meta( $post_id, 'gd_customer_id', $customer_id );

		$date_requested = isset( $_POST['gd_date_requested'] )
			? sanitize_text_field( wp_unslash( $_POST['gd_date_requested'] ) )
			: '';
		update_post_meta( $post_id, 'gd_date_requested', $date_requested );

		$valid_statuses = array( 'open', 'locked', 'accepted', 'expired', 'cancelled' );
		$job_status     = isset( $_POST['gd_job_status'] )
			? sanitize_key( wp_unslash( $_POST['gd_job_status'] ) )
			: 'open';
		if ( ! in_array( $job_status, $valid_statuses, true ) ) {
			$job_status = 'open';
		}
		update_post_meta( $post_id, 'gd_job_status', $job_status );

		// ── Pickup location ───────────────────────────────────────────────────
		$pickup_location = array(
			'suburb'  => isset( $_POST['gd_pickup_suburb'] )
				? sanitize_text_field( wp_unslash( $_POST['gd_pickup_suburb'] ) )
				: '',
			'address' => isset( $_POST['gd_pickup_address'] )
				? sanitize_text_field( wp_unslash( $_POST['gd_pickup_address'] ) )
				: '',
			'lat'     => isset( $_POST['gd_pickup_lat'] )
				? (float) wp_unslash( $_POST['gd_pickup_lat'] )
				: 0.0,
			'lng'     => isset( $_POST['gd_pickup_lng'] )
				? (float) wp_unslash( $_POST['gd_pickup_lng'] )
				: 0.0,
		);

		// Auto-geocode pickup if lat/lng is missing but suburb or address is present.
		if ( ( ! $pickup_location['lat'] || ! $pickup_location['lng'] ) &&
			( ! empty( $pickup_location['address'] ) || ! empty( $pickup_location['suburb'] ) ) ) {
			$location_handler  = new Go_Deliver_Location();
			$geocode_query     = ! empty( $pickup_location['address'] ) ? $pickup_location['address'] : $pickup_location['suburb'];
			$coords            = $location_handler->geocode_address( $geocode_query );
			if ( ! is_wp_error( $coords ) ) {
				$pickup_location['lat'] = $coords['lat'];
				$pickup_location['lng'] = $coords['lng'];
			}
		}

		update_post_meta( $post_id, 'gd_pickup_location', wp_json_encode( $pickup_location ) );
		// Keep the flat suburb key used by the admin jobs list.
		update_post_meta( $post_id, 'gd_pickup_suburb', $pickup_location['suburb'] );

		// ── Dropoff location ──────────────────────────────────────────────────
		$dropoff_location = array(
			'suburb'  => isset( $_POST['gd_dropoff_suburb'] )
				? sanitize_text_field( wp_unslash( $_POST['gd_dropoff_suburb'] ) )
				: '',
			'address' => isset( $_POST['gd_dropoff_address'] )
				? sanitize_text_field( wp_unslash( $_POST['gd_dropoff_address'] ) )
				: '',
			'lat'     => isset( $_POST['gd_dropoff_lat'] )
				? (float) wp_unslash( $_POST['gd_dropoff_lat'] )
				: 0.0,
			'lng'     => isset( $_POST['gd_dropoff_lng'] )
				? (float) wp_unslash( $_POST['gd_dropoff_lng'] )
				: 0.0,
		);

		// Auto-geocode dropoff if lat/lng is missing but suburb or address is present.
		if ( ( ! $dropoff_location['lat'] || ! $dropoff_location['lng'] ) &&
			( ! empty( $dropoff_location['address'] ) || ! empty( $dropoff_location['suburb'] ) ) ) {
			if ( ! isset( $location_handler ) ) {
				$location_handler = new Go_Deliver_Location();
			}
			$geocode_query = ! empty( $dropoff_location['address'] ) ? $dropoff_location['address'] : $dropoff_location['suburb'];
			$coords        = $location_handler->geocode_address( $geocode_query );
			if ( ! is_wp_error( $coords ) ) {
				$dropoff_location['lat'] = $coords['lat'];
				$dropoff_location['lng'] = $coords['lng'];
			}
		}

		update_post_meta( $post_id, 'gd_dropoff_location', wp_json_encode( $dropoff_location ) );

		// ── Items & notes ─────────────────────────────────────────────────────
		$inventory = isset( $_POST['gd_inventory'] )
			? wp_kses_post( wp_unslash( $_POST['gd_inventory'] ) )
			: '';
		update_post_meta( $post_id, 'gd_inventory', $inventory );

		update_post_meta( $post_id, 'gd_labour_pickup',  ! empty( $_POST['gd_labour_pickup'] ) ? 1 : 0 );
		update_post_meta( $post_id, 'gd_labour_dropoff', ! empty( $_POST['gd_labour_dropoff'] ) ? 1 : 0 );

		$access_notes = isset( $_POST['gd_access_notes'] )
			? sanitize_textarea_field( wp_unslash( $_POST['gd_access_notes'] ) )
			: '';
		update_post_meta( $post_id, 'gd_access_notes', $access_notes );

		$special_instructions = isset( $_POST['gd_special_instructions'] )
			? wp_kses_post( wp_unslash( $_POST['gd_special_instructions'] ) )
			: '';
		update_post_meta( $post_id, 'gd_special_instructions', $special_instructions );

		// Set created_at timestamp on first save.
		if ( ! get_post_meta( $post_id, 'gd_created_at', true ) ) {
			update_post_meta( $post_id, 'gd_created_at', current_time( 'mysql' ) );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		// Load on plugin admin pages and on the gd_job post edit screens.
		$is_plugin_page = strpos( $hook_suffix, 'go-deliver' ) !== false;

		$is_job_edit = false;
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			// post-new.php passes post_type in the query string.
			// post.php passes the post ID; derive the type from it.
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['post_type'] ) && 'gd_job' === sanitize_key( $_GET['post_type'] ) ) {
				$is_job_edit = true;
			} elseif ( isset( $_GET['post'] ) && 'gd_job' === get_post_type( absint( $_GET['post'] ) ) ) {
				$is_job_edit = true;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended
		}

		if ( ! $is_plugin_page && ! $is_job_edit ) {
			return;
		}

		wp_enqueue_style(
			'go-deliver-admin',
			GD_PLUGIN_URL . 'admin/css/go-deliver-admin.css',
			array(),
			GD_VERSION
		);

		$google_maps_key = get_option( 'gd_google_maps_api_key', '' );

		if ( $google_maps_key && $is_job_edit ) {
			wp_enqueue_script(
				'google-maps-places-admin',
				'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $google_maps_key ) . '&libraries=places',
				array(),
				null,
				true
			);
		}

		wp_enqueue_script(
			'go-deliver-admin',
			GD_PLUGIN_URL . 'admin/js/go-deliver-admin.js',
			array( 'jquery' ),
			GD_VERSION,
			true
		);

		wp_localize_script(
			'go-deliver-admin',
			'gdAdmin',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'gd_admin_nonce' ),
				'hasGooglePlaces' => ( $google_maps_key && $is_job_edit ) ? '1' : '',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers.
	// -------------------------------------------------------------------------

	/**
	 * Render the admin dashboard overview page.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Go Deliver Dashboard', 'go-deliver' ) . '</h1></div>';
	}

	/**
	 * Render the jobs list page.
	 */
	public function render_jobs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}

		global $wpdb;

		$per_page     = 20;
		$current_page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$suburb_search = isset( $_GET['suburb'] ) ? sanitize_text_field( wp_unslash( $_GET['suburb'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'post_type'      => 'gd_job',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $current_page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$meta_clauses = array();

		if ( $status_filter ) {
			$meta_clauses[] = array(
				'key'   => 'gd_job_status',
				'value' => $status_filter,
			);
		}

		if ( $suburb_search ) {
			$meta_clauses[] = array(
				'key'     => 'gd_pickup_suburb',
				'value'   => $suburb_search,
				'compare' => 'LIKE',
			);
		}

		if ( $meta_clauses ) {
			$query_args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta_clauses ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$query       = new WP_Query( $query_args );
		$jobs        = $query->posts;
		$total_pages = $query->max_num_pages;

		// Batch-fetch bid statistics (count, average, best price) for all jobs
		// on this page in a single query to avoid N+1 database hits.
		$bid_stats = array();
		if ( ! empty( $jobs ) ) {
			$job_ids      = array_map( 'intval', wp_list_pluck( $jobs, 'ID' ) );
			$placeholders = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
					    CAST(jm.meta_value AS UNSIGNED) AS job_id,
					    COUNT(DISTINCT p.ID)                              AS bid_count,
					    AVG(CAST(am.meta_value AS DECIMAL(10,2)))         AS avg_price,
					    MIN(CAST(am.meta_value AS DECIMAL(10,2)))         AS best_price
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} jm
					    ON p.ID = jm.post_id AND jm.meta_key = 'gd_job_id'
					INNER JOIN {$wpdb->postmeta} sm
					    ON p.ID = sm.post_id AND sm.meta_key = 'gd_status' AND sm.meta_value != 'withdrawn'
					INNER JOIN {$wpdb->postmeta} am
					    ON p.ID = am.post_id AND am.meta_key = 'gd_amount'
					WHERE p.post_type   = 'gd_quote'
					  AND p.post_status = 'publish'
					  AND jm.meta_value IN ({$placeholders})
					GROUP BY jm.meta_value",
					$job_ids
				)
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

			foreach ( $rows as $row ) {
				$bid_stats[ (int) $row->job_id ] = $row;
			}
		}

		require GD_PLUGIN_DIR . 'admin/partials/jobs-list.php';
		wp_reset_postdata();
	}

	/**
	 * Render the movers management page.
	 */
	public function render_movers_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}

		// Detail view: single mover approval.
		if ( isset( $_GET['user_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$uid   = absint( $_GET['user_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mover = get_userdata( $uid );
			if ( ! $mover ) {
				wp_die( esc_html__( 'Mover not found.', 'go-deliver' ) );
			}
			$documents    = Go_Deliver_DB::get_documents( $uid );
			$transactions = Go_Deliver_DB::get_transactions( $uid );
			// Read status log stored as user meta by ajax_set_mover_status().
			$raw_log    = get_user_meta( $uid, 'gd_mover_status_log', true );
			$status_log = array();
			if ( is_array( $raw_log ) ) {
				foreach ( $raw_log as $entry ) {
					$status_log[] = (object) $entry;
				}
			}
			require GD_PLUGIN_DIR . 'admin/partials/mover-approval.php';
			return;
		}

		// List view.
		$per_page      = 20;
		$current_page  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$user_args = array(
			'role'   => 'gd_mover',
			'number' => $per_page,
			'paged'  => $current_page,
		);

		if ( $status_filter ) {
			$user_args['meta_key']   = 'gd_mover_status'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$user_args['meta_value'] = $status_filter; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		$user_query  = new WP_User_Query( $user_args );
		$movers      = $user_query->get_results();
		$total_users = $user_query->get_total();
		$total_pages = $per_page > 0 ? (int) ceil( $total_users / $per_page ) : 1;

		require GD_PLUGIN_DIR . 'admin/partials/movers-list.php';
	}

	/**
	 * Render the transactions page.
	 */
	public function render_transactions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}

		global $wpdb;

		$per_page     = 30;
		$current_page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_user  = isset( $_GET['filter_user'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_user'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_type  = isset( $_GET['filter_type'] ) ? sanitize_key( $_GET['filter_type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_from  = isset( $_GET['filter_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_to    = isset( $_GET['filter_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$table  = $wpdb->prefix . 'gd_wallet_transactions';
		$where  = array( '1=1' );
		$params = array();

		if ( $filter_user ) {
			$user = get_user_by( 'login', $filter_user );
			if ( ! $user ) {
				$user = get_user_by( 'email', $filter_user );
			}
			if ( $user ) {
				$where[]  = 'user_id = %d';
				$params[] = $user->ID;
			}
		}

		$valid_types = array( 'topup', 'charge', 'refund', 'adjustment' );
		if ( $filter_type && in_array( $filter_type, $valid_types, true ) ) {
			$where[]  = 'type = %s';
			$params[] = $filter_type;
		}

		if ( $filter_from ) {
			$where[]  = 'created_at >= %s';
			$params[] = $filter_from . ' 00:00:00';
		}

		if ( $filter_to ) {
			$where[]  = 'created_at <= %s';
			$params[] = $filter_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $current_page - 1 ) * $per_page;

		if ( $params ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total_count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}", $params ) );
			$transactions  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) );
			$total_credits = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM `{$table}` WHERE {$where_sql} AND amount > 0", $params ) );
			$total_debits  = abs( (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM `{$table}` WHERE {$where_sql} AND amount < 0", $params ) ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			$transactions  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
			$total_credits = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM `{$table}` WHERE amount > 0" );
			$total_debits  = abs( (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM `{$table}` WHERE amount < 0" ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$total_pages = $per_page > 0 ? (int) ceil( $total_count / $per_page ) : 1;

		require GD_PLUGIN_DIR . 'admin/partials/transactions.php';
	}

	/**
	 * Render the form builder page.
	 */
	public function render_form_builder_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		require GD_PLUGIN_DIR . 'admin/partials/form-builder.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		require GD_PLUGIN_DIR . 'admin/partials/settings.php';
	}

	/**
	 * Render the shortcodes reference page.
	 */
	public function render_shortcodes_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		require GD_PLUGIN_DIR . 'admin/partials/shortcodes.php';
	}

	/**
	 * Render the docs page.
	 */
	public function render_docs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-deliver' ) );
		}
		require GD_PLUGIN_DIR . 'admin/partials/docs.php';
	}

	/**
	 * Add the Go Deliver plugin version and last-updated info to the admin bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$updated_timestamp = filemtime( GD_PLUGIN_DIR . 'go-deliver.php' );
		$updated_label     = false !== $updated_timestamp
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $updated_timestamp )
			: __( 'Unknown', 'go-deliver' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'go-deliver-info',
				'title' => sprintf(
					/* translators: 1: plugin version number, 2: last-updated date/time */
					esc_html__( 'Go Deliver v%1$s | Updated: %2$s', 'go-deliver' ),
					esc_html( GD_VERSION ),
					esc_html( $updated_label )
				),
				'href'  => admin_url( 'admin.php?page=go-deliver' ),
				'meta'  => array(
					'title' => esc_html__( 'Go to Go Deliver Dashboard', 'go-deliver' ),
				),
			)
		);
	}

	// =========================================================================
	// Admin AJAX dispatcher
	// =========================================================================

	/**
	 * Central dispatcher for admin-only AJAX actions.
	 *
	 * All actions share the gd_admin_nonce nonce. Each action maps to a private
	 * handler method on this class.
	 */
	public function dispatch_admin_ajax() {
		check_ajax_referer( 'gd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'go-deliver' ) ), 403 );
		}

		$action = isset( $_POST['action'] ) ? sanitize_key( $_POST['action'] ) : '';

		$map = array(
			'gd_approve_mover'                => 'ajax_approve_mover',
			'gd_reject_mover'                 => 'ajax_reject_mover',
			'gd_suspend_mover'                => 'ajax_suspend_mover',
			'gd_adjust_wallet'                => 'ajax_adjust_wallet',
			'gd_update_document_status'       => 'ajax_update_document_status',
			'gd_save_form_fields'             => 'ajax_save_form_fields',
			'gd_admin_update_mover_profile'   => 'ajax_admin_update_mover_profile',
		);

		if ( isset( $map[ $action ] ) ) {
			$this->{$map[ $action ]}();
			return;
		}

		wp_send_json_error( array( 'message' => __( 'Unknown action.', 'go-deliver' ) ), 400 );
	}

	// =========================================================================
	// Admin AJAX handlers
	// =========================================================================

	/**
	 * AJAX: approve a mover.
	 */
	private function ajax_approve_mover() {
		$this->ajax_set_mover_status( 'approved' );
	}

	/**
	 * AJAX: reject a mover.
	 */
	private function ajax_reject_mover() {
		$this->ajax_set_mover_status( 'rejected' );
	}

	/**
	 * AJAX: suspend a mover.
	 */
	private function ajax_suspend_mover() {
		$this->ajax_set_mover_status( 'suspended' );
	}

	/**
	 * Shared helper: update gd_mover_status for a user and record the change.
	 *
	 * @param string $new_status Target status ('approved'|'rejected'|'suspended').
	 */
	private function ajax_set_mover_status( $new_status ) {
		$valid = array( 'approved', 'rejected', 'suspended' );
		if ( ! in_array( $new_status, $valid, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'go-deliver' ) ) );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'go-deliver' ) ) );
		}

		$reason     = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$old_status = get_user_meta( $user_id, 'gd_mover_status', true ) ?: 'pending';

		update_user_meta( $user_id, 'gd_mover_status', $new_status );

		// Append a log entry to user meta so status history is preserved.
		$log = get_user_meta( $user_id, 'gd_mover_status_log', true );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'old_status' => $old_status,
			'new_status' => $new_status,
			'reason'     => $reason,
			'admin_id'   => get_current_user_id(),
			'changed_at' => current_time( 'mysql' ),
		);
		update_user_meta( $user_id, 'gd_mover_status_log', $log );

		/* translators: %s: new mover status */
		$message = sprintf( __( 'Mover status updated to %s.', 'go-deliver' ), $new_status );

		wp_send_json_success(
			array(
				'message' => $message,
				'status'  => $new_status,
			)
		);
	}

	/**
	 * AJAX: adjust a mover's wallet balance.
	 */
	private function ajax_adjust_wallet() {
		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'go-deliver' ) ) );
		}

		$amount = isset( $_POST['amount'] ) ? (float) $_POST['amount'] : null;
		if ( null === $amount || 0.0 === $amount ) {
			wp_send_json_error( array( 'message' => __( 'Amount must be a non-zero number.', 'go-deliver' ) ) );
		}

		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );

		$current_balance = Go_Deliver_DB::get_wallet_balance( $user_id );
		$new_balance     = round( $current_balance + $amount, 2 );

		if ( $new_balance < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Adjustment would result in a negative balance.', 'go-deliver' ) ) );
		}

		Go_Deliver_DB::update_wallet_balance( $user_id, $new_balance );
		Go_Deliver_DB::log_transaction(
			$user_id,
			'adjustment',
			$amount,
			$description ?: __( 'Admin manual adjustment', 'go-deliver' )
		);

		wp_send_json_success(
			array(
				'message' => __( 'Wallet updated.', 'go-deliver' ),
				'balance' => $new_balance,
			)
		);
	}

	/**
	 * AJAX: approve or reject a single mover document.
	 */
	private function ajax_update_document_status() {
		$doc_id = absint( $_POST['doc_id'] ?? 0 );
		$status = sanitize_key( $_POST['status'] ?? '' );

		if ( ! $doc_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid document ID.', 'go-deliver' ) ) );
		}

		$allowed = array( 'approved', 'rejected', 'pending' );
		if ( ! in_array( $status, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid status.', 'go-deliver' ) ) );
		}

		$result = Go_Deliver_DB::update_document_status( $doc_id, $status );

		if ( false === $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update document status.', 'go-deliver' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Document status updated.', 'go-deliver' ) ) );
	}

	/**
	 * AJAX: update a mover's profile fields (admin-initiated).
	 */
	private function ajax_admin_update_mover_profile() {
		$user_id = absint( $_POST['user_id'] ?? 0 );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'go-deliver' ) ) );
		}

		// Core WP user fields.
		$first_name = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
		$last_name  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( $email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'go-deliver' ) ) );
		}

		// Check email uniqueness (only if changed).
		if ( $email ) {
			$existing = get_user_by( 'email', $email );
			if ( $existing && (int) $existing->ID !== $user_id ) {
				wp_send_json_error( array( 'message' => __( 'That email address is already in use.', 'go-deliver' ) ) );
			}
		}

		$user_data = array( 'ID' => $user_id );
		if ( $first_name ) {
			$user_data['first_name']    = $first_name;
			$user_data['display_name']  = $first_name . ( $last_name ? ' ' . $last_name : '' );
		}
		if ( $last_name ) {
			$user_data['last_name'] = $last_name;
		}
		if ( $email ) {
			$user_data['user_email'] = $email;
		}

		if ( count( $user_data ) > 1 ) {
			$result = wp_update_user( $user_data );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
		}

		// User meta fields.
		$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
		$suburb     = sanitize_text_field( wp_unslash( $_POST['base_suburb'] ?? '' ) );
		$base_lat   = isset( $_POST['base_lat'] ) && '' !== $_POST['base_lat'] ? (float) $_POST['base_lat'] : null;
		$base_lng   = isset( $_POST['base_lng'] ) && '' !== $_POST['base_lng'] ? (float) $_POST['base_lng'] : null;
		$radius     = isset( $_POST['radius'] ) ? absint( $_POST['radius'] ) : null;

		// Job types – restrict to the known set.
		$valid_types = array( 'trademe_pickup', 'item', 'move', 'vehicle', 'boat', 'piano', 'pet', 'junk', 'other' );
		$raw_types   = isset( $_POST['job_types'] ) && is_array( $_POST['job_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['job_types'] ) )
			: array();
		$job_types = array_values( array_intersect( $raw_types, $valid_types ) );

		update_user_meta( $user_id, 'gd_phone', $phone );
		update_user_meta( $user_id, 'gd_mover_base_suburb', $suburb );
		if ( null !== $base_lat ) {
			update_user_meta( $user_id, 'gd_mover_base_lat', $base_lat );
		}
		if ( null !== $base_lng ) {
			update_user_meta( $user_id, 'gd_mover_base_lng', $base_lng );
		}
		if ( null !== $radius ) {
			update_user_meta( $user_id, 'gd_mover_radius', $radius );
		}
		update_user_meta( $user_id, 'gd_mover_job_types', $job_types );

		wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'go-deliver' ) ) );
	}
}

