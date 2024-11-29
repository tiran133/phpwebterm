<?php

namespace Phpwebterm\Support;

trait DockerHelper
{
    public function getContainerId(): string
    {
        return $this->getParameter('container_id');
    }

    public function user()
    {
        return $this->getParameter('user', escapeshellarg: false);
    }

    protected function getAvailableShellCommand(string $container_id): string
    {
        // Check if /bin/bash exists in the container
        $cmd = [$this->dockerBinary];

        if ($host = $this->getHost()) {
            $cmd[] = "-H $host";
        }

        $cmd[] = "exec $container_id test -e /bin/bash";
        exec(implode(' ', $cmd), $output, $exitCode);

        // /bin/bash exists or  Fallback to /bin/sh
        return $exitCode === 0 ? '/bin/bash' : '/bin/sh';
    }

    protected function getHost(): ?string
    {
        if (! is_null($this->requiredParameter['host']) && ! is_null($this->requiredParameter['port'])) {
            $port = $this->getParameter('port');
            $host = $this->getParameter('host');

            return sprintf('%s:%s', $host, $port);
        }

        return null;
    }
}
