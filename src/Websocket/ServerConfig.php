<?php

namespace Phpwebterm\Websocket;

use Illuminate\Support\Arr;

readonly class ServerConfig
{
    public function __construct(private array $config) {}

    public function get($key): mixed
    {

        return Arr::get($this->config, $key);
    }
}
