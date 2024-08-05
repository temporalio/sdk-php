<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\UnexpectedArguments;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowQueryException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UnexpectedArgumentsTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Workflow')] WorkflowStubInterface $stub,
    ): void {
        self::assertSame($stub->query('the_query', 42)?->getValue(0), 'got 42');

        try {
            $stub->query('the_query', true)?->getValue(0);
            throw new \Exception('Query must fail due to unexpected argument type');
        } catch (WorkflowQueryException $e) {
            self::assertStringContainsString(
                'The passed value of type "bool" can not be converted to required type "int"',
                $e->getPrevious()->getMessage(),
            );
        }

        # Silently drops extra arg
        self::assertSame($stub->query('the_query', 123, true)?->getValue(0), 'got 123');

        # Not enough arg
        try {
            $stub->query('the_query')?->getValue(0);
            throw new \Exception('Query must fail due to missing argument');
        } catch (WorkflowQueryException $e) {
            self::assertStringContainsString('0 passed and exactly 1 expected', $e->getPrevious()->getMessage());
        }

        $stub->signal('finish');
        $stub->getResult();
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $beDone = false;

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('the_query')]
    public function theQuery(int $arg): string
    {
        return "got $arg";
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
