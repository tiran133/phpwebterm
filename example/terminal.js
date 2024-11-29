import {TerminalManager} from '/../dist/TerminalManager.es.js';

// If the config is not supplied, it will read the config from ENV variable during vite build process
const config = {
    WEBSOCKET_SCHEME: 'http',
    WEBSOCKET_HOST: '127.0.0.1',
    WEBSOCKET_PORT: '8034',
}

// Instantiate and expose the manager
const terminalManager = new TerminalManager(config);

window.connectServerShell = terminalManager.newEndpoint('server-shell');
window.connectDockerShell = terminalManager.newEndpoint('docker-shell');
window.connectDockerLogs = terminalManager.newEndpoint('docker-logs');

// Opens a shell to a server

// window.connectServerShell({
//     host: '<IP>',
//     port: 22,
//     username: 'dashboard',
//     jump_proxy: '<USER>@<IP>:<PORT>',
//     ssh_key_path: '<PATH TO FILE>'
// });


// Opens a shell into a docker container

// window.connectDockerShell({
//     host: '<IP|SOCKET>>', // EX. '/var/run/docker.sock' or '127.0.0.1'
//     port: '<PORT>',
//     user: '<USERNAME>', // Must exist in container
//     container_id: '<CONTAINER_ID>',
// });

// window.connectDockerLogs({
//     host: '<IP|SOCKET>>', // EX. '/var/run/docker.sock' or '127.0.0.1'
//     port: '<PORT>',
//     user: '<USERNAME>', // Must exist in container
//     log_lines: 1000,
//     container_id: '<CONTAINER_ID>',
// });
