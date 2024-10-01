<?php

declare(strict_types=1);

use Temporal\Testing\WorkerFactory;
use Temporal\Tests\Fixtures\PipelineProvider;
use Temporal\Tests\Interceptor\HeaderChanger;
use Temporal\Tests\Interceptor\InterceptorCallsCounter;
use Temporal\Worker\FeatureFlags;
use Temporal\Worker\WorkerInterface;

require __DIR__ . '/../../vendor/autoload.php';
chdir(__DIR__ . '/../../');

// Default feature flags
FeatureFlags::$warnOnWorkflowUnfinishedHandlers = false;

/**
 * @param non-empty-string $dir
 * @return array<class-string>
 */
$getClasses = static function (string $dir, string $namespace): iterable {
    $dir = realpath($dir);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    /** @var SplFileInfo $_ */
    foreach ($files as $path => $_) {
        if (!\is_file($path) || !\str_ends_with($path, '.php')) {
            continue;
        }

        yield \str_replace(['/', '\\\\'], '\\', $namespace . \substr($path, \strlen($dir), -4));
    }
};

$factory = WorkerFactory::create();

$interceptors = [
    InterceptorCallsCounter::class,
    HeaderChanger::class,
];

$workers = [
    'default' => $factory->newWorker(
        'default',
        \Temporal\Worker\WorkerOptions::new()
            ->withMaxConcurrentWorkflowTaskPollers(5),
        interceptorProvider: new PipelineProvider($interceptors),
    ),
    'FooBar' => $factory->newWorker(
        'FooBar',
        \Temporal\Worker\WorkerOptions::new()
            ->withMaxConcurrentWorkflowTaskPollers(5),
    ),
];

// register all workflows
foreach ($getClasses(__DIR__ . '/../Fixtures/src/Workflow', 'Temporal\\Tests\\Workflow\\') as $class) {
    if (\class_exists($class) && !\interface_exists($class)) {
        $wfRef = new \ReflectionClass($class);
        if ($wfRef->isAbstract()) {
            continue;
        }

        \array_walk(
            $workers,
            static fn (WorkerInterface $worker) => $worker->registerWorkflowTypes($class),
        );
    }
}

// register all activity
foreach ($getClasses(__DIR__ . '/../Fixtures/src/Activity', 'Temporal\\Tests\\Activity\\') as $class) {
    if (class_exists($class) && !\interface_exists($class)) {
        \array_walk(
            $workers,
            static fn (WorkerInterface $worker) => $worker->registerActivityImplementations(new $class()),
        );
    }
}

$factory->run();
