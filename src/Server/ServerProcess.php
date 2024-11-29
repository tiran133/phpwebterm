<?php

namespace Phpwebterm\Server;

use Exception;
use Phpwebterm\Support\Ssh;
use Phpwebterm\TerminalProcess;

abstract class ServerProcess extends TerminalProcess
{
    /**
     * @throws Exception
     */
    public function command(): string
    {
        $host = $this->getParameter('host', escapeshellarg: false);
        $port = intval($this->getParameter('port', escapeshellarg: false));
        $username = $this->getParameter('username', escapeshellarg: false);
        $jumpProxy = $this->getParameter('jump_proxy');
        $ssh_key_path = $this->getParameter('ssh_key_path');

        return $this->newSsh($host, $port, $username, $jumpProxy, $ssh_key_path)->get();

    }

    /**
     * @throws Exception
     */
    public function newSsh($host, $port, $username, $jumpProxy = null, $ssh_key_path = null): Ssh
    {

        $ssh = Ssh::new($username, $host, $port);
        $ssh->disableStrictHostKeyChecking();
        if ($jumpProxy) {
            $ssh->useJumpHost($jumpProxy);
        }

        if ($ssh_key_path) {
            $ssh->usePrivateKey($ssh_key_path);
        }

        return $ssh;
    }
}
