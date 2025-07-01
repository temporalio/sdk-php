<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\BuiltInPrefixedHandlers;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Sdk\V1\EnhancedStackTrace;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Internal\Declaration\EntityNameValidator;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class BuiltInPrefixedHandlersTest extends TestCase
{
    #[Test]
    public function fallbackQuery(
        #[Stub('Extra_Workflow_BuiltInPrefixedHandlers')] WorkflowStubInterface $stub,
    ): void {
        self::assertSame(
            "Query method must not start with the internal prefix `__temporal_`.",
            $stub->update('register_query_with_prefix')->getValue(0),
        );
        self::assertSame(
            "Signal method must not start with the internal prefix `__temporal_`.",
            $stub->update('register_signals_with_prefix')->getValue(0),
        );
        self::assertSame(
            "Update method must not start with the internal prefix `__temporal_`.",
            $stub->update('register_updates_with_prefix')->getValue(0),
        );

        $stackTrace = $stub->query(EntityNameValidator::QUERY_TYPE_STACK_TRACE)->getValue(0);
        self::assertNotEmpty($stackTrace);

        $stub->signal('exit');
        $stub->getResult();
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_BuiltInPrefixedHandlers")]
    public function handle()
    {
        yield $this->onExit();
    }

    #[Workflow\UpdateMethod('register_query_with_prefix')]
    public function registerQueryWithPrefix(): string
    {
        try {
            Workflow::registerQuery(EntityNameValidator::COMMON_BUILTIN_PREFIX . 'test', static fn() => null);
            return 'success';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    #[Workflow\UpdateMethod('register_signals_with_prefix')]
    public function registerSignalWithPrefix(): string
    {
        try {
            Workflow::registerSignal(EntityNameValidator::COMMON_BUILTIN_PREFIX . 'test', static fn() => null);
            return 'success';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    #[Workflow\UpdateMethod('register_updates_with_prefix')]
    public function registerUpdateWithPrefix(): string
    {
        try {
            Workflow::registerUpdate(EntityNameValidator::COMMON_BUILTIN_PREFIX . 'test', static fn() => null);
            return 'success';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }

    private function onExit(): \Generator
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );
    }
}
