<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Client\GRPC\StatusCode;
use Temporal\Internal\Bridge\Bridge;
use Temporal\Internal\Bridge\BridgeConnection;

/**
 * @internal
 * @psalm-require-extends \Grpc\BaseStub
 */
trait CoreStub
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;
    private const METADATA_KEY_PATTERN = '/^[.A-Za-z\d_-]+$/';

    private string $address;

    /** @var array<string, ?string>|null */
    private ?array $tls;

    private ?\FFI\CData $core = null;
    private ?Bridge $bridge = null;
    private ConnectionState $state = ConnectionState::Idle;
    private ?int $connecting = null;

    /**
     * @param array<string, ?string>|null $tls
     */
    public function __construct(string $address, ?array $tls = null)
    {
        $this->address = $address;
        $this->tls = $tls;
    }

    public function getTarget(): string
    {
        return $this->address;
    }

    /**
     * @param bool $try_to_connect
     */
    public function getConnectivityState($try_to_connect = false): int
    {
        $this->updateState($try_to_connect, 0);

        return $this->state->value;
    }

    /**
     * @param int $timeout
     */
    public function waitForReady($timeout): bool
    {
        $this->updateState(true, self::milliseconds($timeout));

        return $this->state === ConnectionState::Ready;
    }

    public function close(): void
    {
        if ($this->bridge !== null && Bridge::isCurrent($this->bridge)) {
            if ($this->connecting !== null) {
                $this->bridge->forgetCall($this->connecting);
            }
            if ($this->core !== null) {
                $this->bridge->freeClient($this->core);
            }
        }
        $this->core = null;
        $this->bridge = null;
        $this->connecting = null;
        $this->state = ConnectionState::Idle;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param string $method
     * @param \Google\Protobuf\Internal\Message $argument
     * @param array{class-string<\Google\Protobuf\Internal\Message>, string} $deserialize
     * @param array<array-key, list<string>> $metadata
     * @psalm-suppress MoreSpecificImplementedParamType, ImplementedReturnTypeMismatch
     */
    protected function _simpleRequest($method, $argument, $deserialize, array $metadata = [], array $options = []): CoreCall
    {
        $normalized = [];
        foreach ($metadata as $key => $values) {
            if (!\preg_match(self::METADATA_KEY_PATTERN, (string) $key)) {
                throw new \InvalidArgumentException('Metadata keys must be nonempty strings containing only alphanumeric characters, hyphens, underscores and dots');
            }
            $key = \strtolower((string) $key);
            $normalized[$key] = \str_ends_with($key, '-bin') ? \array_map(\base64_encode(...), $values) : $values;
        }
        $timeoutMs = isset($options['timeout']) ? \max(1, self::milliseconds((int) $options['timeout'])) : 0;
        $client = $this->client();
        $tag = $this->bridge->startCall($client, $method, $argument->serializeToString(), $normalized, $timeoutMs);

        return new CoreCall($this->bridge, $tag, $deserialize);
    }

    private static function milliseconds(int $microseconds): int
    {
        $milliseconds = \intdiv($microseconds, self::MICROSECONDS_PER_MILLISECOND);

        return $microseconds % self::MICROSECONDS_PER_MILLISECOND > 0 ? $milliseconds + 1 : $milliseconds;
    }

    /**
     * @psalm-assert !null $this->bridge
     */
    private function client(): \FFI\CData
    {
        $this->assertSameProcess();
        if ($this->core === null) {
            $this->bridge = Bridge::shared();
            $this->core = $this->bridge->newClient(BridgeConnection::client($this->address, $this->tls));
        }

        return $this->core;
    }

    private function assertSameProcess(): void
    {
        if ($this->bridge !== null && !Bridge::isCurrent($this->bridge)) {
            throw new \LogicException(Bridge::FORKED_AFTER_START);
        }
    }

    private function updateState(bool $connect, int $waitMs): void
    {
        $this->assertSameProcess();
        if ($connect && $this->connecting === null && $this->state !== ConnectionState::Ready) {
            $client = $this->client();
            $this->connecting = $this->bridge->startConnect($client, BridgeConnection::CONNECT_TIMEOUT_MS);
            $this->state = ConnectionState::Connecting;
        }
        if ($this->connecting === null) {
            return;
        }

        /** @var Bridge $this->bridge */
        $result = $this->bridge->pollCall($this->connecting, $waitMs);
        if ($result === null) {
            return;
        }
        $this->connecting = null;
        $this->state = $result[0] === StatusCode::OK ? ConnectionState::Ready : ConnectionState::TransientFailure;
    }
}
