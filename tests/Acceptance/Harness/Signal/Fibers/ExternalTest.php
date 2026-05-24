<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Signal\Fibers\External;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

const SIGNAL_DATA = 'Signaled!';

class ExternalTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Signal_Fibers_External')]WorkflowStubInterface $stub,
    ): void {
        $stub->signal('my_signal', SIGNAL_DATA);
        self::assertSame(SIGNAL_DATA, $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private ?string $result = null;

    #[WorkflowMethod('Harness_Signal_Fibers_External')]
    public function run()
    {
        Workflow::await(fn(): bool => $this->result !== null);
        return $this->result;
    }

    #[SignalMethod('my_signal')]
    public function mySignal(string $arg)
    {
        $this->result = $arg;
    }
}
