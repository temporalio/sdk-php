<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Nexus\SyncStyles;

use Temporal\Nexus\Attribute\Operation;
use Temporal\Nexus\Attribute\Service;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Attribute\Worker;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Tests\Acceptance\Extra\Nexus\NexusHelper;
use Temporal\Worker\WorkerOptions;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

/**
 * Acceptance test: baseline synchronous Nexus operation behaviour.
 *
 * Originally this file covered four "construction styles" for
 * {@see \Temporal\Nexus\Handler\SynchronousOperationHandler} (constructor,
 * `::fromCallable()`, functor constructor, `::fromFunction()`). Those handler-
 * factory shapes have been removed in favour of the `#[Service]` /
 * `#[Operation]` interface-implementation pattern documented in CLAUDE.md, so
 * there is now only one way to declare a sync operation. The test was
 * collapsed to a single happy-path case to honestly reflect that — keeping
 * four byte-identical methods would have implied differentiation that no
 * longer exists.
 */
#[Worker(options: [self::class, 'workerOptions'])]
class SyncOperationStylesTest extends TestCase
{
    public static function workerOptions(): WorkerOptions
    {
        return WorkerOptions::new()
            ->withMaxConcurrentActivityExecutionSize(10)
            ->withMaxConcurrentNexusTaskExecutionSize(10)
            ->withMaxConcurrentNexusTaskPollers(2);
    }

    #[Test]
    public function syncOperationReturnsResult(
        State $state,
        #[Stub('Extra_Nexus_SyncStyles_Bootstrap')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->getResult('string');

        $helper = NexusHelper::for($state);
        $endpointId = $helper->setupEndpoint(
            $state->namespace,
            'Temporal\\Tests\\Acceptance\\Extra\\Nexus\\SyncStyles',
            'nexus-sync-styles',
        );

        [$code, $resp] = $helper->postOperation($endpointId, 'ShoutService', 'shout', 'alpha');

        self::assertSame(200, $code, "Response: {$resp}");
        self::assertStringContainsString('ALPHA!', $resp);
    }
}

// ── Nexus service ────────────────────────────────────────────────────

#[Service(name: 'ShoutService')]
class ShoutService
{
    #[Operation]
    public function shout(string $input): string
    {
        return \strtoupper($input) . '!';
    }
}

// ── Bootstrap workflow (worker tickle) ────────────────────────────────

#[WorkflowInterface]
class SyncStylesBootstrapWorkflow
{
    #[WorkflowMethod(name: 'Extra_Nexus_SyncStyles_Bootstrap')]
    public function run(): string
    {
        return 'ready';
    }
}
