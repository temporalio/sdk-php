<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Worker\WorkerOptions;

/**
 * @group unit
 * @group plugin
 */
class WorkerPluginContextTestCase extends TestCase
{
    // --- WorkerFactoryPluginContext ---

    public function testFactoryContextBuilderPattern(): void
    {
        $context = new WorkerFactoryPluginContext();

        self::assertNull($context->getDataConverter());
    }

    public function testFactoryContextSetters(): void
    {
        $context = new WorkerFactoryPluginContext();
        $converter = $this->createMock(DataConverterInterface::class);

        $result = $context->setDataConverter($converter);

        self::assertSame($context, $result);
        self::assertSame($converter, $context->getDataConverter());
    }

    // --- WorkerPluginContext ---

    public function testWorkerContextBuilderPattern(): void
    {
        $options = WorkerOptions::new();
        $context = new WorkerPluginContext('test-queue', $options);

        self::assertSame('test-queue', $context->getTaskQueue());
        self::assertSame($options, $context->getWorkerOptions());
        self::assertNull($context->getExceptionInterceptor());
        self::assertSame([], $context->getInterceptors());
    }

    public function testWorkerContextSetWorkerOptions(): void
    {
        $context = new WorkerPluginContext('test-queue', WorkerOptions::new());
        $newOptions = WorkerOptions::new();

        $result = $context->setWorkerOptions($newOptions);

        self::assertSame($context, $result);
        self::assertSame($newOptions, $context->getWorkerOptions());
    }
}
