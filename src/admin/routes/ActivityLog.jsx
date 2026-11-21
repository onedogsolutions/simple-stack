/**
 * Activity feed.
 *
 * `GET /activity` merges the command, SSH and error log post types across every server
 * and returns `{ items, cursor }`. Server names are resolved client side from a single
 * `GET /servers` call so the feed can name its source without one request per row.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import { Badge, Card, ErrorPanel, EmptyState, Skeleton } from '../components/Card.jsx';
import { api } from '../lib/api.js';
import { display, formatDate, relativeTime } from '../lib/format.js';
import { navigate } from '../lib/router.js';

const LIMITS = [ 25, 50, 100, 200 ];

const TYPE_TONES = {
	command: 'brand',
	ssh: 'neutral',
	error: 'negative',
};

/**
 * @return {JSX.Element} Activity route.
 */
export default function ActivityLog() {
	const [ items, setItems ] = useState( [] );
	const [ cursor, setCursor ] = useState( '' );
	const [ serverNames, setServerNames ] = useState( {} );
	const [ limit, setLimit ] = useState( 50 );
	const [ typeFilter, setTypeFilter ] = useState( 'all' );
	const [ search, setSearch ] = useState( '' );
	const [ expanded, setExpanded ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );

		try {
			const [ feed, servers ] = await Promise.all( [
				api.logs.activity( { limit } ),
				api.servers.list( { per_page: 100 } ).catch( () => ( { items: [] } ) ),
			] );

			setItems( feed.items || [] );
			setCursor( feed.cursor || '' );

			const names = {};
			( servers.items || [] ).forEach( ( server ) => {
				names[ server.id ] = server.name || `Server ${ server.id }`;
			} );
			setServerNames( names );

			setError( null );
		} catch ( cause ) {
			setError( cause );
		} finally {
			setLoading( false );
		}
	}, [ limit ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const visible = useMemo( () => {
		const needle = search.trim().toLowerCase();

		return items.filter( ( entry ) => {
			if ( typeFilter !== 'all' && entry.type !== typeFilter ) {
				return false;
			}

			if ( ! needle ) {
				return true;
			}

			return [ entry.title, entry.reference, entry.body, entry.command_type, serverNames[ entry.parent ] ]
				.filter( Boolean )
				.some( ( value ) => String( value ).toLowerCase().includes( needle ) );
		} );
	}, [ items, typeFilter, search, serverNames ] );

	const toggle = ( id ) =>
		setExpanded( ( current ) => ( { ...current, [ id ]: ! current[ id ] } ) );

	return (
		<AppShell
			path="/activity"
			title="Activity"
			subtitle="Merged command, SSH and error log entries across every server."
			action={
				<Button variant="primary" onClick={ load } busy={ loading }>
					Refresh
				</Button>
			}
		>
			<div className="mb-4 flex flex-wrap items-end gap-3">
				<div className="min-w-[14rem] flex-1">
					<label htmlFor="activity-search" className="mb-1 block text-xs font-medium text-ink-500">
						Search
					</label>
					<input
						id="activity-search"
						type="search"
						value={ search }
						onChange={ ( event ) => setSearch( event.target.value ) }
						placeholder="Server, command, message"
						className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					/>
				</div>

				<div>
					<label htmlFor="activity-type" className="mb-1 block text-xs font-medium text-ink-500">
						Stream
					</label>
					<select
						id="activity-type"
						value={ typeFilter }
						onChange={ ( event ) => setTypeFilter( event.target.value ) }
						className="rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					>
						<option value="all">All streams</option>
						<option value="command">Commands</option>
						<option value="ssh">SSH</option>
						<option value="error">Errors</option>
					</select>
				</div>

				<div>
					<label htmlFor="activity-limit" className="mb-1 block text-xs font-medium text-ink-500">
						Show
					</label>
					<select
						id="activity-limit"
						value={ limit }
						onChange={ ( event ) => setLimit( Number( event.target.value ) ) }
						className="rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					>
						{ LIMITS.map( ( value ) => (
							<option key={ value } value={ value }>
								{ value } entries
							</option>
						) ) }
					</select>
				</div>
			</div>

			{ error ? <ErrorPanel error={ error } onRetry={ load } /> : null }

			{ loading && items.length === 0 ? (
				<Card>
					<Skeleton rows={ 8 } />
				</Card>
			) : null }

			{ ! loading && visible.length === 0 && ! error ? (
				<EmptyState
					title="Nothing recorded yet"
					message="Command output, SSH sessions and PHP errors appear here once a server has been provisioned."
				/>
			) : null }

			<div className="space-y-2">
				{ visible.map( ( entry ) => {
					const isOpen = Boolean( expanded[ entry.id ] );
					const body = entry.body || '';

					return (
						<article
							key={ entry.id }
							className="rounded-card border border-surface-border bg-surface-base"
						>
							<header className="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
								<div className="min-w-0">
									<div className="flex flex-wrap items-center gap-2">
										<Badge tone={ TYPE_TONES[ entry.type ] || 'neutral' }>{ entry.type }</Badge>
										{ entry.command_type ? (
											<span className="font-mono text-xs text-ink-500">{ entry.command_type }</span>
										) : null}
										<span className="text-xs text-ink-400">{ relativeTime( entry.date_gmt ) }</span>
									</div>
									<p className="mt-1 truncate text-sm font-medium text-ink-900">
										{ entry.title || display( entry.reference ) }
									</p>
									<p className="mt-0.5 text-xs text-ink-500">
										{ entry.parent ? (
											<button
												type="button"
												onClick={ () => navigate( `/servers/${ entry.parent }` ) }
												className="hover:text-brand-700 hover:underline"
											>
												{ serverNames[ entry.parent ] || `Server ${ entry.parent }` }
											</button>
										) : (
											'No server'
										) }
										{ entry.file ? (
											<span className="ml-2 font-mono">
												{ entry.file }
												{ entry.line ? `:${ entry.line }` : '' }
											</span>
										) : null}
									</p>
								</div>

								<div className="flex shrink-0 items-center gap-2">
									<span className="text-xs text-ink-400">{ formatDate( entry.date_gmt ) }</span>
									{ body ? (
										<Button size="sm" onClick={ () => toggle( entry.id ) }>
											{ isOpen ? 'Hide' : 'Show' }
										</Button>
									) : null}
								</div>
							</header>

							{ isOpen && body ? (
								<pre className="max-h-72 overflow-auto border-t border-surface-border bg-surface-muted/60 px-4 py-3 font-mono text-xs leading-5 text-ink-700">
									{ body }
								</pre>
							) : null}
						</article>
					);
				} ) }
			</div>

			{ cursor ? (
				<p className="mt-4 text-xs text-ink-400">
					Newest entry: <span className="font-mono">{ cursor }</span>
				</p>
			) : null}
		</AppShell>
	);
}
