<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\DataConverter\Type;
use Temporal\Tests\DTO\Message;
use Temporal\Tests\Workflow\ArrayOfObjectsWorkflow;

/**
 * @group client
 * @group functional
 */
class TypeArrayOfObjectsTestCase extends AbstractClient
{
    public function testArrayOfMessagesReceived(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(ArrayOfObjectsWorkflow::class);

        /** @var Message[] $result */
        $result = $client->start($workflow, 'John')->getResult(Type::arrayOf(Message::class));
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Message::class, $result[0]);

        $this->assertSame('Hello john', $result[0]->message);
        $this->assertSame('Hello JOHN', $result[1]->message);
    }
}
