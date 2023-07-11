<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use DateTimeInterface;
use JetBrains\PhpStorm\Immutable;

/**
 * @see \Temporal\Api\Workflow\V1\ResetPointInfo
 * @psalm-immutable
 */
#[Immutable]
final class ResetPointInfo
{
    public function __construct(
        public string $binaryChecksum,
        public string $runId,
        public int $firstWorkflowTaskCompletedId,
        public ?DateTimeInterface $createTime,
        public ?DateTimeInterface $expireTime,
        public bool $resettable,
    ) {
    }
}
