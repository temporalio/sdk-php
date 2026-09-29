<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\ChildWorkflowGeneratedId;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\ChildWorkflowOptions;
use Temporal\Workflow\ChildWorkflowStubInterface;
use Temporal\Workflow\ParentClosePolicy;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ChildWorkflowGeneratedIdTest extends TestCase
{
    #[Test]
    public function childWithoutIdGetsRunScopedSequentialId(
        #[Stub('Extra_Workflow_ChildWorkflowGeneratedId')] WorkflowStubInterface $stub,
    ): void {
        $deadline = \microtime(true) + 10.0;
        while (\count($stub->query('ids')->getValue(0, 'array')) < 3 && \microtime(true) < $deadline) {
            \usleep(100_000);
        }
        $stub->cancel();

        $runId = $stub->getExecution()->getRunID();
        self::assertSame(
            [
                $runId . '_1',
                $runId . '_2',
                $stub->getExecution()->getID() . '-explicit',
                'abandoned child canceled',
                'terminated child canceled',
                $runId . '_4',
            ],
            $stub->getResult('array'),
        );
    }
}

#[WorkflowInterface]
class ParentWorkflow
{
    private array $ids = [];

    #[WorkflowMethod(name: 'Extra_Workflow_ChildWorkflowGeneratedId')]
    public function handle()
    {
        $this->ids[] = (yield self::child()->start())->getID();

        $sameTick = Workflow::async(static function (): \Generator {
            yield self::child(ChildWorkflowOptions::new()->withParentClosePolicy(ParentClosePolicy::Terminate))->start();
        });
        $sameTick->cancel();
        try {
            yield $sameTick;
        } catch (CanceledFailure) {
        }
        $this->ids[] = (yield self::child()->start())->getID();

        $explicit = self::child(
            ChildWorkflowOptions::new()
                ->withWorkflowId(Workflow::getInfo()->execution->getID() . '-explicit')
                ->withParentClosePolicy(ParentClosePolicy::Abandon),
        );
        $this->ids[] = (yield $explicit->start())->getID();

        try {
            yield Workflow::await(static fn(): bool => false);
        } catch (CanceledFailure) {
        }

        $this->ids[] = yield from self::startInCanceledScope('abandoned', ParentClosePolicy::Abandon);
        $this->ids[] = yield from self::startInCanceledScope('terminated', ParentClosePolicy::Terminate);

        $this->ids[] = yield Workflow::asyncDetached(
            static fn(): \Generator => (yield self::child()->start())->getID(),
        );

        return $this->ids;
    }

    #[Workflow\QueryMethod]
    public function ids(): array
    {
        return $this->ids;
    }

    private static function startInCanceledScope(string $name, ParentClosePolicy $policy): \Generator
    {
        try {
            yield self::child(ChildWorkflowOptions::new()->withParentClosePolicy($policy))->start();
            return $name . ' child started';
        } catch (CanceledFailure) {
            return $name . ' child canceled';
        }
    }

    private static function child(?ChildWorkflowOptions $options = null): ChildWorkflowStubInterface
    {
        return Workflow::newUntypedChildWorkflowStub(
            'Extra_Workflow_ChildWorkflowGeneratedId_Child',
            $options ?? ChildWorkflowOptions::new()->withParentClosePolicy(ParentClosePolicy::Abandon),
        );
    }
}

#[WorkflowInterface]
class ChildWorkflow
{
    #[WorkflowMethod(name: 'Extra_Workflow_ChildWorkflowGeneratedId_Child')]
    public function handle(): string
    {
        return 'done';
    }
}
