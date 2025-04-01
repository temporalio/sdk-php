<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Declaration\WorkflowInstance;

use Temporal\DataConverter\ValuesInterface;

final class SignalQueueItem
{
    public function __construct(
        /** @var non-empty-string */
        public readonly string $name,
        public readonly ValuesInterface $values,
    ) {}
}
