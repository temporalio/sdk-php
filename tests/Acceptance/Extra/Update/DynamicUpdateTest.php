<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Update\DynamicUpdate;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class DynamicUpdateTest extends TestCase
{
    #[Test]
    public function addUpdateMethodWithoutValidation(
        #[Stub('Extra_Update_DynamicUpdate')] WorkflowStubInterface $stub,
    ): void {
        $idResult = $stub->update(TestWorkflow::UPDATE_METHOD)->getValue(0);
        self::assertNotNull($idResult);

        $id = Uuid::uuid4()->toString();
        $idResult = $stub->startUpdate(
            UpdateOptions::new(TestWorkflow::UPDATE_METHOD, LifecycleStage::StageCompleted)
                ->withUpdateId($id)
        )->getResult();
        self::assertSame($id, $idResult);
    }

    #[Test]
    public function addUpdateMethodWithValidation(
        #[Stub('Extra_Update_DynamicUpdate')] WorkflowStubInterface $stub,
    ): void {
        // Valid
        $result = $stub->update(TestWorkflow::UPDATE_METHOD_WV, 42)->getValue(0);
        self::assertSame(42, $result);

        // Invalid input
        try {
            $stub->update(TestWorkflow::UPDATE_METHOD_WV, -42);
        } catch (WorkflowUpdateException $e) {
            $previous = $e->getPrevious();
            self::assertInstanceOf(ApplicationFailure::class, $previous);
            self::assertSame('Value must be positive', $previous->getOriginalMessage());
        }
    }
}


#[WorkflowInterface]
class TestWorkflow
{
    public const UPDATE_METHOD = 'update-method';
    public const UPDATE_METHOD_WV = 'update-method-with-validation';

    private array $result = [];
    private bool $exit = false;

    #[WorkflowMethod(name: "Extra_Update_DynamicUpdate")]
    public function handle()
    {
        // Register update methods
        Workflow::registerUpdate(self::UPDATE_METHOD, function () {
            // Also Update context is tested
            $id = Workflow::getUpdateContext()->getUpdateId();
            return $this->result[self::UPDATE_METHOD] = $id;
        });
        // Update method with validation
        Workflow::registerUpdate(
            self::UPDATE_METHOD_WV,
            fn(int $value): int => $value,
            fn(int $value) => $value > 0 or throw new \InvalidArgumentException('Value must be positive'),
        );

        yield Workflow::await(fn() => $this->exit);
        return $this->result;
    }

    #[Workflow\SignalMethod]
    public function exit(): void
    {
        $this->exit = true;
    }
}
