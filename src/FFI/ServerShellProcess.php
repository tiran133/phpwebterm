<?php

namespace Phpwebterm\FFI;

use Phpwebterm\Server\ServerProcess;
use Phpwebterm\Traits\FFIProcess;

class ServerShellProcess extends ServerProcess
{
    use FFIProcess;

    protected function defaultParameters(): array
    {
        return ['host', 'username', 'port' => 22, 'ssh_key_path' => null, 'jump_proxy' => null];
    }
}
