<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command;

/**
 * Carries failure response.
 */
final class FailureResponse extends Response implements FailureResponseInterface
{
    public function __construct(
        private readonly \Throwable $failure,
        string|int $id,
    ) {
        parent::__construct(id: $id);
    }

    public function getFailure(): \Throwable
    {
        return $this->failure;
    }
}
