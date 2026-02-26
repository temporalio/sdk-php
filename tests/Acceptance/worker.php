<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\KeyValue\StorageInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\Client\ScheduleClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoConverter;
use Temporal\DataConverter\ProtoJsonConverter;
use Temporal\Internal\Support\StackRenderer;
use Temporal\Testing\Command;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\RuntimeBuilder;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\WorkerFactory;

\chdir(__DIR__ . '/../..');
require './vendor/autoload.php';
RuntimeBuilder::init();
StackRenderer::addIgnoredPath(__FILE__);

/** @var array<non-empty-string, WorkerInterface> $run */
$workers = [];

try {
    // Load runtime options
    $command = Command::fromCommandLine($argv);
    $runtime = RuntimeBuilder::createState($command, \getcwd(), [
        'Temporal\Tests\Acceptance\Harness' => __DIR__ . '/Harness',
        'Temporal\Tests\Acceptance\Extra' => __DIR__ . '/Extra',
    ], workers: (int) (\getenv('ACTIVITY_WORKERS') ?: 2));
    $run = $runtime->command;
    // Init container
    $container = new Spiral\Core\Container();

    $converters = [
        new NullConverter(),
        new BinaryConverter(),
        new ProtoJsonConverter(),
        new ProtoConverter(),
        new JsonConverter(),
    ];
    // Collect converters from all features
    foreach ($runtime->converters() as $feature => $converter) {
        \array_unshift($converters, $container->get($converter));
    }
    $converter = new DataConverter(...$converters);
    $container->bindSingleton(DataConverter::class, $converter);
    $container->bindSingleton(WorkerFactoryInterface::class, WorkerFactory::create(converter: $converter));

    $workerFactory =  $container->get(\Temporal\Tests\Acceptance\App\Feature\WorkerFactory::class);
    $getWorker = static function (Feature $feature, string $taskQueue) use (&$workers, $workerFactory): WorkerInterface {
        return $workers[$taskQueue] ??= $workerFactory->createWorker($feature, $taskQueue);
    };

    // Create client services
    $serviceClient = $runtime->command->tlsKey === null && $runtime->command->tlsCert === null
        ? ServiceClient::create($runtime->address)
        : ServiceClient::createSSL(
            $runtime->address,
            clientKey: $runtime->command->tlsKey,
            clientPem: $runtime->command->tlsCert,
        );
    $options = (new ClientOptions())->withNamespace($runtime->namespace);
    $workflowClient = WorkflowClient::create(serviceClient: $serviceClient, options: $options, converter: $converter);
    $scheduleClient = ScheduleClient::create(serviceClient: $serviceClient, options: $options, converter: $converter);

    // Bind services
    $container->bindSingleton(State::class, $runtime);
    $container->bindSingleton(ServiceClientInterface::class, $serviceClient);
    $container->bindSingleton(WorkflowClientInterface::class, $workflowClient);
    $container->bindSingleton(ScheduleClientInterface::class, $scheduleClient);
    $container->bindSingleton(RPCInterface::class, RPC::create('tcp://127.0.0.1:6001'));
    $container->bind(
        StorageInterface::class,
        static fn(#[Proxy] ContainerInterface $c): StorageInterface => $c->get(Factory::class)->select('harness'),
    );

    // Register Workflows
    $workerFactory = $container->get(WorkerFactoryInterface::class);
    foreach ($runtime->workflows() as $feature => $workflow) {
        $getWorker($feature, $feature->taskQueue)->registerWorkflowTypes($workflow);

        // Also register workflows on custom task queues declared via #[TaskQueue] attribute
        $reflection = new \ReflectionClass($workflow);
        foreach ($reflection->getAttributes(\Temporal\Workflow\Attribute\TaskQueue::class) as $attr) {
            $queue = $attr->newInstance()->name;
            $getWorker($feature, $queue)->registerWorkflowTypes($workflow);
        }
    }

    // Register Activities
    foreach ($runtime->activities() as $feature => $activity) {
        $getWorker($feature, $feature->taskQueue)->registerActivityImplementations($container->make($activity));

        // Also register activities on custom task queues declared via #[TaskQueue] attribute
        $reflection = new \ReflectionClass($activity);
        foreach ($reflection->getAttributes(\Temporal\Activity\Attribute\TaskQueue::class) as $attr) {
            $queue = $attr->newInstance()->name;
            $getWorker($feature, $queue)->registerActivityImplementations($container->make($activity));
        }
    }

    $workerFactory->run();
} catch (\Throwable $e) {
    td($e);
}
