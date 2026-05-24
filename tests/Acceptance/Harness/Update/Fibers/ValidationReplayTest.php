<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Update\Fibers\ValidationReplay;
use PHPUnit\Framework\Attributes\Test;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Experiments\Fibers\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class ValidationReplayTest extends TestCase
{
    #[Test]
    public static function check(
        #[Stub('Harness_Update_Fibers_ValidationReplay')]WorkflowStubInterface $stub,
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

    #[WorkflowMethod('Harness_Update_Fibers_ValidationReplay')]
    public function run()
    {
        Workflow::await(fn(): bool => $this->done);

        return static::$validations;
    }

    #[\Temporal\Workflow\UpdateMethod('do_update')]
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

    #[\Temporal\Workflow\UpdateValidatorMethod('do_update')]
    public function validateDoUpdate(): void
    {
        if (static::$validations > 1) {
            throw new \RuntimeException('I would reject if I even ran :|');
        }
    }
}
