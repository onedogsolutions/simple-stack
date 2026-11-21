/**
 * Toast notifications.
 *
 * A context provider plus a `useToast()` hook, so any route can report the outcome of
 * an action without rendering its own banner.
 */

import { createContext, useCallback, useContext, useMemo, useState } from 'react';

const ToastContext = createContext( { push: () => {} } );

const TONES = {
	success: 'border-positive-200 bg-positive-50 text-positive-700',
	error: 'border-negative-200 bg-negative-50 text-negative-700',
	info: 'border-brand-200 bg-brand-50 text-brand-700',
};

let nextId = 1;

/**
 * @param {Object}      props
 * @param {JSX.Element} props.children Application tree.
 * @return {JSX.Element} Provider element.
 */
export function ToastProvider( { children } ) {
	const [ toasts, setToasts ] = useState( [] );

	const dismiss = useCallback( ( id ) => {
		setToasts( ( current ) => current.filter( ( toast ) => toast.id !== id ) );
	}, [] );

	const push = useCallback(
		( message, tone = 'info', ttl = 6000 ) => {
			const id = nextId++;
			setToasts( ( current ) => [ ...current, { id, message: String( message ), tone } ] );

			if ( ttl > 0 ) {
				window.setTimeout( () => dismiss( id ), ttl );
			}

			return id;
		},
		[ dismiss ]
	);

	const value = useMemo( () => ( { push, dismiss } ), [ push, dismiss ] );

	return (
		<ToastContext.Provider value={ value }>
			{ children }

			<div className="pointer-events-none fixed right-4 top-12 z-50 flex w-80 flex-col gap-2">
				{ toasts.map( ( toast ) => (
					<div
						key={ toast.id }
						role="status"
						className={ `pointer-events-auto flex items-start justify-between gap-3 rounded-card border px-3 py-2 text-sm shadow-sm ${
							TONES[ toast.tone ] || TONES.info
						}` }
					>
						<span className="whitespace-pre-line">{ toast.message }</span>
						<button
							type="button"
							onClick={ () => dismiss( toast.id ) }
							className="shrink-0 text-xs opacity-60 hover:opacity-100"
							aria-label="Dismiss"
						>
							✕
						</button>
					</div>
				) ) }
			</div>
		</ToastContext.Provider>
	);
}

/**
 * @return {{push: Function, dismiss: Function}} Toast API.
 */
export function useToast() {
	return useContext( ToastContext );
}
