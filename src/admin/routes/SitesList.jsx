/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Sites list.
 *
 * Replaces the `sstk_app` CPT edit screen (`app_type=wordpress-app`).
 */

import { useCallback, useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import DataTable from '../components/DataTable.jsx';
import { ErrorPanel } from '../components/Card.jsx';
import { api } from '../lib/api.js';
import { display, formatDate } from '../lib/format.js';
import { navigate, currentPath } from '../lib/router.js';

const PER_PAGE = 25;

/**
 * Read the `server` hint from the hash query string, if present.
 *
 * @return {number} Server id, or zero.
 */
function serverFromHash() {
	const query = window.location.hash.split( '?' )[ 1 ] || '';
	const params = new URLSearchParams( query );
	return Number( params.get( 'server' ) || 0 );
}

/**
 * @return {JSX.Element} Sites list route.
 */
export default function SitesList() {
	const [ state, setState ] = useState( { items: [], total: 0 } );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ serverId, setServerId ] = useState( serverFromHash );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );

		try {
			const data = await api.sites.list( {
				page,
				per_page: PER_PAGE,
				search,
				server_id: serverId || undefined,
			} );

			setState( { items: data.items || [], total: data.total || 0 } );
			setError( null );
		} catch ( cause ) {
			setError( cause );
		} finally {
			setLoading( false );
		}
	}, [ page, search, serverId ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const columns = [
		{
			key: 'domain',
			header: 'Site',
			render: ( row ) => (
				<div>
					<p className="font-medium text-ink-900">{ row.domain || row.name || `(site ${ row.id })` }</p>
					<p className="mt-0.5 text-xs text-ink-400">{ row.name || '—' }</p>
				</div>
			),
		},
		{
			key: 'server_name',
			header: 'Server',
			render: ( row ) => (
				<button
					type="button"
					onClick={ ( event ) => {
						event.stopPropagation();
						navigate( `/servers/${ row.server_id }` );
					} }
					className="text-sm text-brand-700 hover:underline"
				>
					{ row.server_name || ( row.server_id ? `Server ${ row.server_id }` : '—' ) }
				</button>
			),
		},
		{
			key: 'php_version',
			header: 'Stack',
			render: ( row ) => (
				<div className="text-xs text-ink-600">
					<p className="font-mono">{ display( row.php_version ) }</p>
					<p className="mt-0.5">WordPress { display( row.wp_version ) }</p>
				</div>
			),
		},
		{
			key: 'owner',
			header: 'Owner',
			align: 'right',
			render: ( row ) => <span className="font-mono text-sm text-ink-600">{ row.owner || '—' }</span>,
		},
		{
			key: 'created',
			header: 'Created',
			render: ( row ) => <span className="text-xs text-ink-500">{ formatDate( row.created ) }</span>,
		},
	];

	const totalPages = Math.max( 1, Math.ceil( state.total / PER_PAGE ) );

	return (
		<AppShell
			path={ currentPath() }
			title="Sites"
			subtitle={
				serverId
					? `Sites on server ${ serverId }`
					: `${ state.total } WordPress site${ state.total === 1 ? '' : 's' }`
			}
			action={
				<div className="flex flex-wrap gap-2">
					{ serverId ? (
						<Button onClick={ () => setServerId( 0 ) }>Show all servers</Button>
					) : null }
					<Button variant="primary" onClick={ () => navigate( '/sites/new' ) }>
						Deploy site
					</Button>
				</div>
			}
		>
			<div className="mb-4 flex flex-wrap items-end gap-2">
				<div className="min-w-[14rem] flex-1">
					<label htmlFor="site-search" className="mb-1 block text-xs font-medium text-ink-500">
						Search
					</label>
					<input
						id="site-search"
						type="search"
						value={ search }
						onChange={ ( event ) => {
							setPage( 1 );
							setSearch( event.target.value );
						} }
						placeholder="Domain or site title"
						className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					/>
				</div>
				<Button variant="ghost" onClick={ load } busy={ loading }>
					Refresh
				</Button>
			</div>

			{ error ? <ErrorPanel error={ error } onRetry={ load } /> : null }

			<DataTable
				columns={ columns }
				rows={ state.items }
				rowKey={ ( row ) => row.id }
				onRowClick={ ( row ) => navigate( `/sites/${ row.id }` ) }
				loading={ loading }
				emptyTitle="No sites yet"
				emptyMessage="Deploy a WordPress site onto one of your servers to get started."
			/>

			{ totalPages > 1 ? (
				<div className="mt-4 flex items-center justify-between text-sm">
					<span className="text-ink-500">
						Page { page } of { totalPages }
					</span>
					<div className="flex gap-2">
						<Button size="sm" onClick={ () => setPage( ( current ) => Math.max( 1, current - 1 ) ) } disabled={ page <= 1 }>
							Previous
						</Button>
						<Button
							size="sm"
							onClick={ () => setPage( ( current ) => Math.min( totalPages, current + 1 ) ) }
							disabled={ page >= totalPages }
						>
							Next
						</Button>
					</div>
				</div>
			) : null }
		</AppShell>
	);
}
