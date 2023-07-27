<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Exception\InvalidArgumentException;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\DTO\User;
use Temporal\Tests\Unit\Declaration\Fixture\WorkflowWithoutHandler;
use Temporal\Tests\Workflow\ActivityReturnTypeWorkflow;
use Temporal\Tests\Workflow\Case335Workflow;
use Temporal\Tests\Workflow\GeneratorWorkflow;
use Temporal\Tests\Workflow\QueryWorkflow;
use Temporal\Tests\Workflow\SignalledWorkflowReusable;
use Temporal\Tests\Workflow\SignalledWorkflowWithInheritance;
use Temporal\Tests\Workflow\SimpleDTOWorkflow;
use Temporal\Tests\Workflow\SimpleWorkflow;

/**
 * @group client
 * @group functional
 */
class TypedStubTestCase extends ClientTestCase
{
    public function testGetResult()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(SimpleWorkflow::class);

        $this->assertSame('HELLO WORLD', $simple->handler('hello world'));
    }

    public function testStartAsync()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(SimpleWorkflow::class);

        $r = $client->start($simple, 'test');

        $this->assertNotEmpty($r->getExecution()->getID());
        $this->assertNotEmpty($r->getExecution()->getRunID());

        $this->assertSame('TEST', $r->getResult());
    }

    public function testStartWithoutHandler()
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(WorkflowWithoutHandler::class);

        $this->expectExceptionMessage(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to start workflow without workflow handler');

        $client->start($workflow);
    }

    public function testStartWithSignalWithoutHandler()
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(WorkflowWithoutHandler::class);

        $this->expectExceptionMessage(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to start workflow without workflow handler');

        $client->startWithSignal($workflow, 'signal');
    }

    public function testQueryWorkflow()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(QueryWorkflow::class);


        $e = $client->start($simple);
        $this->assertNotEmpty($e->getExecution()->getID());
        $this->assertNotEmpty($e->getExecution()->getRunID());

        $simple->add(88);

        $this->assertSame(88, $simple->get());
        $this->assertSame(88, $e->getResult());
    }

    public function testGetDTOResult()
    {
        $w = $this->createClient();
        $dto = $w->newWorkflowStub(SimpleDTOWorkflow::class);

        $u = new User();
        $u->name = 'Antony';
        $u->email = 'email@domain.com';

        $this->assertEquals(
            new Message(sprintf("Hello %s <%s>", $u->name, $u->email)),
            $dto->handler($u)
        );
    }

    public function testVoidReturnType()
    {
        $w = $this->createClient();
        $dto = $w->newWorkflowStub(ActivityReturnTypeWorkflow::class);

        $this->assertEquals(
            100,
            $dto->handler()
        );
    }

    public function testGeneratorCoroutines()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(GeneratorWorkflow::class);

        $this->assertSame(
            [
                ['HELLO WORLD', 'HELLO WORLD'],
                ['ANOTHER', 'ANOTHER']
            ],
            $simple->handler('hello world')
        );
    }

    public function testSignalRunningWorkflowWithInheritedSignal()
    {
        $client = $this->createClient();

        $workflow = $client->newWorkflowStub(SignalledWorkflowWithInheritance::class);
        $workflowRun = $client->start($workflow, 1);
        $workflowId = $workflowRun->getExecution()->getID();
        $workflowRunId = $workflowRun->getExecution()->getRunID();

        $signaller = $client->newRunningWorkflowStub(SignalledWorkflowWithInheritance::class, $workflowId, $workflowRunId);
        $signaller->addValue('test1');

        $result = $workflowRun->getResult();
        $this->assertEquals(['test1'], $result);
    }

    public function testSignalRunningWorkflowWithInheritedSignalViaParentInterface()
    {
        $client = $this->createClient();

        $workflow = $client->newWorkflowStub(SignalledWorkflowWithInheritance::class);
        $workflowRun = $client->start($workflow, 1);
        $workflowId = $workflowRun->getExecution()->getID();
        $workflowRunId = $workflowRun->getExecution()->getRunID();

        $signaller = $client->newRunningWorkflowStub(SignalledWorkflowReusable::class, $workflowId, $workflowRunId);
        $signaller->addValue('test1');

        $result = $workflowRun->getResult();
        $this->assertEquals(['test1'], $result);
    }

    public function testSignalResolvesCondidtionsBeforePromiseRun()
    {
        $client = $this->createClient();

        $workflow = $client->newWorkflowStub(Case335Workflow::class);
        $workflowRun = $client->startWithSignal($workflow, 'signal');

        $result = $workflowRun->getResult('bool', 5);
        $this->assertFalse($result);
    }
}
