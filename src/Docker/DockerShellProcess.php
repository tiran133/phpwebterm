<?php

namespace Phpwebterm\Docker;

class DockerShellProcess extends DockerProcess
{
    public function command(): string
    {
        $container_id = $this->getContainerId();

        $cmd[] = $this->dockerBinary;

        if ($host = $this->getHost()) {
            $cmd[] = "-H $host";
        }

        $shell = $this->getAvailableShellCommand($container_id);

        $cmd[] = 'exec -it';

        if ($user = $this->user()) {
            $cmd[] = "-u $user";
        }

        $cmd[] = "$container_id $shell";

        // Build the command
        return implode(' ', $cmd);
    }

    public function runCommands(): void
    {
        $this->stdinWrite("alias ls='ls --color=auto'\n");
        $this->clearTerminal();
    }
}
