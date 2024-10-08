<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Server;

use Temporal\Worker\Transport\Command\ServerResponseInterface;

abstract class ServerResponse implements ServerResponseInterface
{
    public function __construct(
        private readonly string|int $id,
        private readonly TickInfo $info,
    ) {}

    public function getID(): string|int
    {
        return $this->id;
    }

    public function getTickInfo(): TickInfo
    {
        return $this->info;
    }
}
