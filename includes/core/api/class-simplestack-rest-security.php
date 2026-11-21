<?php
/**
 * SimpleStack REST controller for site security posture.
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleStack_REST_Security
 *
 * Runs a strictly read-only probe against a site and reports what is installed and
 * how it is configured. Nothing here mutates the remote system and no key material
 * is ever placed in the response.
 */
class SimpleStack_REST_Security extends SimpleStack_REST_Controller_Base {

	/**
	 * Separator used to merge three read-only commands into one SSH round trip.
	 */
	const SPLIT = '#SIMPLESTACK#';

	/**
	 * Register the security routes.
	 *
	 * @return void
	 */
	public function register_routes() {

		$this->register_post_route(
			'sites' . static::RESOURCE_ID_PATH . '/security-status',
			'security_status',
			array( 'id' => $this->arg_id() )
		);
	}

	/**
	 * Probe a site and return its security posture.
	 *
	 * POST /sites/{id}/security-status
	 *
	 * @param WP_REST_Request $request The incoming request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function security_status( WP_REST_Request $request ) {

		$app_id = (int) $request->get_param( 'id' );
		$app    = get_post( $app_id );

		if ( ! $app || 'wpcd_app' !== $app->post_type ) {
			return $this->error( 'simplestack_site_not_found', __( 'Site not found.', 'wpcd' ), 404 );
		}

		$domain    = WPCD_WORDPRESS_APP()->get_domain_name( $app_id );
		$server_id = (int) get_post_meta( $app_id, 'parent_post_id', true );

		$data = array(
			'app_id'                => $app_id,
			'server_id'             => $server_id,
			'domain'                => $domain,
			'connected'             => false,
			'plugins'               => array(),
			'file_edit_disallowed'  => false,
			'php_version'           => '',
			'message'               => '',
		);

		if ( empty( $domain ) || $server_id <= 0 ) {
			$data['message'] = __( 'The site record has no domain or no parent server, so it cannot be probed.', 'wpcd' );
			return $this->respond( $data );
		}

		$ssh = $this->resolve_ssh( $server_id );

		if ( is_wp_error( $ssh ) ) {
			$data['message'] = $ssh->get_error_message();
			return $this->respond( $data );
		}

		$output = WPCD_WORDPRESS_APP()->ssh()->exec(
			$ssh['ip'],
			$this->build_command( $domain ),
			$ssh,
			'generic',
			$server_id,
			$ssh['root_user']
		);

		if ( is_wp_error( $output ) ) {
			$data['message'] = $output->get_error_message();
			return $this->respond( $data );
		}

		$data['connected'] = true;

		return $this->respond( array_merge( $data, $this->parse_probe_output( (string) $output ) ) );
	}

	/**
	 * Turn the merged probe output into the fields the UI renders.
	 *
	 * Kept separate from the transport work so the parsing can be exercised without an
	 * SSH connection and without a live site.
	 *
	 * @param string $output Raw stdout produced by build_command().
	 *
	 * @return array {
	 *     @type array  $plugins              Normalised plugin rows.
	 *     @type bool   $file_edit_disallowed Whether DISALLOW_FILE_EDIT is true.
	 *     @type string $php_version          PHP version the site runs, or ''.
	 * }
	 */
	protected function parse_probe_output( string $output ): array {

		$packets = explode( self::SPLIT, $output );

		$result = array(
			'plugins'              => array(),
			'file_edit_disallowed' => false,
			'php_version'          => '',
		);

		$plugins = json_decode( trim( isset( $packets[0] ) ? $packets[0] : '' ), true );
		if ( is_array( $plugins ) ) {
			$result['plugins'] = $this->normalize_plugins( $plugins );
		}

		if ( isset( $packets[1] ) ) {
			$result['file_edit_disallowed'] = 'true' === strtolower( trim( $packets[1] ) );
		}

		if ( isset( $packets[2] ) && preg_match( '/PHP\s+([0-9]+\.[0-9]+\.[0-9]+)/i', (string) $packets[2], $matches ) ) {
			$result['php_version'] = $matches[1];
		}

		/*
		 * The vhost handler wins over `php -v`: it is the version the site actually runs,
		 * while the CLI answer can describe a different build. Packet 3 is empty when the
		 * grep found nothing, in which case the CLI answer above is kept.
		 */
		if ( isset( $packets[3] ) && preg_match( '/lsphp([0-9])([0-9])/', (string) $packets[3], $handler ) ) {
			$result['php_version'] = $handler[1] . '.' . $handler[2];
		}

		return $result;
	}

	/**
	 * Build the merged read-only probe command.
	 *
	 * Every packet is a read: `wp plugin list`, `wp config get`, `php -v` and a grep over
	 * the vhost config. Nothing is written to the site, the database or the filesystem.
	 *
	 * @param string $domain The site domain.
	 *
	 * @return string
	 */
	protected function build_command( string $domain ): string {
		/*
		 * WordPress lives in the site's `html` subdirectory, not at the /var/www/<domain>
		 * root. wp-cli needs to start in the directory that holds wp-config.php, so the
		 * trailing /html is what makes the probe answer at all.
		 */
		$path  = escapeshellarg( '/var/www/' . $domain . '/html' );
		$vhost = escapeshellarg( '/usr/local/lsws/conf/vhosts/' . $domain );

		return implode(
			'; ',
			array(
				"cd {$path} && wp plugin list --format=json --allow-root 2>/dev/null",
				'echo ' . escapeshellarg( self::SPLIT ),
				"cd {$path} && wp config get DISALLOW_FILE_EDIT --allow-root 2>/dev/null",
				'echo ' . escapeshellarg( self::SPLIT ),
				'php -v 2>/dev/null | head -n 1',
				'echo ' . escapeshellarg( self::SPLIT ),

				/*
				 * `php -v` answers for whichever CLI PHP is first on PATH, which on an
				 * OpenLiteSpeed host is not necessarily the handler serving this site - and is
				 * nothing at all when no CLI php is installed. The vhost records the real one as
				 * `add lsapi:lsphp84 php`, so read that instead. The glob is left unquoted on
				 * purpose: g_vhost_conf is vhconf.conf or vhconf.xml depending on the version.
				 */
				"grep -h -o 'lsapi:lsphp[0-9]*' {$vhost}/vhconf.* 2>/dev/null | head -n 1",
			)
		);
	}

	/**
	 * Resolve the SSH attributes needed to reach a server.
	 *
	 * @param int $server_id The server post id.
	 *
	 * @return array|WP_Error
	 */
	protected function resolve_ssh( int $server_id ) {

		$provider = WPCD_SERVER()->get_server_provider( $server_id );

		if ( empty( $provider ) ) {
			return $this->error( 'simplestack_server_not_found', __( 'The site has no provider recorded on its server.', 'wpcd' ), 404 );
		}

		if ( empty( WPCD()->get_provider_api( $provider ) ) ) {
			return $this->error( 'simplestack_provider_unavailable', __( 'The provider for this site is not installed in this build.', 'wpcd' ), 400 );
		}

		$key = WPCD_WORDPRESS_APP()->ssh()->get_ssh_key_details(
			array(
				'post_id'  => $server_id,
				'provider' => $provider,
			)
		);

		if ( empty( $key ) || empty( $key['ip'] ) ) {
			return $this->error( 'simplestack_ssh_unavailable', __( 'The server is not reachable right now. Its provider instance may still be starting.', 'wpcd' ), 400 );
		}

		return $key;
	}

	/**
	 * Reduce the wp plugin list payload to the fields the UI needs.
	 *
	 * @param array $plugins Decoded JSON from wp plugin list.
	 *
	 * @return array
	 */
	protected function normalize_plugins( array $plugins ): array {

		$clean = array();

		foreach ( $plugins as $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}

			$clean[] = array(
				'name'    => isset( $plugin['name'] ) ? (string) $plugin['name'] : '',
				'status'  => isset( $plugin['status'] ) ? (string) $plugin['status'] : '',
				'version' => isset( $plugin['version'] ) ? (string) $plugin['version'] : '',
				'update'  => isset( $plugin['update'] ) ? (string) $plugin['update'] : '',
			);
		}

		return $clean;
	}
}
