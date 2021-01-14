<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Temporal\Tests\Client;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\DTO\User;
use Temporal\Tests\TestCase;
use Temporal\Tests\Workflow\QueryWorkflow;
use Temporal\Tests\Workflow\SimpleDTOWorkflow;
use Temporal\Tests\Workflow\SimpleWorkflow;

class TypedStubTestCase extends TestCase
{
    public function testGetResult()
    {
        $w = $this->createClient();
        $simple = $w->newWorkflowStub(SimpleWorkflow::class);

        $this->assertSame('HELLO WORLD', $simple->handler('hello world'));
    }

    public function testStartAsync()
    {
        $w = $this->createClient();
        $simple = $w->newWorkflowStub(SimpleWorkflow::class);

        $r = $w->start($simple, 'test');

        $this->assertNotEmpty($r->getExecution()->id);
        $this->assertNotEmpty($r->getExecution()->runId);

        $this->assertSame('TEST', $r->getResult());
    }

    public function testQueryWorkflow()
    {
        $w = $this->createClient();
        $simple = $w->newWorkflowStub(QueryWorkflow::class);

        $e = $simple->startAsync();
        $this->assertNotEmpty($e->getExecution()->id);
        $this->assertNotEmpty($e->getExecution()->runId);

        $simple->add(88);

        $this->assertSame(88, $simple->get());
        $this->assertSame(88, $e->getResult());
    }

    // todo: fix return type
//    public function testGetDTOResult()
//    {
//        $w = $this->createClient();
//        $dto = $w->newWorkflowStub(SimpleDTOWorkflow::class);
//
//        $u = new User();
//        $u->name = 'Antony';
//        $u->email = 'email@domain.com';
//
//        $this->assertEquals(
//            new Message(sprintf("Hello %s <%s>", $u->name, $u->email)),
//            $dto->handler($u)
//        );
//    }

    /**
     * @return WorkflowClient
     */
    private function createClient(): WorkflowClient
    {
        $sc = ServiceClient::createInsecure('localhost:7233');

        return new WorkflowClient($sc);
    }
}
