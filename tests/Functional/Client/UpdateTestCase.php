<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Client\WorkflowOptions;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Workflow\UpdateWorkflow;

/**
 * @group client
 * @group functional
 */
class UpdateTestCase extends AbstractClient
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
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

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
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

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
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

        try {
            $run = $client->start($workflow);
            $workflow->randomizeName();
            $updated = $workflow->randomizeName(2);
            $workflow->exit();
            $result = $run->getResult();
        } catch (\Throwable $e) {
            $workflow->exit();
            throw $e;
        }

        $this->assertCount(3, $result);
        self::assertNotEmpty($updated);
    }

    /**
     * @group skip-on-test-server
     */
    public function testSimpleCaseWithActivity(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

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
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

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
}
