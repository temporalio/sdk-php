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

    #[Test]
    public function detachedScopeCanStillBeCancelledExplicitly(
        #[Stub('Extra_Workflow_CancelDetachedExplicitly')] WorkflowStubInterface $stub,
    ): void {
        $this->assertSame(
            [
                'detached cleanup',
                'detached cancellation observed',
                'detached cancelled: true',
            ],
            $stub->getResult(timeout: 10),
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
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        try {
            Workflow::async(static function (): void {
                Workflow::timer(1);
            })->await();
            $this->log[] = 'nested timer completed';
        } catch (CanceledFailure) {
            $this->log[] = 'nested inherited cancel';
        }

        try {
            Workflow::await(static fn(): bool => false);
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
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        try {
            Workflow::async(function (): void {
                try {
                    Workflow::timer(1);
                    $this->log[] = 'child timer done';
                } finally {
                    $this->log[] = 'child cleanup';
                }
            })->await();
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
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        Workflow::async(static function (): void {
            Workflow::timer(1);
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
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $this->log[] = 'root cancelled';
        }

        $detached = Workflow::asyncDetached(static function (): string {
            Workflow::timer(1);
            return 'detached completed';
        });

        $this->log[] = 'detached cancelled: ' . ($detached->isCancelled() ? 'true' : 'false');
        $this->log[] = $detached->await();

        return $this->log;
    }
}

#[WorkflowInterface]
class ExplicitDetachedCancelWorkflow
{
    private array $log = [];

    #[WorkflowMethod(name: 'Extra_Workflow_CancelDetachedExplicitly')]
    public function start(): array
    {
        $detached = Workflow::asyncDetached(function (): void {
            try {
                Workflow::await(static fn(): bool => false);
            } finally {
                $this->log[] = 'detached cleanup';
            }
        });

        $detached->cancel();

        try {
            $detached->await();
        } catch (CanceledFailure) {
            $this->log[] = 'detached cancellation observed';
        }

        $this->log[] = 'detached cancelled: ' . ($detached->isCancelled() ? 'true' : 'false');
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
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
        }

        $this->record('start');

        try {
            Workflow::async($this->doSomething(...))->await();
        } catch (CanceledFailure) {
        }

        $this->record('await');

        $awaitThrew = false;
        try {
            Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
            $awaitThrew = true;
        }
        $this->log[] = 'await threw: ' . ($awaitThrew ? 'true' : 'false');

        return $this->log;
    }

    private function doSomething(): void
    {
        $this->record('timer in nested scope');
        Workflow::timer(1);
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
