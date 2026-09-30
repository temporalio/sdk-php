<?php

declare(strict_types=1);

namespace Temporal\Client\GRPC\Core;

use Temporal\Client\GRPC\Connection\ConnectionState;
use Temporal\Worker\Core\Bridge;

/**
 * @internal
 * @psalm-require-extends \Grpc\BaseStub
 */
trait CoreStub
{
    private string $target;
    private array $config;
    private ?\FFI\CData $core = null;
    private int $pid = 0;

    /**
     * @param array{target_url: string, tls?: object} $config
     */
    public function __construct(string $address, array $config)
    {
        $this->target = $address;
        $this->config = $config;
    }

    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param bool $try_to_connect
     */
    public function getConnectivityState($try_to_connect = false): int
    {
        return ConnectionState::Ready->value;
    }

    /**
     * @param int $timeout
     */
    public function waitForReady($timeout): bool
    {
        return true;
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
        $bridge = Bridge::shared();
        if ($this->core === null || $this->pid !== (int) \getmypid()) {
            $this->core = $bridge->newClient($this->config);
            $this->pid = (int) \getmypid();
        }
        foreach ($metadata as $key => $values) {
            if (\str_ends_with(\strtolower($key), '-bin')) {
                $metadata[$key] = \array_map(\base64_encode(...), $values);
            }
        }
        $timeoutMs = isset($options['timeout']) ? \max(1, \intdiv((int) $options['timeout'] + 999, 1000)) : 0;
        $tag = $bridge->startCall($this->core, $method, $argument->serializeToString(), $metadata, $timeoutMs);

        return new CoreCall($bridge, $tag, $deserialize);
    }
}
