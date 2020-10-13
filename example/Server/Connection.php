<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Server;

use Psr\Log\LoggerInterface;
use React\Socket\ConnectionInterface;
use Spiral\Goridge\Protocol;
use Spiral\Goridge\Protocol\Stream\Factory;
use Temporal\Client\Protocol\Json;

final class Connection
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Protocol
     */
    private Protocol $protocol;

    /**
     * @param ConnectionInterface $connection
     * @param LoggerInterface $logger
     */
    public function __construct(ConnectionInterface $connection, LoggerInterface $logger)
    {
        $this->protocol = new Protocol();

        $this->connection = $connection;
        $this->logger = $logger;

        $this->listen();
        $this->start();
    }

    /**
     * @return void
     */
    private function listen(): void
    {
        $this->connection->on('data', function (string $chunk) {
            $source = $this->stream($chunk);
            $stream = $this->protocol->decode(new Factory());

            while ($stream->valid()) {
                $stream->send(\fread($source, $stream->current()));
            }

            /** @var Protocol\Stream\BufferStream $buffer */
            [$buffer] = $stream->getReturn();

            $this->onMessage($buffer->getContents());
        });
    }

    /**
     * @param string $text
     * @return resource
     */
    private function stream(string $text)
    {
        $stream = \fopen('php://memory', 'ab+');
        \fwrite($stream, $text);
        \fseek($stream, 0);

        return $stream;
    }

    private function onMessage(string $message): void
    {
        $this->logger->debug($this->format(' <<<   Received Message: ' . $message));
    }

    /**
     * @param string $message
     * @param mixed ...$args
     * @return string
     */
    private function format(string $message, ...$args): string
    {
        $message = \sprintf($message, ...$args);

        return \sprintf('[%s] %s', $this->connection->getRemoteAddress(), $message);
    }

    /**
     * @throws \JsonException
     */
    private function start(): void
    {
        $this->write(Json::encode([
            'commands' => [
                [
                    'id'      => 1,
                    'command' => 'InitWorker',
                ],
            ],
        ]));
    }

    /**
     * @param string $data
     */
    private function write(string $data): void
    {
        $this->logger->debug($this->format('   >>> Proceed Message: ' . $data));

        foreach ($this->protocol->encode($data) as $chunk) {
            $this->connection->write($chunk);
        }
    }
}
