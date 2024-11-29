<?php

namespace Phpwebterm\Traits;

use Ioctl\Ioctl;
use Ratchet\RFC6455\Messaging\Frame;

trait FFIProcess
{
    // The TIOCSWINSZ ioctl request code
    // This value is standard on Linux systems
    const TIOCSWINSZ = 0x5414;

    protected ?array $process;

    public function startProcess($conn, $loop): void
    {

        $terminalCols = 80;
        $terminalRows = 24;

        try {
            // Use PHP FFI to call forkpty and execute the command
            $ffi = \FFI::cdef('
            typedef unsigned short ushort;
            typedef int pid_t;

            struct winsize {
                ushort ws_row;
                ushort ws_col;
                ushort ws_xpixel;
                ushort ws_ypixel;
            };

            pid_t forkpty(int *amaster, char *name, void *termios_p, struct winsize *winp);
        ', 'libc.so.6');

            // Prepare window size
            $winsize = $ffi->new('struct winsize');
            $winsize->ws_row = $terminalRows;
            $winsize->ws_col = $terminalCols;
            $winsize->ws_xpixel = 0;
            $winsize->ws_ypixel = 0;

            $amaster = $ffi->new('int');
            $pid = $ffi->forkpty(\FFI::addr($amaster), null, null, \FFI::addr($winsize));

            if ($pid == -1) {
                echo "Failed to forkpty\n";
                $conn->close();

                return;
            }

            if ($pid == 0) {
                // Child process
                // Create a new session and set the process group ID
                posix_setsid();

                [$cmd,$args] = $this->splitCommand($this->command());
                $env = $this->getEnvironmentVariables();

                // Remove the added quotes from escapeshellarg()
                $args = array_map(fn ($arg) => trim($arg, "'"), $args);

                pcntl_exec($cmd, $args, $env);

                // If pcntl_exec returns, an error occurred
                fwrite(STDERR, "Failed to execute docker command\n");
                exit(1);
            } else {
                // Parent process
                $ptyFd = $amaster->cdata;

                // Convert file descriptor to a PHP stream
                $ptyStream = fopen("php://fd/$ptyFd", 'r+');
                stream_set_blocking($ptyStream, false);

                // Save the process information
                $this->process = [
                    'pid' => $pid,
                    'ptyFd' => $ptyFd,
                    'ptyStream' => $ptyStream,
                ];

                // Read output from the process
                $loop->addReadStream($ptyStream, function ($stream) use ($loop, $conn, &$ptyStream) {
                    // Suppress warnings from fread()
                    $output = @fread($stream, 8192);
                    if ($output === false || $output === '' || feof($stream)) {
                        // Remove the stream from the event loop
                        $loop->removeReadStream($ptyStream);
                        $conn->close();
                        // Clean up the process reference
                        $this->process = null;

                        return;
                    }
                    $frame = new Frame($output, true, Frame::OP_TEXT);
                    $conn->write($frame->getContents());
                });

                if (method_exists($this, 'runCommands')) {
                    $this->runCommands();
                }
            }
        } catch (\Exception $e) {
            echo 'Error starting process: '.$e->getMessage()."\n";
            $errorFrame = new Frame(json_encode(['error' => 'Failed to start process']), true, Frame::OP_TEXT);
            $conn->write($errorFrame->getContents());
            $conn->close();
        }
    }

    private function splitCommand($command): array
    {
        // Use regex to split the command into arguments while respecting quoted substrings
        preg_match_all('/("([^"]*)"|\'([^\']*)\'|(\S+))/', $command, $matches);

        $args = $matches[0]; // Matches retain quotes exactly as they appear

        if (empty($args)) {
            return ['baseCommand' => '', 'arguments' => []];
        }

        // The first argument is the base command
        $baseCommand = array_shift($args);

        return [
            $baseCommand,
            $args,
        ];
    }

    abstract public function command(): string;

    public function terminate($loop = null): void
    {
        $this->stdinWrite("exit\n");

        if (isset($this->process['ptyStream'])) {
            // Close the PTY stream
            if (is_resource($this->process['ptyStream'])) {
                // Send exit command to shell
                $this->stdinWrite("exit\n");
                fclose($this->process['ptyStream']);
            }
            // Remove the stream from the event loop
            $loop?->removeReadStream($this->process['ptyStream']);
        }

        // Close the PTY file descriptor
        if (isset($this->process['ptyFd']) && is_int($this->process['ptyFd'])) {
            $ffiClose = \FFI::cdef('int close(int fd);', 'libc.so.6');
            $ffiClose->close($this->process['ptyFd']);
        }

        // Terminate the process group
        if (isset($this->process['pid'])) {
            // Negative PID to kill the process group
            posix_kill(-$this->process['pid'], SIGTERM);
        }
    }

    public function stdinWrite(string $content): false|int
    {
        return fwrite($this->process['ptyStream'], $content);
    }

    public function setWindowSize($terminalCols, $terminalRows): void
    {
        // Alternative way to resize the TTY with PHP FFI
        // $this->resizePty($terminalCols, $terminalRows);

        $ioctl = new Ioctl;

        // Create a winsize structure
        $winsize = pack('S4', $terminalRows, $terminalCols, 0, 0);

        // Send the TIOCSWINSZ ioctl command
        $ioctl->ioctl($this->process['ptyFd'], static::TIOCSWINSZ, $winsize);
    }

    public function resizePty($terminalCols, $terminalRows): void
    {
        $ffi = \FFI::cdef('
        typedef unsigned short int ushort;
        struct winsize {
            ushort ws_row;
            ushort ws_col;
            ushort ws_xpixel;
            ushort ws_ypixel;
        };
        int ioctl(int fd, unsigned long request, ...);
    ', 'libc.so.6');

        $winsize = $ffi->new('struct winsize');
        $winsize->ws_row = $terminalRows;
        $winsize->ws_col = $terminalCols;
        $winsize->ws_xpixel = 0;
        $winsize->ws_ypixel = 0;

        $ffi->ioctl($this->process['ptyFd'], static::TIOCSWINSZ, \FFI::addr($winsize));
    }
}
