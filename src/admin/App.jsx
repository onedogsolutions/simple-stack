/**
 * Route table.
 *
 * wp-admin owns the real URL, so the app keeps its own state in the hash
 * (`admin.php?page=simplestack#/servers/42`). `useRoute()` returns the current path and
 * `matchRoute()` turns a pattern with `:params` into captured values.
 *
 * Anything unrecognised falls back to the server list rather than a blank screen: a
 * stale bookmark should always land somewhere useful.
 */

import { ToastProvider } from './components/Toast.jsx';
import { matchRoute, useRoute } from './lib/router.js';

import ServersList from './routes/ServersList.jsx';
import ServerDetail from './routes/ServerDetail.jsx';
import ProvisionServer from './routes/ProvisionServer.jsx';
import SitesList from './routes/SitesList.jsx';
import SiteDetail from './routes/SiteDetail.jsx';
import DeploySite from './routes/DeploySite.jsx';
import ProviderSettings from './routes/ProviderSettings.jsx';
import SecurityStatus from './routes/SecurityStatus.jsx';
import ActivityLog from './routes/ActivityLog.jsx';

const ROUTES = [
	[ '/servers', () => <ServersList /> ],
	[ '/servers/new', () => <ProvisionServer /> ],
	[ '/servers/:id', ( params ) => <ServerDetail id={ Number( params.id ) } /> ],
	[ '/sites', () => <SitesList /> ],
	[ '/sites/new', () => <DeploySite /> ],
	[ '/sites/:id', ( params ) => <SiteDetail id={ Number( params.id ) } /> ],
	[ '/sites/:id/security', ( params ) => <SecurityStatus id={ Number( params.id ) } /> ],
	[ '/settings/providers', () => <ProviderSettings /> ],
	[ '/providers', () => <ProviderSettings /> ],
	[ '/activity', () => <ActivityLog /> ],
];

/**
 * Resolve a path to an element.
 *
 * @param {string} path Current hash path.
 * @return {JSX.Element} Matched route, or the server list when nothing matches.
 */
function render( path ) {
	for ( const [ pattern, build ] of ROUTES ) {
		const params = matchRoute( path, pattern );

		if ( params ) {
			return build( params );
		}
	}

	return <ServersList />;
}

/**
 * @param {Object} props
 * @param {Object} props.config Bootstrap object printed by the PHP enqueue layer.
 * @return {JSX.Element} Application root.
 */
export default function App( { config = {} } ) {
	const path = useRoute();

	return (
		<ToastProvider>
			<div data-simplestack-version={ config.version || '' }>{ render( path ) }</div>
		</ToastProvider>
	);
}
