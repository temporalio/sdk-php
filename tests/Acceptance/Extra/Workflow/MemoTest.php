<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Memo;

use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[CoversFunction('Temporal\Workflow::upsertMemo')]
class MemoTest extends TestCase
{
    #[Test]
    public function sendEmpty(
        #[Stub(
            type: 'Extra_Workflow_Memo',
            memo: [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => ['foo' => 'bar'],
                42 => 'value4',
            ],
        )] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->update('setMemo', []);

            // Get Search Attributes using Client API
            $clientMemo = $stub->describe()->info->memo->getValues();

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
        } catch (\Throwable $e) {
            $stub->terminate('test failed');
            throw $e;
        }

        // Get Memo from Workflow
        $result = $stub->getResult();

        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => (object) ['foo' => 'bar'],
            42 => 'value4',
        ];
        $this->assertEquals($expected, $clientMemo);
        $this->assertEquals($expected, (array) $result);
    }

    #[Test]
    public function overrideAddAndRemove(
        #[Stub(
            type: 'Extra_Workflow_Memo',
            memo: [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => ['foo' => 'bar'],
            ],
        )] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->update('setMemo', [
                'key2' => null,
                'key3' => 42,
                'key4' => 'value4',
            ]);

            // Get Search Attributes using Client API
            $clientMemo = $stub->describe()->info->memo->getValues();

            // Complete workflow
            /** @see TestWorkflow::exit */
            $stub->signal('exit');
        } catch (\Throwable $e) {
            $stub->terminate('test failed');
            throw $e;
        }

        // Get Memo from Workflow
        $result = $stub->getResult();

        $expected = [
            'key1' => 'value1',
            'key3' => 42,
            'key4' => 'value4',
        ];
        $this->assertEquals($expected, $clientMemo);
        $this->assertEquals($expected, (array) $result);
    }
}

#[WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Workflow_Memo")]
    public function handle()
    {
        yield Workflow::await(
            fn(): bool => $this->exit,
        );

        tr(Workflow::getInfo()->memo);

        return Workflow::getInfo()->memo;
    }

    #[Workflow\UpdateMethod]
    public function setMemo(array $memo): void
    {
        Workflow::upsertMemo($memo);
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
