<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Extra\Workflow\Metadata;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Sdk\V1\WorkflowInteractionDefinition;
use Temporal\Api\Sdk\V1\WorkflowMetadata;
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Tests\Acceptance\App\Attribute\Stub;
use Temporal\Tests\Acceptance\App\TestCase;
use Temporal\Workflow;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

class MetadataTest extends TestCase
{
    #[Test]
    public static function withoutDynamicHandlers(
        #[Stub('Extra_Workflow_Metadata')] WorkflowStubInterface $stub,
    ): void {
        $metadata = $stub->query('__temporal_workflow_metadata')?->getValue(0, WorkflowMetadata::class);

        self::assertInstanceOf(WorkflowMetadata::class, $metadata);
        self::assertNotNull($metadata->getDefinition());
        self::assertCount(1, $metadata->getDefinition()->getQueryDefinitions());
        self::assertCount(2, $metadata->getDefinition()->getSignalDefinitions());
        self::assertCount(0, $metadata->getDefinition()->getUpdateDefinitions());
    }

    #[Test]
    public static function withDynamicHandlers(
        #[Stub('Extra_Workflow_Metadata', args: [true])]
        WorkflowStubInterface $stub,
    ): void {
        /** @var WorkflowMetadata $metadata */
        $metadata = $stub->query('__temporal_workflow_metadata')?->getValue(0, WorkflowMetadata::class);

        /** @var \ArrayAccess<int, WorkflowInteractionDefinition>|list<WorkflowInteractionDefinition> $queries */
        $queries = $metadata->getDefinition()->getQueryDefinitions();
        /** @var \ArrayAccess<int, WorkflowInteractionDefinition>|list<WorkflowInteractionDefinition> $signals */
        $signals = $metadata->getDefinition()->getSignalDefinitions();
        /** @var \ArrayAccess<int, WorkflowInteractionDefinition>|list<WorkflowInteractionDefinition> $updates */
        $updates = $metadata->getDefinition()->getUpdateDefinitions();

        self::assertInstanceOf(WorkflowMetadata::class, $metadata);
        self::assertNotNull($metadata->getDefinition());

        # Queries
        self::assertCount(2, $queries);
        # Dynamic query handler
        self::assertSame('Dynamic query handler', $queries[0]->getDescription());
        # Static query handler
        self::assertSame('get_counter', $queries[1]->getName());
        self::assertSame('Get the current counter value', $queries[1]->getDescription());

        # Signals
        self::assertCount(3, $signals);
        # Dynamic signal handler
        self::assertSame('Dynamic signal handler', $signals[0]->getDescription());
        # Static signal handlers
        self::assertSame('finish', $signals[1]->getName());
        self::assertSame('Finish the workflow', $signals[1]->getDescription());
        self::assertSame('inc_counter', $signals[2]->getName());
        self::assertSame('', $signals[2]->getDescription());

        # Updates
        self::assertCount(1, $updates);
        self::assertSame('Dynamic update handler', $updates[0]->getDescription());

    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $counter = 0;
    private bool $beDone = false;

    #[WorkflowMethod('Extra_Workflow_Metadata')]
    public function run(bool $registerFallbacks = false)
    {
        if ($registerFallbacks) {
            Workflow::registerDynamicQuery(static fn(string $name, ValuesInterface $values): mixed => $name);
            Workflow::registerDynamicSignal(static fn(string $name, ValuesInterface $values): mixed => $name);
            Workflow::registerDynamicUpdate(
                static fn(string $name, ValuesInterface $values): mixed => $name,
                static fn(string $name, ValuesInterface $values) => null,
            );
        }

        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('get_counter', description: 'Get the current counter value')]
    public function getCounter(): int
    {
        return $this->counter;
    }

    #[SignalMethod('inc_counter')]
    public function incCounter(): void
    {
        ++$this->counter;
    }

    #[SignalMethod('finish', description: 'Finish the workflow')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
