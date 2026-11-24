<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This class handles metaboxes used in setting up email notifications for servers & sites.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_Settings
 */
class SSTK_EMAIL_NOTIFICATIONS {

	/**
	 * Return instance of self.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * SSTK_EMAIL_NOTIFICATIONS constructor.
	 */
	public function __construct() {
		$this->display_email_notifications_metaboxes(); // show the meta boxes.
		$this->register();  // register the custom post type - sstk_email_address.
		$this->hooks();     // register hooks to make the custom post type do things.
	}

	/**
	 * Add all the hook inside the this private method.
	 */
	public function hooks() {
		// Action hook to save/add all fields of email addresses tab.
		add_action( 'wp_ajax_sstk_email_address_fields_save', array( $this, 'sstk_email_address_fields_save' ) );

		// Action hook to delete email addresses entries.
		add_action( 'wp_ajax_sstk_email_address_entry_delete', array( $this, 'sstk_email_address_entry_delete' ) );

		// Action hook to save to draft compose email.
		add_action( 'wp_ajax_sstk_compose_email_save_to_draft', array( $this, 'sstk_compose_email_save_to_draft' ) );

		// Action hook to send email immediately.
		add_action( 'wp_ajax_sstk_compose_email_send_now', array( $this, 'sstk_compose_email_send_now' ) );

		// Enqueue admin scripts to add on backend.
		add_action( 'admin_enqueue_scripts', array( $this, 'sstk_email_addresses_user_admin_enqueue_scripts' ), 10, 1 );

		// Action hook to send compose email on schedule date and time.
		add_action( 'sstk_check_for_scheduled_compose_email_send', array( $this, 'sstk_send_compose_email_on_scheduled_time_for_servers_apps' ), 1, 1 );

		// Action hook to fire on new site created on WP Multisite.
		add_action( 'wp_initialize_site', array( $this, 'sstk_scheduled_compose_email_schedule_events_for_new_site' ), 10, 2 );

		// Action hook to display sent email details.
		add_action( 'wp_ajax_sstk_sent_email_details_popup', array( $this, 'sstk_sent_email_details_popup' ) );

		// Action hook to delete sent emails entries.
		add_action( 'wp_ajax_sstk_sent_email_entry_delete', array( $this, 'sstk_sent_email_entry_delete' ) );

		// Action hook to display the list of sent emails.
		add_action( 'wp_ajax_sstk_sent_emails_list_pagination', array( $this, 'sstk_sent_emails_list_pagination' ) );

		// Action hook to add new bulk option in server listing.
		add_filter( 'bulk_actions-edit-sstk_app_server', array( $this, 'sstk_add_new_bulk_actions_server' ) );

		// Action hook to add new bulk option in app listing.
		add_filter( 'bulk_actions-edit-sstk_app', array( $this, 'sstk_add_new_bulk_actions_app' ) );

		// Action hook to handle bulk action for server.
		add_filter( 'handle_bulk_actions-edit-sstk_app_server', array( $this, 'sstk_bulk_action_handler_server_app' ), 10, 3 );

		// Action hook to handle bulk action for app.
		add_filter( 'handle_bulk_actions-edit-sstk_app', array( $this, 'sstk_bulk_action_handler_server_app' ), 10, 3 );

		// Action hook to add values in new columns - Server batches.
		add_action( 'manage_sstk_server_batch_posts_custom_column', array( $this, 'server_app_batches_table_content' ), 10, 2 );

		// Filter hook to add new columns on user notifications screen - Server batches.
		add_filter( 'manage_sstk_server_batch_posts_columns', array( $this, 'server_app_batches_table_head' ), 10, 1 );

		// Action hook to add values in new columns - App batches.
		add_action( 'manage_sstk_app_batch_posts_custom_column', array( $this, 'server_app_batches_table_content' ), 10, 2 );

		// Filter hook to add new columns on user notifications screen - App batches.
		add_filter( 'manage_sstk_app_batch_posts_columns', array( $this, 'server_app_batches_table_head' ), 10, 1 );

		// Admin menu action for remove author box from batch posttypes.
		add_action( 'admin_menu', array( $this, 'sstk_batch_posttypes_remove_author_box' ) );

		// Move author metabox to side in publish section.
		add_action( 'post_submitbox_misc_actions', array( $this, 'sstk_batch_posttypes_author_in_publish' ) );
	}

	/**
	 * Register the custom post types.
	 */
	public function register() {
		// For user emails entries.
		register_post_type(
			'sstk_email_address',
			array(
				'labels'              => array(
					'name'                  => _x( 'Email Address', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'Email Address', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => _x( 'Email Address', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'Email Address', 'Add New on Toolbar', 'simple-stack' ),
					'edit_item'             => __( 'Edit Email Address', 'simple-stack' ),
					'view_item'             => __( 'View Email Address', 'simple-stack' ),
					'all_items'             => __( 'All Email Address Entries', 'simple-stack' ),
					'search_items'          => __( 'Search Email Address', 'simple-stack' ),
					'not_found'             => __( 'No Email Addresses were found.', 'simple-stack' ),
					'not_found_in_trash'    => __( 'No Email Addresses were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter Email Address list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'Email Address list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'Email Address list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts' => false,
				),
			)
		);

		// For sent user emails.
		register_post_type(
			'sstk_sent_emails',
			array(
				'labels'              => array(
					'name'                  => _x( 'Sent Email', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'Sent Email', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => _x( 'Sent Email', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'Sent Email', 'Add New on Toolbar', 'simple-stack' ),
					'edit_item'             => __( 'Edit Sent Email', 'simple-stack' ),
					'view_item'             => __( 'View Sent Email', 'simple-stack' ),
					'all_items'             => __( 'All Sent Email Entries', 'simple-stack' ),
					'search_items'          => __( 'Search Sent Email', 'simple-stack' ),
					'not_found'             => __( 'No Sent Email were found.', 'simple-stack' ),
					'not_found_in_trash'    => __( 'No Sent Email were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter Sent Email list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'Sent Email list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'Sent Email list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts' => false,
				),
			)
		);

		// For server batch entries.
		register_post_type(
			'sstk_server_batch',
			array(
				'labels'              => array(
					'name'                  => _x( 'Server Batch', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'Server Batch', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => _x( 'Server Batch', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'Server Batch', 'Add New on Toolbar', 'simple-stack' ),
					'edit_item'             => __( 'Edit Server Batch', 'simple-stack' ),
					'view_item'             => __( 'View Server Batch', 'simple-stack' ),
					'all_items'             => __( 'All Server Batch Entries', 'simple-stack' ),
					'search_items'          => __( 'Search Server Batch', 'simple-stack' ),
					'not_found'             => __( 'No server batches were found.', 'simple-stack' ),
					'not_found_in_trash'    => __( 'No server batches were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter Server Batch list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'Server Batch list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'Server Batch list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( 'author' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => false,
					'edit_post'              => 'sstk_manage_all',
					'edit_posts'             => 'sstk_manage_all',
					'edit_others_posts'      => 'sstk_manage_all',
					'edit_published_posts'   => 'sstk_manage_all',
					'delete_post'            => 'sstk_manage_all',
					'publish_posts'          => 'sstk_manage_all',
					'delete_posts'           => 'sstk_manage_all',
					'delete_others_posts'    => 'sstk_manage_all',
					'delete_published_posts' => 'sstk_manage_all',
					'delete_private_posts'   => 'sstk_manage_all',
					'edit_private_posts'     => 'sstk_manage_all',
					'read_private_posts'     => 'sstk_manage_all',
				),
			)
		);

		// For app batch entries.
		register_post_type(
			'sstk_app_batch',
			array(
				'labels'              => array(
					'name'                  => _x( 'App Batch', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'App Batch', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => _x( 'App Batch', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'App Batch', 'Add New on Toolbar', 'simple-stack' ),
					'edit_item'             => __( 'Edit App Batch', 'simple-stack' ),
					'view_item'             => __( 'View App Batch', 'simple-stack' ),
					'all_items'             => __( 'All App Batch Entries', 'simple-stack' ),
					'search_items'          => __( 'Search App Batch', 'simple-stack' ),
					'not_found'             => __( 'No app batches were found.', 'simple-stack' ),
					'not_found_in_trash'    => __( 'No app batches were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter App Batch list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'App Batch list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'App Batch list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( 'author' ),
				'rewrite'             => null,
				'capabilities'        => array(
					'create_posts'           => false,
					'edit_post'              => 'sstk_manage_all',
					'edit_posts'             => 'sstk_manage_all',
					'edit_others_posts'      => 'sstk_manage_all',
					'edit_published_posts'   => 'sstk_manage_all',
					'delete_post'            => 'sstk_manage_all',
					'publish_posts'          => 'sstk_manage_all',
					'delete_posts'           => 'sstk_manage_all',
					'delete_others_posts'    => 'sstk_manage_all',
					'delete_published_posts' => 'sstk_manage_all',
					'delete_private_posts'   => 'sstk_manage_all',
					'edit_private_posts'     => 'sstk_manage_all',
					'read_private_posts'     => 'sstk_manage_all',
				),
			)
		);
	}

	/**
	 * Enqueue the scripts if we are on the servers and apps details screen.
	 *
	 * @param String $hook Hook name.
	 */
	public function sstk_email_addresses_user_admin_enqueue_scripts( $hook ) {
		if ( in_array( $hook, array( 'post-new.php', 'post.php', 'edit.php' ), true ) ) {

			$screen = get_current_screen();
			if ( is_object( $screen ) && ( 'sstk_app_server' === $screen->post_type || 'sstk_app' === $screen->post_type || 'sstk_server_batch' === $screen->post_type || 'sstk_app_batch' === $screen->post_type ) ) {
				wp_dequeue_script( 'autosave' );

				wp_enqueue_style( 'sstk-email-addresses-css', SSTK_URL . 'assets/css/sstk-email-addresses-user.css', array(), SSTK_SCRIPTS_VERSION );

				wp_enqueue_script( 'sstk-email-addresses-user', SSTK_URL . 'assets/js/sstk-email-addresses-user.js', array( 'jquery' ), SSTK_VERSION, true );
				wp_localize_script(
					'sstk-email-addresses-user',
					'emailaddressesparams',
					array(
						'i10n' => array(
							'empty_firstname'      => __( 'Please enter first name.', 'simple-stack' ),
							'empty_lastname'       => __( 'Please enter last name.', 'simple-stack' ),
							'empty_company'        => __( 'Please enter company name.', 'simple-stack' ),
							'empty_email'          => __( 'Please enter email address.', 'simple-stack' ),
							'empty_notes'          => __( 'Please enter notes.', 'simple-stack' ),
							'confirm_entry_delete' => __( 'Are you sure?', 'simple-stack' ),
							'invalid_reply_to'     => __( 'Please enter a valid reply to email address.', 'simple-stack' ),
							'empty_subject'        => __( 'Please enter email subject.', 'simple-stack' ),
							'empty_body'           => __( 'Please enter email body.', 'simple-stack' ),
							'invalid_other_emails' => __( 'Please enter valid email addresses in other emails field.', 'simple-stack' ),
							'schedule_datetime'    => __( 'Please select date and time to schedule email.', 'simple-stack' ),
							'add_wait_msg'         => __( 'Adding...', 'simple-stack' ),
							'delete_wait_msg'      => __( 'Deleting...', 'simple-stack' ),
							'save_wait_msg'        => __( 'Saving...', 'simple-stack' ),
							'send_wait_msg'        => __( 'Sending...', 'simple-stack' ),
							'empty_reply_to'       => __( 'Please enter the reply to email address.', 'simple-stack' ),
							'empty_from_name'      => __( 'Please enter the from name.', 'simple-stack' ),
							'schedule_later_btn'   => __( 'Schedule for Later', 'simple-stack' ),
							'save_draft_btn'       => __( 'Save as draft', 'simple-stack' ),
							'schedule_confirm_msg' => __( 'The message has been scheduled for ', 'simple-stack' ),
						),
					)
				);
			}
		}
	}

	/**
	 * Function for showing the metabox fields.
	 */
	public function display_email_notifications_metaboxes() {

		// Get the post id from the $_POST global var.
		$post_id   = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		// Check to see if this should be displayed.  The id of the permission is 'email_metabox'.
		if ( ! sstk_can_author_view_server_feature( $post_id, 'email_metabox' ) ) {
			return false;
		}
		if ( ! sstk_can_author_view_site_feature( $post_id, 'email_metabox' ) ) {
			return false;
		}

		// Register meta boxes and fields for email notifications.
		add_filter(
			'rwmb_meta_boxes',
			function ( $metaboxes ) {

				$server_time = gmdate( 'Y-m-d H:i:s' );
				$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

				// This field only for server compose email tab.
				if ( get_post_type( $post_id ) === 'sstk_app_server' ) {
					$server_screen = array(
						'id'         => 'sstk_compose_email_send_to_server_emails',
						'type'       => 'select',
						'options'    => array(
							'servers_emails_only'      => __( 'Send to the server email addresses only', 'simple-stack' ),
							'servers_apps_both_emails' => __( 'Send to both server & app email addresses', 'simple-stack' ),
						),
						'name'       => __( 'Recipients', 'simple-stack' ),
						'tab'        => 'sstk_compose_email',
						'save_field' => true,
					);
				} else {
					$server_screen = array(
						'type' => 'custom_html',
						'std'  => '',
						'id'   => 'sstk_compose_email_tab_html',
						'tab'  => 'sstk_compose_email',
					);
				}

				$prefix = 'sstk_';

				// Register the metabox for composing and sending emails.
				$metaboxes[] = array(
					'id'         => $prefix . 'email_notification_addresses',
					'title'      => __( 'Email Notifications', 'simple-stack' ),
					'post_types' => array( 'sstk_app_server', 'sstk_app' ),
					'priority'   => 'default',
					'tabs'       => array(
						'sstk_email_addresses' => array(
							'label' => 'Email Addresses',
						),
						'sstk_compose_email'   => array(
							'label' => 'Compose Email',
						),
						'sstk_sent_email'      => array(
							'label' => 'Sent Emails',
						),
					),
					'fields'     => array(
						array(
							'type' => 'custom_html',
							'std'  => $this->get_email_addresses_tab_form_html(),
							'id'   => 'sstk_email_address_tab_form_html',
							'tab'  => 'sstk_email_addresses',
						),
						array(
							'type' => 'heading',
							'name' => 'All email addresses',
							'tab'  => 'sstk_email_addresses',
						),
						array(
							'type' => 'custom_html',
							'std'  => $this->get_list_of_email_addresses_html(),
							'id'   => 'sstk_email_address_tab_form_html',
							'tab'  => 'sstk_email_addresses',
						),
						$server_screen,
						array(
							'name'       => __( 'Additional Recipients', 'simple-stack' ),
							'id'         => 'sstk_compose_email_other_emails',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add additional email addresses separated by commas.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'From Name', 'simple-stack' ),
							'id'         => 'sstk_compose_email_from_name',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Reply To', 'simple-stack' ),
							'id'         => 'sstk_compose_email_reply_to',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add reply to email address.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Subject', 'simple-stack' ),
							'id'         => 'sstk_compose_email_subject',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'id'         => 'sstk_compose_email_body',
							'type'       => 'wysiwyg',
							'name'       => __( 'Body', 'simple-stack' ),
							'desc'       => __( 'Valid substitutions are: ##USER_FIRSTNAME##, ##USER_LASTNAME##, ##OWNER_FIRSTNAME##, ##OWNER_LASTNAME##, ##SERVER_NAME##, ##SITE_NAME##', 'simple-stack' ),
							'options'    => array(
								'textarea_rows' => 12,
							),
							'tab'        => 'sstk_compose_email',
							'size'       => 60,
							'save_field' => true,
						),
						array(
							'name'       => __( 'Do you want to schedule the email?', 'simple-stack' ),
							'id'         => 'sstk_compose_email_schedule_email_enable',
							'type'       => 'checkbox',
							'desc'       => __( 'Turn this on to send this message later.', 'simple-stack' ),
							'save_field' => true,
							'tab'        => 'sstk_compose_email',
						),
						array(
							'name'       => __( 'When would you like to send this message?', 'simple-stack' ),
							'id'         => 'sstk_compose_email_schedule_email_datetime',
							'type'       => 'datetime',
							'js_options' => array(
								'stepMinute'      => 5,
								'showTimepicker'  => true,
								'controlType'     => 'select',
								'showButtonPanel' => false,
								'oneLine'         => true,
							),
							'attributes' => array(
								'autocomplete' => false,
							),
							'inline'     => false,
							'timestamp'  => false,
							/* translators: %s server time */
							'desc'       => sprintf( __( 'Your current server date & time is %s.', 'simple-stack' ), $server_time ),
							'save_field' => true,
							'tooltip'    => __( 'Your message will be sent at the date and time set here.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Save as draft', 'simple-stack' ),
							'attributes' => array(
								'id'           => 'sstk-compose-email-save-draft',
								'data-action'  => 'sstk_compose_email_save_to_draft',
								'data-nonce'   => wp_create_nonce( 'sstk-compose-email-save-draft' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Save as draft or Schedule for Later.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Send Now', 'simple-stack' ),
							'attributes' => array(
								'id'           => 'sstk-compose-email-send-now',
								'data-action'  => 'sstk_compose_email_send_now',
								'data-nonce'   => wp_create_nonce( 'sstk-compose-email-send-now' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Send email immediately.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
						),
						array(
							'type' => 'heading',
							'name' => 'Sent emails',
							'tab'  => 'sstk_sent_email',
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Delete All', 'simple-stack' ),
							'attributes' => array(
								'id'             => 'sstk-sent-email-delete-all',
								'data-action'    => 'sstk_sent_email_entry_delete',
								'data-nonce'     => wp_create_nonce( 'sstk-sent-email-entry-delete' ),
								'data-parent_id' => $post_id,
								'data-entry_id'  => __( '0', 'simple-stack' ),
							),
							'tab'        => 'sstk_sent_email',
						),
						array(
							'type' => 'custom_html',
							'std'  => $this->get_list_of_sent_emails_html(),
							'id'   => 'sstk_sent_emails_tab_list_html',
							'tab'  => 'sstk_sent_email',
						),
					),
				);

				return $metaboxes;
			}
		);

		// Register meta boxes and fields for server/app batches.
		add_filter(
			'rwmb_meta_boxes',
			function ( $metaboxes ) {

				$server_time = gmdate( 'Y-m-d H:i:s' );
				$post_id     = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

				// This field only for server batch.
				if ( get_post_type( $post_id ) === 'sstk_server_batch' ) {
					$server_batch_screen = array(
						'id'         => 'sstk_compose_email_send_to_server_emails',
						'type'       => 'select',
						'options'    => array(
							'servers_emails_only'      => __( 'Send to the server email addresses only', 'simple-stack' ),
							'servers_apps_both_emails' => __( 'Send to both the server & app email addresses', 'simple-stack' ),
						),
						'name'       => __( 'Send to emails', 'simple-stack' ),
						'save_field' => true,
					);
				} else {
					$server_batch_screen = array(
						'type' => 'custom_html',
						'std'  => '',
						'id'   => 'sstk_compose_email_tab_html',
					);
				}

				$prefix = 'sstk_';

				// Register the metabox for composing and sending emails.
				$metaboxes[] = array(
					'id'         => $prefix . 'email_notification_addresses_batch',
					'title'      => __( 'Email Notification Details', 'simple-stack' ),
					'post_types' => array( 'sstk_server_batch', 'sstk_app_batch' ),
					'priority'   => 'high',
					'fields'     => array(
						array(
							'type' => 'custom_html',
							'std'  => $this->display_the_selected_server_app_bulk_ids( $post_id ),
							'id'   => 'sstk_selected_server_app_bulk_ids_html',
						),
						$server_batch_screen,
						array(
							'name'       => __( 'Additional Recipients', 'simple-stack' ),
							'id'         => 'sstk_compose_email_other_emails',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add additional email addresses separated by commas.', 'simple-stack' ),
							'save_field' => true,
						),
						array(
							'name'       => __( 'From Name', 'simple-stack' ),
							'id'         => 'sstk_compose_email_from_name',
							'type'       => 'text',
							'size'       => 100,
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Reply To', 'simple-stack' ),
							'id'         => 'sstk_compose_email_reply_to',
							'type'       => 'text',
							'size'       => 100,
							'desc'       => __( 'Add reply to email address.', 'simple-stack' ),
							'tab'        => 'sstk_compose_email',
							'save_field' => true,
						),
						array(
							'name'       => __( 'Subject', 'simple-stack' ),
							'id'         => 'sstk_compose_email_subject',
							'type'       => 'text',
							'size'       => 100,
							'save_field' => true,
						),
						array(
							'id'         => 'sstk_compose_email_body',
							'type'       => 'wysiwyg',
							'name'       => __( 'Body', 'simple-stack' ),
							'desc'       => __( 'Valid substitutions are: ##USER_FIRSTNAME##, ##USER_LASTNAME##, ##OWNER_FIRSTNAME##, ##OWNER_LASTNAME##, ##SERVER_NAME##, ##SITE_NAME##', 'simple-stack' ),
							'options'    => array(
								'textarea_rows' => 12,
							),
							'size'       => 60,
							'save_field' => true,
						),
						array(
							'name'       => __( 'Do you want to schedule the email?', 'simple-stack' ),
							'id'         => 'sstk_compose_email_schedule_email_enable',
							'type'       => 'checkbox',
							'desc'       => __( 'Turn this on to send this message later.', 'simple-stack' ),
							'save_field' => true,
						),
						array(
							'name'       => __( 'When would you like to send this message?', 'simple-stack' ),
							'id'         => 'sstk_compose_email_schedule_email_datetime',
							'type'       => 'datetime',
							'js_options' => array(
								'stepMinute'      => 5,
								'showTimepicker'  => true,
								'controlType'     => 'select',
								'showButtonPanel' => false,
								'oneLine'         => true,
							),
							'inline'     => false,
							'timestamp'  => false,
							/* translators: %s server time */
							'desc'       => sprintf( __( 'Your current server date & time is %s.', 'simple-stack' ), $server_time ),
							'save_field' => true,
							'tooltip'    => __( 'Your message will be sent at the date and time set here.', 'simple-stack' ),
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Save as draft', 'simple-stack' ),
							'attributes' => array(
								'id'           => 'sstk-compose-email-save-draft',
								'data-action'  => 'sstk_compose_email_save_to_draft',
								'data-nonce'   => wp_create_nonce( 'sstk-compose-email-save-draft' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Save as draft or Schedule for Later.', 'simple-stack' ),
						),
						array(
							'type'       => 'button',
							'std'        => __( 'Send Now', 'simple-stack' ),
							'attributes' => array(
								'id'           => 'sstk-compose-email-send-now',
								'data-action'  => 'sstk_compose_email_send_now',
								'data-nonce'   => wp_create_nonce( 'sstk-compose-email-send-now' ),
								'data-post_id' => $post_id,
							),
							'desc'       => __( 'Send email immediately.', 'simple-stack' ),
						),
					),
				);

				return $metaboxes;
			}
		);

	}

	/**
	 * Gets the HTML for email address tab form.
	 *
	 * @return string
	 */
	public function get_email_addresses_tab_form_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		$html = '';

		$html .= '<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_first_name">' . __( 'First Name *', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a first name', 'simple-stack' ) . '" type="text" id="sstk_email_addresses_first_name" class="rwmb-text" name="sstk_email_addresses_first_name">
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_last_name">' . __( 'Last Name *', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a last name', 'simple-stack' ) . '" type="text" id="sstk_email_addresses_last_name" class="rwmb-text" name="sstk_email_addresses_last_name">
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_company">' . __( 'Company', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a company', 'simple-stack' ) . '" type="text" id="sstk_email_addresses_company" class="rwmb-text" name="sstk_email_addresses_company">
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_email_id">' . __( 'Email Address *', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter an email address', 'simple-stack' ) . '" type="email" id="sstk_email_addresses_email_id" class="rwmb-text" name="sstk_email_addresses_email_id">
					</div>
				</div>
				<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_notes">' . __( 'Notes', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">
						<input size="60" autocomplete="off" placeholder="' . __( 'Enter a notes', 'simple-stack' ) . '" type="text" id="sstk_email_addresses_notes" class="rwmb-text" name="sstk_email_addresses_notes">
					</div>
				</div>

				<div class="rwmb-field rwmb-button-wrapper"><div class="rwmb-input"><button type="button" id="sstk-email-address-fields-save" class="rwmb-button button hide-if-no-js" data-post_id="' . $post_id . '" data-action="sstk_email_address_fields_save" data-nonce="' . wp_create_nonce( 'sstk-email-addresses-fields-save' ) . '">' . __( 'Add', 'simple-stack' ) . '</button></div></div>';

		return $html;
	}

	/**
	 * Gets the HTML for listing all the email addresses.
	 *
	 * @return string
	 */
	public function get_list_of_email_addresses_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );

		// Check and get all the email address either for server or site.
		$emails_args    = array(
			'post_type'   => 'sstk_email_address',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'sstk_email_addresses_parent_id',
					'value'   => $post_id,
					'compare' => '=',
				),
			),
		);
		$get_all_emails = get_posts( $emails_args );

		$html = '';

		$html .= '<div class="sstk_email_list_sec">
					<table cellspacing="5" cellpadding="5px" class="sstk_email_list_table">
					<tr>
						<th>' . __( 'First Name', 'simple-stack' ) . '</th>
						<th>' . __( 'Last Name', 'simple-stack' ) . '</th>
						<th>' . __( 'Company', 'simple-stack' ) . '</th>
						<th>' . __( 'Email Address', 'simple-stack' ) . '</th>
						<th>' . __( 'Notes', 'simple-stack' ) . '</th>
						<th>' . __( 'Delete', 'simple-stack' ) . '</th>
					</tr>';

		if ( ! empty( $get_all_emails ) ) {
			foreach ( $get_all_emails as $key => $value ) {
				$entry_id  = $value->ID;
				$parent_id = get_post_meta( $entry_id, 'sstk_email_addresses_parent_id', true );

				if ( $post_id === $parent_id ) {

					$first_name = get_post_meta( $entry_id, 'sstk_email_addresses_first_name', true );
					$last_name  = get_post_meta( $entry_id, 'sstk_email_addresses_last_name', true );
					$company    = get_post_meta( $entry_id, 'sstk_email_addresses_company', true );
					$company    = ! empty( $company ) ? $company : '-';
					$email_id   = get_post_meta( $entry_id, 'sstk_email_addresses_email_id', true );
					$notes      = get_post_meta( $entry_id, 'sstk_email_addresses_notes', true );
					$notes      = ! empty( $notes ) ? $notes : '-';

					$html .= '<tr>
											<td>' . esc_html( $first_name ) . '</td>
											<td>' . esc_html( $last_name ) . '</td>
											<td>' . esc_html( $company ) . '</td>
											<td>' . esc_html( $email_id ) . '</td>
											<td>' . esc_html( $notes ) . '</td>
											<td><a href="javascript:void(0)" class="sstk_delete_entry" data-parent_id="' . esc_attr( $parent_id ) . '" data-entry_id="' . esc_attr( $entry_id ) . '" data-action="sstk_email_address_entry_delete" data-nonce="' . wp_create_nonce( 'sstk-email-address-entry-delete' ) . '" >' . __( 'Delete', 'simple-stack' ) . '</a></td>
										</tr>';
				}
			}
		} else {
			$html .= '<tr><td colspan="6">' . __( 'No email addresses added.', 'simple-stack' ) . '</td></tr>';
		}

			$html .= '</table>
				</div>';

		return $html;
	}

	/**
	 * Gets the HTML for listing all the sent emails.
	 *
	 * @return string
	 */
	public function get_list_of_sent_emails_html() {
		$post_id = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
		$paged   = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );

		$html  = '';
		$html .= '<div class="sstk_page_loading"><div class="sstk_sent_emails_list_main_sec" data-parent_id="' . esc_attr( $post_id ) . '" data-action="sstk_sent_emails_list_pagination" data-nonce="' . wp_create_nonce( 'sstk-sent-email-list-pagination' ) . '">';
		$html .= '</div></div>';

		return $html;
	}

	/**
	 * Save data to the email address cpt.
	 */
	public function sstk_email_address_fields_save() {
		// Nonce check.
		check_ajax_referer( 'sstk-email-addresses-fields-save', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		$author_id = get_current_user_id();

		// Permission check - user access.
		$this->sstk_email_user_check_server_app_permission( $author_id, $post_id );

		$sstk_email_addresses_first_name = sanitize_text_field( filter_input( INPUT_POST, 'sstk_email_addresses_first_name', FILTER_UNSAFE_RAW ) );
		$sstk_email_addresses_last_name  = sanitize_text_field( filter_input( INPUT_POST, 'sstk_email_addresses_last_name', FILTER_UNSAFE_RAW ) );
		$sstk_email_addresses_company    = sanitize_text_field( filter_input( INPUT_POST, 'sstk_email_addresses_company', FILTER_UNSAFE_RAW ) );
		$sstk_email_addresses_email_id   = sanitize_text_field( filter_input( INPUT_POST, 'sstk_email_addresses_email_id', FILTER_UNSAFE_RAW ) );
		$sstk_email_addresses_notes      = sanitize_text_field( filter_input( INPUT_POST, 'sstk_email_addresses_notes', FILTER_UNSAFE_RAW ) );

		$post_title = $sstk_email_addresses_first_name . ' ' . $sstk_email_addresses_last_name;

		// Add post.
		$new_post_id = wp_insert_post(
			array(
				'post_type'   => 'sstk_email_address',
				'post_status' => 'private',
				'post_title'  => $post_title,
				'post_author' => $author_id,
			)
		);

		if ( ! is_wp_error( $new_post_id ) && ! empty( $new_post_id ) ) {
			update_post_meta( $new_post_id, 'sstk_email_addresses_parent_id', $post_id );
			update_post_meta( $new_post_id, 'sstk_email_addresses_first_name', $sstk_email_addresses_first_name );
			update_post_meta( $new_post_id, 'sstk_email_addresses_last_name', $sstk_email_addresses_last_name );
			update_post_meta( $new_post_id, 'sstk_email_addresses_company', $sstk_email_addresses_company );
			update_post_meta( $new_post_id, 'sstk_email_addresses_email_id', $sstk_email_addresses_email_id );
			update_post_meta( $new_post_id, 'sstk_email_addresses_notes', $sstk_email_addresses_notes );

			$msg = array( 'msg' => __( 'Email successfully added.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'Error in saving.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Delete entry of email addresses.
	 */
	public function sstk_email_address_entry_delete() {
		// Nonce check.
		check_ajax_referer( 'sstk-email-address-entry-delete', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$entry_id = filter_input( INPUT_POST, 'entry_id', FILTER_SANITIZE_NUMBER_INT );

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		// Check entry_id in sstk_notify_user.
		$email_args = array(
			'post_type'   => 'sstk_email_address',
			'post_status' => 'private',
			'p'           => $entry_id,
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'sstk_email_addresses_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);

		$entry_found = get_posts( $email_args );

		if ( ! empty( $entry_found ) ) {
			// Delete entry.
			$deleted = wp_delete_post( $entry_id, true );

			if ( $deleted ) {
				$msg = array( 'msg' => __( 'Entry successfully deleted.', 'simple-stack' ) );
				wp_send_json_success( $msg );
			} else {
				$msg = array( 'msg' => __( 'Error in deletion.', 'simple-stack' ) );
				wp_send_json_success( $msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Entry not found in database.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Save to draft the compose email.
	 */
	public function sstk_compose_email_save_to_draft() {
		// Nonce check.
		check_ajax_referer( 'sstk-compose-email-save-draft', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id OR server_batch_id or app_batch_id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'sstk_server_batch' || get_post_type( $post_id ) === 'sstk_app_batch' ) {

			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				foreach ( $all_bulk_ids as $key => $value ) {
					// Permission check - user access.
					$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $value );
				}
			}
		} else {

			// Permission check - user access.
			$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $post_id );
		}

		$sstk_compose_email_send_to_server_emails = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_send_to_server_emails', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_other_emails          = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_other_emails', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_from_name             = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_from_name', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_reply_to              = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_reply_to', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_subject               = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_subject', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_body                  = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_body', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_schedule              = filter_input( INPUT_POST, 'sstk_compose_email_schedule', FILTER_SANITIZE_NUMBER_INT );
		$sstk_compose_email_schedule_datetime     = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_schedule_datetime', FILTER_UNSAFE_RAW ) );

		$sstk_compose_email_body = wpautop( $sstk_compose_email_body );

		if ( ! empty( $post_id ) ) {

			// Update some common fields.
			$this->sstk_update_common_fields_for_compose_email_tab( $post_id, $sstk_compose_email_send_to_server_emails, $sstk_compose_email_other_emails, $sstk_compose_email_from_name, $sstk_compose_email_reply_to, $sstk_compose_email_subject, $sstk_compose_email_body );

			update_post_meta( $post_id, 'sstk_compose_email_schedule_email_enable', $sstk_compose_email_schedule );

			// Cron args.
			$schedule_args = array( $post_id );

			if ( ! empty( $sstk_compose_email_schedule ) && 1 === (int) $sstk_compose_email_schedule ) {
				update_post_meta( $post_id, 'sstk_compose_email_schedule_email_datetime', $sstk_compose_email_schedule_datetime );

				$scheduled_time = strtotime( $sstk_compose_email_schedule_datetime );
				$current_time   = time();

				update_post_meta( $post_id, 'sstk_scheduled_email_cron_run', '0' );

				// Clear old cron for this post_id.
				wp_clear_scheduled_hook( 'sstk_check_for_scheduled_compose_email_send', $schedule_args );

				// If scheduled time greater than the current time then set cron.
				if ( $scheduled_time > $current_time ) {
					// Schedule cron.
					wp_schedule_event( time(), 'sstk_every_two_minutes', 'sstk_check_for_scheduled_compose_email_send', $schedule_args );
				}
			} else {
				update_post_meta( $post_id, 'sstk_scheduled_email_cron_run', '0' );

				// Clear old cron for this post_id.
				wp_clear_scheduled_hook( 'sstk_check_for_scheduled_compose_email_send', $schedule_args );
			}

			$msg = array( 'msg' => __( 'The message has been saved as draft.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		} else {
			$msg = array( 'msg' => __( 'Error in saving.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Send compose email immediately.
	 */
	public function sstk_compose_email_send_now() {
		// Nonce check.
		check_ajax_referer( 'sstk-compose-email-send-now', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id      = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );
		$all_bulk_ids = array();

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'sstk_server_batch' || get_post_type( $post_id ) === 'sstk_app_batch' ) {

			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				foreach ( $all_bulk_ids as $key => $value ) {
					// Permission check - user access.
					$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $value );
				}
			}
		} else {

			// Permission check - user access.
			$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $post_id );
		}

		$sstk_compose_email_send_to_server_emails = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_send_to_server_emails', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_other_emails          = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_other_emails', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_from_name             = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_from_name', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_reply_to              = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_reply_to', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_subject               = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_subject', FILTER_UNSAFE_RAW ) );
		$sstk_compose_email_body                  = sanitize_text_field( filter_input( INPUT_POST, 'sstk_compose_email_body', FILTER_UNSAFE_RAW ) );

		$sstk_compose_email_body = wpautop( $sstk_compose_email_body );

		if ( ! empty( $post_id ) ) {

			// Update some common fields.
			$this->sstk_update_common_fields_for_compose_email_tab( $post_id, $sstk_compose_email_send_to_server_emails, $sstk_compose_email_other_emails, $sstk_compose_email_from_name, $sstk_compose_email_reply_to, $sstk_compose_email_subject, $sstk_compose_email_body );

			// Send email code.
			$success = $this->sstk_send_compose_email_to_each_email_address( $post_id );

			if ( $success ) {
				$msg = array( 'msg' => __( 'Email successfully sent.', 'simple-stack' ) );
				wp_send_json_success( $msg );
			} else {
				$error_msg = array( 'msg' => __( 'Email can not be sent due to some error.', 'simple-stack' ) );
				wp_send_json_error( $error_msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Error in sending.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}

	/**
	 * Update common fields for compose email tab.
	 *
	 * @param int    $post_id server or app id.
	 * @param string $sstk_compose_email_send_to_server_emails sstk_compose_email_send_to_server_emails.
	 * @param string $sstk_compose_email_other_emails sstk_compose_email_other_emails.
	 * @param string $sstk_compose_email_from_name sstk_compose_email_from_name.
	 * @param string $sstk_compose_email_reply_to sstk_compose_email_reply_to.
	 * @param string $sstk_compose_email_subject sstk_compose_email_subject.
	 * @param string $sstk_compose_email_body sstk_compose_email_body.
	 */
	public function sstk_update_common_fields_for_compose_email_tab( $post_id, $sstk_compose_email_send_to_server_emails, $sstk_compose_email_other_emails, $sstk_compose_email_from_name, $sstk_compose_email_reply_to, $sstk_compose_email_subject, $sstk_compose_email_body ) {
		// Check if server screen then update the particular field.
		if ( get_post_type( $post_id ) === 'sstk_app_server' || get_post_type( $post_id ) === 'sstk_server_batch' ) {
			update_post_meta( $post_id, 'sstk_compose_email_send_to_server_emails', $sstk_compose_email_send_to_server_emails );
		}
		// Update all the fields values.
		update_post_meta( $post_id, 'sstk_compose_email_other_emails', $sstk_compose_email_other_emails );
		update_post_meta( $post_id, 'sstk_compose_email_from_name', $sstk_compose_email_from_name );
		update_post_meta( $post_id, 'sstk_compose_email_reply_to', $sstk_compose_email_reply_to );
		update_post_meta( $post_id, 'sstk_compose_email_subject', $sstk_compose_email_subject );
		update_post_meta( $post_id, 'sstk_compose_email_body', $sstk_compose_email_body );
	}

	/**
	 * Get all the emails of server or sites
	 *
	 * @param int $post_id server or app id.
	 */
	public function sstk_get_all_emails_of_server_sites( $post_id ) {
		$all_email_ids = array();

		// Check and get all the email address either for server or site.
		$emails_args = array(
			'post_type'   => 'sstk_email_address',
			'post_status' => 'private',
			'numberposts' => -1,
		);

		// Check for bulk action.
		if ( get_post_type( $post_id ) === 'sstk_server_batch' || get_post_type( $post_id ) === 'sstk_app_batch' ) {
			// Server or app ids.
			$bulk_action_ids = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
			$all_bulk_ids    = explode( ', ', $bulk_action_ids );

			if ( ! empty( $all_bulk_ids ) ) {
				$emails_args['meta_query'] = array(
					array(
						'key'     => 'sstk_email_addresses_parent_id',
						'value'   => $all_bulk_ids,
						'compare' => 'IN',
					),
				);
			}
		} else {
			$emails_args['meta_query'] = array(
				array(
					'key'     => 'sstk_email_addresses_parent_id',
					'value'   => $post_id,
					'compare' => '=',
				),
			);
		}

		$get_all_emails = get_posts( $emails_args );

		if ( ! empty( $get_all_emails ) ) {
			foreach ( $get_all_emails as $key => $value ) {
				$entry_id  = $value->ID;
				$parent_id = get_post_meta( $entry_id, 'sstk_email_addresses_parent_id', true );

				$email_address                                 = get_post_meta( $entry_id, 'sstk_email_addresses_email_id', true );
				$first_name                                    = get_post_meta( $entry_id, 'sstk_email_addresses_first_name', true );
				$last_name                                     = get_post_meta( $entry_id, 'sstk_email_addresses_last_name', true );
				$all_email_ids[ $email_address ]['first_name'] = $first_name;
				$all_email_ids[ $email_address ]['last_name']  = $last_name;
				$all_email_ids[ $email_address ]['parent_id']  = $parent_id;
			}
		}

		return $all_email_ids;
	}

	/**
	 * Send compose email to each email address
	 *
	 * @param int $post_id server or app id.
	 */
	public function sstk_send_compose_email_to_each_email_address( $post_id ) {
		$all_email_ids     = $this->sstk_get_all_emails_of_server_sites( $post_id );
		$all_app_email_ids = array();

		// Get fields of compose email.
		$sstk_compose_email_other_emails = get_post_meta( $post_id, 'sstk_compose_email_other_emails', true );
		$sstk_compose_email_from_name    = get_post_meta( $post_id, 'sstk_compose_email_from_name', true );
		$sstk_compose_email_reply_to     = get_post_meta( $post_id, 'sstk_compose_email_reply_to', true );
		$sstk_compose_email_subject      = get_post_meta( $post_id, 'sstk_compose_email_subject', true );
		$sstk_compose_email_body         = get_post_meta( $post_id, 'sstk_compose_email_body', true );

		// Check for the server screen or server batch screen.
		if ( get_post_type( $post_id ) === 'sstk_app_server' || get_post_type( $post_id ) === 'sstk_server_batch' ) {
			$send_to_emails = get_post_meta( $post_id, 'sstk_compose_email_send_to_server_emails', true );

			if ( 'servers_apps_both_emails' === $send_to_emails ) {
				// Check for bulk action.
				if ( get_post_type( $post_id ) === 'sstk_server_batch' ) {
					// Server or app ids.
					$bulk_action_ids = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
					$all_bulk_ids    = explode( ', ', $bulk_action_ids );

					if ( ! empty( $all_bulk_ids ) ) {
						$args['meta_query'] = array(
							array(
								'key'     => 'parent_post_id',
								'value'   => $all_bulk_ids,
								'compare' => 'IN',
							),
						);
					}
				} else {
					$args['meta_query'] = array(
						array(
							'key'   => 'parent_post_id',
							'value' => $post_id,
						),
					);
				}

				// Get all the apps associated with the server.
				$args = array(
					'post_type'      => 'sstk_app',
					'post_status'    => 'private',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				);

				$app_ids = get_posts( $args );
				if ( ! empty( $app_ids ) ) {
					foreach ( $app_ids as $key => $value ) {
						$app_emails        = $this->sstk_get_all_emails_of_server_sites( $value );
						$all_app_email_ids = array_merge( $app_emails, $all_app_email_ids );
					}
				}
			}
		}

		$final_emails = array_merge( $all_email_ids, $all_app_email_ids ); // Merge array.

		$other_emails = array();
		if ( ! empty( $sstk_compose_email_other_emails ) ) {
			$other_emails = explode( ',', $sstk_compose_email_other_emails );
		}
		$other_emails_arr = array();  // Create array of other emails.
		// Create full array with empty first name and last name for custom added emails.
		if ( get_post_type( $post_id ) === 'sstk_server_batch' || get_post_type( $post_id ) === 'sstk_app_batch' ) {
			if ( ! empty( $other_emails ) ) {
				foreach ( $other_emails as $key => $value ) {
					$other_emails_arr[ $value ] = array(
						'first_name' => '',
						'last_name'  => '',
						'parent_id'  => $post_id,
					);
				}
			}
		} else {
			if ( ! empty( $other_emails ) ) {
				foreach ( $other_emails as $key => $value ) {
					$other_emails_arr[ $value ] = array(
						'first_name' => '',
						'last_name'  => '',
						'parent_id'  => $post_id,
					);
				}
			}
		}
		$emails_to_send = array_merge( $final_emails, $other_emails_arr ); // Merge array.
		if ( empty( $emails_to_send ) ) {
			$msg = array( 'msg' => __( 'No email address founds to send.', 'simple-stack' ) );
			/* translators: %d post id */
			do_action( 'sstk_log_error', sprintf( __( 'Email can not be sent due to no email address for id : %d', 'simple-stack' ), $post_id ), 'error', __FILE__, __LINE__ );
			wp_send_json_error( $msg );
			wp_die();
		}

		$email_subject = apply_filters( 'sstk_compose_email_subject_text', $sstk_compose_email_subject );
		$email_body    = apply_filters( 'sstk_compose_email_body_text', $sstk_compose_email_body );

		$success = false;
		// Send the email...
		if ( ! empty( $email_subject ) && ! empty( $email_body ) ) {
			foreach ( $emails_to_send as $mail_key => $mail_value ) {
				$email_subject = apply_filters( 'sstk_compose_email_subject_text', $sstk_compose_email_subject );
				$email_body    = apply_filters( 'sstk_compose_email_body_text', $sstk_compose_email_body );

				$parent_id         = $mail_value['parent_id'];
				$bulk_action_found = false;

				// Get server owner first name & last name and server name & site name.
				$server_name = '';
				$site_name   = '';
				if ( get_post_type( $parent_id ) === 'sstk_server_batch' || get_post_type( $parent_id ) === 'sstk_app_batch' ) {
					$bulk_action_found = true;
					// Server or app ids.
					$bulk_action_ids = get_post_meta( $parent_id, 'sstk_bulk_server_app_ids', true );
					$all_bulk_ids    = explode( ', ', $bulk_action_ids );
					if ( ! empty( $all_bulk_ids ) ) {
						foreach ( $all_bulk_ids as $key => $value ) {
							if ( get_post_type( $value ) === 'sstk_app' ) {
								$server_id   = get_post_meta( $value, 'parent_post_id', true );
								$server_name = SSTK_SERVER()->get_server_name( $server_id );
								$site_name   = get_post_meta( $value, 'wpapp_domain', true );
							} else {
								$server_id   = $value;
								$server_name = SSTK_SERVER()->get_server_name( $server_id );
								$site_name   = __( 'N/A', 'simple-stack' );
							}

							$this->sstk_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $sstk_compose_email_from_name, $sstk_compose_email_reply_to );

							$success = true;
						}
					}
				} elseif ( get_post_type( $parent_id ) === 'sstk_app' ) {
					$bulk_action_found = false;
					$server_id         = get_post_meta( $parent_id, 'parent_post_id', true );
					$server_name       = SSTK_SERVER()->get_server_name( $server_id );
					$site_name         = get_post_meta( $parent_id, 'wpapp_domain', true );
				} else {
					$bulk_action_found = false;
					$server_id         = $parent_id;
					$server_name       = SSTK_SERVER()->get_server_name( $server_id );
					$site_name         = __( 'N/A', 'simple-stack' );
				}

				if ( false === (bool) $bulk_action_found ) {
					$this->sstk_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $sstk_compose_email_from_name, $sstk_compose_email_reply_to );

					$success = true;
				}
			}
		} else {
			$success = false;
			/* translators: %d post id */
			do_action( 'sstk_log_error', sprintf( __( 'Email can not be sent due to empty subject and body for id : %d', 'simple-stack' ), $post_id ), 'error', __FILE__, __LINE__ );
		}

		return $success;
	}

	/**
	 * Common code for send compose email
	 *
	 * @param int    $post_id post id.
	 * @param int    $parent_id parent id.
	 * @param int    $server_id server id.
	 * @param string $server_name server name.
	 * @param string $site_name site name.
	 * @param string $mail_key mail key.
	 * @param array  $mail_value mail value.
	 * @param string $email_subject email subject.
	 * @param string $email_body email body.
	 * @param string $sstk_compose_email_from_name sstk_compose_email_from_name.
	 * @param string $sstk_compose_email_reply_to sstk_compose_email_reply_to.
	 */
	public function sstk_common_code_for_send_compose_email( $post_id, $parent_id, $server_id, $server_name, $site_name, $mail_key, $mail_value, $email_subject, $email_body, $sstk_compose_email_from_name, $sstk_compose_email_reply_to ) {
		// Get server owner user id.
		$server_owner_id  = get_post_meta( $server_id, 'sstk_server_user_id', true );
		$owner_details    = get_userdata( $server_owner_id );
		$owner_first_name = $owner_details->first_name;
		$owner_last_name  = $owner_details->last_name;

		// Now set a standard array of replaceable parameters.
		$tokens                    = array();
		$tokens['USER_FIRSTNAME']  = $mail_value['first_name'];
		$tokens['USER_LASTNAME']   = $mail_value['last_name'];
		$tokens['OWNER_FIRSTNAME'] = $owner_first_name;
		$tokens['OWNER_LASTNAME']  = $owner_last_name;
		$tokens['SERVER_NAME']     = $server_name;
		$tokens['SITE_NAME']       = $site_name;

		// Replace tokens in email..
		$email_body = SSTK_WORDPRESS_APP()->replace_script_tokens( $email_body, $tokens );

		// Let developers have their way again with the email contents.
		$email_body = apply_filters( 'sstk_compose_email_body_text', $email_body );

		$sent = wp_mail(
			$mail_key,
			$email_subject,
			$email_body,
			array( 'Content-Type: text/html; charset=UTF-8', 'From: ' . $sstk_compose_email_from_name . ' <' . $sstk_compose_email_reply_to . '>', 'Reply-To: ' . $sstk_compose_email_reply_to )
		);

		if ( ! $sent ) {
			/* translators: %1$s mail key, %2$d post id */
			do_action( 'sstk_log_error', sprintf( __( 'Email can not be sent due to some error to %1$s for id : %2$d', 'simple-stack' ), $mail_key, $post_id ), 'error', __FILE__, __LINE__ );
		} else {
			/* translators: %1$s mail key, %2$d post id */
			do_action( 'sstk_log_error', sprintf( __( 'Email successfully sent to %1$s for id : %2$d', 'simple-stack' ), $mail_key, $post_id ), 'debug', __FILE__, __LINE__ );

			// Insert sent mail entry into cpt.
			$this->sstk_sent_email_insert_entries( $post_id, $parent_id, $mail_key, $email_subject, $email_body );
		}
	}

	/**
	 * Sent mail entries insert into CPT.
	 *
	 * @param int    $post_id post id.
	 * @param int    $parent_id parent id.
	 * @param string $mail_key mail key.
	 * @param string $email_subject email subject.
	 * @param string $email_body email body.
	 */
	public function sstk_sent_email_insert_entries( $post_id, $parent_id, $mail_key, $email_subject, $email_body ) {

		if ( empty( $parent_id ) ) {
			$parent_id = $post_id;
		}

		$post_title = $parent_id . ' - ' . $mail_key;
		$author_id  = get_current_user_id();

		// Add post.
		$new_post_id = wp_insert_post(
			array(
				'post_type'   => 'sstk_sent_emails',
				'post_status' => 'private',
				'post_title'  => $post_title,
				'post_author' => $author_id,
			)
		);

		if ( ! is_wp_error( $new_post_id ) && ! empty( $new_post_id ) ) {
			update_post_meta( $new_post_id, 'sstk_sent_email_parent_id', $parent_id );
			update_post_meta( $new_post_id, 'sstk_sent_email_email_address', $mail_key );
			update_post_meta( $new_post_id, 'sstk_sent_email_email_subject', $email_subject );
			update_post_meta( $new_post_id, 'sstk_sent_email_email_body', $email_body );
		}
	}

	/**
	 * Cron function code to send compose email on scheduled time.
	 *
	 * @param int $post_id server or app id.
	 */
	public function sstk_send_compose_email_on_scheduled_time_for_servers_apps( $post_id ) {

		$sstk_compose_email_schedule_datetime = get_post_meta( $post_id, 'sstk_compose_email_schedule_email_datetime', true );
		$cron_run                             = get_post_meta( $post_id, 'sstk_scheduled_email_cron_run', true );

		// Convert date and time into seconds.
		$scheduled_time = strtotime( $sstk_compose_email_schedule_datetime );
		$current_time   = time();

		if ( $current_time > $scheduled_time && '0' === $cron_run ) {
			// Send mail function call.
			$success = $this->sstk_send_compose_email_to_each_email_address( $post_id );

			// Mark as cron run.
			update_post_meta( $post_id, 'sstk_scheduled_email_cron_run', '1' );

			// Clear cron for this post_id.
			$schedule_args = array( $post_id );
			wp_clear_scheduled_hook( 'sstk_check_for_scheduled_compose_email_send', $schedule_args );
		}
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
				self::sstk_scheduled_compose_email_schedule_events();
				restore_current_blog();
			}
		} else {
			self::sstk_scheduled_compose_email_schedule_events();
		}

	}

	/**
	 * Schedule events on Activation of the plugin.
	 *
	 * @return void
	 */
	public static function sstk_scheduled_compose_email_schedule_events() {
		// Clear old crons.
		wp_unschedule_hook( 'sstk_check_for_scheduled_compose_email_send' );

		// Get all servers.
		$server_args = array(
			'post_type'      => 'sstk_app_server',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$server_ids  = get_posts( $server_args );

		// Get all apps.
		$app_args = array(
			'post_type'      => 'sstk_app',
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);
		$app_ids  = get_posts( $app_args );

		$server_app_ids = array_merge( $server_ids, $app_ids );

		// Set the cron for servers and apps.
		if ( ! empty( $server_app_ids ) ) {
			foreach ( $server_app_ids as $key => $value ) {
				$scheduled_enabled = get_post_meta( $value, 'sstk_compose_email_schedule_email_enable', true );
				$scheduled_time    = get_post_meta( $value, 'sstk_compose_email_schedule_email_datetime', true );
				if ( 1 === (int) $scheduled_enabled ) {
					if ( strtotime( $scheduled_time ) > time() ) {
						$schedule_args = array( $value );
						wp_clear_scheduled_hook( 'sstk_check_for_scheduled_compose_email_send', $schedule_args );
						// Set new crons for compose email.
						wp_schedule_event( time(), 'sstk_every_two_minutes', 'sstk_check_for_scheduled_compose_email_send', $schedule_args );
					}
				}
			}
		}

	}

	/**
	 * Fires on deactivation of plugin.
	 *
	 * @param  boolean $network_wide    True if plugin is deactivated network-wide.
	 *
	 * @return void
	 */
	public static function deactivate( $network_wide ) {

		if ( is_multisite() && $network_wide ) {
			// Get all blogs in the network.
			$blog_ids = get_sites( array( 'fields' => 'ids' ) );
			foreach ( $blog_ids as $blog_id ) {
				switch_to_blog( $blog_id );
				self::sstk_scheduled_compose_email_clear_scheduled_events();
				restore_current_blog();
			}
		} else {
			self::sstk_scheduled_compose_email_clear_scheduled_events();
		}

	}

	/**
	 * Clears scheduled events on Deactivation of the plugin.
	 *
	 * @return void
	 */
	public static function sstk_scheduled_compose_email_clear_scheduled_events() {
		wp_unschedule_hook( 'sstk_check_for_scheduled_compose_email_send' );
	}

	/**
	 * To schedule events for newly created site on WP Multisite.
	 *
	 * Action hook: wp_initialize_site
	 *
	 * @param  object $new_site new site.
	 * @param  array  $args args.
	 * @return void
	 */
	public function sstk_scheduled_compose_email_schedule_events_for_new_site( $new_site, $args ) {

		$plugin_name = SSTK_PLUGIN;

		// To check the plugin is active network-wide.
		if ( is_plugin_active_for_network( $plugin_name ) ) {

			$blog_id = $new_site->blog_id;

			switch_to_blog( $blog_id );
			self::sstk_scheduled_compose_email_schedule_events();
			restore_current_blog();
		}

	}

	/**
	 * Display the sent email details popup when click on view button
	 */
	public function sstk_sent_email_details_popup() {
		// nonce check.
		check_ajax_referer( 'sstk-sent-email-details-display', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$post_id = filter_input( INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $post_id );

		ob_start();
		require SSTK_PATH . 'includes/templates/sent_email_details_popup.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

		exit;
	}

	/**
	 * Delete entry of sent emails.
	 */
	public function sstk_sent_email_entry_delete() {
		global $wpdb;
		// Nonce check.
		check_ajax_referer( 'sstk-sent-email-entry-delete', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		$entry_id = filter_input( INPUT_POST, 'entry_id', FILTER_SANITIZE_NUMBER_INT );

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		if ( '0' === (string) $entry_id ) {
			$email_args = array( '' );
		} else {
			$email_args = array( 'p' => $entry_id );
		}

		// Check entry_id in sstk_sent_emails.
		$sent_email_args = array(
			'post_type'   => 'sstk_sent_emails',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'sstk_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);

		$entry_found = get_posts( $sent_email_args );

		if ( ! empty( $entry_found ) ) {

			if ( '0' === (string) $entry_id ) {
				// Delete all entries.
				$delete_query = "DELETE p, pm FROM wp_posts p INNER JOIN wp_postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'sstk_sent_emails' AND pm.meta_key = 'sstk_sent_email_parent_id' AND pm.meta_value = '" . $parent_id . "'";

				$deleted = $wpdb->query( $delete_query );
			} else {
				// Delete entry.
				$deleted = wp_delete_post( $entry_id, true );
			}

			if ( $deleted ) {
				$msg = array( 'msg' => __( 'Successfully deleted.', 'simple-stack' ) );
				wp_send_json_success( $msg );
			} else {
				$msg = array( 'msg' => __( 'Error in deletion.', 'simple-stack' ) );
				wp_send_json_success( $msg );
			}
		} else {
			$msg = array( 'msg' => __( 'Entry not found in database.', 'simple-stack' ) );
			wp_send_json_success( $msg );
		}

		exit;
	}


	/**
	 * Sent email list with pagination.
	 */
	public function sstk_sent_emails_list_pagination() {
		global $wpdb;
		// Nonce check.
		check_ajax_referer( 'sstk-sent-email-list-pagination', 'nonce' );

		// Permission check - login user.
		if ( ! is_user_logged_in() ) {
			$error_msg = array( 'msg' => __( 'Only loggedin admin user can perform this action.', 'simple-stack' ) );
			wp_send_json_error( $error_msg );
			wp_die();
		}

		// Server id or app id.
		$parent_id = filter_input( INPUT_POST, 'parent_id', FILTER_SANITIZE_NUMBER_INT );
		$page      = filter_input( INPUT_POST, 'page', FILTER_SANITIZE_NUMBER_INT );

		// Permission check - user access.
		$this->sstk_email_user_check_server_app_permission( get_current_user_id(), $parent_id );

		$cur_page = $page;
		$page    -= 1;
		// Set the number of results to display.
		$per_page     = 10;
		$previous_btn = true;
		$next_btn     = true;
		$first_btn    = true;
		$last_btn     = true;
		$start        = $page * $per_page;

		// Get total sent email entries.
		$all_sent_emails   = array(
			'post_type'   => 'sstk_sent_emails',
			'post_status' => 'private',
			'numberposts' => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'meta_query'  => array(
				array(
					'key'     => 'sstk_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);
		$count_sent_emails = get_posts( $all_sent_emails );
		$count             = count( $count_sent_emails );

		// Check and get all the sent emails either for server or site.
		$sent_emails_args = array(
			'post_type'      => 'sstk_sent_emails',
			'post_status'    => 'private',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'posts_per_page' => $per_page,
			'offset'         => $start,
			'meta_query'     => array(
				array(
					'key'     => 'sstk_sent_email_parent_id',
					'value'   => $parent_id,
					'compare' => '=',
				),
			),
		);
		$get_sent_emails  = get_posts( $sent_emails_args );

		$html = '';

		$html .= '<div class="sstk_sent_email_list_sec">
					<table cellspacing="5" cellpadding="5px" class="sstk_sent_email_list_table">
					<tr>
						<th>' . __( 'Email Address', 'simple-stack' ) . '</th>
						<th>' . __( 'Subject', 'simple-stack' ) . '</th>
						<th>' . __( 'Body', 'simple-stack' ) . '</th>
						<th>' . __( 'Date', 'simple-stack' ) . '</th>
						<th>' . __( 'Delete', 'simple-stack' ) . '</th>
					</tr>';

		if ( ! empty( $get_sent_emails ) ) {
			foreach ( $get_sent_emails as $key => $value ) {
				$entry_id = $value->ID;
				$date     = $value->post_date;

				$parent_id     = get_post_meta( $entry_id, 'sstk_sent_email_parent_id', true );
				$email_address = get_post_meta( $entry_id, 'sstk_sent_email_email_address', true );
				$subject       = get_post_meta( $entry_id, 'sstk_sent_email_email_subject', true );
				$body          = get_post_meta( $entry_id, 'sstk_sent_email_email_body', true );

				$html .= '<tr>
							<td>' . esc_html( $email_address ) . '</td>
							<td>' . esc_html( $subject ) . '</td>
							<td><a href="javascript:void(0)" class="sstk_view_email_details" data-nonce="' . wp_create_nonce( 'sstk-sent-email-details-display' ) . '" data-action="sstk_sent_email_details_popup" data-post_id="' . $entry_id . '">' . __( 'View', 'simple-stack' ) . '</a></td>
							<td>' . esc_html( $date ) . '</td>
							<td><a href="javascript:void(0)" class="sstk_delete_sent_entry" data-parent_id="' . esc_attr( $parent_id ) . '" data-entry_id="' . esc_attr( $entry_id ) . '" data-action="sstk_sent_email_entry_delete" data-nonce="' . wp_create_nonce( 'sstk-sent-email-entry-delete' ) . '" >' . __( 'Delete', 'simple-stack' ) . '</a></td>
						</tr>';
			}
		} else {
			$html .= '<tr><td colspan="6">' . __( 'No sent emails found.', 'simple-stack' ) . '</td></tr>';
		}

			$html .= '</table>
				</div>';

		// This is where the magic happens.
		$no_of_paginations = ceil( $count / $per_page );

		if ( $cur_page >= 7 ) {
			$start_loop = $cur_page - 3;
			if ( $no_of_paginations > $cur_page + 3 ) {
				$end_loop = $cur_page + 3;
			} elseif ( $cur_page <= $no_of_paginations && $cur_page > $no_of_paginations - 6 ) {
				$start_loop = $no_of_paginations - 6;
				$end_loop   = $no_of_paginations;
			} else {
				$end_loop = $no_of_paginations;
			}
		} else {
			$start_loop = 1;
			if ( $no_of_paginations > 7 ) {
				$end_loop = 7;
			} else {
				$end_loop = $no_of_paginations;
			}
		}

		// Pagination Buttons logic.
		$pag_container = "
        <div class='sstk-universal-pagination'>
            <ul>";

		if ( $first_btn && $cur_page > 1 ) {
			$pag_container .= "<li p='1' class='active'>First</li>";
		} elseif ( $first_btn ) {
			$pag_container .= "<li p='1' class='inactive'>First</li>";
		}

		if ( $previous_btn && $cur_page > 1 ) {
			$pre            = $cur_page - 1;
			$pag_container .= "<li p='$pre' class='active'>Previous</li>";
		} elseif ( $previous_btn ) {
			$pag_container .= "<li class='inactive'>Previous</li>";
		}
		for ( $i = $start_loop; $i <= $end_loop; $i++ ) {
			if ( (int) $cur_page === (int) $i ) {
				$pag_container .= "<li p='$i' class = 'selected' >{$i}</li>";
			} else {
				$pag_container .= "<li p='$i' class='active'>{$i}</li>";
			}
		}

		if ( $next_btn && $cur_page < $no_of_paginations ) {
			$nex            = $cur_page + 1;
			$pag_container .= "<li p='$nex' class='active'>Next</li>";
		} elseif ( $next_btn ) {
			$pag_container .= "<li class='inactive'>Next</li>";
		}

		if ( $last_btn && $cur_page < $no_of_paginations ) {
			$pag_container .= "<li p='$no_of_paginations' class='active'>Last</li>";
		} elseif ( $last_btn ) {
			$pag_container .= "<li p='$no_of_paginations' class='inactive'>Last</li>";
		}

		$pag_container = $pag_container . '
            </ul>
        </div>';

		// We echo the final output.
		echo '<div class = "sstk-pagination-content">' . $html . '</div>' .
		'<div class = "sstk-pagination-nav">' . $pag_container . '</div>';

		exit;
	}

	/**
	 * Check the user permission in ajax call.
	 *
	 * @param int $user_id user id.
	 * @param int $post_id post id.
	 */
	public function sstk_email_user_check_server_app_permission( $user_id, $post_id ) {
		if ( get_post_type( $post_id ) === 'sstk_app_server' ) {
			if ( ! sstk_user_can( $user_id, 'view_server', $post_id ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action.', 'simple-stack' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		} else {
			if ( ! sstk_user_can( $user_id, 'view_app', $post_id ) ) {
				$error_msg = array( 'msg' => __( 'You are not allowed to perform this action.', 'simple-stack' ) );
				wp_send_json_error( $error_msg );
				wp_die();
			}
		}
	}

	/**
	 * Add new bulk options in server list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function sstk_add_new_bulk_actions_server( $bulk_array ) {
		// Remove bulk trash option.
		// @TODO: Why is this here instead of in the SERVER POST class?  What does this have to do with emails?
		$disable_bulk_delete = (int) sstk_get_option( 'wordpress_app_disable_bulk_delete_on_full_server_list' );
		if ( 1 === $disable_bulk_delete ) {
			unset( $bulk_array['trash'] );
		}
		$bulk_array['sstk_compose_send_email'] = __( 'Send Email', 'simple-stack' );
		return $bulk_array;
	}

	/**
	 * Add new bulk options in app list screen.
	 *
	 * @param array $bulk_array bulk array.
	 */
	public function sstk_add_new_bulk_actions_app( $bulk_array ) {
		$bulk_array['sstk_compose_send_email'] = __( 'Send Email', 'simple-stack' );
		return $bulk_array;
	}

	/**
	 * Handle bulk action for server and app.
	 *
	 * @param string $redirect_url redirect url.
	 * @param string $action action.
	 * @param array  $post_ids all post ids.
	 */
	public function sstk_bulk_action_handler_server_app( $redirect_url, $action, $post_ids ) {
		// Let's remove query args first.
		$redirect_url = remove_query_arg( array( 'sstk_sent_compose_email' ), $redirect_url );
		// Do something for "Compose & Send Email" bulk action.
		if ( 'sstk_compose_send_email' === $action ) {
			if ( ! empty( $post_ids ) ) {

				// get current user details.
				$author_id  = get_current_user_id();
				$usermeta   = get_user_by( 'id', $author_id );
				$user_login = $usermeta->data->user_login;
				$post_title = 'Submitted by ' . $user_login;

				if ( get_post_type( $post_ids[0] ) === 'sstk_app_server' ) {
					$batch_id = wp_insert_post(
						array(
							'post_type'   => 'sstk_server_batch',
							'post_status' => 'private',
							'post_title'  => $post_title,
							'post_author' => $author_id,
						)
					);
				}

				if ( get_post_type( $post_ids[0] ) === 'sstk_app' ) {
					$batch_id = wp_insert_post(
						array(
							'post_type'   => 'sstk_app_batch',
							'post_status' => 'private',
							'post_title'  => $post_title,
							'post_author' => $author_id,
						)
					);
				}

				if ( ! is_wp_error( $batch_id ) && ! empty( $batch_id ) ) {

					// Update the all bulk server/app ids.
					$all_bulk_ids = implode( ', ', $post_ids );
					update_post_meta( $batch_id, 'sstk_bulk_server_app_ids', $all_bulk_ids );

					$batch_url = admin_url( 'post.php?post=' . $batch_id . '&action=edit' );
					wp_safe_redirect( $batch_url );
					exit;
				}
			}
		}

		return $redirect_url;
	}


	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function server_app_batches_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'sstk_bulk_action_ids':
				$all_bulk_ids = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
				$value        = $this->sstk_selected_bulk_ids_display_name( $all_bulk_ids );
				break;

			default:
				break;
		}

		echo esc_html( $value );
	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function server_app_batches_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['sstk_bulk_action_ids'] = __( 'Bulk Action Ids', 'simple-stack' );

		return $defaults;
	}

	/**
	 * Remove author box from server and app batches.
	 */
	public function sstk_batch_posttypes_remove_author_box() {
		remove_meta_box( 'authordiv', 'sstk_server_batch', 'normal' );
		remove_meta_box( 'authordiv', 'sstk_app_batch', 'normal' );
	}

	/**
	 * Move author box to side in publish box for server and app batches.
	 */
	public function sstk_batch_posttypes_author_in_publish() {
		global $post_ID;
		$post = get_post( $post_ID );

		if ( 'sstk_server_batch' === $post->post_type || 'sstk_app_batch' === $post->post_type ) {
			echo '<div class="misc-pub-section sstk-author-sec">' . esc_html( __( 'Author:', 'simple-stack' ) );
			post_author_meta_box( $post );
			echo '</div>';
		}
	}

	/**
	 * Display the name of selected bulk ids from server or app.
	 *
	 * @param string $all_bulk_ids all_bulk_ids.
	 *
	 * @return string
	 */
	public function sstk_selected_bulk_ids_display_name( $all_bulk_ids ) {
		$server_app_name = '';
		$bulk_ids_arr    = explode( ', ', $all_bulk_ids );
		if ( ! empty( $bulk_ids_arr ) ) {
			foreach ( $bulk_ids_arr as $bulk_key => $bulk_value ) {
				if ( get_post_type( $bulk_value ) === 'sstk_app_server' ) {
					$server_app_name .= SSTK_SERVER()->get_server_name( $bulk_value ) . ', ';
				} else {
					$site_post = get_post( $bulk_value );
					if ( $site_post ) {
						$server_app_name .= $site_post->post_title . ', ';
					}
				}
			}
		}
		$server_app_name = rtrim( $server_app_name, ', ' );
		return $server_app_name;
	}


	/**
	 * Gets the HTML for display the selected server or app ids in bulk details.
	 *
	 * @param int $post_id bulk action id.
	 *
	 * @return string
	 */
	public function display_the_selected_server_app_bulk_ids( $post_id ) {

		$all_bulk_ids     = get_post_meta( $post_id, 'sstk_bulk_server_app_ids', true );
		$server_app_names = $this->sstk_selected_bulk_ids_display_name( $all_bulk_ids );

		$html = '';

		$html .= '<div class="rwmb-field rwmb-text-wrapper">
					<div class="rwmb-label">
						<label for="sstk_email_addresses_first_name">' . __( 'Selected Bulk Ids', 'simple-stack' ) . '</label>
					</div>
					<div class="rwmb-input">' . $server_app_names . '</div>
				</div>';

		return $html;
	}
}
