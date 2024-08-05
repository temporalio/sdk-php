<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\External;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

const SIGNAL_DATA = 'Signaled!';

class FeatureChecker extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Workflow')] WorkflowStubInterface $stub,
    ): void {
        $stub->signal('my_signal', SIGNAL_DATA);
        self::assertSame(SIGNAL_DATA, $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private ?string $result = null;

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->result !== null);
        return $this->result;
    }

    #[SignalMethod('my_signal')]
    public function mySignal(string $arg)
    {
        $this->result = $arg;
    }
}
