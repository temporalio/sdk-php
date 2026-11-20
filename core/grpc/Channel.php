<?php

declare(strict_types=1);

namespace Grpc;

use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\BridgeConnection;

class Channel
{
    private const SCHEME_PATTERN = '/^(dns|ipv4|ipv6|unix|unix-abstract):/';
    private const AUTHORITY_PREFIX_PATTERN = '~^[a-z-]+:(//[^/]*/)?~';
    private const DEFAULT_SCHEME = 'dns:///';
    private const CLOSED_ERROR_CODE = 1;

    /** @var array<string, ?string>|null */
    private readonly ?array $tls;

    private ?\FFI\CData $client = null;
    private ?int $connecting = null;
    private int $state = CHANNEL_IDLE;
    private bool $closed = false;

    public function __construct(private readonly string $target, array $args)
    {
        /** @var ChannelCredentials|null $credentials */
        $credentials = $args['credentials'] ?? null;
        /** @var string|null $serverName */
        $serverName = $args['grpc.ssl_target_name_override'] ?? null;
        $this->tls = $credentials?->tls($serverName);
    }

    public function getTarget(): string
    {
        $this->assertOpen('getTarget error.');

        return \preg_match(self::SCHEME_PATTERN, $this->target) === 1 ? $this->target : self::DEFAULT_SCHEME . $this->target;
    }

    public function getConnectivityState(bool $try_to_connect = false): int
    {
        $this->assertOpen('getConnectivityState error.');
        $this->poll(0);
        $state = $this->state;
        if ($try_to_connect && $this->connecting === null && $state !== CHANNEL_READY) {
            $this->connecting = Bridge::shared()->startConnect($this->client(), BridgeConnection::CONNECT_TIMEOUT_MS);
            $this->state = $state === CHANNEL_IDLE ? CHANNEL_CONNECTING : $state;
        }

        return $state;
    }

    public function watchConnectivityState(int $last_state, Timeval $deadline): bool
    {
        $this->assertOpen('watchConnectivityState error');
        if ($this->state === $last_state) {
            $this->poll($deadline->millisecondsLeft());
        }
        if ($this->state === $last_state) {
            $deadline->sleepUntil();
        }

        return $this->state !== $last_state;
    }

    public function close(): void
    {
        if (Bridge::started()) {
            if ($this->connecting !== null) {
                Bridge::shared()->forgetCall($this->connecting);
            }
            if ($this->client !== null) {
                Bridge::shared()->freeClient($this->client);
            }
        }
        $this->client = null;
        $this->connecting = null;
        $this->closed = true;
    }

    /**
     * @internal
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }

    /**
     * @internal
     */
    public function isSecure(): bool
    {
        return $this->tls !== null;
    }

    /**
     * @internal
     */
    public function authority(): string
    {
        return $this->tls['domain'] ?? (string) \preg_replace(self::AUTHORITY_PREFIX_PATTERN, '', $this->getTarget());
    }

    /**
     * @internal
     * @param array<string, list<string>> $metadata
     */
    public function startCall(string $method, string $message, array $metadata, int $timeoutMs): int
    {
        return Bridge::shared()->startCall($this->client(), $method, $message, $metadata, $timeoutMs);
    }

    /**
     * @internal
     * @return array{int, string}
     */
    public function finishCall(int $tag): array
    {
        $result = Bridge::shared()->awaitCall($tag);
        $this->state = match ($result[0]) {
            STATUS_OK => CHANNEL_READY,
            STATUS_UNAVAILABLE => $this->state === CHANNEL_READY ? CHANNEL_IDLE : CHANNEL_TRANSIENT_FAILURE,
            default => $this->state,
        };

        return $result;
    }

    /**
     * @internal
     */
    public function forgetCall(int $tag): void
    {
        if (Bridge::started()) {
            Bridge::shared()->forgetCall($tag);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function client(): \FFI\CData
    {
        return $this->client ??= Bridge::shared()->newClient(BridgeConnection::client($this->target, $this->tls));
    }

    private function poll(int $waitMs): void
    {
        if ($this->connecting === null) {
            return;
        }
        $result = Bridge::shared()->pollCall($this->connecting, $waitMs);
        if ($result === null) {
            return;
        }
        $this->connecting = null;
        $this->state = $result[0] === STATUS_OK ? CHANNEL_READY : CHANNEL_TRANSIENT_FAILURE;
    }

    private function assertOpen(string $prefix): void
    {
        if ($this->closed) {
            throw new \RuntimeException($prefix . 'Channel is already closed.', self::CLOSED_ERROR_CODE);
        }
    }
}
