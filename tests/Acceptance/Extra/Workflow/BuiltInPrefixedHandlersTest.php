<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\BuiltInPrefixedHandlers;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Sdk\V1\EnhancedStackTrace;
use Temporal\Api\Sdk\V1\StackTrace;
use Temporal\Api\Sdk\V1\StackTraceFileLocation;
use Temporal\Api\Sdk\V1\StackTraceFileSlice;
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
    public function denyBuiltInPrefixes(
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

        $stub->signal('exit');
        $stub->getResult();
    }

    #[Test]
    public function stackTrace(
        #[Stub('Extra_Workflow_BuiltInPrefixedHandlers')] WorkflowStubInterface $stub,
    ): void {
        $stackTrace = $stub->query(EntityNameValidator::QUERY_TYPE_STACK_TRACE)->getValue(0);
        self::assertStringContainsString(__FILE__, $stackTrace);

        $stub->signal('exit');
        $stub->getResult();
    }

    #[Test]
    public function enhancedStackTrace(
        #[Stub('Extra_Workflow_BuiltInPrefixedHandlers')] WorkflowStubInterface $stub,
    ): void {
        $enhancedStackTrace = $stub->query(EntityNameValidator::ENHANCED_QUERY_TYPE_STACK_TRACE)
            ->getValue(0, EnhancedStackTrace::class);
        self::assertInstanceOf(EnhancedStackTrace::class, $enhancedStackTrace);
        // Source for this file
        self::assertTrue($enhancedStackTrace->getSources()->offsetExists(__FILE__));
        $slice = $enhancedStackTrace->getSources()[__FILE__];
        self::assertInstanceOf(StackTraceFileSlice::class, $slice);
        self::assertSame(
            \file_get_contents(__FILE__),
            $slice->getContent(),
        );
        // The first stack trace frame should be the current file
        $stack = $enhancedStackTrace->getStacks()[0];
        self::assertInstanceOf(StackTrace::class, $stack);

        $found = false;
        foreach ($stack->getLocations() as $location) {
            self::assertInstanceOf(StackTraceFileLocation::class, $location);
            if ($location->getFilePath() === __FILE__) {
                $found = true;
                self::assertSame(Workflow::class . '::await', $location->getFunctionName());
            }
        }

        self::assertTrue($found, 'Expected to find a stack trace location for the current file.');

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
