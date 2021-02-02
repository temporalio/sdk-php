<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Client;

use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Workflow\WorkflowExecution;
use Temporal\Workflow\WorkflowRunInterface;

final class WorkflowRun implements WorkflowRunInterface
{
    /**
     * @var WorkflowStubInterface
     */
    private WorkflowStubInterface $stub;

    /**
     * @var \ReflectionClass|\ReflectionType|string|Type|null
     */
    private $returnType;

    /**
     * @param WorkflowStubInterface $stub
     * @param string|\ReflectionClass|\ReflectionType|Type|null $returnType
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
     * {@inheritDoc}
     */
    public function getResult($type = null, int $timeout = null)
    {
        return $this->stub->getResult($type ?? $this->returnType, $timeout);
    }
}
