<?php

namespace Phpwebterm\FFI;

use Phpwebterm\Support\DockerHelper;
use Phpwebterm\TerminalProcess;
use Phpwebterm\Traits\FFIProcess;

class DockerShellProcess extends TerminalProcess
{
    use DockerHelper, FFIProcess;

    private string $dockerBinary = '/usr/local/bin/docker';

    public function command(): string
    {
        $container_id = $this->getContainerId();
        $container_id = str_replace('\'', '', $container_id);

        $shell = $this->getAvailableShellCommand($container_id);

        $args[] = $this->dockerBinary;
        if ($host = $this->getHost()) {
            $args[] = '-H';
            $args[] = str_replace('\'', '', $host);
        }

        // Combine the array into a single command string
        return implode(' ', array_merge($args, ['exec', '-it', $container_id, $shell]));

    }

    public function runCommands(): void
    {
        $this->stdinWrite("alias ls='ls --color=auto'\n");
        $this->clearTerminal();
    }

    protected function defaultParameters(): array
    {
        return ['container_id', 'host', 'port'];
    }
}
