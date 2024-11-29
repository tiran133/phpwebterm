<?php

namespace Phpwebterm\Docker;

use Phpwebterm\Concerns\ReactProcess;
use Phpwebterm\Support\DockerHelper;
use Phpwebterm\TerminalProcess;

abstract class DockerProcess extends TerminalProcess
{
    use DockerHelper, ReactProcess;

    protected string $dockerBinary = '/usr/local/bin/docker';

    public function getCurrentWorkingDirectory(): ?string
    {
        return null;
    }

    protected function defaultParameters(): array
    {
        return ['container_id', 'host' => null, 'port' => null, 'user' => null];
    }
}
