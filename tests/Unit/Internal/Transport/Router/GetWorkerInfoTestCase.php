<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Internal\Transport\Router;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use React\Promise\Deferred;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Internal\Declaration\Prototype\NexusServicePrototype;
use Temporal\Internal\Marshaller\MarshallerInterface;
use Temporal\Internal\Repository\ArrayRepository;
use Temporal\Internal\Transport\Router\GetWorkerInfo as GetWorkerInfoRoute;
use Temporal\Plugin\PluginRegistry;
use Temporal\Tests\TestCase;
use Temporal\Worker\ServiceCredentials;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

#[CoversClass(GetWorkerInfoRoute::class)]
final class GetWorkerInfoTestCase extends TestCase
{
    public function testNexusServicesKeyIsPascalCase(): void
    {
        $payload = $this->dispatch(['greet']);

        // Wire contract with rrtemporal: WorkerInfo.NexusServices uses the same
        // PascalCase convention as every other top-level field. Go's JSON
        // unmarshal is case-insensitive, so old camelCase `nexusServices`
        // would also parse — but emitting PascalCase keeps the handshake
        // self-consistent and matches the Go struct tag.
        self::assertArrayHasKey('NexusServices', $payload);
        self::assertArrayNotHasKey('nexusServices', $payload);
    }

    public function testNexusServicesShape(): void
    {
        $payload = $this->dispatch(['greet', 'farewell']);

        self::assertSame(
            [
                ['name' => 'GreetingService', 'operations' => ['greet', 'farewell']],
            ],
            $payload['NexusServices'],
        );
    }

    public function testFlagsDoNotAdvertiseMethodCancel(): void
    {
        $payload = $this->dispatch([]);

        // `nexus_method_cancel` was a redundant capability signal — removed.
        // rrtemporal now infers method-cancel support from NexusServices presence.
        $flags = (array) $payload['Flags'];
        self::assertArrayNotHasKey('nexus_method_cancel', $flags);
    }

    public function testFlagsRetainApiKey(): void
    {
        $payload = $this->dispatch([]);

        $flags = (array) $payload['Flags'];
        self::assertArrayHasKey('ApiKey', $flags);
        self::assertSame('', $flags['ApiKey']);
    }

    /**
     * @param list<string> $operationNames Wire keys for the single registered Nexus service.
     * @return array<string, mixed> The first worker entry from the resolved payload.
     */
    private function dispatch(array $operationNames): array
    {
        $worker = $this->createMock(WorkerInterface::class);
        $worker->method('getID')->willReturn('test-queue');
        $worker->method('getOptions')->willReturn(WorkerOptions::new());
        $worker->method('getWorkflows')->willReturn([]);
        $worker->method('getActivities')->willReturn([]);
        $worker->method('getNexusServices')->willReturn([
            new NexusServicePrototype(
                'GreetingService',
                \array_fill_keys($operationNames, $this->stubOperationPrototype()),
                new \ReflectionClass(\stdClass::class),
            ),
        ]);

        $marshaller = $this->createMock(MarshallerInterface::class);
        $marshaller->method('marshal')->willReturn([]);

        $route = new GetWorkerInfoRoute(
            new ArrayRepository([$worker]),
            $marshaller,
            ServiceCredentials::create(),
            new PluginRegistry(),
        );

        $deferred = new Deferred();
        $request = new ServerRequest('GetWorkerInfo', new TickInfo(new DateTimeImmutable()));
        $route->handle($request, [], $deferred);

        $resolved = null;
        $deferred->promise()->then(static function (mixed $value) use (&$resolved): void {
            $resolved = $value;
        });

        self::assertInstanceOf(ValuesInterface::class, $resolved);
        $values = $resolved->getValues();
        self::assertCount(1, $values, 'one worker entry expected');
        return $values[0];
    }

    /**
     * The route only reads operation keys via `array_keys()`, so the value
     * type is irrelevant for the wire-shape assertion.
     */
    private function stubOperationPrototype(): mixed
    {
        return null;
    }
}
