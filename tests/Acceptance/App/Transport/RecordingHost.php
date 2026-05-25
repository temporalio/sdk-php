<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Transport;

use Temporal\Tests\Acceptance\App\Logger\TranscriptWriter;
use Temporal\Worker\Transport\CommandBatch;
use Temporal\Worker\Transport\HostConnectionInterface;

final class RecordingHost implements HostConnectionInterface
{
    private int $frameCounter = 0;

    public function __construct(
        private readonly HostConnectionInterface $inner,
        private readonly TranscriptWriter $transcript,
    ) {
        $this->record(fn() => $this->transcript->writeMeta('host_recording_started', [
            'inner' => $inner::class,
        ]));
    }

    public function waitBatch(): ?CommandBatch
    {
        $batch = $this->inner->waitBatch();
        if ($batch === null) {
            return null;
        }
        $this->frameCounter++;
        $frameId = $this->frameCounter;
        $this->record(fn() => $this->transcript->writeWireInbound($batch->messages, $batch->context, $frameId));
        return $batch;
    }

    public function send(string $frame): void
    {
        $frameId = $this->frameCounter;
        $this->record(fn() => $this->transcript->writeWireOutbound($frame, $frameId));
        $this->inner->send($frame);
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
