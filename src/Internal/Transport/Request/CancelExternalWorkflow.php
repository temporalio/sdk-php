<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Transport\Request;

use Temporal\Worker\Transport\Command\Client\Request;

/**
 * @psalm-immutable
 */
class CancelExternalWorkflow extends Request
{
    public const NAME = 'CancelExternalWorkflow';

    public function __construct(
        private string $namespace,
        private string $workflowId,
        private ?string $runId,
        private ?string $reason = null,
    ) {
        $options = [
            'namespace' => $namespace,
            'workflowID' => $workflowId,
            'runID' => $runId,
            'reason' => $reason,
        ];

        parent::__construct(self::NAME, $options, null);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getRunId(): ?string
    {
        return $this->runId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
}
