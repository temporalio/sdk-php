<?php

declare(strict_types=1);

namespace Temporal\Tests\Functional\Client;

use Psr\Log\AbstractLogger;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Common\PayloadLimitOptions;
use Temporal\Testing\TemporalServer;
use Temporal\Tests\Workflow\SimpleWorkflow;

/**
 * @group client
 * @group functional
 */
class PayloadWarningTestCase extends AbstractClient
{
    private AbstractLogger $logger;

    public function testOversizedInputIsReportedAndStillAccepted(): void
    {
        $client = $this->createClientWithLogger();
        $input = \str_repeat('x', PayloadLimitOptions::DEFAULT_PAYLOAD_SIZE_WARNING + 1);

        $run = $client->start($client->newWorkflowStub(SimpleWorkflow::class), $input);

        self::assertSame(\strtoupper($input), $run->getResult('string'));

        $records = $this->warnings();
        self::assertCount(1, $records);
        self::assertSame('StartWorkflowExecution', $records[0]['context']['method']);
        self::assertGreaterThan(
            PayloadLimitOptions::DEFAULT_PAYLOAD_SIZE_WARNING,
            $records[0]['context']['size'],
        );
    }

    public function testInputBelowTheLimitIsNotReported(): void
    {
        $client = $this->createClientWithLogger();

        $run = $client->start($client->newWorkflowStub(SimpleWorkflow::class), 'hello');

        self::assertSame('HELLO', $run->getResult('string'));
        self::assertSame([], $this->warnings());
    }

    protected function setUp(): void
    {
        $this->logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };

        parent::setUp();
    }

    private function createClientWithLogger(): WorkflowClient
    {
        return new WorkflowClient(
            ServiceClient::create(TemporalServer::address()),
            logger: $this->logger,
        );
    }

    /**
     * @return list<array{message: string, context: array}>
     */
    private function warnings(): array
    {
        return \array_values(
            \array_filter(
                $this->logger->records,
                static fn(array $record): bool => \str_contains($record['message'], '[TMPRL1103]'),
            ),
        );
    }
}
