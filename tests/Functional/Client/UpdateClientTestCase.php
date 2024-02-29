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
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Tests\Workflow\AwaitsUpdateWorkflow;
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
    public function testFetchResolvedResultAfterWorkflowCompleted(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);
        /** @see AwaitsUpdateWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');

        /** @see AwaitsUpdateWorkflow::resolve */
        $resolver = $stub->startUpdate('resolveValue', "key", "resolved");

        // Complete workflow
        /** @see AwaitsUpdateWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => 'resolved'], (array)$result, 'Workflow result contains resolved value');
        $this->assertFalse($handle->hasResult());
        $this->assertFalse($resolver->hasResult(), 'Resolver should not have result because of wait policy');
        // Fetch result
        $this->assertSame('resolved', $handle->getResult());
        $this->assertTrue($handle->hasResult());
    }

    /**
     * @group skip-on-test-server
     */
    public function testFetchResultWithTimeout(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);
        /** @see AwaitsUpdateWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');

        try {
            $start = \microtime(true);
            $handle->getResult(0.2);
            $this->fail('Should throw exception');
        } catch (TimeoutException) {
            $elapsed = \microtime(true) - $start;
            $this->assertFalse($handle->hasResult());
            $this->assertLessThan(1.0, $elapsed);
            $this->assertGreaterThan(0.2, $elapsed);
        }

        // Complete workflow
        /** @see AwaitsUpdateWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();
        $this->assertSame(['key' => null], (array)$result, 'Workflow result contains resolved value');
    }

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
     * @group skip-on-test-server
     */
    public function testHandleUnknownUpdate(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);

        try {
            $stub->startUpdate('unknownUpdateMethod', '42');
            $this->fail('Should throw exception');
        } catch (WorkflowUpdateException $e) {
            $this->assertStringContainsString(
                'unknown update method unknownUpdateMethod',
                $e->getPrevious()->getMessage(),
            );
        }
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

    /**
     * @return AwaitsUpdateWorkflow
     */
    private function createAwaitsUpdateWorkflow(WorkflowClient $client)
    {
        return $client->newWorkflowStub(
            AwaitsUpdateWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowRunTimeout('10 seconds')
        );
    }

    private function createAwaitsUpdateUntypedStub(WorkflowClient $client): WorkflowStubInterface
    {
        return $this->createAwaitsUpdateWorkflow($client)->__getUntypedStub();
    }

    private function createUpdateWorkflowStub(WorkflowClient $client): WorkflowStubInterface
    {
        return $this->createUpdateWorkflow($client)->__getUntypedStub();
    }
}
