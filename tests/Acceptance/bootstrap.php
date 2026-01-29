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
use Temporal\Client\WorkflowStubInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Testing\Command;
use Temporal\Testing\Environment;
use Temporal\Tests\Acceptance\App\Feature\WorkflowStubInjector;
use Temporal\Tests\Acceptance\App\Runtime\ContainerFacade;
use Temporal\Tests\Acceptance\App\Runtime\RRStarter;
use Temporal\Tests\Acceptance\App\Runtime\State;
use Temporal\Tests\Acceptance\App\Runtime\TemporalStarter;
use Temporal\Tests\Acceptance\App\RuntimeBuilder;
use Temporal\Tests\Acceptance\App\Support;

\chdir(__DIR__ . '/../..');
require './vendor/autoload.php';

RuntimeBuilder::init();

$command = Command::fromEnv();
$runtime = RuntimeBuilder::createEmpty($command, \getcwd(), [
    'Temporal\Tests\Acceptance\Harness' => __DIR__ . '/Harness',
    'Temporal\Tests\Acceptance\Extra' => __DIR__ . '/Extra',
]);

# Run RoadRunner and Temporal
$environment = Environment::create($command);
$temporalRunner = new TemporalStarter($environment);
$rrRunner = new RRStarter($runtime, $environment);
$temporalRunner->start();
$rrRunner->start();

# Prepare and run checks

# Prepare services to be injected

$serviceClient = $runtime->command->tlsKey === null && $runtime->command->tlsCert === null
    ? ServiceClient::create($runtime->address)
    : ServiceClient::createSSL(
        $runtime->address,
        clientKey: $runtime->command->tlsKey,
        clientPem: $runtime->command->tlsCert,
    );
echo "Connecting to Temporal service at {$runtime->address}... ";
try {
    $serviceClient->getConnection()->connect(5);
    echo "\e[1;32mOK\e[0m\n";
} catch (Throwable $e) {
    echo "\e[1;31mFAILED\e[0m\n";
    Support::echoException($e);
    return;
}

// TODO if authKey is set
// $serviceClient->withAuthKey($authKey)

$converter = DataConverter::createDefault();

$workflowClient = WorkflowClient::create(
    serviceClient: $serviceClient,
    options: (new ClientOptions())->withNamespace($runtime->namespace),
    converter: $converter,
)->withTimeout(5);

$scheduleClient = ScheduleClient::create(
    serviceClient: $serviceClient,
    options: (new ClientOptions())->withNamespace($runtime->namespace),
    converter: $converter,
)->withTimeout(5);

ContainerFacade::$container = $container = new Spiral\Core\Container();
$container->bindSingleton(State::class, $runtime);
$container->bindSingleton(RRStarter::class, $rrRunner);
$container->bindSingleton(TemporalStarter::class, $temporalRunner);
$container->bindSingleton(Environment::class, $environment);
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
