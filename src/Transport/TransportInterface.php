<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

use React\Promise\PromiseInterface;
use Spiral\Goridge\Message\ReceivedMessageInterface;
use Temporal\Client\Transport\Request\RequestInterface;

interface TransportInterface
{
    /**
     * @param RequestInterface $request
     * @return PromiseInterface
     */
    public function send(RequestInterface $request): PromiseInterface;

    /**
     * @param ReceivedMessageInterface $message
     */
    public function handle(ReceivedMessageInterface $message): void;
}
