<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\LocalActivityScopes;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Activity\LocalActivityInterface;
use Temporal\Activity\LocalActivityOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\UpdateMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class LocalActivityScopesTest extends TestCase
{
    #[Test]
    public function fromBody(
        #[Stub('Extra_LocalActivityScopes_Body')] WorkflowStubInterface $stub,
    ): void {
        self::assertSame('done', $stub->getResult('string'));
    }

    #[Test]
    public function fromUpdate(
        #[Stub('Extra_LocalActivityScopes_Idle')] WorkflowStubInterface $stub,
    ): void {
        self::assertSame('done', $stub->update('run')->getValue(0, 'string'));
    }

    #[Test]
    public function fromSignal(
        #[Stub('Extra_LocalActivityScopes_Idle')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('run');
        $stub->signal('exit');
        self::assertSame('done', $stub->getResult('string'));
    }
}

#[WorkflowInterface]
class BodyWorkflow
{
    #[WorkflowMethod(name: 'Extra_LocalActivityScopes_Body')]
    public function handle()
    {
        return yield Workflow::executeActivity(
            'Extra_LocalActivityScopes_LA.echo',
            ['done'],
            LocalActivityOptions::new()->withScheduleToCloseTimeout(10),
        );
    }
}

#[WorkflowInterface]
class IdleWorkflow
{
    private bool $exit = false;
    private string $result = '';

    #[WorkflowMethod(name: 'Extra_LocalActivityScopes_Idle')]
    public function handle()
    {
        yield Workflow::await(fn() => $this->exit);
        return $this->result;
    }

    #[SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }

    #[SignalMethod]
    public function run()
    {
        $this->result = (string) (yield Workflow::executeActivity(
            'Extra_LocalActivityScopes_LA.echo',
            ['done'],
            LocalActivityOptions::new()->withScheduleToCloseTimeout(10),
        ));
    }

    #[UpdateMethod('run')]
    public function runUpdate()
    {
        return yield Workflow::executeActivity(
            'Extra_LocalActivityScopes_LA.echo',
            ['done'],
            LocalActivityOptions::new()->withScheduleToCloseTimeout(10),
        );
    }
}

#[LocalActivityInterface(prefix: 'Extra_LocalActivityScopes_LA.')]
class EchoLocalActivity
{
    public function echo(string $value): string
    {
        return $value;
    }
}
