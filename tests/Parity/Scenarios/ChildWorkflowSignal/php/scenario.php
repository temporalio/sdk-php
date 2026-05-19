<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\ChildWorkflowSignal\Php;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
final class ChildWorkflow
{
    private ?string $message = null;

    #[WorkflowMethod(name: 'Parity_Harness_ChildWorkflowSignal_Child')]
    public function run()
    {
        yield Workflow::await(fn (): bool => $this->message !== null);
        return $this->message;
    }

    #[SignalMethod('unblock-signal')]
    public function unblock(string $message): void
    {
        $this->message = $message;
    }
}

#[WorkflowInterface]
final class ParentWorkflow
{
    #[WorkflowMethod(name: 'Parity_Harness_ChildWorkflowSignal_Parent')]
    public function run()
    {
        $child = Workflow::newChildWorkflowStub(
            ChildWorkflow::class,
            ChildWorkflowOptions::new()
                ->withTaskQueue(Workflow::getInfo()->taskQueue)
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
        );

        $handle = $child->run();
        yield $child->unblock('unblock');
        return yield $handle;
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ParentWorkflow::class, ChildWorkflow::class);
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-child-workflow-signal-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_ChildWorkflowSignal_Parent',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'unblock') {
        throw new \RuntimeException("expected 'unblock', got: " . \var_export($result, true));
    }

    return $workflowId;
}
