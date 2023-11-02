<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Functional\Interceptor;

use Temporal\Client\WorkflowOptions;
use Temporal\Tests\Functional\Client\ClientTestCase;
use Temporal\Tests\Workflow\Header\ChildedHeaderWorkflow;
use Temporal\Tests\Workflow\Header\EmptyHeaderWorkflow;

/**
 * Header is a special case of context propagation. There are no ability to write Header using public API. BUtt
 * it is possible to pass it using interceptors.
 * A lot of regular cases are tested in {@see InterceptorsTestCase}
 *
 * @group client
 * @group workflow
 * @group functional
 */
class HeaderTestCase extends ClientTestCase
{
    public function testWorkflowEmptyHeader(): void
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            EmptyHeaderWorkflow::class,
            WorkflowOptions::new(),
        );

        $this->assertSame([], (array) $simple->handler()[0]);
    }

    /**
     * ChildWorkflow should inherit headers from his parent
     */
    public function testChildWorkflowHeaderInheritance(): void
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            ChildedHeaderWorkflow::class,
            WorkflowOptions::new(),
        );

        $result = $simple->handler(['test-foo-bar' => 'bar-test-foo'], true);
        $this->assertSame([
            'test-foo-bar' => 'bar-test-foo',
        ], (array) $result[0]);

        $this->assertArrayHasKey('test-foo-bar', (array) $result[2]);
        $this->assertSame(((array) $result[2])['test-foo-bar'], 'bar-test-foo');
    }

    public function testActivityHeaderInheritance(): void
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            ChildedHeaderWorkflow::class,
            WorkflowOptions::new(),
        );

        $result = $simple->handler(['test-foo-bar' => 'bar-test-foo']);
        $this->assertSame([
            'test-foo-bar' => 'bar-test-foo',
        ], (array) $result[0]);

        $this->assertArrayHasKey('test-foo-bar', (array) $result[1]);
        $this->assertSame(((array) $result[1])['test-foo-bar'], 'bar-test-foo');
    }

    public function testActivityHeaderOverwriteByEmpty(): void
    {
        $client = $this->createClient();
        $simple = $client->newWorkflowStub(
            ChildedHeaderWorkflow::class,
            WorkflowOptions::new(),
        );

        $result = $simple->handler(['test-foo-bar' => 'bar-test-foo'], false, []);

        $this->assertEquals(['test-foo-bar' => 'bar-test-foo'], (array) $result[0]);
        $this->assertArrayNotHasKey('test-foo-bar', (array) $result[1]);
    }
}
