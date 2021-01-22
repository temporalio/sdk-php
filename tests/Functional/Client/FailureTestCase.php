<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Carbon\CarbonInterval;
use Temporal\Api\Filter\V1\WorkflowTypeFilter;
use Temporal\Api\Workflowservice\V1\DescribeNamespaceRequest;
use Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest;
use Temporal\Client\GRPC\Context;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\WorkflowClient;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\ChildWorkflowFailure;
use Temporal\Tests\TestCase;

class FailureTestCase extends ClientTestCase
{
    public function testSimpleFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ExceptionalWorkflow');

        $e = $ex->start();

        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        try {
            $this->assertSame('OK', $ex->getResult());
            $this->fail('unreachable');
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('workflow error', $e->getPrevious()->getMessage());
        }
    }

    public function testActivityFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ExceptionalActivityWorkflow');

        $e = $ex->start();
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        $this->expectException(WorkflowFailedException::class);
        $ex->getResult();
    }

    public function testChildWorkflowFailurePropagation()
    {
        $w = $this->createClient();
        $ex = $w->newUntypedWorkflowStub('ComplexExceptionalWorkflow');

        $e = $ex->start();
        $this->assertNotEmpty($e->id);
        $this->assertNotEmpty($e->runId);

        try {
            $ex->getResult();
            $this->fail('unreachable');
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ChildWorkflowFailure::class, $e->getPrevious());
            $this->assertStringContainsString('ComplexExceptionalWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
            $this->assertStringContainsString('ExceptionalActivityWorkflow', $e->getPrevious()->getMessage());

            $e = $e->getPrevious();

            $this->assertInstanceOf(ApplicationFailure::class, $e->getPrevious());
            $this->assertStringContainsString('SimpleActivity->fail', $e->getPrevious()->getMessage());
        }
    }
}
