<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Tests\Workflow\QueryWorkflow;
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
}
