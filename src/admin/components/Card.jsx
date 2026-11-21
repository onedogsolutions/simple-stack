/**
 * Small presentational primitives: Card, Badge, Skeleton, EmptyState.
 */

const TONES = {
	positive: 'bg-positive-50 text-positive-700 border-positive-200',
	warning: 'bg-warning-50 text-warning-700 border-warning-200',
	negative: 'bg-negative-50 text-negative-700 border-negative-200',
	neutral: 'bg-surface-muted text-ink-600 border-surface-border',
	brand: 'bg-brand-50 text-brand-700 border-brand-200',
};

/**
 * @param {Object}  props
 * @param {string}  props.title    Card heading.
 * @param {string}  props.subtitle Optional supporting line.
 * @param {boolean} props.borderless Drop the border and padding.
 * @param {JSX.Element} props.action Optional header action.
 * @return {JSX.Element} Card element.
 */
export function Card( { title, subtitle, action, borderless = false, className = '', children } ) {
	const classes = borderless
		? `rounded-card ${ className }`
		: `rounded-card border border-surface-border bg-surface-base p-5 ${ className }`;

	return (
		<section className={ classes }>
			{ title || action ? (
				<header className="mb-4 flex flex-wrap items-start justify-between gap-3">
					<div>
						{ title ? <h2 className="text-base font-semibold text-ink-900">{ title }</h2> : null }
						{ subtitle ? <p className="mt-1 text-sm text-ink-500">{ subtitle }</p> : null }
					</div>
					{ action }
				</header>
			) : null }
			{ children }
		</section>
	);
}

/**
 * @param {Object} props
 * @param {string} props.tone     positive | warning | negative | neutral | brand
 * @param {string} props.children Label.
 * @return {JSX.Element} Badge element.
 */
export function Badge( { tone = 'neutral', children } ) {
	const classes = `inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium ${
		TONES[ tone ] || TONES.neutral
	}`;

	return <span className={ classes }>{ children }</span>;
}

/**
 * @param {Object} props
 * @param {number} props.rows Number of placeholder rows.
 * @return {JSX.Element} Skeleton block.
 */
export function Skeleton( { rows = 3 } ) {
	return (
		<div className="space-y-2" aria-hidden="true">
			{ Array.from( { length: rows } ).map( ( _, index ) => (
				<div
					key={ index }
					className="h-4 animate-pulse rounded bg-surface-muted"
					style={ { width: `${ 100 - index * 12 }%` } }
				/>
			) ) }
		</div>
	);
}

/**
 * @param {Object}      props
 * @param {string}      props.title    Headline.
 * @param {string}      props.message  Supporting text.
 * @param {JSX.Element} props.action   Optional call to action.
 * @return {JSX.Element} Empty state block.
 */
export function EmptyState( { title, message, action } ) {
	return (
		<div className="rounded-card border border-dashed border-surface-border bg-surface-base px-6 py-12 text-center">
			<h3 className="text-sm font-semibold text-ink-800">{ title }</h3>
			{ message ? <p className="mx-auto mt-2 max-w-md text-sm text-ink-500">{ message }</p> : null }
			{ action ? <div className="mt-4 flex justify-center">{ action }</div> : null }
		</div>
	);
}

/**
 * Inline failure block for a failed request.
 *
 * @param {Object} props
 * @param {Error}  props.error   The caught error.
 * @param {Function} props.onRetry Optional retry handler.
 * @return {JSX.Element} Error panel.
 */
export function ErrorPanel( { error, onRetry } ) {
	return (
		<div className="rounded-card border border-negative-200 bg-negative-50 p-4">
			<p className="text-sm font-medium text-negative-700">
				{ ( error && error.message ) || 'Something went wrong.' }
			</p>
			{ error && error.code ? (
				<p className="mt-1 font-mono text-xs text-negative-600">{ error.code }</p>
			) : null }
			{ onRetry ? (
				<button
					type="button"
					onClick={ onRetry }
					className="mt-3 rounded-card border border-negative-300 px-2.5 py-1 text-xs font-medium text-negative-700 hover:bg-negative-100"
				>
					Try again
				</button>
			) : null }
		</div>
	);
}
