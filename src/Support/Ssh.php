<?php

namespace Phpwebterm\Support;

use Exception;

class Ssh
{
    protected ?string $user = null;

    protected string $host;

    protected array $extraOptions = [];

    protected string $baseCommand = '/usr/bin/ssh';

    /**
     * @throws Exception
     */
    public function __construct(?string $user, string $host, ?int $port = null)
    {
        $this->user = $user;

        $this->host = $host;

        if ($port !== null) {
            $this->usePort($port);
        }
    }

    public function usePort(int $port): self
    {
        if ($port < 0) {
            throw new Exception('Port must be a positive integer.');
        }
        $this->extraOptions['port'] = '-p '.$port;

        return $this;
    }

    /**
     * @throws Exception
     */
    public static function new(...$args): static
    {
        return new static(...$args);
    }

    public function get($command = null): string
    {
        $extraOptions = implode(' ', $this->getExtraOptions());

        $target = $this->getTargetForSsh();

        if ($command) {
            $commands = $this->wrapArray($command);
            $commandString = implode(PHP_EOL, $commands);
            $bash = "'bash -se'";
            $delimiter = 'EOF-SSH-COMMAND';

            return "$this->baseCommand {$extraOptions} {$target} {$bash} << \\$delimiter".PHP_EOL
                .$commandString.PHP_EOL
                .$delimiter;
        }

        return "$this->baseCommand {$extraOptions} {$target}";

    }

    protected function getExtraOptions(): array
    {
        return array_values($this->extraOptions);
    }

    protected function getTargetForSsh(): string
    {
        if ($this->user === null) {
            return $this->host;
        }

        return "{$this->user}@{$this->host}";
    }

    protected function wrapArray($arrayOrString): array
    {
        return (array) $arrayOrString;
    }

    public function getArgs($command = null): array
    {
        $args = explode(' ', implode(' ', $this->getExtraOptions()));
        $args[] = $this->getTargetForSsh();

        if ($command) {
            $commands = $this->wrapArray($command);
            $commandString = implode(PHP_EOL, $commands);
            $delimiter = 'EOF-SSH-COMMAND';

            $args[] = "<< \\$delimiter".PHP_EOL
                .$commandString.PHP_EOL
                .$delimiter;
        }

        return $args;

    }

    public function usePrivateKey(string $pathToPrivateKey): self
    {
        $this->extraOptions['private_key'] = '-i '.$pathToPrivateKey;

        return $this;
    }

    public function useJumpHost(string $jumpHost): self
    {
        $this->extraOptions['jump_host'] = '-J '.$jumpHost;

        return $this;
    }

    public function enableStrictHostKeyChecking(): self
    {
        unset($this->extraOptions['enable_strict_check']);

        return $this;
    }

    public function disableStrictHostKeyChecking(): self
    {
        $this->extraOptions['enable_strict_check'] = '-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null';

        return $this;
    }

    public function addExtraOption(string $option): self
    {
        $this->extraOptions[] = $option;

        return $this;
    }

    public function baseCommand(): string
    {
        return $this->baseCommand;
    }
}
