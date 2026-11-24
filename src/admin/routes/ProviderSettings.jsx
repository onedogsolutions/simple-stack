/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Cloud provider settings.
 *
 * The controller never returns a credential: it returns `has_api_key`, `has_user_password`,
 * `has_ssh_key` and `has_ssh_key_id` booleans plus feature flags and region/size counts.
 * Empty inputs are therefore treated as "leave the stored value alone", which is why every
 * credential control starts blank and is labelled with its stored/not-set state.
 *
 * UpCloud authenticates with an account user name and password instead of an api key, so
 * `uses_api_key` decides which credential controls a provider card shows. Both shapes are
 * encrypted server side before they reach the options table.
 */

import { useCallback, useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import SecretField from '../components/SecretField.jsx';
import { Badge, Card, ErrorPanel, Skeleton } from '../components/Card.jsx';
import { useToast } from '../components/Toast.jsx';
import { api } from '../lib/api.js';
import { display } from '../lib/format.js';

const INPUT_CLASS =
	'w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm text-ink-900 placeholder:text-ink-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';

/**
 * Every credential control is cleared after a save, so this is the one place that knows
 * the full shape of the form.
 *
 * @return {Object} Blank credential values.
 */
const emptyValues = () => ( {
	api_key: '',
	user_name: '',
	user_password: '',
	ssh_key_id: '',
	ssh_key: '',
	notes: '',
} );

/**
 * @return {JSX.Element} Provider settings route.
 */
export default function ProviderSettings() {
	const toast = useToast();

	const [ providers, setProviders ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ busy, setBusy ] = useState( '' );

	const load = useCallback( async ( refresh = false ) => {
		setLoading( true );

		try {
			const data = await api.providers.list( refresh ? { refresh: 1 } : undefined );
			setProviders( data.items || [] );
			setError( null );
		} catch ( cause ) {
			setError( cause );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const save = async ( slug, values ) => {
		setBusy( `save:${ slug }` );

		try {
			await api.providers.save( { slug, ...values } );
			toast.push( `${ slug } credentials saved.`, 'success' );
			await load();
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
		} finally {
			setBusy( '' );
		}
	};

	const test = async ( slug ) => {
		setBusy( `test:${ slug }` );

		try {
			const result = await api.providers.test( slug );
			toast.push( `${ slug }: ${ result.message }`, result.connected ? 'success' : 'error' );
		} catch ( cause ) {
			toast.push( cause.message, 'error' );
		} finally {
			setBusy( '' );
		}
	};

	return (
		<AppShell
			path="/settings/providers"
			title="Providers"
			subtitle="API credentials are encrypted at rest and never returned by the REST API."
			action={
				<Button variant="primary" onClick={ () => load( true ) } busy={ loading }>
					Refresh counts
				</Button>
			}
		>
			{ error ? <ErrorPanel error={ error } onRetry={ () => load() } /> : null }

			{ loading && providers.length === 0 ? (
				<Card>
					<Skeleton rows={ 6 } />
				</Card>
			) : null}

			<div className="space-y-4">
				{ providers.map( ( provider ) => (
					<ProviderCard
						key={ provider.slug }
						provider={ provider }
						busy={ busy }
						onSave={ save }
						onTest={ test }
					/>
				) ) }
			</div>

			{ ! loading && providers.length === 0 && ! error ? (
				<Card title="No providers registered">
					<p className="text-sm text-ink-500">
						The provider registry in <code className="font-mono text-xs">simple-stack.php</code> is
						empty, so nothing can be provisioned.
					</p>
				</Card>
			) : null}
		</AppShell>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.provider Provider summary from `GET /settings/providers`.
 * @param {string}   props.busy     Key of the in-flight operation.
 * @param {Function} props.onSave   (slug, values) => Promise
 * @param {Function} props.onTest   (slug) => Promise
 * @return {JSX.Element} Provider card.
 */
function ProviderCard( { provider, busy, onSave, onTest } ) {
	const [ open, setOpen ] = useState( false );
	const [ values, setValues ] = useState( emptyValues );

	/* Providers that authenticate with a user name and password instead of an api key. */
	const usesApiKey = provider.uses_api_key !== false;

	const update = ( key ) => ( event ) =>
		setValues( ( current ) => ( { ...current, [ key ]: event.target.value } ) );

	const submit = async ( event ) => {
		event.preventDefault();
		await onSave( provider.slug, values );
		setValues( emptyValues() );
	};

	const enabled = Object.entries( provider.features || {} )
		.filter( ( [ , supported ] ) => supported )
		.map( ( [ feature ] ) => feature );

	return (
		<Card
			title={ provider.label || provider.slug }
			subtitle={ provider.slug }
			action={
				<div className="flex flex-wrap items-center gap-2">
					<Badge tone={ provider.available ? 'positive' : 'negative' }>
						{ provider.available ? 'installed' : 'not in build' }
					</Badge>
					<Badge tone={ provider.credentials_available ? 'positive' : 'warning' }>
						{ provider.credentials_available ? 'ready' : 'needs credentials' }
					</Badge>
					<Button size="sm" onClick={ () => setOpen( ( current ) => ! current ) }>
						{ open ? 'Hide' : 'Edit' }
					</Button>
					<Button
						size="sm"
						variant="primary"
						onClick={ () => onTest( provider.slug ) }
						busy={ busy === `test:${ provider.slug }` }
						disabled={ ! provider.available || busy !== '' }
					>
						Test
					</Button>
				</div>
			}
		>
			<dl className="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
				{ usesApiKey ? (
					<Field label="API key">
						<Badge tone={ provider.has_api_key ? 'positive' : 'neutral' }>
							{ provider.has_api_key ? 'stored' : 'not set' }
						</Badge>
					</Field>
				) : (
					<Field label="User name / password">
						<Badge tone={ provider.has_user_password ? 'positive' : 'neutral' }>
							{ provider.has_user_password ? 'stored' : 'not set' }
						</Badge>
					</Field>
				) }
				<Field label="SSH key">
					<Badge tone={ provider.has_ssh_key ? 'positive' : 'neutral' }>
						{ provider.has_ssh_key ? 'stored' : 'not set' }
					</Badge>
				</Field>
				<Field label="SSH key id">
					<Badge tone={ provider.has_ssh_key_id ? 'positive' : 'neutral' }>
						{ provider.has_ssh_key_id ? 'stored' : 'not set' }
					</Badge>
				</Field>
				<Field label="Regions / sizes">
					<span className="font-mono text-xs text-ink-700">
						{ display( provider.regions_count ) } / { display( provider.sizes_count ) }
					</span>
					{ provider.counts_cached ? (
						<span className="ml-1 text-xs text-ink-400">(cached)</span>
					) : null}
				</Field>
			</dl>

			{ enabled.length > 0 ? (
				<div className="mt-4 flex flex-wrap gap-1.5">
					{ enabled.map( ( feature ) => (
						<Badge key={ feature } tone="brand">
							{ feature }
						</Badge>
					) ) }
				</div>
			) : null }

			{ open ? (
				<form onSubmit={ submit } className="mt-5 space-y-4 border-t border-surface-border pt-5">
					{ usesApiKey ? (
						<SecretField
							id={ `api_key_${ provider.slug }` }
							label="API key"
							value={ values.api_key }
							onChange={ ( next ) => setValues( ( current ) => ( { ...current, api_key: next } ) ) }
							stored={ provider.has_api_key }
							help="Encrypted before it reaches the options table. Leave blank to keep the stored key."
						/>
					) : (
						<>
							<div>
								<label
									htmlFor={ `user_name_${ provider.slug }` }
									className="mb-1 block text-sm font-medium text-ink-800"
								>
									Account user name
								</label>
								<input
									id={ `user_name_${ provider.slug }` }
									type="text"
									value={ values.user_name }
									onChange={ update( 'user_name' ) }
									placeholder={ provider.has_credentials ? 'Leave blank to keep the stored name' : 'UpCloud account or API sub-account' }
									className={ INPUT_CLASS }
									autoComplete="off"
									spellCheck="false"
								/>
							</div>

							<SecretField
								id={ `user_password_${ provider.slug }` }
								label="Account password"
								value={ values.user_password }
								onChange={ ( next ) => setValues( ( current ) => ( { ...current, user_password: next } ) ) }
								stored={ provider.has_user_password }
								help="Encrypted before it reaches the options table. Use an API-only sub-account so the root password is never stored here."
							/>
						</>
					) }

					<div>
						<label
							htmlFor={ `ssh_key_id_${ provider.slug }` }
							className="mb-1 block text-sm font-medium text-ink-800"
						>
							SSH key id
						</label>
						<input
							id={ `ssh_key_id_${ provider.slug }` }
							type="text"
							value={ values.ssh_key_id }
							onChange={ update( 'ssh_key_id' ) }
							placeholder={ provider.has_ssh_key_id ? 'Leave blank to keep the stored id' : 'Provider-side key id' }
							className={ INPUT_CLASS }
							autoComplete="off"
							spellCheck="false"
						/>
					</div>

					<div>
						<label
							htmlFor={ `ssh_key_${ provider.slug }` }
							className="mb-1 block text-sm font-medium text-ink-800"
						>
							Private SSH key
						</label>
						<textarea
							id={ `ssh_key_${ provider.slug }` }
							rows={ 4 }
							value={ values.ssh_key }
							onChange={ update( 'ssh_key' ) }
							placeholder={ provider.has_ssh_key ? 'Leave blank to keep the stored key' : '-----BEGIN OPENSSH PRIVATE KEY-----' }
							className={ `${ INPUT_CLASS } font-mono text-xs` }
							spellCheck="false"
							autoComplete="off"
						/>
						<p className="mt-1 text-xs text-ink-500">
							Paste the whole key including the header and footer lines.
						</p>
					</div>

					<div>
						<label
							htmlFor={ `notes_${ provider.slug }` }
							className="mb-1 block text-sm font-medium text-ink-800"
						>
							Notes
						</label>
						<input
							id={ `notes_${ provider.slug }` }
							type="text"
							value={ values.notes }
							onChange={ update( 'notes' ) }
							placeholder="Which account or project this key belongs to"
							className={ INPUT_CLASS }
						/>
					</div>

					<div className="flex gap-2">
						<Button
							type="submit"
							variant="primary"
							busy={ busy === `save:${ provider.slug }` }
							disabled={ busy !== '' }
						>
							Save credentials
						</Button>
						<Button variant="ghost" onClick={ () => setOpen( false ) } disabled={ busy !== '' }>
							Done
						</Button>
					</div>
				</form>
			) : null }
		</Card>
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
