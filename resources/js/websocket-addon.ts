import {IDisposable, Terminal} from '@xterm/xterm';

export class WebsocketAddon {

    private _disposables: IDisposable[] = [];
    private socket: WebSocket;
    private oncloseCallbacks: (() => void)[] = []

    constructor(private readonly url: URL) {
        this.socket = new WebSocket(this.url)
    }

    activate(terminal: Terminal): void {
        this.configureSocket(terminal)
    }

    dispose(): void {
        this._disposables.forEach(d => d.dispose());
        this._disposables.length = 0
    }

    socketClose(): void {
        this.socket.close()
    }

    onclose(callback: () => void) {
        this.oncloseCallbacks.push(callback)
    }

    configureSocket(terminal: Terminal) {
        // socket.binaryType = 'arraybuffer';

        // Handle incoming data from WebSocket
        this.socket.onmessage = (event) => {
            terminal.write(event.data)
        };

        // Handle user input
        this._disposables.push(
            terminal.onData(data => this.socket.send(JSON.stringify({type: 'input', data: data})))
        )

        // Handle WebSocket connection close
        this.socket.onclose = () => {
            this.dispose();
            this.oncloseCallbacks.forEach((c) => c())

            terminal.write("\r\nConnection closed")
        }


        // Send initial terminal size to the server
        this.socket.onopen = () => {
            // Handle terminal resize and send the new size to the server
            this._disposables.push(
                terminal.onResize((size) => {
                    this.socket.send(JSON.stringify({
                        type: 'resize',
                        cols: size.cols,
                        rows: size.rows
                    }));
                })
            )

            setTimeout(() => this.socket.send(JSON.stringify({
                type: 'resize',
                cols: terminal.cols,
                rows: terminal.rows
            })), 300);
        }
    }
}
