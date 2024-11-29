<?php

namespace Phpwebterm\Docker;

class DockerLogsProcess extends DockerProcess
{
    public function command(?string $extraCmd = null): string
    {
        $container_id = $this->getContainerId();
        $log_lines = $this->getParameter('log_lines');

        $cmd[] = $this->dockerBinary;

        if ($host = $this->getHost()) {
            $cmd[] = "-H $host";
        }
        $cmd[] = "logs -f -n$log_lines $container_id";

        return implode(' ', $cmd);

    }

    public function setWindowSize($terminalCols, $terminalRows): void {}

    protected function parameters(): array
    {
        return ['log_lines' => 100];
    }
}
