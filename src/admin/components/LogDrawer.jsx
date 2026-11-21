/**
 * Live log drawer.
 *
 * Replaces the legacy `show-log-console` popup, which streamed `admin-ajax.php` output
 * into a Magnific Popup window. This version polls `GET /servers/{id}/logs` with a
 * `since` cursor, so it works for server actions and for the site actions whose legacy
 * implementation opened a console.
 *
 * The drawer is inline rather than a modal so an operator can keep operating the tab
 * while a provisioning command runs.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

import Button from './Button.jsx';
import { api } from '../lib/api.js';
import { formatDate } from '../lib/format.js';

const POLL_MS = 15000;

const TONE_CLASSES = {
	command: 'border-ink-100 bg-surface-muted text-ink-800',
	ssh: 'border-brand-200 bg-brand-50 text-ink-800',
	error: 'border-negative-200 bg-negative-50 text-negative-800',
};

/**
 * @param {Object}   props
 * @param {number}   props.serverId  Server whose log stream to follow.
 * @param {string}   props.title     Drawer heading.
 * @param {boolean}  props.open      Whether the drawer is expanded.
 * @param {Function} props.onClose   Collapse handler.
 * @param {string}   props.kickoffMessage Optional first line shown before data arrives.
 * @return {JSX.Element} Drawer element.
 */
export default function LogDrawer( { serverId, title = 'Server log', open, onClose, kickoffMessage = '' } ) {
	const [ entries, setEntries ] = useState( [] );
	const [ cursor, setCursor ] = useState( '' );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( false );

	const scrollRef = useRef( null );
	const timerRef = useRef( null );
	const cursorRef = useRef( '' );

	const fetchPage = useCallback(
		async ( since ) => {
			if ( ! serverId ) {
				return;
			}

			setLoading( true );

			try {
				const data = await api.logs.forServer( serverId, {
					type: 'all',
					since: since || '',
					limit: 50,
				} );

				const items = Array.isArray( data.items ) ? data.items : [];

				if ( items.length > 0 ) {
					/* Newest first from the API; reverse so the drawer reads top-down. */
					setEntries( ( current ) => [ ...items.slice().reverse(), ...current ] );
				}

				if ( data.cursor ) {
					cursorRef.current = data.cursor;
					setCursor( data.cursor );
				}

				setError( null );
			} catch ( cause ) {
				setError( cause );
			} finally {
				setLoading( false );
			}
		},
		[ serverId ]
	);

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		fetchPage( cursorRef.current );

		timerRef.current = window.setInterval(
			() => fetchPage( cursorRef.current ),
			POLL_MS
		);

		return () => {
			if ( timerRef.current ) {
				window.clearInterval( timerRef.current );
				timerRef.current = null;
			}
		};
	}, [ open, fetchPage ] );

	useEffect( () => {
		if ( scrollRef.current ) {
			scrollRef.current.scrollTop = 0;
		}
	}, [ entries.length ] );

	if ( ! open ) {
		return null;
	}

	const download = () => {
		const text = entries
			.map( ( entry ) => `[${ entry.date_gmt }] ${ entry.type }: ${ entry.title }\n${ entry.body || '' }` )
			.join( '\n\n' );

		const blob = new Blob( [ text ], { type: 'text/plain;charset=utf-8' } );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );

		link.href = url;
		link.download = `simplestack-server-${ serverId }-log.txt`;
		link.click();
		URL.revokeObjectURL( url );
	};

	return (
		<section className="rounded-card border border-surface-border bg-surface-base">
			<header className="flex flex-wrap items-center justify-between gap-2 border-b border-surface-border px-4 py-2.5">
				<div className="flex items-center gap-2">
					<h3 className="text-sm font-semibold text-ink-900">{ title }</h3>
					{ loading ? (
						<span className="text-xs text-ink-400">Refreshing…</span>
					) : (
						<span className="text-xs text-ink-400">
							{ cursor ? `Up to ${ formatDate( cursor ) }` : 'Waiting for output' }
						</span>
					) }
				</div>
				<div className="flex items-center gap-2">
					<Button size="sm" variant="ghost" onClick={ () => fetchPage( cursorRef.current ) } busy={ loading }>
						Refresh
					</Button>
					<Button size="sm" variant="ghost" onClick={ download } disabled={ entries.length === 0 }>
						Download
					</Button>
					<Button size="sm" variant="ghost" onClick={ onClose }>
						Hide
					</Button>
				</div>
			</header>

			<div
				ref={ scrollRef }
				className="max-h-80 space-y-2 overflow-y-auto px-4 py-3 font-mono text-xs leading-5"
			>
				{ kickoffMessage && entries.length === 0 ? (
					<p className="whitespace-pre-line text-ink-500">{ kickoffMessage }</p>
				) : null }

				{ error ? (
					<p className="text-negative-700">{ error.message }</p>
				) : null }

				{ entries.length === 0 && ! error && ! kickoffMessage ? (
					<p className="text-ink-400">
						No log entries yet. Provisioning output appears here as the server reports back.
					</p>
				) : null }

				{ entries.map( ( entry ) => (
					<article
						key={ entry.id }
						className={ `rounded border px-3 py-2 ${ TONE_CLASSES[ entry.type ] || TONE_CLASSES.command }` }
					>
						<div className="flex flex-wrap items-center justify-between gap-2 text-[11px] uppercase tracking-wide opacity-70">
							<span>{ entry.type }</span>
							<span>{ entry.date_gmt } GMT</span>
						</div>
						{ entry.title ? <p className="mt-1 font-semibold">{ entry.title }</p> : null }
						{ entry.body ? <pre className="mt-1 whitespace-pre-wrap break-words">{ entry.body }</pre> : null }
					</article>
				) ) }
			</div>
		</section>
	);
}
