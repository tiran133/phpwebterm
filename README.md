# PHPWEBTERM

A simple php web terminal application with laravel support.

## Install

```shell
composer require tiran133/phpwebterm
```

## Basic Usage

### PHP

```php

require __DIR__.'/../vendor/autoload.php';

$config = [
    'port' => 8034,
    'listen' => '0.0.0.0',
    'scheme' => 'http',
    // SSL/TLS context
    'certificate' => [
        'local_cert' => 'server.crt',
        'local_pk' => 'server.key',
        'passphrase' => '',
    ],
];

//Create new websocket server
$websocketServe = new Server(new ServerConfig($config));

// Add route: Uses a simple approach to spawn the process.
$websocketServe->addRoute('/server-shell', ServerShellProcess::class);

//Startwebsocket server

$websocketServe->start();
```

### JS

```js
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

// Opens a shell to a server
window.connectServerShell({
    host: '<IP>',
    port: 22,
    username: 'dashboard',
    jump_proxy: '<USER>@<IP>:<PORT>',
    ssh_key_path: '<PATH TO FILE>'
});

```

See `example` directory for more details.
