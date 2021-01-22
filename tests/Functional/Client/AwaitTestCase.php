<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Functional\Client;

use Temporal\Tests\Workflow\AggregatedWorkflow;
use Temporal\Tests\Workflow\LoopWithSignalCoroutinesWorkflow;
use Temporal\Tests\Workflow\LoopWorkflow;
use Temporal\Tests\Workflow\WaitWorkflow;

class AwaitTestCase extends ClientTestCase
{
    public function testSimpleAwait()
    {
        $client = $this->createClient();
        $wait = $client->newWorkflowStub(WaitWorkflow::class);

        $run = $client->start($wait);

        $wait->unlock('unlock the condition');

        $this->assertSame('unlock the condition', $run->getResult('string'));
    }

    public function testAggregated()
    {
        $client = $this->createClient();
        $wait = $client->newWorkflowStub(AggregatedWorkflow::class);

        $run = $client->start($wait, 4);

        $wait->addValue('test1');
        $wait->addValue('test2');
        $wait->addValue('test3');
        $wait->addValue('test4');

        $this->assertSame(
            [
                'test1',
                'test2',
                'test3',
                'test4'
            ],
            $run->getResult('string')
        );
    }

    public function testLoop()
    {
        $client = $this->createClient();
        $wait = $client->newWorkflowStub(LoopWorkflow::class);

        $run = $client->start($wait, 4);

        $wait->addValue('test1');
        $wait->addValue('test2');
        $wait->addValue('test3');
        $wait->addValue('test4');

        $this->assertSame(
            [
                'TEST1',
                'TEST2',
                'TEST3',
                'TEST4'
            ],
            $run->getResult('string')
        );
    }

    public function testLoopWithCoroutinesInSignals()
    {
        $client = $this->createClient();
        $wait = $client->newWorkflowStub(LoopWithSignalCoroutinesWorkflow::class);

        $run = $client->start($wait, 4);

        $wait->addValue('test1');
        $wait->addValue('test2');
        $wait->addValue('test3');
        $wait->addValue('test4');

        $this->assertSame(
            [
                'IN SIGNAL 2 IN SIGNAL TEST1',
                'IN SIGNAL 2 IN SIGNAL TEST2',
                'IN SIGNAL 2 IN SIGNAL TEST3',
                'IN SIGNAL 2 IN SIGNAL TEST4'
            ],
            $run->getResult('string')
        );
    }

    // todo: test failure in signal coroutine
}
