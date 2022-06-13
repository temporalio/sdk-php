<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Carbon\CarbonInterval;
use Temporal\Api\Workflowservice\V1\ListClosedWorkflowExecutionsRequest;
use Temporal\Client\GRPC\Context;
use Temporal\Exception\Client\TimeoutException;

/**
 * @group client
 * @group functional
 */
class ServiceClientTestCase extends ClientTestCase
{
    public function testTimeoutException()
    {
        $this->expectNotToPerformAssertions();
    }
}
