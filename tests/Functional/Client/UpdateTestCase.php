<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Tests\Workflow\UpdateWorkflow;

/**
 * @group client
 * @group functional
 */
class UpdateTestCase extends AbstractClient
{
    public function testUuidPassedAndReturned(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

        $run = $client->start($workflow);
        $workflow->addName('John Doe');
        $workflow->exit();
        $result = $run->getResult();

        $this->assertSame(['Hello, John Doe!'], $result);
    }
}
