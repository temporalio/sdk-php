<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Fibers\MutexRunLocked;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Experiments\Fibers\FiberHelper;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\CancellationScopeInterface;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class MutexRunLockedTest extends TestCase
{
    #[Test]
    public function runLockedWithGeneratorAndAwait(
        #[Stub('Extra_Workflow_Fibers_MutexRunLocked')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('unblock');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result[0], 'Mutex must be unlocked after runLocked is finished');
        $this->assertTrue($result[1], 'The function inside runLocked mist wait for signal');
        $this->assertTrue($result[2], 'Mutex must be locked during runLocked');
        $this->assertNull($result[3], 'No exception must be thrown');
    }

    #[Test]
    public function runLockedAndCancel(
        #[Stub('Extra_Workflow_Fibers_MutexRunLocked')]
        WorkflowStubInterface $stub,
    ): void {
        $stub->signal('cancel');
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertTrue($result[0], 'Mutex must be unlocked after runLocked is cancelled');
        $this->assertNull($result[2], 'Mutex must be locked during runLocked');
        $this->assertSame(CanceledFailure::class, $result[3], 'CanceledFailure must be thrown');
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private \Temporal\Experiments\Fibers\Mutex $mutex;
    private CancellationScopeInterface $promise;
    private bool $unblock = false;
    private bool $exit = false;

    /** True if the Mutex was released after the first runLocked */
    private bool $unlocked = false;

    public function __construct()
    {
        $this->mutex = new \Temporal\Experiments\Fibers\Mutex();
    }

    #[WorkflowMethod(name: "Extra_Workflow_Fibers_MutexRunLocked")]
    #[\Temporal\Workflow\ReturnType(Type::TYPE_ARRAY)]
    public function handle(): array
    {
        $exception = null;
        try {
            $this->promise = Workflow::runLocked($this->mutex, $this->runLocked(...));
            $result = FiberHelper::await($this->promise);
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

        // The last runLocked must not be executed because there a permanent lock
        // that was created inside the first runLocked
        if ($trailed) {
            throw new \Exception('The trailed runLocked must not be executed.');
        }

        return [$this->unlocked, $this->unblock, $result, $exception];
    }

    #[\Temporal\Workflow\SignalMethod]
    public function unblock(): void
    {
        $this->unblock = true;
    }

    #[\Temporal\Workflow\SignalMethod]
    public function cancel(): void
    {
        $this->promise->cancel();
    }

    #[\Temporal\Workflow\SignalMethod]
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
