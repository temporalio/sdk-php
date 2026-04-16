<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\Coexistence;

use Nexus\Sdk\Attribute\Operation;
use Nexus\Sdk\Attribute\OperationImpl;
use Nexus\Sdk\Attribute\Service;
use Nexus\Sdk\Attribute\ServiceImpl;
use Nexus\Sdk\Handler\OperationContext;
use Nexus\Sdk\Handler\OperationHandlerInterface;
use Nexus\Sdk\Handler\OperationStartDetails;
use Nexus\Sdk\Handler\SynchronousOperationHandler;
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
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
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
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
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
        #[Stub('Extra_Nexus_Coexistence_Wf2')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $taskQueue = 'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\Coexistence';
        $endpointName = NexusHelper::uniqueEndpointName('test-nexus-coexist');

        if (!NexusHelper::createEndpoint($endpointName, $state->namespace, $taskQueue, $state->address)) {
            self::markTestSkipped('Could not create Nexus endpoint');
        }

        $endpointId = NexusHelper::getEndpointId($endpointName, $state->address);
        if ($endpointId === null) {
            self::markTestSkipped('Could not resolve endpoint UUID');
        }

        $host = \parse_url("http://{$state->address}", PHP_URL_HOST) ?? '127.0.0.1';

        [$code, $resp] = NexusHelper::postNexus($host, $endpointId, 'CoexistService', 'ping', 'pong');
        self::assertSame(200, $code, "Expected 200, got {$code}. Response: {$resp}");
        self::assertStringContainsString('pong-pong', (string) $resp);
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
interface CoexistServiceInterface
{
    #[Operation]
    public function ping(string $word): string;
}

#[ServiceImpl(service: CoexistServiceInterface::class)]
class CoexistServiceImpl
{
    #[OperationImpl]
    public function ping(): OperationHandlerInterface
    {
        return new SynchronousOperationHandler(
            static fn(OperationContext $ctx, OperationStartDetails $details, ?string $word): string
                => "pong-{$word}",
        );
    }
}
