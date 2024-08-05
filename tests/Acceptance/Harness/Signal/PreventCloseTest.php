<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\PreventClose;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class PreventCloseTest extends TestCase
{
    #[Test]
    public static function checkSignalOutOfExecution(
        #[Stub('Harness_Signal_PreventClose')]WorkflowStubInterface $stub,
    ): void {
        $stub->signal('add', 1);
        \usleep(1_500_000); // Wait 1.5s to workflow complete
        try {
            $stub->signal('add', 2);
            throw new \Exception('Workflow is not completed after the first signal.');
        } catch (WorkflowNotFoundException) {
            // false means the workflow was not replayed
            self::assertSame([1], $stub->getResult()[0]);
            self::assertFalse($stub->getResult()[1], 'The workflow was not replayed');
        }
    }

    #[Test]
    public static function checkPreventClose(
        #[Stub('Harness_Signal_PreventClose')]WorkflowStubInterface $stub,
    ): void {
        self::markTestSkipped('research a better way');

        $stub->signal('add', 1);

        // Wait that the first signal is processed
        usleep(200_000);

        // Add signal while WF is completing
        $stub->signal('add', 2);

        self::assertSame([1, 2], $stub->getResult()[0], 'Both signals were processed');
        self::assertTrue($stub->getResult()[1], 'The workflow was replayed');
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private array $values = [];

    #[WorkflowMethod('Harness_Signal_PreventClose')]
    public function run()
    {
        // Non-deterministic hack
        $replay = Workflow::isReplaying();

        yield Workflow::await(fn(): bool => $this->values !== []);

        // Add some blocking lag 500ms
        \usleep(500_000);

        return [$this->values, $replay];
    }

    #[SignalMethod('add')]
    public function add(int $arg)
    {
        $this->values[] = $arg;
    }
}
