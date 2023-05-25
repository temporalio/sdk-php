<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Interceptor;

use Carbon\CarbonInterval;
use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Functional\Interceptor\AbstractClient;
use Temporal\Tests\Workflow\Interceptor\AwaitHeadersWorkflow;
use Temporal\Tests\Workflow\Interceptor\ContinueAsNewHeadersWorkflow;

/**
 * @group client
 * @group workflow
 * @group functional
 */
final class InterceptorsTimeSkippingTestCase extends AbstractClient
{
    public function testJustTimers(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            AwaitHeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $result = (array)$workflow->handler();

        // Workflow header
        $this->assertEquals([
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::start */
            'start' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute() */
            'execute' => '1',
        ], (array)$result[0]);
    }

    public function testContinueAsNew(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            ContinueAsNewHeadersWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowExecutionTimeout(CarbonInterval::seconds(5)),
        );

        $result = (array)$workflow->handler();

        // Workflow header
        $this->assertEquals([
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::start */
            'start' => '1',
            'ContinueAsNew' => '1',
            /** @see \Temporal\Tests\Interceptor\InterceptorCallsCounter::execute() */
            'execute' => '2',
        ], (array)$result[0]);
    }
}
