<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Tests\Unit\DTO\Type\EnumType\ScalarEnum;
use Temporal\Tests\Unit\DTO\Type\EnumType\SimpleEnum;
use Temporal\Tests\Workflow\ScalarEnumWorkflow;
use Temporal\Tests\Workflow\SimpleEnumWorkflow;

/**
 * @group client
 * @group functional
 *
 * @requires PHP >= 8.1
 */
class EnumTestCase extends ClientTestCase
{
    public function testSimpleEnum(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(SimpleEnumWorkflow::class);

        $result = $client->start($workflow, SimpleEnum::TEST);

        $this->assertSame(SimpleEnum::TEST, $result->getResult(SimpleEnum::class));
    }

    public function testScalarEnum(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(ScalarEnumWorkflow::class);

        $result = $client->start($workflow, ScalarEnum::TESTED_ENUM);

        $this->assertSame(ScalarEnum::TESTED_ENUM, $result->getResult(ScalarEnum::class));
    }
}
