<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\Harness\Query\Metadata;

use PHPUnit\Framework\Attributes\Test;
use Temporal\Api\Sdk\V1\WorkflowMetadata;
use Temporal\Client\WorkflowStubInterface;
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
    public static function check(#[Stub('Harness_Query_Metadata')]WorkflowStubInterface $stub): void
    {
        $metadata = $stub->query('__temporal_workflow_metadata')?->getValue(0, WorkflowMetadata::class);

        self::assertInstanceOf(WorkflowMetadata::class, $metadata);
        self::assertNotNull($metadata->getDefinition());
        self::assertCount(1, $metadata->getDefinition()->getQueryDefinitions());
        self::assertCount(2, $metadata->getDefinition()->getSignalDefinitions());
        self::assertCount(0, $metadata->getDefinition()->getUpdateDefinitions());
    }
}

#[WorkflowInterface]
class FeatureWorkflow
{
    private int $counter = 0;
    private bool $beDone = false;

    #[WorkflowMethod('Harness_Query_Metadata')]
    public function run()
    {
        yield Workflow::await(fn(): bool => $this->beDone);
    }

    #[QueryMethod('get_counter')]
    public function getCounter(): int
    {
        return $this->counter;
    }

    #[SignalMethod('inc_counter')]
    public function incCounter(): void
    {
        ++$this->counter;
    }

    #[SignalMethod('finish')]
    public function finish(): void
    {
        $this->beDone = true;
    }
}
