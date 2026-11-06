<?php

declare(strict_types=1);

namespace Temporal\Tests\Acceptance\App\Transport;

use Coresdk\Activity_task\ActivityTask;
use Coresdk\Workflow_activation\WorkflowActivation;
use Google\Protobuf\Internal\Message;
use Temporal\Testing\Transcript\TranscriptWriter;

final class RecordingCoreWire
{
    private const INBOUND = [WorkflowActivation::class, ActivityTask::class];

    private int $inboundBatchId = 0;

    private int $outboundSeq = 0;

    public function __construct(
        private readonly TranscriptWriter $transcript,
    ) {}

    /**
     * @param class-string<Message> $type
     */
    public function __invoke(string $type, string $bytes): void
    {
        $message = new $type();
        $message->mergeFromString($bytes);
        $frame = $message->serializeToJsonString();

        if (\in_array($type, self::INBOUND, true)) {
            $this->inboundBatchId++;
            $this->outboundSeq = 0;
            $this->transcript->writeWireInbound($frame, [], $this->inboundBatchId);
            return;
        }

        $this->outboundSeq++;
        $this->transcript->writeWireOutbound($frame, $this->inboundBatchId, $this->outboundSeq);
    }
}
