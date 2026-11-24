<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * SSTK_SSH Class
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require SSTK_PATH . 'vendor/autoload.php';

use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\PublicKeyLoader;

/**
 * Parent class for all app specific SSH classes.
 */
class SSTK_SSH {

	/**
	 * SSTK_SSH constructor.
	 */
	public function __construct() {
		// empty.
	}

	/**
	 * Execute a command using SSH.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $command The command to execute.
	 * @param array  $key_attributes The SSH key.
	 * @param string $action The action that is executing this command.
	 * @param string $reference_post_id The post ID most affected by this action and which will be used as a log reference (generally the server post).
	 * @param string $user The user to login with.
	 * @param callable|null $callback callback.
	 * @param array $secrets_to_redact Exact secret values used by the command.
	 *
	 * @return mixed
	 */
	public function exec( $ip, $command, array $key_attributes, $action, $reference_post_id, $user = 'root', $callback = null, $secrets_to_redact = array() ) {
		$private_key = SSTK()->decrypt( $key_attributes['key'] );
		$log_secrets = array_merge( (array) $secrets_to_redact, array( $key_attributes['key'], $private_key, $key_attributes['passwd'] ) );
		$log_command = sstk_redact_secret_text( $command, $log_secrets );

		do_action( 'sstk_log_error', "trying to execute $log_command on $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'sstk_exec_ssh_user', $user );

		$ssh = new SSH2( $ip );

		$key = PublicKeyLoader::load( $private_key, $key_attributes['passwd'] );

		/* Login */
		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'sstk_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			SSTK_POSTS_SSH_LOG()->add_ssh_log_entry( $reference_post_id, 'Login', 'Failed' );
			return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time.  This generally occurs when the server is initializing for the first time or is being restarted. <br /><br /> Please wait a few minutes and try again.  If the problem continues, please contact our support team.', 'simple-stack' ) ) );
		}

		/* Get ssh timeout and, it none set, use a default */
		$default_ssh_timeout = sstk_get_early_option( 'ssh_timeout' );
		if ( empty( $default_ssh_timeout ) ) {
			$default_ssh_timeout = 29;
		}

		/* If using a callback we likely want to lower the ssh timeout to get more frequent updates... */
		if ( ! empty( $callback ) ) {
			$ssh_timeout = 15;
		} else {
			// We're not using a callback so we need to allow as much time as possible to let the script run.
			// But there's no point in trying to get the ssh command to run longer than the PHP script is allowed to run.
			$php_max_execution_time = ini_get( 'max_execution_time' );
			if ( ! empty( $php_max_execution_time ) ) {
				// Set the SSH timeout to a few seconds less than the max execution timeout for a php script.
				$ssh_timeout = ( (int) $php_max_execution_time ) - 15;
				// Note that we cannot set the timeout for the webserver. So if the above timeout value is greater than the server timeouts, then weird behavior will occur.
				// For OpenLiteSpeed, set connTimeout in the tuning block of
				// /usr/local/lsws/conf/httpd_config.conf to 600 seconds or more.
			} else {
				$ssh_timeout = $default_ssh_timeout;
			}
		}

		/* Execute command - you have 3 mins before it times out. Anything greater than 3 mins should be handled with a callback.  Some proxy servers such as Cloudflare might only give you 1 minute before it times out! */
		$ssh->setTimeout( $ssh_timeout );
		$result      = $ssh->exec( $command, $callback );
		$log_result  = sstk_redact_secret_text( $result, $log_secrets );
		do_action( 'sstk_log_error', "Executing command $log_command, getting result = $log_result", 'trace', __FILE__, __LINE__, null, false );

		/* Add log */
		SSTK_POSTS_SSH_LOG()->add_ssh_log_entry( $reference_post_id, $command, $result, $log_secrets );

		/* No data coming back is considered an error.  SO, if running a command that results in no feedback pipe follow it up with "&& echo done" or something similar so you get some output. */
		if ( empty( $result ) ) {
			return new \WP_Error( $this->construct_error( __( 'Oops - an internal error occurred. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' ) ) );
		}

		/* Check for certain errors in generic commands related to this app */
		if ( in_array( $action, array( 'generic' ) ) ) {
			if ( strpos( $result, 'Could not get lock' ) !== false ) {
				do_action( 'sstk_log_error', "Error in executing command: $log_result", 'error', __FILE__, __LINE__ );
				return new \WP_Error( $this->construct_error( $log_result ) );
			}
		}

		/**
		 * Final result
		 *
		 * @TODO: Why are we echoing and then cleaning?  Wouldn't just a plain 'return $result' work?
		 */
		ob_start();
		echo $result;
		return ob_get_clean();
	}

	/**
	 * Download a file using SFTP.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $remote_file The remote file to download.
	 * @param string $local_file The local file to download (if empty, the file contents will be sent as a string).
	 * @param array  $key_attributes The SSH key.
	 * @param string $user The user to login with.
	 * @param int    $error_type 1=return html formatted string when there are errors  2=return a number 3=return a plain string with a message.
	 *
	 * @return mixed
	 */
	public function download( $ip, $remote_file, $local_file, array $key_attributes, $user = 'root', $error_type = 1 ) {
		do_action( 'sstk_log_error', "trying to download $remote_file from $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'sstk_download_ssh_user', $user, $key_attributes );

		$ssh = new SFTP( $ip );

		$key = PublicKeyLoader::load( SSTK()->decrypt( $key_attributes['key'] ), $key_attributes['passwd'] );

		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'sstk_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			switch ( $error_type ) {
				case 1:
					return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. <br /><br />Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' ) ) );
					break;
				case 2:
					return 101;
					break;
				case 3:
					return __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' );
					break;
			}
		}
		if ( empty( $local_file ) ) {
			$contents = $ssh->get( $remote_file );
			if ( empty( $contents ) ) {
				do_action( 'sstk_log_error', "Unable to find file $remote_file", 'error', __FILE__, __LINE__ );
				switch ( $error_type ) {
					case 1:
						return new \WP_Error( $this->construct_error( __( 'We are not able to locate the file we are looking for. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' ) ) );
						break;
					case 2:
						return 102;
						break;
					case 3:
						return __( 'We are not able to locate the file we are looking for. Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' );
						break;
				}
			}
			ob_start();
			echo $contents;
			return ob_get_clean();
		} else {
			$ssh->get( $remote_file, $local_file );
		}
	}

	/**
	 * Uploads a file using SFTP.
	 *
	 * @param string $ip The IP of the instance.
	 * @param string $remote_file The remote file name.
	 * @param string $local_file The local file to upload.
	 * @param array  $key_attributes The SSH key.
	 * @param string $user The user to login with.
	 *
	 * @return mixed
	 */
	public function upload( $ip, $remote_file, $local_file, array $key_attributes, $user = 'root' ) {
		do_action( 'sstk_log_error', "trying to upload $remote_file to $local_file on $ip", 'debug', __FILE__, __LINE__ );

		/* Use a filter to force an override of the user name if necessary - this can be useful when servers such as aws don't use the 'root' user as the default login user */
		$user = apply_filters( 'sstk_upload_ssh_user', $user, $key_attributes );

		$ssh = new SFTP( $ip );

		$key = PublicKeyLoader::load( SSTK()->decrypt( $key_attributes['key'] ), $key_attributes['passwd'] );

		if ( ! $ssh->login( $user, $key ) ) {
			do_action( 'sstk_log_error', "Unable to login to server at ip $ip", 'error', __FILE__, __LINE__ );
			return new \WP_Error( $this->construct_error( __( 'Unfortunately it looks like we are unable to connect to the server at this time. This generally occurs when the server is initializing for the first time or is being restarted. <br /><br />Please wait a few minutes and try again. If the problem continues, please contact our support team.', 'simple-stack' ) ) );
		}

		$result = $ssh->put( $remote_file, $local_file, SFTP::SOURCE_LOCAL_FILE );

		do_action( 'sstk_log_error', "uploaded $remote_file to $local_file on $ip", 'trace', __FILE__, __LINE__, null, false );

		return $result;
	}

	/**
	 * Take an error message STRING and turn it into a nicely formatted
	 * html error suitable for display in the magnific popup.
	 *
	 * @param string $message The message to reformat and display.
	 *
	 * @return string $new_message The reformatted message.
	 */
	public function construct_error( $message ) {
		$message      = sstk_redact_secret_text( $message );
		$new_message  = '<div class="sstk-error-message-wrap">';
		$new_message .= '<div class="sstk-error-message-title">' . __( 'Oops - An Error Has Occurred', 'simple-stack' ) . '</div>';
		$new_message .= '<div class="sstk-error-message">' . $message;

		// Add text to tell the user how to dismiss the message.
		$new_message .= '<div class="sstk-error-dismiss-message">' . __( 'To dismiss this message click on the X in the upper right or anywhere on the page outside of this box.', 'simple-stack' ) . '</div>';
		$new_message .= '</div>';

		// Add a link to the help screen if one is configured.
		$help_url = sstk_get_option( 'sstk_general_help_url' );
		if ( ! empty( $help_url ) ) {
			$new_message     .= '<hr />';
			$new_message     .= '<div class="sstk-error-message-help-footer">';
			$new_message     .= '<div class="sstk-error-message-help-title">' . __( 'Help Resources', 'simple-stack' ) . '</div>';
			$new_message     .= '<div class="sstk-error-message-help-text">';
			$new_message     .= sprintf( __( 'For more information and additional help please check out our <a href="%s"> help pages.</a>', 'simple-stack' ), esc_url( $help_url ) );
			$new_message     .= '</div> <!-- error-message-help-text -->';
			$new_message     .= '</div>';
		}

		$new_message .= '</div> <!-- error-message-wrap -->';

		return $new_message;
	}

	/**
	 * Create an ed25519 keypair and return both parts.
	 */
	public function create_key_pair() {

		$private = EC::createKey( 'Ed25519' );
		$public  = $private->getPublicKey();

		$pair = array(
			'public'  => $public->toString( 'OpenSSH' ),
			'private' => $private->toString( 'OpenSSH' ),
		);

		return apply_filters( 'sstk_create_ssh_key_pair', $pair );

	}

}
