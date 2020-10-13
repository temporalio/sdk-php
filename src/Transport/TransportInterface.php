<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Transport;

/**
 * @psalm-type MessageSubscription = \Closure(string): void
 */
interface TransportInterface
{
    /**
     * @param string $message
     */
    public function send(string $message): void;

    /**
     * @return string
     */
    public function waitForMessage(): string;
}
