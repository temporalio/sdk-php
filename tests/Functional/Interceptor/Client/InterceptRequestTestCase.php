<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Interceptor\Client;

use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Workflow\Interceptor\FooWorkflow;

/**
 * @group workflow
 * @group functional
 */
final class InterceptRequestTestCase extends InterceptorTestCase
{
    public function testSingleInterceptor(): void
    {
        $client = $this->createClient();
        $workflow = $client->newWorkflowStub(
            FooWorkflow::class,
            WorkflowOptions::new(),
        );

        $this->assertSame(['Foo' => '1'], (array)$workflow->handler()[1]);
    }
}
