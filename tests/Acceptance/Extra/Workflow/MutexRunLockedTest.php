<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\MutexRunLocked;

use PHPUnit\Framework\Attributes\Test;
use React\Promise\PromiseInterface;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\Type;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;
use Temporal\Workflow\Mutex;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;

class MutexRunLockedTest extends TestCase
{
    #[Test]
    public function runLockedWithGeneratorAndAwait(
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
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private Mutex $mutex;
    private PromiseInterface $promise;
    private bool $unblock = false;
    private bool $exit = false;

    /** True if the Mutex was released after the first runLocked */
    private bool $unlocked = false;

    public function __construct()
    {
        $this->mutex = new Mutex();
    }

    #[WorkflowMethod(name: "Extra_Workflow_MutexRunLocked")]
    #[ReturnType(Type::TYPE_ARRAY)]
    public function handle(): \Generator
    {
        $exception = null;
        try {
            $result = yield $this->promise = Workflow::runLocked($this->mutex, $this->runLocked(...));
        } catch (\Throwable $e) {
            $exception = $e::class;
        }

        $trailed = false;
        yield Workflow::await(
            fn() => $this->exit,
            Workflow::runLocked($this->mutex, static function () use (&$trailed) {
                $trailed = true;
            }),
        );

        // The last runLocked must not be executed because there a permanent lock
        // that was created inside the first runLocked
        $trailed and throw new \Exception('The trailed runLocked must not be executed.');

        return [$this->unlocked, $this->unblock, $result, $exception];
    }

    #[SignalMethod]
    public function unblock(): void
    {
        $this->unblock = true;
    }

    #[SignalMethod]
    public function cancel(): void
    {
        $this->promise->cancel();
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }

    private function runLocked(): \Generator
    {
        // Permanently lock mutex
        Workflow::runLocked($this->mutex, function () {
            $this->unlocked = true;
            yield Workflow::await(fn() => false);
        });

        yield Workflow::await(fn() => $this->unblock);
        return $this->mutex->isLocked();
    }
}
