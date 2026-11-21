/**
 * SimpleStack admin client build.
 *
 * React is bundled rather than borrowed from @wordpress/element so the Tailwind v4
 * pipeline (@tailwindcss/vite) stays reproducible. See state.md for the trade-off.
 *
 * The output lands in assets/dist/ and is committed, so a production install needs
 * no Node toolchain.
 */

import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig( {
	plugins: [ react(), tailwindcss() ],

	build: {
		outDir: 'assets/dist',
		emptyOutDir: true,
		manifest: true,
		/* One stylesheet for the whole app. Under this flag Vite publishes the CSS as
		   a standalone manifest chunk rather than the entry's `css` array, which is
		   why tools/verify-css-isolation.mjs also scans every chunk for a .css file. */
		cssCodeSplit: false,
		sourcemap: false,
		target: 'es2020',
		rollupOptions: {
			input: {
				admin: 'src/admin/index.jsx',
			},
			output: {
				entryFileNames: 'assets/[name]-[hash].js',
				chunkFileNames: 'assets/[name]-[hash].js',
				assetFileNames: 'assets/[name]-[hash][extname]',
			},
		},
	},

	// WordPress ships its own copy of these; never duplicate them in the bundle.
	optimizeDeps: {
		exclude: [],
	},
} );
