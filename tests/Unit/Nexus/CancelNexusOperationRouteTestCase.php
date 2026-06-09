<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Nexus;

use PHPUnit\Framework\Attributes\CoversClass;
use React\Promise\Deferred;
use Spiral\Attributes\AttributeReader;
use Temporal\DataConverter\DataConverter;
use Temporal\Internal\Declaration\Prototype\NexusServiceCollection;
use Temporal\Internal\Declaration\Reader\NexusServiceReader;
use Temporal\Internal\Marshaller\Mapper\AttributeMapperFactory;
use Temporal\Internal\Marshaller\Marshaller;
use Temporal\Internal\Nexus\NexusTaskHandler;
use Temporal\Internal\Transport\Router\CancelNexusOperation;
use Temporal\Nexus\Attribute\AsyncOperation;
use Temporal\Nexus\Attribute\OperationCancel;
use Temporal\Nexus\Attribute\Service;
use Temporal\Nexus\Nexus;
use Temporal\Nexus\OperationInfo;
use Temporal\Nexus\OperationState;
use Temporal\Tests\Unit\AbstractUnit;
use Temporal\Worker\Environment\Environment;
use Temporal\Worker\Environment\EnvironmentInterface;
use Temporal\Worker\Transport\Command\Server\ServerRequest;
use Temporal\Worker\Transport\Command\Server\TickInfo;

#[Service(name: 'RouteHeaderService')]
interface RouteHeaderService
{
    #[AsyncOperation(output: 'string')]
    public function op(string $input): OperationInfo;
}

class RouteHeaderServiceImpl implements RouteHeaderService
{
    /** @var array<string, string> */
    public static array $capturedCancelHeaders = [];

    public function op(string $input): OperationInfo
    {
        return new OperationInfo('tok', OperationState::Running);
    }

    #[OperationCancel(operation: 'op')]
    public function cancel(string $token): void
    {
        self::$capturedCancelHeaders = Nexus::getCurrentOperationContext()->headers->all();
    }
}

/**
 * Unit tests for the `CancelNexusOperation` router — the `options['headers']`
 * map (sent by RoadRunner on the cancel command) must surface on the handler's
 * OperationContext, symmetric with the start path.
 *
 * @group unit
 * @group nexus
 */
#[CoversClass(CancelNexusOperation::class)]
final class CancelNexusOperationRouteTestCase extends AbstractUnit
{
    private EnvironmentInterface $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new Environment();
        RouteHeaderServiceImpl::$capturedCancelHeaders = [];
    }

    public function testRouteName(): void
    {
        $route = new CancelNexusOperation($this->buildHandler(), $this->buildMarshaller());

        self::assertSame('CancelNexusOperation', $route->getName());
    }

    public function testForwardsOptionHeadersToHandlerContext(): void
    {
        $route = new CancelNexusOperation($this->buildHandler(), $this->buildMarshaller());
        $request = $this->makeRequest([
            'service' => 'RouteHeaderService',
            'operation' => 'op',
            'operationToken' => 'tok',
            'headers' => [
                'X-Nexus-Trace-Id' => 'trace-1',
                'Authorization' => 'Bearer xyz',
            ],
        ]);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        $this->assertResolved($deferred);
        // OperationContext lowercases header keys on construction.
        self::assertSame('trace-1', RouteHeaderServiceImpl::$capturedCancelHeaders['x-nexus-trace-id'] ?? null);
        self::assertSame('Bearer xyz', RouteHeaderServiceImpl::$capturedCancelHeaders['authorization'] ?? null);
    }

    public function testMissingHeadersResolvesWithEmptyContextHeaders(): void
    {
        $route = new CancelNexusOperation($this->buildHandler(), $this->buildMarshaller());
        $request = $this->makeRequest([
            'service' => 'RouteHeaderService',
            'operation' => 'op',
            'operationToken' => 'tok',
        ]);

        $deferred = new Deferred();
        $route->handle($request, [], $deferred);

        $this->assertResolved($deferred);
        self::assertSame([], RouteHeaderServiceImpl::$capturedCancelHeaders);
    }

    private function buildHandler(): NexusTaskHandler
    {
        $reader = new NexusServiceReader(new AttributeReader());
        $collection = new NexusServiceCollection();
        $prototype = $reader->fromClass(RouteHeaderServiceImpl::class)->withInstance(new RouteHeaderServiceImpl());
        $collection->add($prototype, false);

        return new NexusTaskHandler($collection, DataConverter::createDefault(), $this->env);
    }

    private function buildMarshaller(): Marshaller
    {
        return new Marshaller(new AttributeMapperFactory(new AttributeReader()));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function makeRequest(array $options): ServerRequest
    {
        return new ServerRequest(
            name: 'CancelNexusOperation',
            info: new TickInfo(new \DateTimeImmutable()),
            options: $options,
        );
    }

    private function assertResolved(Deferred $deferred): void
    {
        $resolved = false;
        $error = null;
        $deferred->promise()->then(
            function ($value) use (&$resolved): void {
                $resolved = true;
            },
            function (\Throwable $e) use (&$error): void {
                $error = $e;
            },
        );

        if ($error !== null) {
            throw $error;
        }
        self::assertTrue($resolved, 'promise should resolve');
    }
}
