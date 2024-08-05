<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional;

use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Functional\Client\AbstractClient;
use Temporal\Tests\Workflow\AwaitsUpdateWorkflow;
use Temporal\Tests\Workflow\UpdateWorkflow;

/**
 * @group workflow
 * @group functional
 */
class WorkflowUpdateTestCase extends AbstractClient
{
    /**
     * @group skip-on-test-server
     */
    public function testSimpleCase(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            UpdateWorkflow::class,
            WorkflowOptions::new()->withWorkflowRunTimeout('1m'),
        );

        try {
            $run = $client->start($workflow);
            $updated = $workflow->addName('John Doe');
            $workflow->exit();
            $result = $run->getResult();
        } catch (\Throwable $e) {
            $workflow->exit();
            throw $e;
        }

        $this->assertSame(['Hello, John Doe!'], $result);
        $this->assertSame('Hello, John Doe!', $updated);
    }

    /**
     * @group skip-on-test-server
     */
    public function testFailedValidation(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        try {
            $client->start($workflow);
            $workflow->addName('123');
            $this->fail('Exception should be thrown');
        } catch (WorkflowUpdateException $e) {
            $previous = $e->getPrevious();
            $this->assertInstanceOf(ApplicationFailure::class, $previous);
            $this->assertSame('Name must not contain digits', $previous->getOriginalMessage());
        } finally {
            $workflow->exit();
        }
    }

    /**
     * @group skip-on-test-server
     */
    public function testExecuteThrowException(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        try {
            $client->start($workflow);
            $workflow->throwException('John Doe');
            $this->fail('Exception should be thrown');
        } catch (WorkflowUpdateException $e) {
            $previous = $e->getPrevious();
            $this->assertInstanceOf(ApplicationFailure::class, $previous);
            $this->assertSame('Test exception with John Doe', $previous->getOriginalMessage());
        } finally {
            $workflow->exit();
        }
    }

    /**
     * @group skip-on-test-server
     */
    public function testSimpleCaseWithSideEffect(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        try {
            $run = $client->start($workflow);
            $updated1 = $workflow->randomizeName();
            $updated2 = $workflow->randomizeName(2);
            $workflow->exit();
            $result = $run->getResult();
        } catch (\Throwable $e) {
            $workflow->exit();
            throw $e;
        }

        $this->assertCount(3, $result);
        self::assertNotEmpty($updated1);
        self::assertNotEmpty($updated2);
    }

    /**
     * @group skip-on-test-server
     */
    public function testSimpleCaseWithActivity(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        try {
            $run = $client->start($workflow);
            $updated = $workflow->addNameViaActivity('John Doe');
            $workflow->exit();
            $result = $run->getResult();
        } catch (\Throwable $e) {
            $workflow->exit();
            throw $e;
        }

        $this->assertSame(['Hello, john doe!'], $result);
        $this->assertSame('Hello, john doe!', $updated);
    }

    /**
     * @group skip-on-test-server
     */
    public function testWithoutValidationMethod(): void
    {
        $client = $this->createClient();
        $workflow = $this->createUpdateWorkflow($client);

        try {
            $run = $client->start($workflow);
            $updated = $workflow->addNameWithoutValidation('John Doe 42');
            $workflow->exit();
            $result = $run->getResult();
        } catch (\Throwable $e) {
            $workflow->exit();
            throw $e;
        }

        $this->assertSame(['Hello, John Doe 42!'], $result);
        $this->assertSame('Hello, John Doe 42!', $updated);
    }

    /**
     * @group skip-on-test-server
     */
    public function testAwaitWorks(): void
    {
        $client = $this->createClient();
        $workflow = $this->createAwaitsUpdateWorkflow($client);

        $run = $client->start($workflow);
        $startedAt = \microtime(true);
        $updated = $workflow->addWithTimeout('key', 1, 'fallback');
        $endedAt = \microtime(true);
        $workflow->exit();
        $result = $run->getResult();

        $this->assertGreaterThan(1, $endedAt - $startedAt, 'Await is working');
        $this->assertLessThan(3, $endedAt - $startedAt);
        $this->assertSame(['key' => 'fallback'], (array)$result);
        $this->assertSame('fallback', $updated);
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
        return $client->newWorkflowStub(AwaitsUpdateWorkflow::class, WorkflowOptions::new()
            ->withWorkflowRunTimeout('10 seconds')
        );
    }

    private function createAwaitsUpdateUntypedStub(WorkflowClient $client): WorkflowStubInterface
    {
        return $client->newWorkflowStub(AwaitsUpdateWorkflow::class, WorkflowOptions::new()
            ->withWorkflowRunTimeout('10 seconds')
        )->__getUntypedStub();
    }
}
