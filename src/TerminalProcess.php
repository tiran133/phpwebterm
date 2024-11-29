<?php

namespace Phpwebterm;

use InvalidArgumentException;
use Phpwebterm\Exceptions\TerminalProcessValidationException;

abstract class TerminalProcess
{
    protected array $requiredParameter = [];

    /**
     * @throws TerminalProcessValidationException
     */
    public function setParameters($params): void
    {
        $this->requiredParameter = $this->validate($params);
    }

    /**
     * @throws TerminalProcessValidationException
     */
    public function validate($params): array
    {
        $valid = true;

        $parameters = array_merge($this->defaultParameters(), $this->parameters());

        foreach ($parameters as $parameter => $value) {
            if (is_int($parameter)) {
                if (! isset($params[$value])) {
                    $valid = false;
                }
            }
        }

        if (! $valid) {
            // Missing Parameter get all required and throw and exception
            $requiredParameters = [];
            foreach ($parameters as $parameter => $value) {
                if (is_int($parameter)) {
                    $requiredParameters[] = $value;
                }
            }

            throw new TerminalProcessValidationException('Missing required parameter['.implode(',', $requiredParameters).']');
        }

        $requiredParameter = [];
        foreach ($parameters as $parameter => $value) {
            if (is_int($parameter)) {
                $requiredParameter[$value] = escapeshellarg($params[$value]);
            } else {
                $requiredParameter[$parameter] = isset($params[$parameter]) ? escapeshellarg($params[$parameter]) : $value;
            }

        }

        return $requiredParameter;
    }

    abstract protected function defaultParameters(): array;

    protected function parameters(): array
    {
        return [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getParameter(string $name, bool $escapeshellarg = true): ?string
    {
        if (array_key_exists($name, $this->requiredParameter)) {
            return $escapeshellarg || is_null($this->requiredParameter[$name]) ? $this->requiredParameter[$name] : trim($this->requiredParameter[$name], "'");
        }

        throw new InvalidArgumentException('Cannot find parameter ['.$name.']');
    }

    public function getEnvironmentVariables(): array
    {
        return [
            'TERM' => 'xterm-256color',
            'LC_ALL' => 'C.UTF-8',
            'LANG' => 'C.UTF-8',
        ];
    }

    abstract public function terminate($loop = null): void;

    abstract public function startProcess($conn, $loop);

    abstract public function setWindowSize($terminalCols, $terminalRows): void;

    protected function clearTerminal(): void
    {
        $this->stdinWrite("echo -e \"\\033[H\\033[2J\"\n");
    }

    abstract public function stdinWrite(string $content);
}
