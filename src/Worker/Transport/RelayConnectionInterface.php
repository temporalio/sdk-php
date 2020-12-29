<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\Worker\Transport;

use Temporal\Client\Exception\TransportException;

/**
 * @psalm-type Headers = array<string, mixed>
 * @psalm-type Message = array { 0: string, 1: Headers }
 */
interface RelayConnectionInterface
{
    /**
     * @return Message|null
     * @throws TransportException
     */
    public function await(): ?Message;

    /**
     * @param string $message
     * @throws TransportException
     */
    public function send(string $message): void;

    /**
     * @param \Throwable $error
     * @throws TransportException
     */
    public function error(\Throwable $error): void;
}
