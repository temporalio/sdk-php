<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

use Temporal\Api\Workflowservice\V1\WorkflowServiceClient;
use Temporal\Client\Common\ServerCapabilities;

/**
 * @internal
 */
final class Connection implements ConnectionInterface
{
    public ?ServerCapabilities $capabilities = null;
    private WorkflowServiceClient $workflowService;

    /**
     * True if ServiceClient wasn't created yet
     */
    private bool $closed = true;

    /**
     * @param \Closure(): WorkflowServiceClient $clientFactory Service Client factory
     */
    public function __construct(
        public \Closure $clientFactory,
    ) {
        $this->initClient();
    }

    public function isConnected(): bool
    {
        return ConnectionState::from($this->workflowService->getConnectivityState(false)) === ConnectionState::Ready;
    }

    public function connect(float $timeout): void
    {
        $deadline = \microtime(true) + $timeout;
        $this->initClient();

        try {
            if ($this->isConnected()) {
                return;
            }
        } catch (\RuntimeException) {
            $this->disconnect();
            $this->initClient();
        }

        // Start connecting
        $this->getState(true);
        $isFiber = \Fiber::getCurrent() !== null;
        do {
            // Wait a bit
            if ($isFiber) {
                \Fiber::suspend();
            } else {
                $this->workflowService->waitForReady(50);
            }

            $alive = \microtime(true) < $deadline;
            $state = $this->getState();
        } while ($alive && $state === ConnectionState::Connecting);

        $alive or throw new \RuntimeException('Failed to connect to Temporal service. Timeout exceeded.');
        $state === ConnectionState::Idle and throw new \RuntimeException(
            'Failed to connect to Temporal service. Channel is in idle state.',
        );
        $state === ConnectionState::TransientFailure and throw new \RuntimeException(
            'Failed to connect to Temporal service. Channel is in transient failure state.',
        );
        $state === ConnectionState::Shutdown and throw new \RuntimeException(
            'Failed to connect to Temporal service. Channel is in shutdown state.',
        );
    }

    public function disconnect(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->capabilities = null;
        $this->workflowService->close();
    }

    /**
     * @return WorkflowServiceClient Shouldn't be cached
     */
    public function getWorkflowService(): WorkflowServiceClient
    {
        $this->initClient();
        return $this->workflowService;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function getState(bool $tryToConnect = false): ConnectionState
    {
        return ConnectionState::from($this->workflowService->getConnectivityState($tryToConnect));
    }

    /**
     * Create a new client with a new channel
     */
    private function initClient(): void
    {
        if (!$this->closed) {
            return;
        }

        $this->workflowService = ($this->clientFactory)();
        $this->closed = false;
    }

    /**
     * Wait for the channel to be ready.
     *
     * @param float $timeout in seconds
     *
     * @return bool true if channel is ready
     * @throws \Exception if channel is in FATAL_ERROR state
     */
    private function waitForReady(float $timeout): bool
    {
        /** @psalm-suppress InvalidOperand */
        return $this->workflowService->waitForReady((int) ($timeout * 1_000_000));
    }
}
