<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

final class Bridge
{
    public const KIND_WORKFLOW_ACTIVATION = 1;
    public const KIND_ACTIVITY_TASK = 2;
    public const KIND_WORKFLOW_COMPLETED = 3;
    public const KIND_ACTIVITY_COMPLETED = 4;
    public const KIND_SHUTDOWN_FINALIZED = 5;
    public const KIND_RPC_RESULT = 6;
    public const STATUS_OK = 0;
    public const STATUS_ERROR = 1;
    public const STATUS_SHUTDOWN = 2;
    public const POLL_TIMEOUT_MS = 500;
    private const CALL_OK = 0;
    private const EVENT_BUFFER_SIZE = 256;
    private const DEFAULT_THREADS = 1;

    private static ?self $shared = null;
    private static int $sharedPid = 0;
    private readonly \FFI $ffi;
    private readonly \FFI\CData $runtime;
    private readonly \FFI\CData $events;

    /** @var list<array{int, int, int, string}> */
    private array $backlog = [];

    /** @var array<int, array{int, string}> */
    private array $rpcResults = [];

    /** @var array<int, Suspension> */
    private array $rpcWaiters = [];

    private int $rpcTag = 0;
    private bool $pumped = false;

    public function __construct(?string $library = null, ?string $header = null)
    {
        $root = \dirname(__DIR__, 3) . '/core/bridge';
        $library ??= $_SERVER['TEMPORAL_CORE_BRIDGE_LIB']
            ?? $root . '/target/release/libtemporal_php_bridge.' . (\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so');
        $header ??= $root . '/include/temporal_php_bridge.h';

        $definitions = \is_readable($header) ? \file_get_contents($header) : false;
        if ($definitions === false) {
            throw new \RuntimeException(\sprintf('Unable to read the sdk-core bridge header "%s"', $header));
        }
        if (!\is_readable($library)) {
            throw new \RuntimeException(\sprintf('Unable to read the sdk-core bridge library "%s", build it with `cargo build --release` in core/bridge or set TEMPORAL_CORE_BRIDGE_LIB', $library));
        }

        $this->ffi = \FFI::cdef($definitions, $library);
        $config = self::json(['threads' => self::threads(), 'log' => $_SERVER['TEMPORAL_CORE_LOG'] ?? null]);
        $this->runtime = $this->construct('tpb_runtime_new', $config, \strlen($config));
        $this->events = $this->ffi->new(\sprintf('TpbEvent[%d]', self::EVENT_BUFFER_SIZE));
    }

    public static function shared(): self
    {
        if (self::$shared === null || self::$sharedPid !== (int) \getmypid()) {
            self::$shared = new self();
            self::$sharedPid = (int) \getmypid();
        }

        return self::$shared;
    }

    public static function started(): bool
    {
        return self::$shared !== null && self::$sharedPid === (int) \getmypid();
    }

    public function eventFd(): int
    {
        $this->pumped = true;

        return $this->ffi->tpb_event_fd($this->runtime);
    }

    public function newWorker(array $config): \FFI\CData
    {
        $json = self::json($config);

        return $this->construct('tpb_worker_new', $this->runtime, $json, \strlen($json));
    }

    public function newReplayer(array $config, string $history, string $workflowId): \FFI\CData
    {
        $json = self::json($config);

        return $this->construct('tpb_replayer_new', $this->runtime, $json, \strlen($json), $history, \strlen($history), $workflowId, \strlen($workflowId));
    }

    public function newClient(array $config): \FFI\CData
    {
        $json = self::json($config);

        return $this->construct('tpb_client_new', $this->runtime, $json, \strlen($json));
    }

    public function freeClient(\FFI\CData $client): void
    {
        $this->ffi->tpb_client_free($client);
    }

    /**
     * @param array<string, list<string>> $metadata
     */
    public function startCall(\FFI\CData $client, string $path, string $request, array $metadata, int $timeoutMs): int
    {
        $tag = ++$this->rpcTag;
        $json = $metadata === [] ? '' : \json_encode($metadata, \JSON_THROW_ON_ERROR);
        $this->ffi->tpb_client_call($client, $tag, $path, \strlen($path), $request, \strlen($request), $json, \strlen($json), $timeoutMs);

        return $tag;
    }

    /**
     * @return array{int, string}
     */
    public function awaitCall(int $tag): array
    {
        if (!isset($this->rpcResults[$tag]) && $this->pumped && \Fiber::getCurrent() !== null && EventLoop::getDriver()->isRunning()) {
            $this->rpcWaiters[$tag] = EventLoop::getSuspension();
            $this->rpcWaiters[$tag]->suspend();
        }
        while (!isset($this->rpcResults[$tag])) {
            \array_push($this->backlog, ...$this->fetch(self::POLL_TIMEOUT_MS));
        }
        $result = $this->rpcResults[$tag];
        unset($this->rpcResults[$tag]);

        return $result;
    }

    public function pollWorkflowActivation(\FFI\CData $worker, int $tag): void
    {
        $this->ffi->tpb_poll_workflow_activation($worker, $tag);
    }

    public function pollActivityTask(\FFI\CData $worker, int $tag): void
    {
        $this->ffi->tpb_poll_activity_task($worker, $tag);
    }

    public function completeWorkflowActivation(\FFI\CData $worker, int $tag, string $completion): void
    {
        $this->ffi->tpb_complete_workflow_activation($worker, $tag, $completion, \strlen($completion));
    }

    public function completeActivityTask(\FFI\CData $worker, int $tag, string $completion): void
    {
        $this->ffi->tpb_complete_activity_task($worker, $tag, $completion, \strlen($completion));
    }

    public function recordActivityHeartbeat(\FFI\CData $worker, string $heartbeat): void
    {
        if ($this->ffi->tpb_record_activity_heartbeat($worker, $heartbeat, \strlen($heartbeat)) !== self::CALL_OK) {
            throw new \RuntimeException('Unable to record the activity heartbeat: the sdk-core worker is finalized or the heartbeat is invalid');
        }
    }

    public function requestWorkflowEviction(\FFI\CData $worker, string $runId): void
    {
        if ($this->ffi->tpb_request_workflow_eviction($worker, $runId, \strlen($runId)) !== self::CALL_OK) {
            throw new \RuntimeException('Unable to request the workflow eviction: the sdk-core worker is finalized');
        }
    }

    public function initiateShutdown(\FFI\CData $worker): void
    {
        if ($this->ffi->tpb_worker_initiate_shutdown($worker) !== self::CALL_OK) {
            throw new \RuntimeException('Unable to initiate the worker shutdown: the sdk-core worker is finalized');
        }
    }

    public function finalizeShutdown(\FFI\CData $worker, int $tag): void
    {
        $this->ffi->tpb_worker_finalize_shutdown($worker, $tag);
    }

    public function freeWorker(\FFI\CData $worker): void
    {
        $this->ffi->tpb_worker_free($worker);
    }

    /**
     * @return list<array{int, int, int, string}>
     */
    public function nextEvents(int $timeoutMs): array
    {
        if ($this->backlog === []) {
            return $this->fetch($timeoutMs);
        }

        $events = [...$this->backlog, ...$this->fetch(0)];
        $this->backlog = [];

        return $events;
    }

    /**
     * @return list<array{int, int, int, string}>
     */
    public function peekEvents(): array
    {
        $events = $this->fetch(0);
        \array_push($this->backlog, ...$events);

        return $events;
    }

    private static function json(array $value): string
    {
        return \json_encode($value, \JSON_THROW_ON_ERROR);
    }

    private static function threads(): int
    {
        $threads = $_SERVER['TEMPORAL_CORE_THREADS'] ?? self::DEFAULT_THREADS;
        $value = \filter_var($threads, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new \InvalidArgumentException(\sprintf('TEMPORAL_CORE_THREADS must be a positive integer, "%s" given', (string) $threads));
        }

        return $value;
    }

    private function construct(string $function, \FFI\CData|string|int ...$arguments): \FFI\CData
    {
        $err = $this->ffi->new('uint8_t*');
        $errLen = $this->ffi->new('size_t');
        $object = $this->ffi->$function(...[...$arguments, \FFI::addr($err), \FFI::addr($errLen)]);

        if ($object === null) {
            $message = \FFI::isNull($err) ? 'unknown error' : $this->take($err, $errLen->cdata);
            throw new \RuntimeException(\sprintf('%s failed: %s', $function, $message));
        }

        return $object;
    }

    /**
     * @return list<array{int, int, int, string}>
     */
    private function fetch(int $timeoutMs): array
    {
        $count = $this->ffi->tpb_next_events($this->runtime, $timeoutMs, $this->events, self::EVENT_BUFFER_SIZE);
        $result = [];
        for ($i = 0; $i < $count; ++$i) {
            $event = $this->events[$i];
            $data = $this->take($event->data, $event->len);
            if ($event->kind !== self::KIND_RPC_RESULT) {
                $result[] = [$event->tag, $event->kind, $event->status, $data];
                continue;
            }
            $grpcCode = $event->status;
            $this->rpcResults[$event->tag] = [$grpcCode, $data];
            if (isset($this->rpcWaiters[$event->tag])) {
                $waiter = $this->rpcWaiters[$event->tag];
                unset($this->rpcWaiters[$event->tag]);
                $waiter->resume();
            }
        }

        return $result;
    }

    private function take(?\FFI\CData $data, int $len): string
    {
        if ($len === 0 || $data === null || \FFI::isNull($data)) {
            return '';
        }

        $bytes = \FFI::string($data, $len);
        $this->ffi->tpb_bytes_free($data, $len);

        return $bytes;
    }
}
