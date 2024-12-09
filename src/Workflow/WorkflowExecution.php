<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Workflow;

use Temporal\Common\Uuid;
use Temporal\Internal\Marshaller\Meta\Marshal;

/**
 * Workflow Execution DTO.
 *
 * @see \Temporal\Api\Common\V1\WorkflowExecution
 */
class WorkflowExecution
{
    /**
     * @psalm-readonly
     * @psalm-allow-private-mutation
     */
    #[Marshal(name: 'ID')]
    #[Marshal(name: 'workflow_id')]
    private string $id;

    /**
     * @psalm-readonly
     * @psalm-allow-private-mutation
     */
    #[Marshal(name: 'RunID')]
    #[Marshal(name: 'run_id')]
    private ?string $runId;

    public function __construct(?string $id = null, ?string $runId = null)
    {
        $this->id = $id ?? Uuid::nil();
        $this->runId = $runId;
    }

    public function getID(): string
    {
        return $this->id;
    }

    public function getRunID(): ?string
    {
        return $this->runId;
    }

    public function toProtoWorkflowExecution(): \Temporal\Api\Common\V1\WorkflowExecution
    {
        $e = new \Temporal\Api\Common\V1\WorkflowExecution();
        $e->setWorkflowId($this->id);
        $e->setRunId((string) $this->runId);

        return $e;
    }
}
