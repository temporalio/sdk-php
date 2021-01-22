<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Functional\Client;

use Temporal\Exception\Client\WorkflowFailedException;
use Temporal\Exception\Failure\ActivityFailure;
use Temporal\Tests\Workflow\SagaWorkflow;

class SagaTestCase extends ClientTestCase
{
    public function testGetResult()
    {
        $w = $this->createClient();
        $saga = $w->newWorkflowStub(SagaWorkflow::class);

        try {
            $saga->run();
        } catch (WorkflowFailedException $e) {
            $this->assertInstanceOf(ActivityFailure::class, $e->getPrevious());
        }
    }
}
