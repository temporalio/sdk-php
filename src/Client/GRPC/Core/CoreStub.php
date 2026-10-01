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
use Temporal\Worker\Core\Bridge;

/**
 * @internal
 * @psalm-require-extends \Grpc\BaseStub
 */
trait CoreStub
{
    private const MICROSECONDS_PER_MILLISECOND = 1000;
    private const CONNECT_TIMEOUT_MS = 10_000;

    private string $address;
    private ?object $tls;
    private ?\FFI\CData $core = null;
    private int $pid = 0;
    private ConnectionState $state = ConnectionState::Idle;
    private ?int $connecting = null;

    /**
     * @param array{tls?: object} $config
     */
    public function __construct(string $address, array $config = [])
    {
        $this->address = $address;
        $this->tls = $config['tls'] ?? null;
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
        if ($this->core !== null && $this->pid === (int) \getmypid()) {
            Bridge::shared()->freeClient($this->core);
        }
        $this->core = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param string $method
     * @param \Google\Protobuf\Internal\Message $argument
     * @param array{class-string<\Google\Protobuf\Internal\Message>, string} $deserialize
     * @param array<string, list<string>> $metadata
     * @psalm-suppress MoreSpecificImplementedParamType, ImplementedReturnTypeMismatch
     */
    protected function _simpleRequest($method, $argument, $deserialize, array $metadata = [], array $options = []): CoreCall
    {
        foreach ($metadata as $key => $values) {
            if (\str_ends_with(\strtolower($key), '-bin')) {
                $metadata[$key] = \array_map(\base64_encode(...), $values);
            }
        }
        $timeoutMs = isset($options['timeout']) ? \max(1, self::milliseconds((int) $options['timeout'])) : 0;
        $tag = Bridge::shared()->startCall($this->client(), $method, $argument->serializeToString(), $metadata, $timeoutMs);

        return new CoreCall(Bridge::shared(), $tag, $deserialize);
    }

    private static function milliseconds(int $microseconds): int
    {
        return \intdiv($microseconds + self::MICROSECONDS_PER_MILLISECOND - 1, self::MICROSECONDS_PER_MILLISECOND);
    }

    private function client(): \FFI\CData
    {
        if ($this->core === null || $this->pid !== (int) \getmypid()) {
            $this->core = Bridge::shared()->newClient([
                'target_url' => ($this->tls === null ? 'http://' : 'https://') . $this->address,
                'tls' => $this->tls,
            ]);
            $this->pid = (int) \getmypid();
        }

        return $this->core;
    }

    private function updateState(bool $connect, int $waitMs): void
    {
        if ($this->pid !== (int) \getmypid()) {
            $this->state = ConnectionState::Idle;
            $this->connecting = null;
        }
        if ($connect && $this->connecting === null && $this->state !== ConnectionState::Ready) {
            $this->connecting = Bridge::shared()->startConnect($this->client(), self::CONNECT_TIMEOUT_MS);
            $this->state = ConnectionState::Connecting;
        }
        if ($this->connecting === null) {
            return;
        }

        $result = Bridge::shared()->pollCall($this->connecting, $waitMs);
        if ($result === null) {
            return;
        }
        $this->connecting = null;
        $this->state = $result[0] === StatusCode::OK ? ConnectionState::Ready : ConnectionState::TransientFailure;
    }
}
