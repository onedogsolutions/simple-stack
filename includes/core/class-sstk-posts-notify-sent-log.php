<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * SSTK_NOTIFY_SENT class - used to manage post type that holds a log of all notifications sent to users.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSTK_NOTIFY_SENT
 */
class SSTK_NOTIFY_SENT extends SSTK_POSTS_LOG {

	/**
	 * SSTK_NOTIFY_SENT instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * SSTK_NOTIFY_SENT constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->register();  // register the custom post type - sstk_notify_sent.
		$this->hooks();     // register hooks to make the custom post type do things...
	}

	/**
	 * Add all the hook inside the this private method.
	 */
	private function hooks() {

		// Meta box display callback for notification sent log.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		// Action hook to add values in new columns.
		add_action( 'manage_sstk_notify_sent_posts_custom_column', array( $this, 'notify_sent_table_content' ), 10, 2 );

		// Add filter hook to add new columns on notify sent log listing screen.
		add_filter( 'manage_sstk_notify_sent_posts_columns', array( $this, 'notify_sent_table_head' ), 10, 1 );

		// Filter hook to remove edit bulk action from notify sent log listing screen.
		add_filter( 'bulk_actions-edit-sstk_notify_sent', array( $this, 'sstk_log_bulk_actions' ), 10, 1 );

		// Action hook to scan new notifications.
		add_action( 'sstk_scan_notifications_actions', array( $this, 'sstk_scan_new_notifications_actions' ), 10 );
	}

	/**
	 * Register the custom post type.
	 */
	public function register() {
		register_post_type(
			'sstk_notify_sent',
			array(
				'labels'              => array(
					'name'                  => _x( 'Notifications Sent Logs', 'Post type general name', 'simple-stack' ),
					'singular_name'         => _x( 'Notifications Sent Log', 'Post type singular name', 'simple-stack' ),
					'menu_name'             => _x( 'Notifications Sent Log', 'Admin Menu text', 'simple-stack' ),
					'name_admin_bar'        => _x( 'Notifications Sent Log', 'Add New on Toolbar', 'simple-stack' ),
					'edit_item'             => __( 'Edit Notification Sent', 'simple-stack' ),
					'view_item'             => __( 'View Notification Sent', 'simple-stack' ),
					'all_items'             => __( 'History', 'simple-stack' ), // Label to signify all items in a submenu link.
					'search_items'          => __( 'Search Logs', 'simple-stack' ),
					'not_found'             => __( 'No Logs were found.', 'simple-stack' ),
					'not_found_in_trash'    => __( 'No Logs were found in Trash.', 'simple-stack' ),
					'filter_items_list'     => _x( 'Filter Notification Sent Logs list', 'Screen reader text for the filter links heading on the post type listing screen. Default "Filter posts list"/"Filter pages list". Added in 4.4', 'simple-stack' ),
					'items_list_navigation' => _x( 'Notification Sent Logs list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default "Posts list navigation"/"Pages list navigation". Added in 4.4', 'simple-stack' ),
					'items_list'            => _x( 'Notification Sent Logs list', 'Screen reader text for the items list heading on the post type listing screen. Default "Posts list"/"Pages list". Added in 4.4', 'simple-stack' ),
				),
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=sstk_notify_log',
				'show_in_nav_menus'   => true,
				'show_in_admin_bar'   => false,
				'public'              => true,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'hierarchical'        => false,
				'menu_position'       => null,
				'supports'            => array( '' ),
				'rewrite'             => null,
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => false,
					'read_posts'   => 'sstk_manage_logs',
					'edit_posts'   => 'sstk_manage_logs',
				),
				'map_meta_cap'        => true,
			)
		);

		$this->set_post_type( 'sstk_notify_sent' );

		$search_fields = array(
			'notify_sent_parent_id',
			'notify_sent_types',
			'notify_sent_message',
			'notify_sent_references',
			'notify_sent_success',
			'notify_sent_to_platform',
		);

		$this->set_post_search_fields( $search_fields );

	}

	/**
	 * Add contents to the table columns
	 *
	 * @param string $column_name column name.
	 * @param int    $post_id post id.
	 *
	 * print column value.
	 */
	public function notify_sent_table_content( $column_name, $post_id ) {

		$value = '';

		switch ( $column_name ) {

			case 'sstk_notify_sent_parent_id':
				// Display the name of the server or app.
				$parent_post_id = get_post_meta( $post_id, 'parent_post_id', true );
				if ( $parent_post_id ) {
					$parent_post = get_post( $parent_post_id );
					if ( $parent_post ) {
						$title = wp_kses_post( $parent_post->post_title );
						$value = sprintf( '<a href="%s">' . (string) $parent_post_id . '</a>', get_edit_post_link( $parent_post_id ) );
					} else {
						$value = __( 'Parent post not found', 'simple-stack' );
					}
				} else {
					$value = __( 'Parent post not found', 'simple-stack' );
				}

				break;

			case 'sstk_notify_sent_types':
				// Display the list of types.
				$value = get_post_meta( $post_id, 'notify_sent_types', true );
				break;

			case 'sstk_notify_sent_message':
				// Display the notify message.
				$value = get_post_meta( $post_id, 'notify_sent_message', true );
				break;

			case 'sstk_notify_sent_references':
				// Display the list of references.
				$value = get_post_meta( $post_id, 'notify_sent_references', true );
				break;

			case 'sstk_notify_sent_success':
				// Display the success or failure.
				$value = get_post_meta( $post_id, 'notify_sent_success', true );
				if ( '1' === $value ) {
					$value = 'Yes';
				} else {
					$value = 'No';
				}
				break;

			case 'sstk_notify_sent_to_platform':
				// Display the platform where notification was sent to.
				$value = get_post_meta( $post_id, 'notify_sent_to_platform', true );
				if ( strlen( $value ) >= 200 ) {
					$value = substr( $value, 0, 200 ) . '...';
				}
				break;

			default:
				break;
		}

		$allowed_html = array(
			'a'      => array(
				'href'  => array(),
				'title' => array(),
			),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
		);

		echo wp_kses( $value, $allowed_html );

	}

	/**
	 * Add table header values
	 *
	 * @param array $defaults array of default head values.
	 *
	 * @return $defaults modified array with new columns
	 */
	public function notify_sent_table_head( $defaults ) {

		unset( $defaults['date'] );

		$defaults['sstk_notify_sent_parent_id']     = __( 'Owner/Parent', 'simple-stack' );
		$defaults['sstk_notify_sent_types']         = __( 'Type', 'simple-stack' );
		$defaults['sstk_notify_sent_message']       = __( 'Message', 'simple-stack' );
		$defaults['sstk_notify_sent_references']    = __( 'Reference', 'simple-stack' );
		$defaults['sstk_notify_sent_success']       = __( 'Sent', 'simple-stack' );
		$defaults['sstk_notify_sent_to_platform']   = __( 'Sent To', 'simple-stack' );
		$defaults['date']                           = __( 'Date', 'simple-stack' );

		return $defaults;

	}

	/**
	 * Register meta box(es).
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'notify_sent',
			__( 'Notifications Sent Log Entry', 'simple-stack' ),
			array( $this, 'render_notify_sent_meta_box' ),
			'sstk_notify_sent',
			'advanced',
			'high'
		);
	}

	/**
	 * Render the notifications sent log entry detail meta box
	 *
	 * @param WP_Post $post Current post object.
	 *
	 * @print HTML $html HTML of the meta box
	 */
	public function render_notify_sent_meta_box( $post ) {

		$html = '';

		$parent_post_id             = get_post_meta( $post->ID, 'parent_post_id', true );
		$notify_sent_types          = get_post_meta( $post->ID, 'notify_sent_types', true );
		$notify_sent_message        = get_post_meta( $post->ID, 'notify_sent_message', true );
		$notify_sent_references     = get_post_meta( $post->ID, 'notify_sent_references', true );
		$notify_sent_success        = get_post_meta( $post->ID, 'notify_sent_success', true );
		$notify_sent_to_platform    = get_post_meta( $post->ID, 'notify_sent_to_platform', true );

		ob_start();
		require SSTK_PATH . 'includes/templates/notify_sent_log.php';
		$html = ob_get_contents();
		ob_end_clean();

		echo $html;

	}


	/**
	 * Add a new User Notification Sent Log record
	 *
	 * @param int     $parent_post_id The post id that represents the item this log is being done against.
	 * @param string  $notification_type The type of notification.
	 * @param string  $message The notification message itself.
	 * @param string  $notification_reference any additional or third party reference.
	 * @param boolean $success notification sent or not.
	 * @param string  $sent_to notification sent to email/slack/zapier.
	 * @param int     $post_id The ID of an existing log, if it exists.
	 */
	public function add_user_notify_sent_log_entry( $parent_post_id, $notification_type, $message, $notification_reference, $success, $sent_to = null, $post_id = null ) {

		// Author is current user or system.
		$author_id = get_current_user();

		// Get parent post.
		$post = get_post( $parent_post_id );

		if ( empty( $post_id ) ) {
			if ( $post ) {
				$post_title = $post->post_title;
			} else {
				$post_title = $message;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'sstk_notify_sent',
					'post_status' => 'private',
					'post_title'  => 'User notification for ' . $post_title,
					'post_author' => $author_id,
				)
			);
		}

		// if $message is an error, convert to string...
		if ( is_wp_error( $message ) ) {
			$message = esc_html( $message );
		}

		if ( ! is_wp_error( $post_id ) && ! empty( $post_id ) ) {
			update_post_meta( $post_id, 'parent_post_id', $parent_post_id );
			update_post_meta( $post_id, 'notify_sent_types', $notification_type );
			update_post_meta( $post_id, 'notify_sent_message', $message );
			update_post_meta( $post_id, 'notify_sent_references', $notification_reference );
			update_post_meta( $post_id, 'notify_sent_success', $success );

			// If notification is sent successfully then store the platform value where it was sent to.
			if ( $success ) {
				update_post_meta( $post_id, 'notify_sent_to_platform', $sent_to );
			} else {
				update_post_meta( $post_id, 'notify_sent_to_platform', '-' );
			}
		}

		return $post_id;

	}


	/**
	 * Scan for new notifications and send it to the user
	 */
	public function sstk_scan_new_notifications_actions() {

		// Get all posts of user notifications post type.
		$user_alerts = get_posts(
			array(
				'post_type'   => 'sstk_notify_user',
				'post_status' => 'private',
				'numberposts' => -1,
			)
		);

		// Get ids for users who will be notified.
		$notify_users = array();
		if ( ! empty( $user_alerts ) ) {
			foreach ( $user_alerts as $key => $value ) {
				$author_id                    = $value->post_author;
				$user_notify_id               = $value->ID;
				$notify_users[ $author_id ][] = array( 'alert_id' => $user_notify_id );
			}
		}

		// Create query to get new notifications.
		$notify_args = array(
			'post_type'   => 'sstk_notify_log',
			'post_status' => 'private',
			'numberposts' => 10,
			'orderby'     => 'date',
			'order'       => 'ASC',
			'meta_query'  => array(
				array(
					'key'     => 'notification_sent',
					'value'   => 0,
					'compare' => '=',
				),
			),
		);

		// Run the query to get the new notifications.
		$notify_logs = get_posts( $notify_args );

		// Start looping through the notifications.
		if ( ! empty( $notify_logs ) ) {
			foreach ( $notify_logs as $notify_key => $notify_value ) {
				$notify_log_id      = $notify_value->ID;
				$notify_log_created = $notify_value->post_date;
				$date               = gmdate( 'Y-m-d', strtotime( $notify_log_created ) );
				$time               = gmdate( 'H:i:s', strtotime( $notify_log_created ) );

				// get notification log data.
				$notify_type    = get_post_meta( $notify_log_id, 'notification_type', true );
				$notify_ref     = get_post_meta( $notify_log_id, 'notification_reference', true );
				$notify_message = get_post_meta( $notify_log_id, 'notification_message', true );
				$parent_post_id = get_post_meta( $notify_log_id, 'parent_post_id', true );

				$server_name = '';
				$ipv4        = '';
				$provider    = '';
				$server_id   = '';
				$domain_name = '';
				$site_id     = '';

				if ( get_post_type( $parent_post_id ) === 'sstk_app_server' ) {
					$server_name = SSTK_SERVER()->get_server_name( $parent_post_id );
					$ipv4        = get_post_meta( $parent_post_id, 'sstk_server_ipv4', true );
					$ipv6        = get_post_meta( $parent_post_id, 'sstk_server_ipv6', true );
					$provider    = get_post_meta( $parent_post_id, 'sstk_server_provider', true );
					$server_id   = $parent_post_id;
					$site_id     = __( 'N/A', 'simple-stack' );
					$domain_name = __( 'N/A', 'simple-stack' );
				} else {
					$server_id   = get_post_meta( $parent_post_id, 'parent_post_id', true );
					$server_name = SSTK_SERVER()->get_server_name( $server_id );
					$ipv4        = get_post_meta( $server_id, 'sstk_server_ipv4', true );
					$ipv6        = get_post_meta( $server_id, 'sstk_server_ipv6', true );
					$provider    = get_post_meta( $server_id, 'sstk_server_provider', true );
					$domain_name = get_post_meta( $parent_post_id, 'wpapp_domain', true );
					$site_id     = $parent_post_id;
				}

				// loop of user alerts for each notification log.
				if ( ! empty( $notify_users ) ) {
					foreach ( $notify_users as $user_key => $user_value ) {
						if ( ! empty( $user_value ) ) {
							$usermeta         = get_user_by( 'id', $user_key );
							$user_login       = $usermeta->data->user_login;
							$first_name       = $usermeta->first_name;
							$last_name        = $usermeta->last_name;
							$alert_user_id    = $usermeta->data->ID;
							$alert_user_email = $usermeta->data->user_email;
							foreach ( $user_value as $alert_key => $alert_value ) {
								$alert_id = $alert_value['alert_id'];

								// get user alert data.
								$user_email        = get_post_meta( $alert_id, 'sstk_notify_user_email_addresses', true );
								$user_slack        = get_post_meta( $alert_id, 'sstk_notify_user_slack_webhooks', true );
								$user_zapier       = get_post_meta( $alert_id, 'sstk_notify_user_zapier_send', true );
								$user_zapier_hooks = get_post_meta( $alert_id, 'sstk_notify_user_zapier_webhooks', true );

								$user_sites     = get_metadata( 'post', $alert_id, 'sstk_notify_user_sites', false );
								$user_all_sites = SSTK_POSTS_NOTIFY_USER()->get_user_notify_sites( $alert_user_id );
								$user_all_sites = SSTK_POSTS_NOTIFY_USER()->sstk_get_actual_values_notify_fields( $user_all_sites );
								// Default all items - if sites not found.
								if ( empty( $user_sites ) ) {
									$user_sites = $user_all_sites;
								}

								$user_servers     = get_metadata( 'post', $alert_id, 'sstk_notify_user_servers', false );
								$user_all_servers = SSTK_POSTS_NOTIFY_USER()->get_user_notify_servers( $alert_user_id );
								$user_all_servers = SSTK_POSTS_NOTIFY_USER()->sstk_get_actual_values_notify_fields( $user_all_servers );
								// Default all items - if servers not found.
								if ( empty( $user_servers ) ) {
									$user_servers = $user_all_servers;
								}

								$user_types = get_metadata( 'post', $alert_id, 'sstk_notify_user_type', false );
								// Default all items - if types not found.
								if ( empty( $user_types ) ) {
									$user_types = SSTK_POSTS_NOTIFY_USER()->get_user_notify_types();
									$user_types = SSTK_POSTS_NOTIFY_USER()->sstk_get_actual_values_notify_fields( $user_types );
								}

								$user_references = get_metadata( 'post', $alert_id, 'sstk_notify_user_reference', false );
								// Default all items - if references not found.
								if ( empty( $user_references ) ) {
									$user_references = SSTK_POSTS_NOTIFY_USER()->get_user_notify_references();
									$user_references = SSTK_POSTS_NOTIFY_USER()->sstk_get_actual_values_notify_fields( $user_references );
								}

								// Compare the notification logs value with user alert.
								if ( ( in_array( (int) $parent_post_id, $user_sites ) || in_array( (int) $parent_post_id, $user_servers ) ) && ( in_array( $notify_type, $user_types ) && in_array( $notify_ref, $user_references ) ) ) {

									// Check if user has permission to view server/site.
									if ( in_array( $parent_post_id, $user_all_servers ) || in_array( $parent_post_id, $user_all_sites ) ) {

										// Send email notification to user.
										if ( ! empty( $user_email ) ) {
											$this->sstk_send_email_notifications_to_user( $alert_id, $notify_log_id, $user_email, $user_login, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider );
										}

										// Send slack notification to user.
										if ( ! empty( $user_slack ) ) {
											$this->sstk_send_slack_webhook_notifications_to_user( $alert_id, $notify_log_id, $user_slack, $user_login, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider );
										}

										// Send zapier notification to user.
										if ( ! empty( $user_zapier_hooks ) && $user_zapier == '1' ) {
											$this->sstk_send_zapier_webhook_notifications_to_user( $alert_id, $notify_log_id, $user_zapier_hooks, $user_login, $alert_user_id, $alert_user_email, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider );
										}
									}
								}
							}
						}
					}
				}

				// update notification send status to prevent it again in loop.
				update_post_meta( $notify_log_id, 'notification_sent', 1 );
			}
		}

		set_transient( 'sstk_scan_new_notifications_to_send_is_active', 1, 15 * MINUTE_IN_SECONDS );
	}


	/**
	 * Function for sending email notifications to the user
	 *
	 * @param int    $alert_id       alert_id.
	 * @param int    $notify_log_id  notify_log_id.
	 * @param string $user_email     user_email.
	 * @param string $user_login     user_login.
	 * @param string $notify_type    notify_type.
	 * @param string $notify_ref     notify_ref.
	 * @param string $notify_message notify_message.
	 * @param string $server_name    server_name.
	 * @param string $domain_name    domain_name.
	 * @param string $date           date.
	 * @param string $time           time.
	 * @param string $server_id      server_id.
	 * @param string $site_id        site_id.
	 * @param string $first_name     first_name.
	 * @param string $last_name      last_name.
	 * @param string $ipv4           ipv4.
	 * @param string $provider       provider.
	 */
	public function sstk_send_email_notifications_to_user( $alert_id, $notify_log_id, $user_email, $user_login, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider ) {
		// Get email notification settings.
		$email_subject = apply_filters( 'sstk_wpapp_email_notify_subject_raw', sstk_get_option( 'wordpress_app_email_notify_subject' ) );
		$email_body    = apply_filters( 'sstk_wpapp_email_notify_body_raw', sstk_get_option( 'wordpress_app_email_notify_body' ) );

		// Now construct a standard array of replaceable parameters.
		$tokens               = array();
		$tokens['USERNAME']   = $user_login;
		$tokens['TYPE']       = $notify_type;
		$tokens['REFERENCE']  = $notify_ref;
		$tokens['MESSAGE']    = $notify_message;
		$tokens['SERVERNAME'] = $server_name;
		$tokens['DOMAIN']     = $domain_name;
		$tokens['DATE']       = $date;
		$tokens['TIME']       = $time;
		$tokens['SERVERID']   = $server_id;
		$tokens['SITEID']     = $site_id;
		$tokens['FIRST_NAME'] = $first_name;
		$tokens['LAST_NAME']  = $last_name;
		$tokens['IPV4']       = $ipv4;
		$tokens['PROVIDER']   = $provider;

		// Replace tokens in the email subject.
		$email_subject = SSTK_WORDPRESS_APP()->replace_script_tokens( $email_subject, $tokens );
		// Let developers have their way again with the email subject, this time with tokens replaced.
		$email_subject = apply_filters( 'sstk_wpapp_email_notify_subject', $email_subject );

		// Replace tokens in the email body.
		$email_body = SSTK_WORDPRESS_APP()->replace_script_tokens( $email_body, $tokens );
		// Let developers have their way again with the email contents, this time with tokens replaced.
		$email_body = apply_filters( 'sstk_wpapp_email_notify_body', $email_body );

		// Send the email...
		if ( ! empty( $email_subject ) && ! empty( $email_body ) ) {
			$sent = wp_mail(
				$user_email,
				$email_subject,
				$email_body,
				array( 'Content-Type: text/html; charset=UTF-8' )
			);

			$sent_to = $user_email;

			if ( ! $sent ) {
				$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send email notification for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
			} else {
				$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Email notification sent successfully for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '1', $sent_to, null );
			}
		} else {
			$sent_to = $user_email;
			$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send email notification due to empty email subject or body field for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
		}
	}

	/**
	 * Function for sending Slack webhook notifications to the user
	 *
	 * @param int    $alert_id       alert_id.
	 * @param int    $notify_log_id  notify_log_id.
	 * @param string $user_slack     user_slack.
	 * @param string $user_login     user_login.
	 * @param string $notify_type    notify_type.
	 * @param string $notify_ref     notify_ref.
	 * @param string $notify_message notify_message.
	 * @param string $server_name    server_name.
	 * @param string $domain_name    domain_name.
	 * @param string $date           date.
	 * @param string $time           time.
	 * @param string $server_id      server_id.
	 * @param string $site_id        site_id.
	 * @param string $first_name     first_name.
	 * @param string $last_name      last_name.
	 * @param string $ipv4           ipv4.
	 * @param string $provider       provider.
	 */
	public function sstk_send_slack_webhook_notifications_to_user( $alert_id, $notify_log_id, $user_slack, $user_login, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider ) {
		// Get Slack webhook notification settings.
		$slack_message = apply_filters( 'sstk_wpapp_slack_notify_message_raw', sstk_get_option( 'wordpress_app_slack_notify_message' ) );

		// Now set a standard array of replaceable parameters.
		$slacktokens               = array();
		$slacktokens['USERNAME']   = $user_login;
		$slacktokens['TYPE']       = $notify_type;
		$slacktokens['REFERENCE']  = $notify_ref;
		$slacktokens['MESSAGE']    = $notify_message;
		$slacktokens['SERVERNAME'] = $server_name;
		$slacktokens['DOMAIN']     = $domain_name;
		$slacktokens['DATE']       = $date;
		$slacktokens['TIME']       = $time;
		$slacktokens['SERVERID']   = $server_id;
		$slacktokens['SITEID']     = $site_id;
		$slacktokens['FIRST_NAME'] = $first_name;
		$slacktokens['LAST_NAME']  = $last_name;
		$slacktokens['IPV4']       = $ipv4;
		$slacktokens['PROVIDER']   = $provider;

		// Replace tokens in slack message..
		$slack_message = SSTK_WORDPRESS_APP()->replace_script_tokens( $slack_message, $slacktokens );

		// Let developers have their way again with the Slack message, this time with tokens replaced.
		$slack_message = apply_filters( 'sstk_wpapp_slack_notify_message', $slack_message );

		// Send the message...
		if ( ! empty( $slack_message ) ) {

			$msg = array( 'text' => wp_strip_all_tags( $slack_message ) );

			$webhooks = explode( ',', $user_slack );

			if ( is_array( $webhooks ) ) {
				foreach ( $webhooks as $key => $value ) {
					// curl code for send message.
					$c = curl_init( $value );
					curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $c, CURLOPT_POST, true );
					curl_setopt( $c, CURLOPT_POSTFIELDS, array( 'payload' => json_encode( $msg ) ) );
					$sent_message = curl_exec( $c );
					curl_close( $c );

					$sent_to = $value;

					if ( $sent_message !== 'ok' ) {
						$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send slack notification for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
					} else {
						$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Slack notification sent successfully for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '1', $sent_to, null );
					}
				}
			}
		} else {
			$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send slack notification due to empty slack message field for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
		}
	}


	/**
	 * Function for sending zapier webhook notifications to the user
	 *
	 * @param int          $alert_id          alert_id.
	 * @param int          $notify_log_id     notify_log_id.
	 * @param array|string $user_zapier_hooks user_zapier_hooks.
	 * @param string       $user_login        user_login.
	 * @param string       $alert_user_id     alert_user_id.
	 * @param string       $alert_user_email  alert_user_email.
	 * @param string       $notify_type       notify_type.
	 * @param string       $notify_ref        notify_ref.
	 * @param string       $notify_message    notify_message.
	 * @param string       $server_name       server_name.
	 * @param string       $domain_name       domain_name.
	 * @param string       $date              date.
	 * @param string       $time              time.
	 * @param string       $server_id         server_id.
	 * @param string       $site_id           site_id.
	 * @param string       $first_name        first_name.
	 * @param string       $last_name         last_name.
	 * @param string       $ipv4              ipv4.
	 * @param string       $provider          provider.
	 */
	public function sstk_send_zapier_webhook_notifications_to_user( $alert_id, $notify_log_id, $user_zapier_hooks, $user_login, $alert_user_id, $alert_user_email, $notify_type, $notify_ref, $notify_message, $server_name, $domain_name, $date, $time, $server_id, $site_id, $first_name, $last_name, $ipv4, $provider ) {
		// Get Zapier webhook notification settings.
		$zapier_message = apply_filters( 'sstk_wpapp_zapier_notify_message_raw', sstk_get_option( 'wordpress_app_zapier_notify_message' ) );

		// Now set a standard array of replaceable parameters.
		$zapiertokens               = array();
		$zapiertokens['USERNAME']   = $user_login;
		$zapiertokens['USERID']     = $alert_user_id;
		$zapiertokens['USEREMAIL']  = $alert_user_email;
		$zapiertokens['TYPE']       = $notify_type;
		$zapiertokens['REFERENCE']  = $notify_ref;
		$zapiertokens['MESSAGE']    = $notify_message;
		$zapiertokens['SERVERNAME'] = $server_name;
		$zapiertokens['DOMAIN']     = $domain_name;
		$zapiertokens['DATE']       = $date;
		$zapiertokens['TIME']       = $time;
		$zapiertokens['SERVERID']   = $server_id;
		$zapiertokens['SITEID']     = $site_id;
		$zapiertokens['FIRST_NAME'] = $first_name;
		$zapiertokens['LAST_NAME']  = $last_name;
		$zapiertokens['IPV4']       = $ipv4;
		$zapiertokens['PROVIDER']   = $provider;

		// Replace tokens in Zapier message..
		$zapier_message = SSTK_WORDPRESS_APP()->replace_script_tokens( $zapier_message, $zapiertokens );

		// Let developers have their way again with the zapier message, this time with tokens replaced.
		$zapier_message = apply_filters( 'sstk_wpapp_zapier_notify_message', $zapier_message );

		// Send the message...
		if ( ! empty( $zapier_message ) ) {

			$msg               = array(
				'text'             => wp_strip_all_tags( $zapier_message ),
				'username'         => $user_login,
				'user_id'          => $alert_user_id,
				'user_email'       => $alert_user_email,
				'notify_type'      => $notify_type,
				'notify_reference' => $notify_ref,
				'notify_message'   => $notify_message,
				'server_name'      => $server_name,
				'domain_name'      => $domain_name,
				'date'             => $date,
				'time'             => $time,
				'server_id'        => $server_id,
				'site_id'          => $site_id,
				'first_name'       => $first_name,
				'last_name'        => $last_name,
				'ipv4'             => $ipv4,
				'provider'         => $provider,
			);
			$json_encoded_data = wp_json_encode( $msg );

			$webhooks = explode( ',', $user_zapier_hooks );

			if ( is_array( $webhooks ) ) {
				foreach ( $webhooks as $key => $value ) {
					// curl code for send message.
					$c = curl_init( $value );
					curl_setopt( $c, CURLOPT_HEADER, array( 'Content-Type: application/json', 'Content-Length: ' . strlen( $json_encoded_data ) ) );
					curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );
					curl_setopt( $c, CURLOPT_POST, 1 );
					curl_setopt( $c, CURLOPT_POST, true );
					curl_setopt( $c, CURLOPT_POSTFIELDS, $json_encoded_data );
					$sent_message = curl_exec( $c );
					$status_code  = curl_getinfo( $c, CURLINFO_HTTP_CODE );
					curl_close( $c );

					$sent_to = $value;

					if ( $status_code != '200' ) {
						$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send zapier notification for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
					} else {
						$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Zapier notification sent successfully for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '1', $sent_to, null );
					}
				}
			}
		} else {
			$this->add_user_notify_sent_log_entry( $alert_id, $notify_type, sprintf( __( 'Could not send zapier notification due to empty zapier message field for notification_id : %d', 'simple-stack' ), $notify_log_id ), $notify_ref, '0', $sent_to, null );
		}
	}
}
