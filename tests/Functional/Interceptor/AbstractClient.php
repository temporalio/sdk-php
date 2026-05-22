<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Interceptor;

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Tests\Fixtures\PipelineProvider;
use Temporal\Tests\Functional\AbstractFunctional;
use Temporal\Tests\Interceptor\InterceptorCallsCounter;

/**
 * @group client
 */
abstract class AbstractClient extends AbstractFunctional
{
    /**
     * @param string $connection
     * @return WorkflowClient
     */
    protected function createClient(?string $connection = null): WorkflowClient
    {
        return new WorkflowClient(
            ServiceClient::create($connection ?? (\getenv('TEMPORAL_ADDRESS') ?: '127.0.0.1:7233')),
            interceptorProvider: new PipelineProvider([InterceptorCallsCounter::class]),
        );
    }
}
