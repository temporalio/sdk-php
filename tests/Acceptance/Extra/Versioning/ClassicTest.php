<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Versioning\Classic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Testing\Replay\CoreWorkflowReplayer;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\CoreWorker;
use Temporal\Worker\Core\CoreWorkerFactory;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ClassicTest extends TestCase
{
    #[Test]
    public function replayDifferentVersions(
        #[Stub(
            type: 'Extra_Versioning_Classic',
        )] WorkflowStubInterface $stub,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $result = $stub->getResult();
        self::assertSame('v2', $result);

        $replayer = CoreWorker::enabled() ? self::coreReplayer($client, $feature) : new WorkflowReplayer();
        $replayer->replayFromJSON('Extra_Versioning_Classic', __DIR__ . '/Classic/Versioning-default.json');
        $replayer->replayFromJSON(
            'Extra_Versioning_Classic',
            __DIR__ . (CoreWorker::enabled() ? '/Classic/Versioning-v1-core.json' : '/Classic/Versioning-v1.json'),
        );

        $replayer->replayFromServer($stub->getWorkflowType(), $stub->getExecution());
    }

    private static function coreReplayer(WorkflowClientInterface $client, Feature $feature): CoreWorkflowReplayer
    {
        $factory = CoreWorkerFactory::create();
        $factory->newWorker($feature->taskQueue)->registerWorkflowTypes(TestWorkflow::class);

        return new CoreWorkflowReplayer($factory, $client);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    #[WorkflowMethod(name: "Extra_Versioning_Classic")]
    public function handle()
    {
        $version = yield Workflow::getVersion('test', Workflow::DEFAULT_VERSION, 2);

        if ($version === 1) {
            yield Workflow::sideEffect(static fn(): string => 'test');
            return 'v1';
        }

        if ($version === 2) {
            return yield Workflow::executeActivity(
                /** @see TestActivity::handler() */
                'Extra_Versioning_Classic.handler',
                args: ['v2'],
                options: ActivityOptions::new()->withScheduleToCloseTimeout(5),
            );
        }

        return 'default';
    }
}

#[ActivityInterface(prefix: 'Extra_Versioning_Classic.')]
class TestActivity
{
    #[ActivityMethod]
    public function handler(string $result): string
    {
        return $result;
    }
}
