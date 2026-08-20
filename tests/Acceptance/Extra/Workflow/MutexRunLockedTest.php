<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\MutexRunLocked;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class MutexRunLockedTest extends TestCase
{
    #[Test]
    public function runLockedWithScopeAndAwait(
        #[Stub('Extra_Workflow_MutexRunLocked')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('unblock');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result[0], 'Mutex must be unlocked after runLocked is finished');
        $this->assertTrue($result[1], 'The function inside runLocked mist wait for signal');
        $this->assertTrue($result[2], 'Mutex must be locked during runLocked');
        $this->assertNull($result[3], 'No exception must be thrown');
        $this->assertFalse($result[4], 'The trailed runLocked must not run: the permanent lock is held');
    }

    #[Test]
    public function runLockedAndCancel(
        #[Stub('Extra_Workflow_MutexRunLocked')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('cancel');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result[0], 'Mutex must be unlocked after runLocked is cancelled');
        $this->assertNull($result[2], 'Mutex must be locked during runLocked');
        $this->assertSame(CanceledFailure::class, $result[3], 'CanceledFailure must be thrown');
        $this->assertTrue($result[4], 'Cancelling the outer runLocked releases the inner permanent lock, so the trailed runLocked runs');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private Workflow\Mutex $mutex;
    private CancellationScopeInterface $scope;
    private bool $unblock = false;
    private bool $exit = false;

    /** True if the Mutex was released after the first runLocked */
    private bool $unlocked = false;

    public function __construct()
    {
        $this->mutex = new Workflow\Mutex();
    }

    #[WorkflowMethod(name: "Extra_Workflow_MutexRunLocked")]
    #[Workflow\ReturnType(Type::TYPE_ARRAY)]
    public function handle(): array
    {
        $result = null;
        $exception = null;
        try {
            $result = ($this->scope = Workflow::runLocked($this->mutex, $this->runLocked(...)))->await();
        } catch (\Throwable $e) {
            $exception = $e::class;
        }

        $trailed = false;
        Workflow::await(
            fn() => $this->exit,
            Workflow::runLocked($this->mutex, static function () use (&$trailed): void {
                $trailed = true;
            }),
        );

        return [$this->unlocked, $this->unblock, $result, $exception, $trailed];
    }

    #[Workflow\SignalMethod]
    public function unblock(): void
    {
        $this->unblock = true;
    }

    #[Workflow\SignalMethod]
    public function cancel(): void
    {
        $this->scope->cancel();
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }

    private function runLocked(): bool
    {
        // Permanently lock mutex
        Workflow::runLocked($this->mutex, function (): void {
            $this->unlocked = true;
            Workflow::await(static fn() => false);
        });

        Workflow::await(fn() => $this->unblock);
        return $this->mutex->isLocked();
    }
}
