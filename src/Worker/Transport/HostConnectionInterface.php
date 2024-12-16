<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport;

use Temporal\Exception\TransportException;

/**
 * @psalm-type Headers = array<string, mixed>
 */
interface HostConnectionInterface
{
    /**
     * @throws TransportException
     */
    public function waitBatch(): ?CommandBatch;

    /**
     * @throws TransportException
     */
    public function send(string $frame): void;

    /**
     * @throws TransportException
     */
    public function error(\Throwable $error): void;
}
