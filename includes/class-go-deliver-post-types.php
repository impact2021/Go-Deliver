<?php
/**
 * Registers custom post types for the plugin.
 *
 * @package Go_Deliver
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Go_Deliver_Post_Types
 *
 * Handles registration of the gd_job and gd_quote custom post types.
 */
class Go_Deliver_Post_Types {

	/**
	 * Register all custom post types.
	 * Hooked to the 'init' action.
	 */
	public function register_post_types() {
		$this->register_gd_job();
		$this->register_gd_quote();
	}

	// -------------------------------------------------------------------------
	// Private helpers.
	// -------------------------------------------------------------------------

	/**
	 * Register the 'gd_job' custom post type.
	 */
	private function register_gd_job() {
		$labels = array(
			'name'                  => _x( 'Jobs', 'Post type general name', 'go-deliver' ),
			'singular_name'         => _x( 'Job', 'Post type singular name', 'go-deliver' ),
			'menu_name'             => _x( 'Jobs', 'Admin menu text', 'go-deliver' ),
			'name_admin_bar'        => _x( 'Job', 'Add new on toolbar', 'go-deliver' ),
			'add_new'               => __( 'Add New', 'go-deliver' ),
			'add_new_item'          => __( 'Add New Job', 'go-deliver' ),
			'new_item'              => __( 'New Job', 'go-deliver' ),
			'edit_item'             => __( 'Edit Job', 'go-deliver' ),
			'view_item'             => __( 'View Job', 'go-deliver' ),
			'all_items'             => __( 'All Jobs', 'go-deliver' ),
			'search_items'          => __( 'Search Jobs', 'go-deliver' ),
			'parent_item_colon'     => __( 'Parent Jobs:', 'go-deliver' ),
			'not_found'             => __( 'No jobs found.', 'go-deliver' ),
			'not_found_in_trash'    => __( 'No jobs found in Trash.', 'go-deliver' ),
			'featured_image'        => __( 'Job Cover Image', 'go-deliver' ),
			'set_featured_image'    => __( 'Set cover image', 'go-deliver' ),
			'remove_featured_image' => __( 'Remove cover image', 'go-deliver' ),
			'use_featured_image'    => __( 'Use as cover image', 'go-deliver' ),
			'archives'              => __( 'Job archives', 'go-deliver' ),
			'insert_into_item'      => __( 'Insert into job', 'go-deliver' ),
			'uploaded_to_this_item' => __( 'Uploaded to this job', 'go-deliver' ),
			'items_list'            => __( 'Jobs list', 'go-deliver' ),
			'items_list_navigation' => __( 'Jobs list navigation', 'go-deliver' ),
			'filter_items_list'     => __( 'Filter jobs list', 'go-deliver' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Moving job requests submitted by customers.', 'go-deliver' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'go-deliver',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-car',
			'supports'            => array( 'title' ),
			'taxonomies'          => array(),
		);

		register_post_type( 'gd_job', $args );
	}

	/**
	 * Register the 'gd_quote' custom post type.
	 */
	private function register_gd_quote() {
		$labels = array(
			'name'                  => _x( 'Quotes', 'Post type general name', 'go-deliver' ),
			'singular_name'         => _x( 'Quote', 'Post type singular name', 'go-deliver' ),
			'menu_name'             => _x( 'Quotes', 'Admin menu text', 'go-deliver' ),
			'name_admin_bar'        => _x( 'Quote', 'Add new on toolbar', 'go-deliver' ),
			'add_new'               => __( 'Add New', 'go-deliver' ),
			'add_new_item'          => __( 'Add New Quote', 'go-deliver' ),
			'new_item'              => __( 'New Quote', 'go-deliver' ),
			'edit_item'             => __( 'Edit Quote', 'go-deliver' ),
			'view_item'             => __( 'View Quote', 'go-deliver' ),
			'all_items'             => __( 'All Quotes', 'go-deliver' ),
			'search_items'          => __( 'Search Quotes', 'go-deliver' ),
			'parent_item_colon'     => __( 'Parent Quotes:', 'go-deliver' ),
			'not_found'             => __( 'No quotes found.', 'go-deliver' ),
			'not_found_in_trash'    => __( 'No quotes found in Trash.', 'go-deliver' ),
			'featured_image'        => __( 'Quote Cover Image', 'go-deliver' ),
			'set_featured_image'    => __( 'Set cover image', 'go-deliver' ),
			'remove_featured_image' => __( 'Remove cover image', 'go-deliver' ),
			'use_featured_image'    => __( 'Use as cover image', 'go-deliver' ),
			'archives'              => __( 'Quote archives', 'go-deliver' ),
			'insert_into_item'      => __( 'Insert into quote', 'go-deliver' ),
			'uploaded_to_this_item' => __( 'Uploaded to this quote', 'go-deliver' ),
			'items_list'            => __( 'Quotes list', 'go-deliver' ),
			'items_list_navigation' => __( 'Quotes list navigation', 'go-deliver' ),
			'filter_items_list'     => __( 'Filter quotes list', 'go-deliver' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'Price quotes submitted by movers against customer jobs.', 'go-deliver' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'go-deliver',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'show_in_rest'        => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => 26,
			'menu_icon'           => 'dashicons-clipboard',
			'supports'            => array( 'title', 'custom-fields' ),
			'taxonomies'          => array(),
		);

		register_post_type( 'gd_quote', $args );
	}
}
