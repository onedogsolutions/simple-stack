/* Modified for Simple Stack by One Dog Solutions on 2026-11-23. */
/**
 * Renders any tab schema returned by `GET /{servers|sites}/{id}/tabs`.
 *
 * This is the component that makes the hard cutover possible: all 22 server tabs and
 * 29 site tabs are described by the same Meta Box field arrays, so one generic renderer
 * covers them. Tab-specific work is limited to the field types below.
 *
 * Action buttons reproduce the legacy jQuery behaviour exactly
 * (`sstk-wpapp-admin-common.js`): honour the confirmation prompt, gather the fields
 * named by `data-sstk-fields`, and post them as the tab handler's `params`.
 */

import { useEffect, useMemo, useState } from 'react';

import Button from './Button.jsx';
import { Card, EmptyState, Skeleton } from './Card.jsx';
import {
	collectActionParams,
	dependencyIds,
	elementsForTab,
	groupIntoRows,
	indexById,
	initialValues,
} from '../lib/schema.js';

const WIDTHS = {
	full: 'grid-cols-1',
	half: 'grid-cols-1 lg:grid-cols-2',
	third: 'grid-cols-1 lg:grid-cols-3',
	'two-thirds': 'grid-cols-1 lg:grid-cols-3',
};

const INPUT_CLASS =
	'w-full rounded-card border border-surface-border bg-surface-base px-3 py-2 text-sm text-ink-900 placeholder:text-ink-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 disabled:bg-surface-muted';

/**
 * @param {Object}   props
 * @param {Array}    props.tabs      Tab descriptors: { slug, label }.
 * @param {Array}    props.fields    Raw field definitions.
 * @param {Function} props.onAction  (action, params, field, files) => Promise
 * @param {Function} props.onShowLog Optional handler to open the log drawer.
 * @param {boolean}  props.loading   Show placeholders.
 * @param {string}   props.disabledReason Shown instead of controls when the target is unavailable.
 * @return {JSX.Element} Tabbed panel.
 */
export default function TabRenderer( {
	tabs,
	fields,
	onAction,
	onShowLog,
	loading = false,
	disabledReason = '',
} ) {
	const list = useMemo( () => ( Array.isArray( tabs ) ? tabs : [] ), [ tabs ] );
	const fieldList = useMemo( () => ( Array.isArray( fields ) ? fields : [] ), [ fields ] );

	const [ active, setActive ] = useState( '' );
	const [ values, setValues ] = useState( () => initialValues( fieldList ) );
	const [ files, setFiles ] = useState( {} );
	const [ pending, setPending ] = useState( '' );
	const [ openCards, setOpenCards ] = useState( {} );

	useEffect( () => {
		setValues( initialValues( fieldList ) );
	}, [ fieldList ] );

	useEffect( () => {
		if ( list.length === 0 ) {
			setActive( '' );
			return;
		}

		setActive( ( current ) => ( list.some( ( tab ) => tab.slug === current ) ? current : list[ 0 ].slug ) );
	}, [ list ] );

	const index = useMemo( () => indexById( fieldList ), [ fieldList ] );

	const rows = useMemo(
		() => ( active ? groupIntoRows( elementsForTab( fieldList, active ) ) : [] ),
		[ fieldList, active ]
	);

	if ( loading ) {
		return (
			<Card>
				<Skeleton rows={ 6 } />
			</Card>
		);
	}

	if ( list.length === 0 ) {
		return (
			<EmptyState
				title="No tabs are available"
				message="This record exposes no tabs for the current user or site state."
			/>
		);
	}

	const setValue = ( id, value ) => {
		setValues( ( current ) => ( { ...current, [ id ]: value } ) );
	};

	const setFile = ( id, file ) => {
		setFiles( ( current ) => ( { ...current, [ id ]: file } ) );
	};

	const run = async ( field, overrides = {} ) => {
		if ( disabledReason || pending ) {
			return;
		}

		if ( field.confirm && ! window.confirm( field.confirm ) ) {
			return;
		}

		const params = collectActionParams( field, index, values, overrides );

		/*
		 * The sFTP and system-user tabs post an uploaded public key as `file`, exactly as
		 * the legacy client did (it read the key from `$_FILES['file']`).
		 */
		const uploads = {};

		dependencyIds( field ).forEach( ( id ) => {
			if ( files[ id ] ) {
				uploads.file = files[ id ];
			}
		} );

		const key = `${ field.action }:${ field.id }`;

		setPending( key );

		try {
			await onAction( field.action, params, field, uploads );

			if ( field.showLog && onShowLog ) {
				onShowLog( field );
			}
		} finally {
			setPending( '' );
		}
	};

	return (
		<div>
			<div className="mb-4 flex flex-wrap gap-1 border-b border-surface-border">
				{ list.map( ( tab ) => {
					const isActive = tab.slug === active;

					return (
						<button
							key={ tab.slug }
							type="button"
							onClick={ () => setActive( tab.slug ) }
							aria-current={ isActive ? 'page' : undefined }
							className={ `-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors ${
								isActive
									? 'border-brand-600 text-brand-700'
									: 'border-transparent text-ink-500 hover:border-surface-border hover:text-ink-800'
							}` }
						>
							{ tab.label }
						</button>
					);
				} ) }
			</div>

			{ disabledReason ? (
				<div className="mb-4 rounded-card border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700">
					{ disabledReason }
				</div>
			) : null }

			<div className="space-y-4">
				{ rows.map( ( row, rowIndex ) => {
					const gridClass = WIDTHS[ row.width ] || WIDTHS.full;

					return (
						<div key={ `row-${ rowIndex }` } className={ `grid gap-4 ${ gridClass }` }>
							<Card borderless={ row.borderless } className={ row.borderless ? '' : '' }>
								<div className="space-y-4">
									{ row.items.map( ( field, itemIndex ) => (
										<FieldControl
											key={ `${ field.id || field.label }-${ itemIndex }` }
											field={ field }
											value={ values[ field.id ] }
											onChange={ ( next ) => setValue( field.id, next ) }
											onFile={ ( file ) => setFile( field.id, file ) }
											onAction={
												field.kind === 'toggle'
													? field.action
														? ( next ) => run( field, { [ field.id ]: next } )
														: undefined
													: () => run( field )
											}
											pending={ pending === `${ field.action }:${ field.id }` }
											disabled={ Boolean( disabledReason ) }
											expanded={ Boolean( openCards[ `${ rowIndex }:${ itemIndex }` ] ) }
											onToggleExpanded={ () =>
												setOpenCards( ( current ) => ( {
													...current,
													[ `${ rowIndex }:${ itemIndex }` ]: ! current[ `${ rowIndex }:${ itemIndex }` ],
												} ) )
											}
										/>
									) ) }
								</div>
							</Card>
						</div>
					);
				} ) }
			</div>
		</div>
	);
}

/**
 * Render one control descriptor.
 *
 * @param {Object} props Control props.
 * @return {JSX.Element|null} Control element.
 */
function FieldControl( { field, value, onChange, onFile, onAction, pending, disabled, expanded, onToggleExpanded } ) {
	if ( field.kind === 'hidden' ) {
		return null;
	}

	if ( field.kind === 'label' ) {
		return (
			<div className="border-b border-surface-border pb-2">
				{ field.label ? (
					<h3 className="text-sm font-semibold text-ink-900">{ field.label }</h3>
				) : null }
				{ field.description || field.raw ? (
					<div
						className="mt-1 text-sm leading-6 text-ink-500 [&_details]:mt-1 [&_summary]:cursor-pointer [&_summary]:font-medium [&_a]:text-brand-600 [&_a]:underline"
						dangerouslySetInnerHTML={ { __html: field.raw || field.description } }
					/>
				) : null }
			</div>
		);
	}

	if ( field.kind === 'html' ) {
		if ( ! field.raw ) {
			return null;
		}

		/* Server-produced documentation markup (for example the console help details). */
		return (
			<div
				className="text-sm leading-6 text-ink-600 [&_details]:mt-1 [&_summary]:cursor-pointer [&_summary]:font-medium [&_a]:text-brand-600 [&_a]:underline [&_code]:rounded [&_code]:bg-surface-muted [&_code]:px-1 [&_code]:font-mono [&_code]:text-xs"
				dangerouslySetInnerHTML={ { __html: field.raw } }
			/>
		);
	}

	if ( field.kind === 'readonly' ) {
		return (
			<div>
				{ field.label ? <p className="mb-1 text-sm font-medium text-ink-800">{ field.label }</p> : null }
				<p className="text-sm text-ink-500">
					{ value ? String( value ) : 'Managed on the server; not editable here.' }
				</p>
			</div>
		);
	}

	if ( field.kind === 'action' ) {
		const isLogAction = field.showLog;

		return (
			<div className="flex flex-wrap items-center gap-3">
				<Button
					variant={ isLogAction ? 'primary' : 'secondary' }
					onClick={ onAction }
					busy={ pending }
					disabled={ disabled }
				>
					{ field.label || field.action }
				</Button>
				{ field.description ? (
					<p className="text-sm text-ink-500">{ field.description }</p>
				) : null }
				{ field.logMessage && expanded ? (
					<p className="w-full text-xs text-ink-500">{ field.logMessage }</p>
				) : null }
			</div>
		);
	}

	const label = field.label ? (
		<label htmlFor={ field.id } className="mb-1 block text-sm font-medium text-ink-800">
			{ field.label }
		</label>
	) : null;

	const help = field.description ? (
		<p className="mt-1 text-xs text-ink-500">{ field.description }</p>
	) : null;

	if ( field.kind === 'toggle' ) {
		return (
			<div className="flex items-start gap-3">
				<input
					id={ field.id }
					type="checkbox"
					className="mt-1 h-4 w-4 rounded border-surface-border text-brand-600 focus:ring-brand-500"
					checked={ Boolean( value ) && value !== 'off' && value !== '' ? true : Boolean( value ) }
					onChange={ ( event ) => {
						const next = event.target.checked;
						onChange( next );

						if ( field.action && onAction ) {
							onAction( next );
						}
					} }
					disabled={ disabled || pending }
				/>
				<div>
					<label htmlFor={ field.id } className="text-sm font-medium text-ink-800">
						{ field.label || field.param }
					</label>
					{ help }
				</div>
			</div>
		);
	}

	const common = {
		id: field.id,
		className: INPUT_CLASS,
		value: value === undefined || value === null || typeof value === 'boolean' ? '' : String( value ),
		onChange: ( event ) => onChange( event.target.value ),
		disabled,
	};

	if ( field.kind === 'select' ) {
		return (
			<div>
				{ label }
				<select { ...common }>
					<option value="">Select…</option>
					{ field.options.map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
				{ help }
			</div>
		);
	}

	if ( field.kind === 'textarea' ) {
		return (
			<div>
				{ label }
				<textarea { ...common } rows={ 4 } placeholder={ field.placeholder } />
				{ help }
			</div>
		);
	}

	if ( field.kind === 'password' ) {
		return (
			<div>
				{ label }
				<input { ...common } type="password" placeholder={ field.placeholder } autoComplete="new-password" />
				{ help }
			</div>
		);
	}

	if ( field.kind === 'file' ) {
		return (
			<div>
				{ label }
				<input
					id={ field.id }
					type="file"
					className="w-full text-sm text-ink-700 file:mr-3 file:rounded-card file:border file:border-surface-border file:bg-surface-base file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink-800 hover:file:border-brand-400"
					onChange={ ( event ) => onFile( event.target.files && event.target.files[ 0 ] ? event.target.files[ 0 ] : null ) }
					disabled={ disabled }
				/>
				{ help }
			</div>
		);
	}

	let inputType = 'text';

	if ( field.kind === 'number' ) {
		inputType = 'number';
	} else if ( field.kind === 'temporal' ) {
		inputType = field.type === 'datetime' ? 'datetime-local' : field.type;
	} else if ( field.type === 'email' || field.type === 'url' ) {
		inputType = field.type;
	}

	return (
		<div>
			{ label }
			<input
				{ ...common }
				type={ inputType }
				placeholder={ field.placeholder }
				maxLength={ field.maxlength }
			/>
			{ help }
		</div>
	);
}
