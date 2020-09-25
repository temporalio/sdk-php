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
use Spiral\Goridge\Protocol;
use Spiral\Goridge\Protocol\Stream\Factory;

class ServerEmulator
{
    /**
     * @var LoopInterface
     */
    private LoopInterface $loop;

    /**
     * @var Protocol
     */
    private Protocol $protocol;

    /**
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->protocol = new Protocol();
    }

    /**
     * @param string|int $uri
     */
    public function run($uri): void
    {
        $server = new TcpServer($uri, $this->loop);

        $server->on('connection', function (ConnectionInterface $connection): void {
            $addr = $connection->getRemoteAddress();

            echo "[$addr] Establish Connection\n";

            $connection->on('data', function ($chunk) use ($connection, $addr) {
                $data = $this->decode($chunk);

                echo "[$addr] <<< $chunk\n";

                if (isset($data['method'])) {
                    $this->send($connection, $this->encodeResponse($data['id'], $data['method']));
                }
            });

            $this->runTimers($connection);
        });

        $this->loop->run();
    }

    /**
     * @param string $message
     * @return string
     */
    private function encode(string $message): string
    {
        $stream = $this->protocol->encode($message, Protocol\Type::TYPE_MESSAGE);

        $result = '';

        foreach ($stream as $chunk) {
            $result .= $chunk;
        }

        return $result;
    }

    /**
     * @param string $chunk
     * @return array
     * @throws \JsonException
     */
    private function decode(string $chunk): array
    {
        $source = $this->stream($chunk);

        $stream = $this->protocol->decode(new Factory());

        while ($stream->valid()) {
            $stream->send(\fread($source, $stream->current()));
        }

        /** @var Protocol\Stream\BufferStream $buffer */
        [$buffer] = $stream->getReturn();

        return \json_decode($buffer->getContents(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $text
     * @return false|resource
     */
    private function stream(string $text)
    {
        $stream = \fopen('php://memory', 'ab+');
        \fwrite($stream, $text);
        \fseek($stream, 0);

        return $stream;
    }

    /**
     * @param int $id
     * @param mixed $payload
     * @return string
     * @throws \JsonException
     */
    private function encodeResponse(int $id, $payload): string
    {
        $response = \json_encode(['id' => $id, 'result' => $payload], \JSON_THROW_ON_ERROR);

        return $this->encode($response);
    }

    /**
     * @param ConnectionInterface $connection
     */
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

    /**
     * @param string $method
     * @param mixed $payload
     * @return string
     * @throws \JsonException
     */
    private function encodeRequest(string $method, $payload): string
    {
        $response = \json_encode(
            ['id' => \random_int(1, \PHP_INT_MAX), 'method' => $method, 'params' => $payload],
            \JSON_THROW_ON_ERROR
        );

        return $this->encode($response);
    }

    /**
     * @param ConnectionInterface $connection
     * @param $payload
     */
    private function send(ConnectionInterface $connection, $payload): void
    {
        $addr = $connection->getRemoteAddress();

        echo "[$addr] >>> $payload\n";

        $connection->write($payload);
    }
}
