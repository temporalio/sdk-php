<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
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
use Temporal\Plugin\PluginRegistry;
use Temporal\Testing\Command;
use Temporal\Testing\Transcript\TranscriptStore;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Testing\Transcript\TranscriptPlugin;
use Temporal\Tests\Acceptance\App\Runtime\FatalHandler;
use Temporal\Tests\Acceptance\App\Runtime\Feature;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\RuntimeBuilder;
use Temporal\Worker\Logger\StderrLogger;
use Temporal\Tests\Acceptance\App\Transport\RecordingHost;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\WorkerFactory;

\chdir(__DIR__ . '/../..');
require './vendor/autoload.php';

$logger = new StderrLogger();
$workerTranscript = TranscriptStore::create(stderr: $logger)
    ->createWriter(TranscriptStore::currentRunIdOrOrphan(), 'worker');
FatalHandler::register($workerTranscript, $logger);

RuntimeBuilder::init();
StackRenderer::addIgnoredPath(__FILE__);


/** @var list<class-string> $allowedTestClasses */
$allowedTestClasses = [];
foreach ($argv as $arg) {
    if (\str_starts_with($arg, 'test-class=')) {
        $allowedTestClasses[] = \substr($arg, 11);
    }
}
if ($allowedTestClasses === []) {
    $logger->info('[selection] no test-class= args, registering all features');
} else {
    $logger->info('[selection] loaded test classes from CLI args', ['count' => \count($allowedTestClasses)]);
}

/** @var array<non-empty-string, WorkerInterface> $run */
$workers = [];

try {
    $command = Command::fromCommandLine($argv);
    $runtime = RuntimeBuilder::createState(
        $command,
        \getcwd(),
        [
            'Temporal\Tests\Acceptance\Harness' => __DIR__ . '/Harness',
            'Temporal\Tests\Acceptance\Extra' => __DIR__ . '/Extra',
        ],
        workers: (int) (\getenv('ACTIVITY_WORKERS') ?: 2),
        allowedTestClasses: $allowedTestClasses,
    );
    $run = $runtime->command;
    $container = new Spiral\Core\Container();
    $container->bindSingleton(TranscriptWriter::class, $workerTranscript);
    $container->bindSingleton(LoggerInterface::class, $logger);

    $converters = [
        new NullConverter(),
        new BinaryConverter(),
        new ProtoJsonConverter(),
        new ProtoConverter(),
        new JsonConverter(),
    ];
    foreach ($runtime->converters() as $feature => $converter) {
        \array_unshift($converters, $container->get($converter));
    }
    $converter = new DataConverter(...$converters);
    $container->bindSingleton(DataConverter::class, $converter);

    $plugins = [new TranscriptPlugin($workerTranscript)];
    $container->bindSingleton(
        WorkerFactoryInterface::class,
        WorkerFactory::create(
            converter: $converter,
            pluginRegistry: new PluginRegistry($plugins),
        )
    );

    $workerFactory = $container->get(\Temporal\Tests\Acceptance\App\Feature\WorkerFactory::class);
    $getWorker = static function (Feature $feature) use (&$workers, $workerFactory): WorkerInterface {
        return $workers[$feature->taskQueue] ??= $workerFactory->createWorker($feature);
    };

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

    $container->bindSingleton(State::class, $runtime);
    $container->bindSingleton(LoggerInterface::class, $logger);
    $container->bindSingleton(ServiceClientInterface::class, $serviceClient);
    $container->bindSingleton(WorkflowClientInterface::class, $workflowClient);
    $container->bindSingleton(ScheduleClientInterface::class, $scheduleClient);
    $container->bindSingleton(RPCInterface::class, RPC::create(\getenv('ROADRUNNER_ADDRESS') ?: 'tcp://127.0.0.1:6001'));
    $container->bind(
        StorageInterface::class,
        static fn(#[Proxy] ContainerInterface $c): StorageInterface => $c->get(Factory::class)->select('harness'),
    );

    foreach ($runtime->workflows() as $feature => $workflow) {
        $getWorker($feature)->registerWorkflowTypes($workflow);
    }

    foreach ($runtime->activities() as $feature => $activity) {
        $getWorker($feature)->registerActivityImplementations($container->make($activity));
    }

    $host = new RecordingHost(RoadRunner::create(), $workerTranscript);
    $container->get(WorkerFactoryInterface::class)->run($host);
} catch (\Throwable $e) {
    $workerTranscript->writeFatal($e);
    $workerTranscript->flush();
    $logger->critical('fatal', [
        'class' => $e::class,
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(1);
}
