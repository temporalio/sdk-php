<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\UpsertTypedSearchAttributesWorkflow;

/**
 * @group client
 * @group functional
 */
final class UpsertTypedSearchAttributesWorkflowTestCase extends WorkflowTestCase
{
    public function testTypedUpsertCompletesAndIsRecorded(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(UpsertTypedSearchAttributesWorkflow::class);

        $run = $this->workflowClient->start($workflow, 'CustomValue');

        self::assertSame('done', $run->getResult('string', 30));
        $this->searchAttributeMocks->assertUpsertedValue('CustomKeyword', 'CustomValue');
        $this->searchAttributeMocks->assertUpserted('CustomInt');
        self::assertSame(42, $this->searchAttributeMocks->getUpserted('CustomInt'));
        $this->searchAttributeMocks->assertUpsertedValue('index', 'idx');
        $this->searchAttributeMocks->assertUpsertedValue('2024', 'year');

        self::assertSame(
            [
                'CustomKeyword' => ['operation' => 'set', 'type' => 'keyword', 'value' => 'CustomValue'],
                'CustomInt' => ['operation' => 'set', 'type' => 'int64', 'value' => 42],
                'index' => ['operation' => 'set', 'type' => 'keyword', 'value' => 'idx'],
                '2024' => ['operation' => 'set', 'type' => 'keyword', 'value' => 'year'],
            ],
            $this->searchAttributeMocks->getUpsertedAttributes(),
        );
    }
}
