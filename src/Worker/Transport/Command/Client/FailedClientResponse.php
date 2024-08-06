<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Client;

use Temporal\Worker\Transport\Command\FailureResponseInterface;

final class FailedClientResponse implements FailureResponseInterface
{
    public function __construct(
        private readonly int|string $id,
        private readonly ?\Throwable $failure = null,
    ) {}

    public function getID(): string|int
    {
        return $this->id;
    }

    public function getFailure(): \Throwable
    {
        return $this->failure;
    }
}
