/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Server detail.
 *
 * Replaces the Meta Box tabbed edit screen for `sstk_app_server`. The overview panel is
 * fed by `GET /servers/{id}` because the legacy "top of page" summary came from a
 * private method that the REST layer does not expose; the tabbed body is the same
 * `get_tabnames` / `get_tabs` schema the legacy renderer consumed.
 */

import { useCallback, useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import LogDrawer from '../components/LogDrawer.jsx';
import TabRenderer from '../components/TabRenderer.jsx';
import { ConfirmDialog } from '../components/Modal.jsx';
import { Badge, Card, ErrorPanel, Skeleton } from '../components/Card.jsx';
import { useToast } from '../components/Toast.jsx';
import { api } from '../lib/api.js';
import { display, formatDate, relativeTime, stateTone, titleCase } from '../lib/format.js';
import { navigate } from '../lib/router.js';

/**
 * @param {Object} props
 * @param {number} props.id Server post id.
 * @return {JSX.Element} Server detail route.
 */
export default function ServerDetail( { id } ) {
	const toast = useToast();

	const [ server, setServer ] = useState( null );
	const [ schema, setSchema ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ logOpen, setLogOpen ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );

	const load = useCallback( async () => {
		setLoading( true );

		try {
			const [ detail, tabs ] = await Promise.all( [ api.servers.get( id ), api.tabs.forServer( id ) ] );
			setServer( detail );
			setSchema( tabs );
			setError( null );
		} catch ( cause ) {
			setError( cause );
		} finally {
			setLoading( false );
		}
	}, [ id ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const runAction = async ( action, params, field, files ) => {
		try {
			const result = await api.servers.runAction( id, action, params, files );
			toast.push( `“${ titleCase( action ) }” dispatched.`, 'success' );

			if ( result && result.result && result.result.refresh === 'yes' ) {
				load();
			}

			return result;
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
			throw cause;
		}
	};

	const remove = async () => {
		setDeleting( true );

		try {
			await api.servers.remove( id );
			toast.push( 'Server deletion queued.', 'success' );
			navigate( '/servers' );
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
		} finally {
			setDeleting( false );
			setConfirmDelete( false );
		}
	};

	const crumbs = [
		{ label: 'Servers', path: '/servers' },
		{ label: ( server && server.name ) || `Server ${ id }` },
	];

	if ( loading && ! server ) {
		return (
			<AppShell path={ `/servers/${ id }` } title="Loading server…" breadcrumbs={ crumbs }>
				<Card>
					<Skeleton rows={ 8 } />
				</Card>
			</AppShell>
		);
	}

	if ( error && ! server ) {
		return (
			<AppShell path={ `/servers/${ id }` } title="Server unavailable" breadcrumbs={ crumbs }>
				<ErrorPanel error={ error } onRetry={ load } />
			</AppShell>
		);
	}

	const meta = ( server && server.meta ) || {};

	return (
		<AppShell
			path={ `/servers/${ id }` }
			title={ server.name || `Server ${ id }` }
			subtitle={ `${ server.provider_label || titleCase( server.provider ) } · ${ server.region || 'unknown region' }` }
			breadcrumbs={ crumbs }
			action={
				<div className="flex flex-wrap gap-2">
					<Button onClick={ () => setLogOpen( ( current ) => ! current ) }>
						{ logOpen ? 'Hide log' : 'Show live log' }
					</Button>
					<Button variant="danger" onClick={ () => setConfirmDelete( true ) }>
						Delete
					</Button>
				</div>
			}
		>
			<div className="space-y-4">
				<Card title="Overview">
					<dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
						<Detail label="State">
							<div className="flex flex-wrap gap-1.5">
								<Badge tone={ stateTone( server.state ) }>{ server.state || 'unknown' }</Badge>
								{ server.health ? <Badge tone={ stateTone( server.health ) }>{ server.health }</Badge> : null }
							</div>
						</Detail>
						<Detail label="IPv4">{ display( server.ipv4 ) }</Detail>
						<Detail label="IPv6">{ display( server.ipv6 ) }</Detail>
						<Detail label="Instance">{ display( server.instance_id ) }</Detail>
						<Detail label="Size">{ display( server.size ) }</Detail>
						<Detail label="OS">{ display( server.os ) }</Detail>
						<Detail label="Web server">{ display( server.webserver_type ) }</Detail>
						<Detail label="PHP">{ display( server.php_version ) }</Detail>
						<Detail label="Sites">{ display( server.app_count ) }</Detail>
						<Detail label="Created">{ formatDate( server.created ) }</Detail>
						<Detail label="Commands">
							<Badge tone={ server.available ? 'positive' : 'warning' }>
								{ server.available ? 'available' : 'unavailable' }
							</Badge>
						</Detail>
						<Detail label="Last check">{ relativeTime( meta.last_updated || '' ) }</Detail>
					</dl>
				</Card>

				{ server.app_count > 0 ? (
					<Card
						title="Sites on this server"
						action={
							<Button size="sm" onClick={ () => navigate( `/sites?server=${ server.id }` ) }>
								View sites
							</Button>
						}
					>
						<p className="text-sm text-ink-500">
							{ server.app_count } WordPress site{ server.app_count === 1 ? '' : 's' } hosted here.
							Open the Sites section to manage them.
						</p>
					</Card>
				) : null }

				<LogDrawer
					serverId={ server.id }
					open={ logOpen }
					onClose={ () => setLogOpen( false ) }
					title={ `Log — ${ server.name || id }` }
				/>

				{ schema ? (
					<TabRenderer
						tabs={ schema.tabs }
						fields={ schema.fields }
						onAction={ runAction }
						onShowLog={ () => setLogOpen( true ) }
						disabledReason={
							server.available
								? ''
								: 'This server is not currently reachable for commands. Actions may fail until it is available.'
						}
					/>
				) : null }

				<ChecklistPanel />
			</div>

			<ConfirmDialog
				open={ confirmDelete }
				title="Delete this server?"
				message="The provider instance and every site on it will be destroyed. This cannot be undone."
				confirmLabel="Delete server"
				onConfirm={ remove }
				onCancel={ () => setConfirmDelete( false ) }
				busy={ deleting }
			/>
		</AppShell>
	);
}

/**
 * @param {Object} props
 * @return {JSX.Element} Definition list entry.
 */
function Detail( { label, children } ) {
	return (
		<div>
			<dt className="text-xs uppercase tracking-wide text-ink-400">{ label }</dt>
			<dd className="mt-1 text-sm text-ink-800">{ children }</dd>
		</div>
	);
}

const CONSOLE_TABS = [
	'ols_console',
	'ssh_console',
	'statistics',
];

/**
 * Document the tabs that render through the generic schema but need a note.
 *
 * @return {JSX.Element} Informational card.
 */
function ChecklistPanel() {
	return (
		<Card title="About these panels">
			<p className="text-sm leading-6 text-ink-500">
				Every panel above is rendered from the same field schema the legacy screen used, so
				its behaviour is unchanged. Actions that stream output redirect to the live log
				drawer instead of the old popup console.
			</p>
			<p className="mt-2 font-mono text-xs text-ink-400">{ CONSOLE_TABS.join( ' · ' ) }</p>
		</Card>
	);
}
