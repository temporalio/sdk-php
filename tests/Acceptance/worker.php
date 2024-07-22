<?php

declare(strict_types=1);

use Temporal\Tests\Acceptance\App\Input\Command;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\RuntimeBuilder;
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
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;

chdir(__DIR__ . '/../..');
require './vendor/autoload.php';
RuntimeBuilder::init();

/** @var array<non-empty-string, WorkerInterface> $run */
$workers = [];

try {
    // Load runtime options
    $command = Command::fromCommandLine($argv);
    $runtime = RuntimeBuilder::createState($command, \getcwd(), __DIR__ . '/Harness');
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

    $factory = WorkerFactory::create(converter: $converter);
    $getWorker = static function (string $taskQueue) use (&$workers, $factory): WorkerInterface {
        return $workers[$taskQueue] ??= $factory->newWorker(
            $taskQueue,
            WorkerOptions::new()->withMaxConcurrentActivityExecutionSize(10)
        );
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
        fn (#[Proxy] ContainerInterface $c): StorageInterface => $c->get(Factory::class)->select('harness'),
    );

    // Register Workflows
    foreach ($runtime->workflows() as $feature => $workflow) {
        $getWorker($feature->taskQueue)->registerWorkflowTypes($workflow);
    }

    // Register Activities
    foreach ($runtime->activities() as $feature => $activity) {
        $getWorker($feature->taskQueue)->registerActivityImplementations($container->make($activity));
    }

    $factory->run();
} catch (\Throwable $e) {
    \td($e);
}
