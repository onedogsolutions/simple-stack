<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This class handles app server.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_POSTS_APP_SERVER
 */
class SSTK_POSTS_APP_SERVER extends SSTK_Posts_Base {

	/* Include traits */
	use sstk_get_set_post_type;
	use sstk_metaboxes_for_taxonomies_for_servers_and_apps;
	use sstk_metaboxes_for_teams_for_servers_and_apps;
	use sstk_metaboxes_for_labels_notes_for_servers_and_apps;

	/**
	 * Instance function.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * POSTS_APP_SERVER constructor.
	 */
	public function __construct() {

		/**
		 * The global exclude server term array.
		 * This is needed because we're going to hook into the get_terms_args filter
		 * to filter out server group items based on permissions.
		 * We filter these out because certain server group items should
		 * not be shown to certain users - especially users who might be purchasing
		 * servers.
		 *
		 * The global is needed to prevent an infinite loop as we modify the data
		 * passed into the get_terms_args filter.
		 */
		$sstk_exclude_server_group = array();

		$this->register();  // register the custom post.
		$this->hooks();     // register hooks to make the custom post type do things...
		$this->init_hooks_for_taxonomies_for_servers_and_apps();    // located in the sstk_metaboxes_for_taxonomies_for_servers_and_apps trait file.
		$this->init_hooks_for_teams_for_servers_and_apps(); // located in the sstk_metaboxes_for_teams_for_servers_and_apps trait file.
		$this->init_hooks_for_labels_notes_for_servers_and_apps();  // located in the sstk_metaboxes_for_labels_notes_for_servers_and_apps trait file.

	}

	/**
	 * Hooks function.
	 */
	private function hooks() {

		// Meta box display callback.
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );

		// Save server meta values.
		add_action( 'save_post', array( $this, 'save_meta_values' ), 10, 2 );

		// Filter hook to add new columns.
		add_filter( 'manage_sstk_app_server_posts_columns', array( $this, 'app_server_table_head' ), 10, 1 );

		// Action hook to add values in new columns.
		add_action( 'manage_sstk_app_server_posts_custom_column', array( $this, 'app_server_table_content' ), 10, 2 );

		// Filter hook to add sortable columns.
		add_filter( 'manage_edit-sstk_app_server_sortable_columns', array( $this, 'app_server_table_sorting' ), 10, 1 );

		// Remove PRIVATE state label from certain custom post types - function is actually in ancestor class.
		add_filter( 'display_post_states', array( $this, 'display_post_states' ), 20, 2 );

		// Load up css and js scripts used for managing this cpt data screens.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10, 1 );

		// Filter the action links that show up when you hover over an item in the CPT list.
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );

		// Action hook to add prompt when a post is going to be deleted.
		add_action( 'admin_footer-edit.php', array( $this, 'sstk_app_trash_prompt' ) );
		add_action( 'admin_footer-post.php', array( $this, 'sstk_app_trash_prompt' ) );

		// Action hook to extend admin search.
		add_action( 'pre_get_posts', array( $this, 'sstk_app_server_extend_admin_search' ), 10, 1 );
		add_action( 'pre_get_posts', array( $this, 'sstk_app_server_meta_or_title_search' ), 10, 1 );

		// Filter hook to modify where clause.
		add_filter( 'posts_where', array( $this, 'sstk_app_server_posts_where' ), 10, 2 );

		// Action hook to extend admin filter options.
		add_action( 'restrict_manage_posts', array( $this, 'sstk_app_server_table_filtering' ) );

		// Filter hook to filter server listing on custom meta data.
		add_filter( 'parse_query', array( $this, 'sstk_app_server_parse_query' ), 10, 1 );

		// Filter hook to add custom meta box.
		add_filter( 'rwmb_meta_boxes', array( $this, 'sstk_app_server_register_meta_boxes' ), 10, 1 );

		// Action hook to change author for server type post.
		add_action( 'rwmb_sstk_change_server_owner_after_save_post', array( $this, 'sstk_change_server_owner_after_save_post' ), 10, 1 );

		// Action hook to check if user has permission to edit server.
		add_action( 'load-post.php', array( $this, 'sstk_app_server_load_post' ) );

		// Action hook to check if user has permission to delete server.
		add_action( 'wp_trash_post', array( $this, 'sstk_app_server_delete_post' ), 10, 1 );
		add_action( 'before_delete_post', array( $this, 'sstk_app_server_delete_post' ), 10, 1 );

		// Filter hook to change post count on server listing screen based on logged in users permissions.
		add_filter( 'views_edit-sstk_app_server', array( $this, 'sstk_app_server_custom_view_count' ), 10, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'sstk_app_server_new_site' ), 10, 2 );

		// Filter hook to remove delete link from server listing row if delete protection is enabled.
		add_filter( 'post_row_actions', array( $this, 'sstk_app_server_post_row_actions' ), 10, 2 );

		// Action hook to hide "Move to trash" link on server detail screen if delete protection is enabled.
		add_action( 'admin_head-post.php', array( $this, 'sstk_app_server_hide_delete_link' ) );

		// Filter hook to restrict trash post if delete protection is enabled.
		add_filter( 'pre_trash_post', array( $this, 'sstk_app_server_restrict_trash_post' ), 10, 2 );

		// Filter hook to restrict change server owner if user does not have right to change.
		add_action( 'rwmb_sstk_change_server_owner_before_save_post', array( $this, 'sstk_change_server_owner_before_save_post' ), 10, 1 );

		// Filter Hook to add custom column head on network sites listing.
		add_filter( 'wpmu_blogs_columns', array( $this, 'sstk_app_server_blogs_columns' ), 10, 1 );

		// Action Hook to add custom column content on network sites listing.
		add_action( 'manage_sites_custom_column', array( $this, 'sstk_app_server_sites_custom_column' ), 10, 2 );

		// Filter hook to remove delete bulk action if user is not admin or super admin.
		add_filter( 'bulk_actions-edit-sstk_app_server', array( $this, 'sstk_app_server_bulk_actions' ), 10, 1 );

		// Action hook to clean up server meta fields.
		add_action( 'wp_ajax_sstk_cleanup_servers', array( $this, 'sstk_cleanup_servers' ) );

		// Filter hook to add custom column header for sstk_app_server_group listing.
		add_filter( 'manage_edit-sstk_app_server_group_columns', array( $this, 'sstk_manage_sstk_app_server_group_columns' ) );

		// Filter hook to add custom column content for sstk_app_server_group listing.
		add_filter( 'manage_sstk_app_server_group_custom_column', array( $this, 'sstk_manage_sstk_app_server_group_columns_content' ), 10, 3 );

		// Action hook to add meta values for restored post.
		add_action( 'untrashed_post', array( $this, 'sstk_app_server_untrashed_post' ), 10, 1 );

		// Action hook to get all server terms - will be used to exclude certain items in the server group metabox.
		add_action( 'admin_head', array( $this, 'sstk_exclude_server_group_taxonomy_ids' ), 99 );

		// Filter hook to change the argument to exclude server terms - will be used to exclude certain items in the server group metabox.
		add_action( 'get_terms_args', array( $this, 'sstk_exclude_from_server_term_args' ), 1000, 2 );
	}

	/**
	 *
	 * Custom actions when you hover over a post link in a CPT list.
	 *
	 * @param array   $actions The list of actions.
	 * @param WP_Post $post The post object.
	 *
	 * @return array    Array of actions.
	 */
	public function row_actions( $actions, $post ) {
		if ( 'sstk_app_server' === $post->post_type ) {
			$actions = apply_filters( 'sstk_actions', $actions, $post->ID );
		}
		return $actions;
	}

	/*
	 * Register common styles/scripts for the servers/apps.
	 *
	 * The legacy select2 / magnific-popup / jQuery admin bundle that used to live here
	 * has been retired: the server and site screens are rendered by the Simple Stack
	 * React app, which enqueues its own bundle from assets/dist.
	 */
	public function enqueue_server_post_common_scripts() {
	}

	/**
	 * Register server post chart style/scripts.
	 *
	 * @global object $post
	 */
	public function enqueue_server_post_chart_scripts() {
		global $post;

		wp_register_script( 'sstk-chart-js', SSTK_URL . 'assets/js/Chart.min.js', array( 'jquery' ), SSTK_SCRIPTS_VERSION, true );

		wp_enqueue_script( 'sstk-wpapp-server-chart', SSTK_URL . 'includes/core/apps/wordpress-app/assets/js/sstk-wpapp-server-chart.js', array( 'sstk-chart-js' ), SSTK_SCRIPTS_VERSION, true );

		// Disk Statistics data.
		$disk_stat_data1 = SSTK_SERVER_STATISTICS()->sstk_app_server_get_formatted_disk_statistics( $post->ID );
		$disk_stat_data2 = array(
			'chart_labels' => array(
				'chart_main_title'       => __( 'Disk Space', 'simple-stack' ),
				'chart_column_1K_blocks' => __( '1K-blocks', 'simple-stack' ),
				'chart_column_Used'      => __( 'Used', 'simple-stack' ),
				'chart_column_Available' => __( 'Available', 'simple-stack' ),
			),
		);

		$params['disk_stat'] = array_merge( $disk_stat_data1, $disk_stat_data2 );

		// VNSTAT Traffic data.
		$vnstat_data1 = SSTK_SERVER_STATISTICS()->sstk_app_server_get_formatted_vnstat_data( $post->ID );
		$vnstat_data2 = array(
			'chart_labels' => array(
				'chart_curr_day_title'   => __( 'VNSTAT Traffic for Today (in KiB)', 'simple-stack' ),
				'chart_curr_month_title' => __( 'VNSTAT Traffic for Month (in KiB)', 'simple-stack' ),
				'chart_all_time_title'   => __( 'VNSTAT Traffic for All Time (in KiB)', 'simple-stack' ),
				'chart_rx_label'         => __( 'Rx', 'simple-stack' ),
				'chart_tx_label'         => __( 'Tx', 'simple-stack' ),
			),
		);

		$params['vnstat'] = array_merge( $vnstat_data1, $vnstat_data2 );

		// VMSTAT Memory data.
		$vmstat_data1 = SSTK_SERVER_STATISTICS()->sstk_app_server_get_formatted_vmstat_data( $post->ID );
		$vmstat_data2 = array(
			'chart_labels' => array(
				'chart_main_title'    => __( 'VMSTAT (Memory)', 'simple-stack' ),
				'chart_dataset_label' => __( 'Memory', 'simple-stack' ),
			),
		);

		$params['vmstat'] = array_merge( $vmstat_data1, $vmstat_data2 );

		wp_localize_script( 'sstk-wpapp-server-chart', 'sstk_server_stat_data', $params );

	}

	/**
	 * Register the scripts for the custom post type.
	 *
	 * @param string $hook hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'edit.php', 'post.php' ), true ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && 'sstk_app_server' === $screen->post_type ) {
				$this->enqueue_server_post_common_scripts();
			}
		}

		if ( in_array( $hook, array( 'post.php' ), true ) ) {
			global $post;
			$screen = get_current_screen();

			$disk_statistics = get_post_meta( $post->ID, 'sstk_wpapp_disk_statistics', true );

			if ( is_object( $screen ) && 'sstk_app_server' === $screen->post_type && ! empty( $disk_statistics ) ) {
				$this->enqueue_server_post_chart_scripts();
			}
		}

	}

	/**
	 * Get terms used to localize certain JS scripts
	 *
	 * @return array key-value pair for use with wp_localize_script
	 */
	public function get_js_terms() {

		$taking_too_long_message  = __( 'Well, this is embarassing.  It looks like this process is taking a very very long time which is unusual. So here are some things you might want to double-check:', 'simple-stack' );
		$taking_too_long_message .= '<br />';
		$taking_too_long_message .= '<br />' . __( '1. It is possible that there is a login issue or an api key issue. The two most common issues are incorrectly configured ssh keys and incorrectly configured api keys.', 'simple-stack' );
		$taking_too_long_message .= '<br />' . __( '2. Some cloud providers such as VULTR and UPCLOUD might require you to whitelist your IP address or explicitly require that you enable use of their API.', 'simple-stack' );
		$taking_too_long_message .= '<br />' . __( '3. Is WP-CRON running properly? Without it our background processes will not run and this process cannot be completed.', 'simple-stack' );
		$taking_too_long_message .= '<br />' . __( '4. Verify that ssh port 22 is open on the server on which the Simple Stack site is installed. Many commercial WordPress hosts do not allow direct ssh connections out of their WordPress servers.', 'simple-stack' );
		$taking_too_long_message .= '<br />' . __( '5. Check to see if there are any messages in your WordPress debug.log file.', 'simple-stack' );
		$taking_too_long_message .= '<br />' . __( '6. Check the SSH LOG screen for error messages that might provide a clue as to what\'s going on.', 'simple-stack' );
		$taking_too_long_message .= '<br />';
		$taking_too_long_message .= '<br />' . __( 'You can opt to cancel this process and restart it or you can wait a bit longer to see if it continues normally.', 'simple-stack' );
		$taking_too_long_message .= '<br />';
		$taking_too_long_message .= '<br />' . __( 'If you cancel this process you can delete the server from the server list and then try again.', 'simple-stack' );

		$server_install_feedback_1 = __( 'Just printing a little message here to let you know that we\'re still working on this...', 'simple-stack' );
		$server_install_feedback_2 = __( 'We\'re not going anywhere - still working...', 'simple-stack' );
		$server_install_feedback_3 = __( 'Sorry that it\'s taking a little longer than we\'d like...', 'simple-stack' );
		$server_install_feedback_4 = __( 'We\'re really cranking away here....', 'simple-stack' );
		$server_install_feedback_5 = __( 'We\'re continuing to work on this. Your next update will be in about 20 minutes...', 'simple-stack' );

		$return = array(
			'loading'                   => __( 'Loading', 'simple-stack' ) . '...',
			'invalid_server_name'       => __( 'Invalid server name.', 'simple-stack' ),
			'invalid_version'           => __( 'Invalid version. Please select or enter a valid WordPress version.', 'simple-stack' ),
			'taking_too_long'           => $taking_too_long_message,
			'server_install_feedback_1' => $server_install_feedback_1,
			'server_install_feedback_2' => $server_install_feedback_2,
			'server_install_feedback_3' => $server_install_feedback_3,
			'server_install_feedback_4' => $server_install_feedback_4,
			'server_install_feedback_5' => $server_install_feedback_5,
		);

		return $return;

	}

	/**
	 * Register the custom post type.
	 */
	public function register() {

		self::sstk_app_server_register_post_and_taxonomy();

		$this->set_post_taxonomy( 'sstk_app_server_group' );

		$this->set_group_key( 'sstk_server_group' );

		$this->set_post_type( 'sstk_app_server' );
	}

	/**
	 * Add APP Server table header sorting columns
	 *
	 * @param array $columns array of default head columns.
	 *
	 * @return $columns modified array with new columns
	 */
	public function app_server_table_sorting( $columns ) {

		$columns['sstk_server_provider']    = 'sstk_server_provider';
		$columns['sstk_server_region']      = 'sstk_server_region';
		$columns['sstk_server_instance_id'] = 'sstk_server_instance_id';
		$columns['sstk_server_initial_app'] = 'sstk_server_initial_app';
		$columns['sstk_local_status']       = 'sstk_local_status';
		$columns['sstk_server_app_count']   = 'sstk_server_app_count';
		$columns['sstk_server_group']       = 'sstk_server_group';
		$columns['sstk_server_owner']       = 'sstk_server_owner';

		return $columns;
	}

	/**
	 * Add contents to the APP SERVER table columns.
	 *
	 * @param string $column_name string column name.
	 * @param int    $post_id int post id.
	 *
	 * @return void returns nothing - prints column value instead.
	 */
	public function app_server_table_content( $column_name, $post_id ) {

		$value = '';
		switch ( $column_name ) {
			case 'sstk_server_short_desc':
				// Display the short description.
				$value = esc_html( get_post_meta( $post_id, 'sstk_short_description', true ) );
				break;
			case 'sstk_server_provider':
				// Provider.
				$provider = get_post_meta( $post_id, 'sstk_server_provider', true );

				// If provider is empty for some reason just break.
				if ( empty( $provider ) || is_wp_error( $provider ) ) {
					break;
				}

				// Provider api.
				$provider_api = SSTK()->get_provider_api( $provider );
				if ( empty( $provider_api ) || is_wp_error( $provider_api ) ) {
					break;
				}

				// Provider description.
				$provider_desc = SSTK()->sstk_get_cloud_provider_desc( $provider );

				// Region.
				$region = get_post_meta( $post_id, 'sstk_server_region', true );
				$region = $provider_api->get_region_description( $region );

				// Size.
				$size = get_post_meta( $post_id, 'sstk_server_size', true );
				if ( empty( $size ) ) {
					$size = get_post_meta( $post_id, 'sstk_server_size_raw', true );
				}
				if ( ! empty( $size ) || '0' === (string) $size ) {
					// A string value of '0' is valid so we include it in the above IF statement.
					$size = $provider_api->get_size_description( $size );
				}
				if ( empty( $size ) && ( '0' !== (string) $size ) ) {
					// A string value of '0' is valid so we excluded it in the above IF statement otherwise the empty check will pass because '0' is falsy.
					$size = 'unknown';
				}

				// Instance id.
				$instance_id = get_post_meta( $post_id, 'sstk_server_provider_instance_id', true );

				// ipv4 & ipv6 for display.
				$ips = SSTK_SERVER()->get_all_ip_addresses_for_display( $post_id );

				// Initial os.
				$initial_os = SSTK()->get_os_description( SSTK_SERVER()->get_server_os( $post_id ) );

				// Add Provider to final output.
				$value = strtoupper( $provider_desc );
				$value = $this->sstk_column_wrap_string_with_span_and_class( $value, 'provider', 'left' );
				$value = $this->sstk_column_wrap_string_with_div_and_class( $value, 'provider' );

				// Add initial OS to final output.
				if ( ! is_admin() ) {
					// On the front-end we want both a label and the os value.
					$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'OS:', 'simple-stack' ), 'initial_os', 'left' );
					$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $initial_os, 'initial_os', 'right' );
					$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'provider' );
				} else {
					// On the back-end we only want the value.
					$value2 = $this->sstk_column_wrap_string_with_span_and_class( $initial_os, 'initial_os', 'left' );
					$value .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'provider' );
				}

				// Add Region to final output.
				if ( ! boolval( sstk_get_option( 'sstk_server_list_region_column' ) ) ) {
					$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'Region: ', 'simple-stack' ), 'region', 'left' );
					$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $region, 'region', 'right' );
					$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'region' );
				}

				// Add size to final output.
				$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'Size: ', 'simple-stack' ), 'server_size', 'left' );
				$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $size, 'server_size', 'right' );
				$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'server_size' );

				// Add instance id to final output.
				if ( boolval( sstk_get_option( 'wordpress_app_show_server_instance_id_element_in_server_list' ) ) && ( ! boolval( sstk_get_option( 'sstk_server_list_instance_id_column' ) ) ) ) {
					if ( ( ! boolval( sstk_get_option( 'sstk_server_list_hide_instance_id_column' ) ) ) || sstk_is_admin() ) {
						$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'Instance ID: ', 'simple-stack' ), 'instance_id', 'left' );
						$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $instance_id, 'instance_id', 'right' );
						$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'instance_id' );
					}
				}

				// Add IPs to final output.
				if ( ! is_admin() ) {
					// On the front-end we want both a label and the os value.
					$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'IP:', 'simple-stack' ), 'ips', 'left' );
					$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $ips, 'ips', 'right' );
					$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'ips' );
				} else {
					// On the back-end we only want the value.
					$value2 = $this->sstk_column_wrap_string_with_span_and_class( $ips, 'ips', 'left' );
					$value .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'ips' );
				}

				break;
			case 'sstk_server_size':
				// not used right now.
				$value = get_post_meta( $post_id, 'sstk_server_size', true );
				if ( empty( $value ) ) {
					$value = get_post_meta( $post_id, 'sstk_server_size_raw', true );
				}
				if ( empty( $value ) && '0' !== (string) $value ) {
					// A string value of '0' is valid so we excluded it in the above IF statement.
					$value = 'unknown';
				}
				break;
			case 'sstk_server_region':
				$value = get_post_meta( $post_id, 'sstk_server_region', true );
				break;
			case 'sstk_server_instance_id':
				$value = get_post_meta( $post_id, 'sstk_server_provider_instance_id', true );
				break;
			case 'sstk_local_status':
				// Nothing here - instead individual app classes will use this filter to populate data about the server status.
				$value = apply_filters( 'sstk_app_server_admin_list_local_status_column', $value, $post_id );

				// If the server has been restored from trash, then there is likely no link to a live server.
				if ( boolval( get_post_meta( $post_id, 'sstk_wpapp_disconnected', true ) ) ) {
					$delete_text  = __( '***************************', 'simple-stack' ) . '<br />';
					$delete_text .= __( 'Record restored from trash.', 'simple-stack' ) . '<br />';
					$delete_text .= __( 'The Server likely has been deleted at your cloud provider.', 'simple-stack' ) . '<br />';
					$delete_text .= __( 'There is no link from this item to any live server.', 'simple-stack' ) . '<br />';
					$delete_text .= __( '***************************', 'simple-stack' ) . '<br />';
					if ( ! empty( $value ) ) {
						$value = '<br />' . $value . $delete_text;
					} else {
						$value = $delete_text;
					}
				} else {
					// If we have nothing in the $value field so far, therefore we should assume active.
					if ( empty( $value ) ) {
						$value = __( 'Active', 'simple-stack' );
					}
				}

				// Construct a classname based on whether the status is currently active or not.
				$partial_class_name = 'local_server_status';
				if ( __( 'Active', 'simple-stack' ) === $value ) {
					$partial_class_name = 'local_server_status_active';
				}

				// Wrap the status in a div with the constructed class name.
				$value = $this->sstk_column_wrap_string_with_div_and_class( $value, $partial_class_name );

				// If there is a state entered into the database, show it.
				$state = get_post_meta( $post_id, 'sstk_server_current_state', true );

				// provider.
				$provider = SSTK_SERVER()->get_server_provider( $post_id );

				$instance_id = SSTK_SERVER()->get_server_provider_instance_id( $post_id ); // instance id so the provider knows which one we're talking about.

				$remote_state_text = '';
				$provider_api      = SSTK()->get_provider_api( $provider );
				if ( $provider_api && is_object( $provider_api ) ) {
					// FYI: The remote state is set by the method AJAX_SERVER, case 'update-status' in file class-wordpress-app.php.
					// It is called when the admin clicks the UPDATE REMOTE STATE link in the server list.
					$remote_state_text = $provider_api->get_server_state_text( $state );
				}

				// Construct a classname based on whether the state is currently active or not.
				$partial_class_name = 'remote_server_state';
				if ( 'active' === $state ) {
					$partial_class_name = 'remote_server_state_active';
				}
				if ( 'in-progress' === $state ) {
					$partial_class_name = 'remote_server_state_in_progress';
				}

				// Wrap the state in our usual spans and divs with the constructed class name.
				if ( ! empty( $state ) ) {
					$value2  = $this->sstk_column_wrap_string_with_span_and_class( __( 'Remote State: ', 'simple-stack' ), $partial_class_name, 'left' );
					$value2 .= $this->sstk_column_wrap_string_with_span_and_class( $remote_state_text, $partial_class_name, 'right' );
					$value  .= $this->sstk_column_wrap_string_with_div_and_class( $value2, $partial_class_name );
				}

				// Construct a link to give the user the option to get the true remote status of the server.
				$link_html = sprintf(
					'<a href="" class="sstk-update-server-status" data-sstk-id="%1$s">%2$s</a>',
					$post_id,
					__( 'Update Remote State', 'simple-stack' )
				);
				$value2    = $this->sstk_column_wrap_string_with_span_and_class( $link_html, 'update_remote_state_link', 'left' );
				$value    .= $this->sstk_column_wrap_string_with_div_and_class( $value2, 'update_remote_state_link' );

				break;
			case 'sstk_server_initial_app':
				$value = get_post_meta( $post_id, 'sstk_server_initial_app_name', true );
				break;

			case 'sstk_server_ipv4':
				// not used right now.
				$value = SSTK_SERVER()->get_ipv4_address( $post_id );
				break;

			case 'sstk_server_ipv6':
				// not used right now.
				$value = SSTK_SERVER()->get_ipv6_address( $post_id );
				break;

			case 'sstk_server_app_count':
				$app_count                   = SSTK_SERVER()->get_app_count( $post_id );
				$url                         = is_admin() ? admin_url( 'edit.php?post_type=sstk_app&filter_action=Filter&sstk_app_server_dd=' . $post_id ) : get_permalink( SSTK_WORDPRESS_APP_PUBLIC::get_apps_list_page_id() ) . '?filter_action=Filter&_sstk_app_server_dd=' . $post_id;
				$app_count_label             = $this->sstk_column_wrap_string_with_span_and_class( __( 'App Count:', 'simple-stack' ), 'server_app_count', 'left' );
				$app_count_value_for_display = $this->sstk_column_wrap_string_with_span_and_class( $app_count, 'server_app_count', 'right' );
				$value                       = sprintf( '%s <a href="%s" target="_blank">%s</a>', $app_count_label, esc_url( $url ), $app_count_value_for_display );
				$value                       = $this->sstk_column_wrap_string_with_div_and_class( $value, 'server_app_count' );

				// Display sites underneath the post count.
				$num_apps = sstk_get_option( 'wordpress_app_app_limit_server_list' );
				if ( '' === (string) $num_apps ) {
					// Empty string means admin has not entered a value in settings so default it to four.
					// Important: Do NOT use empty() in the above conditional that triggered this block otherwise '0' will evaluate to empty which is NOT what we want here.
					$num_apps = 4;
				} else {
					$num_apps = (int) $num_apps;
				}

				if ( $num_apps > 0 ) {

					// Get sites.
					$args = array(
						'post_type'      => 'sstk_app',
						'post_status'    => 'private',
						'posts_per_page' => $num_apps,
						'fields'         => 'ids',
						'meta_query'     => array(
							array(
								'key'   => 'parent_post_id',
								'value' => $post_id,
							),
						),
					);

					$server_app_ids = get_posts( $args );

					if ( ! empty( $server_app_ids ) ) {

						$app_links = '';
						foreach ( $server_app_ids as $app_id ) {

							// This defines the break between lines.  Front-end will not have it.  Backend will.
							if ( is_admin() ) {
								$break_char = '<br />';
							} else {
								$break_char = '';
							}

							// If the site is available, show links.

							if ( sstk_is_admin() || sstk_user_can( get_current_user_id(), 'view_app', $app_id ) || (int) get_post_field( 'post_author', $app_id ) === get_current_user_id() ) {

								// Initialize string.
								$app_link = '';

								// Icons to navigate to front-end or wp-admin.  But only if the site is available.
								if ( true === SSTK_WORDPRESS_APP()->is_site_available_for_commands( true, $app_id ) ) {
									if ( 'wordpress-app' === (string) get_post_meta( $post_id, 'sstk_server_server-type', true ) ) {
										$app_link  = SSTK_WORDPRESS_APP()->get_formatted_wpadmin_link( $app_id, true );
										$app_link .= ' ' . SSTK_WORDPRESS_APP()->get_formatted_site_link( $app_id, '', true );
									}
								} else {
									// No icon - just a note that say 'pending'
									// Translators: %s is just a label with the word 'Pending' or similar.
									$app_link = sprintf( '[%s]', __( 'Pending', 'simple-stack' ) );
								}

								// Add in the site url label and link to management tabs.
								$url        = is_admin() ? admin_url( 'post.php?post=' . $app_id . '&action=edit' ) : get_permalink( $app_id );
								$app_link   = sprintf( $break_char . $app_link . ' ' . '<a href="%s" target="_blank">%s</a>', esc_url( $url ), sstk_get_the_title( $app_id ) );
								$app_link   = $this->sstk_column_wrap_string_with_span_and_class( $app_link, 'server_app_link', 'left' );
								$app_links .= $app_link;

							} else {
								$app_link   = sprintf( $break_char . '%s ', sstk_get_the_title( $app_id ) );
								$app_link   = $this->sstk_column_wrap_string_with_span_and_class( $app_link, 'server_app_link', 'left' );
								$app_links .= $app_link;
							}
						}

						$value .= $this->sstk_column_wrap_string_with_div_and_class( $app_links, 'server_app_links' );

					}
				}

				break;

			case 'sstk_server_group':
				$terms = get_the_terms( $post_id, 'sstk_app_server_group' );
				if ( ! empty( $terms ) ) {
					$value = '';
					foreach ( $terms as $term ) {
						$term_id   = $term->term_id;
						$term_name = $term->name;
						$color     = get_term_meta( $term_id, 'sstk_group_color', true );
						$url       = esc_url( add_query_arg( 'sstk_app_server_group', $term_id ) );
						$value    .= sprintf( '<a href="%s"><span class="sstk-app-server-app-group" style="background-color: %s">%s</span></a>', $url, $color, $term_name );
					}
				} else {
					$value = sprintf( '<span class="sstk-app-server-app-group" style="background-color: %s">%s</span>', 'gray', __( 'None', 'simple-stack' ) );
				}
				break;

			case 'sstk_server_owner':
				// Display the name of the owner who set up the server...
				$server_owner = esc_html( get_user_by( 'ID', get_post( $post_id )->post_author )->user_login );
				$value        = $server_owner; // @Todo: Make a hyperlink to user profile screens in admin.
				break;

			case 'sstk_assigned_teams':
				$sstk_assigned_teams = get_post_meta( $post_id, 'sstk_assigned_teams', false );

				$teams = array();
				if ( $sstk_assigned_teams ) {
					foreach ( $sstk_assigned_teams as $team ) {
						if ( 'trash' === get_post_status( $team ) || false === (bool) get_post_status( $team ) ) {
							continue;
						}

						$user_id         = get_current_user_id();
						$is_team_manager = sstk_check_user_is_team_manager( $user_id, $team );

						if ( current_user_can( 'sstk_manage_teams' ) && ( get_post( $team )->post_author === $user_id || $is_team_manager ) ) {
							$url     = admin_url( sprintf( 'post.php?post=%s&action=edit', $team ) );
							$teams[] = sprintf( '<a href="%s" target="_blank">%s</a>', $url, get_the_title( $team ) );
						} else {
							$teams[] = get_the_title( $team );
						}
					}

					$value = implode( ', ', $teams );
				} else {
					$value = __( 'No team assigned.', 'simple-stack' );
				}

				break;

		}

		$value = apply_filters( 'sstk_app_server_table_content', $value, $column_name, $post_id );

		echo $value;
	}

	/**
	 * Add APP SERVER table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function app_server_table_head( $defaults ) {

		unset( $defaults['date'] );

		// Title.
		$show_it = true;
		if ( ! is_admin() && ( boolval( sstk_get_option( 'wordpress_app_fe_hide_title_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( ! $show_it ) {
			unset( $defaults['title'] );
		} else {
			if ( ! is_admin() ) {
				$defaults['title'] = __( 'Server Name', 'simple-stack' );
			}
		}

		// Description.
		if ( boolval( sstk_get_option( 'sstk_show_server_list_short_desc' ) ) ) {
			$show_it = true;
			if ( ! is_admin() && ( ! boolval( sstk_get_option( 'wordpress_app_fe_show_description_in_server_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['sstk_server_short_desc'] = __( 'Description', 'simple-stack' );
			}
		}

		// Server Group.
		$show_it = true;
		if ( ! is_admin() && ( ! boolval( sstk_get_option( 'wordpress_app_fe_show_server_group_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['sstk_server_group'] = __( 'Server Group', 'simple-stack' );
		}

		// Server Actions.
		$show_it = true;
		if ( ! is_admin() && ( boolval( sstk_get_option( 'wordpress_app_fe_hide_server_actions_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['sstk_server_actions'] = __( 'Server Actions', 'simple-stack' );
		}

		// Server Provider.
		$show_it = true;
		if ( ! is_admin() && ( boolval( sstk_get_option( 'wordpress_app_fe_hide_provider_details_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['sstk_server_provider'] = __( 'Provider Details', 'simple-stack' );
		}

		// Server Region.
		if ( boolval( sstk_get_option( 'sstk_server_list_region_column' ) ) ) {
			$defaults['sstk_server_region'] = __( 'Region', 'simple-stack' );
		}

		// Instance ID.
		if ( boolval( sstk_get_option( 'sstk_server_list_instance_id_column' ) ) ) {
			$defaults['sstk_server_instance_id'] = __( 'Instance ID', 'simple-stack' );
		}

		// Local Status.
		$show_it = true;
		if ( ! is_admin() && ( boolval( sstk_get_option( 'wordpress_app_fe_hide_local_status_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['sstk_local_status'] = __( 'Local Status', 'simple-stack' );
		}

		// Server Type.
		if ( boolval( sstk_get_option( 'sstk_show_server_list_server_type' ) ) ) {
			$defaults['sstk_server_initial_app'] = __( 'Initial App/<br />Server Type', 'simple-stack' );
		}

		// App Count.
		$show_it = true;
		if ( ! is_admin() && ( boolval( sstk_get_option( 'wordpress_app_fe_hide_app_count_in_server_list' ) ) ) ) {
			$show_it = false;
		}
		if ( $show_it ) {
			$defaults['sstk_server_app_count'] = __( 'Apps', 'simple-stack' );
		}

		// Date.
		if ( boolval( sstk_get_option( 'sstk_show_server_list_date' ) ) ) {
			$defaults['date'] = __( 'Date', 'simple-stack' );
		}

		// Server owner.
		if ( boolval( sstk_get_option( 'sstk_show_server_list_owner' ) ) ) {
			$show_it = true;
			if ( ! is_admin() && ( ! boolval( sstk_get_option( 'wordpress_app_fe_show_owner_in_server_list' ) ) ) ) {
				$show_it = false;
			}

			if ( $show_it ) {
				if ( sstk_is_admin() || ( ! sstk_is_admin() && ! boolval( sstk_get_option( 'sstk_hide_server_list_owner_non_admins' ) ) ) ) {
					$defaults['sstk_server_owner'] = __( 'Owner', 'simple-stack' );
				}
			}
		}

		// Teams.
		if ( boolval( sstk_get_option( 'sstk_show_server_list_team' ) ) ) {
			$show_it = true;
			if ( ! is_admin() && ( ! boolval( sstk_get_option( 'wordpress_app_fe_show_teams_in_server_list' ) ) ) ) {
				$show_it = false;
			}
			if ( $show_it ) {
				$defaults['sstk_assigned_teams'] = __( 'Teams', 'simple-stack' );
			}
		}

		return $defaults;
	}

	/**
	 * Register meta box(es).
	 */
	public function meta_boxes() {

		/* Only render for true admins! */
		if ( ! sstk_is_admin() ) {
			return;
		}

		/* Only render if the settings option is turned on. */
		if ( ! (bool) sstk_get_option( 'show-advanced-metaboxes' ) ) {
			return;
		}

		// Add APP SERVER detail meta box into APP SERVER custom post type.
		add_meta_box(
			'sstk_server_detail',
			__( 'Server Details', 'simple-stack' ),
			array( $this, 'render_app_server_details_meta_box' ),
			'sstk_app_server',
			'advanced',
			'low'
		);
	}

	/**
	 * Render the APP SERVER detail meta box
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_app_server_details_meta_box( $post ) {

		$html = '';

		$sstk_server_title                = $post->post_title;
		$sstk_server_region               = get_post_meta( $post->ID, 'sstk_server_region', true );
		$sstk_server_size                 = get_post_meta( $post->ID, 'sstk_server_size', true );
		$sstk_server_size_raw             = get_post_meta( $post->ID, 'sstk_server_size_raw', true );
		$sstk_server_ipv4                 = get_post_meta( $post->ID, 'sstk_server_ipv4', true );
		$sstk_server_ipv6                 = get_post_meta( $post->ID, 'sstk_server_ipv6', true );
		$sstk_server_name                 = get_post_meta( $post->ID, 'sstk_server_name', true );
		$sstk_server_provider             = get_post_meta( $post->ID, 'sstk_server_provider', true );
		$sstk_server_provider_instance_id = get_post_meta( $post->ID, 'sstk_server_provider_instance_id', true );
		$sstk_server_created              = get_post_meta( $post->ID, 'sstk_server_created', true );
		$sstk_server_parent_post_id       = get_post_meta( $post->ID, 'sstk_server_parent_post_id', true );
		$sstk_server_scripts_version      = SSTK_WORDPRESS_APP()->get_configured_scripts_version();

		$sstk_server_init                   = get_post_meta( $post->ID, 'sstk_server_init', true );
		$sstk_server_initial_app_name       = get_post_meta( $post->ID, 'sstk_server_initial_app_name', true );
		$sstk_server_plugin_initial_version = get_post_meta( $post->ID, 'sstk_server_plugin_initial_version', true );
		$sstk_server_plugin_updated_version = get_post_meta( $post->ID, 'sstk_server_plugin_updated_version', true );
		$sstk_server_server_type            = get_post_meta( $post->ID, 'sstk_server_server-type', true );
		$sstk_server_webserver_type         = get_post_meta( $post->ID, 'sstk_server_webserver_type', true );
		$sstk_server_initial_app_name       = get_post_meta( $post->ID, 'sstk_server_initial_app_name', true );

		$sstk_server_action_status              = get_post_meta( $post->ID, 'sstk_server_action_status', true );
		$sstk_server_action                     = get_post_meta( $post->ID, 'sstk_server_action', true );
		$sstk_server_after_create_action_app_id = get_post_meta( $post->ID, 'sstk_server_after_create_action_app_id', true );
		$sstk_server_command_mutex              = get_post_meta( $post->ID, 'sstk_command_mutex', true );  // Notice the lack of "server" in this post meta field.
		$sstk_server_last_upgrade_done          = get_post_meta( $post->ID, 'sstk_last_upgrade_done', true ); // Notice the lack of "server" in this post meta field.

		/* The deferred action field is an array/serialized read-only field */
		$sstk_server_last_deferred_action_source = get_post_meta( $post->ID, 'sstk_server_last_deferred_action_source', true );
		if ( ! empty( $sstk_server_last_deferred_action_source ) && is_array( $sstk_server_last_deferred_action_source ) ) {
			$sstk_server_last_deferred_action_source_string = '';
			foreach ( $sstk_server_last_deferred_action_source as $key => $value ) {

				/**
				 * Create a key-value string that looks like this for display:
				 * 2020-03-21 19:55:45: wordpress-app
				 * 2020-03-21 19:56:13: wordpress-app
				 * 2020-03-21 19:56:14: wordpress-app
				 * 2020-03-21 19:56:16: wordpress-app
				 */

				$action_date = null;
				try {
					$strdate     = (int) trim( $key );
					$action_date = new DateTime( "@$strdate" );  // Key should be a unix epoch date string.
				} catch ( Exception $e ) {
					// just include a note in the string and add the raw data.
					$sstk_server_last_deferred_action_source_string .= '<br />' . __( 'recovering from datetime conversion exception, showing raw data for this item below', 'simple-stack' );
					$sstk_server_last_deferred_action_source_string .= '<br />' . $key . ': ' . $value;
					continue;
				}

				if ( $action_date ) {
					if ( empty( $sstk_server_last_deferred_action_source_string ) ) {
						$sstk_server_last_deferred_action_source_string .= $action_date->format( 'Y-m-d H:i:s' ) . ': ' . $value;
					} else {
						$sstk_server_last_deferred_action_source_string .= '<br />' . $action_date->format( 'Y-m-d H:i:s' ) . ': ' . $value;
					}
				} else {
					$sstk_server_last_deferred_action_source_string .= '<br />' . $key . ': ' . $value;
				}
			}
			$sstk_server_last_deferred_action_source = $sstk_server_last_deferred_action_source_string;  // reassign the new string to the orignal array variable.
		}

		/* The server_actions field is an array/serialized read-only field */
		$sstk_server_actions = get_post_meta( $post->ID, 'sstk_server_actions', true );
		if ( ! empty( $sstk_server_actions ) && is_array( $sstk_server_actions ) ) {
			$sstk_server_actions_string = '';
			foreach ( $sstk_server_actions as $key => $value ) {
				$action_date = null;
				$action      = null;

				/**
				 * Create a key-value string that looks like this for display:
				 * 2020-03-21 19:55:45: created
				 * 2020-03-21 19:56:13: stuff1
				 * 2020-03-21 19:56:14: stuff2
				 */

				try {
					$action_date = new DateTime( "@$value" );  // Value should be a unix epoch date string.
					$action      = $key;
					// $action_date = new DateTime( "@$key" );  // This causes an exception - uncomment to cause an exception for testing.
				} catch ( Exception $e ) {
					// oops - problem - maybe the key values are reversed which they are starting in V4.0.0 of SSTK.
					try {
						$action_date = new DateTime( "@$key" );  // Value should be a unix epoch date string.
						$action      = $value;
					} catch ( Exception $e ) {
						// still not ok so just include a note in the string and add the raw data.
						$sstk_server_actions_string .= '<br />' . __( 'recovering from datetime conversion exception, showing raw data for this item below', 'simple-stack' );
						$sstk_server_actions_string .= '<br />' . $value . ': ' . $key;
						continue;
					}
				}

				if ( $action_date ) {
					if ( empty( $sstk_server_actions_string ) ) {
						$sstk_server_actions_string .= $action_date->format( 'Y-m-d H:i:s' ) . ': ' . $action;
					} else {
						$sstk_server_actions_string .= '<br />' . $action_date->format( 'Y-m-d H:i:s' ) . ': ' . $action;
					}
				} else {
					$sstk_server_actions_string .= '<br />' . $value . ': ' . $key;
				}
			}
			$sstk_server_actions = $sstk_server_actions_string;   // reassign the new string to the orignal array variable.

		}
		/* End read-only fields */

		// Convert created date into more readable format.
		if ( ! empty( $sstk_server_created ) ) {
			$sstk_server_created = gmdate( 'Y-m-d H:i:s', strtotime( $sstk_server_created ) );
		}

		ob_start();
		require SSTK_PATH . 'includes/templates/server_details.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;
	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_values( $post_id, $post ) {

		// Add nonce for security and authentication.
		$nonce_name   = sanitize_text_field( filter_input( INPUT_POST, 'sstk_meta', FILTER_UNSAFE_RAW ) );
		$nonce_action = 'sstk_server_nonce_meta_action';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Make sure post type is sstk_app_server.
		if ( 'sstk_app_server' !== $post->post_type ) {
			return;
		}

		$sstk_server_title                = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_title', FILTER_UNSAFE_RAW ) );
		$sstk_server_region               = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_region', FILTER_UNSAFE_RAW ) );
		$sstk_server_size                 = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_size', FILTER_UNSAFE_RAW ) );
		$sstk_server_size_raw             = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_size_raw', FILTER_UNSAFE_RAW ) );
		$sstk_server_ipv4                 = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_ipv4', FILTER_UNSAFE_RAW ) );
		$sstk_server_ipv6                 = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_ipv6', FILTER_UNSAFE_RAW ) );
		$sstk_server_name                 = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_name', FILTER_UNSAFE_RAW ) );
		$sstk_server_provider             = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_provider', FILTER_UNSAFE_RAW ) );
		$sstk_server_provider_instance_id = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_provider_instance_id', FILTER_UNSAFE_RAW ) );
		$sstk_server_created              = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_created', FILTER_UNSAFE_RAW ) );
		$sstk_server_parent_post_id       = filter_input( INPUT_POST, 'sstk_server_parent_post_id', FILTER_SANITIZE_NUMBER_INT );
		$sstk_server_scripts_version      = SSTK_WORDPRESS_APP()->get_configured_scripts_version();

		$sstk_server_init                   = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_init', FILTER_UNSAFE_RAW ) );
		$sstk_server_initial_app_name       = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_initial_app_name', FILTER_UNSAFE_RAW ) );
		$sstk_server_plugin_initial_version = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_plugin_initial_version', FILTER_UNSAFE_RAW ) );
		$sstk_server_plugin_updated_version = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_plugin_updated_version', FILTER_UNSAFE_RAW ) );
		$sstk_server_server_type            = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_server-type', FILTER_UNSAFE_RAW ) );
		$sstk_server_webserver_type         = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_webserver_type', FILTER_UNSAFE_RAW ) );

		$sstk_server_action_status              = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_action_status', FILTER_UNSAFE_RAW ) );
		$sstk_server_action                     = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_action', FILTER_UNSAFE_RAW ) );
		$sstk_server_after_create_action_app_id = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_after_create_action_app_id', FILTER_UNSAFE_RAW ) );
		$sstk_server_command_mutex              = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_command_mutex', FILTER_UNSAFE_RAW ) );
		$sstk_server_last_upgrade_done          = sanitize_text_field( filter_input( INPUT_POST, 'sstk_server_last_upgrade_done', FILTER_UNSAFE_RAW ) );

		$sstk_server_owner = filter_input( INPUT_POST, 'sstk_server_owner', FILTER_SANITIZE_NUMBER_INT );

		if ( ! empty( $sstk_server_created ) ) {
			$timestamp                      = strtotime( $sstk_server_created );
			$sstk_server_created            = gmdate( 'Y-m-dTH:i:s', $timestamp );
			$sstk_server_actions['created'] = $timestamp;
		}

		update_post_meta( $post_id, 'sstk_server_region', $sstk_server_region );
		update_post_meta( $post_id, 'sstk_server_size', $sstk_server_size );
		update_post_meta( $post_id, 'sstk_server_size_raw', $sstk_server_size_raw );
		update_post_meta( $post_id, 'sstk_server_ipv4', $sstk_server_ipv4 );
		update_post_meta( $post_id, 'sstk_server_ipv6', $sstk_server_ipv6 );
		update_post_meta( $post_id, 'sstk_server_name', $sstk_server_name );
		update_post_meta( $post_id, 'sstk_server_provider', $sstk_server_provider );
		update_post_meta( $post_id, 'sstk_server_provider_instance_id', $sstk_server_provider_instance_id );

		update_post_meta( $post_id, 'sstk_server_init', $sstk_server_init );
		update_post_meta( $post_id, 'sstk_server_initial_app_name', $sstk_server_initial_app_name );
		update_post_meta( $post_id, 'sstk_server_plugin_initial_version', $sstk_server_plugin_initial_version );
		update_post_meta( $post_id, 'sstk_server_plugin_updated_version', $sstk_server_plugin_updated_version );
		update_post_meta( $post_id, 'sstk_server_server-type', $sstk_server_server_type );
		update_post_meta( $post_id, 'sstk_server_webserver_type', $sstk_server_webserver_type );

		update_post_meta( $post_id, 'sstk_server_created', $sstk_server_created );
		update_post_meta( $post_id, 'sstk_server_parent_post_id', $sstk_server_parent_post_id );
		update_post_meta( $post_id, 'sstk_server_actions', $sstk_server_actions );
		update_post_meta( $post_id, 'sstk_server_scripts_version', $sstk_server_scripts_version );
		update_post_meta( $post_id, 'sstk_server_action_status', $sstk_server_action_status );
		update_post_meta( $post_id, 'sstk_server_action', $sstk_server_action );
		update_post_meta( $post_id, 'sstk_server_after_create_action_app_id', $sstk_server_after_create_action_app_id );

		// Notice that these two fields do not have the word "server" in it.
		update_post_meta( $post_id, 'sstk_command_mutex', $sstk_server_command_mutex );
		update_post_meta( $post_id, 'sstk_last_upgrade_done', $sstk_server_last_upgrade_done );

		// Update the server title.
		$post->post_title = $sstk_server_title;

		// Update the post author.
		$post->post_author = $sstk_server_owner;

		remove_action( 'save_post', array( $this, 'save_meta_values' ), 10 ); // remove hook to prevent infinite loop.
		wp_update_post( $post );
		add_action( 'save_post', array( $this, 'save_meta_values' ), 10, 2 ); // re-add hook.
	}

	/**
	 * Return prompt messages while deleting/restoring a server
	 *
	 * @return array
	 */
	public function sstk_app_trash_prompt_messages() {
		return array(
			'delete'  => apply_filters( 'sstk_server_delete_prompt', __( 'ALL data on this server will be LOST! Are you really really sure you want to proceed and delete this server?', 'simple-stack' ) ),
			'restore' => __( 'Please note: Rstoring this item will not restore your server or your server data - it will just be an orphaned record without a connection to any server.', 'simple-stack' ),
		);
	}

	/**
	 * Confirmation prompt for all trash actions on server list/detail screen.
	 *
	 * Action hook: admin_footer-edit.php
	 * Action hook: admin_footer-post.php
	 *
	 * @return true
	 */
	public function sstk_app_trash_prompt() {
		$messages = $this->sstk_app_trash_prompt_messages();
		$screen   = get_current_screen();
		if ( in_array( $screen->id, array( 'edit-sstk_app_server', 'sstk_app_server' ), true ) ) {
			$prompt_message = isset( $messages['delete'] ) ? $messages['delete'] : '';
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('a.submitdelete').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
						if(r){
							window.location = href;
						}
					});

					$('#doaction').click(function(e){
						if($('#bulk-action-selector-top').val() == 'trash'){
							if($('input[name="post[]"]:checked').length > 0){
								var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
								if(!r){
									e.preventDefault();
								}
							}
						}
					});
				});
			</script>
			<?php
		}

		if ( 'edit-sstk_app_server' === $screen->id ) {
			$prompt_message = isset( $messages['restore'] ) ? $messages['restore'] : '';
			?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
					$('span.untrash a').click(function(e){
						e.preventDefault();
						var href = $(this).attr('href');
						var r = confirm('<?php echo esc_html( $prompt_message ); ?>');
						if(r){
							window.location = href;
						}
					});
				});
			</script>
			<?php
		}
		return true;
	}

	/**
	 * Enhance search for server listing screen
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param object $query query object.
	 *
	 * @return null
	 */
	public function sstk_app_server_extend_admin_search( $query ) {

		global $typenow;

		// use your post type.
		$post_type = 'sstk_app_server';

		if ( is_admin() && $typenow === $post_type && $query->is_search() ) {

			// Use your Custom fields/column name to search for.
			$search_fields = array(
				'sstk_server_provider',
				'sstk_server_size',
				'sstk_server_size_raw',
				'sstk_server_ipv4',
				'sstk_server_ipv6',
				'sstk_server_region',
				'sstk_server_provider_instance_id',
				'sstk_server_current_state',
				'sstk_server_initial_app_name',
				'sstk_short_description',
				'sstk_long_description',
			);

			$search_term = $query->query_vars['s'];

			if ( '' !== $search_term ) {
				$meta_query = array( 'relation' => 'OR' );

				foreach ( $search_fields as $search_field ) {
					array_push(
						$meta_query,
						array(
							'key'     => $search_field,
							'value'   => $search_term,
							'compare' => 'LIKE',
						)
					);
				}

				// Use an 'OR' comparison for each additional custom meta field.
				if ( count( $meta_query ) > 1 ) {
					$meta_query['relation'] = 'OR';
				}
				// Set the meta_query parameter.
				$query->set( 'meta_query', $meta_query );

				// To allow the search to also return "OR" results on the post_title.
				$query->set( '_meta_or_title', $search_term );
			};
		} else {
			return;
		}

	}

	/**
	 * Meta or title search for sstk_app_server post type
	 *
	 * Action hook: pre_get_posts
	 *
	 * @param object $query query object.
	 */
	public function sstk_app_server_meta_or_title_search( $query ) {

		global $typenow;

		$post_type = 'sstk_app_server';
		$title     = $query->get( '_meta_or_title' );
		if ( is_admin() && $typenow === $post_type && $query->is_search() && $title ) {
			add_filter(
				'get_meta_sql',
				function( $sql ) use ( $title ) {
					global $wpdb;

					// Only run once.
					static $nr = 0;
					if ( 0 !== $nr++ ) {
						return $sql;
					}

					// Modified WHERE.
					$sql['where'] = sprintf(
						' AND ( (%s) OR (%s) ) ',
						$wpdb->prepare( "{$wpdb->posts}.post_title LIKE '%%%s%%'", $title ),
						mb_substr( $sql['where'], 5, mb_strlen( $sql['where'] ) )
					);

					return $sql;
				}
			);
		}
	}

	/**
	 * Change where clause for sql query.
	 *
	 * Action hook: posts_where
	 *
	 * @param  string $where where string.
	 * @param  object $wp_query wp_query object.
	 *
	 * @return string
	 */
	public function sstk_app_server_posts_where( $where, $wp_query ) {
		global $wpdb, $typenow;

		$post_type = 'sstk_app_server';

		if ( is_admin() && $typenow === $post_type && $wp_query->is_search() ) {
			if ( isset( $wp_query->query_vars['_meta_or_title'] ) && '' !== $wp_query->query_vars['_meta_or_title'] ) {

				$_meta_or_title = $wp_query->query_vars['_meta_or_title'];
				$_meta_or_title = trim( $_meta_or_title );

				if ( strpos( $_meta_or_title, ' ' ) !== false ) {
					$search_terms = explode( ' ', $_meta_or_title );

					foreach ( $search_terms as $search_term ) {
						$find[] = '((' . $wpdb->posts . '.post_title LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_excerpt LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_content LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $search_term ) . '%' ) . '\'))';
					}

					$find = implode( ' AND ', $find );
					$find = sprintf( 'AND (%s) ', $find );

					$replace = '';
					$where   = str_replace( $find, $replace, $where );

				} else {
					$find    = 'AND (((' . $wpdb->posts . '.post_title LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_excerpt LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\') OR (' . $wpdb->posts . '.post_content LIKE \'' . esc_sql( '%' . $wpdb->esc_like( $_meta_or_title ) . '%' ) . '\')))';
					$replace = '';
					$where   = str_replace( $find, $replace, $where );
				}
			}
		}
		return $where;
	}

	/**
	 * Add filters on the server listing screen at the backend
	 *
	 * Action hook: restrict_manage_posts
	 *
	 * @return void
	 */
	public function sstk_app_server_table_filtering() {

		global $typenow, $pagenow;

		$post_type = 'sstk_app_server';

		if ( ( is_admin() && $pagenow === 'edit.php' && $typenow === $post_type ) || SSTK_WORDPRESS_APP_PUBLIC::is_servers_list_page() ) {

			$providers = $this->generate_meta_dropdown( $post_type, 'sstk_server_provider', __( 'All Providers', 'simple-stack' ) );
			echo sstk_kses_select( $providers );

			$regions = $this->generate_meta_dropdown( $post_type, 'sstk_server_region', __( 'All Regions', 'simple-stack' ) );
			echo sstk_kses_select( $regions );

			$local_status = $this->generate_meta_dropdown( $post_type, 'sstk_server_current_state', __( 'Local Status', 'simple-stack' ) );
			echo sstk_kses_select( $local_status );

			$taxonomy     = 'sstk_app_server_group';
			$server_group = $this->generate_term_dropdown( $taxonomy, __( 'Server Groups', 'simple-stack' ) );
			echo sstk_kses_select( $server_group );

			$ipv4 = $this->generate_meta_dropdown( $post_type, 'sstk_server_ipv4', __( 'All IPv4', 'simple-stack' ) );
			echo sstk_kses_select( $ipv4 );

			if ( sstk_get_early_option( 'sstk_show_ipv6' ) ) {
				$ipv6 = $this->generate_meta_dropdown( $post_type, 'sstk_server_ipv6', __( 'All IPv6', 'simple-stack' ) );
				echo sstk_kses_select( $ipv6 );
			}

			$server_owners = $this->generate_owner_dropdown( $post_type, 'sstk_server_owner', __( 'Server Owners', 'simple-stack' ) );
			echo sstk_kses_select( $server_owners );

			$restart_needed = $this->generate_meta_dropdown( $post_type, 'sstk_server_restart_needed', __( 'Restart Needed', 'simple-stack' ) );
			echo sstk_kses_select( $restart_needed );

			$web_server_type = $this->generate_meta_dropdown( $post_type, 'sstk_server_webserver_type', __( 'Web Server', 'simple-stack' ) );
			echo sstk_kses_select( $web_server_type );
		}
	}

	/**
	 * To modify default query parameters and to show server listing based on custom filters
	 *
	 * Action filter: parse_query
	 *
	 * @param object $query query object.
	 *
	 * @return void
	 */
	public function sstk_app_server_parse_query( $query ) {

		global $pagenow;

		$filter_action = sanitize_text_field( filter_input( INPUT_GET, 'filter_action', FILTER_UNSAFE_RAW ) );

		if ( ( ( is_admin() && $query->is_main_query() && $pagenow == 'edit.php' ) || sstk_is_public_servers_list_query( $query ) ) && $query->query['post_type'] == 'sstk_app_server' && ! sstk_is_admin() ) {
			$qv          = &$query->query_vars;
			$post_status = sanitize_text_field( filter_input( INPUT_GET, 'post_status', FILTER_UNSAFE_RAW ) );
			$post_status = ! empty( $post_status ) ? $post_status : 'private';
			$post__in    = sstk_get_posts_by_permission( 'view_server', 'sstk_app_server', $post_status );

			if ( count( $post__in ) ) {
				$qv['post__in'] = $post__in;
			} else {
				$qv['post__in'] = array( 0 );
			}
		}

		if ( ( ( is_admin() && $query->is_main_query() && $pagenow === 'edit.php' ) || sstk_is_public_servers_list_query( $query ) ) && $query->query['post_type'] === 'sstk_app_server' && ( ! empty( $filter_action ) ) ) {
			$qv = &$query->query_vars;

			// SERVER PROVIDER.
			if ( isset( $_GET['sstk_server_provider'] ) && ! empty( $_GET['sstk_server_provider'] ) ) {
				$server_provider = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_provider', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_provider',
					'value'   => $server_provider,
					'compare' => '=',
				);
			}

			// REGION.
			if ( isset( $_GET['sstk_server_region'] ) && ! empty( $_GET['sstk_server_region'] ) ) {
				$region = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_region', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_region',
					'value'   => $region,
					'compare' => '=',
				);
			}

			// LOCAL STATUS.
			if ( isset( $_GET['sstk_server_current_state'] ) && ! empty( $_GET['sstk_server_current_state'] ) ) {
				$local_status = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_current_state', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_current_state',
					'value'   => $local_status,
					'compare' => '=',
				);
			}

			// SERVER GROUP.
			if ( isset( $_GET['sstk_app_server_group'] ) && ! empty( $_GET['sstk_app_server_group'] ) ) {
				$term_id = filter_input( INPUT_GET, 'sstk_app_server_group', FILTER_SANITIZE_NUMBER_INT );

				$qv['tax_query'] = array(
					'relation' => 'OR',
					array(
						'taxonomy' => 'sstk_app_server_group',
						'field'    => 'term_id',
						'terms'    => array( (int) $term_id ),
					),
				);

			}

			// IPv4.
			if ( isset( $_GET['sstk_server_ipv4'] ) && ! empty( $_GET['sstk_server_ipv4'] ) ) {
				$ipv4 = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_ipv4', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_ipv4',
					'value'   => $ipv4,
					'compare' => '=',
				);
			}

			// IPv6.
			if ( isset( $_GET['sstk_server_ipv6'] ) && ! empty( $_GET['sstk_server_ipv6'] ) ) {
				$ipv6 = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_ipv6', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_ipv6',
					'value'   => $ipv6,
					'compare' => '=',
				);

				// Make sure the field exists otherwise all servers will be returned.  Older servers do not have this value and for some reason WP queries will treat empty values as matching the filter.
				$qv['meta_query'][] = array(
					'key'     => 'sstk_server_ipv6',
					'compare' => 'EXISTS',
				);
			}

			// SERVER OWNER.
			if ( isset( $_GET['sstk_server_owner'] ) && ! empty( $_GET['sstk_server_owner'] ) ) {
				$sstk_server_owner = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_owner', FILTER_UNSAFE_RAW ) );

				$qv['author'] = $sstk_server_owner;

			}

			// RESTART NEEDED.
			if ( isset( $_GET['sstk_server_restart_needed'] ) && ! empty( $_GET['sstk_server_restart_needed'] ) ) {
				$restart_needed = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_restart_needed', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_restart_needed',
					'value'   => $restart_needed,
					'compare' => '=',
				);

				// Make sure the field exists otherwise all servers will be returned.  Servers with older callbacks will not have this value and for some reason WP queries will treat empty values as matching the filter.
				$qv['meta_query'][] = array(
					'key'     => 'sstk_server_restart_needed',
					'compare' => 'EXISTS',
				);
			}

			// WEB SERVER TYPE.
			if ( isset( $_GET['sstk_server_webserver_type'] ) && ! empty( $_GET['sstk_server_webserver_type'] ) ) {
				$web_server_type = sanitize_text_field( filter_input( INPUT_GET, 'sstk_server_webserver_type', FILTER_UNSAFE_RAW ) );

				$qv['meta_query'][] = array(
					'field'   => 'sstk_server_webserver_type',
					'value'   => $web_server_type,
					'compare' => '=',
				);

				// Make sure the field exists otherwise all servers will be returned.  Older servers might not have this value and for some reason WP queries will treat empty values as matching the filter.
				// THe side effect of this is that if the sstk_server_webserver_type meta does not exist, the record will not be returned in any query.
				$qv['meta_query'][] = array(
					'key'     => 'sstk_server_webserver_type',
					'compare' => 'EXISTS',
				);
			}
		}

		if ( ( ( is_admin() && $query->is_main_query() && $pagenow === 'edit.php' ) || sstk_is_public_servers_list_query( $query ) ) && $query->query['post_type'] === 'sstk_app_server' && ! empty( $_GET['team_id'] ) && empty( $filter_action ) ) {

			$qv               = &$query->query_vars;
			$qv['meta_query'] = array();

			$team_id = filter_input( INPUT_GET, 'team_id', FILTER_SANITIZE_NUMBER_INT );

			$qv['meta_query'][] = array(
				'field'   => 'sstk_assigned_teams',
				'value'   => $team_id,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);

		}

		if ( ( ( is_admin() && $query->is_main_query() && $pagenow === 'edit.php' ) || sstk_is_public_servers_list_query( $query ) ) && $query->query['post_type'] === 'sstk_app_server' && ! empty( $_GET['sstk_app_server_group'] ) && empty( $filter_action ) ) {

			$qv = &$query->query_vars;

			$sstk_app_server_group = filter_input( INPUT_GET, 'sstk_app_server_group', FILTER_SANITIZE_NUMBER_INT );

			$qv['tax_query'] = array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'sstk_app_server_group',
					'field'    => 'term_id',
					'terms'    => array( (int) $sstk_app_server_group ),
				),
			);

		}

	}

	/**
	 * To add custom metabox on server details screen
	 * Multiple metaboxes created for:
	 * 1. Allow user to change server owner(author)
	 * 2. Allow user to make server delete protected
	 *
	 * Filter hook: rwmb_meta_boxes
	 *
	 * @param array $metaboxes metaboxes.
	 *
	 * @return array
	 */
	public function sstk_app_server_register_meta_boxes( $metaboxes ) {

		// Get some values that we're going to need for fields later.
		// Start with the author of the post.
		$author_id = sstk_get_form_submission_post_author();

		$users_to_include = array();

		if ( ! sstk_is_admin() ) {
			$post_id = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
			$users   = sstk_get_users_in_team( $post_id );
			if ( ! in_array( $author_id, $users ) ) {
				array_push( $users, $author_id );
			}
			$users_to_include = $users;
		}

		$prefix = 'sstk_';

		// Register the metabox to change server owner.
		$metaboxes[] = array(
			'id'         => $prefix . 'change_server_owner',
			'title'      => __( 'Change Server Owner', 'simple-stack' ),
			'pages'      => array( 'sstk_app_server' ), // displays on sstk_app_server post type only.
			'context'    => 'side',
			'priority'   => 'low',
			'fields'     => array(

				// add a user type field.
				array(
					'name'        => '',
					'desc'        => '',
					'id'          => $prefix . 'server_owner',
					'type'        => 'user',
					'std'         => $author_id,
					'placeholder' => __( 'Select Server Owner', 'simple-stack' ),
					'query_args'  => array(
						'include' => $users_to_include,
					),
				),

			),
			'validation' => array(
				'rules'    => array(
					$prefix . 'server_owner' => array(
						'required' => true,
					),
				),
				'messages' => array(
					$prefix . 'server_owner' => array(
						'required' => __( 'Server Owner is required.', 'simple-stack' ),
					),
				),
			),
		);

		$checked = rwmb_meta( 'sstk_server_delete_protection' );

		// Register the metabox for delete server protection.
		$metaboxes[] = array(
			'id'       => $prefix . 'server_delete_protection_metabox',
			'title'    => __( 'Server Delete Protection', 'simple-stack' ),
			'pages'    => array( 'sstk_app_server' ), // displays on sstk_app_server post type only.
			'context'  => 'side',
			'priority' => 'low',
			'fields'   => array(

				// add a checkbox field.
				array(
					'name' => '',
					'desc' => __( 'Check this box to remove all delete links from the screen - it will prevent this server from being accidentally deleted.', 'simple-stack' ),
					'id'   => $prefix . 'server_delete_protection',
					'type' => 'checkbox',
					'std'  => $checked,
				),

			),
		);

		return $metaboxes;

	}

	/**
	 * Removes the sstk_server_owner meta from the server detail screen.
	 *
	 * Action hook: rwmb_sstk_change_server_owner_after_save_post
	 *
	 * @param integer $post_id post id.
	 *
	 * @return void
	 */
	public function sstk_change_server_owner_after_save_post( $post_id ) {

		// Remove the sstk_server_owner meta - we dont' want a separate meta since that could be confusing.
		// The post author is the owner so no need to have a separate meta stored in the db.
		delete_post_meta( $post_id, 'sstk_server_owner' );

	}

	/**
	 * Checks if user has permission to edit the server
	 *
	 * @return void
	 */
	public function sstk_app_server_load_post() {

		$screen = get_current_screen();

		if ( 'sstk_app_server' === $screen->post_type && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && ! sstk_is_admin() ) {
			$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
			$user_id     = get_current_user_id();
			$post_author = get_post( $post_id )->post_author;

			if ( ! sstk_user_can( $user_id, 'view_server', $post_id ) && (int) $post_author !== ( $user_id ) ) {
				wp_die( esc_html( __( 'You don\'t have permission to edit this post.', 'simple-stack' ) ) );
			}
		}
	}

	/**
	 * Restricts user to delete a post if he/she doesn't have delete_server permission
	 * Note: Changes to this permission logic might also need to be done in function
	 * ajax_server() located in file class-wordpres-app.php in the 'delete-server-record'
	 * section of the SWITCH-CASE control structure.
	 *
	 * @param int  $post_id post id.
	 * @param bool $return true=return a value and break, false=do not return a value.
	 *
	 * @return void|boolean
	 */
	public function sstk_app_server_delete_post( $post_id, $return = false ) {

		// No permissions check if we're running tasks via cron.
		// We're not doing anything to delete servers via cron right now.
		// But we might later so adding this check now since we have it in the sstk_app_delete_post() function.
		if ( true === wp_doing_cron() || true === sstk_is_doing_cron() ) {
			return true;
		}

		$success = true;
		if ( get_post_type( $post_id ) === 'sstk_app_server' && ! sstk_is_admin() ) {
			$user_id     = (int) get_current_user_id();
			$post_author = (int) get_post( $post_id )->post_author;
			if ( ! sstk_user_can( $user_id, 'delete_server', $post_id ) && $post_author !== $user_id ) {
				$success = false;
			}
		}

		if ( $return ) {
			return $success;
		}

		if ( ! $success ) {
			wp_die( esc_html( __( 'You don\'t have permission to delete this post.', 'simple-stack' ) ) );
		}
	}

	/**
	 * Filters table views for the sstk_app_server post type
	 *
	 * @param  array $views Array of table view links keyed by status slug.
	 * @return array Filtered views.
	 */
	public function sstk_app_server_custom_view_count( $views ) {
		global $current_screen;
		if ( 'edit-sstk_app_server' === $current_screen->id && ! sstk_is_admin() ) {
			$views = $this->sstk_app_manipulate_views( 'sstk_app_server', $views, 'view_server' );
		}
		return $views;
	}

	/**
	 * Fires on activation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is activated network-wide.
	 *
	 * @return void
	 */
	public static function activate( $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::sstk_app_server_register_post_and_taxonomy();
				self::sstk_app_server_create_default_taxonomy_terms();
				restore_current_blog();
			}
		} else {
			self::sstk_app_server_register_post_and_taxonomy();
			self::sstk_app_server_create_default_taxonomy_terms();
		}
	}

	/**
	 * Registers the custom post type and taxonomy
	 * Creating custom taxonomy terms after execution of this function
	 */
	public static function sstk_app_server_register_post_and_taxonomy() {

		// Figure out what the capabilities should be for the 'new' button.
		$new_cap = 'do_not_allow';
		if ( sstk_is_admin() ) {
			$new_cap = 'sstk_manage_all';  // This ensures that the ADD NEW SERVER RECORD button shows for the admin (since all admins will get the sstk_manage_all capability).
		}

		register_post_type(
			'sstk_app_server',
			array(
				'labels'              => array(
					'name'                  => _x( 'Cloud Servers', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'Cloud Server', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => defined( 'SSTK_MENU_NAME' ) ? SSTK_MENU_NAME : _x( 'Simple Stack', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'Cloud Server', 'Add New on Toolbar', 'simple-stack' ),
					'add_new'               => __( 'New Server Record', 'simple-stack' ),
					'add_new_item'          => __( 'New Server Record', 'simple-stack' ),
					'edit_item'             => __( 'Edit Cloud Server', 'simple-stack' ),
					'view_item'             => __( 'View Cloud Server', 'simple-stack' ),
					'all_items'             => __( 'Cloud Servers', 'simple-stack' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Cloud Servers', 'simple-stack' ),
					'not_found'             => apply_filters( 'sstk_no_app_servers_found_msg', __( 'No Application Servers were found.', 'simple-stack' ) ),
					'not_found_in_trash'    => __( 'No Application Servers were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter Cloud Servers list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'Cloud Servers list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'Cloud Servers list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => true,
				// The CPT-driven admin menu is retired: every screen the operator needs is
				// rendered by the React client under the Simple Stack menu (Phase 4). The post
				// type stays fully registered because the REST layer queries it.
				'show_in_menu'        => false,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				// 'menu_icon'              => 'dashicons-admin-site-alt2',
				'menu_icon'           => 'dashicons-cloud',
				'menu_position'       => 50,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => true,
				'hierarchical'        => false,
				'supports'            => array( '' ),
				'rewrite'             => array( 'slug' => 'cloud_server' ),
				'capabilities'        => array(
					// This value is false so that it does not create the "Add New" menu item.
					// Creating a server will be handled by a custom button.
					'create_posts'           => $new_cap,
					'edit_post'              => 'sstk_manage_servers',
					'edit_posts'             => 'sstk_manage_servers',
					'edit_others_posts'      => 'sstk_manage_servers',
					'edit_published_posts'   => 'sstk_manage_servers',
					'delete_post'            => 'sstk_manage_servers',
					'publish_posts'          => 'sstk_manage_servers',
					'delete_posts'           => 'sstk_manage_servers',
					'delete_others_posts'    => 'sstk_manage_servers',
					'delete_published_posts' => 'sstk_manage_servers',
					'delete_private_posts'   => 'sstk_manage_servers',
					'edit_private_posts'     => 'sstk_manage_servers',
					'read_private_posts'     => 'sstk_manage_servers',
				),
				'taxonomies'          => array( 'sstk_app_server_group' ),
			)
		);

		// Add new taxonomy, make it hierarchical (like categories).
		$labels = array(
			'name'              => _x( 'Cloud Server Groups', 'taxonomy general name', 'simple-stack' ),
			'singular_name'     => _x( 'Cloud Server Group', 'taxonomy singular name', 'simple-stack' ),
			'search_items'      => __( 'Search Cloud Server Groups', 'simple-stack' ),
			'all_items'         => __( 'All Cloud Server Groups', 'simple-stack' ),
			'parent_item'       => __( 'Parent Cloud Server Group', 'simple-stack' ),
			'parent_item_colon' => __( 'Parent Cloud Server Group:', 'simple-stack' ),
			'edit_item'         => __( 'Edit Cloud Server Group', 'simple-stack' ),
			'update_item'       => __( 'Update Cloud Server Group', 'simple-stack' ),
			'add_new_item'      => __( 'Add New Cloud Server Group', 'simple-stack' ),
			'new_item_name'     => __( 'New Cloud Server Group Name', 'simple-stack' ),
			'menu_name'         => __( 'Server Groups', 'simple-stack' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_in_menu'      => false,
			'show_admin_column' => false,
			'query_var'         => true,
			'capabilities'      => array(
				'manage_terms' => 'sstk_manage_groups',
				'edit_terms'   => 'sstk_manage_groups',
				'delete_terms' => 'sstk_manage_groups',
				'assign_terms' => 'sstk_manage_groups',
			),
		);

		// Hide the server group metabox.
		$hide_server_group_mb = sstk_get_early_option( 'sstk_hide_server_group_mb' );
		if ( $hide_server_group_mb ) {
			if ( ! sstk_is_admin() ) {
				$args['meta_box_cb'] = false;
			}
		}

		register_taxonomy( 'sstk_app_server_group', array( 'sstk_app_server' ), $args );
	}

	/**
	 * Creates the default terms for sstk_app_server_group taxonomy.
	 */
	public static function sstk_app_server_create_default_taxonomy_terms() {
		$taxonomy     = 'sstk_app_server_group';
		$server_terms = array(
			array(
				'name'  => 'Live',
				'color' => '#b71c1c',
			),
			array(
				'name'  => 'Staging',
				'color' => '#880E4F',
			),
			array(
				'name'  => 'Dev',
				'color' => '#4A148C',
			),
			array(
				'name'  => 'Other',
				'color' => '#33691E',
			),
			array(
				'name'  => 'VIP',
				'color' => '#E65100',
			),
			array(
				'name'  => 'Sync Source',
				'color' => '#3E2723',
			),
			array(
				'name'  => 'Demo',
				'color' => '#01579B',
			),
			array(
				'name'  => 'Sensitive',
				'color' => '#006064',
			),
		);

		foreach ( $server_terms as $server_term ) {
			if ( term_exists( $server_term['name'], $taxonomy ) ) {
				continue;
			}
			// Insert the term if not exists.
			$term = wp_insert_term( $server_term['name'], $taxonomy );

			if ( ! is_wp_error( $term ) ) {
				add_term_meta( $term['term_id'], 'sstk_group_color', $server_term['color'] );
			}
		}
	}

	/**
	 * To create default terms for sstk_app_server_group taxonomy for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function sstk_app_server_new_site( $new_site, $args ) {
		$plugin_name = SSTK_PLUGIN;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {
			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::sstk_app_server_create_default_taxonomy_terms();
			restore_current_blog();
		}
	}

	/**
	 * Removes link to delete post and hides the checkbox for server if Delete Protection is enabled or user does not have delete server permission.
	 *
	 * Filter hook: post_row_actions
	 *
	 * @param  array  $actions actions.
	 * @param  object $post post.
	 *
	 * @return array
	 */
	public function sstk_app_server_post_row_actions( $actions, $post ) {
		$user_id                       = get_current_user_id();
		$sstk_server_delete_protection = get_post_meta( $post->ID, 'sstk_server_delete_protection', true );

		if ( 'sstk_app_server' === $post->post_type && ! sstk_user_can( $user_id, 'delete_server', $post->ID ) && (int) $post->post_author !== (int) $user_id ) {
			unset( $actions['trash'] );
			unset( $actions['delete'] );
		}

		if ( 'sstk_app_server' === $post->post_type && ! empty( $sstk_server_delete_protection ) ) {
			unset( $actions['trash'] );
			unset( $actions['delete'] );
			$enable_bulk_delete = (int) sstk_get_option( 'wordpress_app_enable_bulk_delete_on_server_when_delete_protected' );
			if ( 1 <> $enable_bulk_delete ) {
				?>
				<script type="text/javascript">
					jQuery(document).ready(function($){
						$('#cb-select-<?php echo esc_attr( $post->ID ); ?>').attr('disabled', true);
					});
				</script>
				<?php
			}
		}

		return $actions;
	}

	/**
	 * Removes "Move to trash" for server details screen if Delete Protection is enabled or user does not have delete server permission.
	 *
	 * Action hook: admin_head-post.php
	 *
	 * @return void
	 */
	public function sstk_app_server_hide_delete_link() {
		$user_id                       = get_current_user_id();
		$post_id                       = filter_input( INPUT_GET, 'post', FILTER_VALIDATE_INT );
		$post                          = get_post( $post_id );
		$sstk_server_delete_protection = get_post_meta( $post_id, 'sstk_server_delete_protection', true );

		if ( ( 'sstk_app_server' === $post->post_type && ! sstk_user_can( $user_id, 'delete_server', $post->ID ) && $post->post_author !== $user_id ) || ( 'sstk_app_server' === $post->post_type && ! empty( $sstk_server_delete_protection ) ) ) {
			?>
			<style>#delete-action { display: none; }</style>
			<?php
		}

		// Hide the visibility options from publish metabox.
		if ( ( 'sstk_app_server' === $post->post_type || 'sstk_app' === $post->post_type ) && ! sstk_is_admin() ) {
			?>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery('#misc-publishing-actions').remove();
					jQuery('#minor-publishing-actions').remove();
				});
			</script>
			<?php
		}

	}

	/**
	 * Restricts the trash post of the server if Delete Protection is enabled.
	 *
	 * Filter hook: pre_trash_post
	 *
	 * @param  boolean $trash trash.
	 * @param  object  $post post.
	 *
	 * @return boolean
	 */
	public function sstk_app_server_restrict_trash_post( $trash, $post ) {

		$sstk_server_delete_protection = get_post_meta( $post->ID, 'sstk_server_delete_protection', true );

		if ( 'sstk_app_server' === $post->post_type && ! empty( $sstk_server_delete_protection ) ) {
			return true;
		}

		return $trash;
	}

	/**
	 * Restricts to change the server owner if the user does not have the right to change.
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function sstk_change_server_owner_before_save_post( $post_id ) {
		$user_id     = (int) get_current_user_id();
		$users       = sstk_get_users_in_team( $post_id );
		$post_author = (int) get_post( $post_id )->post_author;

		if ( ! in_array( $post_author, $users, true ) ) {
			array_push( $users, $post_author );
		}

		$sstk_server_owner = (int) filter_input( INPUT_POST, 'sstk_server_owner', FILTER_VALIDATE_INT );

		if ( ! sstk_is_admin() && ! in_array( $user_id, $users, true ) && $post_author !== $sstk_server_owner ) {
			wp_die( esc_html( __( 'You are not allowed to change the owner.', 'simple-stack' ) ) );
		}

		// Update the post author.
		$post              = get_post( $post_id );
		$post->post_author = $sstk_server_owner;
		wp_update_post( $post );
	}

	/**
	 * Adds server count column head on network sites listing
	 *
	 * @param  array $sites_columns sites_columns.
	 *
	 * @return array
	 */
	public function sstk_app_server_blogs_columns( $sites_columns ) {

		$sites_columns['sstk_server_count'] = __( 'Server Count', 'simple-stack' );

		return $sites_columns;
	}

	/**
	 * Adds server count column content on network sites listing
	 *
	 * @param  string $column_name column_name.
	 * @param  int    $blog_id blog_id.
	 *
	 * @return void
	 */
	public function sstk_app_server_sites_custom_column( $column_name, $blog_id ) {
		global $wpdb;
		$plugin_name = SSTK_PLUGIN;
		if ( is_plugin_active_for_network( $plugin_name ) || in_array( $plugin_name, get_blog_option( $blog_id, 'active_plugins' ), true ) ) {

			$value = '';
			switch ( $column_name ) {
				case 'sstk_server_count':
					$db_prefix   = $wpdb->get_blog_prefix( $blog_id );
					$post_type   = 'sstk_app_server';
					$post_status = 'private';
					$query       = $wpdb->prepare( "SELECT COUNT(*) FROM {$db_prefix}posts WHERE post_type = '%s' AND post_status = '%s'", $post_type, $post_status );
					$result      = $wpdb->get_var( $query );
					$value       = $result;
					break;
			}

			echo $value;
		}
	}

	/**
	 * Removes delete option from bulk action on server listing screen if user is not admin or super admin
	 *
	 * Filter hook: bulk_actions-edit-sstk_app_server
	 *
	 * @param  array $actions actions.
	 *
	 * @return array
	 */
	public function sstk_app_server_bulk_actions( $actions ) {
		if ( ! sstk_is_admin() ) {
			unset( $actions['trash'] );
		}

		// Also disable if the option it set in the SETTINGS screen.
		$disable_bulk_delete = (int) sstk_get_option( 'wordpress_app_disable_bulk_delete_on_full_server_list' );
		if ( 1 === $disable_bulk_delete ) {
			unset( $actions['trash'] );
		}

		return $actions;
	}

	/**
	 * Clean up some of the meta fields for sstk_app_server posts.
	 *
	 * Action hook: wp_ajax_sstk_cleanup_servers
	 *
	 * @return void
	 */
	public function sstk_cleanup_servers() {

		// Nonce check.
		check_ajax_referer( 'sstk-cleanup-servers', 'nonce' );

		// Permissions check.
		if ( ! sstk_is_admin() ) {

			$error_msg = array( 'msg' => __( 'You are not allowed to perform this action - only admins are permitted here.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();

		}

		$args = array(
			'post_type'      => 'sstk_app_server',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$server_ids = get_posts( $args );

		if ( ! empty( $server_ids ) ) {
			foreach ( $server_ids as $server_id ) {

				do_action( 'sstk_cleanup_server_before', $server_id );

				delete_post_meta( $server_id, 'sstk_temp_log_id' );

				do_action( 'sstk_cleanup_server_after', $server_id );

			}

			do_action( 'sstk_cleanup_servers_after' );

			$msg = __( 'Clean Up completed!', 'simple-stack' );

		} else {
			$msg = __( 'No Server Records found!', 'simple-stack' );
		}

		$return = array( 'msg' => $msg );

		wp_send_json_success( $return );
		wp_die();

	}

	/**
	 * Adds custom column header for sstk_app_server_group taxonomy listing screen.
	 *
	 * Filter hook: manage_edit-sstk_app_server_group_columns
	 *
	 * @param  array $columns columns.
	 *
	 * @return array
	 */
	public function sstk_manage_sstk_app_server_group_columns( $columns ) {
		$columns['sstk_color'] = __( 'Color', 'simple-stack' );

		return $columns;
	}

	/**
	 * Adds custom column content for sstk_app_server_group taxonomy listing screen.
	 *
	 * Filter hook: manage_sstk_app_server_group_custom_column
	 *
	 * @param  string $content content.
	 * @param  string $column_name column name.
	 * @param  int    $term_id team id.
	 */
	public function sstk_manage_sstk_app_server_group_columns_content( $content, $column_name, $term_id ) {
		if ( 'sstk_color' === $column_name ) {
			$color_code = get_term_meta( $term_id, 'sstk_group_color', true );
			$color      = ! empty( $color_code ) ? $color_code : '#999999';

			$content = sprintf( '<span class="sstk-app-server-app-group" style="background-color: %s;">%s</span>', $color, $color );
		}
		return $content;
	}

	/**
	 * Adds meta value when sstk_app_server type post is restored from trash.
	 *
	 * Action hook: untrashed_post
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function sstk_app_server_untrashed_post( $post_id ) {

		if ( 'sstk_app_server' === get_post_type( $post_id ) ) {
			update_post_meta( $post_id, 'sstk_wpapp_disconnected', 1 );
			update_post_meta( $post_id, 'sstk_server_current_state', 'deleted' );
		}

	}

	/**
	 * Set the delete protection flag on a server record.
	 *
	 * This isn't normally used except by events that are
	 * being sequenced using PENDING LOGS.  That's because
	 * the flag is a metabox.io field and is updated
	 * automatically when the server post is saved.
	 *
	 * @param  int $post_id post id.
	 *
	 * @return void
	 */
	public function sstk_app_server_set_deletion_protection_flag( $post_id ) {

		update_post_meta( $post_id, 'sstk_server_delete_protection', '1' );

	}

	/**
	 * Get all server terms which will going to be excluded
	 * from the server taxonomy metabox.
	 * They will be set up in a global variable defined
	 * in our constructor function - sstk_exclude_server_group.
	 */
	public function sstk_exclude_server_group_taxonomy_ids() {
		global $sstk_exclude_server_group;  // This global is defined in our CONSTRUCTOR function at the top of this file.

		if ( 'sstk_app_server' === get_post_type() ) {
			// This function defined in the metaboxes_for_taxonomies_for_servers_and_apps file.
			$sstk_exclude_server_group = $this->sstk_common_code_server_app_group_metabox( 'sstk_app_server_group' );
		}

	}

	/**
	 * Exclude server term ids based on the global variable $sstk_exclude_server_group
	 * defined in our constructor function.
	 *
	 * @param array $args args.
	 * @param array $taxonomies taxonomies.
	 */
	public function sstk_exclude_from_server_term_args( $args, $taxonomies ) {
		global $sstk_exclude_server_group; // This global is defined in our CONSTRUCTOR function at the top of this file.

		if ( in_array( 'sstk_app_server_group', $taxonomies ) ) {
			// This function defined in the metaboxes_for_taxonomies_for_servers_and_apps file.
			$args = $this->sstk_exclude_term_ids_for_server_app_group( $args, $sstk_exclude_server_group );
		}

		return $args;
	}

	/**
	 * Takes a string and wraps it with a span and a class related to the column name.
	 *
	 * For example, if we get a string such as "Domain:" we might
	 * return <span class="sstk-column-label-domain">Domain:</span>.
	 *
	 * Calls the global function sstk_wrap_string_with_span_and_class
	 * defined in the functions.php which does the actual wrapping.
	 *
	 * @param string $string The string to wrap.
	 * @param string $column The column name.
	 * @param string $align Valid values are 'left' and 'right'.
	 *
	 * @return string
	 */
	public function sstk_column_wrap_string_with_span_and_class( $string, $column, $align ) {

		if ( 'left' === $align ) {
			return sstk_wrap_string_with_span_and_class( $string, $column, 'server-col-element-label' );
		} else {
			return sstk_wrap_string_with_span_and_class( $string, $column, 'server-col-element-value' );
		}

	}

	/**
	 * Takes a string and wraps it with a div.
	 *
	 * For example, if we get a string such as "Domain:" we might
	 * return <div class="sstk-column-label-domain">Domain:</div>.
	 *
	 * @param string $string The string to wrap.
	 * @param string $column The column name.
	 *
	 * @return string
	 */
	public function sstk_column_wrap_string_with_div_and_class( $string, $column ) {

		return sstk_wrap_string_with_div_and_class( $string, $column, 'server-col-element-wrap' );

	}

}
