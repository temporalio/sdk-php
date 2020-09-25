<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Protocol\Transport;

use Amp\Loop;
use React\EventLoop\LoopInterface;
use Spiral\Goridge\Protocol;
use Spiral\Goridge\Protocol\Version;
use Spiral\Goridge\Transport\ReactReceiver;
use Spiral\Goridge\Transport\Receiver\Message;
use Spiral\Goridge\Transport\Receiver\MessageInterface;
use Spiral\Goridge\Transport\ReceiverInterface;
use Spiral\Goridge\Transport\ResponderInterface;
use Spiral\Goridge\Transport\SyncStreamResponder;
use Temporal\Client\Exception\TransportException;

final class GoridgeTransport implements TransportInterface
{
    /**
     * @var ReceiverInterface
     */
    private ReceiverInterface $receiver;

    /**
     * @var ResponderInterface
     */
    private ResponderInterface $responder;

    /**
     * @param LoopInterface $loop
     * @param resource $read
     * @param resource $write
     * @param int $version
     * @throws \Exception
     */
    public function __construct(LoopInterface $loop, $read, $write, int $version = Version::VERSION_1)
    {
        $protocol = new Protocol($version);

        $this->receiver = new ReactReceiver($loop, $read, $protocol);
        $this->responder = new SyncStreamResponder($write, $protocol);
    }

    /**
     * @param resource $stream
     * @param LoopInterface $loop
     * @param int $version
     * @return static
     * @throws \Exception
     */
    public static function fromDuplexStream(LoopInterface $loop, $stream, int $version = Version::VERSION_1): self
    {
        return new self($loop, $stream, $stream, $version);
    }

    /**
     * @param string $message
     * @throws TransportException
     */
    public function send(string $message): void
    {
        try {
            $this->responder->send($message);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param \Closure $then
     * @throws TransportException
     */
    public function onRequest(\Closure $then): void
    {
        $this->receiver->receive(function (MessageInterface $message) use ($then): void {
            if ($message instanceof Message) {
                try {
                    $then($message->getContents());
                } catch (\Throwable $e) {
                    throw new TransportException($e->getMessage(), $e->getCode(), $e);
                }
            }
        });
    }
}
