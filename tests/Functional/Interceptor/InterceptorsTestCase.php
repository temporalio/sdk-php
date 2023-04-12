<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Interceptor;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowOptions;
use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Tests\Workflow\Interceptor\HeadersWorkflow;
use Temporal\Tests\Workflow\Interceptor\QueryHeadersWorkflow;
use Temporal\Tests\Workflow\Interceptor\SignalHeadersWorkflow;

/**
 * @group client
 * @group workflow
 * @group functional
 */
final class InterceptorsTestCase extends AbstractClient
{
    use WithoutTimeSkipping;

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
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::start */
            'start' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute() */
            'execute' => '1',
        ], (array)$result[0]);
        // Activity header
        $this->assertEquals([
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::start */
            'start' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute() */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::handleOutboundRequest() */
            'handleOutboundRequest' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::handleActivityInbound() */
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
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::start() */
            'start' => '1',
            /**
             * Inherited from handler run
             *
             * @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute()
             */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::handleSignal() */
            'handleSignal' => '1',
        ], (array)$run->getResult());
    }

    public function testSignalWithStartMethod(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            SignalHeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $run = $client->startWithSignal($workflow, 'signal');

        // Workflow header
        $this->assertSame([
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::signalWithStart() */
            'signalWithStart' => '1',
            /**
             * Inherited from handler run
             *
             * @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute()
             */
            'execute' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::handleSignal() */
            'handleSignal' => '1',
        ], (array)$run->getResult());
    }

    // todo: rewrite tests because there is no header in query call
    // todo: add test about dynamic query
    // public function testQueryMethod(): void
    // {
    //     $client = $this->createClient();
    //     $workflow = $client->newWorkflowStub(
    //         QueryHeadersWorkflow::class,
    //         WorkflowOptions::new()
    //             ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
    //     );
    //
    //     $client->start($workflow);
    //     $result = $workflow->getHeaders();
    //     $workflow->signal();
    //
    //     // Workflow header
    //     $this->assertEquals([
    //         /** @see \Temporal\Tests\Interceptor\FooHeaderIterator::handleQuery() */
    //         'handleQuery' => '1',
    //     ], $result);
    // }

    /**
     * Workflow context should be set for each Query call
     * Todo: doesn't work on test server
     */
    // public function testQueryMethodContexts(): void
    // {
    //     $client = $this->createClient();
    //     $workflow1 = $client->newWorkflowStub(
    //         QueryHeadersWorkflow::class,
    //         WorkflowOptions::new()
    //             ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
    //     );
    //     $workflow2 = $client->newWorkflowStub(
    //         QueryHeadersWorkflow::class,
    //         WorkflowOptions::new()
    //             ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
    //     );
    //
    //     $run1 = $client->start($workflow1);
    //     $run2 = $client->start($workflow2);
    //
    //     $context1 = $workflow1->getContext();
    //     $context2 = $workflow2->getContext();
    //     $context1_2 = $workflow1->getContext();
    //     $context2_2 = $workflow2->getContext();
    //
    //     self::assertNotSame($run1->getExecution()->getRunID(), $run2->getExecution()->getRunID());
    //     self::assertNotEquals($context2['RunId'], $context1['RunId']);
    //     self::assertSame($context1['RunId'], $context1_2['RunId']);
    //     self::assertSame($context2['RunId'], $context2_2['RunId']);
    //     self::assertSame($run2->getExecution()->getRunID(), $context2['RunId']);
    //     self::assertSame($run1->getExecution()->getRunID(), $context1['RunId']);
    // }
}
