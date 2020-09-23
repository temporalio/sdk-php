<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Goridge;

class Facade implements RelayInterface
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
     * @param ReceiverInterface $receiver
     * @param ResponderInterface $responder
     */
    public function __construct(ReceiverInterface $receiver, ResponderInterface $responder)
    {
        $this->receiver = $receiver;
        $this->responder = $responder;
    }

    /**
     * @param \Closure $onMessage
     */
    public function onReceive(\Closure $onMessage): void
    {
        $this->receiver->onReceive($onMessage);
    }

    /**
     * @param string $body
     * @param int $flags
     */
    public function send(string $body, int $flags): void
    {
        $this->responder->send($body, $flags);
    }
}
