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
        $this->transcript->writeMeta('host_recording_started', [
            'inner' => $inner::class,
        ]);
    }

    public function waitBatch(): ?CommandBatch
    {
        $batch = $this->inner->waitBatch();
        if ($batch === null) {
            return null;
        }
        $this->frameCounter++;
        $this->transcript->writeWireInbound($batch->messages, $batch->context, $this->frameCounter);
        return $batch;
    }

    public function send(string $frame): void
    {
        $this->transcript->writeWireOutbound($frame, $this->frameCounter);
        $this->inner->send($frame);
    }

    public function error(\Throwable $error): void
    {
        $this->transcript->writeWireError($error);
        $this->inner->error($error);
    }
}
