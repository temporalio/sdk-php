<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\SideEffectWorkflow;

final class SideEffectMockTestCase extends WorkflowTestCase
{
    public function testMockedSideEffectOverridesClosureResult(): void
    {
        $this->workflowMocks->expectSideEffect('OVERRIDDEN');

        $workflow = $this->workflowClient->newWorkflowStub(SideEffectWorkflow::class);
        $run = $this->workflowClient->start($workflow, 'Hello');

        self::assertSame('overridden', $run->getResult('string', 10));
    }
}
