/**
 * Site detail.
 *
 * Replaces the Meta Box tabbed edit screen for `wpcd_app`. Site actions go through
 * `POST /sites/{id}/action`, which maps the body onto `$_POST` and then applies the
 * `wpcd_app_{app}_tab_action` filter, so every tab handler sees exactly the payload it
 * expects.
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
import { display, formatDate, stateTone, titleCase } from '../lib/format.js';
import { navigate } from '../lib/router.js';

/**
 * @param {Object} props
 * @param {number} props.id Site post id.
 * @return {JSX.Element} Site detail route.
 */
export default function SiteDetail( { id } ) {
	const toast = useToast();

	const [ site, setSite ] = useState( null );
	const [ schema, setSchema ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ logOpen, setLogOpen ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const [ deleting, setDeleting ] = useState( false );

	const load = useCallback( async () => {
		setLoading( true );

		try {
			const [ detail, tabs ] = await Promise.all( [ api.sites.get( id ), api.tabs.forSite( id ) ] );
			setSite( detail );
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
			const result = await api.sites.runAction( id, action, params, files );
			toast.push( `“${ titleCase( action ) }” submitted.`, 'success' );
			load();

			return result;
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
			throw cause;
		}
	};

	const remove = async () => {
		setDeleting( true );

		try {
			await api.sites.remove( id );
			toast.push( 'Site moved to the trash.', 'success' );
			navigate( '/sites' );
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
		} finally {
			setDeleting( false );
			setConfirmDelete( false );
		}
	};

	const crumbs = [
		{ label: 'Sites', path: '/sites' },
		{ label: ( site && site.domain ) || `Site ${ id }` },
	];

	if ( loading && ! site ) {
		return (
			<AppShell path={ `/sites/${ id }` } title="Loading site…" breadcrumbs={ crumbs }>
				<Card>
					<Skeleton rows={ 8 } />
				</Card>
			</AppShell>
		);
	}

	if ( error && ! site ) {
		return (
			<AppShell path={ `/sites/${ id }` } title="Site unavailable" breadcrumbs={ crumbs }>
				<ErrorPanel error={ error } onRetry={ load } />
			</AppShell>
		);
	}

	const meta = ( site && site.meta ) || {};
	const status = String( site.action_status || '' );

	return (
		<AppShell
			path={ `/sites/${ id }` }
			title={ site.domain || site.name || `Site ${ id }` }
			subtitle={ site.server_name ? `on ${ site.server_name }` : 'Unassigned server' }
			breadcrumbs={ crumbs }
			action={
				<div className="flex flex-wrap gap-2">
					<Button onClick={ () => navigate( `/sites/${ id }/security` ) }>Security status</Button>
					<Button onClick={ () => setLogOpen( ( current ) => ! current ) }>
						{ logOpen ? 'Hide log' : 'Show server log' }
					</Button>
					<Button variant="danger" onClick={ () => setConfirmDelete( true ) }>
						Trash
					</Button>
				</div>
			}
		>
			<div className="space-y-4">
				<Card title="Overview">
					<dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
						<Detail label="Domain">{ display( site.domain ) }</Detail>
						<Detail label="Server">
							{ site.server_id ? (
								<button
									type="button"
									onClick={ () => navigate( `/servers/${ site.server_id }` ) }
									className="text-brand-700 hover:underline"
								>
									{ site.server_name || `Server ${ site.server_id }` }
								</button>
							) : (
								'—'
							) }
						</Detail>
						<Detail label="PHP">{ display( site.php_version ) }</Detail>
						<Detail label="WordPress">{ display( site.wp_version ) }</Detail>
						<Detail label="Admin user">{ display( meta.user ) }</Detail>
						<Detail label="Admin email">{ display( meta.email ) }</Detail>
						<Detail label="Created">{ formatDate( site.created ) }</Detail>
						<Detail label="Action status">
							{ status ? <Badge tone={ stateTone( status ) }>{ status }</Badge> : <span>—</span> }
						</Detail>
					</dl>
				</Card>

				<LogDrawer
					serverId={ site.server_id }
					open={ logOpen }
					onClose={ () => setLogOpen( false ) }
					title={ `Log — ${ site.domain || id }` }
				/>

				{ schema ? (
					<TabRenderer
						tabs={ schema.tabs }
						fields={ schema.fields }
						onAction={ runAction }
						onShowLog={ () => setLogOpen( true ) }
					/>
				) : null }
			</div>

			<ConfirmDialog
				open={ confirmDelete }
				title="Trash this site?"
				message="The WordPress record is removed from SimpleStack. Files on the server are left in place until you remove them from the server itself."
				confirmLabel="Trash site"
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
