<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Temporal\Api\Common\V1\Payload;
use Temporal\Api\Common\V1\Payloads;
use Temporal\Api\Workflowservice\V1\QueryWorkflowResponse;
use Temporal\Api\Workflowservice\V1\StartWorkflowExecutionRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Common\PayloadLimitOptions;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\Internal\ExternalStorage\ExternalStorageRunner;
use Temporal\Internal\ExternalStorage\ReferencePayload;
use Temporal\Tests\Unit\Internal\ExternalStorage\Stub\InMemoryStorageDriver;

final class ExternalStorageTestCase extends TestCase
{
    /** @var list<object> */
    private array $sent = [];

    /** @var list<string> */
    private array $warnings = [];

    public function testRequestIsStoredAndResponseStaysRaw(): void
    {
        $client = $this->createClient()->withExternalStorage($this->runner(new InMemoryStorageDriver()));

        $response = $client->testCall($this->request(2000));

        self::assertSame(
            ReferencePayload::MESSAGE_TYPE,
            $this->sent[0]->getInput()->getPayloads()[0]->getMetadata()[EncodingKeys::METADATA_MESSAGE_TYPE],
        );
        self::assertInstanceOf(QueryWorkflowResponse::class, $response);
        self::assertTrue(ReferencePayload::isReference($response->getQueryResult()->getPayloads()[0]));
    }

    public function testPayloadLimitsMeasureTheStoredRequest(): void
    {
        $client = $this->createClient()
            ->withPayloadLimits(new PayloadLimitOptions(1024, 1024), $this->createLogger())
            ->withExternalStorage($this->runner(new InMemoryStorageDriver()));

        $client->testCall($this->request(2000));

        self::assertSame([], $this->warnings);
    }

    public function testClientWithoutExternalStorageSendsPayloadsInline(): void
    {
        $this->createClient()->testCall($this->request(2000));

        self::assertSame(\str_repeat('x', 2000), $this->sent[0]->getInput()->getPayloads()[0]->getData());
    }

    public function testExternalStorageCanBeRemoved(): void
    {
        $client = $this->createClient()
            ->withExternalStorage($this->runner(new InMemoryStorageDriver()))
            ->withExternalStorage(null);

        $client->testCall($this->request(2000));

        self::assertSame(\str_repeat('x', 2000), $this->sent[0]->getInput()->getPayloads()[0]->getData());
    }

    public function testWorkflowClientAppliesClientOptions(): void
    {
        $driver = new InMemoryStorageDriver();
        $client = new WorkflowClient(
            $this->createClient(),
            (new ClientOptions())->withExternalStorage(new ExternalStorage([$driver], payloadSizeThreshold: 1024)),
        );

        $serviceClient = $client->getServiceClient();
        self::assertInstanceOf(ServiceClient::class, $serviceClient);
        $serviceClient->testCall($this->request(2000));

        self::assertCount(1, $driver->objects);
    }

    private function runner(InMemoryStorageDriver $driver): ExternalStorageRunner
    {
        return new ExternalStorageRunner(
            new ExternalStorage([$driver], payloadSizeThreshold: 1024),
            'default',
            new NullLogger(),
        );
    }

    private function request(int $size): StartWorkflowExecutionRequest
    {
        return (new StartWorkflowExecutionRequest())->setInput(
            new Payloads(['payloads' => [(new Payload())->setData(\str_repeat('x', $size))]]),
        );
    }

    private function createLogger(): AbstractLogger
    {
        return new class($this->warnings) extends AbstractLogger {
            public function __construct(private array &$warnings) {}

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }
        };
    }

    private function createClient(): ServiceClient
    {
        $stub = fn() => new class($this->sent) extends WorkflowServiceClient {
            public function __construct(private array &$sent) {}

            public function getConnectivityState($try_to_connect = false): int
            {
                return ConnectionState::Ready->value;
            }

            public function testCall(object $arg, array $metadata = [], array $options = []): object
            {
                $this->sent[] = $arg;
                $result = new QueryWorkflowResponse();
                $result->mergeFromString((new QueryWorkflowResponse())->setQueryResult($arg->getInput())->serializeToString());

                return new class($result) {
                    public function __construct(private object $result) {}

                    public function wait(): array
                    {
                        return [$this->result, (object) ['code' => 0, 'details' => '']];
                    }
                };
            }

            public function close(): void {}
        };

        return new class($stub) extends ServiceClient {
            public function testCall(object $request): mixed
            {
                return $this->invoke('testCall', $request, null);
            }
        };
    }
}
