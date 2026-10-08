<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\WorkflowLocal;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowLocal;
use Temporal\Workflow\WorkflowMethod;

class WorkflowLocalTest extends TestCase
{
    #[Test]
    public function concurrentRunsKeepSeparateValues(
        #[Stub('Extra_Workflow_WorkflowLocal')]
        WorkflowStubInterface $first,
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $second = $client->newUntypedWorkflowStub(
            'Extra_Workflow_WorkflowLocal',
            WorkflowOptions::new()->withTaskQueue($feature->taskQueue),
        );
        $client->start($second);

        $first->signal('add');
        $second->signal('add');
        $second->signal('add');
        $second->signal('add');
        $first->signal('exit');
        $second->signal('exit');

        self::assertSame(1, $first->getResult('int'));
        self::assertSame(3, $second->getResult('int'));
    }
}

final class Counter
{
    /** @var null|WorkflowLocal<int> */
    private static ?WorkflowLocal $value = null;

    public static function increment(): void
    {
        self::local()->set(self::local()->get() + 1);
    }

    public static function get(): int
    {
        return self::local()->get();
    }

    /**
     * @return WorkflowLocal<int>
     */
    private static function local(): WorkflowLocal
    {
        return self::$value ??= new WorkflowLocal(static fn(): int => 0);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: 'Extra_Workflow_WorkflowLocal')]
    public function handle(): \Generator
    {
        yield Workflow::await(fn(): bool => $this->exit);

        return Counter::get();
    }

    #[SignalMethod]
    public function add(): void
    {
        Counter::increment();
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
