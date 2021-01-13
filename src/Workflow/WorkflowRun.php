<?php


namespace Temporal\Workflow;

use Temporal\Client\WorkflowStubInterface;

// todo: move to client (?)
final class WorkflowRun
{
    /**
     * @var WorkflowStubInterface
     */
    private WorkflowStubInterface $stub;

    /**
     * @var mixed
     */
    private $returnType;

    /**
     * @param WorkflowStubInterface $stub
     * @param $returnType
     */
    public function __construct(WorkflowStubInterface $stub, $returnType)
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
     * @param int|null $timeout
     * @return mixed
     */
    public function getResult(int $timeout = null)
    {
        return $this->stub->getResult($timeout, $this->returnType);
    }
}
