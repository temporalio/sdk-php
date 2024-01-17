<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Tests\Workflow\DynamicObjectReturnWorkflow;

/**
 * @group client
 * @group functional
 */
final class DynamicObjectReturnWorkflowTestCase extends AbstractClient
{
    public function testWorkflow(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(DynamicObjectReturnWorkflow::class);
        $run = $client->start($workflow);

        self::assertSame('OK', $run->getResult('string'));
    }
}

