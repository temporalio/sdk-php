<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Internal\Client;

use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;

final class WorkflowRun implements WorkflowRunInterface
{
    private WorkflowStubInterface $stub;
    private $returnType;

    /**
     * @param WorkflowStubInterface $stub
     * @param $returnType
     */
    public function __construct(WorkflowStubInterface $stub, $returnType = null)
    {
        $this->stub = $stub;
        $this->returnType = $returnType;
    }

    /**
     * @return WorkflowExecution
     */
    public function getExecution(): WorkflowExecution
    {
        return $this->stub->getExecution();
    }

    /**
     * @param Type|string $returnType
     * @param int $timeout
     * @return mixed
     */
    public function getResult($returnType = null, int $timeout = self::DEFAULT_TIMEOUT)
    {
        return $this->stub->getResult($returnType ?? $this->returnType, $timeout);
    }
}
