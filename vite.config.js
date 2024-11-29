import {defineConfig} from 'vite';
import cssInjectedByJsPlugin from 'vite-plugin-css-injected-by-js';

export default defineConfig({
    build: {
        minify: false, // Disable minification to keep names intact
        lib: {
            entry: './resources/js/TerminalManager.ts', // Path to your entry file
            name: 'TerminalManager', // Name for the global variable (UMD/iife builds)
            fileName: (format) => `TerminalManager.${format}.js`, // Customize output file name
        },
        rollupOptions: {
            output: {
                globals: {
                    // Define external globals if needed
                },
                exports: 'named', // Ensure named exports are preserved
            },
        },
    },
    plugins: [cssInjectedByJsPlugin()],

});
