<?php

/**
 * This file is part of Goridge package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge;

use React\EventLoop\LoopInterface;
use Spiral\Goridge\Protocol\DecoderInterface;

class AsyncReceiver implements ReceiverInterface
{
    /**
     * @var array|\Closure[]
     */
    private array $listeners = [];

    /**
     * @var \Generator|null
     */
    private ?\Generator $context = null;

    /**
     * @param resource $input
     * @param DecoderInterface $decoder
     * @param LoopInterface $loop
     * @throws \Exception
     */
    public function __construct($input, DecoderInterface $decoder, LoopInterface $loop)
    {
        $loop->addReadStream($input, fn () => $this->read($input, $decoder));
    }

    /**
     * @param resource $stream
     * @param DecoderInterface $decoder
     */
    private function read($stream, DecoderInterface $decoder): void
    {
        if ($this->context === null) {
            $this->context = $decoder->decode();
        }

        if ($this->context->valid()) {
            $data = \fread($stream, $this->context->current());

            $this->context->send($data);
        }

        if (! $this->context->valid()) {
            foreach ($this->listeners as $listener) {
                $listener($this->context->getReturn());
            }

            $this->context = null;
        }
    }

    /**
     * @param \Closure $onMessage
     * @return void
     */
    public function onMessage(\Closure $onMessage): void
    {
        $this->listeners[] = $onMessage;
    }
}
