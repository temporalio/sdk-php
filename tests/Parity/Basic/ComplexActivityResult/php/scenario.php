<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Basic\ComplexActivityResult\Php;

use Carbon\CarbonInterval;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Worker\WorkerInterface;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[ActivityInterface(prefix: 'Parity.Basic.ComplexActivityResult.')]
interface BagActivity
{
    /**
     * @return list<string>
     */
    #[ActivityMethod('bag')]
    public function bag(): array;
}

final class BagActivityImpl implements BagActivity
{
    public function bag(): array
    {
        return ['alpha', 'beta', 'gamma'];
    }
}

#[WorkflowInterface]
final class ComplexActivityResultWorkflow
{
    #[WorkflowMethod(name: 'Parity_Basic_ComplexActivityResult')]
    public function run()
    {
        $stub = Workflow::newActivityStub(
            BagActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::seconds(2)),
        );

        $items = yield $stub->bag();

        return \implode(':', $items);
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ComplexActivityResultWorkflow::class);
    $worker->registerActivityImplementations(new BagActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-basic-complex-activity-result-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Basic_ComplexActivityResult',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'alpha:beta:gamma') {
        throw new \RuntimeException("unexpected: " . \var_export($result, true));
    }

    return $workflowId;
}
