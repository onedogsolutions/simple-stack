/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * REST client for the `simple-stack/v1` namespace.
 *
 * Every response is wrapped in `{ ok, data, meta }` by the PHP base controller, so
 * this module unwraps the envelope once and never leaks it into components. The
 * nonce comes from the inline bootstrap object the PHP enqueue layer prints.
 */

export class ApiError extends Error {
	constructor( message, { code = 'sstk_error', status = 0 } = {} ) {
		super( message );
		this.name = 'ApiError';
		this.code = code;
		this.status = status;
	}
}

function bootstrap() {
	return typeof window !== 'undefined' && window.simplestackAdmin
		? window.simplestackAdmin
		: {};
}

/**
 * Absolute REST root, always with a trailing slash.
 *
 * @return {string} REST root URL.
 */
export function restRoot() {
	const root = bootstrap().restUrl || '/wp-json/simple-stack/v1/';
	return root.endsWith( '/' ) ? root : `${ root }/`;
}

/**
 * Build a request URL, skipping empty query values so filters stay out of the URL.
 *
 * @param {string} path  Path relative to the namespace root.
 * @param {Object} query Optional query parameters.
 * @return {string} Absolute URL.
 */
function buildUrl( path, query ) {
	const base = restRoot();
	const clean = String( path ).replace( /^\/+/, '' );
	const url = new URL( base + clean, window.location.origin );

	Object.entries( query || {} ).forEach( ( [ key, value ] ) => {
		if ( value === undefined || value === null || value === '' ) {
			return;
		}

		url.searchParams.set( key, String( value ) );
	} );

	return url.toString();
}

/**
 * Perform one request and unwrap the envelope.
 *
 * @param {string} path                 Path relative to the namespace root.
 * @param {Object} options              Options.
 * @param {string} options.method       HTTP method.
 * @param {Object} options.query        Query parameters.
 * @param {Object} options.body         JSON body.
 * @return {Promise<any>} The `data` member of the response envelope.
 */
async function request( path, { method = 'GET', query, body } = {} ) {
	const headers = { Accept: 'application/json' };
	const { nonce } = bootstrap();

	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	let payload;

	if ( body instanceof FormData ) {
		/* The browser must set Content-Type so it can add the multipart boundary. */
		payload = body;
	} else if ( body !== undefined ) {
		headers[ 'Content-Type' ] = 'application/json';
		payload = JSON.stringify( body );
	}

	let response;

	try {
		response = await fetch( buildUrl( path, query ), {
			method,
			headers,
			body: payload,
			credentials: 'same-origin',
		} );
	} catch ( cause ) {
		throw new ApiError( 'Could not reach the server.', {
			code: 'sstk_network_error',
		} );
	}

	let json = null;

	try {
		json = await response.json();
	} catch ( cause ) {
		json = null;
	}

	if ( ! response.ok || ( json && json.ok === false ) ) {
		throw new ApiError(
			( json && json.message ) || `Request failed with status ${ response.status }.`,
			{ code: ( json && json.code ) || 'sstk_http_error', status: response.status }
		);
	}

	if ( json && Object.prototype.hasOwnProperty.call( json, 'data' ) ) {
		return json.data;
	}

	return json;
}

const enc = encodeURIComponent;

/**
 * Build the body for a tab action.
 *
 * Most tabs are JSON. The sFTP and system-user tabs upload a public key file, so when
 * files are present the request becomes multipart and `params` travels as a JSON
 * string (the REST layer accepts either shape).
 *
 * @param {string} action Action slug.
 * @param {Object} params Tab parameters.
 * @param {Object} files  File objects keyed by request field name.
 * @return {Object|FormData} Request body.
 */
function actionBody( action, params, files ) {
	const clean = params || {};
	const uploads = Object.entries( files || {} ).filter( ( [ , file ] ) => file instanceof File );

	if ( uploads.length === 0 ) {
		return { action, params: clean };
	}

	const form = new FormData();
	form.append( 'action', action );
	form.append( 'params', JSON.stringify( clean ) );

	uploads.forEach( ( [ key, file ] ) => form.append( key, file ) );

	return form;
}

export const api = {
	servers: {
		/**
		 * @param {Object} query provider|region|status|search|page|per_page
		 * @return {Promise<Object>} { items, total, page, per_page }
		 */
		list: ( query ) => request( 'servers', { query } ),

		/**
		 * @param {number} id Server post id.
		 * @return {Promise<Object>} Summary + meta + php_version + available.
		 */
		get: ( id ) => request( `servers/${ enc( id ) }` ),

		/**
		 * @param {Object} body name|provider|region|size|os|webserver_type|author_email
		 * @return {Promise<Object>} { server_id, task_id, server }
		 */
		create: ( body ) => request( 'servers', { method: 'POST', body } ),

		/**
		 * @param {number} id Server post id.
		 * @return {Promise<Object>} { server_id, deleted }
		 */
		remove: ( id ) => request( `servers/${ enc( id ) }`, { method: 'DELETE' } ),

		/**
		 * @param {number} id         Server post id.
		 * @param {string} action     Action slug accepted by the server action bridge.
		 * @param {Object} params     Parameters for the tab handler.
		 * @param {Object} files      Optional uploads keyed by request field name.
		 * @return {Promise<Object>} { server_id, action, result }
		 */
		runAction: ( id, action, params, files ) =>
			request( `servers/${ enc( id ) }/action`, {
				method: 'POST',
				body: actionBody( action, params, files ),
			} ),
	},

	sites: {
		/**
		 * @param {Object} query server_id|search|page|per_page
		 * @return {Promise<Object>} { items, total, page, per_page }
		 */
		list: ( query ) => request( 'sites', { query } ),

		/**
		 * @param {number} id Site post id.
		 * @return {Promise<Object>} Summary + meta + action_status
		 */
		get: ( id ) => request( `sites/${ enc( id ) }` ),

		/**
		 * @param {Object} body server_id|domain|admin_user|admin_email|admin_password|wp_version|locale|site_package
		 * @return {Promise<Object>} { app_id, server_id, command, domain }
		 */
		deploy: ( body ) => request( 'sites', { method: 'POST', body } ),

		/**
		 * @param {number} id Site post id.
		 * @return {Promise<Object>} { app_id, deleted }
		 */
		remove: ( id ) => request( `sites/${ enc( id ) }`, { method: 'DELETE' } ),

		/**
		 * @param {number} id     Site post id.
		 * @param {string} action Action slug handled by a site tab.
		 * @param {Object} params Parameters matching the legacy `$_POST['params']` payload.
		 * @param {Object} files  Optional uploads keyed by request field name.
		 * @return {Promise<Object>} { app_id, server_id, action, result }
		 */
		runAction: ( id, action, params, files ) =>
			request( `sites/${ enc( id ) }/action`, {
				method: 'POST',
				body: actionBody( action, params, files ),
			} ),

		/**
		 * @param {number} id Site post id.
		 * @return {Promise<Object>} Read-only security probe result.
		 */
		securityStatus: ( id ) =>
			request( `sites/${ enc( id ) }/security-status`, { method: 'POST' } ),
	},

	tabs: {
		/**
		 * @param {number} id Server post id.
		 * @return {Promise<Object>} { context, id, tabs, fields }
		 */
		forServer: ( id ) => request( `servers/${ enc( id ) }/tabs` ),

		/**
		 * @param {number} id Site post id.
		 * @return {Promise<Object>} { context, id, server_id, tabs, fields }
		 */
		forSite: ( id ) => request( `sites/${ enc( id ) }/tabs` ),
	},

	logs: {
		/**
		 * @param {number} serverId Server post id.
		 * @param {Object} query    type|since|limit
		 * @return {Promise<Object>} { items, cursor, server, type }
		 */
		forServer: ( serverId, query ) =>
			request( `servers/${ enc( serverId ) }/logs`, { query } ),

		/**
		 * @param {Object} query limit|server_id
		 * @return {Promise<Object>} { items, cursor }
		 */
		activity: ( query ) => request( 'activity', { query } ),
	},

	providers: {
		/**
		 * @param {Object} query refresh=1 to bypass cached counts
		 * @return {Promise<Object>} { items, regions }
		 */
		list: ( query ) => request( 'settings/providers', { query } ),

		/**
		 * @param {Object} body slug|api_key|user_name|user_password|ssh_key_id|ssh_key|notes
		 *                      api_key applies to providers whose `uses_api_key` is true;
		 *                      user_name/user_password apply to the rest (UpCloud).
		 * @return {Promise<Object>} Saved provider summary
		 */
		save: ( body ) => request( 'settings/providers', { method: 'POST', body } ),

		/**
		 * @param {string} slug Provider slug.
		 * @return {Promise<Object>} { slug, connected, message }
		 */
		test: ( slug ) =>
			request( `settings/providers/${ enc( slug ) }/test`, { method: 'POST' } ),
	},
};
