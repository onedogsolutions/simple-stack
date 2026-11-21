/**
 * Tailwind v4 legacy config, loaded from src/admin/styles/index.css via @config.
 *
 * Two rules matter and both are enforced by tools/verify-css-isolation.mjs:
 *
 *   1. `important: '#simplestack-admin'` confines every generated utility to the
 *      plugin's mount point, so nothing can reach the wp-admin chrome.
 *   2. `corePlugins.preflight: false` — Preflight resets html/body/* and would
 *      restyle the entire admin area.
 */
module.exports = {
	content: [ './src/admin/**/*.{js,jsx}' ],

	// Every utility is emitted as `#simplestack-admin .utility`.
	important: '#simplestack-admin',

	corePlugins: {
		// Never emit Preflight: it targets html, body and * at the document root.
		preflight: false,
	},

	theme: {
		extend: {
			colors: {
				brand: {
					50: '#fdf2f6',
					100: '#fce7ef',
					200: '#fbcfe0',
					300: '#f8a8c6',
					400: '#f2749f',
					500: '#e91e63',
					600: '#d11455',
					700: '#b00f47',
					800: '#8c0d3b',
					900: '#6f0d31',
				},
				ink: {
					900: '#0d091a',
					800: '#1f1833',
					700: '#3a3152',
					600: '#514a68',
					500: '#6b6480',
					400: '#8b8599',
					300: '#a8a2b8',
					100: '#e6e4ec',
				},
				surface: {
					base: '#ffffff',
					muted: '#f3f3f5',
					border: '#dcdae3',
				},
				positive: {
					50: '#ecfdf5',
					100: '#d1fae5',
					200: '#a7f3d0',
					600: '#059669',
					700: '#047857',
				},
				warning: {
					50: '#fffbeb',
					100: '#fef3c7',
					200: '#fde68a',
					600: '#d97706',
					700: '#b45309',
				},
				negative: {
					50: '#fef2f2',
					100: '#fee2e2',
					200: '#fecaca',
					300: '#fca5a5',
					600: '#dc2626',
					700: '#b91c1c',
				},
			},
			fontFamily: {
				sans: [ '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif' ],
				mono: [ 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace' ],
			},
			borderRadius: {
				card: '0.5rem',
			},
		},
	},
};
