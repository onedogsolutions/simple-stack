/**
 * Credential input.
 *
 * Passwords and API keys are never round-tripped: the REST layer blanks password-typed
 * tab fields and reports provider credentials as `has_api_key` / `has_ssh_key` booleans.
 * This control therefore always starts empty and shows whether a value is already
 * stored, so an operator can tell the difference between "unset" and "unchanged".
 */

import { useState } from 'react';

import { Badge } from './Card.jsx';

/**
 * @param {Object}   props
 * @param {string}   props.id        Element id.
 * @param {string}   props.label     Field label.
 * @param {string}   props.value     Current value.
 * @param {Function} props.onChange  Change handler.
 * @param {boolean}  props.stored    Whether a value already exists server side.
 * @param {string}   props.help      Optional help text.
 * @param {string}   props.placeholder Placeholder.
 * @param {boolean}  props.disabled  Disable the input.
 * @return {JSX.Element} Secret field.
 */
export default function SecretField( {
	id,
	label,
	value,
	onChange,
	stored = false,
	help = '',
	placeholder = '',
	disabled = false,
} ) {
	const [ revealed, setRevealed ] = useState( false );

	return (
		<div>
			<div className="mb-1 flex items-center justify-between gap-2">
				<label htmlFor={ id } className="text-sm font-medium text-ink-800">
					{ label }
				</label>
				{ stored ? <Badge tone="positive">Stored</Badge> : <Badge tone="neutral">Not set</Badge> }
			</div>

			<div className="flex items-stretch gap-2">
				<input
					id={ id }
					type={ revealed ? 'text' : 'password' }
					className="min-w-0 flex-1 rounded-card border border-surface-border bg-surface-base px-3 py-2 font-mono text-sm text-ink-900 placeholder:text-ink-300 focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
					value={ value || '' }
					placeholder={ stored ? 'Leave blank to keep the stored value' : placeholder }
					onChange={ ( event ) => onChange( event.target.value ) }
					disabled={ disabled }
					autoComplete="new-password"
					spellCheck="false"
				/>
				<button
					type="button"
					onClick={ () => setRevealed( ( previous ) => ! previous ) }
					className="shrink-0 rounded-card border border-surface-border px-3 text-xs font-medium text-ink-600 hover:bg-surface-muted"
					disabled={ disabled }
				>
					{ revealed ? 'Hide' : 'Show' }
				</button>
			</div>

			{ help ? <p className="mt-1 text-xs text-ink-500">{ help }</p> : null }
		</div>
	);
}
