<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Backup tab.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SSTK_WORDPRESS_TABS_GIT_CONTROL_SITE
 */
class SSTK_WORDPRESS_TABS_GIT_CONTROL_SITE extends SSTK_WORDPRESS_TABS {

	/**
	 * SSTK_WORDPRESS_TABS_BACKUP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_fields' ), 10, 2 );
		add_filter( "sstk_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action' ), 10, 3 );
		/* add_filter( 'sstk_is_ssh_successful', array( $this, 'was_ssh_successful' ), 10, 5 ); */

		// Command completed hook.
		add_action( "sstk_command_{$this->get_app_name()}_completed", array( $this, 'command_completed' ), 10, 2 );

		/**
		 * Hooks and filters to handle GitHub Webhooks
		 */
		add_action( 'rest_api_init', array( $this, 'register_github_webhook_endpoint' ) );  // Register the webhook endpoint.

		// Git clone to site action triggered from pending log.
		add_action( 'sstk_pending_log_git_clone_to_site', array( $this, 'sstk_pending_log_git_clone_to_site' ), 10, 3 );

		// Allow the git clone to site action to be triggered via an action hook.
		add_action( 'sstk_git_clone_to_site', array( $this, 'git_clone_to_site' ), 10, 3 );

		// Git check action triggered from pending log.
		add_action( 'sstk_pending_log_git_checkout', array( $this, 'sstk_pending_log_git_checkout' ), 10, 3 );

		// Allow the git checkout action to be triggered via an action hook.
		add_action( 'sstk_git_checkout', array( $this, 'git_checkout_branch' ), 10, 3 );

	}

	/**
	 * Called when a command completes.
	 *
	 * Action Hook: sstk_command_{$this->get_app_name()}_completed
	 *
	 * @param int    $id     The postID of the site cpt.
	 * @param string $name   The name of the command.
	 */
	public function command_completed( $id, $name ) {
		if ( get_post_type( $id ) !== 'sstk_app' ) {
			return;
		}

		// The name will have a format as such: command---domain---number.  For example: dry_run---cf1110.wpvix.com---905.
		// Lets tear it into pieces and put into an array.  The resulting array should look like this with exactly three elements.
		// [0] => dry_run.
		// [1] => cf1110.wpvix.com.
		// [2] => 911.
		$command_array = explode( '---', $name );

		// if the command is to initialize a git site, mark the site as initialized.
		if ( 'git_init' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			if ( true === $success ) {
				$this->set_git_status( $id, true );

				$msg = __( 'Git was initialized for this site.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			} else {
				$msg = __( 'An attempt to initialize git for this site failed.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}
		}

		// if the command is to clone a site without it being initialized, handle pending tasks (if any) and log the attempt.
		if ( 'git_clone_to_site' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			// Maybe this was triggered by a pending log task.  If so, grab the meta so we can update the task record later.
			$task_id = get_post_meta( $id, 'wpapp_pending_log_git_clone_to_site', true );

			if ( true === $success ) {

				// If this was triggered by a pending log task update the task as complete.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

				}

				// Log the attempt.
				$msg = __( 'Repo was cloned to this site without initializing git.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			} else {

				// If this was triggered by a pending log task update the task as failed.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

				}

				// Log the failure.
				$msg = __( 'An attempt to clone a repo to this site failed.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}

			// Delete the pending task post meta if it exists.
			if ( ! empty( $task_id ) ) {
				delete_post_meta( $id, 'wpapp_pending_log_git_clone_to_site' );
			}
		}

		// if the command is to checkout a site without it being initialized, handle pending tasks (if any) and log the attempt.
		if ( 'git_checkout' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			// Maybe this was triggered by a pending log task.  If so, grab the meta so we can update the task record later.
			$task_id = get_post_meta( $id, 'wpapp_pending_log_git_checkout', true );

			if ( true === $success ) {

				// Get the new branch.
				$new_branch = get_post_meta( $id, 'temp_git_branch', true );

				// Update it on the site record.
				$this->set_git_branch( $id, $new_branch );

				// If this was triggered by a pending log task update the task as complete.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'complete' );

				}

				// Log the attempt.
				$msg = __( 'Git checkout complete.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			} else {

				// If this was triggered by a pending log task update the task as failed.
				if ( ! empty( $task_id ) ) {

					// Grab our data array from pending tasks record...
					$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

					// Mark the task as complete.
					SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, 'failed' );

				}

				// Log the failure.
				$msg = __( 'An attempt to checkout a branch failed.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}

			// Delete the pending task post meta if it exists.
			if ( ! empty( $task_id ) ) {
				delete_post_meta( $id, 'wpapp_pending_log_git_checkout' );
			}

			// Remove temporary metas.
			delete_post_meta( $id, 'temp_git_branch' );
		}

		// If the command is to create a tag, lets log it and store the tag history.
		if ( 'git_tag' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			if ( true === $success ) {

				// Get the new tag.
				$new_tag      = get_post_meta( $id, 'temp_git_tag', true );
				$new_tag_desc = get_post_meta( $id, 'temp_git_tag_desc', true );

				// Add to tag history.
				$this->git_add_tag_history( $id, $new_tag, $new_tag_desc );

				/* Translators: %s is a git tag name. */
				$msg = sprintf( __( 'Tag/Version %s was created.', 'simple-stack' ), $new_tag );
				$this->git_add_to_site_log( $id, $msg );
			} else {
				$msg = __( 'An attempt to create a new git tag/version was not successful.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}

			// Remove temporary metas.
			delete_post_meta( $id, 'temp_git_tag' );
			delete_post_meta( $id, 'temp_git_tag_desc' );
		}

		// If the command is to pull an existing tag, lets log it and store the tag history.
		if ( 'git_fetch_tag' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			if ( true === $success ) {

				// Get tag we're pulling.
				$new_tag = get_post_meta( $id, 'temp_git_tag', true );

				// Add to tag history.
				$this->git_add_tag_history( $id, $new_tag, __( 'Tag pull request - tag was not present on local machine.' ) );

				/* Translators: %s is a git tag name. */
				$msg = sprintf( __( 'Tag/Version %s was pulled.', 'simple-stack' ), $new_tag );
				$this->git_add_to_site_log( $id, $msg );
			} else {
				$msg = __( 'An attempt to pull a new tag/version was not successful.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}

			// Remove temporary metas.
			delete_post_meta( $id, 'temp_git_tag' );
		}

		// If the command is to pull and apply an existing tag, lets log it and store the tag history.
		if ( 'git_fetch_apply_version_with_overwrite' === $command_array[0] ) {

			// Lets pull the logs.
			$logs = $this->get_app_command_logs( $id, $name );

			// Is the command successful?
			$success = (bool) $this->is_ssh_successful( $logs, 'git_control_site_command.txt' );

			if ( true === $success ) {

				// Get tag we're pulling.
				$new_tag = get_post_meta( $id, 'temp_git_tag', true );

				// Add to tag history.
				$this->git_add_tag_history( $id, $new_tag, __( 'Tag pull request - tag was not present on local machine.' ) );

				/* Translators: %s is a git tag name. */
				$msg = sprintf( __( 'Tag/Version %s was pulled and applied to the site files.', 'simple-stack' ), $new_tag );
				$this->git_add_to_site_log( $id, $msg );
			} else {
				$msg = __( 'An attempt to pull and apply a new tag/version was not successful.', 'simple-stack' );
				$this->git_add_to_site_log( $id, $msg );
			}

			// Remove temporary metas.
			delete_post_meta( $id, 'temp_git_tag' );
		}

		// remove the 'temporary' meta so that another attempt will run if necessary.
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "sstk_app_{$this->get_app_name()}_action_args" );

	}

	/**
	 * Returns a string that can be used as the unique name for this tab.
	 */
	public function get_tab_slug() {
		return 'git-site-control';
	}

	/**
	 * Returns a string that is the name of a view TEAM permission required to view this tab.
	 */
	public function get_view_tab_team_permission_slug() {
		return 'view_wpapp_site_git_control_tab';
	}

	/**
	 * Populates the tab name.
	 *
	 * @param array $tabs The default value.
	 * @param int   $id   The post ID of the server.
	 *
	 * @return array    $tabs The default value.
	 */
	public function get_tab( $tabs, $id ) {
		if ( $this->get_tab_security( $id ) ) {
			$tabs[ $this->get_tab_slug() ] = array(
				'label' => __( 'Git', 'simple-stack' ),
				'icon'  => 'fa-duotone fa-code-fork',
			);
		}
		return $tabs;
	}

	/**
	 * Checks whether or not the user can view the current tab.
	 *
	 * @param int $id The post ID of the site.
	 *
	 * @return boolean
	 */
	public function get_tab_security( $id ) {
		// If admin has an admin lock in place and the user is not admin they cannot view the tab or perform actions on them.
		if ( $this->get_admin_lock_status( $id ) && ! sstk_is_admin() ) {
			return false;
		}
		// If we got here then check team and other permissions.
		return ( true === $this->sstk_wpapp_site_user_can( $this->get_view_tab_team_permission_slug(), $id ) && true === $this->sstk_can_author_view_site_tab( $id, $this->get_tab_slug() ) );
	}

	/**
	 * Called when an action needs to be performed on the tab.
	 *
	 * @param mixed  $result The default value of the result.
	 * @param string $action The action to be performed.
	 * @param int    $id The post ID of the app.
	 *
	 * @return mixed    $result The default value of the result.
	 */
	public function tab_action( $result, $action, $id ) {

		/* Verify that the user is even allowed to view the app before proceeding to do anything else */
		if ( ! $this->sstk_user_can_view_wp_app( $id ) ) {
			/* Translators: %1 is the action; %2 is a file name */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %1$s in file %2$s', 'simple-stack' ), $action, __FILE__ ) );
		}

		/* Now verify that the user can perform actions on this screen, assuming that they can view the server */
		$valid_actions = array(
			'git-site-control-init-site',
			'git-site-control-remove',
			'git-site-control-sync',
			'git-site-control-switch-branch',
			'git-site-control-clone-only',
			'git-site-control-credentials-only',
			'git-site-control-remove-metas',
			'git-site-control-remove-git',
			'git-site-control-sync-fields-sync',
			'git-site-control-sync-fields-commit-and-push',
			'git-site-control-checkout-branch',
			'git-site-control-create-new-branch',
			'git-site-control-create-tag',
			'git-site-control-pull-tag',
			'git-site-control-fetch-and-apply-tag',
			'git-site-control-delete-all-tag-folders',
			'git-site-control-push-to-deploy-action',
			'git-site-control-push-to-deploy-reset-keys',
		);
		if ( in_array( $action, $valid_actions, true ) ) {
			if ( ! $this->get_tab_security( $id ) ) {
				/* translators: %1s is replaced with an internal action name; %2$s is replaced with the file name; %3$s is replaced with the post id being acted on. %4$s is the user id running this action. */
				return new \WP_Error( sprintf( __( 'You are not allowed to perform this action - permissions check has failed for action %1$s in file %2$s for post %3$s by user %4$s', 'simple-stack' ), $action, basename( __FILE__ ), $id, get_current_user_id() ) );
			}
		}

		if ( $this->get_tab_security( $id ) ) {
			switch ( $action ) {
				case 'git-site-control-init-site':
					$bash_action = 'git_init';
					$result      = $this->git_site_init( $bash_action, $id );
					break;
				case 'git-site-control-clone-only':
					$bash_action = 'git_clone_to_site';
					$result      = $this->git_clone_to_site( $bash_action, $id );
					break;
				case 'git-site-control-credentials-only':
					$bash_action = 'git_site_credentials';
					$result      = $this->git_site_init_credentials( $bash_action, $id );
					break;
				case 'git-site-control-remove-metas':
					$result = $this->remove_metas( $action, $id );
					break;
				case 'git-site-control-remove-git':
					$bash_action = 'git_domain_remove';
					$result      = $this->git_actions( $bash_action, $id );
					break;
				case 'git-site-control-sync-fields-sync':
					$bash_action = 'git_sync';
					$result      = $this->git_actions( $bash_action, $id );
					break;
				case 'git-site-control-sync-fields-commit-and-push':
					$bash_action = 'git_commit_and_push';
					$result      = $this->git_actions( $bash_action, $id );
					break;
				case 'git-site-control-checkout-branch':
					$bash_action = 'git_checkout';
					$result      = $this->git_checkout_branch( $bash_action, $id );
					break;
				case 'git-site-control-create-new-branch':
					$bash_action = 'git_new_branch';
					$result      = $this->git_actions( $bash_action, $id );
					break;
				case 'git-site-control-create-tag':
					$bash_action = 'git_tag';
					$result      = $this->git_create_tag( $bash_action, $id );
					break;
				case 'git-site-control-pull-tag':
					$bash_action = 'git_fetch_tag';
					$result      = $this->git_fetch_tag( $bash_action, $id );
					break;
				case 'git-site-control-fetch-and-apply-tag':
					$bash_action = 'git_fetch_apply_version_with_overwrite';
					$result      = $this->git_fetch_tag( $bash_action, $id );
					break;
				case 'git-site-control-delete-all-tag-folders':
					$bash_action = 'git_remove_all_version_folders';
					$result      = $this->git_actions( $bash_action, $id );
					break;
				case 'git-site-control-push-to-deploy-action':
					$result = $this->push_to_deploy_save_actions( $action, $id );
					break;
				case 'git-site-control-push-to-deploy-reset-keys':
					$result = $this->reset_push_to_deploy_keys( $action, $id );
					break;

			}
			// Many actions need to refresh the page so that new data can be loaded or so that the data entered into data entry fields cleared out.
			// But we don't want to force a refresh after long running commands. Otherwise the user will not be able to see the results of those commands in the 'terminal'.
			if ( in_array( $action, $valid_actions ) && ! in_array(
				$action,
				array(
					'git-site-control-init-site',
					'git-site-control-checkout-branch',
					'git-site-control-clone-only',
					'git-site-control-create-tag',
					'git-site-control-pull-tag',
					'git-site-control-fetch-and-apply-tag',
				),
				true
			) && ! is_wp_error( $result ) ) {
				$result = array( 'refresh' => 'yes' );
			}
		}
		return $result;
	}

	/**
	 * Gets the fields to be shown.
	 *
	 * @param array $fields fields.
	 * @param int   $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields( array $fields, $id ) {
		if ( ! $id ) {
			// id not found!
			return $fields;
		}

		// If user is not allowed to access the tab then don't paint the fields.
		if ( ! $this->get_tab_security( $id ) ) {
			return $fields;
		}

		// Bail if site is not enabled.
		if ( ! $this->is_site_enabled( $id ) ) {
			return array_merge( $fields, $this->get_disabled_header_field( $this->get_tab_slug() ) );
		}

		// Is git installed on the server where this site is located?
		$server_id = $this->get_server_id_by_app_id( $id );
		if ( $server_id ) {
			$git_server_status = $this->get_git_status( $server_id );
		} else {
			// server id not found.
			return $fields;
		}

		// Is git initialized on this site?
		$git_site_status = $this->get_git_status( $id );

		/**
		 * Show Init buttons or warning if git is not installed on server or site.
		 */
		if ( false === $git_server_status ) {
			$header_msg = __( 'Git is not installed on this server. To use git on this site, you must install git on the server first. Please see the GIT tab on the server screen.', 'simple-stack' );
			$fields[]   = array(
				'id'   => 'git-site-control-header',
				'name' => __( 'Git', 'simple-stack' ),
				'desc' => $header_msg,
				'type' => 'heading',
				'tab'  => $this->get_tab_slug(),
			);
		} else {
			// Git installed on server. If git is not installed on site, show site specific message.
			if ( false === $git_site_status ) {
				$header_msg  = __( 'Git is not activated for this site - activate it below.', 'simple-stack' );
				$header_msg .= '<br />';
				$header_msg .= __( 'Note: fields left blank will attempt to pull defaults from the server or from global settings.', 'simple-stack' );
				$fields[]    = array(
					'id'   => 'git-site-control-header',
					'name' => __( 'Git', 'simple-stack' ),
					'desc' => $header_msg,
					'type' => 'heading',
					'tab'  => $this->get_tab_slug(),
				);

				// Git is installed - get data needed to initialize git on this site.
				$fields = array_merge( $fields, $this->get_fields_for_init( $id ) );

				// Also, show certain sections if credentials have been installed.
				$credentials_status = (bool) get_post_meta( $id, 'wpapp_git_initial_credentials_only_valid', true );
				if ( true === $credentials_status ) {
					$fields = array_merge( $fields, $this->get_fields_for_git_push_to_deploy_handle_actions( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_push_to_deploy_keys( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_history( $id ) );
				}
			} else {
				// Git has been initialized for this site.
				$header_msg  = __( 'Git is active on this site.', 'simple-stack' );
				$header_msg .= '<br />';
				$header_msg .= $this->get_formatted_git_remote_repo_for_display( $id );
				$header_msg .= $this->get_formatted_git_branch_for_display( $id );

				$fields[] = array(
					'id'   => 'git-site-control-header',
					'name' => __( 'Git', 'simple-stack' ),
					'desc' => $header_msg,
					'type' => 'heading',
					'tab'  => $this->get_tab_slug(),
				);

				// Get additional fields for each section.
				if ( true === (bool) sstk_get_early_option( 'wordpress_app_git_enable_advanced' ) ) {
					$fields = array_merge( $fields, $this->get_fields_for_git_sync( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_checkout( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_new_branch( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_create_tag( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_fetch_tag( $id ) );
					$fields = array_merge( $fields, $this->get_fields_for_git_tag_list( $id ) );
				}
				$fields = array_merge( $fields, $this->get_fields_for_git_push_to_deploy_handle_actions( $id ) );
				$fields = array_merge( $fields, $this->get_fields_for_git_push_to_deploy_keys( $id ) );
				$fields = array_merge( $fields, $this->get_fields_for_git_history( $id ) );

			}
		}

		/**
		 * Option to reset metas and remove git from site.
		 */
		if ( true === $git_site_status ) {
			$fields = array_merge( $fields, $this->get_fields_for_git_misc( $id ) );
		}

		// Show settings.
		$fields = array_merge( $fields, $this->get_fields_for_git_display_settings( $id ) );

		return $fields;

	}

	/**
	 * Gets the fields to be shown in the site initialization
	 * section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_init( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$actions[] = array(
			'id'         => 'git-site-control-init-fields-remote-repo',
			'name'       => __( 'Remote Repo URL', 'simple-stack' ),
			'std'        => ! empty( $git_settings['git_remote_url'] ) ? $git_settings['git_remote_url'] : '',
			'desc'       => __( 'URL to your git repository on GitHub.', 'simple-stack' ),
			'columns'    => 12,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_remote_url',
			),
			'type'       => 'url',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-init-fields-email',
			'name'       => __( 'Email Address', 'simple-stack' ),
			'std'        => $git_settings['git_user_email'],
			'desc'       => __( 'Email address used by your git provider.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_user_email',
			),
			'type'       => 'email',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);
		$actions[] = array(
			'id'          => 'git-site-control-init-fields-display-name',
			'name'        => __( 'Display Name', 'simple-stack' ),
			'std'         => $git_settings['git_display_name'],
			'placeholder' => __( 'The display name used for your user account at your git provider.', 'simple-stack' ),
			'desc'        => __( 'eg: john smith', 'simple-stack' ),
			'columns'     => 6,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_display_name',
			),
			'type'        => 'text',
			'tab'         => $this->get_tab_slug(),
			'save_field'  => false,
		);
		$actions[] = array(
			'id'          => 'git-site-control-init-fields-user-name',
			'name'        => __( 'User Name', 'simple-stack' ),
			'std'         => $git_settings['git_user_name'],
			'placeholder' => __( 'The user name used for your account at your git provider.', 'simple-stack' ),
			'desc'        => __( 'eg: janesmith', 'simple-stack' ),
			'columns'     => 6,
			'attributes'  => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_user_name',
			),
			'type'        => 'text',
			'tab'         => $this->get_tab_slug(),
			'save_field'  => false,
		);
		$actions[] = array(
			'id'         => 'git-site-control-init-fields-token',
			'name'       => __( 'API Token', 'simple-stack' ),
			'std'        => $this->decrypt( $git_settings['git_token'] ),
			'desc'       => __( 'API Token for your git account at your git provider.', 'simple-stack' ),
			'tooltip'    => __( 'API tokens must provide read-write privileges for your repos. Generate one on GitHub under the settings area of your account.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_token',
			),
			'spellcheck' => 'false',
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		if ( true === (bool) sstk_get_early_option( 'wordpress_app_git_enable_advanced' ) ) {
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-branch',
				'name'       => __( 'Branch', 'simple-stack' ),
				'std'        => $git_settings['git_branch'],
				'desc'       => __( 'The default branch for your repos - eg: main or master.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_branch',
				),
				'type'       => 'text',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-git-ignore-link',
				'name'       => __( 'GitIgnore File Link', 'simple-stack' ),
				'std'        => $git_settings['git_ignore_url'],
				'desc'       => __( 'Link to a text file containing git ignore contents.', 'simple-stack' ),
				'tooltip'    => __( 'A raw gist is a good place to locate this file.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_ignore_url',
				),
				'type'       => 'url',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-pre-process-file-link',
				'name'       => __( 'Pre-Processing Script Link', 'simple-stack' ),
				'std'        => $git_settings['git_pre_processing_script_link'],
				'desc'       => __( 'Link to bash script that will execute before initializing a site with git.', 'simple-stack' ),
				'tooltip'    => __( 'A raw gist is a good place to locate this file as long as it does not have any private data.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_pre_processing_script_link',
				),
				'type'       => 'url',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-post-process-file-link',
				'name'       => __( 'Post-Processing Script Link', 'simple-stack' ),
				'std'        => $git_settings['git_post_processing_script_link'],
				'desc'       => __( 'Link to bash script that will execute after initializing a site with git.', 'simple-stack' ),
				'tooltip'    => __( 'A raw gist is a good place to locate this file as long as it does not have any private data.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_post_processing_script_link',
				),
				'type'       => 'url',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-git-ignore-folders',
				'name'       => __( 'Ignore Folders', 'simple-stack' ),
				'std'        => $git_settings['git_exclude_folders'],
				'desc'       => __( 'A comma-separated list of folders to add to git ignore.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_exclude_folders',
				),
				'type'       => 'text',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-git-ignore-files',
				'name'       => __( 'Ignore Files', 'simple-stack' ),
				'std'        => $git_settings['git_exclude_files'],
				'desc'       => __( 'A comma-separated list of files to add to git ignore.', 'simple-stack' ),
				'columns'    => 6,
				'attributes' => array(
					// the key of the field (the key goes in the request).
					'data-sstk-name' => 'git_exclude_files',
				),
				'type'       => 'text',
				'tab'        => $this->get_tab_slug(),
				'save_field' => false,
			);
		}

		if ( true === (bool) sstk_get_early_option( 'wordpress_app_git_enable_advanced' ) ) {
			$actions[] = array(
				'id'         => 'git-site-control-init-fields-init-action',
				'name'       => '',
				'std'        => __( 'Initialize Git on this site', 'simple-stack' ),
				'desc'       => __( 'This action is not reversible - please make a site backup if you\'re unsure about doing this!', 'simple-stack' ),
				'columns'    => 4,
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-sstk-action'              => 'git-site-control-init-site',
					// fields that contribute data for this action.
					'data-sstk-fields'              => wp_json_encode(
						array(
							'#git-site-control-init-fields-remote-repo',
							'#git-site-control-init-fields-email',
							'#git-site-control-init-fields-display-name',
							'#git-site-control-init-fields-user-name',
							'#git-site-control-init-fields-token',
							'#git-site-control-init-fields-branch',
							'#git-site-control-init-fields-git-ignore-link',
							'#git-site-control-init-fields-pre-process-file-link',
							'#git-site-control-init-fields-post-process-file-link',
							'#git-site-control-init-fields-git-ignore-folders',
							'#git-site-control-init-fields-git-ignore-files',
						)
					),
					'data-sstk-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to initialize this site with GIT?  Files from your remote repo will be merged into this site. This action is not reversible!', 'simple-stack' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to initialize site to use git...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
				),
				'type'       => 'button',
				'tab'        => $this->get_tab_slug(),
				'class'      => 'sstk_app_action',
				'save_field' => false,
			);

			$actions[] = array(
				'id'         => 'git-site-control-init-fields-clone-only',
				'name'       => '',
				'std'        => __( 'Clone Without Init', 'simple-stack' ),
				'desc'       => __( 'Clone files from remote repo without initializing git.', 'simple-stack' ),
				'tooltip'    => __( 'Clone files from remote repo without initializing git on the site. Files from the repo will be copied over the existing files on the site.', 'simple-stack' ),
				'columns'    => 4,
				'attributes' => array(
					// the _action that will be called in ajax.
					'data-sstk-action'              => 'git-site-control-clone-only',
					// fields that contribute data for this action.
					'data-sstk-fields'              => wp_json_encode(
						array(
							'#git-site-control-init-fields-remote-repo',
							'#git-site-control-init-fields-email',
							'#git-site-control-init-fields-display-name',
							'#git-site-control-init-fields-user-name',
							'#git-site-control-init-fields-token',
							'#git-site-control-init-fields-branch',
							'#git-site-control-init-fields-git-ignore-link',
							'#git-site-control-init-fields-pre-process-file-link',
							'#git-site-control-init-fields-post-process-file-link',
							'#git-site-control-init-fields-git-ignore-folders',
							'#git-site-control-init-fields-git-ignore-files',
						)
					),
					'data-sstk-id'                  => $id,
					// make sure we give the user a confirmation prompt.
					'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to copy files from your repo to this site?  This action is not reversible!', 'simple-stack' ),
					'data-show-log-console'         => true,
					// Initial console message.
					'data-initial-console-message'  => __( 'Preparing to copy sites from your remote repo to this site...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
				),
				'type'       => 'button',
				'tab'        => $this->get_tab_slug(),
				'class'      => 'sstk_app_action',
				'save_field' => false,
			);
		}

		$actions[] = array(
			'id'         => 'git-site-control-init-fields-credentials-only',
			'name'       => '',
			'std'        => __( 'Save Credentials', 'simple-stack' ),
			'desc'       => __( 'Save credentials for site without fully initializing git.', 'simple-stack' ),
			'tooltip'    => __( 'Save and setup the server to connect to the repo using the provided credentials. You can then clone the site later if you wish.', 'simple-stack' ),
			'columns'    => 4,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-credentials-only',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-init-fields-remote-repo',
						'#git-site-control-init-fields-email',
						'#git-site-control-init-fields-display-name',
						'#git-site-control-init-fields-user-name',
						'#git-site-control-init-fields-token',
						'#git-site-control-init-fields-branch',
						/*
						'#git-site-control-init-fields-git-ignore-link',
						'#git-site-control-init-fields-pre-process-file-link',
						'#git-site-control-init-fields-post-process-file-link',
						'#git-site-control-init-fields-git-ignore-folders',
						'#git-site-control-init-fields-git-ignore-files',
						*/
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to setup git credentials for this site?  This action is not reversible!', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;
	}

	/**
	 * Gets the fields to be shown in the git sync
	 * section of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_sync( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$header_msg  = __( 'Sync your current branch with your remote repo. This can change the files on your site.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= $this->get_formatted_git_branch_for_display( $id );
		$actions[]   = array(
			'id'   => 'git-site-control-sync-fields-header',
			'name' => __( 'Git Sync', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-sync-fields-commit-message',
			'name'       => __( 'Commit Message', 'simple-stack' ),
			'tooltip'    => __( 'Enter a commit message to be used if changes need to be committed first before pulling and pushing from the remote repo.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_commit_msg',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-sync-fields-sync-action',
			'name'       => __( 'Sync', 'simple-stack' ),
			'tooltip'    => __( 'This action will 1. Commit local changes. 2. Pull and merge from remote repo and 3. Push changes back to remote repo.', 'simple-stack' ),
			'std'        => __( 'Sync With Remote Repo', 'simple-stack' ),
			'columns'    => 3,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-sync-fields-sync',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-sync-fields-commit-message',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to sync your current site files with your remote repo?  This action is not reversible!', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-sync-fields-commit-action',
			'name'       => __( 'Commit', 'simple-stack' ),
			'tooltip'    => __( 'This action will 1. Commit local changes. & 2. Push changes back to remote repo.', 'simple-stack' ),
			'std'        => __( 'Commit & Push', 'simple-stack' ),
			'columns'    => 3,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-sync-fields-commit-and-push',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-sync-fields-commit-message',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to commit your current site changes and push them to your remote repo?  This action is not reversible!', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;
	}

	/**
	 * Gets the fields to be shown in the git checkout
	 * sections of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_checkout( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$header_msg  = __( 'Checkout a different branch or tag (version). This can change the files on your site.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= $this->get_formatted_git_branch_for_display( $id );
		$actions[]   = array(
			'id'   => 'git-site-control-checkout-fields-header',
			'name' => __( 'Git Checkout Branch or tag (Version)', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-checkout-branch',
			'name'       => __( 'Branch or Tag', 'simple-stack' ),
			'desc'       => __( 'Enter the branch or tag to be checked out, eg: dev or main.', 'simple-stack' ),
			'tooltip'    => __( 'If you choose a TAG to checkout, chances are that your HEAD will be left in a DETACHED state. If you do not know what this means, do not checkout a tag or version.', 'simple-stack' ),
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_branch',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-checkout-fields-action',
			'name'       => '',
			'std'        => __( 'Checkout', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-checkout-branch',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-checkout-branch',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to checkout this branch or tag? Changes from the remote repo will be merged with the local site files. If you chose a TAG to checkout, chances are that your HEAD will be left in a DETACHED state. If you do not know what this means, do not check out a tag or version.', 'simple-stack' ),
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to checkout a branch or tag...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields to be shown in the new branch & checkout
	 * sections of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_new_branch( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$header_msg  = __( 'Create new branch and checkout.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= $this->get_formatted_git_branch_for_display( $id );
		$actions[]   = array(
			'id'   => 'git-site-control-checkout-fields-header',
			'name' => __( 'Git Create Branch', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-branch-source-branch',
			'name'       => __( 'Source Branch', 'simple-stack' ),
			'desc'       => __( 'Enter the branch we\'ll be using as the source of the new branch.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_branch',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-branch-new-branch',
			'name'       => __( 'New Branch', 'simple-stack' ),
			'desc'       => __( 'Enter the name of the branch to create.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_new_branch',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-branch-fields-action',
			'name'       => '',
			'std'        => __( 'Create & Checkout New Branch', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-create-new-branch',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-create-branch-source-branch',
						'#git-site-control-create-branch-new-branch',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to checkout this branch? Changes from the remote repo will be merged with the local site files.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields to be shown in the create tag
	 * sections of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_create_tag( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$header_msg  = __( 'Create new tag or version.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= __( 'A new version folder will be created for this tag.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= $this->get_formatted_git_branch_for_display( $id );
		$actions[]   = array(
			'id'   => 'git-site-control-create-tag-header',
			'name' => __( 'Create New Tag (Version)', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-tag-name',
			'name'       => __( 'New Tag', 'simple-stack' ),
			'desc'       => __( 'Enter the name for the new tag eg: v1.1.2.  No spaces or special chars.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_tag',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-tag-desc',
			'name'       => __( 'Description', 'simple-stack' ),
			'desc'       => __( 'Enter a few words about this tag.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_tag_desc',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-create-tag-action',
			'name'       => '',
			'std'        => __( 'Create New Tag', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-create-tag',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-create-tag-name',
						'#git-site-control-create-tag-desc',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to create a new tag or version?', 'simple-stack' ),
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to create a new tag...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields to be shown in the pull tag
	 * sections of the tab.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_fetch_tag( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		// Left column header.
		$header_msg  = __( 'Fetch Tag and create a version folder without overwriting existing site files.', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= $this->get_formatted_git_branch_for_display( $id );
		$actions[]   = array(
			'id'      => 'git-site-control-pull-tag-header',
			'name'    => __( 'Fetch Existing Tag (Version) From Repo', 'simple-stack' ),
			'desc'    => $header_msg,
			'columns' => 6,
			'type'    => 'heading',
			'tab'     => $this->get_tab_slug(),
		);

		// Right column header.
		$header_msg  = __( 'Fetch Tag and apply to site - site files WILL be changed!', 'simple-stack' );
		$header_msg .= '<br />';
		$header_msg .= __( 'Note that this tag will write files to your CURRENT branch. So if you are using an older tag or a tag from a different branch you will want to be very very careful!', 'simple-stack' );
		$header_msg .= '<br />';
		$actions[]   = array(
			'id'      => 'git-site-control-fetch-and-apply-tag-header',
			'name'    => __( 'Fetch And Apply Tag (Version)', 'simple-stack' ),
			'desc'    => $header_msg,
			'columns' => 6,
			'type'    => 'heading',
			'tab'     => $this->get_tab_slug(),
		);

		// field in left column.
		$actions[] = array(
			'id'         => 'git-site-control-pull-tag-name',
			'name'       => __( 'Tag To Fetch', 'simple-stack' ),
			'desc'       => __( 'Enter the name for the tag to fetch eg: v1.1.2.  No spaces or special chars.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_tag',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		// field in right column.
		$actions[] = array(
			'id'         => 'git-site-control-fetch-and-apply-tag-name',
			'name'       => __( 'Tag To Fetch', 'simple-stack' ),
			'desc'       => __( 'Enter the name for the tag to fetch eg: v1.1.2.  No spaces or special chars.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_tag',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		// Button in left column.
		$actions[] = array(
			'id'         => 'git-site-control-pull-tag-action',
			'name'       => '',
			'std'        => __( 'Fetch Tag / Version', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-pull-tag',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-pull-tag-name',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to fetch this tag or version from the remote repo?', 'simple-stack' ),
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to pull tag/version from remote repo...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		// Button in right column.
		$actions[] = array(
			'id'         => 'git-site-control-fetch-and-apply-tag-action',
			'name'       => '',
			'std'        => __( 'Fetch & Apply', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-fetch-and-apply-tag',
				// fields that contribute data for this action.
				'data-sstk-fields'              => wp_json_encode(
					array(
						'#git-site-control-fetch-and-apply-tag-name',
					)
				),
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to apply this tag / version to your files? Your site files will be overwritten!', 'simple-stack' ),
				'data-show-log-console'         => true,
				// Initial console message.
				'data-initial-console-message'  => __( 'Preparing to pull tag/version from remote repo and overwrite site files...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the backup has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the backup is complete the entire log can be viewed in the COMMAND LOG screen.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}


	/**
	 * Gets the fields that display in the misc section.
	 *
	 * @param int $id id.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_misc( $id ) {

		$fields[] = array(
			'id'   => 'git-site-control-misc-header',
			'name' => __( 'Misc', 'simple-stack' ),
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);
		$fields[] = array(
			'id'         => 'git-site-control-remove-git-action',
			'name'       => '',
			'std'        => __( 'Remove Git', 'simple-stack' ),
			'desc'       => __( 'Remove git from this site.', 'simple-stack' ),
			'tooltip'    => __( 'This will attempt to remove git files from the site. Simple Stack will no longer track this site as being git enabled.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-remove-git',
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to remove git from this site? This action is not reversible!', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);
		$fields[] = array(
			'id'         => 'git-site-control-reset-metas-action',
			'name'       => '',
			'std'        => __( 'Reset metas', 'simple-stack' ),
			'desc'       => __( 'Remove git related metas from this site.', 'simple-stack' ),
			'tooltip'    => __( 'This will NOT remove git from the site on the server!', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-remove-metas',
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to reset the git related metas for this site? This action is not reversible!', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		$fields[] = array(
			'id'         => 'git-site-control-delete-all-tag-folders-action',
			'name'       => '',
			'std'        => __( 'Delete All Tag Folders', 'simple-stack' ),
			'desc'       => __( 'Remove all Tag folders', 'simple-stack' ),
			'tooltip'    => __( 'Whenever you create or pull a tag, we create a new folder for it. This will delete all those folders.', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-delete-all-tag-folders',
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to delete all the tag /version folders for this site? This action is not reversible unless you repull your tags from the remote repo.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $fields;

	}

	/**
	 * Gets the fields required for how to handle push-to-deploy.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_push_to_deploy_handle_actions( $id ) {

		$header_msg = __( 'What should we do when we get a webhook notice from your git provider (GitHub)?', 'simple-stack' );

		$actions[] = array(
			'id'   => 'git-site-control-push-to-deploy-actions-header',
			'name' => __( 'Push-To-Deploy Actions', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-push-to-deploy-actions-branches',
			'name'       => __( 'Which branches should trigger a deploy?', 'simple-stack' ),
			'desc'       => __( 'Enter branches separated by commas.', 'simple-stack' ),
			'std'        => get_post_meta( $id, 'sstk_app_git_push_to_deploy_action_branches', true ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_push_to_deploy_action_branches',
			),
			'type'       => 'text',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-push-to-deploy-actions-types',
			'name'       => __( 'What should we do?', 'simple-stack' ),
			'desc'       => __( 'What should we do when our push-to-deploy webhook is triggered for this site?', 'simple-stack' ),
			'std'        => get_post_meta( $id, 'sstk_app_git_push_to_deploy_action_type', true ),
			'columns'    => 6,
			'attributes' => array(
				// the key of the field (the key goes in the request).
				'data-sstk-name' => 'git_push_to_deploy_action_type',
			),
			'type'       => 'select',
			'options'    => array(
				'fetch'    => __( 'Fetch (Copy Changes To Site - will not pull or merge if this site has an integrated git repo)', 'simple-stack' ),
				'checkout' => __( 'Checkout (Will checkout files to the local repo for the site and sync the site files to it)', 'simple-stack' ),
			),
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-push-to-deploy-action',
			'name'       => '',
			'std'        => __( 'Save Actions', 'simple-stack' ),
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action' => 'git-site-control-push-to-deploy-action',
				// fields that contribute data for this action.
				'data-sstk-fields' => wp_json_encode(
					array(
						'#git-site-control-push-to-deploy-actions-branches',
						'#git-site-control-push-to-deploy-actions-types',
					)
				),
				'data-sstk-id'     => $id,
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields required to integrate push-to-deploy (webhook and webhook secret.)
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_push_to_deploy_keys( $id ) {

		$header_msg = __( 'Add these values in the webooks area of your git provider (GitHub).', 'simple-stack' );

		$return  = '<div class="sstk_git_push_to_deploy_data">';
		$return .= '<div class="sstk_git_push_to_deploy_inner_wrap">';

		$return     .= '<div class="sstk_git_push_to_deploy_data_label_item">';
			$return .= __( 'Webhook URL:', 'simple-stack' );
		$return     .= '</div>';

		$return     .= '<div class="sstk_git_push_to_deploy_data_value_item">';
			$return .= sstk_wrap_clipboard_copy( get_post_meta( $id, 'sstk_app_git_push_to_deploy_webhook_url', true ), false, false );
		$return     .= '</div>';

		$return     .= '<div class="sstk_git_push_to_deploy_data_label_item">';
			$return .= __( 'Webhook Secret:', 'simple-stack' );
		$return     .= '</div>';

		$return     .= '<div class="sstk_git_push_to_deploy_data_value_item">';
			$return .= sstk_wrap_clipboard_copy( $this->decrypt( get_post_meta( $id, 'sstk_app_git_push_to_deploy_secret_key', true ) ), false, false );
		$return     .= '</div>';

		$return .= '</div>';
		$return .= '</div>';

		$actions[] = array(
			'id'   => 'git-site-control-push-to-deploy-header',
			'name' => __( 'Push-To-Deploy Keys', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-push-to-deploy-keys',
			'name'       => '',
			'std'        => $return,
			'type'       => 'custom_html',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		$actions[] = array(
			'id'         => 'git-site-control-push-to-deploy-reset-keys-action',
			'name'       => '',
			'std'        => __( 'Reset Keys', 'simple-stack' ),
			'columns'    => 6,
			'attributes' => array(
				// the _action that will be called in ajax.
				'data-sstk-action'              => 'git-site-control-push-to-deploy-reset-keys',
				'data-sstk-id'                  => $id,
				// make sure we give the user a confirmation prompt.
				'data-sstk-confirmation-prompt' => __( 'Are you sure you would like to reset the URL & Secret key for your webhook? You will have to update this everywhere it\'s used.', 'simple-stack' ),
			),
			'type'       => 'button',
			'tab'        => $this->get_tab_slug(),
			'class'      => 'sstk_app_action',
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Gets the fields that display the current settings.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_display_settings( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		$header_msg = __( 'These were the values used when this site was linked with your git repo.', 'simple-stack' );

		$return  = '<div class="sstk_git_initial_settings_data">';
		$return .= '<div class="sstk_git_initial_settings_data_inner_wrap">';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Remote Repo:', 'simple-stack' );
			$return     .= '</div>';

			$return .= '<div class="sstk_git_initial_settings_data_value_item">';
				// phpcs:disable
				if ( ! empty( $git_settings['git_remote_url'] ) ) {
			$return .= esc_html( $git_settings['git_remote_url'] );
				}
				// phpcs:enable
			$return .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Initial Branch:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_branch'] );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'User Display Name:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= $git_settings['git_display_name'];
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'User Name:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_user_name'] );
			$return     .= '</div>';

		if ( true === (bool) sstk_get_early_option( 'wordpress_app_git_enable_advanced' ) ) {

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Pre-Processing Script Link:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_pre_processing_script_link'] );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Post-Processing Script Link:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_post_processing_script_link'] );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Ignore Folders:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_exclude_folders'] );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Ignore Files:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_exclude_files'] );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_label_item">';
				$return .= __( 'Git Ignore Link:', 'simple-stack' );
			$return     .= '</div>';

			$return     .= '<div class="sstk_git_initial_settings_data_value_item">';
				$return .= esc_html( $git_settings['git_ignore_url'] );
			$return     .= '</div>';
		}

		$return .= '</div>';
		$return .= '</div>';

		$actions[] = array(
			'id'   => 'git-site-control-view-settings-header',
			'name' => __( 'Git Initial Settings', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-view-settings',
			'name'       => '',
			'std'        => $return,
			'type'       => 'custom_html',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Get fields that displays the list of current tags we know about.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_tag_list( $id ) {

		$header_msg = __( 'These are the tags/versions that we are aware of. If you have created tags on the remote repo but not fetched them on this screen we will not be aware of them.', 'simple-stack' );

		$return  = '<div class="sstk_git_tag_list">';
		$return .= '<div class="sstk_git_tag_list_inner_wrap">';

		$tags = $this->get_git_tag_history( $id );

		foreach ( array_reverse( $tags ) as $tag => $tag_array ) {

			$return .= '<div class="sstk_git_tag_value">';
			$return .= $tag;
			$return .= '</div>';

			$return .= '<div class="sstk_git_tag_label_desc">';
			$return .= ! empty( $tag_array['desc'] ) ? $tag_array['desc'] : __( 'No description available', 'simple-stack' );
			$return .= '</div>';

		}
		$return .= '</div>';
		$return .= '</div>';

		$actions[] = array(
			'id'   => 'git-site-control-list-tags',
			'name' => __( 'Current Tags', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-view-tags',
			'name'       => '',
			'std'        => $return,
			'type'       => 'custom_html',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Get fields that displays the git history log.
	 *
	 * @param int $id The post id of the site we're working with.
	 *
	 * @return array Array of actions, complying with the structure necessary by metabox.io fields.
	 */
	public function get_fields_for_git_history( $id ) {

		$header_msg = __( 'Git History Log. Only the latest 25 items are shown.', 'simple-stack' );

		$return  = '<div class="sstk_git_logs_list">';
		$return .= '<div class="sstk_git_logs_list_inner_wrap">';

		$logs = $this->get_git_log_history( $id );

		foreach ( array_reverse( $logs ) as $log => $log_array ) {

			$return .= '<div class="sstk_git_log_label">';
			$return .= $log_array['reporting_time_human_utc'];
			$return .= '</div>';

			$return .= '<div class="sstk_git_log_value">';
			$return .= ! empty( $log_array['msg'] ) ? $log_array['msg'] : __( 'Empty log entry', 'simple-stack' );
			$return .= '</div>';

		}
		$return .= '</div>';
		$return .= '</div>';

		$actions[] = array(
			'id'   => 'git-site-control-logs',
			'name' => __( 'History', 'simple-stack' ),
			'desc' => $header_msg,
			'type' => 'heading',
			'tab'  => $this->get_tab_slug(),
		);

		$actions[] = array(
			'id'         => 'git-site-control-view-logs',
			'name'       => '',
			'std'        => $return,
			'type'       => 'custom_html',
			'tab'        => $this->get_tab_slug(),
			'save_field' => false,
		);

		return $actions;

	}

	/**
	 * Return an array of field names that will be used
	 * as the keys into an array to store corresponding
	 * values.
	 * These keys/field names match the ones expected
	 * by the GIT bash scripts.
	 *
	 * Note: This same function is duplicated in the
	 * server git tab. Changes here might be needed
	 * there.
	 */
	public function get_git_default_field_names() {
		$field_names = array(
			'git_user_email',
			'git_display_name',
			'git_user_name',
			'git_token',
			'git_branch',
			'git_ignore_url',
			'git_pre_processing_script_link',
			'git_post_processing_script_link',
			'git_exclude_folders',
			'git_exclude_files',
		);
		return $field_names;
	}

	/**
	 * Read the git settings from the database and return.
	 *
	 * @param int $id post id of site we're working with.
	 *
	 * @return array
	 */
	public function get_git_settings( $id ) {

		$settings = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_settings', true ) );

		if ( empty( $settings ) ) {
			$fields = $this->get_git_default_field_names();
			// Loop through field names to create key-value array since that format is what's usually stored in the database.
			$settings = array();
			foreach ( $fields as $fld ) {
				$settings[ $fld ] = '';
			}
		}

		return $settings;

	}

	/**
	 * Return an array of global git defaults
	 */
	public function get_global_git_defaults() {

		$defaults = array(
			'git_user_email'                  => sstk_get_option( 'wordpress_app_git_email_address' ),
			'git_display_name'                => sstk_get_option( 'wordpress_app_git_display_name' ),
			'git_user_name'                   => sstk_get_option( 'wordpress_app_git_user_name' ),
			'git_token'                       => sstk_get_option( 'wordpress_app_git_token' ),
			'git_branch'                      => sstk_get_option( 'wordpress_app_git_branch' ),
			'git_ignore_url'                  => sstk_get_option( 'wordpress_app_git_ignore_link' ),
			'git_pre_processing_script_link'  => sstk_get_option( 'wordpress_app_git_pre_processing_script_link' ),
			'git_post_processing_script_link' => sstk_get_option( 'wordpress_app_git_post_processing_script_link' ),
			'git_exclude_folders'             => sstk_get_option( 'wordpress_app_git_ignore_folders' ),
			'git_exclude_files'               => sstk_get_option( 'wordpress_app_git_ignore_files' ),
		);

		return $defaults;

	}

	/**
	 * Return an array of git default values defined on the server.
	 *
	 * @param int $id The post if of the SITE we're working with.
	 *
	 * @return array.
	 */
	public function get_server_git_defaults( $id ) {

		$defaults = array();

		$server_id = $this->get_server_id_by_app_id( $id );
		if ( $server_id ) {
			$defaults = sstk_maybe_unserialize( get_post_meta( $server_id, 'sstk_wpapp_git_defaults', true ) );
		}

		if ( empty( $defaults ) ) {
			$fields = $this->get_git_default_field_names();
			// Loop through field names to create key-value array since that format is what's usually stored in the database.
			$defaults = array();
			foreach ( $fields as $fld ) {
				$defaults[ $fld ] = '';
			}
		}

		return $defaults;

	}

	/**
	 * Take a git settings array and merge it with
	 * settings from the server or global settings.
	 *
	 * See the method get_git_default_field_names() earlier in this class to see the list of expected field names.
	 * The arrays are expected to be key-value pairs with the field names as the key.
	 *
	 * @param int   $id post id of site we're working with.
	 * @param array $settings Key-value array of settings id => value.
	 *
	 * @return array
	 */
	public function merge_git_settings( $id, $settings ) {

		// Get defaults from server settings.
		$server_git_defaults = $this->get_server_git_defaults( $id );

		// Get defaults from global settings.
		$global_git_defaults = $this->get_global_git_defaults();

		// Merge all the defaults.
		// Can't use wp_parse_args since we don't want blanks overwriting other default values.
		$defaults = array();
		$fields   = $this->get_git_default_field_names();
		foreach ( $fields as $field_name ) {
			$defaults[ $field_name ] = ! empty( $settings[ $field_name ] ) ? $settings[ $field_name ] : ( ! empty( $server_git_defaults[ $field_name ] ) ? $server_git_defaults[ $field_name ] : $global_git_defaults[ $field_name ] );
		}

		return $defaults;
	}

	/**
	 * Initialize a site with git.
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_init').
	 * @param int    $id id.
	 */
	public function git_site_init( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have a REPO name before doing anything else.
		if ( empty( $args['git_remote_url'] ) ) {
			/* Translators: %s is the label name for a field that should not be blank. */
			return new \WP_Error( __( 'The repo name/url should not be blank.', 'simple-stack' ) );
		}

		// Merge our incoming git settings with global and server defaults.
		$git_settings = $this->merge_git_settings( $id, $args );

		// Add back in the repo url to the $git_settings array - it would not have been handled by our call to merge_git_settings() since it's not a value that is set as a default anywhere.
		$git_settings['git_remote_url'] = $args['git_remote_url'];

		// Replace the $args array with our git_settings array (because all our other scripts use $args when passing an array to bash scripts - don't want to surprise a dev later with an unexpected var name).
		$args = $git_settings;

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Save the fields into the database even if if some of them are blank or incorrect.  This allows the user to change them without retyping everything!
		// Note that we will create a new array just for saving so that we can encrypt the token without affecting later processing that needs an unencrypted token.
		$git_settings_for_saving              = $git_settings;
		$git_settings_for_saving['git_token'] = $this->encrypt( $git_settings_for_saving['git_token'] );
		update_post_meta( $id, 'sstk_app_git_settings', $git_settings_for_saving );
		$this->set_git_branch( $id, $git_settings['git_branch'] );

		// Create a push-to-deploy url and save it. It doesn't matter if the git init is succesful, this is a reusable value.
		$this->generate_push_to_deploy_keys( $id );

		// Certain settings should not be blank.  Loop through those and error out if they're blank.
		$non_blank_fields = array(
			'git_user_email'   => __( 'Email Address', 'simple-stack' ),
			'git_display_name' => __( 'Display Name', 'simple-stack' ),
			'git_user_name'    => __( 'User Name', 'simple-stack' ),
			'git_token'        => __( 'API Token', 'simple-stack' ),
			'git_branch'       => __( 'Branch', 'simple-stack' ),
		);
		foreach ( $non_blank_fields as $field => $display_name ) {
			if ( empty( $git_settings[ $field ] ) ) {
				/* Translators: %s is the label name for a field that should not be blank. */
				return new \WP_Error( sprintf( __( 'The field %s should not be blank. We could not find a value in the server or global defaults.', 'simple-stack' ), $display_name ) );
			}
		}

		// At this point, the $git_settings array keys should match those expected by the bash scripts.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Configure the run cmd.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site_command.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#4-provisioning-scripts
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Clone files from repo to site without initializing the site with git.
	 *
	 * Action Hook: sstk_git_clone_to_site (optional)
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_clone_to_site').
	 * @param int    $id id.
	 * @param array  $in_args Arguments that can be used instead of the data in $_POST['params'].
	 */
	public function git_clone_to_site( $action, $id, $in_args = array() ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		if ( ! empty( $in_args ) ) {
			// Incoming args has data so use that instead of pulling from $_POST.
			$args = $in_args;
		} else {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		}

		// Make sure we have a REPO name before doing anything else.
		if ( empty( $args['git_remote_url'] ) ) {
			/* Translators: %s is the label name for a field that should not be blank. */
			return new \WP_Error( __( 'The repo name/url should not be blank.', 'simple-stack' ) );
		}

		// Merge our incoming git settings with global and server defaults.
		$git_settings = $this->merge_git_settings( $id, $args );

		// Add back in the repo url to the $git_settings array - it would not have been handled by our call to merge_git_settings() since it's not a value that is set as a default anywhere.
		$git_settings['git_remote_url'] = $args['git_remote_url'];

		// Replace the $args array with our git_settings array (because all our other scripts use $args when passing an array to bash scripts - don't want to surprise a dev later with an unexpected var name).
		$args = $git_settings;

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Save the fields into the database even if if some of them are blank or incorrect.  This allows the user to change them without retyping everything!
		// Note that we will create a new array just for saving so that we can encrypt the token without affecting later processing that needs an unencrypted token.
		$git_settings_for_saving              = $git_settings;
		$git_settings_for_saving['git_token'] = $this->encrypt( $git_settings_for_saving['git_token'] );
		update_post_meta( $id, 'sstk_app_git_settings', $git_settings_for_saving );

		// Certain settings should not be blank.  Loop through those and error out if they're blank.
		$non_blank_fields = array(
			'git_user_email'   => __( 'Email Address', 'simple-stack' ),
			'git_display_name' => __( 'Display Name', 'simple-stack' ),
			'git_user_name'    => __( 'User Name', 'simple-stack' ),
			'git_token'        => __( 'API Token', 'simple-stack' ),
			'git_branch'       => __( 'Branch', 'simple-stack' ),
		);
		foreach ( $non_blank_fields as $field => $display_name ) {
			if ( empty( $git_settings[ $field ] ) ) {
				/* Translators: %s is the label name for a field that should not be blank. */
				return new \WP_Error( sprintf( __( 'The field %s should not be blank. We could not find a value in the server or global defaults.', 'simple-stack' ), $display_name ) );
			}
		}

		// At this point, the $git_settings array keys should match those expected by the bash scripts.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Configure the run cmd.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site_command.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#4-provisioning-scripts
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Checkout a branch or tag.
	 *
	 * Action Hook: sstk_git_checkout (optional)
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_clone_to_site').
	 * @param int    $id id.
	 * @param array  $in_args Arguments that can be used instead of the data in $_POST['params'].
	 */
	public function git_checkout_branch( $action, $id, $in_args = array() ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		if ( ! empty( $in_args ) ) {
			// Incoming args has data so use that instead of pulling from $_POST.
			$args = $in_args;
		} else {
			$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );
		}

		// Make sure we have a branch name before doing anything else.
		if ( empty( $args['git_branch'] ) ) {
			return new \WP_Error( __( 'The branch or tag should not be blank.', 'simple-stack' ) );
		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// At this point, we have everything we need so initialize some vars we'll use later.
		$run_cmd = '';

		// Store the branch into a temporary meta so we can use it after the command completes.
		update_post_meta( $id, 'temp_git_branch', $original_args['git_branch'] );

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Configure the run cmd.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site_command.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#4-provisioning-scripts
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Create a new tag / version
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_tag').
	 * @param int    $id id.
	 */
	public function git_create_tag( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have a tag name before doing anything else.
		if ( empty( $args['git_tag'] ) ) {
			return new \WP_Error( __( 'The tag name should not be blank.', 'simple-stack' ) );
		}

		// Make sure the sanitized tag name and the provided tag name are the same.
		$sanitized_tag = $this->sanitize_tag( $args['git_tag'] );
		if ( $sanitized_tag !== $args['git_tag'] ) {
			/* Translators: %s is the suggested tag name. */
			return new \WP_Error( sprintf( __( 'The tag provided is invalid. Maybe it should be %s', 'simple-stack' ), $sanitized_tag ) );
		}

		// Make sure we have a description.
		if ( empty( $args['git_tag_desc'] ) ) {
			return new \WP_Error( __( 'The tag description should not be blank.', 'simple-stack' ) );
		}

		// Sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Store the tag into a temporary meta so we can use it after the command completes.
		update_post_meta( $id, 'temp_git_tag', $original_args['git_tag'] );
		update_post_meta( $id, 'temp_git_tag_desc', $original_args['git_tag_desc'] );

		// At this point, we have everything we need so initialize some vars we'll use later.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Configure the run cmd.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site_command.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#4-provisioning-scripts
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Pull a new tag / version from the remote repo.
	 *
	 * This function handles TWO different actions:
	 * - git_fetch_tag
	 * - git_fetch_apply_version_with_overwrite
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_fetch_tag' or 'git_fetch_apply_version_with_overwrite' ).
	 * @param int    $id id.
	 */
	public function git_fetch_tag( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have a tag name before doing anything else.
		if ( empty( $args['git_tag'] ) ) {
			return new \WP_Error( __( 'The tag name should not be blank.', 'simple-stack' ) );
		}

		// Make sure the sanitized tag name and the provided tag name are the same.
		$sanitized_tag = $this->sanitize_tag( $args['git_tag'] );
		if ( $sanitized_tag !== $args['git_tag'] ) {
			/* Translators: %s is the suggested tag name. */
			return new \WP_Error( sprintf( __( 'The tag provided is invalid. Maybe it should be %s', 'simple-stack' ), $sanitized_tag ) );
		}

		// Sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Store the tag into a temporary meta so we can use it after the command completes.
		update_post_meta( $id, 'temp_git_tag', $original_args['git_tag'] );

		// At this point, we have everything we need so initialize some vars we'll use later.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Setup unique command name.
		$command             = sprintf( '%s---%s---%d', $action, $domain, time() );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		// Configure the run cmd.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site_command.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
				)
			)
		);

		/**
		 * Run the constructed command
		 * Check out the write up about the different aysnc methods we use
		 * here: https://github.com/onedogsolutions/simple-stack/blob/main/docs/SIMPLESTACK.md#4-provisioning-scripts
		 */
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );

		return $return;

	}

	/**
	 * Setup credentials for the site without initializing it.
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case 'git_site_credentials').
	 * @param int    $id id.
	 */
	public function git_site_init_credentials( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have a REPO name before doing anything else.
		if ( empty( $args['git_remote_url'] ) ) {
			/* Translators: %s is the label name for a field that should not be blank. */
			return new \WP_Error( __( 'The repo name/url should not be blank.', 'simple-stack' ) );
		}

		// Merge our incoming git settings with global and server defaults.
		$git_settings = $this->merge_git_settings( $id, $args );

		// Add back in the repo url to the $git_settings array - it would not have been handled by our call to merge_git_settings() since it's not a value that is set as a default anywhere.
		$git_settings['git_remote_url'] = $args['git_remote_url'];

		// Replace the $args array with our git_settings array (because all our other scripts use $args when passing an array to bash scripts - don't want to surprise a dev later with an unexpected var name).
		$args = $git_settings;

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		// Save the fields into the database even if if some of them are blank or incorrect.  This allows the user to change them without retyping everything!
		// Note that we will create a new array just for saving so that we can encrypt the token without affecting later processing that needs an unencrypted token.
		$git_settings_for_saving              = $git_settings;
		$git_settings_for_saving['git_token'] = $this->encrypt( $git_settings_for_saving['git_token'] );
		update_post_meta( $id, 'sstk_app_git_settings', $git_settings_for_saving );

		// Certain settings should not be blank.  Loop through those and error out if they're blank.
		$non_blank_fields = array(
			'git_user_email'   => __( 'Email Address', 'simple-stack' ),
			'git_display_name' => __( 'Display Name', 'simple-stack' ),
			'git_user_name'    => __( 'User Name', 'simple-stack' ),
			'git_token'        => __( 'API Token', 'simple-stack' ),
		);
		foreach ( $non_blank_fields as $field => $display_name ) {
			if ( empty( $git_settings[ $field ] ) ) {
				/* Translators: %s is the label name for a field that should not be blank. */
				return new \WP_Error( sprintf( __( 'The field %s should not be blank. We could not find a value in the server or global defaults.', 'simple-stack' ), $display_name ) );
			}
		}

		// At this point, the $git_settings array keys should match those expected by the bash scripts.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );

		// Get the full command to be executed by ssh.
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'git_control_site.txt',
			array_merge(
				$args,
				array(
					'action' => $action,
					'domain' => $domain,
				)
			)
		);

		// log.
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'git_control_site.txt' );
		if ( ! $success ) {
			// Log the attempt.
			$this->git_add_to_site_log( $id, __( 'Attempt to setup credentials for the site was not successful.', 'simple-stack' ) );
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			// Action successful Update metas and log.
			update_post_meta( $id, 'wpapp_git_initial_credentials_only_valid', 1 );

			// Log it.
			$this->git_add_to_site_log( $id, __( 'Credentials were setup for this site without initializing git.', 'simple-stack' ) );

		}

		return $success;

	}

	/**
	 * Perform various git actions on the site.
	 * These are short-running actions.
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used).
	 * @param int    $id id.
	 */
	public function git_actions( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Initialize vars to be used later.
		$run_cmd = '';

		// Get the domain we're working on.
		$domain = get_post_meta( $id, 'wpapp_domain', true );
		if ( empty( $domain ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the domain for action %s', 'simple-stack' ), $action ) );
		}

		// Validation for each action type.
		switch ( $action ) {
			case 'git_domain_remove':
				// None needed.
				break;
			case 'git_sync':
			case 'git_commit_and_push':
				if ( empty( $args['git_commit_msg'] ) ) {
					/* Translators: %s is the action name. */
					return new \WP_Error( sprintf( __( 'Unable to execute this request because the commit message is empty. (action %s)', 'simple-stack' ), $action ) );
				}
				break;
			case 'git_new_branch':
				if ( empty( $args['git_branch'] ) ) {
					/* Translators: %s is the action name. */
					return new \WP_Error( sprintf( __( 'Unable to execute this request because you did not supply a source branch. (action %s)', 'simple-stack' ), $action ) );
				}
				if ( empty( $args['git_new_branch'] ) ) {
					/* Translators: %s is the action name. */
					return new \WP_Error( sprintf( __( 'Unable to execute this request because you did not supply a name for the new branch. (action %s)', 'simple-stack' ), $action ) );
				}

				// Make sure we have a good branch name.
				$args['git_new_branch'] = sanitize_title( $args['git_new_branch'] );
				break;
			case 'git_remove_all_version_folders':
				// Nothing needed here.
				break;

		}

		// sanitize the fields to allow them to be used safely on the bash command line.
		$original_args = $args;
		$args          = array_map(
			function( $item ) {
				return escapeshellarg( $item );
			},
			$args
		);

		switch ( $action ) {
			case 'git_domain_remove':
			case 'git_sync':
			case 'git_commit_and_push':
			case 'git_new_branch':
			case 'git_remove_all_version_folders':
				// Get the full command to be executed by ssh.
				$run_cmd = $this->turn_script_into_command(
					$instance,
					'git_control_site.txt',
					array_merge(
						$args,
						array(
							'action' => $action,
							'domain' => $domain,
						)
					)
				);
				break;
		}

		// log.
		do_action( 'sstk_log_error', sprintf( 'attempting to run command for %s = %s ', print_r( $instance, true ), $run_cmd ), 'trace', __FILE__, __LINE__, $instance, false );

		// execute and evaluate results.
		$result  = $this->execute_ssh( 'generic', $instance, array( 'commands' => $run_cmd ) );
		$success = $this->is_ssh_successful( $result, 'git_control_site.txt' );
		if ( ! $success ) {
			// Log the attempt.
			switch ( $action ) {
				case 'git_domain_remove':
					$this->git_add_to_site_log( $id, __( 'Attempt to remove git for the site was not successful.', 'simple-stack' ) );
					break;
				case 'git_sync':
					$this->git_add_to_site_log( $id, __( 'Attempt to sync site with remote repo was not successful.', 'simple-stack' ) );
					break;
				case 'git_commit_and_push':
					$this->git_add_to_site_log( $id, __( 'Attempt to commit and push to remote repo was not successful.', 'simple-stack' ) );
					break;
				case 'git_new_branch':
					$this->git_add_to_site_log( $id, __( 'Attempt to create a new branch was not successful.', 'simple-stack' ) );
					break;
				case 'git_remove_all_version_folders':
					$this->git_add_to_site_log( $id, __( 'Attempt to all tag/version folders was not successful.', 'simple-stack' ) );
					break;
			}

			/* Translators: %1s is the action name; %2s is a long result string or array. */
			return new \WP_Error( sprintf( __( 'Unable to %1$s site: %2$s', 'simple-stack' ), $action, $result ) );
		} else {
			// Action successful - log it and do some other actions (depending on the action string).
			switch ( $action ) {
				case 'git_domain_remove':
					// Log it.
					$this->git_add_to_site_log( $id, __( 'Git was removed from this site.', 'simple-stack' ) );
					// Remove git meta.
					$this->set_git_status( $id, false );
					break;
				case 'git_sync':
					// Log it.
					$this->git_add_to_site_log( $id, __( 'Site was synced with remote repo.', 'simple-stack' ) );
					break;
				case 'git_commit_and_push':
					// Log it.
					$this->git_add_to_site_log( $id, __( 'Site changes were committed and pushed to remote repo.', 'simple-stack' ) );
					break;
				case 'git_new_branch':
					// Log it.
					/* Translators: %s is the git branch that was created. */
					$this->git_add_to_site_log( $id, sprintf( __( 'Branch %s was created and checked out.', 'simple-stack' ), $original_args['git_new_branch'] ) );

					// And update the current branch.
					$this->set_git_branch( $id, $original_args['git_new_branch'] );
					break;
				case 'git_remove_all_version_folders':
					// Log it.
					$this->git_add_to_site_log( $id, __( 'All tag/version folders were deleted from this site.', 'simple-stack' ) );
					break;
			}
		}

		return $success;

	}

	/**
	 * Save the push-to-deploy-actions options.
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - not used in this function).
	 * @param int    $id id.
	 */
	public function push_to_deploy_save_actions( $action, $id ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'simple-stack' ), $action ) );
		}

		$args = array_map( 'sanitize_text_field', wp_parse_args( wp_unslash( $_POST['params'] ) ) );

		// Make sure we have at least one branch.
		if ( empty( $args['git_push_to_deploy_action_branches'] ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because no branch was specified.  At least one branch must be specified. (action %s)', 'simple-stack' ), $action ) );
		}

		// Make sure the action to be performed is valid.
		if ( ! in_array( $args['git_push_to_deploy_action_type'], array( 'fetch', 'checkout' ), true ) ) {
			/* Translators: %s is the action name. */
			return new \WP_Error( sprintf( __( 'Unable to execute this request because the push-to-deploy action is not valid - it should be fetch or checkout. (action %s)', 'simple-stack' ), $action ) );
		}

		// Save to metas.
		update_post_meta( $id, 'sstk_app_git_push_to_deploy_action_branches', $args['git_push_to_deploy_action_branches'] );
		update_post_meta( $id, 'sstk_app_git_push_to_deploy_action_type', $args['git_push_to_deploy_action_type'] );

		return true;

	}

	/**
	 * Get the remote repo url.
	 *
	 * @param int $id The post id of the site we're interrogating.
	 *
	 * @return string|bool
	 */
	public function get_remote_repo_url( $id ) {

		// Get existing settings.
		$git_settings = $this->get_git_settings( $id );

		return ! empty( $git_settings['git_remote_url'] ) ? $git_settings['git_remote_url'] : false;

	}

	/**
	 * Add a message to the git log array for the site.
	 *
	 * @param int    $id Post id of site we're working with.
	 * @param string $msg Message to write to log.
	 */
	public function git_add_to_site_log( $id, $msg ) {

		// Get current logs.
		$logs = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_history', true ) );

		// Make sure we have something in the logs array otherwise create a blank one.
		if ( empty( $logs ) ) {
			$logs = array();
		}
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		// Add to array.
		$key          = sstk_generate_uuid();
		$logs[ $key ] = array(
			'reporting_time'           => time(),
			'reporting_time_human'     => date( 'Y-m-d H:i:s', time() ),
			'reporting_time_human_utc' => gmdate( 'Y-m-d H:i:s' ),
			'msg'                      => $msg,
		);

		// Keep only 25 items.
		$keep = 25;
		if ( count( $logs ) > $keep ) {
			array_splice( $logs, 0, count( $logs ) - $keep );
		}

		// Push back to database.
		return update_post_meta( $id, 'sstk_app_git_history', $logs );

	}

	/**
	 * Return the git log meta value.
	 *
	 * @param int $id Post id of site we're working with.
	 *
	 * @return array.
	 */
	public function get_git_log_history( $id ) {
		// Get current tag list.
		$logs = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_history', true ) );

		// Make sure we have something in the logs array otherwise create a blank one.
		if ( empty( $logs ) ) {
			$logs = array();
		}
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		return $logs;
	}

	/**
	 * Add to an array of git tags created.
	 *
	 * @param int    $id Post id of site we're working with.
	 * @param string $new_tag The new tag created or added.
	 * @param string $new_tag_desc Description of the new tag.
	 */
	public function git_add_tag_history( $id, $new_tag, $new_tag_desc ) {

		// Get current tag list.
		$tags = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_tag_history', true ) );

		// Make sure we have something in the tags array otherwise create a blank one.
		if ( empty( $tags ) ) {
			$tags = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		// Add to array.
		if ( empty( $tags[ $new_tag ] ) ) {
			// Tag does not yet exist in the array so add it.
			$tags[ $new_tag ] = array(
				'reporting_time'           => time(),
				'reporting_time_human'     => date( 'Y-m-d H:i:s', time() ),
				'reporting_time_human_utc' => gmdate( 'Y-m-d H:i:s' ),
				'desc'                     => $new_tag_desc,
			);
		} else {
			// Perhaps update the time here? Or add other history?
			$tags[ $new_tag ]['last_pull_reporting_time']           = time();
			$tags[ $new_tag ]['last_pull_reporting_time_human']     = date( 'Y-m-d H:i:s', time() );
			$tags[ $new_tag ]['last_pull_reporting_time_human_utc'] = gmdate( 'Y-m-d H:i:s' );
		}

		// Push back to database.
		return update_post_meta( $id, 'sstk_app_git_tag_history', $tags );

	}

	/**
	 * Return the git tag history meta value.
	 *
	 * @param int $id Post id of site we're working with.
	 *
	 * @return array.
	 */
	public function get_git_tag_history( $id ) {

		// Get current tag list.
		$tags = sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_tag_history', true ) );

		// Make sure we have something in the logs array otherwise create a blank one.
		if ( empty( $tags ) ) {
			$tags = array();
		}
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		return $tags;

	}

	/**
	 * Get a branch formatted with divs and spans for display in a header.
	 *
	 * @param int $id Post id of site we're working with.
	 */
	public function get_formatted_git_branch_for_display( $id ) {
		/* Translators: %s is the current git branch for the site. */
		$branch = sprintf( __( 'Current Branch: %s', 'simple-stack' ), $this->get_git_branch( $id ) );
		$branch = '<div class="sstk_git_site_branch_wrap"><span class="sstk_git_site_branch">' . $branch . '</span></div>';
		return $branch;
	}

	/**
	 * Get the repo url formatted with divs and spans for display in a header.
	 *
	 * @param int $id Post id of site we're working with.
	 */
	public function get_formatted_git_remote_repo_for_display( $id ) {

		/* Translators: %s is the current git repo url associated with this site. */
		$repo = sprintf( __( 'Repo: %s', 'simple-stack' ), $this->get_remote_repo_url( $id ) );
		$repo = '<div class="sstk_git_site_repo_wrap"><span class="sstk_git_site_repo">' . $repo . '</span></div>';

		return $repo;
	}

	/**
	 * Remove git related metas.
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case it's not used').
	 * @param int    $id id.
	 *
	 * @return boolean;
	 */
	public function remove_metas( $action, $id ) {

		// Legacy metas used during development. These were changed to new names later.
		delete_post_meta( $id, 'sstk_wpapp_git_settings' );
		delete_post_meta( $id, 'sstk_wpapp_git_history' );
		delete_post_meta( $id, 'sstk_app_tag_history' );

		// Production metas.
		delete_post_meta( $id, 'sstk_app_git_settings' );
		delete_post_meta( $id, 'wpapp_git_branch' );
		delete_post_meta( $id, 'wpapp_git_initial_credentials_only_valid' );
		delete_post_meta( $id, 'sstk_app_git_tag_history' );
		delete_post_meta( $id, 'sstk_app_git_push_to_deploy_webhook_url' );
		delete_post_meta( $id, 'sstk_app_git_push_to_deploy_secret_key' );
		delete_post_meta( $id, 'sstk_app_git_push_to_deploy_action_branches' );
		delete_post_meta( $id, 'sstk_app_git_push_to_deploy_action_type' );

		// Remove the status meta.
		$this->set_git_status( $id, false );

		// Log the action.
		$this->git_add_to_site_log( $id, __( 'Metas deleted.', 'simple-stack' ) );

		return true;
	}

	/**
	 * Takes a string and removes everything except alphanumeric
	 * characters, dashes and periods.
	 *
	 * @param string $tag The tag to clean.
	 */
	public function sanitize_tag( $tag ) {
		return sstk_clean_alpha_numeric_dashes_periods_underscores( $tag );
	}

	/**
	 * Register an endpoint to handle the webhook from GitHub.
	 *
	 * The route has to be public: GitHub has no WordPress account. What secures it is the
	 * per-site `sstk_app_git_push_to_deploy_secret_key`, checked below with `hash_equals()`.
	 */
	public function register_github_webhook_endpoint() {
		register_rest_route(
			'simple-stack/v' . SSTK_REST_VERSION,
			'/handle-git-webhook/(?P<randomid>\d+)/(?P<id>\d+)/',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_git_push_to_deploy_notification' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle the data pushed to us from GitHub.
	 *
	 * @param WP_REST_Request $data Data given to us by WP from the GitHub post rest api call.
	 */
	public function handle_git_push_to_deploy_notification( WP_REST_Request $data ) {

		// @TODO: Ignore the event if it's not a 'push'.

		// Get the signature from the X-Hub-Signature header. GitHub always sends one, so a
		// request without it did not come from GitHub.
		if ( empty( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ) {
			return 'missing signature';  // Note: no translation here in case the sender needs a fixed error message code/string.
		}

		$signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) );

		// Extract the signature from the header value.
		if ( ! str_contains( $signature, '=' ) ) {
			return 'malformed signature';  // Note: no translation here in case the sender needs a fixed error message code/string.
		}

		list($algorithm, $signature) = explode( '=', $signature, 2 );

		// Check that the algorithm is SHA1 (GitHub uses this by default).
		if ( 'sha1' !== $algorithm ) {
			// Unsupported algorithm.
			return 'unsupported algorithm';  // Note: no translation here in case the sender needs a fixed error message code/string.
		}

		// Get the request body.
		$payload = file_get_contents( 'php://input' );

		// Compute the HMAC signature of the payload using the site's non-empty secret.
		$id = filter_var( sanitize_text_field( $data['id'] ), FILTER_VALIDATE_INT );
		if ( ! $id ) {
			return 'invalid site';
		}

		$secret = $this->decrypt( get_post_meta( $id, 'sstk_app_git_push_to_deploy_secret_key', true ) );
		if ( empty( $secret ) ) {
			return 'missing webhook secret';
		}

		$computed_signature = hash_hmac( $algorithm, $payload, $secret );

		// Compare the computed signature with the signature from the header.
		if ( ! hash_equals( $signature, $computed_signature ) ) {
			// Signatures don't match, reject the payload.
			return 'incorrect signature';  // Note: no translation here in case the sender needs a fixed error message code/string.
		}

		// Get the payload.
		$decoded_payload = json_decode( $payload, true ); // true means return an array.

		// Which branch is it?
		$ref = $decoded_payload['ref'];
		if ( empty( $ref ) ) {
			do_action( 'sstk_log_error', sprintf( 'Git push-to-deploy webhook cannot be handled because we could not determine a branch. App Id: %s', $id ), 'error', __FILE__, __LINE__, $instance, false );
			return false;
		}

		// $ref contains something like /refs/heads/main or /refes/heads/dev01.  Need to extract the last part.
		$parts  = explode( '/', $ref );
		$branch = end( $parts );
		if ( empty( $branch ) ) {
			do_action( 'sstk_log_error', sprintf( 'Git push-to-deploy webhook cannot be handled because we could not determine a branch. App Id: %s', $id ), 'error', __FILE__, __LINE__, $instance, false );
			return false;
		}

		// What branches should trigger action on our part?
		$trigger_branches = explode( ',', sstk_maybe_unserialize( get_post_meta( $id, 'sstk_app_git_push_to_deploy_action_branches', true ) ) );

		// If the current push is not for one of the branches, do nothing.
		if ( ! in_array( $branch, $trigger_branches, true ) ) {
			do_action( 'sstk_log_error', sprintf( 'Git push-to-deploy webhook cannot be handled because the pushed branch %s do not match any of the defined action branches for this site. App Id: %s', $branch, $id ), 'error', __FILE__, __LINE__, $instance, false );
			return false;
		}

		// What action should we perform?
		$webhook_action = get_post_meta( $id, 'sstk_app_git_push_to_deploy_action_type', true );

		switch ( $webhook_action ) {
			case 'fetch':
				// Setup array to be passed to pending logs.
				$args['domain']         = get_post_meta( $id, 'wpapp_domain', true );
				$args['git_remote_url'] = $this->get_remote_repo_url( $id );
				$args['git_branch']     = $branch;
				$args['action_hook']    = 'sstk_pending_log_git_clone_to_site';
				$args['action']         = 'git_clone_to_site';
				SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $id, 'git-clone-to-site', $id, $args, 'ready', $id, __( 'Git site clone triggered from incoming webhook', 'simple-stack' ) );
				break;
			case 'checkout':
				// Setup array to be passed to pending logs.
				$args['domain']      = get_post_meta( $id, 'wpapp_domain', true );
				$args['git_branch']  = $branch;
				$args['action_hook'] = 'sstk_pending_log_git_checkout';
				$args['action']      = 'git_checkout';
				SSTK_POSTS_PENDING_TASKS_LOG()->add_pending_task_log_entry( $id, 'git-checkout', $id, $args, 'ready', $id, __( 'Git checkout triggered from incoming webhook', 'simple-stack' ) );
				break;
				break;
		}

		// Log the push-to-deploy request to the site.
		$msg = sprintf( __( 'A push-to-deploy webhook was received for branch %s.', 'simple-stack' ), $branch );
		$this->git_add_to_site_log( $id, $msg );

		return true;
	}

	/**
	 * Git clone to site.
	 *
	 * Called from an action hook from the pending logs background process - SSTK_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: sstk_pending_log_git_clone_to_site
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $id         Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function sstk_pending_log_git_clone_to_site( $task_id, $id, $args ) {

		// Get data from pending-log.
		// Expecting the following array structure.
		/*
		Array (
			'domain' => 'sjx3te5gftpe.vnxv.com',
			'git_remote_url' => 'https://github.com/elindydotcom/testsstkintegration03',
			'git_branch' => 'dev01',
			'action_hook' => 'sstk_pending_log_git_clone_to_site',
			'action' => 'git_clone_to_site',
			'pending_tasks_id' => 225681,
			'pending_task_associated_server_id' => '215746',
			'pending_task_parent_post_type' => 'sstk_app',
		)
		*/
		$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Set task to in-process.
		$task_status = 'in-process';
		SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

		// Stamp the site with the task id.  We'll use it when the command completes.
		update_post_meta( $id, 'wpapp_pending_log_git_clone_to_site', $task_id );

		/**
		 * Fire action hook to call the git clone action for the site on the server.
		 * We're passing the $data array directly since it has the three elements the action hook expects:
		 *   - git_remote_url
		 *   - git_branch
		 *   - domain
		 */
		do_action( 'sstk_git_clone_to_site', 'git_clone_to_site', $id, $data );
	}

	/**
	 * Checkout a branch.
	 *
	 * Called from an action hook from the pending logs background process - SSTK_POSTS_PENDING_TASKS_LOG()->do_tasks()
	 *
	 * Action Hook: sstk_pending_log_git_checkout
	 *
	 * @param int   $task_id    Id of pending task that is firing this thing...
	 * @param int   $id         Id of site involved in this action.
	 * @param array $args       All the data needed to handle this action.
	 */
	public function sstk_pending_log_git_checkout( $task_id, $id, $args ) {

		// Get data from pending-log.
		// Expecting the following array structure.
		/*
		Array (
			'domain' => 'sjx3te5gftpe.vnxv.com',
			'git_branch' => 'dev01',
			'action_hook' => 'sstk_pending_log_git_checkout',
			'action' => 'git_ccheckout',
			'pending_tasks_id' => 225681,
			'pending_task_associated_server_id' => '215746',
			'pending_task_parent_post_type' => 'sstk_app',
		)
		*/
		$data = SSTK_POSTS_PENDING_TASKS_LOG()->get_data_by_id( $task_id );

		// Set task to in-process.
		$task_status = 'in-process';
		SSTK_POSTS_PENDING_TASKS_LOG()->update_task_by_id( $task_id, $data, $task_status );

		// Stamp the site with the task id.  We'll use it when the command completes.
		update_post_meta( $id, 'wpapp_pending_log_git_checkout', $task_id );

		/**
		 * Fire action hook to call the git checkout action for the site on the server.
		 * We're passing the $data array directly since it has the two elements the action hook expects:
		 *   - git_branch
		 *   - domain
		 */
		do_action( 'sstk_git_checkout', 'git_checkout', $id, $data );
	}

	/**
	 * Generate a git push-to-deploy call-back url.
	 *
	 * URL should match the endpoint structure declared in
	 * function register_github_webhook_endpoint.
	 *
	 * @param string $id Post id of site we're working with.
	 *
	 * @return string.
	 */
	public function generate_push_to_deploy_url( $id ) {

		$url = '';

		$randomid = sstk_random_str( 32, '0123456789' );

		$url = apply_filters( 'sstk_git_push_to_deploy_url', rest_url( "simple-stack/v" . SSTK_REST_VERSION . "/handle-git-webhook/$randomid/$id" ), $id );

		return $url;
	}

	/**
	 * Reset the push-to-deploy keys
	 *
	 * @param string $action The action to be performed (this matches the string required in the bash scripts if bash scripts are used - in this case it's not used').
	 * @param int    $id The post id of the site we're working with.
	 *
	 * @return boolean;
	 */
	public function reset_push_to_deploy_keys( $action, $id ) {

		$this->generate_push_to_deploy_keys( $id );

		return true;
	}

	/**
	 * Generate and save push-to-deploy keys.
	 *
	 * @param int $id The post id of the site we're working with.
	 */
	public function generate_push_to_deploy_keys( $id ) {

		$push_to_deploy_webhook_url = $this->generate_push_to_deploy_url( $id );
		$push_to_deploy_secret_key  = sstk_random_str();
		update_post_meta( $id, 'sstk_app_git_push_to_deploy_webhook_url', $push_to_deploy_webhook_url );
		update_post_meta( $id, 'sstk_app_git_push_to_deploy_secret_key', $this->encrypt( $push_to_deploy_secret_key ) );

	}

}

new SSTK_WORDPRESS_TABS_GIT_CONTROL_SITE();
