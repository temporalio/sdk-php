<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Workflow\HeaderWorkflow;

/**
 * @group client
 * @group functional
 */
class HeaderTestCase extends ClientTestCase
{
    public function testWorkflowEmptyHeader()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            HeaderWorkflow::class,
            WorkflowOptions::new()
                ->withHeader([])
        );

        $this->assertSame([], (array)$simple->handler()[0]);
    }

    public function testWorkflowSimpleCase()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            HeaderWorkflow::class,
            WorkflowOptions::new()
                ->withHeader(['fooo' => 'bar'])
        );

        $this->assertSame(['fooo' => 'bar'], (array)$simple->handler()[0]);
    }

    public function testWorkflowDifferentTypes()
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            HeaderWorkflow::class,
            WorkflowOptions::new()
                ->withHeader([
                    'foo' => 'bar',
                    123 => 123,
                    '' => null,
                ])
        );

        $this->assertEquals([
            'foo' => 'bar',
            123 => '123',
            '' => '',
        ], (array)$simple->handler()[0]);
    }
}
