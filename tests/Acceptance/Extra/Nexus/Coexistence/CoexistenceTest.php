<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Coexistence;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity;
use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;
use Temporal\Activity\ActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusEndpoints;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHttpClient;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusWorkerOptions;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: a worker can host workflows, activities AND Nexus services together.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class CoexistenceTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return NexusWorkerOptions::default();
    }

    #[Test]
    public function workflowExecutesWithActivityAlongsideNexus(
        #[Stub('Extra_Nexus_Coexistence_Wf')]
        WorkflowStubInterface $stub,
    ): void {
        $result = $stub->getResult('string');
        self::assertSame('activity-result:hello', $result);
    }

    #[Test]
    public function nexusOperationStillWorksAfterActivityRegistered(
        State $state,
        NexusEndpoints $endpoints,
        NexusHttpClient $http,
        #[Stub('Extra_Nexus_Coexistence_Wf2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $endpoint = $endpoints->register($state->namespace, __NAMESPACE__, 'test-nexus-coexist');

        [$code, $resp, ] = $http->post($endpoint, 'CoexistService', 'ping', 'pong');
        self::assertSame(200, $code, "Expected 200, got {$code}. Response: {$resp}");
        self::assertStringContainsString('pong-pong', $resp);
    }
}

// ── Activity ─────────────────────────────────────────────────────

#[ActivityInterface(prefix: 'Extra_Nexus_Coexistence_')]
class CoexistenceActivity
{
    #[ActivityMethod]
    public function process(string $input): string
    {
        return "activity-result:{$input}";
    }
}

// ── Workflows ────────────────────────────────────────────────────

#[WorkflowInterface]
class CoexistenceWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Coexistence_Wf')]
    public function run()
    {
        $activity = Workflow::newActivityStub(
            CoexistenceActivity::class,
            ActivityOptions::new()->withStartToCloseTimeout(10),
        );
        return yield $activity->process('hello');
    }
}

#[WorkflowInterface]
class CoexistenceBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_Coexistence_Wf2')]
    public function run(): string
    {
        return 'ready';
    }
}

// ── Nexus service (coexists with workflow + activity) ─────────────

#[Service(name: 'CoexistService')]
class CoexistService
{
    #[Operation]
    public function ping(string $word): string
    {
        return "pong-{$word}";
    }
}
