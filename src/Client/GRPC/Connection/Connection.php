<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\ServerCapabilities;

/**
 * @internal
 */
final class Connection implements ConnectionInterface
{
    public ?ServerCapabilities $capabilities = null;

    public function __construct(
        public readonly WorkflowServiceClient $workflowService,
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    public function getState(bool $tryToConnect = false): ConnectionState
    {
        return ConnectionState::from($this->workflowService->getConnectivityState($tryToConnect));
    }

    public function waitForReady(float $timeout): bool
    {
        /** @psalm-suppress InvalidOperand */
        return $this->workflowService->waitForReady((int)($timeout * 1_000_000));
    }

    public function close(): void
    {
        $this->capabilities = null;
        $this->workflowService->close();
    }
}
