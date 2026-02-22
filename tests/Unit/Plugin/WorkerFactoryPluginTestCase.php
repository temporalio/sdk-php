<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\WorkerFactory;

/**
 * Acceptance tests for plugin integration with WorkerFactory.
 *
 * @group unit
 * @group plugin
 */
class WorkerFactoryPluginTestCase extends TestCase
{
    public function testConfigureWorkerFactoryIsCalled(): void
    {
        $called = false;
        $plugin = new class($called) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.spy';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context): void
            {
                $this->called = true;
            }
        };

        new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );

        self::assertTrue($called);
    }

    public function testConfigureWorkerFactoryModifiesDataConverter(): void
    {
        $customConverter = $this->createMock(DataConverterInterface::class);

        $plugin = new class($customConverter) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private DataConverterInterface $converter) {}

            public function getName(): string
            {
                return 'test.converter';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context): void
            {
                $context->setDataConverter($this->converter);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );

        self::assertSame($customConverter, $factory->getDataConverter());
    }

    public function testConfigureWorkerIsCalled(): void
    {
        $called = false;
        $receivedTaskQueue = null;

        $plugin = new class($called, $receivedTaskQueue) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(
                private bool &$called,
                private ?string &$receivedTaskQueue,
            ) {}

            public function getName(): string
            {
                return 'test.spy';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->called = true;
                $this->receivedTaskQueue = $context->getTaskQueue();
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );
        $factory->newWorker('my-queue');

        self::assertTrue($called);
        self::assertSame('my-queue', $receivedTaskQueue);
    }

    public function testConfigureWorkerModifiesWorkerOptions(): void
    {
        $customOptions = WorkerOptions::new()->withMaxConcurrentActivityExecutionSize(42);

        $plugin = new class($customOptions) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private WorkerOptions $opts) {}

            public function getName(): string
            {
                return 'test.options';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $context->setWorkerOptions($this->opts);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );
        $worker = $factory->newWorker('test-queue');

        self::assertSame(42, $worker->getOptions()->maxConcurrentActivityExecutionSize);
    }

    public function testInitializeWorkerIsCalled(): void
    {
        $receivedWorker = null;

        $plugin = new class($receivedWorker) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private ?WorkerInterface &$receivedWorker) {}

            public function getName(): string
            {
                return 'test.init';
            }

            public function initializeWorker(WorkerInterface $worker): void
            {
                $this->receivedWorker = $worker;
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );
        $worker = $factory->newWorker('test-queue');

        self::assertSame($worker, $receivedWorker);
    }

    public function testInitializeWorkerReceivesCorrectTaskQueue(): void
    {
        $receivedTaskQueue = null;

        $plugin = new class($receivedTaskQueue) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private ?string &$receivedTaskQueue) {}

            public function getName(): string
            {
                return 'test.tq';
            }

            public function initializeWorker(WorkerInterface $worker): void
            {
                $this->receivedTaskQueue = $worker->getID();
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );
        $factory->newWorker('my-task-queue');

        self::assertSame('my-task-queue', $receivedTaskQueue);
    }

    public function testPluginHookOrder(): void
    {
        $order = [];

        $plugin = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.order';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context): void
            {
                $this->order[] = 'configureWorkerFactory';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'configureWorker';
            }

            public function initializeWorker(WorkerInterface $worker): void
            {
                $this->order[] = 'initializeWorker';
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );

        self::assertSame(['configureWorkerFactory'], $order);

        $factory->newWorker();

        self::assertSame([
            'configureWorkerFactory',
            'configureWorker',
            'initializeWorker',
        ], $order);
    }

    public function testMultiplePluginsCalledInOrder(): void
    {
        $order = [];

        $plugin1 = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.first';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'first';
            }
        };

        $plugin2 = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->order[] = 'second';
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin1, $plugin2],
        );
        $factory->newWorker();

        self::assertSame(['first', 'second'], $order);
    }

    public function testConfigureWorkerCalledPerWorker(): void
    {
        $taskQueues = [];

        $plugin = new class($taskQueues) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$taskQueues) {}

            public function getName(): string
            {
                return 'test.per-worker';
            }

            public function configureWorker(WorkerPluginContext $context): void
            {
                $this->taskQueues[] = $context->getTaskQueue();
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin],
        );
        $factory->newWorker('queue-a');
        $factory->newWorker('queue-b');

        self::assertSame(['queue-a', 'queue-b'], $taskQueues);
    }

    public function testGetWorkerPluginsReturnsRegistered(): void
    {
        $plugin1 = new class('p1') extends AbstractPlugin {};
        $plugin2 = new class('p2') extends AbstractPlugin {};

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin1, $plugin2],
        );

        $plugins = $factory->getWorkerPlugins();
        self::assertCount(2, $plugins);
        self::assertSame($plugin1, $plugins[0]);
        self::assertSame($plugin2, $plugins[1]);
    }

    public function testDuplicatePluginThrowsException(): void
    {
        $plugin1 = new class('dup') extends AbstractPlugin {};
        $plugin2 = new class('dup') extends AbstractPlugin {};

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Duplicate plugin "dup"');

        new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            plugins: [$plugin1, $plugin2],
        );
    }

    private function mockRpc(): RPCConnectionInterface
    {
        return $this->createMock(RPCConnectionInterface::class);
    }
}
