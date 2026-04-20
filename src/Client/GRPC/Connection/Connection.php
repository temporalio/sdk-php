<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Connection;

use Grpc\BaseStub;
use Temporal\Client\Common\ServerCapabilities;

/**
 * @internal
 */
final class Connection implements ConnectionInterface
{
    private ?ServerCapabilities $capabilities = null;
    private BaseStub $client;

    /**
     * True if ServiceClient wasn't created yet
     */
    private bool $closed = true;

    /**
     * @param \Closure(): BaseStub $clientFactory Service Client factory
     */
    public function __construct(
        private readonly \Closure $clientFactory,
    ) {
        $this->initClient();
    }

    public function isConnected(): bool
    {
        return ConnectionState::from($this->client->getConnectivityState(false)) === ConnectionState::Ready;
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
                $this->client->waitForReady(50);
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
        $this->client->close();
    }

    public function getCapabilities(): ?ServerCapabilities
    {
        return $this->capabilities;
    }

    public function setCapabilities(?ServerCapabilities $capabilities): void
    {
        $this->capabilities = $capabilities;
    }

    /**
     * @return BaseStub Shouldn't be cached
     */
    public function getClient(): BaseStub
    {
        $this->initClient();
        return $this->client;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function getState(bool $tryToConnect = false): ConnectionState
    {
        return ConnectionState::from($this->client->getConnectivityState($tryToConnect));
    }

    /**
     * Create a new client with a new channel
     */
    private function initClient(): void
    {
        if (!$this->closed) {
            return;
        }

        $this->client = ($this->clientFactory)();
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
        return $this->client->waitForReady((int) ($timeout * 1_000_000));
    }
}
