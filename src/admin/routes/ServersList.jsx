/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Servers list.
 *
 * Replaces the `sstk_app_server` CPT edit screen. Filtering mirrors the REST query
 * arguments so the URL stays shareable through the hash.
 */

import { useCallback, useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import DataTable from '../components/DataTable.jsx';
import { Badge, ErrorPanel } from '../components/Card.jsx';
import { api } from '../lib/api.js';
import { display, formatDate, stateTone, titleCase } from '../lib/format.js';
import { navigate } from '../lib/router.js';

const PER_PAGE = 25;

/**
 * @return {JSX.Element} Servers list route.
 */
export default function ServersList() {
	const [ state, setState ] = useState( { items: [], total: 0 } );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ filters, setFilters ] = useState( { provider: '', status: '' } );
	const [ providers, setProviders ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const load = useCallback(
		async ( signal ) => {
			setLoading( true );

			try {
				const data = await api.servers.list( {
					page,
					per_page: PER_PAGE,
					search,
					provider: filters.provider,
					status: filters.status,
				} );

				if ( signal && signal.cancelled ) {
					return;
				}

				setState( { items: data.items || [], total: data.total || 0 } );
				setError( null );
			} catch ( cause ) {
				setError( cause );
			} finally {
				setLoading( false );
			}
		},
		[ page, search, filters ]
	);

	useEffect( () => {
		const signal = { cancelled: false };
		load( signal );

		return () => {
			signal.cancelled = true;
		};
	}, [ load ] );

	useEffect( () => {
		api.providers
			.list()
			.then( ( data ) => setProviders( data.items || [] ) )
			.catch( () => setProviders( [] ) );
	}, [] );

	const columns = [
		{
			key: 'name',
			header: 'Server',
			render: ( row ) => (
				<div>
					<p className="font-medium text-ink-900">{ row.name || `(server ${ row.id })` }</p>
					<p className="mt-0.5 font-mono text-xs text-ink-400">{ row.ipv4 || row.instance_id || '—' }</p>
				</div>
			),
		},
		{
			key: 'provider_label',
			header: 'Provider',
			render: ( row ) => (
				<div>
					<p className="text-ink-800">{ row.provider_label || titleCase( row.provider ) }</p>
					<p className="mt-0.5 text-xs text-ink-400">{ row.region || '—' }</p>
				</div>
			),
		},
		{
			key: 'size',
			header: 'Size',
			render: ( row ) => <span className="text-sm text-ink-700">{ display( row.size ) }</span>,
		},
		{
			key: 'state',
			header: 'State',
			render: ( row ) => (
				<div className="flex flex-wrap items-center gap-1.5">
					<Badge tone={ stateTone( row.state ) }>{ row.state || 'unknown' }</Badge>
					{ row.health ? <Badge tone={ stateTone( row.health ) }>{ row.health }</Badge> : null }
				</div>
			),
		},
		{
			key: 'app_count',
			header: 'Sites',
			align: 'right',
			sortValue: ( row ) => Number( row.app_count || 0 ),
			render: ( row ) => <span className="font-mono text-sm">{ row.app_count || 0 }</span>,
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
			path="/servers"
			title="Servers"
			subtitle={ `${ state.total } server${ state.total === 1 ? '' : 's' } under management` }
			action={
				<Button variant="primary" onClick={ () => navigate( '/servers/new' ) }>
					Provision server
				</Button>
			}
		>
			<div className="mb-4 flex flex-wrap items-end gap-2">
				<div className="min-w-[14rem] flex-1">
					<label htmlFor="server-search" className="mb-1 block text-xs font-medium text-ink-500">
						Search
					</label>
					<input
						id="server-search"
						type="search"
						value={ search }
						onChange={ ( event ) => {
							setPage( 1 );
							setSearch( event.target.value );
						} }
						placeholder="Server name"
						className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					/>
				</div>

				<div>
					<label htmlFor="server-provider" className="mb-1 block text-xs font-medium text-ink-500">
						Provider
					</label>
					<select
						id="server-provider"
						value={ filters.provider }
						onChange={ ( event ) => {
							setPage( 1 );
							setFilters( ( current ) => ( { ...current, provider: event.target.value } ) );
						} }
						className="rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					>
						<option value="">All providers</option>
						{ providers.map( ( provider ) => (
							<option key={ provider.slug } value={ provider.slug }>
								{ provider.label }
							</option>
						) ) }
					</select>
				</div>

				<Button variant="ghost" onClick={ () => load() } busy={ loading }>
					Refresh
				</Button>
			</div>

			{ error ? <ErrorPanel error={ error } onRetry={ () => load() } /> : null }

			<DataTable
				columns={ columns }
				rows={ state.items }
				rowKey={ ( row ) => row.id }
				onRowClick={ ( row ) => navigate( `/servers/${ row.id }` ) }
				loading={ loading }
				emptyTitle="No servers yet"
				emptyMessage="Provision your first Vultr or UpCloud server to get started."
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
