<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

use Spiral\Goridge\Protocol;
use Spiral\Goridge\Protocol\DecoderInterface;
use Spiral\Goridge\Protocol\EncoderInterface;
use Spiral\Goridge\Protocol\Version;
use Spiral\Goridge\Transport\ReceiverInterface;
use Spiral\Goridge\Transport\ResponderInterface;
use Spiral\Goridge\Transport\SyncReceiverInterface;
use Spiral\Goridge\Transport\SyncStreamReceiver;
use Spiral\Goridge\Transport\SyncStreamResponder;
use Temporal\Client\Exception\TransportException;

class GoRidge extends Transport
{
    /**
     * @var ReceiverInterface
     */
    protected ReceiverInterface $receiver;

    /**
     * @var ResponderInterface
     */
    protected ResponderInterface $responder;

    /**
     * @param resource $read
     * @param resource $write
     * @param int $version
     * @throws \Exception
     */
    public function __construct($read, $write, int $version = Version::VERSION_1)
    {
        $protocol = new Protocol($version);

        $this->receiver = $this->receiver($read, $protocol);
        $this->responder = $this->responder($write, $protocol);
    }

    /**
     * @param resource $read
     * @param DecoderInterface $decoder
     * @return ReceiverInterface
     * @throws \Exception
     */
    protected function receiver($read, DecoderInterface $decoder): ReceiverInterface
    {
        return new SyncStreamReceiver($read, $decoder);
    }

    /**
     * @param resource $write
     * @param EncoderInterface $encoder
     * @return ResponderInterface
     * @throws \Exception
     */
    protected function responder($write, EncoderInterface $encoder): ResponderInterface
    {
        return new SyncStreamResponder($write, $encoder);
    }

    /**
     * @param resource $stream
     * @param int $version
     * @return static
     * @throws \Exception
     */
    public static function fromDuplexStream($stream, int $version = Version::VERSION_1): self
    {
        return new static($stream, $stream, $version);
    }

    /**
     * {@inheritDoc}
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
     * @return string
     */
    public function waitForMessage(): string
    {
        if ($this->receiver instanceof SyncReceiverInterface) {
            return $this->receiver
                ->waitForResponse()
                ->getContents()
            ;
        }

        throw new \LogicException('Can not run event loop');
    }
}
