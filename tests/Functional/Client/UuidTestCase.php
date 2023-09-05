<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Tests\Workflow\SimpleUuidWorkflow;

/**
 * @group client
 * @group functional
 */
class UuidTestCase extends ClientTestCase
{
    public function testUuidPassedAndReturned(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(SimpleUuidWorkflow::class);

        $uuid = Uuid::uuid4();
        $result = $workflow->handler($uuid);

        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertSame((string)$uuid, (string)$result);
    }

    public function testSideEffectWithUuid(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(SimpleUuidWorkflow::class);

        $uuid = Uuid::uuid4();
        $run = $client->start($workflow, $uuid);
        $result = $run->getResult(UuidInterface::class);

        (new WorkflowReplayer())->replayFromServer(
            'SimpleUuidWorkflow',
            $run->getExecution(),
        );

        $this->assertInstanceOf(UuidInterface::class, $result);
        $this->assertSame((string)$uuid, (string)$result);
    }
}
