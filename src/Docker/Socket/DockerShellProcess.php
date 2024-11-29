<?php

namespace Phpwebterm\Docker\Socket;

use Phpwebterm\Docker\Socket\Concerns\ContainerExec;
use Phpwebterm\TerminalProcess;

class DockerShellProcess extends TerminalProcess
{
    use ContainerExec;

    public function __construct()
    {
        $this->attachStdin()
            ->attachStdout()
            ->attachStderr()
            ->tty();
    }

    public function command(): string
    {
        $port = $this->requiredParameter['port'];
        $container_id = $this->requiredParameter['container_id'];

        // Check if /bin/bash exists in the container
        $checkCmd = "/usr/local/bin/docker -H 127.0.0.1:$port exec $container_id test -e /bin/bash";
        exec($checkCmd, $output, $exitCode);

        // /bin/bash exists or  Fallback to /bin/sh
        return $exitCode === 0 ? '/bin/bash' : '/bin/sh';
    }

    public function runCommands(): void
    {
        $this->stdinWrite("alias ls='ls --color=auto'\n");
        $this->clearTerminal();
    }

    protected function defaultParameters(): array
    {
        return ['container_id', 'host' => '127.0.0.1', 'port', 'user' => null];
    }
}
