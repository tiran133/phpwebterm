<?php

namespace Phpwebterm\Server;

use Phpwebterm\Concerns\ReactProcess;

class ServerShellProcess extends ServerProcess
{
    use ReactProcess;

    public function getCurrentWorkingDirectory(): ?string
    {
        return null;
    }

    protected function defaultParameters(): array
    {
        return ['host', 'username', 'port' => 22, 'ssh_key_path' => null, 'jump_proxy' => null];
    }
}
