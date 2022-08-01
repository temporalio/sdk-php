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
use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Tests\Workflow\AsyncClosureWorkflow;

/**
 * @group client
 * @group functional
 */
class AsyncClosureTestCase extends ClientTestCase
{
    use WithoutTimeSkipping;

    public function testAsyncIsCancelledWithTimer()
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            AsyncClosureWorkflow::class,
            WorkflowOptions::new()->withWorkflowExecutionTimeout(3)
        );

        $run = $client->start($workflow);

        $this->assertSame('before', $run->getResult('string'));
    }
}
