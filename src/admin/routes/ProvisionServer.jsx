/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Provision a server.
 *
 * Uses `POST /servers`, which calls `SSTK_SERVER()->create_server( 'create', $attributes )`
 * and then queues the install task exactly as the legacy REST controller did.
 */

import { useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import { Card, ErrorPanel } from '../components/Card.jsx';
import { useToast } from '../components/Toast.jsx';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';

const OS_OPTIONS = [
	{ value: 'ubuntu2404lts', label: 'Ubuntu 24.04 LTS' },
	{ value: 'ubuntu2604lts', label: 'Ubuntu 26.04 LTS' },
];

const FIELDS = [
	{
		key: 'name',
		label: 'Server name',
		required: true,
		placeholder: 'web-prod-01',
		help: 'Internal label only; the provider instance is created from the fields below.',
	},
	{ key: 'region', label: 'Region', required: true, placeholder: 'ewr' },
	{ key: 'size', label: 'Plan / size', required: true, placeholder: 'vc2-2c-4gb' },
	{
		key: 'os',
		label: 'Operating system',
		type: 'select',
		options: OS_OPTIONS,
		help: 'Ubuntu 24.04 and 26.04 are supported; PHP 8.4 is the default.',
	},
	{ key: 'author_email', label: 'Owner email', type: 'email', placeholder: 'ops@onedogsolutions.com' },
];

/**
 * @return {JSX.Element} Provisioning route.
 */
export default function ProvisionServer() {
	const toast = useToast();

	const [ providers, setProviders ] = useState( [] );
	const [ providersError, setProvidersError ] = useState( null );
	const [ values, setValues ] = useState( {
		name: '',
		provider: '',
		region: '',
		size: '',
		os: 'ubuntu2404lts',
		author_email: '',
	} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		api.providers
			.list()
			.then( ( data ) => {
				const usable = ( data.items || [] ).filter( ( provider ) => provider.credentials_available );

				setProviders( usable );
				setProvidersError( null );

				if ( usable.length > 0 ) {
					setValues( ( current ) => ( current.provider ? current : { ...current, provider: usable[ 0 ].slug } ) );
				}
			} )
			.catch( ( cause ) => setProvidersError( cause ) );
	}, [] );

	const update = ( key ) => ( event ) =>
		setValues( ( current ) => ( { ...current, [ key ]: event.target.value } ) );

	const selectedOsLabel = OS_OPTIONS.find( ( option ) => option.value === values.os )?.label || 'Ubuntu 24.04 LTS';

	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );

		try {
			const result = await api.servers.create( {
				name: values.name,
				provider: values.provider,
				region: values.region,
				size: values.size,
				os: values.os || undefined,
				author_email: values.author_email || undefined,
			} );

			toast.push( `Provisioning queued for ${ result.domain || values.name }.`, 'success' );
			navigate( `/servers/${ result.server_id }` );
		} catch ( cause ) {
			setError( cause );
			toast.push( cause.message, 'error' );
		} finally {
			setBusy( false );
		}
	};

	return (
		<AppShell
			path="/servers/new"
			title="Provision server"
			subtitle="Creates a provider instance and installs OpenLiteSpeed."
			breadcrumbs={ [ { label: 'Servers', path: '/servers' }, { label: 'New server' } ] }
		>
			<div className="max-w-3xl space-y-4">
				{ providersError ? <ErrorPanel error={ providersError } /> : null }

				{ ! providersError && providers.length === 0 ? (
					<Card title="No provider is configured">
						<p className="text-sm text-ink-500">
							Add an API key for Vultr or UpCloud before provisioning a server.
						</p>
						<Button className="mt-3" variant="primary" onClick={ () => navigate( '/providers' ) }>
							Open provider settings
						</Button>
					</Card>
				) : null }

				{ providers.length > 0 ? (
					<Card title="Server details">
						<form onSubmit={ submit } className="space-y-4">
							<div>
								<label htmlFor="provider" className="mb-1 block text-sm font-medium text-ink-800">
									Provider
								</label>
								<select
									id="provider"
									value={ values.provider }
									onChange={ update( 'provider' ) }
									className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
								>
									{ providers.map( ( provider ) => (
										<option key={ provider.slug } value={ provider.slug }>
											{ provider.label }
										</option>
									) ) }
								</select>
							</div>

							{ FIELDS.map( ( field ) => (
								<div key={ field.key }>
									<label htmlFor={ field.key } className="mb-1 block text-sm font-medium text-ink-800">
										{ field.label }
										{ field.required ? <span className="ml-0.5 text-negative-600">*</span> : null }
									</label>
									{ field.type === 'select' ? (
										<select
											id={ field.key }
											value={ values[ field.key ] }
											onChange={ update( field.key ) }
											className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
										>
											{ field.options.map( ( option ) => (
												<option key={ option.value } value={ option.value }>
													{ option.label }
												</option>
											) ) }
										</select>
									) : (
										<input
											id={ field.key }
											type={ field.type || 'text' }
											required={ field.required }
											placeholder={ field.placeholder }
											value={ values[ field.key ] }
											onChange={ update( field.key ) }
											className="w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm placeholder:text-ink-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
										/>
									) }
									{ field.help ? <p className="mt-1 text-xs text-ink-500">{ field.help }</p> : null }
								</div>
							) ) }

							<div className="rounded-card border border-surface-border bg-surface-muted px-3 py-2 text-xs text-ink-500">
								Web server: <span className="font-medium text-ink-800">OpenLiteSpeed</span> · Stack:
								{ selectedOsLabel } · PHP <span className="font-mono">lsphp84</span>
							</div>

							{ error ? <ErrorPanel error={ error } /> : null }

							<div className="flex gap-2">
								<Button type="submit" variant="primary" busy={ busy }>
									Provision
								</Button>
								<Button variant="ghost" onClick={ () => navigate( '/servers' ) } disabled={ busy }>
									Cancel
								</Button>
							</div>
						</form>
					</Card>
				) : null }
			</div>
		</AppShell>
	);
}
