<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\TaskFailure;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class TaskFailureTest extends TestCase
{
    #[Test]
    public static function retryableException(
        #[Stub('Harness_Update_TaskFailure')]WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->update('do_update');
            throw new \RuntimeException('Expected validation exception');
        } catch (WorkflowUpdateException $e) {
            self::assertStringContainsString("I'll fail update", $e->getPrevious()?->getMessage());
        } finally {
            # Finish Workflow
            $stub->update('throw_or_done', doThrow: false);
        }

        self::assertSame(2, $stub->getResult());
    }

    #[Test]
    #[DoesNotPerformAssertions]
    public static function validationException(
        #[Stub('Harness_Update_TaskFailure')]WorkflowStubInterface $stub,
    ): void {
        try {
            $stub->update('throw_or_done', true);
            throw new \RuntimeException('Expected validation exception');
        } catch (WorkflowUpdateException) {
            # Expected
        } finally {
            # Finish Workflow
            $stub->update('throw_or_done', doThrow: false);
        }
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;
    private static int $fails = 0;

    #[WorkflowMethod('Harness_Update_TaskFailure')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);

        return static::$fails;
    }

    #[Workflow\UpdateMethod('do_update')]
    public function doUpdate(): string
    {
        # Don't use static variables like this. We do here because we need to fail the task a
        # controlled number of times.
        if (static::$fails < 2) {
            ++static::$fails;
            throw new class extends \Error {
                public function __construct()
                {
                    parent::__construct("I'll fail task");
                }
            };
        }

        throw new ApplicationFailure("I'll fail update", 'task-failure', true);
    }

    #[Workflow\UpdateMethod('throw_or_done')]
    public function throwOrDone(bool $doThrow): void
    {
        $this->done = true;
    }

    #[Workflow\UpdateValidatorMethod('throw_or_done')]
    public function validateThrowOrDone(bool $doThrow): void
    {
        $doThrow and throw new \RuntimeException('This will fail validation, not task');
    }
}
