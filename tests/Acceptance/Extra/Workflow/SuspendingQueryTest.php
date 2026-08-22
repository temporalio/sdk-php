<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\SuspendingQuery;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class SuspendingQueryTest extends TestCase
{
    #[Test]
    public function queryHandlerThatSuspendsFailsInsteadOfHanging(
        #[Stub('Extra_Workflow_SuspendingQuery')] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->query('suspending')?->getValue(0);
            self::fail('A suspending query handler must not succeed.');
        } catch (\Throwable $error) {
            self::assertStringContainsString('Workflow is not initialized', self::chainMessage($error));
        } finally {
            $stub->signal('exit');
        }

        self::assertSame('done', $stub->getResult('string'));
    }

    #[Test]
    public function plainQueryStillWorksAfterASuspendingOneFailed(
        #[Stub('Extra_Workflow_SuspendingQuery')] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->query('suspending')?->getValue(0);
        } catch (\Throwable) {
        }

        self::assertSame('plain', $stub->query('plain')?->getValue(0));

        $stub->signal('exit');
        self::assertSame('done', $stub->getResult('string'));
    }

    private static function chainMessage(\Throwable $error): string
    {
        $messages = [];

        for ($current = $error; $current !== null; $current = $current->getPrevious()) {
            $messages[] = $current->getMessage();
        }

        return \implode(' | ', $messages);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: 'Extra_Workflow_SuspendingQuery')]
    public function handle(): string
    {
        Workflow::await(fn(): bool => $this->exit);

        return 'done';
    }

    #[Workflow\QueryMethod(name: 'suspending')]
    public function suspending(): string
    {
        Workflow::await(static fn(): bool => true);

        return 'unreachable';
    }

    #[Workflow\QueryMethod(name: 'plain')]
    public function plain(): string
    {
        return 'plain';
    }

    #[Workflow\SignalMethod(name: 'exit')]
    public function exit(): void
    {
        $this->exit = true;
    }
}
