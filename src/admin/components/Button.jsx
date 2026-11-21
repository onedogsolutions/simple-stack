/**
 * Button.
 *
 * Tailwind utilities here are all scoped by `important: '#simplestack-admin'`, so they
 * cannot leak into wp-admin's own buttons.
 */

const VARIANTS = {
	primary: 'bg-brand-600 text-white border-brand-600 hover:bg-brand-700 hover:border-brand-700',
	secondary: 'bg-surface-base text-ink-800 border-surface-border hover:bg-surface-muted',
	ghost: 'bg-transparent text-ink-600 border-transparent hover:bg-surface-muted hover:text-ink-900',
	danger: 'bg-transparent text-negative-600 border-negative-200 hover:bg-negative-50',
};

const SIZES = {
	sm: 'text-xs px-2.5 py-1',
	md: 'text-sm px-3.5 py-2',
};

/**
 * @param {Object}  props
 * @param {string}  props.variant  primary | secondary | ghost | danger
 * @param {string}  props.size     sm | md
 * @param {boolean} props.busy     Show a pending state and disable the button.
 * @return {JSX.Element} Button element.
 */
export default function Button( {
	variant = 'secondary',
	size = 'md',
	busy = false,
	disabled = false,
	className = '',
	type = 'button',
	children,
	...rest
} ) {
	const classes = [
		'inline-flex items-center justify-center gap-1.5 rounded-card border font-medium transition-colors',
		'focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-1',
		'disabled:cursor-not-allowed disabled:opacity-50',
		VARIANTS[ variant ] || VARIANTS.secondary,
		SIZES[ size ] || SIZES.md,
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<button type={ type } className={ classes } disabled={ disabled || busy } { ...rest }>
			{ busy ? (
				<span
					className="inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent"
					aria-hidden="true"
				/>
			) : null }
			{ children }
		</button>
	);
}
