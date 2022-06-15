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
use Temporal\DataConverter\Type;
use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Exception\Failure\ApplicationFailure;
use Temporal\Exception\Failure\CanceledFailure;
use Temporal\Tests\Workflow\AggregatedWorkflow;
use Temporal\Tests\Workflow\AsyncClosureWorkflow;
use Temporal\Tests\Workflow\LoopWithSignalCoroutinesWorkflow;
use Temporal\Tests\Workflow\LoopWorkflow;
use Temporal\Tests\Workflow\WaitWorkflow;
use Temporal\Workflow\WorkflowStub;

/**
 * @group client
 * @group functional
 */
class AsyncClosureTestCase extends ClientTestCase
{
    public function testAsyncIsCancelledWithTimer()
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            AsyncClosureWorkflow::class,
            WorkflowOptions::new()->withWorkflowExecutionTimeout(1)
        );

        $run = $client->start($workflow);

        $this->assertSame('Done', $run->getResult('string'));
    }
}
