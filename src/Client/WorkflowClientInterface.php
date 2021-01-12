<?php


namespace Temporal\Client;

interface WorkflowClientInterface
{
    //   public function start();

    /**
     * @psalm-template T of object
     * @param class-string<T> $class
     * @param WorkflowOptions|null $options
     * @return object<T>|T
     */
    public function newWorkflowStub(string $class, WorkflowOptions $options = null): object;

    /**
     * @param string $name
     * @param WorkflowOptions|null $options
     * @return WorkflowStubInterface
     */
    public function newUntypedWorkflowStub(string $name, WorkflowOptions $options = null): WorkflowStubInterface;
}
