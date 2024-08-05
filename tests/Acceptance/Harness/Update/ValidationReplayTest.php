<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\ValidationReplay;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ValidationReplayTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Workflow')] WorkflowStubInterface $stub,
    ): void {
        $stub->update('do_update');
        self::assertSame(1, $stub->getResult());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private bool $done = false;

    # Don't use static variables like this.
    private static int $validations = 0;

    #[WorkflowMethod('Workflow')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->done);

        return static::$validations;
    }

    #[Workflow\UpdateMethod('do_update')]
    public function doUpdate(): void
    {
        if (static::$validations === 0) {
            ++static::$validations;
            throw new class extends \Error {
                public function __construct()
                {
                    parent::__construct("I'll fail task");
                }
            };
        }

        $this->done = true;
    }

    #[Workflow\UpdateValidatorMethod('do_update')]
    public function validateDoUpdate(): void
    {
        if (static::$validations > 1) {
            throw new \RuntimeException('I would reject if I even ran :|');
        }
    }
}
