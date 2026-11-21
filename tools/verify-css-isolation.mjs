#!/usr/bin/env node
/**
 * Build-time guard: prove the SimpleStack stylesheet cannot leak into wp-admin.
 *
 * wp-admin's own styles are unlayered author styles. Tailwind v4 emits real CSS
 * cascade layers, and an unlayered rule beats a layered rule regardless of
 * specificity — so a stray `body { ... }` or an unscoped `.flex` utility would win
 * against, or clobber, the admin chrome. This script fails the build instead.
 *
 * Assertions:
 *   1. no top-level `html`, `body`, `*` or `:root` selector that actually styles something
 *      (rules that only declare custom properties are Tailwind's token layers and are fine);
 *   2. every selector emitted into `@layer utilities` is anchored on #simplestack-admin;
 *   3. at least one scoped utility exists, so a silently empty Tailwind run fails the build;
 *   4. no unprocessed `@tailwind` / `@config` / `@import "tailwindcss"` residue;
 *   5. the Vite manifest exists and lists a stylesheet.
 *
 * Exit code is non-zero on the first violation class, with every offending
 * selector printed so the fix is obvious.
 */

import { readFileSync, existsSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = process.cwd();
const DIST = join( ROOT, 'assets', 'dist' );
const MANIFEST = join( DIST, '.vite', 'manifest.json' );

const MOUNT = '#simplestack-admin';

/** Selectors that must never appear at the top level of the bundle. */
const FORBIDDEN_ROOT_SELECTORS = [ 'html', 'body', '*', ':root', '::selection' ];

const errors = [];
const notes = [];

/**
 * Strip CSS comments without destroying strings.
 *
 * @param {string} css Source CSS.
 * @return {string} CSS with comments removed.
 */
function stripComments( css ) {
	let out = '';
	let i = 0;

	while ( i < css.length ) {
		if ( css[ i ] === '/' && css[ i + 1 ] === '*' ) {
			const end = css.indexOf( '*/', i + 2 );
			i = end === -1 ? css.length : end + 2;
			continue;
		}

		const char = css[ i ];

		if ( char === '"' || char === "'" ) {
			const quote = char;
			out += char;
			i += 1;

			while ( i < css.length ) {
				out += css[ i ];
				if ( css[ i ] === '\\' ) {
					out += css[ i + 1 ] ?? '';
					i += 2;
					continue;
				}
				if ( css[ i ] === quote ) {
					i += 1;
					break;
				}
				i += 1;
			}
			continue;
		}

		out += char;
		i += 1;
	}

	return out;
}

/**
 * Walk a stylesheet and collect every style rule together with the at-rule
 * context it was declared inside and its declaration block.
 *
 * @param {string} css Comment-free CSS.
 * @return {Array<{selector: string, rules: string[], body: string}>} Rule contexts.
 */
function collectRules( css ) {
	const stack = [];
	const rules = [];
	let prelude = '';
	let i = 0;

	while ( i < css.length ) {
		const char = css[ i ];

		if ( char === '{' ) {
			const head = prelude.trim();
			prelude = '';

			if ( head.startsWith( '@' ) ) {
				/* Keep the whole prelude so `@layer utilities` stays distinguishable from
				   `@layer theme` and `@layer properties`. */
				stack.push( head );
				i += 1;
				continue;
			}

			/* Capture the declaration block so token-only rules can be told apart
			   from rules that actually style something. */
			let depth = 1;
			const start = i + 1;
			i += 1;
			while ( i < css.length && depth > 0 ) {
				if ( css[ i ] === '{' ) {
					depth += 1;
				} else if ( css[ i ] === '}' ) {
					depth -= 1;
				}
				i += 1;
			}

			if ( head !== '' ) {
				rules.push( {
					selector: head,
					rules: [ ...stack ],
					body: css.slice( start, i - 1 ),
				} );
			}
			continue;
		}

		if ( char === '}' ) {
			stack.pop();
			prelude = '';
			i += 1;
			continue;
		}

		if ( char === ';' ) {
			/* A statement at-rule such as @layer a, b; or @import ...; */
			prelude = '';
			i += 1;
			continue;
		}

		prelude += char;
		i += 1;
	}

	return rules;
}

/**
 * Decide whether a declaration block only defines custom properties.
 *
 * Tailwind v4 emits its design tokens as `:root, :host { --spacing: ... }` and its
 * compatibility variables as `*, ::before, ::after { --tw-rotate-x: initial }`.
 * Declaring a custom property changes nothing visually, so those layers are safe;
 * a rule that sets `margin` or `background` on :root is not.
 *
 * @param {string} body Declaration block contents.
 * @return {boolean} True when every declaration is a custom property.
 */
function isTokenOnlyDeclarations( body ) {
	const declarations = body
		.split( ';' )
		.map( ( declaration ) => declaration.trim() )
		.filter( ( declaration ) => declaration !== '' );

	if ( declarations.length === 0 ) {
		return false;
	}

	return declarations.every( ( declaration ) => declaration.startsWith( '--' ) );
}

/**
 * Split a selector list on commas that are not inside parentheses or brackets.
 *
 * @param {string} selector Selector list.
 * @return {string[]} Individual selectors.
 */
function splitSelectorList( selector ) {
	const parts = [];
	let depth = 0;
	let current = '';

	for ( const char of selector ) {
		if ( char === '(' || char === '[' ) {
			depth += 1;
		} else if ( char === ')' || char === ']' ) {
			depth -= 1;
		}

		if ( char === ',' && depth === 0 ) {
			parts.push( current.trim() );
			current = '';
			continue;
		}

		current += char;
	}

	if ( current.trim() !== '' ) {
		parts.push( current.trim() );
	}

	return parts;
}

/* ------------------------------------------------------------------ main */

if ( ! existsSync( DIST ) ) {
	console.error( `verify-css-isolation: ${ DIST } does not exist. Run "npm run build" first.` );
	process.exit( 1 );
}

if ( ! existsSync( MANIFEST ) ) {
	console.error( 'verify-css-isolation: assets/dist/.vite/manifest.json is missing.' );
	console.error( 'The PHP enqueue layer reads that manifest; build with `manifest: true`.' );
	process.exit( 1 );
}

const manifest = JSON.parse( readFileSync( MANIFEST, 'utf8' ) );
const entryKey = Object.keys( manifest ).find( ( key ) => key.includes( 'src/admin/index.jsx' ) );

if ( ! entryKey ) {
	console.error( 'verify-css-isolation: the manifest has no src/admin/index.jsx entry.' );
	process.exit( 1 );
}

/* The entry normally owns a `css` array. A build with `cssCodeSplit: false`
   publishes the stylesheet as a standalone manifest chunk instead, so fall back
   to any chunk whose emitted file is a stylesheet. */
let cssFiles = ( manifest[ entryKey ].css ?? [] ).map( ( file ) => join( DIST, file ) );

if ( cssFiles.length === 0 ) {
	cssFiles = Object.values( manifest )
		.map( ( chunk ) => chunk.file )
		.filter( ( file ) => typeof file === 'string' && file.endsWith( '.css' ) )
		.map( ( file ) => join( DIST, file ) );
}

if ( cssFiles.length === 0 ) {
	console.error( 'verify-css-isolation: the manifest lists no stylesheet.' );
	process.exit( 1 );
}

for ( const cssFile of cssFiles ) {
	if ( ! existsSync( cssFile ) ) {
		console.error( `verify-css-isolation: manifest lists a missing stylesheet: ${ cssFile }` );
		process.exit( 1 );
	}

	const source = readFileSync( cssFile, 'utf8' );
	const css = stripComments( source );

	/* 3. Directive residue. */
	for ( const token of [ '@tailwind', '@config', '@apply', '@import "tailwindcss"' ] ) {
		if ( css.includes( token ) ) {
			errors.push( `${ cssFile }: unprocessed directive residue "${ token }"` );
		}
	}

	/* 1 + 2. Selector scoping. */
	const rules = collectRules( css );
	let utilityRules = 0;

	for ( const { selector, rules: context, body } of rules ) {
		const inUtilities = context.some( ( name ) => name.startsWith( '@layer' ) && name.includes( 'utilities' ) );
		const inKeyframes = context.some( ( name ) => name.startsWith( '@keyframes' ) );

		if ( inKeyframes ) {
			continue;
		}

		const tokenOnly = isTokenOnlyDeclarations( body );

		for ( const part of splitSelectorList( selector ) ) {
			const normalized = part.replace( /\s+/g, ' ' ).trim();

			if ( normalized === '' ) {
				continue;
			}

			const isRootSelector = FORBIDDEN_ROOT_SELECTORS.includes( normalized );

			/* Declaring a custom property on :root or * is Tailwind's token layer and
			   cannot change layout. Anything else reaching the document root is a leak. */
			if ( isRootSelector && ! tokenOnly ) {
				errors.push( `${ cssFile }: document selector with real declarations "${ normalized }${ inUtilities ? ' (in @layer utilities)' : '' }"` );
				continue;
			}

			if ( inUtilities ) {
				utilityRules += 1;

				if ( ! normalized.includes( MOUNT ) ) {
					errors.push( `${ cssFile }: utility selector is not scoped to ${ MOUNT }: "${ normalized }"` );
				}
			}
		}
	}

	/* A Tailwind run that emits nothing would pass every assertion above. */
	if ( utilityRules === 0 ) {
		errors.push( `${ cssFile }: no scoped utility selectors were emitted. Check that the content glob in tailwind.config.js matches src/admin.` );
	}

	notes.push( `${ cssFile.replace( ROOT + '/', '' ) }: ${ rules.length } rules, ${ utilityRules } scoped utility selectors` );
}

if ( errors.length > 0 ) {
	console.error( 'verify-css-isolation: FAILED\n' );
	for ( const error of errors.slice( 0, 40 ) ) {
		console.error( `  - ${ error }` );
	}
	if ( errors.length > 40 ) {
		console.error( `  ... and ${ errors.length - 40 } more` );
	}
	console.error( '\nEvery generated rule must be anchored on #simplestack-admin.' );
	console.error( 'Check `important` in tailwind.config.js and that Preflight is not imported.' );
	process.exit( 1 );
}

console.log( 'verify-css-isolation: OK' );
for ( const note of notes ) {
	console.log( `  ${ note }` );
}
