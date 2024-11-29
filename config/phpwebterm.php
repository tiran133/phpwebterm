<?php

return [
    'port' => env('TERMINAL_WEBSOCKET_PORT', 8034),
    'listen' => env('TERMINAL_WEBSOCKET_HOST', '0.0.0.0'),
    'scheme' => env('TERMINAL_WEBSOCKET_SCHEME', 'http'),
    // SSL/TLS context
    'certificate' => [
        'local_cert' => env('TERMINAL_WEBSOCKET_SSL_CERT', storage_path('app/certs/').'server.crt'),
        'local_pk' => env('TERMINAL_WEBSOCKET_SSL_KEY', storage_path('app/certs/').'server.key'),
        'passphrase' => env('TERMINAL_WEBSOCKET_SSL_PASSPHRASE', ''),
    ],
    'routes' => [
        'macos' => [
            '/docker-shell' => Phpwebterm\Docker\Socket\DockerShellProcess::class,
            '/docker-shell-cli' => Phpwebterm\Docker\DockerShellProcess::class,
            '/docker-logs' => Phpwebterm\Docker\DockerLogsProcess::class,
            '/server-shell' => Phpwebterm\Server\ServerShellProcess::class,
        ],
        'linux' => [
            '/docker-shell' => Phpwebterm\Docker\Socket\DockerShellProcess::class,
            '/docker-logs' => Phpwebterm\Docker\DockerLogsProcess::class,
            '/server-shell' => Phpwebterm\FFI\ServerShellProcess::class,
        ],
    ],
];
