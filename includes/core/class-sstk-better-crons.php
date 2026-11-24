<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * This class handles WP CLI command for linux cron.
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BETTER SSTK CRONS
 *
 * @package simple-stack
 * @version 1.0.0 / sstk
 * @since 4.2.0
 */
class BETTER_SSTK_CRONS {

	/**
	 * Disable WP cron when defined( 'DISABLE_SSTK_CRON','true' ) for SSTK
	 */
	private function sstk_disable_wp_cron() {
		// Perform action only when defined( 'DISABLE_SSTK_CRON','true' ).
		if ( defined( 'DISABLE_SSTK_CRON' ) && true === DISABLE_SSTK_CRON ) {
			// Clear old crons.
			wp_unschedule_hook( 'sstk_wordpress_deferred_actions_for_server' );
			wp_unschedule_hook( 'sstk_wordpress_deferred_actions_for_apps' );
			wp_unschedule_hook( 'sstk_wordpress_file_watcher' );
			wp_unschedule_hook( 'sstk_scan_notifications_actions' );
			wp_unschedule_hook( 'sstk_clean_up_pending_logs' );
			wp_unschedule_hook( 'sstk_email_alert_for_long_pending_tasks' );
		}
	}

	/**
	 * Set up WP cron actions for SSTK.
	 */
	public function sstk_wp_cron_actions() {
		// Perform action only when defined( 'DISABLE_SSTK_CRON','true' ).
		if ( defined( 'DISABLE_SSTK_CRON' ) && false === DISABLE_SSTK_CRON ) {

			WP_CLI::warning( __( 'DISABLE_SSTK_CRON should be (true) in wp-config.php', 'simple-stack' ) );
			exit();
		}

		/**
		 * Set constant to let others know we're doing our cron stuff.
		 */
		define( 'SSTK_DOING_CORE_CRON', true );

		/**
		 * Add notification we've started.
		 */
		// phpcs:ignore
		do_action( 'sstk_log_error', __( 'Starting better core crons', 'simple-stack' ), 'better_cron', __FILE__, __LINE__, '', false );

		/**
		 * Unschedule cron when defined( 'DISABLE_SSTK_CRON','true' ).
		 * This should not be necessary but it's a prophylactic.
		 */
		$this->sstk_disable_wp_cron();

		/**
		 * Allow others to hook in before we fire all our cron processes.
		 */
		do_action( 'sstk_before_better_crons' );

		/**
		 * Sequentially fire all our CRON action hooks.
		 */
		do_action( 'sstk_wordpress_deferred_actions_for_server' );
		do_action( 'sstk_wordpress_deferred_actions_for_apps' );
		do_action( 'sstk_wordpress_file_watcher' );
		do_action( 'sstk_scan_notifications_actions' );
		do_action( 'sstk_email_alert_for_long_pending_tasks' );
		do_action( 'sstk_clean_up_pending_logs' );

		/*
		 * Allow extensions to hook in after the core cron processes finish.
		 */
		do_action( 'sstk_after_better_crons' );

		/**
		 * Add notification we've finished.
		 */
		// phpcs:ignore
		do_action( 'sstk_log_error', __( 'Better core crons complete', 'simple-stack' ), 'better_cron', __FILE__, __LINE__, '', false );

	}

}


add_action( 'cli_init', 'sstk_cli_register_commands' );
/**
 * Registers our command when cli get's initialized.
 */
function sstk_cli_register_commands() {
	WP_CLI::add_command( 'simple-stack', 'BETTER_SSTK_CRONS' );
}
