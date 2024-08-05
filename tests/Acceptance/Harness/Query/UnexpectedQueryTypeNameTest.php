<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\UnexpectedQueryTypeName;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UnexpectedQueryTypeNameTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('HarnessWorkflow_Query_UnexpectedQueryTypeName')]WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->query('nonexistent');
            throw new \Exception('Query must fail due to unknown queryType');
        } catch (WorkflowQueryException $e) {
            self::assertStringContainsString(
                'unknown queryType nonexistent',
                $e->getPrevious()->getMessage(),
            );
        }

        $stub->signal('finish');
        $stub->getResult();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $beDone = false;

    #[WorkflowMethod('HarnessWorkflow_Query_UnexpectedQueryTypeName')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
