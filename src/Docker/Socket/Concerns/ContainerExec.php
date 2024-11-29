<?php

namespace Phpwebterm\Docker\Socket\Concerns;

use Illuminate\Support\Facades\Http;
use Phpwebterm\Support\HttpClient;
use Ratchet\RFC6455\Messaging\Frame;
use React\Socket\ConnectionInterface;
use React\Socket\TcpConnector;
use React\Stream\DuplexStreamInterface;

trait ContainerExec
{
    use HttpClient;

    private ?array $process = null;

    private ?string $execID = null;

    private bool $attachStdin = false;

    private bool $attachStdout = false;

    private bool $attachStderr = false;

    private bool $tty = false;

    public function stdinWrite(string $content): false|int
    {
        return $this->process['stream']->write($content);
    }

    /**
     * @throws \Exception
     */
    public function startProcess($conn, $loop): void
    {
        $socket = $this->getSocketUri();

        $container_id = $this->getContainerID();

        $response = $this->sendPostRequest("$socket/containers/$container_id/exec", $this->getExecConfig(), asJson: true);

        $this->execID = $response['Id'];

        $this->startExec($conn, $loop);
    }

    public function getSocketUri(): string
    {
        $port = $this->getParameter('port', escapeshellarg: false);
        $host = $this->getParameter('host', escapeshellarg: false);

        return "$host:$port";
    }

    public function getContainerID(): string
    {
        return str_replace("'", '', $this->requiredParameter['container_id']);
    }

    private function getExecConfig(): array
    {
        $env = $this->getEnvironmentVariables();
        $env = array_map(
            fn ($key, $value) => "$key=$value",
            array_keys($env),
            array_values($env)
        );

        return [
            'AttachStdin' => $this->attachStdin,
            'AttachStdout' => $this->attachStdout,
            'AttachStderr' => $this->attachStderr,
            'User' => $this->user(),
            'Tty' => $this->tty,
            'Env' => $env,
            'Cmd' => [$this->command()],
        ];
    }

    abstract public function getEnvironmentVariables(): array;

    public function user()
    {
        return $this->getParameter('user', escapeshellarg: false);
    }

    abstract public function command(): string;

    /**
     * Starts the exec instance and handles bidirectional communication.
     */
    protected function startExec(ConnectionInterface $conn, $loop): void
    {
        $socket = $this->getSocketUri();

        // Create a connector for TCP socket
        $connector = new TcpConnector($loop);

        // Connect to Docker TCP socket
        $connector->connect($socket)->then(
            function (DuplexStreamInterface $stream) use ($conn) {
                // Construct HTTP request with query parameters
                $path = "/exec/{$this->execID}/start";
                $body = json_encode([
                    'Detach' => false,
                    'Tty' => $this->tty,
                    'ConsoleSize' => [
                        24, 80,
                    ],
                ]);

                $request = "POST {$path} HTTP/1.1\r\n";
                $request .= "Host: localhost\r\n";
                $request .= "Content-type: application/json\r\n";
                $request .= 'Content-length: '.strlen($body)."\r\n";
                $request .= "Connection: close\r\n\r\n";
                $request .= $body;

                // Buffer for response
                $buffer = '';
                $headersParsed = false;

                $stream->on('data', function ($data) use (&$buffer, &$headersParsed, $stream, $conn) {
                    if (! $headersParsed) {
                        $buffer .= $data;
                        if (str_contains($buffer, "\r\n\r\n")) {
                            [$headers, $rest] = explode("\r\n\r\n", $buffer, 2);
                            $headers = explode("\r\n", $headers);
                            $statusLine = array_shift($headers);
                            preg_match('#HTTP/\d\.\d\s+(\d+)#', $statusLine, $matches);
                            $statusCode = isset($matches[1]) ? intval($matches[1]) : 0;

                            if ($statusCode !== 200) {
                                echo "Error starting exec: HTTP {$statusCode}\n";
                                $conn->write("Error starting exec: HTTP {$statusCode}");
                                $conn->close();
                                $stream->end();

                                return;
                            }

                            $headersParsed = true;

                            // At this point, the connection has been upgraded to a bidirectional stream
                            echo "Connection to container successful\n";

                            // Remove the existing 'data' listener for initial headers
                            $stream->removeAllListeners('data');

                            // Handle data from Docker exec and send to WebSocket
                            $stream->on('data', function ($execData) use ($conn) {
                                $frame = new Frame($execData, true, Frame::OP_TEXT);
                                $conn->write($frame->getContents());
                            });

                            $this->process['stream'] = $stream;

                            if (method_exists($this, 'runCommands')) {
                                $this->runCommands();
                            }
                        }
                    } else {
                        // Already parsed headers; any further data is handled by the 'data' listener above
                    }
                });

                $stream->on('error', function ($error) use ($conn) {
                    echo "Error on exec start stream: {$error}\n";
                    $conn->write("Error on exec start stream: {$error}");
                    $conn->close();
                });

                $stream->on('close', function () use ($stream, $conn) {
                    echo "Exec start stream closed for connection\n";
                    $conn->close();
                    $stream->end();
                });

                // Send HTTP request
                $stream->write($request);
            },
            function ($error) use ($conn) {
                echo "Error connecting to Docker socket for exec start: {$error}\n";
                $conn->write("Error connecting to Docker socket for exec start: {$error}");
                $conn->close();
            }
        );
    }

    public function terminate($loop = null): void
    {
        if ($this->process) {
            if (isset($this->process['stream'])) {
                $this->process['stream']->write("exit\r");
                $this->process['stream']->end();
            }
            $this->process = null;
        }
    }

    /**
     * @throws \Exception
     */
    public function setWindowSize($terminalCols, $terminalRows): void
    {
        $socket = $this->getSocketUri();

        if ($this->execID && $this->process) {
            $query = http_build_query([
                'h' => $terminalRows,
                'w' => $terminalCols,
            ]);

            $this->sendPostRequest($socket."/exec/$this->execID/resize?$query");
        }
    }

    public function attachStdout(bool $attachStdout = true): static
    {
        $this->attachStdout = $attachStdout;

        return $this;
    }

    public function attachStdin(bool $attachStdin = true): static
    {
        $this->attachStdin = $attachStdin;

        return $this;
    }

    public function attachStderr(bool $attachStderr = true): static
    {
        $this->attachStderr = $attachStderr;

        return $this;
    }

    public function tty(bool $tty = true): static
    {
        $this->tty = $tty;

        return $this;
    }
}
