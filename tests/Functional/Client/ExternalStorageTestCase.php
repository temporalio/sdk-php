<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Psr\Log\NullLogger;
use Temporal\Api\Common\V1\Payload;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\EncodingKeys;
use Temporal\DataConverter\ExternalStorage\ExternalStorage;
use Temporal\Internal\ExternalStorage\ExternalStorageDataConverter;
use Temporal\Internal\ExternalStorage\ExternalStorageRunner;
use Temporal\Internal\ExternalStorage\ReferencePayload;
use Temporal\Testing\TemporalServer;
use Temporal\Tests\Unit\Internal\ExternalStorage\Stub\InMemoryStorageDriver;
use Temporal\Workflow\WorkflowExecution;

/**
 * @group client
 * @group functional
 */
final class ExternalStorageTestCase extends AbstractClient
{
    public function testServerKeepsTheReferenceAndTheConverterRestoresThePayload(): void
    {
        $driver = new InMemoryStorageDriver();
        $storage = new ExternalStorage([$driver], payloadSizeThreshold: 1024);
        $client = new WorkflowClient(
            ServiceClient::create(TemporalServer::address()),
            (new ClientOptions())->withExternalStorage($storage),
        );
        $input = \str_repeat('x', 4096);
        $stub = $client->newUntypedWorkflowStub(
            'ExternalStorageWithoutWorker',
            WorkflowOptions::new()->withTaskQueue('external-storage-without-worker'),
        );

        $client->start($stub, $input);
        $execution = $stub->getExecution();

        try {
            $stored = $this->firstInput($this->createClient(), $execution);
            self::assertSame(
                ReferencePayload::MESSAGE_TYPE,
                $stored->getMetadata()[EncodingKeys::METADATA_MESSAGE_TYPE],
            );
            self::assertSame('true', $stored->getMetadata()[ReferencePayload::MARKER]);
            self::assertCount(1, $stored->getExternalPayloads());

            $converter = new ExternalStorageDataConverter(
                DataConverter::createDefault(),
                new ExternalStorageRunner($storage, 'default', new NullLogger()),
            );
            self::assertSame($input, $converter->fromPayload($stored, 'string'));
            self::assertCount(1, $driver->objects);
        } finally {
            $stub->terminate('done');
        }
    }

    private function firstInput(WorkflowClient $client, WorkflowExecution $execution): Payload
    {
        foreach ($client->getWorkflowHistory($execution) as $event) {
            return $event->getWorkflowExecutionStartedEventAttributes()->getInput()->getPayloads()[0];
        }

        self::fail('The workflow history is empty.');
    }
}
