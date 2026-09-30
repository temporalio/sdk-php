<?php

/**
 * This file is part of Temporal package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Temporal\Worker\Core;

final class Bridge
{
    public const KIND_WORKFLOW_ACTIVATION = 1;
    public const KIND_ACTIVITY_TASK = 2;
    public const KIND_WORKFLOW_COMPLETED = 3;
    public const KIND_ACTIVITY_COMPLETED = 4;
    public const KIND_SHUTDOWN = 5;
    public const STATUS_OK = 0;
    public const STATUS_ERROR = 1;
    public const STATUS_SHUTDOWN = 2;
    private const EVENT_BUFFER_SIZE = 256;

    private readonly \FFI $ffi;
    private readonly \FFI\CData $runtime;
    private readonly \FFI\CData $events;

    /** @var list<array{int, int, int, string}> */
    private array $backlog = [];

    public function __construct(?string $library = null, ?string $header = null)
    {
        $root = \dirname(__DIR__, 3) . '/core/bridge';
        $library ??= $_SERVER['TEMPORAL_CORE_BRIDGE_LIB']
            ?? $root . '/target/release/libtemporal_php_bridge.' . (\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so');
        $header ??= $root . '/include/temporal_php_bridge.h';

        $this->ffi = \FFI::cdef((string) \file_get_contents($header), $library);
        $runtime = $this->ffi->tpb_runtime_new();
        if ($runtime === null) {
            throw new \RuntimeException('Unable to create the sdk-core runtime, see stderr for details');
        }
        $this->runtime = $runtime;
        $this->events = $this->ffi->new(\sprintf('TpbEvent[%d]', self::EVENT_BUFFER_SIZE));
    }

    public function eventFd(): int
    {
        return $this->ffi->tpb_event_fd($this->runtime);
    }

    public function newWorker(array $config): \FFI\CData
    {
        return $this->createWorker('tpb_worker_new', $config);
    }

    public function newReplayer(array $config, string $history): \FFI\CData
    {
        return $this->createWorker('tpb_replayer_new', $config, $history, \strlen($history));
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
        $this->ffi->tpb_record_activity_heartbeat($worker, $heartbeat, \strlen($heartbeat));
    }

    public function requestWorkflowEviction(\FFI\CData $worker, string $runId): void
    {
        $this->ffi->tpb_request_workflow_eviction($worker, $runId, \strlen($runId));
    }

    public function initiateShutdown(\FFI\CData $worker): void
    {
        $this->ffi->tpb_worker_initiate_shutdown($worker);
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
        if ($this->backlog !== []) {
            $events = $this->backlog;
            $this->backlog = [];

            return $events;
        }

        return $this->fetch($timeoutMs);
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

    private function createWorker(string $function, array $config, string|int ...$args): \FFI\CData
    {
        $json = \json_encode($config, \JSON_THROW_ON_ERROR);
        $err = $this->ffi->new('uint8_t*');
        $errLen = $this->ffi->new('size_t');
        $worker = $this->ffi->$function($this->runtime, $json, \strlen($json), ...[...$args, \FFI::addr($err), \FFI::addr($errLen)]);

        if ($worker === null) {
            $message = \FFI::isNull($err) ? 'unknown error' : $this->take($err, $errLen->cdata);
            throw new \RuntimeException('Unable to create sdk-core worker: ' . $message);
        }

        return $worker;
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
            $result[] = [$event->tag, $event->kind, $event->status, $this->take($event->data, $event->len)];
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
