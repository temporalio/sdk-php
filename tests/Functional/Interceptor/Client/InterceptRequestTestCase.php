<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Interceptor\Client;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowOptions;
use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Tests\Workflow\Interceptor\HeadersWorkflow;
use Temporal\Tests\Workflow\Interceptor\QueryHeadersWorkflow;
use Temporal\Tests\Workflow\Interceptor\SignalHeadersWorkflow;

/**
 * @group workflow
 * @group functional
 */
final class InterceptRequestTestCase extends InterceptorTestCase
{
    public function testSingleInterceptor(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            HeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $result = (array)$workflow->handler();

        // Workflow header
        $this->assertSame([
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::execute() */
            'execute' => '1',
        ], (array)$result[0]);
        // Activity header
        $this->assertEquals([
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::execute() */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::handleOutboundRequest() */
            'handleOutboundRequest' => '1',
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::handleActivityInbound() */
            'handleActivityInbound' => '1',
        ], (array)$result[1]);
    }

    public function testSignalMethod(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            SignalHeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $run = $client->start($workflow);
        $workflow->signal();

        // Workflow header
        $this->assertSame([
            /**
             * Inherited from handler run
             * @see \Temporal\Tests\Interceptor\FooHeaderIterator::execute()
             */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::handleSignal() */
            'handleSignal' => '1',
        ], (array)$run->getResult());
    }

    public function testQueryMethod(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            QueryHeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $client->start($workflow);
        $result = $workflow->query();

        // Workflow header
        $this->assertEquals([
            /**
             * Inherited from handler run
             * @see \Temporal\Tests\Interceptor\FooHeaderIterator::execute()
             */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::handleQuery() */
            'handleQuery' => '1',
        ], $result);
    }
}
