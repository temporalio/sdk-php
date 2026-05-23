<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Plugin\AbstractPlugin;
use Temporal\Plugin\PluginInterface;
use Temporal\Plugin\PluginRegistry;
use Temporal\Plugin\WorkerFactoryPluginContext;
use Temporal\Plugin\WorkerPluginContext;
use Temporal\Plugin\WorkerPluginInterface;
use Temporal\Plugin\WorkerPluginTrait;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\Worker\Transport\HostConnectionInterface;
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

            public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
            {
                $this->called = true;
                $next($context);
            }
        };

        new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
            {
                $context->setDataConverter($this->converter);
                $next($context);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->called = true;
                $this->receivedTaskQueue = $context->getTaskQueue();
                $next($context);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $context->setWorkerOptions($this->opts);
                $next($context);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function initializeWorker(WorkerInterface $worker, callable $next): void
            {
                $this->receivedWorker = $worker;
                $next($worker);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function initializeWorker(WorkerInterface $worker, callable $next): void
            {
                $this->receivedTaskQueue = $worker->getID();
                $next($worker);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
            {
                $this->order[] = 'configureWorkerFactory';
                $next($context);
            }

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->order[] = 'configureWorker';
                $next($context);
            }

            public function initializeWorker(WorkerInterface $worker, callable $next): void
            {
                $this->order[] = 'initializeWorker';
                $next($worker);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->order[] = 'first';
                $next($context);
            }
        };

        $plugin2 = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->order[] = 'second';
                $next($context);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin1, $plugin2]),
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

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->taskQueues[] = $context->getTaskQueue();
                $next($context);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
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
            pluginRegistry: new PluginRegistry([$plugin1, $plugin2]),
        );

        $plugins = $factory->getPluginRegistry()->getPlugins(PluginInterface::class);
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
            pluginRegistry: new PluginRegistry([$plugin1, $plugin2]),
        );
    }

    public function testRunHookIsCalled(): void
    {
        $called = false;
        $plugin = new class($called) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private bool &$called) {}

            public function getName(): string
            {
                return 'test.run';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->called = true;
                return $next($factory);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
        );

        $factory->run($this->mockHost());

        self::assertTrue($called);
    }

    public function testRunHookReceivesFactoryInstance(): void
    {
        $receivedFactory = null;

        $plugin = new class($receivedFactory) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private ?WorkerFactoryInterface &$receivedFactory) {}

            public function getName(): string
            {
                return 'test.factory-ref';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->receivedFactory = $factory;
                return $next($factory);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
        );

        $factory->run($this->mockHost());

        self::assertSame($factory, $receivedFactory);
    }

    public function testRunHookChainOrder(): void
    {
        $order = [];

        $plugin1 = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.first';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->order[] = 'first:before';
                try {
                    return $next($factory);
                } finally {
                    $this->order[] = 'first:after';
                }
            }
        };

        $plugin2 = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.second';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->order[] = 'second:before';
                try {
                    return $next($factory);
                } finally {
                    $this->order[] = 'second:after';
                }
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin1, $plugin2]),
        );

        $factory->run($this->mockHost());

        // First plugin is outermost: before in forward order, after in reverse (LIFO)
        self::assertSame([
            'first:before',
            'second:before',
            'second:after',
            'first:after',
        ], $order);
    }

    public function testRunHookCanWrapWithTryFinally(): void
    {
        $cleanupCalled = false;

        $plugin = new class($cleanupCalled) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private bool &$cleanupCalled) {}

            public function getName(): string
            {
                return 'test.cleanup';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                try {
                    return $next($factory);
                } finally {
                    $this->cleanupCalled = true;
                }
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
        );

        $factory->run($this->mockHost());

        self::assertTrue($cleanupCalled);
    }

    public function testRunHookCanSkipNext(): void
    {
        $innerCalled = false;

        $outerPlugin = new class implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function getName(): string
            {
                return 'test.outer';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                // Intentionally skip $next()
                return 42;
            }
        };

        $innerPlugin = new class($innerCalled) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private bool &$innerCalled) {}

            public function getName(): string
            {
                return 'test.inner';
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->innerCalled = true;
                return $next($factory);
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$outerPlugin, $innerPlugin]),
        );

        $result = $factory->run($this->mockHost());

        self::assertSame(42, $result);
        self::assertFalse($innerCalled);
    }

    public function testRunHookFullLifecycleOrder(): void
    {
        $order = [];

        $plugin = new class($order) implements WorkerPluginInterface {
            use WorkerPluginTrait;

            public function __construct(private array &$order) {}

            public function getName(): string
            {
                return 'test.lifecycle';
            }

            public function configureWorkerFactory(WorkerFactoryPluginContext $context, callable $next): void
            {
                $this->order[] = 'configureWorkerFactory';
                $next($context);
            }

            public function configureWorker(WorkerPluginContext $context, callable $next): void
            {
                $this->order[] = 'configureWorker';
                $next($context);
            }

            public function initializeWorker(WorkerInterface $worker, callable $next): void
            {
                $this->order[] = 'initializeWorker';
                $next($worker);
            }

            public function run(WorkerFactoryInterface $factory, callable $next): int
            {
                $this->order[] = 'run:before';
                try {
                    return $next($factory);
                } finally {
                    $this->order[] = 'run:after';
                }
            }
        };

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
        );
        $factory->newWorker();
        $factory->run($this->mockHost());

        self::assertSame([
            'configureWorkerFactory',
            'configureWorker',
            'initializeWorker',
            'run:before',
            'run:after',
        ], $order);
    }

    public function testRunHookReturnsValueFromRunLoop(): void
    {
        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
        );

        $result = $factory->run($this->mockHost());

        self::assertSame(0, $result);
    }

    public function testDefaultTraitRunPassesThrough(): void
    {
        $plugin = new class('test.noop') extends AbstractPlugin {};

        $factory = new WorkerFactory(
            DataConverter::createDefault(),
            $this->mockRpc(),
            pluginRegistry: new PluginRegistry([$plugin]),
        );

        $result = $factory->run($this->mockHost());

        self::assertSame(0, $result);
    }

    private function mockRpc(): RPCConnectionInterface
    {
        return $this->createMock(RPCConnectionInterface::class);
    }

    /**
     * Create a mock host that immediately returns null (empty run loop).
     */
    private function mockHost(): HostConnectionInterface
    {
        $host = $this->createMock(HostConnectionInterface::class);
        $host->method('waitBatch')->willReturn(null);

        return $host;
    }
}
