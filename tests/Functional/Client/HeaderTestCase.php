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
use Temporal\Workflow\ChildWorkflowOptions;

/**
 * todo: rewrite for interceptors and hidden headers
 * @group client
 * @group functional
 */
class HeaderTestCase extends ClientTestCase
{
    // public function testWorkflowEmptyHeader(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         [],
    //     );
    //
    //     $this->assertSame([], (array)$simple->handler()[0]);
    // }
    //
    // public function testWorkflowSimpleCase(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         ['fooo' => 'bar'],
    //     );
    //
    //     $this->assertSame(['fooo' => 'bar'], (array)$simple->handler()[0]);
    // }
    //
    // /**
    //  * Pass Header values of different types
    //  */
    // public function testWorkflowDifferentTypes(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         [
    //             'foo' => 'bar',
    //             123 => 123,
    //             '' => null,
    //             'false' => false,
    //         ],
    //     );
    //
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //         123 => '123',
    //         '' => '',
    //         'false' => '',
    //     ], (array)$simple->handler()[0]);
    // }
    //
    // /**
    //  * Set headers for ChildWorkflow only
    //  */
    // public function testChildWorkflowHeader(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //     );
    //
    //     $result = $simple->handler(['test' => 'best']);
    //     $this->assertEquals([], (array)$result[0]);
    //
    //     $this->assertEquals([
    //         'test' => 'best',
    //     ], (array)$result[2]);
    // }
    //
    // /**
    //  * ChildWorkflow should inherit headers from his parent
    //  * Case when {@see ChildWorkflowOptions} is not passed
    //  */
    // public function testChildWorkflowHeaderInheritance(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         [
    //             'foo' => 'bar',
    //         ],
    //     );
    //
    //     $result = $simple->handler(true);
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //     ], (array)$result[0]);
    //
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //     ], (array)$result[2]);
    // }
    //
    // /**
    //  * ChildWorkflow should inherit headers from his parent
    //  * Case when {@see ChildWorkflowOptions} without headers is passed
    //  */
    // public function testChildWorkflowHeaderOverwriteByEmpty(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         [
    //             'foo' => 'bar',
    //         ],
    //     );
    //
    //     $result = $simple->handler([]);
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //     ], (array)$result[0]);
    //
    //     $this->assertEquals([], (array)$result[2]);
    // }
    //
    // /**
    //  * ChildWorkflow should inherit headers from his parent and merge with new ones
    //  */
    // public function testChildWorkflowHeaderMerge(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         [
    //             'foo' => 'bar',
    //         ],
    //     );
    //
    //     $result = $simple->handler((object) ['test' => 'best']);
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //     ], (array)$result[0]);
    //
    //     $this->assertEquals([
    //         'foo' => 'bar',
    //         'test' => 'best',
    //     ], (array)$result[2]);
    // }
    //
    // public function testActivityHeaderOnly(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //     );
    //
    //     $result = $simple->handler(false, ['test' => 'best']);
    //     $this->assertEquals([], (array)$result[0]);
    //
    //     $this->assertEquals([
    //         'test' => 'best',
    //     ], (array)$result[1]);
    // }
    //
    // public function testActivityHeaderInheritance(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         ['test' => 'best']
    //     );
    //
    //     $result = $simple->handler(false, null);
    //
    //     $this->assertEquals(['test' => 'best'], (array)$result[0]);
    //     $this->assertEquals(['test' => 'best'], (array)$result[1]);
    // }
    //
    // public function testActivityHeaderOverwriteByEmpty(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         ['test' => 'best']
    //     );
    //
    //     $result = $simple->handler(false, []);
    //
    //     $this->assertEquals(['test' => 'best'], (array)$result[0]);
    //     $this->assertEquals([], (array)$result[1]);
    // }
    //
    // public function testActivityHeaderMerge(): void
    // {
    //     $client = $this->createClient();
    //     $simple = $client->newWorkflowStub(
    //         HeaderWorkflow::class,
    //         WorkflowOptions::new(),
    //         ['foo' => 'bar',]
    //     );
    //
    //     $result = $simple->handler(false, ['test' => 'best']);
    //
    //     $this->assertEquals(['foo' => 'bar'], (array)$result[0]);
    //     $this->assertEquals(['foo' => 'bar', 'test' => 'best'], (array)$result[1]);
    // }
}
