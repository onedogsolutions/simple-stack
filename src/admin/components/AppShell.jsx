/**
 * Application chrome: header, primary navigation, breadcrumbs.
 *
 * Deliberately thin. wp-admin still owns the page title, the admin menu and the
 * admin bar; this shell only organises the React content inside `#simplestack-admin`.
 */

import { navigate } from '../lib/router.js';

const NAV = [
	{ path: '/servers', label: 'Servers' },
	{ path: '/sites', label: 'Sites' },
	{ path: '/settings/providers', label: 'Providers' },
	{ path: '/activity', label: 'Activity' },
];

/**
 * @param {Object}      props
 * @param {string}      props.path       Current hash path.
 * @param {string}      props.title      Page heading.
 * @param {string}      props.subtitle   Optional supporting line.
 * @param {Array}       props.breadcrumbs Trail of { label, path }.
 * @param {JSX.Element} props.action     Page-level action.
 * @param {JSX.Element} props.children   Page content.
 * @return {JSX.Element} Shell element.
 */
export default function AppShell( { path, title, subtitle, breadcrumbs = [], action, children } ) {
	const isActive = ( target ) => path === target || path.startsWith( `${ target }/` );

	return (
		<div className="min-h-screen bg-surface-muted px-6 py-6 font-sans text-ink-900">
			<header className="mb-5 flex flex-wrap items-center justify-between gap-3">
				<div>
					<p className="text-xs font-semibold uppercase tracking-wider text-brand-600">SimpleStack</p>
					<h1 className="mt-1 text-2xl font-semibold tracking-tight text-ink-900">{ title }</h1>
					{ subtitle ? <p className="mt-1 text-sm text-ink-500">{ subtitle }</p> : null }
				</div>
				{ action }
			</header>

			<nav className="mb-5 flex flex-wrap gap-1" aria-label="SimpleStack sections">
				{ NAV.map( ( item ) => {
					const active = isActive( item.path );

					return (
						<button
							key={ item.path }
							type="button"
							onClick={ () => navigate( item.path ) }
							aria-current={ active ? 'page' : undefined }
							className={ `rounded-card px-3 py-1.5 text-sm font-medium transition-colors ${
								active
									? 'bg-brand-600 text-white'
									: 'text-ink-600 hover:bg-surface-base hover:text-ink-900'
							}` }
						>
							{ item.label }
						</button>
					);
				} ) }
			</nav>

			{ breadcrumbs.length > 0 ? (
				<nav aria-label="Breadcrumb" className="mb-4 flex flex-wrap items-center gap-1.5 text-xs text-ink-500">
					{ breadcrumbs.map( ( crumb, index ) => (
						<span key={ `${ crumb.label }-${ index }` } className="flex items-center gap-1.5">
							{ index > 0 ? <span aria-hidden="true">/</span> : null }
							{ crumb.path ? (
								<button
									type="button"
									onClick={ () => navigate( crumb.path ) }
									className="hover:text-brand-600 hover:underline"
								>
									{ crumb.label }
								</button>
							) : (
								<span className="text-ink-800">{ crumb.label }</span>
							) }
						</span>
					) ) }
				</nav>
			) : null }

			<main>{ children }</main>
		</div>
	);
}
