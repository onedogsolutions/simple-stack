/**
 * Minimal hash router.
 *
 * wp-admin owns the URL bar, so the app cannot use pushState without fighting the
 * admin's own history handling. Hash routes keep deep links working
 * (`admin.php?page=simplestack#/servers/42`) and survive a page reload.
 */

import { useEffect, useState } from 'react';

/**
 * Read the current route path.
 *
 * @return {string} Path such as `/servers/42`, always starting with a slash.
 */
export function currentPath() {
	const raw = typeof window === 'undefined' ? '' : window.location.hash.replace( /^#/, '' );
	const path = raw.split( '?' )[ 0 ].trim() || '/';

	return path.startsWith( '/' ) ? path : `/${ path }`;
}

/**
 * Navigate to a hash route.
 *
 * @param {string} path Target path.
 * @return {void}
 */
export function navigate( path ) {
	const target = path.startsWith( '/' ) ? path : `/${ path }`;
	window.location.hash = target;
}

/**
 * Subscribe to hash changes.
 *
 * @return {string} Current path.
 */
export function useRoute() {
	const [ path, setPath ] = useState( currentPath );

	useEffect( () => {
		const onChange = () => setPath( currentPath() );
		window.addEventListener( 'hashchange', onChange );

		return () => window.removeEventListener( 'hashchange', onChange );
	}, [] );

	return path;
}

/**
 * Split a route pattern into matched segments.
 *
 * @param {string}   path    Current path.
 * @param {string[]} pattern Pattern segments; `:name` captures a value.
 * @return {Object|null} Captured params, or null when the pattern does not match.
 */
export function matchRoute( path, pattern ) {
	const actual = path.split( '/' ).filter( Boolean );
	const expected = pattern.split( '/' ).filter( Boolean );

	if ( actual.length !== expected.length ) {
		return null;
	}

	const params = {};

	for ( let index = 0; index < expected.length; index += 1 ) {
		const segment = expected[ index ];

		if ( segment.startsWith( ':' ) ) {
			params[ segment.slice( 1 ) ] = decodeURIComponent( actual[ index ] );
			continue;
		}

		if ( segment !== actual[ index ] ) {
			return null;
		}
	}

	return params;
}
