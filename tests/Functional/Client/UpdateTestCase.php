<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\Client\WorkflowStubInterface;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Tests\Workflow\AwaitsUpdateWorkflow;
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
     * @group skip-on-test-server
     */
    public function testSingleAwaitsWithoutTimeout(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);
        /** @see AwaitsUpdateWorkflow::add */
        $handle = $stub->startUpdate('await', 'key');
        $this->assertNull($handle->getResult());

        /** @see AwaitsUpdateWorkflow::get */
        $this->assertNull($stub->query('getValue', "key")->getValue(0));

        /** @see AwaitsUpdateWorkflow::resolve */
        $handle = $stub->update('resolveValue', "key", "resolved");
        $this->assertSame("resolved", $handle->getValue(0));

        /** @see AwaitsUpdateWorkflow::get */
        $this->assertSame("resolved", $stub->query('getValue', "key")->getValue(0));

        /** @see AwaitsUpdateWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame(['key' => 'resolved'], (array)$result);
    }

    /**
     * @group skip-on-test-server
     */
    public function testMultipleAwaitsWithoutTimeout(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);
        for ($i = 1; $i <= 5; $i++) {
            /** @see AwaitsUpdateWorkflow::add */
            $handle = $stub->startUpdate('await', "key$i", 5, "fallback$i");
            $this->assertNull($handle->getResult());

            /** @see AwaitsUpdateWorkflow::get */
            $this->assertNull($stub->query('getValue', "key$i")->getValue(0));
        }

        for ($i = 1; $i <= 5; $i++) {
            /** @see AwaitsUpdateWorkflow::resolve */
            $handle = $stub->update('resolveValue', "key$i", "resolved$i");
            $this->assertSame("resolved$i", $handle->getValue(0));

            /** @see AwaitsUpdateWorkflow::get */
            $this->assertSame("resolved$i", $stub->query('getValue', "key$i")->getValue(0));
        }

        /** @see AwaitsUpdateWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame([
            'key1' => 'resolved1',
            'key2' => 'resolved2',
            'key3' => 'resolved3',
            'key4' => 'resolved4',
            'key5' => 'resolved5',
        ], (array)$result);
    }

    /**
     * @group skip-on-test-server
     */
    public function testMultipleAwaitsWithTimeout(): void
    {
        $client = $this->createClient();
        $stub = $this->createAwaitsUpdateUntypedStub($client);

        $client->start($stub);
        for ($i = 1; $i <= 5; $i++) {
            /** @see AwaitsUpdateWorkflow::addWithTimeout */
            $handle = $stub->startUpdate('awaitWithTimeout', "key$i", 5, "fallback$i");
            $this->assertNull($handle->getResult());
        }

        for ($i = 1; $i <= 5; $i++) {
            /** @see AwaitsUpdateWorkflow::resolve */
            $handle = $stub->update('resolveValue', "key$i", "resolved$i");
            $this->assertSame("resolved$i", $handle->getValue(0));
        }

        /** @see AwaitsUpdateWorkflow::exit */
        $stub->signal('exit');
        $result = $stub->getResult();

        $this->assertSame([
            'key1' => 'resolved1',
            'key2' => 'resolved2',
            'key3' => 'resolved3',
            'key4' => 'resolved4',
            'key5' => 'resolved5',
        ], (array)$result);
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
