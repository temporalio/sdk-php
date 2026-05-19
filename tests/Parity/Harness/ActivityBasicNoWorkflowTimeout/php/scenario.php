<?php

declare(strict_types=1);

namespace Temporal\Tests\Parity\Harness\ActivityBasicNoWorkflowTimeout\Php;

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

#[ActivityInterface(prefix: 'Parity.Harness.ActivityBasicNoWorkflowTimeout.')]
interface EchoActivity
{
    #[ActivityMethod('echo')]
    public function echo(): string;
}

final class EchoActivityImpl implements EchoActivity
{
    public function echo(): string
    {
        return 'echo';
    }
}

#[WorkflowInterface]
final class ActivityBasicNoWorkflowTimeoutWorkflow
{
    #[WorkflowMethod(name: 'Parity_Harness_ActivityBasicNoWorkflowTimeout')]
    public function run()
    {
        yield Workflow::newActivityStub(
            EchoActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(CarbonInterval::minute()),
        )->echo();

        return yield Workflow::newActivityStub(
            EchoActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(CarbonInterval::minute()),
        )->echo();
    }
}

function parity_php_register(WorkerInterface $worker): void
{
    $worker->registerWorkflowTypes(ActivityBasicNoWorkflowTimeoutWorkflow::class);
    $worker->registerActivityImplementations(new EchoActivityImpl());
}

function parity_php_run(WorkflowClientInterface $client, string $taskQueue): string
{
    $workflowId = 'parity-harness-activity-basic-no-workflow-timeout-php-' . \bin2hex(\random_bytes(6));

    $stub = $client->newUntypedWorkflowStub(
        'Parity_Harness_ActivityBasicNoWorkflowTimeout',
        WorkflowOptions::new()
            ->withWorkflowId($workflowId)
            ->withTaskQueue($taskQueue)
            ->withWorkflowExecutionTimeout(CarbonInterval::seconds(15)),
    );

    $client->start($stub);
    $result = $stub->getResult('string');
    if ($result !== 'echo') {
        throw new \RuntimeException("expected 'echo', got: " . \var_export($result, true));
    }

    return $workflowId;
}
