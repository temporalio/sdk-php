<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests\Client\Workflow;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use Temporal\Client\Internal\Coroutine\Stack;
use Temporal\Client\Internal\Workflow\Input;
use Temporal\Client\Internal\Workflow\Process\Process;
use Temporal\Client\Internal\Workflow\Process\CoroutineAwareInterface;
use Temporal\Client\Internal\Workflow\Requests;
use Temporal\Client\Worker\Environment\EnvironmentInterface;
use Temporal\Client\Workflow\WorkflowContextInterface;
use Temporal\Client\Workflow\WorkflowContext;
use Temporal\Client\Workflow\WorkflowInfo;
use Temporal\Tests\Client\Testing\TestingEnvironment;

class ContextEnvironmentTestCase extends WorkflowTestCase
{
    /**
     * @param EnvironmentInterface $env
     * @return WorkflowContextInterface
     */
    public function context(EnvironmentInterface $env): WorkflowContextInterface
    {
        $input = new Input(new WorkflowInfo());

        return new WorkflowContext($this->loop, $env, $input, $this->requests);
    }

    /**
     * @return void
     */
    public function testTimeZone(): void
    {
        $zones = \DateTimeZone::listIdentifiers();
        $zone = new CarbonTimeZone($zones[\array_rand($zones)]);

        $context = $this->context((new TestingEnvironment())->setZone($zone));

        $this->assertSame($zone, $context->getTimeZone());
    }

    /**
     * @return void
     */
    public function testReplaying(): void
    {
        $env = new TestingEnvironment();

        // Default value
        $context = $this->context($env);
        $this->assertFalse($context->isReplaying());

        // Replaying
        $context = $this->context($env->setIsReplaying(true));
        $this->assertTrue($context->isReplaying());

        // Not Replaying
        $context = $this->context($env->setIsReplaying(false));
        $this->assertFalse($context->isReplaying());
    }

    /**
     * @return void
     */
    public function testTickTime(): void
    {
        $time = Carbon::now()->subDays(100);

        $context = $this->context((new TestingEnvironment())->setTickTime($time));

        $this->assertSame($time, $context->now());
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function testRunId(): void
    {
        $env = new TestingEnvironment();

        $context = $this->context($env);
        $this->assertNull($context->getRunId());

        for ($i = 0; $i < 5; ++$i) {
            $id = \random_bytes(42);

            $context = $this->context($env->setRunId($id));
            $this->assertSame($id, $context->getRunId());
        }
    }
}
