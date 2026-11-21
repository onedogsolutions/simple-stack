/**
 * SimpleStack admin client entry point.
 *
 * Mounts into the container the PHP layer prints on SimpleStack screens. If the
 * container is absent (a non-SimpleStack screen) this does nothing, so wp-admin is
 * never touched by a stray mount.
 */

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import App from './App.jsx';
import './styles/index.css';

const container = document.getElementById( 'simplestack-admin' );

/**
 * Seed the hash from the screen the PHP layer rendered.
 *
 * Each SimpleStack submenu (Servers, Sites, Providers, Activity) is a separate
 * `admin.php?page=` request that prints its own `data-route`. Clicking a submenu is a
 * full page load with no hash, so without this every submenu would land on `/servers`.
 * An existing hash always wins: that is a deep link or a reload of the current screen.
 */
function seedRoute( config ) {
	const current = window.location.hash.replace( /^#/, '' ).trim();

	if ( current !== '' ) {
		return;
	}

	const route = String( container.dataset.route || config.route || '/servers' );
	window.location.hash = route.startsWith( '/' ) ? route : `/${ route }`;
}

if ( container ) {
	const config = window.simplestackAdmin || {};

	seedRoute( config );

	createRoot( container ).render(
		<StrictMode>
			<App config={ config } />
		</StrictMode>
	);
}
