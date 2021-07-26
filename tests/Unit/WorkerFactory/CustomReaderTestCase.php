<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);


namespace Temporal\Tests\Unit\WorkerFactory;

use Temporal\Tests\Unit\Declaration\Fixture\CustomReaderWorkerFactory;
use Temporal\Tests\Unit\Declaration\Fixture\UnannotatedClass;

class CustomReaderTestCase extends WorkerFactoryTestCase
{
    public function testCustomReader()
    {
        $workerFactory = CustomReaderWorkerFactory::create();
        $worker = $workerFactory->newWorker()->registerActivityImplementations(new UnannotatedClass());

        // With default reader we would get no activities.
        $this->assertCount(1, $worker->getActivities());
    }
}
