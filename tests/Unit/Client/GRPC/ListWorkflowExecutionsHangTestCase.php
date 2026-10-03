<?php

declare(strict_types=1);

namespace Temporal\Tests\Unit\Client\GRPC;

use PHPUnit\Framework\TestCase;
use Temporal\Api\Workflowservice\V1\ListWorkflowExecutionsRequest;
use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Client\WorkflowClient;
use Temporal\Exception\Client\ServiceClientException;

/**
 * @see https://github.com/temporalio/sdk-php/issues/752
 */
final class ListWorkflowExecutionsHangTestCase extends TestCase
{
    public function testListExecutionsRpcReceivesDefaultDeadlineWhenNoneConfigured(): void
    {
        $workflowService = new class extends WorkflowServiceClient {
            public array $options = [];

            public function __construct() {}

            public function close(): void {}

            public function ListWorkflowExecutions(
                ListWorkflowExecutionsRequest $argument,
                $metadata = [],
                $options = [],
            ): never {
                $this->options = $options;
                throw new ServiceClientException((object) ['code' => StatusCode::INVALID_ARGUMENT, 'metadata' => []]);
            }
        };

        $serviceClient = new ServiceClient(static fn(): WorkflowServiceClient => $workflowService);
        $serviceClient = $serviceClient->withContext($serviceClient->getContext()->withDeadline(new \DateTimeImmutable('+5 seconds')));
        $client = WorkflowClient::create($serviceClient);

        try {
            \iterator_to_array($client->listWorkflowExecutions('WorkflowType="Repro"'));
            self::fail('Expected the non-retryable error to surface.');
        } catch (ServiceClientException) {
        }

        self::assertArrayHasKey(
            'timeout',
            $workflowService->options,
            'listWorkflowExecutions must pass a deadline so the RPC cannot retry forever.',
        );
        self::assertGreaterThan(0, $workflowService->options['timeout']);
    }
}
