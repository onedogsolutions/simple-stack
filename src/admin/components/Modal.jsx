/**
 * Modal dialog and confirmation prompt.
 *
 * Rendered into the app container rather than `document.body` so the Tailwind scoping
 * selector (`#simplestack-admin .utility`) still matches every element inside it.
 */

import { useEffect, useRef } from 'react';

import Button from './Button.jsx';

/**
 * @param {Object}      props
 * @param {boolean}     props.open     Whether the dialog is visible.
 * @param {string}      props.title    Dialog heading.
 * @param {Function}    props.onClose  Close handler.
 * @param {JSX.Element} props.footer   Optional footer content.
 * @return {JSX.Element|null} Dialog element.
 */
export default function Modal( { open, title, onClose, footer, children } ) {
	const panel = useRef( null );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				onClose();
			}
		};

		document.addEventListener( 'keydown', onKeyDown );

		if ( panel.current ) {
			const focusable = panel.current.querySelector(
				'input, select, textarea, button, [tabindex]:not([tabindex="-1"])'
			);

			if ( focusable ) {
				focusable.focus();
			}
		}

		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ open, onClose ] );

	if ( ! open ) {
		return null;
	}

	return (
		<div
			className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-900/40 p-6"
			role="presentation"
			onMouseDown={ ( event ) => {
				if ( event.target === event.currentTarget ) {
					onClose();
				}
			} }
		>
			<div
				ref={ panel }
				role="dialog"
				aria-modal="true"
				aria-label={ title }
				className="mt-10 w-full max-w-xl rounded-card border border-surface-border bg-surface-base shadow-xl"
			>
				<header className="flex items-start justify-between gap-4 border-b border-surface-border px-5 py-4">
					<h2 className="text-base font-semibold text-ink-900">{ title }</h2>
					<Button variant="ghost" size="sm" onClick={ onClose } aria-label="Close">
						✕
					</Button>
				</header>

				<div className="px-5 py-4">{ children }</div>

				{ footer ? (
					<footer className="flex justify-end gap-2 border-t border-surface-border px-5 py-3">
						{ footer }
					</footer>
				) : null }
			</div>
		</div>
	);
}

/**
 * Confirmation dialog for destructive or irreversible actions.
 *
 * @param {Object}   props
 * @param {boolean}  props.open    Whether the prompt is visible.
 * @param {string}   props.title   Prompt heading.
 * @param {string}   props.message Body text.
 * @param {string}   props.confirmLabel Confirm button label.
 * @param {Function} props.onConfirm Confirmed handler.
 * @param {Function} props.onCancel  Cancelled handler.
 * @param {boolean}  props.busy    Pending state.
 * @return {JSX.Element} Confirmation dialog.
 */
export function ConfirmDialog( {
	open,
	title = 'Are you sure?',
	message,
	confirmLabel = 'Confirm',
	onConfirm,
	onCancel,
	busy = false,
} ) {
	return (
		<Modal
			open={ open }
			title={ title }
			onClose={ onCancel }
			footer={
				<>
					<Button variant="secondary" onClick={ onCancel } disabled={ busy }>
						Cancel
					</Button>
					<Button variant="primary" onClick={ onConfirm } busy={ busy }>
						{ confirmLabel }
					</Button>
				</>
			}
		>
			<p className="text-sm text-ink-600">{ message }</p>
		</Modal>
	);
}
