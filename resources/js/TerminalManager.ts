import {Terminal} from '@xterm/xterm';
import '@xterm/xterm/css/xterm.css';
import {FitAddon} from '@xterm/addon-fit';
import {WebsocketAddon} from './websocket-addon';

export class TerminalManager {
    private terminals: Map<string, { term: Terminal; fitAddon: FitAddon }> = new Map();
    private readonly handleResizeBound: () => void;

    constructor() {
        this.handleResizeBound = this.handleResize.bind(this); // Bind once and store the reference
    }

    newEndpoint(path: string, elementId: string = "") {
        return (args: {}) => {
            elementId = elementId === "" ? path : elementId
            if (!this.terminals.has(elementId)) {
                const url = this.getWSUrl(path);
                url.search = new URLSearchParams(args).toString();
                this.createTerminal(elementId, url);
            }
        };
    }

    private attachResizeHandler() {
        if (this.terminals.size === 1) {
            // Add the resize handler only when the first terminal is added
            window.addEventListener('resize', this.handleResizeBound);
        }
    }

    private detachResizeHandler() {
        // Remove the resize handler when the last terminal is disposed
        window.removeEventListener('resize', this.handleResizeBound);
    }

    private handleResize() {
        this.terminals.forEach(({fitAddon}) => {
            fitAddon.fit();
        });
    }

    private createTerminal(elementId: string, url: URL): () => void {
        const fitAddon = new FitAddon();
        const term = new Terminal({
            rows: 24,
            cursorStyle: 'underline',
            cursorBlink: true,
            disableStdin: false,
        });

        const websocketAddon = new WebsocketAddon(url);
        websocketAddon.onclose(() => this.detachResizeHandler())

        term.loadAddon(fitAddon);
        term.loadAddon(websocketAddon);

        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = '';
            term.open(element);
            term.focus();
            fitAddon.fit();

            // Store terminal and fitAddon for future resizing
            this.terminals.set(elementId, {term, fitAddon});
            this.attachResizeHandler();
        } else {
            console.error(`Element with ID "${elementId}" not found.`);
        }

        return () => {
            // Clean up
            term.dispose();
            websocketAddon.socketClose();
            this.terminals.delete(elementId);
        };
    }

    private getWSUrl(path: string): URL {
        const scheme =
            import.meta.env.VITE_TERMINAL_WEBSOCKET_SCHEME === 'https' ? 'wss://' : 'ws://';
        const host = import.meta.env.VITE_TERMINAL_WEBSOCKET_HOST || '127.0.0.1';
        const port = import.meta.env.VITE_TERMINAL_WEBSOCKET_PORT || '8034';

        return new URL(`${scheme}${host}:${port}/${path}`);
    }
}

