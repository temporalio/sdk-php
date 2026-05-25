<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Transport;

use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Worker\Transport\CommandBatch;
use Temporal\Worker\Transport\HostConnectionInterface;

final class RecordingHost implements HostConnectionInterface
{
    private int $inboundBatchId = 0;

    private int $outboundSeq = 0;

    public function __construct(
        private readonly HostConnectionInterface $inner,
        private readonly TranscriptWriter $transcript,
    ) {
        $this->record(fn() => $this->transcript->writeMeta('host_recording_started', [
            'inner' => $inner::class,
            'pid' => \getmypid() ?: 0,
            'transcript_path' => $this->transcript->getPath(),
        ]));
    }

    public function waitBatch(): ?CommandBatch
    {
        try {
            $batch = $this->inner->waitBatch();
        } catch (\Throwable $error) {
            $this->record(fn() => $this->transcript->writeWireError($error));
            throw $error;
        }
        if ($batch === null) {
            return null;
        }
        $this->inboundBatchId++;
        $this->outboundSeq = 0;
        $batchId = $this->inboundBatchId;
        $this->record(fn() => $this->transcript->writeWireInbound($batch->messages, $batch->context, $batchId));
        return $batch;
    }

    public function send(string $frame): void
    {
        $this->outboundSeq++;
        $batchId = $this->inboundBatchId;
        $sequence = $this->outboundSeq;
        try {
            $this->inner->send($frame);
        } catch (\Throwable $error) {
            $this->record(fn() => $this->transcript->writeWireError($error));
            throw $error;
        }
        $this->record(fn() => $this->transcript->writeWireOutbound($frame, $batchId, $sequence));
    }

    public function error(\Throwable $error): void
    {
        $this->record(fn() => $this->transcript->writeWireError($error));
        $this->inner->error($error);
    }

    private function record(callable $write): void
    {
        try {
            $write();
        } catch (\Throwable) {
        }
    }
}
