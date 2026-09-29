<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Workflow;

final class GeneratedChildWorkflowId
{
    public function __construct(
        private readonly string $runId,
        public int $sequence,
    ) {}

    /**
     * @psalm-mutation-free
     */
    public function getWorkflowId(): string
    {
        return $this->runId . '_' . $this->sequence;
    }
}
