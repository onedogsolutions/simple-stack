/**
 * Site security posture.
 *
 * Backed by `POST /sites/{id}/security-status`, which runs a strictly read-only probe
 * over SSH (`wp plugin list`, `wp config get DISALLOW_FILE_EDIT`, `php -v` and a grep of
 * the vhost handler) and returns the merged result. Nothing on this screen mutates the
 * remote site, and no key material ever reaches the browser.
 */

import { useCallback, useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import DataTable from '../components/DataTable.jsx';
import { Badge, Card, ErrorPanel, Skeleton } from '../components/Card.jsx';
import { useToast } from '../components/Toast.jsx';
import { api } from '../lib/api.js';
import { display } from '../lib/format.js';
import { navigate } from '../lib/router.js';

/**
 * @param {Object} props
 * @param {number} props.id Site post id.
 * @return {JSX.Element} Security status route.
 */
export default function SecurityStatus( { id } ) {
	const toast = useToast();

	const [ report, setReport ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const probe = useCallback( async () => {
		setLoading( true );

		try {
			const result = await api.sites.securityStatus( id );
			setReport( result );
			setError( null );

			if ( ! result.connected ) {
				toast.push( result.message || 'The site could not be probed.', 'error' );
			}
		} catch ( cause ) {
			setError( cause );
			toast.push( cause.message, 'error' );
		} finally {
			setLoading( false );
		}
	}, [ id, toast ] );

	useEffect( () => {
		probe();
	}, [ probe ] );

	const crumbs = [
		{ label: 'Sites', path: '/sites' },
		{ label: 'Site', path: `/sites/${ id }` },
		{ label: 'Security' },
	];

	const plugins = ( report && report.plugins ) || [];
	const active = plugins.filter( ( plugin ) => plugin.status === 'active' ).length;
	const outdated = plugins.filter( ( plugin ) => plugin.update === 'available' ).length;

	const columns = [
		{
			key: 'name',
			header: 'Plugin',
			render: ( row ) => <span className="font-mono text-xs text-ink-800">{ row.name || '—' }</span>,
		},
		{
			key: 'status',
			header: 'Status',
			render: ( row ) => (
				<Badge tone={ row.status === 'active' ? 'positive' : row.status === 'inactive' ? 'neutral' : 'warning' }>
					{ row.status || 'unknown' }
				</Badge>
			),
		},
		{
			key: 'version',
			header: 'Version',
			render: ( row ) => <span className="text-xs text-ink-600">{ display( row.version ) }</span>,
		},
		{
			key: 'update',
			header: 'Update',
			align: 'right',
			render: ( row ) =>
				row.update === 'available' ? (
					<Badge tone="warning">available</Badge>
				) : (
					<span className="text-xs text-ink-400">none</span>
				),
		},
	];

	return (
		<AppShell
			path={ `/sites/${ id }/security` }
			title="Security status"
			subtitle={
				report && report.domain
					? report.domain
					: 'Read-only probe of the installed plugins and file-editor policy.'
			}
			breadcrumbs={ crumbs }
			action={
				<div className="flex flex-wrap gap-2">
					<Button onClick={ () => navigate( `/sites/${ id }` ) }>Back to site</Button>
					<Button variant="primary" onClick={ probe } busy={ loading }>
						Re-run probe
					</Button>
				</div>
			}
		>
			{ error ? <ErrorPanel error={ error } onRetry={ probe } /> : null }

			{ loading && ! report ? (
				<Card>
					<Skeleton rows={ 6 } />
				</Card>
			) : null }

			{ report ? (
				<div className="space-y-4">
					{ ! report.connected ? (
						<div className="rounded-card border border-warning-200 bg-warning-50 p-4 text-sm text-warning-700">
							<p className="font-medium">The site could not be probed.</p>
							{ report.message ? <p className="mt-1">{ report.message }</p> : null }
							<p className="mt-2 text-xs">
								The most common causes are a server that is still starting, a DNS record that does
								not resolve yet, or a provider credential that has been rotated.
							</p>
						</div>
					) : null }

					<Card title="Summary">
						<dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
							<Field label="Connection">
								<Badge tone={ report.connected ? 'positive' : 'negative' }>
									{ report.connected ? 'reachable' : 'unreachable' }
								</Badge>
							</Field>
							<Field label="File editing">
								<Badge tone={ report.file_edit_disallowed ? 'positive' : 'negative' }>
									{ report.file_edit_disallowed ? 'DISALLOW_FILE_EDIT set' : 'editing allowed' }
								</Badge>
							</Field>
							<Field label="PHP runtime">
								<span className="font-mono text-xs text-ink-700">{ display( report.php_version ) }</span>
							</Field>
							<Field label="Plugins">
								<span className="text-sm text-ink-700">
									{ plugins.length } installed · { active } active
									{ outdated > 0 ? ` · ${ outdated } outdated` : '' }
								</span>
							</Field>
						</dl>
					</Card>

					<Card title="Installed plugins" subtitle="Reported by `wp plugin list` on the remote site.">
						<DataTable
							columns={ columns }
							rows={ plugins }
							rowKey={ ( row, index ) => `${ row.name }-${ index }` }
							emptyTitle="No plugin data"
							emptyMessage="The probe returned no plugins. WP-CLI may not be installed on this server."
						/>
					</Card>
				</div>
			) : null }
		</AppShell>
	);
}

/**
 * @param {Object} props
 * @return {JSX.Element} Definition list entry.
 */
function Field( { label, children } ) {
	return (
		<div>
			<dt className="text-xs uppercase tracking-wide text-ink-400">{ label }</dt>
			<dd className="mt-1 text-sm text-ink-800">{ children }</dd>
		</div>
	);
}
