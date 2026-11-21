/**
 * Presentation formatters shared by every route.
 */

/**
 * WordPress stores post dates as `YYYY-MM-DD HH:MM:SS` in GMT with no zone marker,
 * which `Date` parses as local time. Convert explicitly.
 *
 * @param {string} gmt Date string from `post_date_gmt`.
 * @return {Date|null} Parsed date, or null when unparseable.
 */
export function parseGmt( gmt ) {
	if ( ! gmt || typeof gmt !== 'string' ) {
		return null;
	}

	const isoTry = gmt.includes( 'T' ) ? gmt : `${ gmt.replace( ' ', 'T' ) }Z`;
	const date = new Date( isoTry );

	return Number.isNaN( date.getTime() ) ? null : date;
}

/**
 * @param {string} gmt Date string from `post_date_gmt`.
 * @return {string} Localised absolute date, or an em dash.
 */
export function formatDate( gmt ) {
	const date = parseGmt( gmt );

	if ( ! date ) {
		return '—';
	}

	return date.toLocaleString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

const RELATIVE_STEPS = [
	[ 60, 'second', 1 ],
	[ 3600, 'minute', 60 ],
	[ 86400, 'hour', 3600 ],
	[ 604800, 'day', 86400 ],
	[ 2629800, 'week', 604800 ],
	[ 31557600, 'month', 2629800 ],
	[ Infinity, 'year', 31557600 ],
];

/**
 * @param {string} gmt Date string from `post_date_gmt`.
 * @return {string} Human relative time such as "3 hours ago".
 */
export function relativeTime( gmt ) {
	const date = parseGmt( gmt );

	if ( ! date ) {
		return '—';
	}

	const seconds = Math.round( ( Date.now() - date.getTime() ) / 1000 );

	if ( seconds < 45 ) {
		return 'just now';
	}

	for ( const [ ceiling, unit, divisor ] of RELATIVE_STEPS ) {
		if ( seconds < ceiling ) {
			const value = Math.max( 1, Math.round( seconds / divisor ) );
			return `${ value } ${ unit }${ value === 1 ? '' : 's' } ago`;
		}
	}

	return formatDate( gmt );
}

/**
 * @param {number} bytes Byte count.
 * @return {string} Human readable size.
 */
export function formatBytes( bytes ) {
	const value = Number( bytes );

	if ( ! Number.isFinite( value ) || value <= 0 ) {
		return '0 B';
	}

	const units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
	const exponent = Math.min( units.length - 1, Math.floor( Math.log( value ) / Math.log( 1024 ) ) );
	const scaled = value / 1024 ** exponent;

	return `${ scaled >= 10 || exponent === 0 ? Math.round( scaled ) : scaled.toFixed( 1 ) } ${ units[ exponent ] }`;
}

/**
 * Turn `vultr-v2` or `wp_linux_cron` into `Vultr V2` / `Wp Linux Cron`.
 *
 * @param {string} value Raw slug.
 * @return {string} Title cased label.
 */
export function titleCase( value ) {
	return String( value || '' )
		.replace( /[-_]+/g, ' ' )
		.trim()
		.replace( /\b\w/g, ( character ) => character.toUpperCase() );
}

const STATE_TONES = {
	on: 'positive',
	off: 'neutral',
	active: 'positive',
	inactive: 'neutral',
	running: 'positive',
	started: 'positive',
	restarting: 'warning',
	reinstalling: 'warning',
	pending: 'warning',
	queued: 'warning',
	notready: 'warning',
	'not ready': 'warning',
	stopped: 'negative',
	offline: 'negative',
	deleted: 'negative',
	error: 'negative',
	failed: 'negative',
	terminated: 'negative',
	available: 'positive',
	unavailable: 'negative',
	healthy: 'positive',
	unhealthy: 'negative',
};

/**
 * Map a server/site state string onto a badge tone.
 *
 * @param {string} state Recorded state.
 * @return {string} One of positive | warning | negative | neutral.
 */
export function stateTone( state ) {
	const key = String( state || '' ).trim().toLowerCase();
	return STATE_TONES[ key ] || 'neutral';
}

/**
 * Render an unknown scalar safely.
 *
 * @param {*} value Any value.
 * @return {string} Display string.
 */
export function display( value ) {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}

	if ( typeof value === 'boolean' ) {
		return value ? 'Yes' : 'No';
	}

	if ( typeof value === 'object' ) {
		return JSON.stringify( value );
	}

	return String( value );
}
