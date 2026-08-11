<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance;

use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber as ExecutionStartedSubscriberInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Container;
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
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Testing\Environment;
use Temporal\Tests\Acceptance\App\Feature\WorkflowStubInjector;
use Temporal\Testing\Transcript\TranscriptStore;
use Temporal\Testing\Transcript\TranscriptWriter;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Tests\Acceptance\App\RuntimeBuilder;
use Temporal\Tests\Acceptance\App\Support;
use Temporal\Worker\Logger\StderrLogger;

final class ExecutionStartedSubscriber implements ExecutionStartedSubscriberInterface
{
    private const NAMESPACE_PREFIX = 'Temporal\\Tests\\Acceptance\\';

    public function notify(ExecutionStarted $event): void
    {
        try {
            $this->boot($event);
        } catch (\Throwable $e) {
            echo $e;
            exit(1);
        }
    }

    private function boot(ExecutionStarted $event): void
    {
        $classNames = [];
        foreach ($event->testSuite()->tests() as $test) {
            if ($test instanceof TestMethod && \str_starts_with($test->className(), self::NAMESPACE_PREFIX)) {
                $classNames[] = $test->className();
            }
        }

        $selectedTestClasses = \array_values(\array_unique($classNames));

        if ($selectedTestClasses === []) {
            return;
        }

        $logger = new StderrLogger();
        $logger->info('[selection] picked test classes after filtering', [
            'count' => \count($selectedTestClasses),
            'classes' => $selectedTestClasses,
        ]);

        RuntimeBuilder::init();

        $environment = Environment::create();
        $state = RuntimeBuilder::createState(
            $environment->command,
            \getcwd(),
            [
                'Temporal\Tests\Acceptance\Harness' => __DIR__ . '/Harness',
                'Temporal\Tests\Acceptance\Extra' => __DIR__ . '/Extra',
            ],
            workers: (int) (\getenv('ACTIVITY_WORKERS') ?: 2),
            allowedTestClasses: $selectedTestClasses,
        );
        $logger->info('[selection] registered test features', [
            'registered' => $state->countFeatures(),
            'requested' => \count($selectedTestClasses),
        ]);

        $container = new Container();
        ContainerFacade::$container = $container;
        $container->bindSingleton(State::class, $state);
        $container->bindSingleton(Environment::class, $environment);
        $container->bindSingleton(LoggerInterface::class, $logger);
        $container->bindSingleton(StderrLogger::class, $logger);

        $runId = TranscriptStore::getOrCreateRunId();
        $logger->info('[transcript] run id', ['run_id' => $runId]);

        $transcriptStore = TranscriptStore::create(stderr: $logger);
        $transcriptStore->pruneOldRuns(keep: 20);
        $testTranscript = $transcriptStore->createWriter($runId, 'test');
        $container->bindSingleton(TranscriptWriter::class, $testTranscript);

        $temporalRunner = new TemporalStarter($environment);
        $rrRunner = new RRStarter($state, $environment);
        $temporalRunner->start();
        $rrRunner->start();

        $serviceClient = $state->command->tlsKey === null && $state->command->tlsCert === null
            ? ServiceClient::create($state->address)
            : ServiceClient::createSSL(
                $state->address,
                clientKey: $state->command->tlsKey,
                clientPem: $state->command->tlsCert,
            );
        echo "Connecting to Temporal service at {$state->address}... ";
        try {
            $serviceClient->getConnection()->connect(5);
            echo "\e[1;32mOK\e[0m\n";
        } catch (\Throwable $e) {
            echo "\e[1;31mFAILED\e[0m\n";
            Support::echoException($e);
            throw $e;
        }

        $converter = DataConverter::createDefault();

        $workflowClient = WorkflowClient::create(
            serviceClient: $serviceClient,
            options: (new ClientOptions())->withNamespace($state->namespace),
            converter: $converter,
        )->withTimeout(5);

        $scheduleClient = ScheduleClient::create(
            serviceClient: $serviceClient,
            options: (new ClientOptions())->withNamespace($state->namespace),
            converter: $converter,
        )->withTimeout(5);

        $container->bindSingleton(RRStarter::class, $rrRunner);
        $container->bindSingleton(TemporalStarter::class, $temporalRunner);
        $container->bindSingleton(ServiceClientInterface::class, $serviceClient);
        $container->bindSingleton(WorkflowClientInterface::class, $workflowClient);
        $container->bindSingleton(ScheduleClientInterface::class, $scheduleClient);
        $container->bindInjector(WorkflowStubInterface::class, WorkflowStubInjector::class);
        $container->bindSingleton(DataConverterInterface::class, $converter);
        $container->bind(RPCInterface::class, static fn() => RPC::create(\getenv('RR_RPC_ADDRESS') ?: 'tcp://127.0.0.1:6001'));
        $container->bind(
            StorageInterface::class,
            static fn(#[Proxy] ContainerInterface $container): StorageInterface => $container->get(Factory::class)->select('harness'),
        );
    }
}
