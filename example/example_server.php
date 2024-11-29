<?php

use Phpwebterm\Docker;
use Phpwebterm\Docker\DockerLogsProcess;
use Phpwebterm\Docker\DockerShellProcess;
use Phpwebterm\FFI\DockerShellProcess as DockerShellFFIProcess;
use Phpwebterm\FFI\ServerShellProcess as ServerShellFFIProcess;
use Phpwebterm\Server\ServerShellProcess;
use Phpwebterm\Websocket\Server;
use Phpwebterm\Websocket\ServerConfig;

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

$websocketServe = new Server(new ServerConfig($config));

// Uses the docker cli
$websocketServe->addRoute('/docker-shell', DockerShellProcess::class);

// Uses the docker SDK to connect though the docker socket better support terminal size manipulation
$websocketServe->addRoute('/docker-shell-socket', Docker\Socket\DockerShellProcess::class);

// Uses the docker cli
$websocketServe->addRoute('/docker-logs', DockerLogsProcess::class);

// Uses a simple approach to spawn the process.
$websocketServe->addRoute('/server-shell', ServerShellProcess::class);

// Work only on Linux but better low level terminal manipulation support
$websocketServe->addRoute('/docker-shell-ffi', DockerShellFFIProcess::class);
$websocketServe->addRoute('/server-shell-ffi', ServerShellFFIProcess::class);

$websocketServe->start();
