<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;
use Spiral\Goridge\Message\ProceedMessageInterface;
use Spiral\Goridge\Protocol\GoridgeV2;
use Spiral\Goridge\Protocol\Protocol;
use Spiral\Goridge\Protocol\ProtocolInterface;

class ServerEmulator
{
    private LoopInterface $loop;

    private ProtocolInterface $protocol;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->protocol = new GoridgeV2();
    }

    public function run($uri): void
    {
        $server = new TcpServer($uri, $this->loop);

        $server->on('connection', function (ConnectionInterface $connection): void {
            $addr = $connection->getRemoteAddress();

            echo "[$addr] Establish Connection\n";

            $connection->on('data', function ($chunk) use ($connection, $addr) {
                $data = $this->decode($chunk);

                echo "[$addr] Received Data: $chunk\n";

                if (isset($data['method'])) {
                    $response = $this->encodeResponse($data['id'], $data['method']);

                    $this->send($connection, $response->body);
                }
            });

            $this->runTimers($connection);
        });

        $this->loop->run();
    }

    private function decode(string $chunk): array
    {
        $stream = $this->stream($chunk);

        $message = Protocol::decodeThrough($this->protocol, fn(int $length) => \fread($stream, $length));

        return \json_decode($message->body, true, 512, \JSON_THROW_ON_ERROR);
    }

    private function stream(string $text)
    {
        $stream = \fopen('php://memory', 'ab+');
        \fwrite($stream, $text);
        \fseek($stream, 0);

        return $stream;
    }

    private function encodeResponse(int $id, $payload): ProceedMessageInterface
    {
        $response = \json_encode(['id' => $id, 'result' => $payload], \JSON_THROW_ON_ERROR);

        return $this->protocol->encode($response, 0);
    }

    private function runTimers(ConnectionInterface $connection): void
    {
        $this->loop->addTimer(1, function () use ($connection) {
            $payload = $this->encodeRequest('StartWorkflow', [
                'name'      => 'PizzaDelivery',
                'wid'       => 'WORKFLOW_ID',
                'rid'       => 'WORKFLOW_RUN_ID',
                'taskQueue' => 'WORKFLOW_TASK_QUEUE',
                'payload'   => [1, 2, 3],
            ]);

            $this->send($connection, $payload);
        });

        $this->loop->addTimer(10, function () use ($connection) {
            $payload = $this->encodeRequest('StartActivity', [
                'name'      => 'ExampleActivity',
                'wid'       => 'WORKFLOW_ID',
                'rid'       => 'WORKFLOW_RUN_ID',
                'arguments' => ['name' => 'value', 'some' => 'Hello World!'],
            ]);

            $this->send($connection, $payload);
        });
    }

    private function encodeRequest(string $method, $payload): ProceedMessageInterface
    {
        $response = \json_encode(
            ['id' => \random_int(1, \PHP_INT_MAX), 'method' => $method, 'params' => $payload],
            \JSON_THROW_ON_ERROR
        );

        return $this->protocol->encode($response, 0);
    }

    private function send(ConnectionInterface $connection, $payload): void
    {
        $addr = $connection->getRemoteAddress();

        echo "[$addr] Processed Data: $payload\n";

        $connection->write($payload);
    }
}
