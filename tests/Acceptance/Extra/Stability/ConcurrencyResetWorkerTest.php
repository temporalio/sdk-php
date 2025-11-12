<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Stability\ConcurrencyResetWorker;

use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodedValues;
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowServiceException;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\WorkflowMethod;

final class Dto
{
    public UuidInterface $parentId;
    public string $parentResult;
    public UuidInterface $childId;
    public string $childResult;

    public function __construct()
    {
        $this->parentId = Uuid::uuid4();
        $this->childId = Uuid::uuid4();

        $this->parentResult = (string) $this->parentId;
        $this->childResult = (string) $this->childId;
    }
}

class ConcurrencyResetWorkerTest extends TestCase
{
    #[Test]
    public function chainOfDeath(
        WorkflowClientInterface $client,
        Feature $feature,
    ): void {
        $stubs = $dtos = [];

        # Start multiple Workflows
        for ($i = 0; $i < 10; ++$i) {
            $stubs[$i] = self::runAndDie(
                client: $client,
                feature: $feature,
                dto: $dtos[$i] = new Dto(),
                die: $i % 3 === 2, // Die every 3rd Workflow
            );
        }

        # Finish all Workflows
        foreach ($stubs as $stub) {
            /** @see TestWorkflow::exit() */
            $stub->signal('exit');
        }

        # Validate all Workflows
        foreach ($stubs as $i => $stub) {
            # Assert results
            self::checkHistory(
                $client,
                $stub,
                $dtos[$i],
            );
        }
    }

    private static function checkHistory(WorkflowClientInterface $client, mixed $stub, Dto $dto): void
    {
        $found = false;
        foreach ($client->getWorkflowHistory($stub->getExecution()) as $event) {
            if ($event->hasMarkerRecordedEventAttributes()) {
                $record = $event->getMarkerRecordedEventAttributes();
                self::assertSame('SideEffect', $record->getMarkerName());

                $data = $record->getDetails()['data'];
                self::assertInstanceOf(Payloads::class, $data);
                $values = EncodedValues::fromPayloads($data, DataConverter::createDefault());
                self::assertSame($dto->childResult, $values->getValue(0), 'Side effect value mismatch');

                $found = true;
            }

            # Assert that Workflow has no failures
            if ($event->hasWorkflowTaskFailedEventAttributes()) {
                self::fail($event->getWorkflowTaskFailedEventAttributes()->getFailure()->getMessage());
            }
        }

        self::assertTrue($found, 'Side Effect must be found in the Workflow history');
        self::assertSame($dto->parentResult, $stub->getResult(), 'Workflow result mismatch');
    }

    private static function runAndDie(
        WorkflowClientInterface $client,
        Feature $feature,
        Dto $dto,
        bool $die = true,
    ): WorkflowStubInterface {
        $stub = $client->withTimeout(4)
            ->newUntypedWorkflowStub(
                'Extra_Stability_ConcurrencyResetWorker',
                WorkflowOptions::new()
                    ->withTaskQueue($feature->taskQueue)
                    ->withWorkflowExecutionTimeout(20)
                    ->withWorkflowId((string) $dto->parentId),
            );

        $client->start($stub, $dto);

        if ($die) {
            $dieStub = $client->withTimeout(0.5)->newUntypedRunningWorkflowStub(
                workflowID: (string) $dto->parentId,
                workflowType: 'Extra_Stability_ConcurrencyResetWorker',
            );
            # Query the Workflow to kill the Worker
            try {
                /** @see TestWorkflow::die() */
                $dieStub->query('die');
                self::fail('Query must fail with a timeout');
            } catch (WorkflowServiceException $e) {
                # Should fail with a timeout
                trap($e->getPrevious())->if(!$e->getPrevious() instanceof TimeoutException);
                // self::assertInstanceOf(TimeoutException::class, $e->getPrevious());
            } finally {
                unset($dieStub);
            }
        }

        return $stub;
    }
}

#[Workflow\WorkflowInterface]
class TestWorkflow
{
    private bool $exit = false;

    #[WorkflowMethod('Extra_Stability_ConcurrencyResetWorker')]
    #[ReturnType(Type::TYPE_STRING)]
    public function expire(DTO $dto): \Generator
    {
        yield Workflow::sideEffect(static fn(): string => $dto->childResult);
        yield Workflow::await(fn(): bool => $this->exit);

        return $dto->parentResult;
    }

    #[Workflow\QueryMethod('die')]
    public function die(int $sleep = 4): void
    {
        \sleep($sleep);
        exit(1);
    }

    #[Workflow\SignalMethod('exit')]
    public function exit()
    {
        yield Workflow::uuid7();
        $this->exit = true;
    }
}
