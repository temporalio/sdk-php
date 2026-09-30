<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Tests;

use Carbon\CarbonInterval;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Testing\Replay\CoreWorkflowReplayer;
use Temporal\Testing\Replay\WorkflowReplayer;
use Temporal\Worker\Core\CoreWorkerFactory;

abstract class TestCase extends BaseTestCase
{
    protected function assertEqualIntervals(\DateInterval $expected, \DateInterval $actual): void
    {
        if ($expected instanceof CarbonInterval) {
            $expected->equalTo($actual) or $this->fail("Failed asserting that two intervals are equal.");
            return;
        }

        if ($actual instanceof CarbonInterval) {
            $actual->equalTo($expected) or $this->fail("Failed asserting that two intervals are equal.");
            return;
        }

        $this->assertEquals($expected, $actual);
    }

    protected function createReplayer(WorkflowClientInterface $client, string ...$workflows): WorkflowReplayer|CoreWorkflowReplayer
    {
        if (\getenv('TEMPORAL_WORKER_TRANSPORT') !== 'core') {
            return new WorkflowReplayer();
        }

        $factory = CoreWorkerFactory::create();
        $factory->newWorker()->registerWorkflowTypes(...$workflows);

        return new CoreWorkflowReplayer($factory, $client);
    }
}
