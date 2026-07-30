<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Testing\WithoutTimeSkipping;
use Temporal\Tests\Workflow\UpdateWorkflow;

/**
 * @group functional
 * @group client
 */
class UpdateOnTimeSkippingServerTestCase extends AbstractClient
{
    use WithoutTimeSkipping;

    public function testUpdateResolvesOnTimeSkippingServer(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(UpdateWorkflow::class);

        $client->start($workflow);

        /** @see UpdateWorkflow::addNameWithoutValidation */
        $result = $workflow->__getUntypedStub()
            ->startUpdate('addNameWithoutValidation', 'Test')
            ->getResult(5);

        self::assertSame('Hello, Test!', $result);

        $workflow->exit();
    }
}
