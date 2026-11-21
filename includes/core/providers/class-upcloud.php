<?php
/**
 * UpCloud provider (API 1.3).
 *
 * @package wpcd
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_Upcloud
 *
 * Implements the CLOUD_PROVIDER_API contract against the UpCloud 1.3 REST API.
 *
 * Differences from DigitalOcean and Vultr that shape everything in this file:
 *
 * - Authentication is HTTP Basic using an account user name and password, not a
 *   bearer token.  The settings screen therefore stores `vpn_upcloud_user_name` and
 *   `vpn_upcloud_user_password` instead of an api key, and both are encrypted at
 *   rest.  uses_api_key() answers false so the settings screen stops looking for an
 *   api key that will never exist.
 * - The password travels inside the Authorization header on every request.  Nothing
 *   in this class may log a request header, a request body or a full request URL.
 * - A storage template has to be cloned to create a disk, so the create call needs a
 *   template uuid resolved from GET /storage/template before it can run.
 * - `user_data` is a plain script; UpCloud does not expect it to be base64 encoded.
 * - UpCloud has no id-addressable snapshot resource.  Backups belong to a storage
 *   device and are identified by date, which does not fit the snapshot-id contract,
 *   so the snapshot feature flags stay off rather than shipping a half-mapped API.
 */
class CLOUD_PROVIDER_API_Upcloud extends CLOUD_PROVIDER_API {

	/**
	 * Base URL for every UpCloud 1.3 endpoint.
	 */
	const _URL = 'https://api.upcloud.com/1.3/';

	/**
	 * The Ubuntu release used when the caller has not requested a specific one.
	 */
	const _DEFAULT_OS_VERSION = '24.04';

	/**
	 * How long a power operation may stay outstanding before we stop waiting.
	 */
	const _POWER_ACTION_TIMEOUT = 1800;

	/**
	 * The provider account user name.
	 *
	 * @var string $user_name
	 */
	private $user_name;

	/**
	 * The provider account password.
	 *
	 * @var string $user_password
	 */
	private $user_password;

	/**
	 * CLOUD_PROVIDER_API_Upcloud constructor.
	 *
	 * @param array $args Optional overrides, used when this class is instantiated for a
	 *                    virtual provider defined in the wpcd_cloud_provider post type.
	 */
	public function __construct( $args = array() ) {

		parent::__construct();

		/* Set provider name and slug. */
		$this->set_provider( 'UpCloud' );
		$this->set_provider_slug( 'upcloud' );

		/* Set baseline provider name and slug - these stay put even on custom servers. */
		$this->set_base_provider_name( 'UpCloud' );
		$this->set_base_provider_slug( 'upcloud' );

		/* Allow a virtual provider to present itself under a different name and slug. */
		if ( ! empty( $args['provider_name'] ) ) {
			$this->set_provider( $args['provider_name'] );
		}
		if ( ! empty( $args['provider_slug'] ) ) {
			$this->set_provider_slug( $args['provider_slug'] );
		}
		if ( isset( $args['provider_region_prefix'] ) ) {
			$this->set_region_prefix( $args['provider_region_prefix'] );
		}

		/* Where the user gets their api credentials. */
		$this->set_provider_dashboard_link( 'https://hub.upcloud.com/account/api' );

		/* Features this provider implements. */
		$this->set_feature_flag( 'test_connection', true );
		$this->set_feature_flag( 'resize', true );
		$this->set_feature_flag( 'ssh_create', true );

		/*
		 * Off on purpose.  UpCloud identifies a backup by the storage device it belongs
		 * to and the date it was taken, so there is no snapshot id to return, delete or
		 * poll.  Leaving these on would only surface buttons that cannot work.
		 */
		$this->set_feature_flag( 'snapshots', false );
		$this->set_feature_flag( 'snapshot-delete', false );
		$this->set_feature_flag( 'snapshot-list', false );
		$this->set_feature_flag( 'backups', false );

		/* Nothing in this tree reads the custom-image options back, so do not offer them. */
		$this->set_feature_flag( 'custom_images', false );
		$this->set_feature_flag( 'dynamic_tags', false );
		$this->set_feature_flag( 'enable_dynamic_tags_on_server_create', false );
		$this->set_feature_flag( 'enable_backups_on_server_create', false );

		/* Pull the credentials from settings - both are stored encrypted. */
		$slug                = $this->get_provider_slug();
		$this->user_name     = WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $slug . '_user_name' ) );
		$this->user_password = WPCD()->decrypt( wpcd_get_early_option( 'vpn_' . $slug . '_user_password' ) );

		/* Add the link to the UpCloud dashboard next to the credential fields. */
		add_filter( "wpcd_cloud_provider_settings_api_key_label_desc_{$slug}", array( $this, 'set_link_to_provider_dashboard' ) );

		/* UpCloud adds its own credential fields to the settings screen. */
		add_filter( "wpcd_cloud_provider_settings_{$slug}", array( $this, 'settings' ), 10, 2 );

	}

	/**
	 * Set function for the account user name.
	 *
	 * @param string $user_name the user name.
	 *
	 * @return void
	 */
	public function set_user_name( $user_name ) {
		$this->user_name = $user_name;
	}

	/**
	 * Getter function for the account user name.
	 *
	 * @return string
	 */
	public function get_user_name() {
		return $this->user_name;
	}

	/**
	 * Set function for the account password.
	 *
	 * @param string $user_password the password.
	 *
	 * @return void
	 */
	public function set_user_password( $user_password ) {
		$this->user_password = $user_password;
	}

	/**
	 * Getter function for the account password.
	 *
	 * Only ever used to build the Authorization header.
	 *
	 * @return string
	 */
	public function get_user_password() {
		return $this->user_password;
	}

	/**
	 * UpCloud authenticates with a user name and password, not an api key.
	 *
	 * @return bool
	 */
	public function uses_api_key() {
		return false;
	}

	/**
	 * Whether the account credentials have been configured.
	 *
	 * @return bool
	 */
	public function has_credentials() {
		return ( ! empty( $this->user_name ) && ! empty( $this->user_password ) );
	}

	/**
	 * Return the credential material used to build cache keys.
	 *
	 * The ancestor hashes this into every transient name so that two accounts on the
	 * same install cannot collide, and both clear_cache() and
	 * get_cached_transient_list() hash it as well.
	 *
	 * @return string
	 */
	public function get_secret_key() {
		return $this->user_password;
	}

	/**
	 * Credentials, plus the key material an UpCloud server actually needs.
	 *
	 * UpCloud installs SSH keys from the create payload rather than from a key
	 * registry, so what matters here is that a public key exists to send and a private
	 * key exists for WPCD to connect with.  `vpn_upcloud_sshkey_id` is only a local
	 * bookkeeping marker and is deliberately not part of this test.
	 *
	 * @return bool
	 */
	public function credentials_available() {

		if ( ! $this->has_credentials() ) {
			return false;
		}

		$slug = $this->get_provider_slug();

		if ( empty( wpcd_get_early_option( 'vpn_' . $slug . '_public_sshkey' ) ) ) {
			return false;
		}

		if ( empty( wpcd_get_early_option( 'vpn_' . $slug . '_sshkey' ) ) ) {
			return false;
		}

		return true;

	}

	/**
	 * Add the UpCloud account credential fields to the settings screen.
	 *
	 * Filter hook: wpcd_cloud_provider_settings_upcloud
	 *
	 * The api key and notes fields are removed rather than relabelled.  A visible field
	 * that nothing reads is an invitation to paste the account password into it, where
	 * it would be stored under a key no part of this class looks at.
	 *
	 * @param array  $provider_fields array of settings fields.
	 * @param string $tab_id the ID of the tab being handled when this filter is executed.
	 *
	 * @return array
	 */
	public function settings( $provider_fields, $tab_id ) {

		$provider = $this->get_provider_slug();
		if ( empty( $provider ) ) {
			return $provider_fields;
		}

		$fields = array(
			array(
				'id'   => "vpn_{$provider}_user_name",
				'type' => 'text',
				'name' => __( 'Account User Name', 'wpcd' ),
				'size' => '60',
				'desc' => __( 'UpCloud authenticates with the user name and password of your UpCloud account, or of an API-only sub-account, instead of an api key. Both values are encrypted before they are stored. Use a sub-account with API access so that the root account credentials never sit on this site.', 'wpcd' ),
				'tab'  => $tab_id,
			),
			array(
				'id'         => "vpn_{$provider}_user_password",
				'type'       => 'text',
				'name'       => __( 'Account Password', 'wpcd' ),
				'size'       => '60',
				'class'      => 'wpcd_settings_pass_toggle',
				'attributes' => array(
					'spellcheck' => 'false',
				),
				'desc'       => __( 'It is encrypted before being stored in the database and is only ever sent to api.upcloud.com.', 'wpcd' ),
				'tab'        => $tab_id,
			),
		);

		$removed = array( "vpn_{$provider}_apikey", "vpn_{$provider}_keynotes" );

		$kept = array();
		foreach ( $provider_fields as $field ) {
			if ( is_array( $field ) && isset( $field['id'] ) && in_array( $field['id'], $removed, true ) ) {
				continue;
			}
			$kept[] = $field;
		}

		return array_merge( $fields, $kept );

	}

	/**
	 * Call the API.
	 *
	 * @param string $method The method to invoke.
	 * @param array  $attributes The attributes for the action.
	 *
	 * Recognised attributes:
	 *  [id] / [provider_instance_id] - the UpCloud server uuid.
	 *  [region]                      - zone id, eg: fi-hel1.
	 *  [size]                        - WPCD size key; mapped through settings.
	 *  [size_raw]                    - UpCloud plan name, eg: 2xCPU-4GB.
	 *  [name]                        - title and hostname of the new server.
	 *  [initial_os]                  - WPCD OS key, eg: ubuntu2404lts.
	 *  [post_id]                     - server post id; used to read the pending action.
	 *  [new_size]                    - UpCloud plan name for a resize.
	 *  [public_key] / [public_key_name] - key material for ssh_create.
	 *
	 * @return mixed
	 */
	public function call( $method, $attributes = array() ) {

		/* Reads that are safe to cache never touch the API twice. */
		if ( in_array( $method, $this->get_cache_methods(), true ) ) {
			$cached = $this->get_cached_data( $method );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		if ( ! $this->has_credentials() ) {
			return false;
		}

		/* Accept the legacy provider_instance_id key as an alias for id. */
		if ( isset( $attributes['provider_instance_id'] ) && ! isset( $attributes['id'] ) ) {
			$attributes['id'] = $attributes['provider_instance_id'];
		}

		$id = isset( $attributes['id'] ) ? trim( (string) $attributes['id'] ) : '';

		$needs_id = array( 'details', 'status', 'reboot', 'off', 'on', 'delete', 'resize' );

		if ( in_array( $method, $needs_id, true ) && '' === $id ) {
			return new WP_Error( 'upcloud-missing-id', __( 'A provider instance id is required for this operation.', 'wpcd' ) );
		}

		switch ( $method ) {
			case 'regions':
				return $this->call_regions();
			case 'sizes':
				return $this->call_sizes();
			case 'keys':
				return $this->call_keys();
			case 'create':
				return $this->call_create( $attributes );
			case 'details':
				return $this->call_details( $id );
			case 'status':
				return $this->call_status( $attributes, $id );
			case 'reboot':
			case 'off':
			case 'on':
				return $this->call_power( $method, $id );
			case 'delete':
				return $this->call_delete( $id );
			case 'resize':
				return $this->call_resize( $attributes, $id );
			case 'ssh_create':
				return $this->call_ssh_create( $attributes );
			case 'test_connection':
				return $this->call_test_connection();
			case 'snapshot':
			case 'delete_snapshot':
			case 'list_snapshots':
			case 'list_all_snapshots':
			case 'pending_snapshot_status':
				return new WP_Error( 'not-supported', __( 'UpCloud does not expose snapshots through this control panel. Use the UpCloud control panel to manage server backups.', 'wpcd' ) );
			default:
				return new WP_Error( 'not-supported', __( 'This operation is not supported by the UpCloud provider.', 'wpcd' ) );
		}

	}

	/**
	 * Return the list of UpCloud zones.
	 *
	 * @return array|WP_Error Zone id => display label.
	 */
	private function call_regions() {

		$payload = $this->execute( 'zone', 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->zones->zone ) ) {
			return array();
		}

		$return = array();
		$prefix = $this->get_region_prefix();

		foreach ( $payload->zones->zone as $zone ) {
			if ( empty( $zone->id ) ) {
				continue;
			}

			$label = ! empty( $zone->description ) ? (string) $zone->description : (string) $zone->id;
			$return[ $zone->id ] = empty( $prefix ) ? $label : $prefix . ': ' . $label;
		}

		$this->store_cache( $this->get_cache_key( 'regions' ), $return, 'regions' );

		return $return;

	}

	/**
	 * Return the list of UpCloud plans.
	 *
	 * UpCloud plans have no id.  Their `name` (eg: 2xCPU-4GB) is the stable identifier
	 * and is what the create call accepts, so it becomes the array key here.
	 *
	 * @return array|WP_Error Plan name => display label.
	 */
	private function call_sizes() {

		$payload = $this->execute( 'plan', 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->plans->plan ) ) {
			return array();
		}

		$return = array();

		foreach ( $payload->plans->plan as $plan ) {

			if ( empty( $plan->name ) ) {
				continue;
			}

			$cores        = isset( $plan->core_number ) ? (int) $plan->core_number : 0;
			$memory       = isset( $plan->memory_amount ) ? (int) $plan->memory_amount : 0;
			$storage      = isset( $plan->storage_size ) ? (int) $plan->storage_size : 0;
			$traffic      = isset( $plan->public_traffic_out ) ? (int) $plan->public_traffic_out : 0;
			$storage_tier = isset( $plan->storage_tier ) ? (string) $plan->storage_tier : '';

			/* translators: %1$s is the UpCloud plan name; the remaining replacements describe the plan. */
			$return[ $plan->name ] = sprintf( __( '%1$s (%2$d CPUs, %3$d MB RAM, %4$d GB %5$s, %6$d GB traffic/month)', 'wpcd' ), $plan->name, $cores, $memory, $storage, $storage_tier, $traffic );
		}

		ksort( $return );

		$this->store_cache( $this->get_cache_key( 'sizes' ), $return, 'sizes' );

		return $return;

	}

	/**
	 * Return the ssh keys that have been uploaded to UpCloud.
	 *
	 * @return array|WP_Error Key title => key title.
	 */
	private function call_keys() {

		$payload = $this->execute( 'ssh_key', 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$return = array();

		if ( empty( $payload->ssh_keys->ssh_key ) || ! is_array( $payload->ssh_keys->ssh_key ) ) {
			return $return;
		}

		foreach ( $payload->ssh_keys->ssh_key as $key ) {
			if ( empty( $key->title ) ) {
				continue;
			}
			$return[ (string) $key->title ] = (string) $key->title;
		}

		$this->store_cache( $this->get_cache_key( 'keys' ), $return, 'keys' );

		return $return;

	}

	/**
	 * Create a new server.
	 *
	 * @param array $attributes Attributes of the server being provisioned.
	 *
	 * @return array|WP_Error
	 */
	private function call_create( $attributes ) {

		$zone = isset( $attributes['region'] ) ? trim( (string) $attributes['region'] ) : '';
		if ( '' === $zone ) {
			return new WP_Error( 'upcloud-missing-region', __( 'A zone is required to create an UpCloud server.', 'wpcd' ) );
		}

		$provider = $this->get_provider_slug();

		/*
		 * WPCD maps a size key (small, medium, ...) onto the provider's own plan name in
		 * settings so that products can be re-pointed without touching this class.
		 */
		$plan = '';
		if ( ! empty( $attributes['size'] ) ) {
			$plan = (string) wpcd_get_option( 'vpn_' . $provider . '_' . $attributes['size'] );
		}
		if ( empty( $plan ) && ! empty( $attributes['size_raw'] ) ) {
			$plan = trim( (string) $attributes['size_raw'] );
		}
		if ( '' === $plan ) {
			return new WP_Error( 'upcloud-missing-size', __( 'A plan is required to create an UpCloud server.', 'wpcd' ) );
		}

		$template = $this->resolve_storage_template( $attributes );
		if ( is_wp_error( $template ) ) {
			return $template;
		}

		/* UpCloud installs keys from the payload, so the public key is required up front. */
		$public_key = trim( (string) wpcd_get_early_option( 'vpn_' . $provider . '_public_sshkey' ) );
		if ( '' === $public_key ) {
			return new WP_Error( 'upcloud-missing-ssh-key', __( 'A public ssh key is required to create an UpCloud server, otherwise there would be no way to log into it.', 'wpcd' ) );
		}

		/* Startup commands are supplied by the application layer. */
		$run_cmd = apply_filters( 'wpcd_cloud_provider_run_cmd', '', $attributes );
		$run_cmd = apply_filters( 'wpcd_cloud_provider_run_cmd_' . $provider, $run_cmd, $attributes );

		$name     = isset( $attributes['name'] ) ? (string) $attributes['name'] : '';
		$hostname = $this->normalise_hostname( $name );

		$body = array(
			'server' => array(
				'zone'            => $zone,
				'title'           => $name,
				'hostname'        => $hostname,
				'plan'            => $plan,
				'storage_devices' => array(
					'storage_device' => array(
						array(
							'action'  => 'clone',
							'storage' => $template['uuid'],
							'title'   => $hostname . '-os',
							'size'    => $template['size'],
							'tier'    => $template['tier'],
						),
					),
				),
				'login_user'      => array(
					'username'        => 'root',
					'create_password' => 'no',
					'ssh_keys'        => array(
						'ssh_key' => array( $public_key ),
					),
				),
				'networking'      => array(
					'interfaces' => array(
						'interface' => array(
							array(
								'type'         => 'public',
								'ip_addresses' => array(
									'ip_address' => array(
										array( 'family' => 'IPv4' ),
									),
								),
							),
						),
					),
				),
			),
		);

		if ( '' !== $run_cmd ) {
			/*
			 * UpCloud takes user_data as a plain script string - it does not expect it to
			 * be base64 encoded the way Vultr does.
			 */
			$body['server']['user_data'] = "#cloud-config\nruncmd:\n" . $run_cmd;
		}

		$payload = $this->execute( 'server', 'POST', $body );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->server->uuid ) ) {
			return new WP_Error( 'upcloud-create-failed', __( 'UpCloud accepted the request but did not return a server uuid.', 'wpcd' ) );
		}

		$server = $payload->server;

		return array(
			'provider_instance_id' => $server->uuid,
			'name'                 => ! empty( $server->title ) ? $server->title : $name,
			'created'              => gmdate( 'c' ),
			'actions'              => array( 'created' => time() ),
			'run_cmd'              => $run_cmd,
		);

	}

	/**
	 * Return details for a server.
	 *
	 * @param string $id UpCloud server uuid.
	 *
	 * @return array|WP_Error
	 */
	private function call_details( $id ) {

		$server = $this->get_server( $id );
		if ( is_wp_error( $server ) ) {
			return $server;
		}

		return array(
			/* UpCloud does not report the installed OS on the server record. */
			'os'     => '',
			'ip'     => $this->get_ipv4( $server ),
			'ipv6'   => $this->get_ipv6( $server ),
			'name'   => isset( $server->title ) ? (string) $server->title : '',
			'status' => $this->map_state( $server ),
		);

	}

	/**
	 * Report on the operation that an async action started.
	 *
	 * Only ever called by WPCD_SERVER::handle_async_action() on the second pass.
	 * UpCloud acknowledges power changes asynchronously but hands back no operation id,
	 * so completion is inferred from the server state, with the elapsed time as a
	 * backstop so a lost signal cannot wedge the server in "performing ...".
	 *
	 * @param array  $attributes The attributes the action was started with.
	 * @param string $id UpCloud server uuid.
	 *
	 * @return array
	 */
	private function call_status( $attributes, $id ) {

		$server = $this->get_server( $id );

		if ( is_wp_error( $server ) ) {
			$expired = $this->power_action_expired( $id );
			if ( $expired ) {
				delete_transient( $this->power_action_transient( $id ) );
			}
			return array( 'status' => $expired ? 'completed' : 'in-progress' );
		}

		$state = isset( $server->state ) ? strtolower( (string) $server->state ) : '';

		$pending = '';
		if ( ! empty( $attributes['post_id'] ) ) {
			$pending = (string) get_post_meta( (int) $attributes['post_id'], 'wpcd_server_action', true );
		}

		switch ( $pending ) {
			case 'off':
				$completed = ( 'stopped' === $state );
				break;
			case 'on':
			case 'reboot':
				$completed = ( 'started' === $state );
				break;
			default:
				$completed = in_array( $state, array( 'started', 'stopped' ), true );
				break;
		}

		if ( ! $completed ) {
			$completed = $this->power_action_expired( $id );
		}

		if ( $completed ) {
			delete_transient( $this->power_action_transient( $id ) );
		}

		return array(
			'status' => $completed ? 'completed' : 'in-progress',
			'ip'     => $this->get_ipv4( $server ),
		);

	}

	/**
	 * Start, stop or restart a server.
	 *
	 * @param string $method One of reboot, off or on.
	 * @param string $id UpCloud server uuid.
	 *
	 * @return array|WP_Error
	 */
	private function call_power( $method, $id ) {

		$endpoints = array(
			'reboot' => 'restart',
			'off'    => 'stop',
			'on'     => 'start',
		);

		if ( ! isset( $endpoints[ $method ] ) ) {
			return new WP_Error( 'not-supported', __( 'This power operation is not supported by the UpCloud provider.', 'wpcd' ) );
		}

		$payload = $this->execute( 'server/' . rawurlencode( $id ) . '/' . $endpoints[ $method ], 'POST' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		/* Stamp the start so a missing completion signal cannot wedge the server. */
		set_transient( $this->power_action_transient( $id ), time(), 6 * HOUR_IN_SECONDS );

		return array(
			'status'    => 'in-progress',
			'action_id' => $method,
		);

	}

	/**
	 * Destroy a server.
	 *
	 * @param string $id UpCloud server uuid.
	 *
	 * @return array|WP_Error
	 */
	private function call_delete( $id ) {

		$payload = $this->execute( 'server/' . rawurlencode( $id ), 'DELETE' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array( 'status' => 'done' );

	}

	/**
	 * Change the plan of a server.
	 *
	 * UpCloud requires the server to be stopped before its plan changes.  The resize
	 * tab already tells the operator to watch the provider dashboard and restart the
	 * server themselves, so this only issues the request.
	 *
	 * @param array  $attributes Attributes; expects [new_size].
	 * @param string $id UpCloud server uuid.
	 *
	 * @return array|WP_Error
	 */
	private function call_resize( $attributes, $id ) {

		$new_size = isset( $attributes['new_size'] ) ? trim( (string) $attributes['new_size'] ) : '';
		if ( '' === $new_size ) {
			return new WP_Error( 'upcloud-missing-size', __( 'A new plan is required to resize an UpCloud server.', 'wpcd' ) );
		}

		$payload = $this->execute(
			'server/' . rawurlencode( $id ) . '/modify',
			'POST',
			array( 'server' => array( 'plan' => $new_size ) )
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array(
			'status'    => 'in-progress',
			'action_id' => $new_size,
		);

	}

	/**
	 * Register a new ssh key with UpCloud.
	 *
	 * Note what this does and does not do.  The key pair is generated locally by WPCD
	 * before this is called, and the settings screen then stores the public key in
	 * `vpn_upcloud_public_sshkey` and the private key in `vpn_upcloud_sshkey`.  The
	 * public key is what gets installed into every new server, because UpCloud takes
	 * key material inline on the create call rather than referencing a key registry.
	 *
	 * The returned `ssh_key_id` is therefore a local marker rather than a provider id.
	 * It exists so the settings screen can complete its flow and save the key pair.
	 * Registering the key in the UpCloud account as well is best effort: it makes the
	 * key visible in the UpCloud control panel, but a failure there must not discard a
	 * key pair that WPCD has already generated.
	 *
	 * @param array $attributes Attributes; expects [public_key] and [public_key_name].
	 *
	 * @return array|WP_Error
	 */
	private function call_ssh_create( $attributes ) {

		$public_key = isset( $attributes['public_key'] ) ? trim( (string) $attributes['public_key'] ) : '';
		$key_name   = isset( $attributes['public_key_name'] ) ? trim( (string) $attributes['public_key_name'] ) : '';

		if ( '' === $public_key ) {
			return new WP_Error( 'upcloud-missing-public-key', __( 'A public key is required to register an ssh key.', 'wpcd' ) );
		}

		if ( '' === $key_name ) {
			$key_name = 'simplestack-' . gmdate( 'YmdHis' );
		}

		/* Best effort - the key is already usable without this. */
		$this->execute(
			'ssh_key',
			'POST',
			array(
				'ssh_key' => array(
					'title'   => $key_name,
					'ssh_key' => $public_key,
				),
			)
		);

		return array( 'ssh_key_id' => $key_name );

	}

	/**
	 * Check that the credentials can reach the account.
	 *
	 * @return array|WP_Error
	 */
	private function call_test_connection() {

		$payload = $this->execute( 'account', 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		/* The only key the settings screen reads. */
		return array( 'test_status' => ! empty( $payload->account ) );

	}

	/**
	 * Translate a server record into one of the shared state constants.
	 *
	 * @param object $server UpCloud server record.
	 *
	 * @return string
	 */
	private function map_state( $server ) {

		$state = isset( $server->state ) ? strtolower( (string) $server->state ) : '';

		switch ( $state ) {
			case 'started':
				return $this->_STATE_ACTIVE;
			case 'stopped':
				return $this->_STATE_OFF;
			case 'maintenance':
				return $this->_STATE_INPROGRESS;
			case 'error':
				return $this->_STATE_ERRORED;
		}

		return $this->_STATE_UNKNOWN;

	}

	/**
	 * Read one server record.
	 *
	 * @param string $id UpCloud server uuid.
	 *
	 * @return object|WP_Error
	 */
	private function get_server( $id ) {

		$payload = $this->execute( 'server/' . rawurlencode( (string) $id ), 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->server ) ) {
			return new WP_Error( 'upcloud-no-server', __( 'UpCloud did not return a server record.', 'wpcd' ) );
		}

		return $payload->server;

	}

	/**
	 * Find the storage template that matches the requested Ubuntu release.
	 *
	 * @param array $attributes Attributes; expects [initial_os].
	 *
	 * @return array|WP_Error Array with uuid, size and tier.
	 */
	private function resolve_storage_template( $attributes ) {

		$version = $this->get_os_version( $attributes );

		$templates = $this->get_cached_data( 'other' );

		if ( false === $templates ) {

			$payload = $this->execute( 'storage/template', 'GET' );

			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			if ( empty( $payload->storage_templates->storage_template ) ) {
				return new WP_Error( 'upcloud-no-templates', __( 'UpCloud returned no storage templates.', 'wpcd' ) );
			}

			$templates = $payload->storage_templates->storage_template;

			$this->store_cache( $this->get_cache_key( 'other' ), $templates, 'other' );

		}

		$match = null;

		foreach ( $templates as $template ) {

			if ( empty( $template->uuid ) || empty( $template->title ) ) {
				continue;
			}

			if ( 0 !== stripos( trim( (string) $template->title ), 'Ubuntu' ) ) {
				continue;
			}

			if ( false === strpos( (string) $template->title, $version ) ) {
				continue;
			}

			/* Prefer a template UpCloud will actually let us clone from. */
			if ( isset( $template->state ) && 'online' !== strtolower( (string) $template->state ) ) {
				continue;
			}

			$match = $template;
			break;

		}

		if ( null === $match ) {
			/* translators: %s is the Ubuntu release we tried to find, eg: 24.04. */
			return new WP_Error( 'upcloud-no-template', sprintf( __( 'UpCloud does not offer an Ubuntu %s storage template.', 'wpcd' ), $version ) );
		}

		$size = isset( $match->size ) ? (int) $match->size : 25;
		if ( $size < 10 ) {
			$size = 25;
		}

		return array(
			'uuid' => (string) $match->uuid,
			'size' => $size,
			'tier' => isset( $match->tier ) && ! empty( $match->tier ) ? (string) $match->tier : 'maxiops',
		);

	}

	/**
	 * Map a WPCD OS key to the Ubuntu release UpCloud labels its templates with.
	 *
	 * @param array $attributes Attributes; expects [initial_os].
	 *
	 * @return string
	 */
	private function get_os_version( $attributes ) {

		$versions = array(
			'ubuntu1804lts' => '18.04',
			'ubuntu2004lts' => '20.04',
			'ubuntu2204lts' => '22.04',
			'ubuntu2404lts' => '24.04',
			'ubuntu2604lts' => '26.04',
		);

		if ( isset( $attributes['initial_os'] ) && isset( $versions[ $attributes['initial_os'] ] ) ) {
			return $versions[ $attributes['initial_os'] ];
		}

		return self::_DEFAULT_OS_VERSION;

	}

	/**
	 * Extract the public IPv4 address from a server record.
	 *
	 * @param object $server UpCloud server record.
	 *
	 * @return string
	 */
	private function get_ipv4( $server ) {

		$addresses = $this->get_ip_addresses( $server );

		foreach ( $addresses as $address ) {
			if ( empty( $address->address ) ) {
				continue;
			}
			if ( isset( $address->family ) && 'IPv4' !== (string) $address->family ) {
				continue;
			}
			if ( isset( $address->access ) && 'public' !== (string) $address->access ) {
				continue;
			}
			return (string) $address->address;
		}

		/* Mirrors the DigitalOcean provider so callers only have one sentinel to know. */
		return 'error-no-ip-found';

	}

	/**
	 * Extract the IPv6 address from a server record.
	 *
	 * @param object $server UpCloud server record.
	 *
	 * @return string
	 */
	private function get_ipv6( $server ) {

		$addresses = $this->get_ip_addresses( $server );

		foreach ( $addresses as $address ) {
			if ( empty( $address->address ) ) {
				continue;
			}
			if ( isset( $address->family ) && 'IPv6' !== (string) $address->family ) {
				continue;
			}
			return (string) $address->address;
		}

		return 'error-no-ipv6-found';

	}

	/**
	 * Flatten the ip_addresses block of a server record into a list.
	 *
	 * @param object $server UpCloud server record.
	 *
	 * @return array
	 */
	private function get_ip_addresses( $server ) {

		if ( empty( $server->ip_addresses->ip_address ) ) {
			return array();
		}

		$addresses = $server->ip_addresses->ip_address;

		/* UpCloud returns a single object rather than a list when there is only one. */
		if ( is_object( $addresses ) ) {
			return array( $addresses );
		}

		return is_array( $addresses ) ? $addresses : array();

	}

	/**
	 * Reduce a server name to something UpCloud will accept as a hostname.
	 *
	 * @param string $name The requested server name.
	 *
	 * @return string
	 */
	private function normalise_hostname( $name ) {

		$hostname = strtolower( preg_replace( '/[^A-Za-z0-9-]+/', '-', (string) $name ) );
		$hostname = trim( $hostname, '-' );

		if ( '' === $hostname ) {
			$hostname = 'simplestack-' . gmdate( 'YmdHis' );
		}

		return substr( $hostname, 0, 63 );

	}

	/**
	 * Transient name used to remember when a power operation was started.
	 *
	 * @param string $id UpCloud server uuid.
	 *
	 * @return string
	 */
	private function power_action_transient( $id ) {
		return 'wpcd_upcloud_power_started_' . md5( (string) $id );
	}

	/**
	 * Decide whether a power operation has been outstanding for too long.
	 *
	 * @param string $id UpCloud server uuid.
	 *
	 * @return bool
	 */
	private function power_action_expired( $id ) {

		$started = get_transient( $this->power_action_transient( $id ) );

		if ( false === $started ) {
			/*
			 * No stamp at all.  Either it expired (six hours is far longer than any
			 * stop, start or restart takes) or the cache was flushed.  Either way there
			 * is nothing left to wait for, so stop blocking the server.
			 */
			return true;
		}

		return ( time() - (int) $started ) > self::_POWER_ACTION_TIMEOUT;

	}

	/**
	 * Perform one authenticated request and return the decoded body.
	 *
	 * @param string     $endpoint Endpoint relative to self::_URL.
	 * @param string     $action HTTP method.
	 * @param array|null $body Request body, or null for none.
	 *
	 * @return object|WP_Error
	 */
	private function execute( $endpoint, $action = 'GET', $body = null ) {

		$response = $this->request( $endpoint, $action, $body );
		$code     = $this->get_response_code( $response );

		if ( $this->is_success( $code ) ) {
			return $this->decode( $response );
		}

		/* A 429 means UpCloud is rate limiting us - wait out the advertised delay once. */
		if ( 429 === $code ) {
			$delay = 5;
			if ( ! is_wp_error( $response ) ) {
				$retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
				if ( is_numeric( $retry_after ) ) {
					$delay = min( 30, max( 1, (int) $retry_after ) );
				}
			}

			sleep( $delay );

			$response = $this->request( $endpoint, $action, $body );
			$code     = $this->get_response_code( $response );

			if ( $this->is_success( $code ) ) {
				return $this->decode( $response );
			}
		}

		/*
		 * Only the method and the endpoint go into the log.  The request headers hold
		 * the account password and the request body holds the cloud-init payload;
		 * neither belongs in a log file or a support bundle.
		 */
		do_action( 'wpcd_log_error', "UpCloud API $action $endpoint failed with status $code", 'error', __FILE__, __LINE__ );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'upcloud-transport-error', $response->get_error_message() );
		}

		$error_code    = 'upcloud-error';
		$error_message = sprintf(
			/* translators: %d is the HTTP status code returned by UpCloud. */
			__( 'The UpCloud API request failed with HTTP status %d.', 'wpcd' ),
			$code
		);

		$payload = $this->decode( $response );

		if ( is_object( $payload ) ) {
			if ( ! empty( $payload->error->error_code ) ) {
				$error_code = (string) $payload->error->error_code;
			}
			if ( ! empty( $payload->error->error_message ) ) {
				$error_message = (string) $payload->error->error_message;
			}
		}

		return new WP_Error( $error_code, $error_message );

	}

	/**
	 * Build and dispatch one HTTP request.
	 *
	 * @param string     $endpoint Endpoint relative to self::_URL.
	 * @param string     $action HTTP method.
	 * @param array|null $body Request body, or null for none.
	 *
	 * @return array|WP_Error
	 */
	private function request( $endpoint, $action, $body = null ) {

		$args = array(
			'method'  => $action,
			'headers' => array(
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Authorization' => 'Basic ' . base64_encode( $this->user_name . ':' . $this->user_password ),
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		return wp_remote_request( self::_URL . $endpoint, $args );

	}

	/**
	 * Extract the HTTP status code, or 0 when the request never completed.
	 *
	 * @param array|WP_Error $response The response.
	 *
	 * @return int
	 */
	private function get_response_code( $response ) {

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		return (int) wp_remote_retrieve_response_code( $response );

	}

	/**
	 * Whether a status code represents success.
	 *
	 * @param int $code HTTP status code.
	 *
	 * @return bool
	 */
	private function is_success( $code ) {
		return ( $code >= 200 && $code < 300 );
	}

	/**
	 * Decode a response body into an object.
	 *
	 * @param array $response The response.
	 *
	 * @return object
	 */
	private function decode( $response ) {

		$raw     = wp_remote_retrieve_body( $response );
		$payload = json_decode( $raw );

		if ( ! is_object( $payload ) ) {
			/* 204 responses carry no body at all - an empty object is the honest answer. */
			$payload = new \stdClass();
		}

		return $payload;

	}

}
