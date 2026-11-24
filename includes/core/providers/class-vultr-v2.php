<?php
/** Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Vultr provider (API v2).
 *
 * @package simple-stack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLOUD_PROVIDER_API_VultrV2
 *
 * Implements the CLOUD_PROVIDER_API contract against the Vultr v2 REST API.
 *
 * A few things worth knowing before editing this file:
 *
 * - Authentication is a bearer token.  The token is read from the
 *   `sstk_vultr-v2_apikey` option and is encrypted at rest, so it must pass through
 *   SSTK()->decrypt() before use.
 * - Vultr's power endpoints (reboot, start, halt) answer with 204 No Content and
 *   hand back no operation id. There is therefore nothing to poll, so completion
 *   has to be inferred from the instance's
 *   `power_status` / `server_status`.  See call_power() and call_status().
 * - `user_data` must be base64 encoded by us; the API does not encode it.
 * - The api key must never reach a log line, an exception message or a REST
 *   response.  Every request helper below logs the method and the endpoint only.
 */
class CLOUD_PROVIDER_API_VultrV2 extends CLOUD_PROVIDER_API {

	/**
	 * Base URL for every Vultr v2 endpoint.
	 */
	const _URL = 'https://api.vultr.com/v2/';

	/**
	 * Label of the OS image used when the caller has not requested a specific one.
	 */
	const _DEFAULT_OS_LABEL = 'Ubuntu 24.04 LTS x64';

	/**
	 * Page size requested from Vultr's paginated list endpoints.  Vultr caps this at 500.
	 */
	const _PER_PAGE = 500;

	/**
	 * Maximum number of pages fetched from a single list endpoint.
	 *
	 * Guards against a malformed `meta.links` block turning one API call into an
	 * unbounded loop inside a page request.
	 */
	const _MAX_PAGES = 20;

	/**
	 * How long a power operation may stay outstanding before we stop waiting for a
	 * signal that will never arrive.
	 */
	const _POWER_ACTION_TIMEOUT = 1800;

	/**
	 * The provider api key.
	 *
	 * @var string $api_key
	 */
	private $api_key;

	/**
	 * CLOUD_PROVIDER_API_VultrV2 constructor.
	 *
	 * @param array $args Optional overrides, used when this class is instantiated for a
	 *                    virtual provider defined in the sstk_cloud_provider post type.
	 */
	public function __construct( $args = array() ) {

		parent::__construct();

		/* Set provider name and slug. */
		$this->set_provider( 'Vultr' );
		$this->set_provider_slug( 'vultr-v2' );

		/* Set the underlying provider type name and slug for virtual provider aliases. */
		$this->set_base_provider_name( 'Vultr' );
		$this->set_base_provider_slug( 'vultr-v2' );

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

		/* Where the user gets their api key. */
		$this->set_provider_dashboard_link( 'https://my.vultr.com/settings/#settingsapi' );

		/* Features this provider implements. */
		$this->set_feature_flag( 'ssh_create', true );
		$this->set_feature_flag( 'test_connection', true );
		$this->set_feature_flag( 'snapshots', true );
		$this->set_feature_flag( 'snapshot-delete', true );
		$this->set_feature_flag( 'snapshot-list', true );
		$this->set_feature_flag( 'resize', true );
		$this->set_feature_flag( 'backups', true );
		$this->set_feature_flag( 'enable_backups_on_server_create', true );

		/*
		 * Explicitly off.  SSTK stores a custom image id per OS in
		 * `sstk_{provider}_{os}` options but no code path in this tree reads them
		 * back, so advertising the feature would only produce fields that do
		 * nothing.
		 */
		$this->set_feature_flag( 'custom_images', false );
		$this->set_feature_flag( 'dynamic_tags', false );
		$this->set_feature_flag( 'enable_dynamic_tags_on_server_create', false );

		/* Pull the api key from settings - it is stored encrypted. */
		$this->set_api_key( SSTK()->decrypt( sstk_get_early_option( 'sstk_' . $this->get_provider_slug() . '_apikey' ) ) );

		/* Add the link to the Vultr dashboard next to the api key field. */
		$provider = $this->get_provider_slug();
		add_filter( "sstk_cloud_provider_settings_api_key_label_desc_{$provider}", array( $this, 'set_link_to_provider_dashboard' ) );

	}

	/**
	 * Set function for the api key.
	 *
	 * @param string $the_key the key.
	 *
	 * @return void
	 */
	public function set_api_key( $the_key ) {
		$this->api_key = $the_key;
	}

	/**
	 * Getter function for the api key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Return the credential material used to build cache keys.
	 *
	 * The ancestor class hashes whatever this returns into every transient name, which
	 * means one install can hold caches for two different accounts without collision.
	 * Overriding it here also makes the descendant's cache reachable by the ancestor's
	 * clear_cache() and get_cached_transient_list(), both of which hash this value.
	 *
	 * @return string
	 */
	public function get_secret_key() {
		return $this->api_key;
	}

	/**
	 * Call the API.
	 *
	 * @param string $method The method to invoke.
	 * @param array  $attributes The attributes for the action.
	 *
	 * Recognised attributes:
	 *  [id] / [provider_instance_id] - the Vultr instance id.
	 *  [region]                      - region id, eg: ewr.
	 *  [size]                        - SSTK size key; mapped through settings.
	 *  [size_raw]                    - Vultr plan id, used when [size] is not mapped.
	 *  [name]                        - label applied to the new instance.
	 *  [initial_os]                  - SSTK OS key, eg: ubuntu2404lts.
	 *  [post_id]                     - server post id; used to read the pending action.
	 *  [new_size]                    - Vultr plan id for a resize.
	 *  [snapshot_id]                 - snapshot to delete.
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

		if ( empty( $this->api_key ) ) {
			return false;
		}

		/* Accept the legacy provider_instance_id key as an alias for id. */
		if ( isset( $attributes['provider_instance_id'] ) && ! isset( $attributes['id'] ) ) {
			$attributes['id'] = $attributes['provider_instance_id'];
		}

		$id = isset( $attributes['id'] ) ? trim( (string) $attributes['id'] ) : '';

		/* Methods that need an instance id. */
		$needs_id = array( 'details', 'status', 'reboot', 'off', 'on', 'delete', 'resize', 'snapshot', 'list_snapshots' );

		if ( in_array( $method, $needs_id, true ) && '' === $id ) {
			return new WP_Error( 'vultr-missing-id', __( 'A provider instance id is required for this operation.', 'simple-stack' ) );
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
			case 'snapshot':
				return $this->call_snapshot( $id );
			case 'delete_snapshot':
				return $this->call_delete_snapshot( $attributes );
			case 'list_snapshots':
				return $this->call_list_snapshots( $id );
			case 'list_all_snapshots':
				return $this->call_list_all_snapshots();
			case 'pending_snapshot_status':
				return $this->call_pending_snapshot_status( $attributes );
			case 'ssh_create':
				return $this->call_ssh_create( $attributes );
			case 'test_connection':
				return $this->call_test_connection();
			default:
				return new WP_Error( 'not-supported', __( 'This operation is not supported by the Vultr provider.', 'simple-stack' ) );
		}

	}

	/**
	 * Return the list of Vultr regions.
	 *
	 * @return array|WP_Error Region id => display label.
	 */
	private function call_regions() {

		$regions = $this->fetch_all( 'regions', 'regions' );
		if ( is_wp_error( $regions ) ) {
			return $regions;
		}

		$return = array();
		$prefix = $this->get_region_prefix();

		foreach ( $regions as $region ) {
			if ( empty( $region->id ) ) {
				continue;
			}

			$city    = isset( $region->city ) ? (string) $region->city : '';
			$country = isset( $region->country ) ? (string) $region->country : '';
			$label   = trim( $city . ', ' . $country, ', ' );

			if ( '' === $label ) {
				$label = (string) $region->id;
			}

			$return[ $region->id ] = empty( $prefix ) ? $label : $prefix . ': ' . $label;
		}

		$this->store_cache( $this->get_cache_key( 'regions' ), $return, 'regions' );

		return $return;

	}

	/**
	 * Return the list of Vultr plans.
	 *
	 * The plan id (eg: vc2-1c-1gb) is what the create endpoint expects, so it is used as
	 * the array key.  Tiny plans are filtered out because the stack we install needs a
	 * realistic amount of memory.
	 *
	 * @return array|WP_Error Plan id => display label.
	 */
	private function call_sizes() {

		$plans = $this->fetch_all( 'plans', 'plans' );
		if ( is_wp_error( $plans ) ) {
			return $plans;
		}

		$return = array();

		foreach ( $plans as $plan ) {

			if ( empty( $plan->id ) ) {
				continue;
			}

			if ( isset( $plan->ram ) && ( (int) $plan->ram < 1024 ) ) {
				continue;
			}

			$vcpus     = isset( $plan->vcpu_count ) ? (int) $plan->vcpu_count : 0;
			$ram       = isset( $plan->ram ) ? (int) $plan->ram : 0;
			$disk      = isset( $plan->disk ) ? (int) $plan->disk : 0;
			$bandwidth = isset( $plan->bandwidth ) ? (int) $plan->bandwidth : 0;
			$cost      = isset( $plan->monthly_cost ) ? (string) $plan->monthly_cost : '0';

			/* translators: %1$s is the Vultr plan id; the remaining replacements describe the plan. */
			$return[ $plan->id ] = sprintf( __( '%1$s (%2$d CPUs, %3$d MB RAM, %4$d GB SSD, $%5$s per month, %6$d GB bandwidth)', 'simple-stack' ), $plan->id, $vcpus, $ram, $disk, $cost, $bandwidth );
		}

		ksort( $return );

		$this->store_cache( $this->get_cache_key( 'sizes' ), $return, 'sizes' );

		return $return;

	}

	/**
	 * Return the ssh keys that have been uploaded to Vultr.
	 *
	 * @return array|WP_Error Key id => key name.
	 */
	private function call_keys() {

		$keys = $this->fetch_all( 'ssh-keys', 'ssh_keys' );
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$return = array();

		foreach ( $keys as $key ) {
			if ( empty( $key->id ) ) {
				continue;
			}
			$return[ $key->id ] = isset( $key->name ) ? (string) $key->name : (string) $key->id;
		}

		$this->store_cache( $this->get_cache_key( 'keys' ), $return, 'keys' );

		return $return;

	}

	/**
	 * Create a new instance.
	 *
	 * @param array $attributes Attributes of the server being provisioned.
	 *
	 * @return array|WP_Error
	 */
	private function call_create( $attributes ) {

		$region = isset( $attributes['region'] ) ? trim( (string) $attributes['region'] ) : '';
		if ( '' === $region ) {
			return new WP_Error( 'vultr-missing-region', __( 'A region is required to create a Vultr instance.', 'simple-stack' ) );
		}

		$provider = $this->get_provider_slug();

		/*
		 * SSTK stores the real plan id against a size key (small, medium, ...) in
		 * settings so that products can be mapped to plans without hardcoding ids
		 * in this class.  fall back to size_raw when nothing has been mapped.
		 */
		$plan = '';
		if ( ! empty( $attributes['size'] ) ) {
			$plan = (string) sstk_get_option( 'sstk_' . $provider . '_' . $attributes['size'] );
		}
		if ( empty( $plan ) && ! empty( $attributes['size_raw'] ) ) {
			$plan = trim( (string) $attributes['size_raw'] );
		}
		if ( '' === $plan ) {
			return new WP_Error( 'vultr-missing-size', __( 'A plan is required to create a Vultr instance.', 'simple-stack' ) );
		}

		$os_id = $this->resolve_os_id( $attributes );
		if ( is_wp_error( $os_id ) ) {
			return $os_id;
		}

		/* Startup commands are supplied by the application layer. */
		$run_cmd = apply_filters( 'sstk_cloud_provider_run_cmd', '', $attributes );
		$run_cmd = apply_filters( 'sstk_cloud_provider_run_cmd_' . $provider, $run_cmd, $attributes );

		$name     = isset( $attributes['name'] ) ? (string) $attributes['name'] : '';
		$hostname = $this->normalise_hostname( $name );

		$backups = (bool) sstk_get_option( 'sstk_' . $provider . '_enable_provider_backups_on_server_create' );

		$body = array(
			'region'   => $region,
			'plan'     => $plan,
			'os_id'    => $os_id,
			'label'    => $name,
			'hostname' => $hostname,
			'backups'  => $backups ? 'enabled' : 'disabled',
		);

		/* The public key lives at Vultr; the create endpoint takes a list of key ids. */
		$sshkey_id = (int) sstk_get_option( 'sstk_' . $provider . '_sshkey_id' );
		if ( $sshkey_id > 0 ) {
			$body['sshkey_id'] = array( $sshkey_id );
		}

		if ( '' !== $run_cmd ) {
			$script = "#cloud-config\nruncmd:\n" . $run_cmd;
			/*
			 * Vultr expects user_data to be base64 encoded and does not encode it for
			 * us.  Note that base64 is an encoding, not a protection: never place a
			 * credential in this string.
			 */
			$body['user_data'] = base64_encode( $script ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$payload = $this->execute( 'instances', 'POST', $body );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->instance->id ) ) {
			return new WP_Error( 'vultr-create-failed', __( 'Vultr accepted the request but did not return an instance id.', 'simple-stack' ) );
		}

		$instance = $payload->instance;

		return array(
			'provider_instance_id' => $instance->id,
			'name'                 => ! empty( $instance->label ) ? $instance->label : $name,
			'created'              => ! empty( $instance->date_created ) ? $instance->date_created : gmdate( 'c' ),
			'actions'              => array( 'created' => time() ),
			'run_cmd'              => $run_cmd,
		);

	}

	/**
	 * Return details for an instance.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_details( $id ) {

		$instance = $this->get_instance( $id );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		return array(
			'os'     => isset( $instance->os ) ? (string) $instance->os : '',
			'ip'     => $this->get_ipv4( $instance ),
			'ipv6'   => $this->get_ipv6( $instance ),
			'name'   => isset( $instance->label ) ? (string) $instance->label : '',
			'status' => $this->map_state( $instance ),
		);

	}

	/**
	 * Report on the operation that an async action started.
	 *
	 * This is only ever called by SSTK_SERVER::handle_async_action() on the second
	 * pass.  Vultr returns 204 with no operation id for power changes, so completion
	 * has to be inferred:
	 *
	 * - "off" is done once power_status reads "stopped".
	 * - "on" is done once power_status reads "running".
	 * - "reboot" is done once the instance is running again and Vultr has released its
	 *   "locked" lock.
	 *
	 * A power operation that never produces the expected signal would otherwise leave
	 * the server stuck in "performing ..." forever, so the elapsed time is used as a
	 * backstop.  The start time is stamped by call_power().
	 *
	 * @param array  $attributes The attributes the action was started with.
	 * @param string $id Vultr instance id.
	 *
	 * @return array
	 */
	private function call_status( $attributes, $id ) {

		$instance = $this->get_instance( $id );

		if ( is_wp_error( $instance ) ) {
			/*
			 * The instance cannot be read right now.  Do not let that alone wedge the
			 * server - fall through to the elapsed-time backstop instead.
			 */
			$expired = $this->power_action_expired( $id );
			if ( $expired ) {
				delete_transient( $this->power_action_transient( $id ) );
			}
			return array( 'status' => $expired ? 'completed' : 'in-progress' );
		}

		$state  = isset( $instance->status ) ? strtolower( (string) $instance->status ) : '';
		$power  = isset( $instance->power_status ) ? strtolower( (string) $instance->power_status ) : '';
		$server = isset( $instance->server_status ) ? strtolower( (string) $instance->server_status ) : '';

		$pending = '';
		if ( ! empty( $attributes['post_id'] ) ) {
			$pending = (string) get_post_meta( (int) $attributes['post_id'], 'sstk_server_action', true );
		}

		switch ( $pending ) {
			case 'off':
				$completed = ( 'stopped' === $power );
				break;
			case 'on':
				$completed = ( 'running' === $power );
				break;
			case 'reboot':
				$completed = ( 'running' === $power && 'locked' !== $server );
				break;
			default:
				$completed = in_array( $state, array( 'active' ), true ) && 'stopped' !== $power;
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
			'ip'     => $this->get_ipv4( $instance ),
		);

	}

	/**
	 * Start, stop or reboot an instance.
	 *
	 * @param string $method One of reboot, off or on.
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_power( $method, $id ) {

		$endpoints = array(
			'reboot' => 'reboot',
			'off'    => 'halt',
			'on'     => 'start',
		);

		if ( ! isset( $endpoints[ $method ] ) ) {
			return new WP_Error( 'not-supported', __( 'This power operation is not supported by the Vultr provider.', 'simple-stack' ) );
		}

		$payload = $this->execute( 'instances/' . rawurlencode( $id ) . '/' . $endpoints[ $method ], 'POST' );
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
	 * Destroy an instance.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_delete( $id ) {

		$payload = $this->execute( 'instances/' . rawurlencode( $id ), 'DELETE' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array( 'status' => 'done' );

	}

	/**
	 * Change the plan of an instance.
	 *
	 * Vultr resizes in place for upgrades; downgrades require the instance to be
	 * powered off first.  The resize tab already tells the operator to watch the
	 * Vultr dashboard and restart the server themselves, so this method only issues
	 * the request and reports whether Vultr accepted it.
	 *
	 * @param array  $attributes Attributes; expects [new_size].
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_resize( $attributes, $id ) {

		$new_size = isset( $attributes['new_size'] ) ? trim( (string) $attributes['new_size'] ) : '';
		if ( '' === $new_size ) {
			return new WP_Error( 'vultr-missing-size', __( 'A new plan is required to resize a Vultr instance.', 'simple-stack' ) );
		}

		$payload = $this->execute( 'instances/' . rawurlencode( $id ), 'PATCH', array( 'plan' => $new_size ) );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array(
			'status'    => 'in-progress',
			'action_id' => $new_size,
		);

	}

	/**
	 * Request a snapshot of an instance.
	 *
	 * Vultr returns the final snapshot id immediately, so the caller can treat this
	 * id as authoritative rather than looking it up later.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_snapshot( $id ) {

		/* translators: %s is the instance id being snapshotted. */
		$description = sprintf( __( 'Simple Stack snapshot of instance %s', 'simple-stack' ), $id );

		$payload = $this->execute( 'instances/' . rawurlencode( $id ) . '/snapshot', 'POST', array( 'description' => $description ) );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->snapshot->id ) ) {
			return array( 'status' => 'fail' );
		}

		return array(
			'id'               => $id,
			'snapshot-id'      => $payload->snapshot->id,
			'snapshot-id-type' => 'final',
			'provider_status'  => isset( $payload->snapshot->status ) ? (string) $payload->snapshot->status : 'pending',
			'status'           => 'success',
		);

	}

	/**
	 * Delete a snapshot.
	 *
	 * @param array $attributes Attributes; expects [snapshot_id].
	 *
	 * @return array|WP_Error
	 */
	private function call_delete_snapshot( $attributes ) {

		$snapshot_id = isset( $attributes['snapshot_id'] ) ? trim( (string) $attributes['snapshot_id'] ) : '';
		if ( '' === $snapshot_id ) {
			return new WP_Error( 'vultr-missing-snapshot-id', __( 'A snapshot id is required to delete a snapshot.', 'simple-stack' ) );
		}

		$payload = $this->execute( 'snapshots/' . rawurlencode( $snapshot_id ), 'DELETE' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array( 'status' => 'success' );

	}

	/**
	 * List the snapshots belonging to one instance.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return array|WP_Error
	 */
	private function call_list_snapshots( $id ) {

		$snapshots = $this->fetch_all( 'instances/' . rawurlencode( $id ) . '/snapshots', 'snapshots' );
		if ( is_wp_error( $snapshots ) ) {
			return $snapshots;
		}

		return $this->format_snapshot_list( $snapshots, $id );

	}

	/**
	 * List every snapshot in the account.
	 *
	 * @return array|WP_Error
	 */
	private function call_list_all_snapshots() {

		$snapshots = $this->fetch_all( 'snapshots', 'snapshots' );
		if ( is_wp_error( $snapshots ) ) {
			return $snapshots;
		}

		return $this->format_snapshot_list( $snapshots, '' );

	}

	/**
	 * Read the status of a snapshot that is still being written.
	 *
	 * @param array $attributes Attributes; expects [pending_snapshot_id] or [snapshot_id].
	 *
	 * @return array|WP_Error
	 */
	private function call_pending_snapshot_status( $attributes ) {

		$snapshot_id = '';
		if ( ! empty( $attributes['pending_snapshot_id'] ) ) {
			$snapshot_id = trim( (string) $attributes['pending_snapshot_id'] );
		}
		if ( '' === $snapshot_id && ! empty( $attributes['snapshot_id'] ) ) {
			$snapshot_id = trim( (string) $attributes['snapshot_id'] );
		}

		if ( '' === $snapshot_id ) {
			return new WP_Error( 'vultr-missing-snapshot-id', __( 'A snapshot id is required to read a snapshot status.', 'simple-stack' ) );
		}

		$payload = $this->execute( 'snapshots/' . rawurlencode( $snapshot_id ), 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$status = isset( $payload->snapshot->status ) ? strtolower( (string) $payload->snapshot->status ) : '';

		return array(
			'status'      => ( 'complete' === $status ) ? 'complete' : $status,
			'snapshot_id' => $snapshot_id,
		);

	}

	/**
	 * Register a new ssh key with Vultr.
	 *
	 * @param array $attributes Attributes; expects [public_key] and [public_key_name].
	 *
	 * @return array|WP_Error
	 */
	private function call_ssh_create( $attributes ) {

		$public_key = isset( $attributes['public_key'] ) ? trim( (string) $attributes['public_key'] ) : '';
		$key_name   = isset( $attributes['public_key_name'] ) ? trim( (string) $attributes['public_key_name'] ) : '';

		if ( '' === $public_key ) {
			return new WP_Error( 'vultr-missing-public-key', __( 'A public key is required to register an ssh key.', 'simple-stack' ) );
		}

		if ( '' === $key_name ) {
			$key_name = 'simplestack-' . gmdate( 'YmdHis' );
		}

		$payload = $this->execute(
			'ssh-keys',
			'POST',
			array(
				'name'    => $key_name,
				'ssh_key' => $public_key,
			)
		);

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->ssh_key->id ) ) {
			return new WP_Error( 'vultr-ssh-create-failed', __( 'Vultr accepted the key but did not return an id for it.', 'simple-stack' ) );
		}

		return array( 'ssh_key_id' => $payload->ssh_key->id );

	}

	/**
	 * Check that the api key can reach the account.
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
	 * Translate an instance record into one of the shared state constants.
	 *
	 * @param object $instance Vultr instance record.
	 *
	 * @return string
	 */
	private function map_state( $instance ) {

		$state  = isset( $instance->status ) ? strtolower( (string) $instance->status ) : '';
		$power  = isset( $instance->power_status ) ? strtolower( (string) $instance->power_status ) : '';
		$server = isset( $instance->server_status ) ? strtolower( (string) $instance->server_status ) : '';

		switch ( $state ) {
			case 'active':
				/*
				 * A halted instance still reports status "active"; power_status is the
				 * only thing that distinguishes "running" from "off".
				 */
				return ( 'stopped' === $power ) ? $this->_STATE_OFF : $this->_STATE_ACTIVE;
			case 'pending':
			case 'installing':
			case 'resizing':
			case 'suspended':
				return $this->_STATE_INPROGRESS;
		}

		switch ( $server ) {
			case 'locked':
				return $this->_STATE_INPROGRESS;
			case 'error':
				return $this->_STATE_ERRORED;
		}

		return $this->_STATE_UNKNOWN;

	}

	/**
	 * Read one instance record, retrying once on a transient failure.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return object|WP_Error
	 */
	private function get_instance( $id ) {

		$payload = $this->execute( 'instances/' . rawurlencode( (string) $id ), 'GET' );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		if ( empty( $payload->instance ) ) {
			return new WP_Error( 'vultr-no-instance', __( 'Vultr did not return an instance record.', 'simple-stack' ) );
		}

		return $payload->instance;

	}

	/**
	 * Resolve the SSTK OS key into a Vultr os_id.
	 *
	 * @param array $attributes Attributes; expects [initial_os].
	 *
	 * @return int|WP_Error
	 */
	private function resolve_os_id( $attributes ) {

		$label = $this->get_os_label( $attributes );

		$images = $this->fetch_all( 'os', 'os' );
		if ( is_wp_error( $images ) ) {
			return $images;
		}

		foreach ( $images as $image ) {
			if ( empty( $image->id ) || empty( $image->name ) ) {
				continue;
			}
			if ( 0 === strcasecmp( trim( (string) $image->name ), $label ) ) {
				return (int) $image->id;
			}
		}

		/*
		 * No exact match.  Vultr renames images over time (LTS point releases move the
		 * label around), so fall back to the newest image whose name starts with the
		 * requested family rather than failing a provisioning run outright.
		 */
		$family   = strtok( $label, ' ' );
		$fallback = 0;
		foreach ( $images as $image ) {
			if ( empty( $image->id ) || empty( $image->name ) ) {
				continue;
			}
			if ( 0 !== stripos( trim( (string) $image->name ), $family ) ) {
				continue;
			}
			/* Prefer the highest id - Vultr allocates ids in release order. */
			$fallback = max( $fallback, (int) $image->id );
		}

		if ( $fallback > 0 ) {
			return $fallback;
		}

		/* translators: %s is the OS label we tried to find at the provider. */
		return new WP_Error( 'vultr-no-os', sprintf( __( 'Vultr does not offer an image named "%s".', 'simple-stack' ), $label ) );

	}

	/**
	 * Map a SSTK OS key to the label Vultr uses for the same image.
	 *
	 * @param array $attributes Attributes; expects [initial_os].
	 *
	 * @return string
	 */
	private function get_os_label( $attributes ) {

		$labels = array(
			'ubuntu2404lts' => 'Ubuntu 24.04 LTS x64',
			'ubuntu2604lts' => 'Ubuntu 26.04 LTS x64',
		);

		if ( isset( $attributes['initial_os'] ) && isset( $labels[ $attributes['initial_os'] ] ) ) {
			return $labels[ $attributes['initial_os'] ];
		}

		return self::_DEFAULT_OS_LABEL;

	}

	/**
	 * Extract the public IPv4 address from an instance record.
	 *
	 * @param object $instance Vultr instance record.
	 *
	 * @return string
	 */
	private function get_ipv4( $instance ) {

		$ip = isset( $instance->main_ip ) ? trim( (string) $instance->main_ip ) : '';

		if ( '' === $ip || '0.0.0.0' === $ip ) {
			/* Use the shared missing-address sentinel. */
			return 'error-no-ip-found';
		}

		return $ip;

	}

	/**
	 * Extract the IPv6 address from an instance record.
	 *
	 * @param object $instance Vultr instance record.
	 *
	 * @return string
	 */
	private function get_ipv6( $instance ) {

		$ip = isset( $instance->v6_main_ip ) ? trim( (string) $instance->v6_main_ip ) : '';

		if ( '' === $ip ) {
			return 'error-no-ipv6-found';
		}

		return $ip;

	}

	/**
	 * Reduce a server name to something Vultr will accept as a hostname.
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
	 * Shape a list of Vultr snapshots into the array the caller expects.
	 *
	 * @param array  $snapshots Raw snapshot records.
	 * @param string $resource_id Instance id the snapshots came from, or '' for the account.
	 *
	 * @return array
	 */
	private function format_snapshot_list( $snapshots, $resource_id ) {

		$return = array();

		foreach ( $snapshots as $snapshot ) {
			$return['snapshots'][] = array(
				'id'            => isset( $snapshot->id ) ? (string) $snapshot->id : '',
				'name'          => isset( $snapshot->description ) ? (string) $snapshot->description : '',
				'resource_id'   => (string) $resource_id,
				'resource_size' => isset( $snapshot->size ) ? (int) $snapshot->size : 0,
				'resource_type' => 'snapshot',
				'tags'          => array(),
				'status'        => isset( $snapshot->status ) ? (string) $snapshot->status : '',
			);
		}

		$return['snapshot_count'] = isset( $return['snapshots'] ) ? count( $return['snapshots'] ) : 0;

		if ( empty( $return['snapshots'] ) ) {
			$return = array(
				'snapshot_count' => 0,
				'snapshots'      => array(),
			);
		}

		return $return;

	}

	/**
	 * Transient name used to remember when a power operation was started.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return string
	 */
	private function power_action_transient( $id ) {
		return 'sstk_vultr_power_started_' . md5( (string) $id );
	}

	/**
	 * Decide whether a power operation has been outstanding for too long.
	 *
	 * @param string $id Vultr instance id.
	 *
	 * @return bool
	 */
	private function power_action_expired( $id ) {

		$started = get_transient( $this->power_action_transient( $id ) );

		if ( false === $started ) {
			/*
			 * No stamp at all.  Either it expired (six hours is far longer than any
			 * halt, start or reboot takes) or the cache was flushed.  Either way there
			 * is nothing left to wait for, so stop blocking the server.
			 */
			return true;
		}

		return ( time() - (int) $started ) > self::_POWER_ACTION_TIMEOUT;

	}

	/**
	 * Fetch every page of a paginated list endpoint.
	 *
	 * @param string $endpoint Endpoint relative to self::_URL, eg: 'plans'.
	 * @param string $key The property holding the list in the response body.
	 *
	 * @return array|WP_Error
	 */
	private function fetch_all( $endpoint, $key ) {

		$items    = array();
		$cursor   = '';
		$requests = 0;

		do {
			$url = $endpoint . '?per_page=' . self::_PER_PAGE;

			if ( '' !== $cursor ) {
				$url .= '&cursor=' . rawurlencode( $cursor );
			}

			$body = $this->execute( $url, 'GET' );

			if ( is_wp_error( $body ) ) {
				return $body;
			}

			if ( ! empty( $body->{$key} ) && is_array( $body->{$key} ) ) {
				$items = array_merge( $items, $body->{$key} );
			}

			$cursor = $this->get_next_cursor( $body );
			++$requests;

		} while ( '' !== $cursor && $requests < self::_MAX_PAGES );

		return $items;

	}

	/**
	 * Pull the pagination cursor out of a Vultr list response.
	 *
	 * @param object $body Decoded response body.
	 *
	 * @return string Empty string when this was the last page.
	 */
	private function get_next_cursor( $body ) {

		if ( empty( $body->meta->links->next ) ) {
			return '';
		}

		$query = (string) wp_parse_url( (string) $body->meta->links->next, PHP_URL_QUERY );
		if ( '' === $query ) {
			return '';
		}

		$args = array();
		parse_str( $query, $args );

		return isset( $args['cursor'] ) ? (string) $args['cursor'] : '';

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

		/* A 429 means Vultr is rate limiting us - wait out the advertised delay once. */
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
		 * Only the method and the endpoint go into the log.  The request body and the
		 * headers both carry either the bearer token or the cloud-init payload and
		 * neither belongs in a log file or in a support bundle.
		 */
		do_action( 'sstk_log_error', "Vultr API $action $endpoint failed with status $code", 'error', __FILE__, __LINE__ );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'vultr-transport-error', $response->get_error_message() );
		}

		$error_code    = 'vultr-error';
		$error_message = sprintf(
			/* translators: %d is the HTTP status code returned by Vultr. */
			__( 'The Vultr API request failed with HTTP status %d.', 'simple-stack' ),
			$code
		);

		$payload = $this->decode( $response );

		if ( is_object( $payload ) ) {
			if ( ! empty( $payload->error ) ) {
				$error_code = (string) $payload->error;
			}
			if ( ! empty( $payload->message ) ) {
				$error_message = (string) $payload->message;
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
				'Authorization' => 'Bearer ' . $this->api_key,
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
