<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Internal\Bridge;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use Temporal\Worker\Logger\StderrLogger;

/**
 * @internal
 * @psalm-suppress UndefinedMethod
 */
final class Bridge
{
    public const KIND_WORKFLOW_ACTIVATION = 1;
    public const KIND_ACTIVITY_TASK = 2;
    public const KIND_WORKFLOW_COMPLETED = 3;
    public const KIND_ACTIVITY_COMPLETED = 4;
    public const KIND_SHUTDOWN_FINALIZED = 5;
    public const KIND_RPC_RESULT = 6;
    public const KIND_LOG = 7;
    public const STATUS_OK = 0;
    public const STATUS_SHUTDOWN = 2;
    public const POLL_TIMEOUT_MS = 500;
    public const FORKED_AFTER_START = 'The sdk-core runtime started before fork() and cannot be used in the forked process';
    private const FINALIZE_TIMEOUT_SECONDS = 5.0;
    private const CALL_OK = 0;
    private const EVENT_BUFFER_SIZE = 256;
    private const DEFAULT_THREADS = 1;
    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;
    private const DEFAULT_LOG_FILTER = 'warn';
    private const LOG_LEVELS = ['ERROR' => LogLevel::ERROR, 'WARN' => LogLevel::WARNING, 'INFO' => LogLevel::INFO];
    private const HEADER = __DIR__ . '/../../../core/bridge/include/temporal_php_bridge.h';

    private static ?self $shared = null;
    private static int $sharedPid = 0;
    private readonly \FFI $ffi;
    private readonly \FFI\CData $runtime;
    private readonly \FFI\CData $events;

    /** @var list<array{int, int, int, string}> */
    private array $backlog = [];

    /** @var array<int, array{int, string}|null> */
    private array $rpcResults = [];

    /** @var array<int, Suspension> */
    private array $rpcWaiters = [];

    private int $rpcTag = 0;
    private LoggerInterface $logger;

    /** @var resource|null */
    private $eventPipe = null;

    public function __construct()
    {
        $this->logger = new StderrLogger();
        $library = CoreEnvironment::string(CoreEnvironment::BRIDGE_LIB)
            ?? \dirname(__DIR__, 3) . '/core/bridge/target/release/libtemporal_php_bridge.' . (\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so');
        if (!\is_readable($library)) {
            throw new \RuntimeException(\sprintf('Unable to read the sdk-core bridge library "%s", build it with `cargo build --release` in core/bridge or set TEMPORAL_CORE_BRIDGE_LIB; the gRPC client uses ext-grpc instead when it is installed', $library));
        }

        $this->ffi = \FFI::cdef((string) \file_get_contents(self::HEADER), $library);
        $config = self::json([
            'threads' => CoreEnvironment::integer(CoreEnvironment::THREADS, self::DEFAULT_THREADS, 1),
            'log' => CoreEnvironment::string(CoreEnvironment::LOG) ?? self::DEFAULT_LOG_FILTER,
            'prometheus' => CoreEnvironment::string(CoreEnvironment::PROMETHEUS),
        ]);
        $this->runtime = $this->construct('tpb_runtime_new', $config, \strlen($config));
        /** @var \FFI\CData $events */
        $events = $this->ffi->new(\sprintf('TpbEvent[%d]', self::EVENT_BUFFER_SIZE));
        $this->events = $events;
    }

    public static function shared(): self
    {
        if (self::$shared !== null && self::$sharedPid !== (int) \getmypid()) {
            throw new \LogicException(self::FORKED_AFTER_START);
        }
        if (self::$shared === null) {
            self::$shared = new self();
            self::$sharedPid = (int) \getmypid();
        }

        return self::$shared;
    }

    public static function started(): bool
    {
        return self::$shared !== null && self::$sharedPid === (int) \getmypid();
    }

    public static function isCurrent(self $bridge): bool
    {
        return self::started() && self::$shared === $bridge;
    }

    public function useLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return resource
     */
    public function openEventPipe()
    {
        if ($this->eventPipe === null) {
            /** @var resource $pipe */
            $pipe = \fopen('php://fd/' . $this->ffi->tpb_event_fd($this->runtime), 'r');
            \stream_set_blocking($pipe, false);
            $this->eventPipe = $pipe;
        }

        return $this->eventPipe;
    }

    public function closeEventPipe(): void
    {
        if ($this->eventPipe !== null) {
            $pipe = $this->eventPipe;
            \fclose($pipe);
            $this->eventPipe = null;
        }
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
        $this->rpcResults[$tag] = null;
        $this->ffi->tpb_client_call($client, $tag, $path, \strlen($path), $request, \strlen($request), $json, \strlen($json), $timeoutMs);

        return $tag;
    }

    public function startConnect(\FFI\CData $client, int $timeoutMs): int
    {
        $tag = ++$this->rpcTag;
        $this->rpcResults[$tag] = null;
        $this->ffi->tpb_client_connect($client, $tag, $timeoutMs);

        return $tag;
    }

    /**
     * @return array{int, string}|null
     */
    public function pollCall(int $tag, int $timeoutMs): ?array
    {
        $deadline = \hrtime(true) + $timeoutMs * self::NANOSECONDS_PER_MILLISECOND;
        do {
            $left = \max(0, \intdiv($deadline - \hrtime(true), self::NANOSECONDS_PER_MILLISECOND));
            \array_push($this->backlog, ...$this->fetch(\min($left, self::POLL_TIMEOUT_MS)));
        } while (!isset($this->rpcResults[$tag]) && \hrtime(true) < $deadline);
        $result = $this->rpcResults[$tag] ?? null;
        if ($result !== null) {
            unset($this->rpcResults[$tag]);
        }

        return $result;
    }

    /**
     * @return array{int, string}
     */
    public function awaitCall(int $tag): array
    {
        if (!isset($this->rpcResults[$tag]) && $this->eventPipe !== null && \Fiber::getCurrent() !== null && EventLoop::getDriver()->isRunning()) {
            $this->rpcWaiters[$tag] = EventLoop::getSuspension();
            $this->rpcWaiters[$tag]->suspend();
        }
        while (!isset($this->rpcResults[$tag])) {
            \array_push($this->backlog, ...$this->fetch(self::POLL_TIMEOUT_MS));
        }
        /** @var array{int, string} $result */
        $result = $this->rpcResults[$tag];
        unset($this->rpcResults[$tag]);

        return $result;
    }

    public function forgetCall(int $tag): void
    {
        unset($this->rpcResults[$tag], $this->rpcWaiters[$tag]);
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

    public function initiateShutdown(\FFI\CData $worker): void
    {
        $this->ffi->tpb_worker_initiate_shutdown($worker);
    }

    /**
     * @param array<int, \FFI\CData> $workers
     * @return array<int, string>
     */
    public function shutdownWorkers(array $workers): array
    {
        foreach ($workers as $tag => $worker) {
            $this->ffi->tpb_worker_finalize_shutdown($worker, $tag);
        }

        $pending = $workers;
        $errors = [];
        $deadline = \microtime(true) + self::FINALIZE_TIMEOUT_SECONDS;
        while ($pending !== [] && \microtime(true) < $deadline) {
            foreach ($this->nextEvents(self::POLL_TIMEOUT_MS) as [$tag, $kind, $status, $data]) {
                if (isset($workers[$tag]) && $status !== self::STATUS_OK && $status !== self::STATUS_SHUTDOWN) {
                    $errors[$tag][] = $data;
                }
                if ($kind === self::KIND_SHUTDOWN_FINALIZED) {
                    unset($pending[$tag]);
                }
            }
        }
        foreach (\array_keys($pending) as $tag) {
            $errors[$tag][] = 'The sdk-core worker did not finalize in time';
        }
        foreach ($workers as $worker) {
            $this->ffi->tpb_worker_free($worker);
        }

        return \array_map(static fn(array $messages): string => \implode('; ', $messages), $errors);
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

    /**
     * @psalm-suppress UndefinedPropertyFetch
     */
    private function construct(string $function, \FFI\CData|string|int ...$arguments): \FFI\CData
    {
        /** @var \FFI\CData $err */
        $err = $this->ffi->new('uint8_t*');
        /** @var \FFI\CData $errLen */
        $errLen = $this->ffi->new('size_t');
        $object = $this->ffi->$function(...[...$arguments, \FFI::addr($err), \FFI::addr($errLen)]);

        if ($object === null) {
            throw new \RuntimeException(\sprintf('%s failed: %s', $function, $this->take($err, $errLen->cdata)));
        }

        return $object;
    }

    /**
     * @return list<array{int, int, int, string}>
     */
    private function fetch(int $timeoutMs): array
    {
        $result = [];
        do {
            $count = $this->ffi->tpb_next_events($this->runtime, $timeoutMs, $this->events, self::EVENT_BUFFER_SIZE);
            $timeoutMs = 0;
            for ($i = 0; $i < $count; ++$i) {
                $event = $this->events[$i];
                $data = $this->take($event->data, $event->len);
                if ($event->kind === self::KIND_LOG) {
                    $this->log($data);
                    continue;
                }
                if ($event->kind !== self::KIND_RPC_RESULT) {
                    $result[] = [$event->tag, $event->kind, $event->status, $data];
                    continue;
                }
                if (!\array_key_exists($event->tag, $this->rpcResults)) {
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
        } while ($count === self::EVENT_BUFFER_SIZE);

        return $result;
    }

    private function log(string $json): void
    {
        /** @var array{level: string, target: string, message: string, fields: array<string, mixed>} $entry */
        $entry = \json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $this->logger->log(
            self::LOG_LEVELS[$entry['level']] ?? LogLevel::DEBUG,
            $entry['message'],
            ['target' => $entry['target']] + $entry['fields'],
        );
    }

    private function take(?\FFI\CData $data, int $len): string
    {
        if ($len === 0 || $data === null) {
            return '';
        }

        $bytes = \FFI::string($data, $len);
        $this->ffi->tpb_bytes_free($data, $len);

        return $bytes;
    }
}
