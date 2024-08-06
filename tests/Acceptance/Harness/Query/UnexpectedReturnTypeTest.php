<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\UnexpectedReturnType;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\DataConverterException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class UnexpectedReturnTypeTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Query_UnexpectedReturnType')]WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->query('the_query')?->getValue(0, 'int');
            throw new \Exception('Query must fail due to unexpected return type');
        } catch (DataConverterException $e) {
            self::assertStringContainsString(
                'The passed value of type "string" can not be converted to required type "int"',
                $e->getMessage(),
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

    #[WorkflowMethod('Harness_Query_UnexpectedReturnType')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('the_query')]
    public function theQuery(): string
    {
        return 'hi bob';
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
