<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\Basic;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class BasicTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Workflow')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('my_signal', 'arg');
        self::assertSame('arg', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private string $value = '';

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->value !== '');
        return $this->value;
    }

    #[SignalMethod('my_signal')]
    public function mySignal(string $arg)
    {
        $this->value = $arg;
    }
}
