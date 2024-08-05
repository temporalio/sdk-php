<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Tests\Workflow\UpdateWorkflow;

/**
 * @group client
 * @group functional
 */
class UpdateClientTestCase extends AbstractClient
{
    /**
     * @group skip-on-test-server
     */
    public function testUpdateReturnType(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        $client->start($workflow);
        // Based on the native return type
        $updated1 = $workflow->returnNilUuid();
        self::assertInstanceOf(UuidInterface::class, $updated1);
        self::assertSame(Uuid::NIL, $updated1->toString());

        // Based on the ReturnType attribute
        $uuid = Uuid::uuid7();
        $updated = $workflow->returnUuid($uuid);
        self::assertInstanceOf(UuidInterface::class, $updated);
        self::assertTrue($uuid->equals($updated));
    }

    /**
     * @group skip-on-test-server
     */
    public function testUpdateReturnTypeFromOptions(): void
    {
        $client = $this->createClient();
        $stub = $this->createUpdateWorkflowStub($client);

        $client->start($stub);

        // Based on Update options
        $updated = $stub
            ->startUpdate(
                /** @see UpdateWorkflow::returnAsObject */
                UpdateOptions::new('returnAsObject')->withResultType('array'),
                ['42', '69'],
            )->getResult(2);
        self::assertSame(['42', '69'], $updated);

        // Check defaults
        $handle = $stub
            ->startUpdate(
                /** @see UpdateWorkflow::returnAsObject */
                UpdateOptions::new('returnAsObject'),
                ['42', '69'],
            );
        $updated = $handle->getResult(2);
        self::assertIsObject($updated);
        self::assertTrue($handle->hasResult());
        self::assertEquals($updated, $handle->getEncodedValues(0)->getValue(0, 'object'));
    }

    /**
     * @return UpdateWorkflow
     */
    private function createUpdateWorkflow(WorkflowClient $client)
    {
        return $client->newWorkflowStub(UpdateWorkflow::class, WorkflowOptions::new()
            ->withWorkflowRunTimeout('10 seconds')
        );
    }

    private function createUpdateWorkflowStub(WorkflowClient $client): WorkflowStubInterface
    {
        return $this->createUpdateWorkflow($client)->__getUntypedStub();
    }
}
