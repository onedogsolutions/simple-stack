/**
 * Deploy a WordPress site.
 *
 * Uses `POST /sites`, which resolves to `install_wp_validate( $args )` — the same
 * validation routine the legacy install popup used, so the queued command and the
 * resulting pending task are identical.
 */

import { useEffect, useState } from 'react';

import AppShell from '../components/AppShell.jsx';
import Button from '../components/Button.jsx';
import SecretField from '../components/SecretField.jsx';
import { Card, ErrorPanel } from '../components/Card.jsx';
import { useToast } from '../components/Toast.jsx';
import { api } from '../lib/api.js';
import { navigate } from '../lib/router.js';

const INPUT_CLASS =
	'w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm placeholder:text-ink-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';

/**
 * Suggest a random password strong enough for wp-admin.
 *
 * @return {string} Password.
 */
function suggestPassword() {
	const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*';
	const bytes = new Uint32Array( 24 );

	if ( window.crypto && window.crypto.getRandomValues ) {
		window.crypto.getRandomValues( bytes );
	} else {
		bytes.forEach( ( _, index ) => {
			bytes[ index ] = Math.floor( Math.random() * 2 ** 32 );
		} );
	}

	return Array.from( bytes, ( value ) => alphabet[ value % alphabet.length ] ).join( '' );
}

/**
 * @return {JSX.Element} Deploy site route.
 */
export default function DeploySite() {
	const toast = useToast();

	const [ servers, setServers ] = useState( [] );
	const [ serversError, setServersError ] = useState( null );
	const [ values, setValues ] = useState( {
		server_id: '',
		domain: '',
		admin_user: 'admin',
		admin_email: '',
		admin_password: '',
		wp_version: 'latest',
		locale: 'en_US',
	} );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		api.servers
			.list( { per_page: 100 } )
			.then( ( data ) => {
				const items = data.items || [];
				setServers( items );

				if ( items.length > 0 ) {
					setValues( ( current ) => ( {
						...current,
						server_id: current.server_id || String( items[ 0 ].id ),
					} ) );
				}
			} )
			.catch( ( cause ) => setServersError( cause ) );
	}, [] );

	const update = ( key ) => ( event ) =>
		setValues( ( current ) => ( { ...current, [ key ]: event.target.value } ) );

	const submit = async ( event ) => {
		event.preventDefault();
		setBusy( true );
		setError( null );

		try {
			const result = await api.sites.deploy( {
				server_id: Number( values.server_id ),
				domain: values.domain,
				admin_user: values.admin_user,
				admin_email: values.admin_email,
				admin_password: values.admin_password,
				wp_version: values.wp_version,
				locale: values.locale,
			} );

			toast.push( `Installation queued for ${ result.domain || values.domain }.`, 'success' );
			navigate( `/sites/${ result.app_id }` );
		} catch ( cause ) {
			setError( cause );
			toast.push( cause.message, 'error' );
		} finally {
			setBusy( false );
		}
	};

	const noServers = ! serversError && servers.length === 0;

	return (
		<AppShell
			path="/sites/new"
			title="Deploy site"
			subtitle="Installs WordPress on an existing OpenLiteSpeed server."
			breadcrumbs={ [ { label: 'Sites', path: '/sites' }, { label: 'New site' } ] }
		>
			<div className="max-w-3xl space-y-4">
				{ serversError ? <ErrorPanel error={ serversError } /> : null }

				{ noServers ? (
					<Card title="No servers available">
						<p className="text-sm text-ink-500">Provision a server before deploying a site.</p>
						<Button className="mt-3" variant="primary" onClick={ () => navigate( '/servers/new' ) }>
							Provision server
						</Button>
					</Card>
				) : null }

				{ servers.length > 0 ? (
					<Card title="Site details">
						<form onSubmit={ submit } className="space-y-4">
							<div>
								<label htmlFor="server_id" className="mb-1 block text-sm font-medium text-ink-800">
									Server
								</label>
								<select
									id="server_id"
									value={ values.server_id }
									onChange={ update( 'server_id' ) }
									className={ INPUT_CLASS }
								>
									{ servers.map( ( server ) => (
										<option key={ server.id } value={ server.id }>
											{ server.name || `Server ${ server.id }` } — { server.region || 'unknown' }
										</option>
									) ) }
								</select>
							</div>

							<div>
								<label htmlFor="domain" className="mb-1 block text-sm font-medium text-ink-800">
									Domain<span className="ml-0.5 text-negative-600">*</span>
								</label>
								<input
									id="domain"
									type="text"
									required
									placeholder="example.com"
									value={ values.domain }
									onChange={ update( 'domain' ) }
									className={ INPUT_CLASS }
								/>
								<p className="mt-1 text-xs text-ink-500">
									The DNS record must already point at the server before SSL can be issued.
								</p>
							</div>

							<div className="grid gap-4 sm:grid-cols-2">
								<div>
									<label htmlFor="admin_user" className="mb-1 block text-sm font-medium text-ink-800">
										Admin user<span className="ml-0.5 text-negative-600">*</span>
									</label>
									<input
										id="admin_user"
										type="text"
										required
										value={ values.admin_user }
										onChange={ update( 'admin_user' ) }
										className={ INPUT_CLASS }
										autoComplete="off"
									/>
								</div>

								<div>
									<label htmlFor="admin_email" className="mb-1 block text-sm font-medium text-ink-800">
										Admin email<span className="ml-0.5 text-negative-600">*</span>
									</label>
									<input
										id="admin_email"
										type="email"
										required
										placeholder="owner@example.com"
										value={ values.admin_email }
										onChange={ update( 'admin_email' ) }
										className={ INPUT_CLASS }
									/>
								</div>
							</div>

							<div>
								<SecretField
									id="admin_password"
									label="Admin password"
									value={ values.admin_password }
									onChange={ ( next ) => setValues( ( current ) => ( { ...current, admin_password: next } ) ) }
									help="Generated locally; it is never echoed back by the REST layer or written to a log."
								/>
								<button
									type="button"
									onClick={ () =>
										setValues( ( current ) => ( { ...current, admin_password: suggestPassword() } ) )
									}
									className="mt-2 text-xs font-medium text-brand-700 hover:underline"
								>
									Generate a strong password
								</button>
							</div>

							<div className="grid gap-4 sm:grid-cols-2">
								<div>
									<label htmlFor="wp_version" className="mb-1 block text-sm font-medium text-ink-800">
										WordPress version
									</label>
									<input
										id="wp_version"
										type="text"
										value={ values.wp_version }
										onChange={ update( 'wp_version' ) }
										className={ INPUT_CLASS }
									/>
								</div>
								<div>
									<label htmlFor="locale" className="mb-1 block text-sm font-medium text-ink-800">
										Locale
									</label>
									<input
										id="locale"
										type="text"
										value={ values.locale }
										onChange={ update( 'locale' ) }
										className={ INPUT_CLASS }
									/>
								</div>
							</div>

							{ error ? <ErrorPanel error={ error } /> : null }

							<div className="flex gap-2">
								<Button type="submit" variant="primary" busy={ busy }>
									Deploy
								</Button>
								<Button variant="ghost" onClick={ () => navigate( '/sites' ) } disabled={ busy }>
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
