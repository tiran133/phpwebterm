<?php

namespace Phpwebterm\Websocket;

use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Phpwebterm\Exceptions\TerminalProcessValidationException;
use Phpwebterm\TerminalProcess;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SecureServer;
use React\Socket\SocketServer;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class Server
{
    private ServerConfig $config;

    private ?Collection $routes = null;

    public function __construct(ServerConfig $config)
    {
        $this->config = $config;
        $this->routes = collect();

    }

    public function addRoute($path, string $processClass): static
    {

        $this->routes->push(['path' => Str::start($path, '/'), 'class' => $processClass]);

        return $this;
    }

    public function start(): void
    {

        // Prepare Websocket
        $loop = Loop::get();

        // Create Websocket Server
        $socket = new SocketServer($this->getWebsocketAddress(), [], $loop);

        if ($this->config->get('scheme') === 'https') {
            $socket = new SecureServer($socket, $loop, $this->config->get('certificate'));
        }

        // Setup Handling Incoming connection
        $socket->on('connection', $this->onConnection($loop));

        $this->line(sprintf('WebSocket server running at %s', $this->getWebsocketAddress()));

        //Setup Complete start the loop.
        $loop->run();

    }

    private function getWebsocketAddress(): string
    {
        $port = $this->config->get('port');
        $listen = $this->config->get('listen');

        return "$listen:$port";
    }

    private function onConnection(LoopInterface $loop): \Closure
    {
        return function ($conn) use ($loop) {

            $negotiator = new ServerNegotiator(new RequestVerifier);
            $clientIp = $this->getIpFromConnection($conn);
            $this->line("New connection from IP: $clientIp ... ");

            //Setup Connection
            $conn->once('data', $this->onWebsocketDataOnce($conn, $negotiator, $loop));

            // Add interrupt Signal
            foreach ([SIGINT, SIGTERM] as $signal) {
                $loop->addSignal($signal, function ($signal) use ($loop, $conn) {
                    $conn->close();
                    $loop->stop();
                });
            }
        };
    }

    private function getIpFromConnection($conn): string
    {
        $address = $conn->getRemoteAddress();
        // Remove the protocol (tcp://)
        $address = str_replace($this->config->get('scheme') === 'https' ? 'tls://' : 'tcp://', '', $address);

        // Handle IPv6 addresses enclosed in square brackets
        if (str_starts_with($address, '[')) {
            $parts = explode(']:', $address);
            $ip = substr($parts[0], 1); // Remove the opening bracket
        } else {
            $parts = explode(':', $address);
            $ip = $parts[0];
        }

        return $ip;
    }

    public function line($content): void
    {
        echo $content."\n";
    }

    private function onWebsocketDataOnce($conn, ServerNegotiator $negotiator, $loop): \Closure
    {
        return function ($data) use ($conn, $negotiator, $loop) {
            try {

                $request = Message::parseRequest($data);
                $response = $negotiator->handshake($request);
                $conn->write(Message::toString($response));
                $this->line('WebSocket handshake successful');

                // Extract the URI path
                $uri = $request->getUri()->getPath();
                $uriQuery = $request->getUri()->getQuery();
                parse_str($uriQuery, $queryParams);

                // Initialize per-connection variables
                /** @var TerminalProcess $process */
                $process = null;

                $this->setupProcess($uri, $queryParams, $conn, $loop, $process);

                $messageBuffer = $this->createMessageBuffer($conn, $process);

                // Handle incoming WebSocket messages
                $conn->on('data', function ($data) use ($messageBuffer) {
                    $messageBuffer->onData($data);
                });

                // Handle WebSocket connection close
                $conn->on('close', function () use ($loop, &$process) {
                    $process?->terminate($loop);
                    $this->line('Connection closed');
                });

            } catch (BadRequestException $e) {
                $this->line('WebSocket handshake failed: '.$e->getMessage());
                $conn->end();
            } catch (\TypeError $e) {
                $this->line('Error: '.$e->getMessage());
                $conn->end();
            }

        };
    }

    private function setupProcess($uri, $queryParams, $conn, $loop, ?TerminalProcess &$process): void
    {
        /** @var TerminalProcess $process */
        $processRoute = $this->routes->where('path', $uri)->first();
        if ($processRoute) {
            try {
                if (is_string($processRoute['class'])) {
                    $process = new $processRoute['class'];
                } else {
                    $process = $processRoute['class'];
                }

                if (! $process instanceof TerminalProcess) {
                    throw new TerminalProcessValidationException('Process must be a instance of ['.TerminalProcess::class.']');
                }
                $process->setParameters($queryParams);
                $process->startProcess($conn, $loop);
            } catch (TerminalProcessValidationException $e) {
                $this->line($e->getMessage());
            }
        } else {
            // Unsupported route
            $errorFrame = new Frame(json_encode(['error' => 'Unsupported route']), true, Frame::OP_TEXT);
            $conn->write($errorFrame->getContents());
            $conn->close();
        }
    }

    private function createMessageBuffer($conn, ?TerminalProcess &$process): MessageBuffer
    {
        return new MessageBuffer(
            new CloseFrameChecker,
            function ($msg) use (&$process) {
                $message = json_decode($msg, true);
                $this->handleShellMessage($message, $process);
            },
            function (Frame $controlFrame) use ($conn) {
                if ($controlFrame->getOpcode() === Frame::OP_CLOSE) {
                    $this->line('Received close frame, connection will close.');
                    $conn->close();
                }
            }
        );
    }

    private function handleShellMessage($message, TerminalProcess $process): void
    {
        if (isset($message['type'])) {
            if ($message['type'] === 'resize') {
                $process->setWindowSize($message['cols'], $message['rows']);
            } elseif ($message['type'] === 'input') {
                $process->stdinWrite($message['data']);
            }
        }
    }
}
