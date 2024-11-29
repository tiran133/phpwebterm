<?php

namespace Phpwebterm\Concerns;

use Exception;
use Ratchet\RFC6455\Messaging\Frame;
use React\ChildProcess\Process;

trait ReactProcess
{
    protected Process $process;

    /**
     * @throws Exception
     */
    public function startProcess($conn, $loop): void
    {

        $env = $this->getEnvironmentVariables();
        $cmd = $this->buildCommand();
        $cwd = $this->getCurrentWorkingDirectory();

        $this->process = new Process($cmd, $cwd, $env);
        $this->process->start($loop);

        // Get the stdin and stdout streams
        $stdout = $this->process->stdout;

        // Handle process output
        $stdout->on('data', function ($output) use ($conn) {
            $frame = new Frame($output, true, Frame::OP_TEXT);
            $conn->write($frame->getContents());
        });

        // Handle process error output
        $this->process->stderr->on('data', function ($data) {
            echo "Process stderr: $data\n";
        });

        // Handle process exit
        $this->process->on('exit', function ($exitCode, $termSignal) use ($conn) {
            if (is_null($termSignal)) {
                echo "Process exited with code $exitCode\n";
            } else {
                $signal = match ($termSignal) {
                    SIGTERM => 'SIGTERM',
                    SIGINT => 'SIGINT',
                    default => $termSignal
                };
                echo "Process exited with signal $signal\n";

            }
            $conn->close();
        });

        if (method_exists($this, 'runCommands')) {
            $this->runCommands();
        }
    }

    abstract public function getEnvironmentVariables(): array;

    /**
     * @throws Exception
     */
    public function buildCommand(): string
    {

        $subcommand = $this->command();

        if (PHP_OS === 'Darwin') {
            $cmd = "script -q /dev/null $subcommand";
        } elseif (PHP_OS === 'Linux') {
            $cmd = "script -q /dev/null -c '$subcommand'";
        }

        return $cmd ?? '';
    }

    abstract public function command(): string;

    abstract public function getCurrentWorkingDirectory(): ?string;

    public function terminate($loop = null): void
    {
        $this->stdinWrite("exit\n");
    }

    public function stdinWrite(string $content): bool
    {
        return $this->process->stdin->write($content);
    }

    public function setWindowSize($terminalCols, $terminalRows): void
    {
        // Send stty command to adjust terminal size
        $this->stdinWrite(sprintf("stty cols %d rows %d\n", $terminalCols, $terminalRows));
        $this->clearTerminal();
    }
}
