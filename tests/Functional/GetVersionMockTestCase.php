<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Testing\WorkflowTestCase;
use Temporal\Tests\Workflow\VersionedWorkflow;

final class GetVersionMockTestCase extends WorkflowTestCase
{
    public function testPinnedVersionOverridesDefault(): void
    {
        $this->workflowMocks->expectVersion('change-1', 3);

        $workflow = $this->workflowClient->newWorkflowStub(VersionedWorkflow::class);
        $run = $this->workflowClient->start($workflow);

        self::assertSame(3, $run->getResult('int', 10));
    }
}
