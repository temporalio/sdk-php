<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Server;

use React\Socket\ConnectionInterface;
use Spiral\Goridge\Protocol;
use Spiral\Goridge\Protocol\Stream\Factory;

class GoRidge
{
    /**
     * @var Protocol
     */
    private Protocol $protocol;

    /**
     * @var Factory
     */
    private Factory $buffer;

    /**
     * @var \Closure
     */
    private \Closure $onMessage;

    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * @param ConnectionInterface $connection
     * @param \Closure $onMessage
     */
    public function __construct(ConnectionInterface $connection, \Closure $onMessage)
    {
        $this->connection = $connection;
        $this->onMessage = $onMessage;

        $this->protocol = new Protocol();
        $this->buffer = new Factory();

        $this->connection->on('data', function (string $chunk) {
            $this->dispatch($chunk);
        });
    }

    /**
     * @param string $data
     */
    public function write(string $data): void
    {
        foreach ($this->protocol->encode($data) as $chunk) {
            $this->connection->write($chunk);
        }
    }

    /**
     * @param string $chunk
     */
    private function dispatch(string $chunk): void
    {
        $source = $this->stream($chunk);
        $stream = $this->protocol->decode($this->buffer);

        while ($stream->valid()) {
            $stream->send(\fread($source, $stream->current()));
        }

        /** @var Protocol\Stream\BufferStream $buffer */
        [$buffer] = $stream->getReturn();

        ($this->onMessage)($buffer->getContents());
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
}
