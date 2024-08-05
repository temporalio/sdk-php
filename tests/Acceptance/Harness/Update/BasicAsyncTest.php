<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\BasicAsync;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class BasicAsyncTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Workflow')] WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->update('my_update', 'bad-update-arg');
            throw new \RuntimeException('Expected validation exception');
        } catch (WorkflowUpdateException $e) {
            self::assertStringContainsString('Invalid Update argument', $e->getPrevious()?->getMessage());
        }

        $updated = $stub->update('my_update', 'foo-bar')->getValue(0);
        self::assertSame('update-result', $updated);
        self::assertSame('foo-bar', $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private string $state = '';

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->state !== '');
        return $this->state;
    }

    #[Workflow\UpdateMethod('my_update')]
    public function myUpdate(string $arg): string
    {
        $this->state = $arg;
        return 'update-result';
    }

    #[Workflow\UpdateValidatorMethod('my_update')]
    public function myValidateUpdate(string $arg): void
    {
        $arg === 'bad-update-arg' and throw new \Exception('Invalid Update argument');
    }
}
