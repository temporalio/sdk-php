<?php

declare(strict_types=1);

use Temporal\Interceptor\Interceptor;
use Temporal\Testing\WorkerFactory;
use Temporal\Tests\Fixtures\InterceptorProvider;

require __DIR__ . '/../vendor/autoload.php';

/**
 * @param string $dir
 * @return array<string>
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

// Collect interceptors
$interceptors = [];
foreach ($getClasses(__DIR__ . '/Fixtures/src/Interceptor', 'Temporal\\Tests\\Interceptor\\') as $class) {
    if (\class_exists($class) && !\interface_exists($class) && \is_a($class, Interceptor::class, true)) {
        $interceptors[] = $class;
    }
}

$worker = $factory->newWorker(
    'default',
    \Temporal\Worker\WorkerOptions::new()
        ->withMaxConcurrentWorkflowTaskPollers(5),
    interceptorProvider: new InterceptorProvider($interceptors),
);

// register all workflows
foreach ($getClasses(__DIR__ . '/Fixtures/src/Workflow', 'Temporal\\Tests\\Workflow\\') as $class) {
    if (class_exists($class) && !interface_exists($class)) {
        $worker->registerWorkflowTypes($class);
    }
}

// register all activity
foreach ($getClasses(__DIR__ . '/Fixtures/src/Activity', 'Temporal\\Tests\\Activity\\') as $class) {
    if (class_exists($class) && !interface_exists($class)) {
        $worker->registerActivityImplementations(new $class());
    }
}

$factory->run();
