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
$getClasses = static function (string $dir): iterable {
    $files = glob($dir . '/*.php');

    foreach ($files as $file) {
        yield substr(basename($file), 0, -4);
    }
};

$factory = WorkerFactory::create();

// Collect interceptors
$interceptors = [];
foreach ($getClasses(__DIR__ . '/Fixtures/src/Interceptor') as $name) {
    $class = 'Temporal\\Tests\\Interceptor\\' . $name;

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
foreach ($getClasses(__DIR__ . '/Fixtures/src/Workflow') as $name) {
    $class = 'Temporal\\Tests\\Workflow\\' . $name;

    if (class_exists($class) && !interface_exists($class)) {
        $worker->registerWorkflowTypes($class);
    }
}

// register all activity
foreach ($getClasses(__DIR__ . '/Fixtures/src/Activity') as $name) {
    $class = 'Temporal\\Tests\\Activity\\' . $name;
    if (class_exists($class) && !interface_exists($class)) {
        $worker->registerActivityImplementations(new $class());
    }
}

$factory->run();
