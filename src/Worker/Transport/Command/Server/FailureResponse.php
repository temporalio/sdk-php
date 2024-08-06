<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Transport\Command\Server;

use Temporal\Worker\Transport\Command\FailureResponseInterface;

/**
 * Carries failure response.
 */
final class FailureResponse extends ServerResponse implements FailureResponseInterface
{
    public function __construct(private readonly \Throwable $failure, string|int $id, TickInfo $info)
    {
        parent::__construct(id: $id, info: $info);
    }

    public function getFailure(): \Throwable
    {
        return $this->failure;
    }
}
