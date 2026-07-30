<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\CancelPropagation;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class CancelPropagationTest extends TestCase
{
    #[Test]
    public function cancellationPropagatesToScopesAndAwaitsCreatedAfterCancel(
        #[Stub('Extra_Workflow_CancelPropagation')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $log = $stub->getResult(timeout: 10);

        $this->assertSame(
            [
                'root cancelled',
                'nested inherited cancel',
                'await failed fast',
            ],
            $log,
        );
    }

    #[Test]
    public function scopeCancelledAtBirthRunsFinallyAndPropagatesOnce(
        #[Stub('Extra_Workflow_CancelOnCloseOnce')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $log = $stub->getResult(timeout: 10);

        $this->assertSame(
            [
                'root cancelled',
                'child cleanup',
                'child caught',
            ],
            $log,
        );
    }

    #[Test]
    public function onCancelHandlerAttachedAfterCancelFiresImmediately(
        #[Stub('Extra_Workflow_CancelOnCancelHook')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $log = $stub->getResult(timeout: 10);

        $this->assertSame(
            [
                'root cancelled',
                'oncancel fired',
            ],
            $log,
        );
    }

    #[Test]
    public function detachedScopeStartedAfterCancelDoesNotInheritCancel(
        #[Stub('Extra_Workflow_CancelDetachedSurvives')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $log = $stub->getResult(timeout: 10);

        $this->assertSame(
            [
                'root cancelled',
                'detached cancelled: false',
                'detached completed',
            ],
            $log,
        );
    }

    /**
     * Faithful replica of the reproduction attached to issue #769:
     * a nested scope and an await registered after the scope was cancelled.
     */
    #[Test]
    public function issue769ReproductionReportsCancelledNestedScopeAndFailFastAwait(
        #[Stub('Extra_Workflow_Issue769')] WorkflowStubInterface $stub,
    ): void {
        $stub->cancel();

        $log = $stub->getResult(timeout: 10);

        $this->assertSame(
            [
                'start: true',
                'timer in nested scope: true',
                'await: true',
                'await threw: true',
            ],
            $log,
        );
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_CancelPropagation')]
    public function handle()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        try {
            yield (function () {
                yield Workflow::timer(1);
            })();
            $this->log[] = 'nested timer completed';
        } catch (CanceledFailure) {
            $this->log[] = 'nested inherited cancel';
        }

        try {
            yield Workflow::await(static fn(): bool => false);
            $this->log[] = 'await returned';
        } catch (CanceledFailure) {
            $this->log[] = 'await failed fast';
        }

        return $this->log;
    }
}

#[WorkflowInterface]
class CleanupOnceWorkflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_CancelOnCloseOnce')]
    public function handle()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        try {
            yield (function () {
                try {
                    yield Workflow::timer(1);
                    $this->log[] = 'child timer done';
                } finally {
                    $this->log[] = 'child cleanup';
                }
            })();
        } catch (CanceledFailure) {
            $this->log[] = 'child caught';
        }

        return $this->log;
    }
}

#[WorkflowInterface]
class CancelOnCancelHookWorkflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_CancelOnCancelHook')]
    public function start()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        Workflow::async(function () {
            yield Workflow::timer(1);
        })->onCancel(function (): void {
            $this->log[] = 'oncancel fired';
        });

        return $this->log;
    }
}

#[WorkflowInterface]
class DetachedSurvivesCancelWorkflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_CancelDetachedSurvives')]
    public function start()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        $detached = Workflow::asyncDetached(function () {
            yield Workflow::timer(1);
            return 'detached completed';
        });

        $this->log[] = 'detached cancelled: ' . ($detached->isCancelled() ? 'true' : 'false');
        $this->log[] = yield $detached;

        return $this->log;
    }
}

#[WorkflowInterface]
class Issue769Workflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_Issue769')]
    public function start()
    {
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
        }

        $this->record('start');

        try {
            yield $this->doSomething();
        } catch (CanceledFailure) {
        }

        $this->record('await');

        $awaitThrew = false;
        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $awaitThrew = true;
        }
        $this->log[] = 'await threw: ' . ($awaitThrew ? 'true' : 'false');

        return $this->log;
    }

    private function doSomething(): \Generator
    {
        $this->record('timer in nested scope');
        yield Workflow::timer(1);
    }

    private function record(string $location): void
    {
        $context = Workflow::getCurrentContext();
        $isCancelled = (new \ReflectionProperty($context::class, 'scope'))
            ->getValue($context)
            ->isCancelled();
        $this->log[] = $location . ': ' . ($isCancelled ? 'true' : 'false');
    }
}
